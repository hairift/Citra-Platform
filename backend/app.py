"""
CITRA AI backend - Flask + Socket.IO.

Serves real-time Wiraga / Wirama / Wirasa scoring to the Laravel front-end and
writes results into the same MySQL database Laravel uses.

Notable corrections over the first version
------------------------------------------
* Evaluation state is per-session (``SessionRegistry``) instead of one global
  evaluator shared by every connected dancer.
* JWT identities are strings - flask-jwt-extended 4.x rejects integer subjects,
  which made every authenticated route fail.
* AI modules initialise at import time, so ``gunicorn app:app`` gets them too;
  previously they only loaded under ``__main__``.
* MediaPipe graphs are not thread-safe, so estimators come from a pool with one
  instance per worker rather than a single shared object.
* Every response is passed through ``to_jsonable`` - numpy scalars used to make
  ``jsonify``/``emit`` raise.
* Maestro keyframes are streamed from disk instead of being held in a JSON
  column (the Klana reference alone is 24 MB).
"""

from __future__ import annotations

import base64
import json
import os
import queue
import threading
import traceback
from datetime import datetime, timedelta
from functools import wraps
from typing import Dict, List, Optional

import cv2
import numpy as np
from flask import Flask, g, jsonify, request, send_from_directory
from flask_cors import CORS
from flask_jwt_extended import (
    JWTManager, create_access_token, get_jwt_identity, jwt_required,
    verify_jwt_in_request,
)
from flask_socketio import SocketIO, emit, join_room, leave_room

from config import Config
from models import (
    CitraNotification, GerakanProgress, Leaderboard, MaestroReference,
    PoseDataset, PracticeSession, User, db, hash_password,
)

# ---------------------------------------------------------------------------
# App bootstrap
# ---------------------------------------------------------------------------

app = Flask(__name__)
app.config.from_object(Config)

CORS(app, resources={r'/*': {'origins': Config.CORS_ORIGINS}})
socketio = SocketIO(
    app,
    cors_allowed_origins=Config.CORS_ORIGINS,
    async_mode='eventlet',
    max_http_buffer_size=8 * 1024 * 1024,   # base64 frames are chunky
    ping_timeout=60,
    ping_interval=25,
)
jwt = JWTManager(app)
db.init_app(app)

for folder in (Config.UPLOAD_FOLDER, Config.MAESTRO_FOLDER,
               Config.RAW_VIDEO_FOLDER, Config.DATASET_FOLDER, Config.MODEL_FOLDER):
    os.makedirs(folder, exist_ok=True)


# ---------------------------------------------------------------------------
# AI module registry
# ---------------------------------------------------------------------------

class PoseEstimatorPool:
    """
    One MediaPipe graph per worker thread.

    MediaPipe's Python solutions keep internal state and are not safe to call
    concurrently; a pool avoids both the race and the cost of rebuilding a
    graph per frame.
    """

    def __init__(self, size: int = 3, **kwargs):
        self.size = size
        self.kwargs = kwargs
        self._pool: "queue.Queue" = queue.Queue()
        self._created = 0
        self._lock = threading.Lock()

    def _spawn(self):
        from ai.pose_estimator import PoseEstimator
        return PoseEstimator(**self.kwargs)

    def acquire(self):
        try:
            return self._pool.get_nowait()
        except queue.Empty:
            with self._lock:
                if self._created < self.size:
                    self._created += 1
                    return self._spawn()
            # Pool exhausted - block until a worker returns one.
            return self._pool.get(timeout=15)

    def release(self, estimator) -> None:
        self._pool.put(estimator)

    def shutdown(self) -> None:
        while True:
            try:
                self._pool.get_nowait().release()
            except queue.Empty:
                break


AI: Dict = {
    'ready': False,
    'pose_pool': None,
    'expression': None,
    'rhythm': None,
    'registry': None,
    'deep': None,
    'error': None,
}

# Cache of maestro keyframe files, keyed by reference id.
_keyframe_cache: Dict[str, Dict] = {}
_keyframe_lock = threading.Lock()


def init_ai_modules() -> None:
    """Load every AI component. Runs at import so gunicorn workers get it too."""
    if AI['ready']:
        return
    try:
        from ai.evaluation_engine import SessionRegistry
        from ai.pose_estimator import ExpressionAnalyzer
        from ai.rhythm_analyzer import RhythmAnalyzer

        AI['pose_pool'] = PoseEstimatorPool(
            size=int(os.getenv('POSE_POOL_SIZE', '3')),
            model_complexity=int(os.getenv('POSE_MODEL_COMPLEXITY', '1')),
            min_detection_confidence=0.6,
            min_tracking_confidence=0.6,
        )
        AI['expression'] = ExpressionAnalyzer()
        AI['rhythm'] = RhythmAnalyzer()
        AI['registry'] = SessionRegistry(idle_timeout=Config.SESSION_IDLE_TIMEOUT)
        AI['ready'] = True
        AI['error'] = None
        print('[citra] AI modules initialised')
    except Exception as exc:
        AI['error'] = str(exc)
        print(f'[citra] WARNING: AI modules unavailable: {exc}')
        traceback.print_exc()


def load_deep_models() -> None:
    """Deep models are optional - the geometric scorer works without them."""
    try:
        from ai.deep_models import DeepModelBundle
        bundle = DeepModelBundle(Config.MODEL_FOLDER).load()
        AI['deep'] = bundle
        print(f'[citra] deep models: {bundle.status}')
    except Exception as exc:
        print(f'[citra] deep models unavailable: {exc}')
        AI['deep'] = None


init_ai_modules()

if os.getenv('CITRA_LOAD_DEEP_MODELS', '1') in ('1', 'true', 'True'):
    threading.Thread(target=load_deep_models, daemon=True).start()


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def jsonable(payload):
    from ai.pose_utils import to_jsonable
    return to_jsonable(payload)


def ok(payload: Dict, status: int = 200):
    return jsonify(jsonable(payload)), status


def err(message: str, status: int = 400, **extra):
    return jsonify(jsonable({'error': message, **extra})), status


def current_user_id() -> Optional[int]:
    """JWT subjects are strings; convert back to the integer PK."""
    identity = get_jwt_identity()
    if identity is None:
        return None
    try:
        return int(identity)
    except (TypeError, ValueError):
        return None


def issue_token(user: User) -> str:
    return create_access_token(
        identity=str(user.id),
        expires_delta=timedelta(days=Config.JWT_ACCESS_TOKEN_EXPIRES_DAYS),
        additional_claims={'name': user.name, 'email': user.email},
    )


def require_ai(fn):
    """Return a clear 503 instead of an AttributeError when AI is missing."""
    @wraps(fn)
    def wrapper(*args, **kwargs):
        if not AI['ready']:
            return err('Modul AI belum siap di server', 503, detail=AI.get('error'))
        return fn(*args, **kwargs)
    return wrapper


def decode_image(data_url: str) -> Optional[np.ndarray]:
    """base64 data-URL (or bare base64) -> BGR frame."""
    if not data_url:
        return None
    try:
        payload = data_url.split(',', 1)[1] if ',' in data_url else data_url
        raw = base64.b64decode(payload)
        buf = np.frombuffer(raw, np.uint8)
        return cv2.imdecode(buf, cv2.IMREAD_COLOR)
    except Exception:
        return None


def process_pose(frame: np.ndarray) -> Dict:
    """Run one frame through a pooled estimator."""
    pool: PoseEstimatorPool = AI['pose_pool']
    estimator = pool.acquire()
    try:
        return estimator.process_frame(frame)
    finally:
        pool.release(estimator)


def compare_with_maestro(pose_data: Dict, maestro_pose: Dict) -> Dict:
    pool: PoseEstimatorPool = AI['pose_pool']
    estimator = pool.acquire()
    try:
        return estimator.compare_poses(
            pose_data, maestro_pose, angle_threshold=Config.ANGLE_TOLERANCE_DEG
        )
    finally:
        pool.release(estimator)


def load_keyframes(reference: MaestroReference) -> List[Dict]:
    """
    Read a reference's keyframes, preferring the on-disk file.

    The Klana keypoint dump is ~24 MB, far past what belongs in a MySQL JSON
    column, so ``keyframes_path`` points at the file and the parsed result is
    cached in memory.
    """
    if not reference:
        return []

    cache_key = f'ref:{reference.id}'
    with _keyframe_lock:
        cached = _keyframe_cache.get(cache_key)
    if cached is not None:
        return cached['keyframes']

    keyframes: List[Dict] = []
    path = reference.keyframes_path
    if path:
        full = path if os.path.isabs(path) else os.path.join(Config.MAESTRO_FOLDER, path)
        if os.path.isfile(full):
            try:
                with open(full, 'r', encoding='utf-8') as fh:
                    keyframes = json.load(fh)
            except Exception as exc:
                print(f'[citra] failed to read keyframes {full}: {exc}')

    if not keyframes and reference.pose_keyframes:
        keyframes = reference.pose_keyframes

    # Attach the precomputed feature vector once so live comparison is cheap.
    from ai.pose_utils import landmark_dicts_to_array, pose_feature_vector
    for kf in keyframes:
        if 'features' not in kf and kf.get('landmarks'):
            kf['features'] = pose_feature_vector(
                landmark_dicts_to_array(kf['landmarks'])
            ).tolist()

    with _keyframe_lock:
        _keyframe_cache[cache_key] = {'keyframes': keyframes}
    return keyframes


def maestro_features_matrix(reference: MaestroReference) -> Optional[np.ndarray]:
    """(T, 63) reference trajectory used for the end-of-session DTW score."""
    keyframes = load_keyframes(reference)
    feats = [kf['features'] for kf in keyframes if kf.get('features')]
    if len(feats) < 8:
        return None
    return np.asarray(feats, dtype=np.float32)


def keyframe_at(keyframes: List[Dict], timestamp: float) -> Optional[Dict]:
    """Nearest reference pose to a playback position."""
    if not keyframes:
        return None
    times = [float(kf.get('timestamp', 0.0)) for kf in keyframes]
    idx = int(np.argmin(np.abs(np.array(times) - float(timestamp))))
    kf = keyframes[idx]
    return {
        'timestamp': kf.get('timestamp'),
        'angles': kf.get('angles', {}),
        'landmarks': kf.get('landmarks', []),
        'features': kf.get('features'),
        'orientation': kf.get('orientation', {}),
    }


def notify(user_id: int, ntype: str, title: str, message: str = '',
           icon: str = '🔔', link: Optional[str] = None) -> None:
    """Best-effort in-app notification; never breaks the caller."""
    try:
        db.session.add(CitraNotification(
            user_id=user_id, type=ntype, title=title,
            message=message[:500], icon=icon, link=link,
        ))
    except Exception as exc:
        print(f'[citra] notification failed: {exc}')


# ---------------------------------------------------------------------------
# Auth
# ---------------------------------------------------------------------------

@app.route('/api/register', methods=['POST'])
def register():
    data = request.get_json(silent=True) or {}
    name = (data.get('name') or '').strip()
    email = (data.get('email') or '').strip().lower()
    password = data.get('password') or ''

    if not name or not email or not password:
        return err('Nama, email, dan password harus diisi')
    if len(password) < 8:
        return err('Password minimal 8 karakter')
    if '@' not in email:
        return err('Format email tidak valid')

    if User.query.filter_by(email=email).first():
        return err('Email sudah terdaftar')

    user = User(
        name=name,
        email=email,
        password=hash_password(password),
        avatar='default-avatar.png',
        level='Pemula',
        total_score=0,
        practice_count=0,
    )
    db.session.add(user)
    db.session.commit()

    return ok({
        'message': 'Registrasi berhasil',
        'user': user.to_dict(),
        'access_token': issue_token(user),
    }, 201)


@app.route('/api/login', methods=['POST'])
def login():
    data = request.get_json(silent=True) or {}
    email = (data.get('email') or '').strip().lower()
    password = data.get('password') or ''

    if not email or not password:
        return err('Email dan password harus diisi')

    user = User.query.filter_by(email=email).first()
    if not user or not user.check_password(password):
        return err('Email atau password salah', 401)

    return ok({
        'message': 'Login berhasil',
        'user': user.to_dict(),
        'access_token': issue_token(user),
    })


@app.route('/api/user', methods=['GET'])
@jwt_required()
def get_user():
    user = User.query.get(current_user_id())
    if not user:
        return err('User tidak ditemukan', 404)
    return ok({'user': user.to_dict()})


@app.route('/api/user', methods=['PUT'])
@jwt_required()
def update_user():
    user = User.query.get(current_user_id())
    if not user:
        return err('User tidak ditemukan', 404)

    data = request.get_json(silent=True) or {}
    if data.get('name'):
        user.name = str(data['name']).strip()[:255]
    if data.get('avatar'):
        user.avatar = str(data['avatar'])[:255]
    if isinstance(data.get('settings'), dict):
        user.settings = data['settings']

    db.session.commit()
    return ok({'user': user.to_dict()})


# ---------------------------------------------------------------------------
# Practice sessions
# ---------------------------------------------------------------------------

@app.route('/api/practice/start', methods=['POST'])
@jwt_required()
@require_ai
def start_practice():
    user_id = current_user_id()
    data = request.get_json(silent=True) or {}

    karakter = (data.get('karakter') or 'klana').lower()
    if karakter not in ('panji', 'samba', 'rumyang', 'tumenggung', 'klana'):
        return err('Karakter tidak dikenal')

    gerakan = data.get('gerakan')
    reference_id = data.get('maestro_reference_id')

    reference = None
    if reference_id:
        reference = MaestroReference.query.get(reference_id)

    if reference is None:
        # Only references whose pose dataset has been extracted can be scored
        # against. The curriculum rows seeded from config/citra.php exist so the
        # tutorial shows a full syllabus, but they carry no keyframes - picking
        # one would silently start a session with nothing to compare against.
        scorable = MaestroReference.query.filter(
            MaestroReference.karakter == karakter,
            MaestroReference.role == 'maestro',
            MaestroReference.keyframes_path.isnot(None),
        )
        if gerakan:
            reference = scorable.filter_by(gerakan_slug=gerakan).first()
        if reference is None:
            reference = scorable.order_by(MaestroReference.order_index).first()
        if reference is None:
            reference = (MaestroReference.query
                         .filter(MaestroReference.keyframes_path.isnot(None))
                         .order_by(MaestroReference.order_index)
                         .first())

    session_id = f'{user_id}-{int(datetime.utcnow().timestamp() * 1000)}'
    AI['registry'].create(
        session_id=session_id,
        user_id=user_id,
        karakter=karakter,
        gerakan=gerakan,
        correct_threshold=Config.POSE_CORRECT_THRESHOLD,
    )

    keyframes = load_keyframes(reference) if reference else []

    return ok({
        'session_id': session_id,
        'karakter': karakter,
        'gerakan': gerakan,
        'maestro': reference.to_dict() if reference else None,
        'keyframe_count': len(keyframes),
        'message': 'Sesi latihan dimulai',
    })


@app.route('/api/practice/end', methods=['POST'])
@jwt_required()
@require_ai
def end_practice():
    user_id = current_user_id()
    data = request.get_json(silent=True) or {}
    session_id = data.get('session_id')

    evaluator = AI['registry'].pop(session_id) if session_id else None
    if evaluator is None:
        return err('Sesi tidak ditemukan atau sudah berakhir', 404)
    if evaluator.user_id != user_id:
        return err('Sesi ini bukan milik Anda', 403)

    reference = None
    if data.get('maestro_reference_id'):
        reference = MaestroReference.query.get(data['maestro_reference_id'])
    features = maestro_features_matrix(reference) if reference else None

    result = evaluator.result(maestro_features=features)
    scores = result['scores']

    # Mirror the Laravel guard: a session too short to be a real attempt is
    # discarded rather than allowed to pollute the stats and leaderboard.
    min_seconds = int(os.getenv('MIN_SESSION_SECONDS', '10'))
    if result['duration'] < min_seconds:
        return ok({
            'discarded': True,
            'message': f'Sesi terlalu singkat (minimal {min_seconds} detik) dan tidak disimpan.',
            'result': result,
        }, 422)

    user = User.query.get(user_id)
    if not user:
        return err('User tidak ditemukan', 404)

    session = PracticeSession(
        user_id=user_id,
        karakter=evaluator.karakter,
        gerakan=evaluator.gerakan,
        maestro_reference_id=reference.id if reference else None,
        wiraga_score=scores['wiraga'],
        wirama_score=scores['wirama'],
        wirasa_score=scores['wirasa'],
        total_score=scores['total'],
        grade=result['grade'],
        duration=result['duration'],
        frames_analyzed=result['frames_analyzed'],
        correct_frames=result['correct_frames'],
        best_streak=result['best_streak'],
        feedback=result['feedback'],
        timeline=result['timeline'],
        score_series=result['score_series'],
        joint_scores=result['joint_scores'],
        status='completed',
    )
    db.session.add(session)
    db.session.flush()   # need session.id before we commit

    _apply_session_to_user(user, session)
    _update_leaderboard(user_id, evaluator.karakter, scores['total'])
    _update_gerakan_progress(user_id, evaluator.karakter, evaluator.gerakan, scores['total'])

    db.session.commit()

    result['practice_id'] = session.id
    result['user'] = user.to_dict()
    return ok({'message': 'Sesi selesai', 'result': result, 'practice_id': session.id})


def _apply_session_to_user(user: User, session: PracticeSession) -> None:
    """Roll a finished session into the user's aggregate stats and streak."""
    user.practice_count = (user.practice_count or 0) + 1
    user.total_score = (user.total_score or 0) + int(round(session.total_score or 0))
    user.total_practice_seconds = (user.total_practice_seconds or 0) + int(session.duration or 0)

    today = datetime.utcnow().date()
    last = user.last_practice_at.date() if user.last_practice_at else None
    if last == today:
        pass                                   # already counted today
    elif last == today - timedelta(days=1):
        user.current_streak = (user.current_streak or 0) + 1
    else:
        user.current_streak = 1
    user.longest_streak = max(user.longest_streak or 0, user.current_streak or 0)
    user.last_practice_at = datetime.utcnow()

    from ai.evaluation_engine import ScoreCalculator
    new_level = ScoreCalculator.calculate_level(user.total_score, user.practice_count)
    if new_level != user.level:
        old = user.level
        user.level = new_level
        notify(user.id, 'system', f'Naik level: {new_level}!',
               f'Selamat, level Anda naik dari {old} menjadi {new_level}.',
               icon='🎖️', link='/profile')


def _update_leaderboard(user_id: int, karakter: str, score: float) -> None:
    entry = Leaderboard.query.filter_by(user_id=user_id, karakter=karakter).first()
    improved = False

    if entry is None:
        entry = Leaderboard(user_id=user_id, karakter=karakter, best_score=score)
        db.session.add(entry)
        improved = True
    elif score > (entry.best_score or 0):
        entry.best_score = score
        improved = True

    if not improved:
        return

    db.session.flush()
    # Re-rank this character's board.
    rows = (Leaderboard.query
            .filter_by(karakter=karakter)
            .order_by(Leaderboard.best_score.desc(), Leaderboard.updated_at.asc())
            .all())
    for idx, row in enumerate(rows, start=1):
        row.rank = idx
        if row.user_id == user_id and idx <= 10:
            notify(user_id, 'leaderboard', f'Peringkat #{idx} di {karakter.title()}!',
                   f'Skor terbaik Anda {score:.1f} menempatkan Anda di peringkat {idx}.',
                   icon='🏆', link='/leaderboard')


def _update_gerakan_progress(user_id: int, karakter: str,
                             gerakan: Optional[str], score: float) -> None:
    if not gerakan:
        return
    row = GerakanProgress.query.filter_by(
        user_id=user_id, karakter=karakter, gerakan=gerakan
    ).first()
    if row is None:
        row = GerakanProgress(user_id=user_id, karakter=karakter, gerakan=gerakan)
        db.session.add(row)

    row.attempts = (row.attempts or 0) + 1
    if score > (row.best_score or 0):
        row.best_score = score
    if score >= 75 and not row.completed:
        row.completed = True
        row.completed_at = datetime.utcnow()


@app.route('/api/practice/history', methods=['GET'])
@jwt_required()
def get_practice_history():
    user_id = current_user_id()
    limit = min(request.args.get('limit', 20, type=int), 100)
    karakter = request.args.get('karakter')

    query = PracticeSession.query.filter_by(user_id=user_id)
    if karakter:
        query = query.filter_by(karakter=karakter)

    sessions = query.order_by(PracticeSession.created_at.desc()).limit(limit).all()
    return ok({'sessions': [s.to_dict() for s in sessions]})


@app.route('/api/practice/<int:session_id>', methods=['GET'])
@jwt_required()
def get_practice_detail(session_id: int):
    session = PracticeSession.query.filter_by(
        id=session_id, user_id=current_user_id()
    ).first()
    if not session:
        return err('Sesi tidak ditemukan', 404)
    return ok({'session': session.to_dict()})


@app.route('/api/practice/stats', methods=['GET'])
@jwt_required()
def get_practice_stats():
    user_id = current_user_id()
    sessions = PracticeSession.query.filter_by(user_id=user_id).all()
    data = [s.to_dict() for s in sessions]

    from ai.evaluation_engine import ScoreCalculator
    current_streak, longest_streak = ScoreCalculator.calculate_streak(
        [s['created_at'] for s in data if s.get('created_at')]
    )

    return ok({
        'progress': ScoreCalculator.calculate_progress(data),
        'daily_stats': ScoreCalculator.calculate_daily_stats(data),
        'character_mastery': ScoreCalculator.get_character_mastery(data),
        'streak': {'current': current_streak, 'longest': longest_streak},
    })


# ---------------------------------------------------------------------------
# Maestro references & dataset
# ---------------------------------------------------------------------------

@app.route('/api/maestro', methods=['GET'])
def get_maestro_list():
    query = MaestroReference.query.filter_by(is_published=True)
    if request.args.get('karakter'):
        query = query.filter_by(karakter=request.args['karakter'])
    if request.args.get('role'):
        query = query.filter_by(role=request.args['role'])

    refs = query.order_by(MaestroReference.karakter, MaestroReference.order_index).all()
    return ok({'references': [r.to_dict() for r in refs]})


@app.route('/api/maestro/<int:maestro_id>', methods=['GET'])
def get_maestro_detail(maestro_id: int):
    reference = MaestroReference.query.get(maestro_id)
    if not reference:
        return err('Referensi tidak ditemukan', 404)
    return ok({'reference': reference.to_dict()})


@app.route('/api/maestro/<int:maestro_id>/keyframes', methods=['GET'])
def get_maestro_keyframes(maestro_id: int):
    """
    Reference poses for a video.

    ``?t=12.5`` returns just the nearest keyframe (what the live comparison
    needs); without it a decimated list is returned so the browser is not asked
    to swallow tens of megabytes.
    """
    reference = MaestroReference.query.get(maestro_id)
    if not reference:
        return err('Referensi tidak ditemukan', 404)

    keyframes = load_keyframes(reference)
    if not keyframes:
        return ok({'keyframes': [], 'count': 0})

    if request.args.get('t') is not None:
        t = request.args.get('t', type=float) or 0.0
        return ok({'keyframe': keyframe_at(keyframes, t), 'count': len(keyframes)})

    limit = min(request.args.get('limit', 240, type=int), 1200)
    stride = max(1, len(keyframes) // limit)
    slim = [
        {'timestamp': kf.get('timestamp'), 'angles': kf.get('angles', {}),
         'landmarks': kf.get('landmarks', [])}
        for kf in keyframes[::stride]
    ]
    return ok({'keyframes': slim, 'count': len(keyframes), 'stride': stride})


@app.route('/api/dataset', methods=['GET'])
def get_datasets():
    query = PoseDataset.query
    if request.args.get('karakter'):
        query = query.filter_by(karakter=request.args['karakter'])
    datasets = query.order_by(PoseDataset.karakter, PoseDataset.slug).all()
    return ok({'datasets': [d.to_dict() for d in datasets]})


@app.route('/api/dataset/<slug>/segments', methods=['GET'])
def get_dataset_segments(slug: str):
    dataset = PoseDataset.query.filter_by(slug=slug).first()
    if not dataset:
        return err('Dataset tidak ditemukan', 404)
    return ok({'slug': slug, 'segments': dataset.segments or []})


@app.route('/api/maestro/upload', methods=['POST'])
@jwt_required()
@require_ai
def upload_maestro():
    """Upload a new maestro video. Admin only."""
    user = User.query.get(current_user_id())
    if not user or not user.is_admin:
        return err('Hanya admin yang dapat mengunggah referensi maestro', 403)

    if 'video' not in request.files:
        return err('File video wajib diunggah')

    video = request.files['video']
    if not video.filename:
        return err('Nama file video kosong')

    ext = video.filename.rsplit('.', 1)[-1].lower() if '.' in video.filename else ''
    if ext not in Config.ALLOWED_VIDEO_EXTENSIONS:
        return err(f'Format video tidak didukung: .{ext}')

    karakter = (request.form.get('karakter') or 'klana').lower()
    gerakan_name = request.form.get('gerakan_name') or 'Gerakan Baru'
    slug = f'{karakter}_{int(datetime.utcnow().timestamp())}'
    filename = f'{slug}.{ext}'
    path = os.path.join(Config.RAW_VIDEO_FOLDER, filename)
    video.save(path)

    reference = MaestroReference(
        slug=slug,
        karakter=karakter,
        gerakan_name=gerakan_name,
        gerakan_slug=request.form.get('gerakan_slug'),
        role=request.form.get('role', 'maestro'),
        video_path=filename,
        description=request.form.get('description', ''),
        difficulty=request.form.get('difficulty', 'menengah'),
        is_published=False,   # published once build_dataset.py has run
    )
    db.session.add(reference)
    db.session.commit()

    return ok({
        'message': 'Video tersimpan. Jalankan '
                   '"python build_dataset.py --force" untuk mengekstrak dataset pose.',
        'reference': reference.to_dict(),
        'raw_path': filename,
    }, 201)


# ---------------------------------------------------------------------------
# Leaderboard & notifications
# ---------------------------------------------------------------------------

@app.route('/api/leaderboard', methods=['GET'])
def get_leaderboard():
    karakter = request.args.get('karakter')
    limit = min(request.args.get('limit', 10, type=int), 100)

    if not karakter or karakter == 'all':
        users = (User.query
                 .order_by(User.total_score.desc(), User.practice_count.desc())
                 .limit(limit).all())
        return ok({'leaderboard': [
            {'rank': i, 'user_id': u.id, 'name': u.name, 'avatar': u.avatar,
             'level': u.level, 'karakter': 'all', 'best_score': u.total_score,
             'practice_count': u.practice_count}
            for i, u in enumerate(users, start=1)
        ]})

    rows = (db.session.query(Leaderboard, User)
            .join(User, Leaderboard.user_id == User.id)
            .filter(Leaderboard.karakter == karakter)
            .order_by(Leaderboard.best_score.desc())
            .limit(limit).all())

    return ok({'leaderboard': [
        {'rank': i, 'user_id': u.id, 'name': u.name, 'avatar': u.avatar,
         'level': u.level, 'karakter': entry.karakter,
         'best_score': round(entry.best_score or 0, 1)}
        for i, (entry, u) in enumerate(rows, start=1)
    ]})


@app.route('/api/notifications', methods=['GET'])
@jwt_required()
def list_notifications():
    rows = (CitraNotification.query
            .filter_by(user_id=current_user_id())
            .order_by(CitraNotification.created_at.desc())
            .limit(30).all())
    return ok({'notifications': [
        {'id': n.id, 'type': n.type, 'title': n.title, 'message': n.message,
         'icon': n.icon, 'link': n.link,
         'read': n.read_at is not None,
         'created_at': n.created_at.isoformat() if n.created_at else None}
        for n in rows
    ]})


@app.route('/api/notifications/read', methods=['POST'])
@jwt_required()
def mark_notifications_read():
    (CitraNotification.query
     .filter_by(user_id=current_user_id(), read_at=None)
     .update({'read_at': datetime.utcnow()}))
    db.session.commit()
    return ok({'message': 'Semua notifikasi ditandai terbaca'})


# ---------------------------------------------------------------------------
# Stateless analysis endpoints
# ---------------------------------------------------------------------------

@app.route('/api/analyze/pose', methods=['POST'])
@require_ai
def analyze_pose():
    data = request.get_json(silent=True) or {}
    frame = decode_image(data.get('image', ''))
    if frame is None:
        return err('Data gambar tidak valid')

    pose_data = process_pose(frame)
    comparison = None
    if data.get('maestro_pose'):
        comparison = compare_with_maestro(pose_data, data['maestro_pose'])

    expression = AI['expression'].analyze(pose_data, data.get('karakter', 'klana'))

    return ok({'pose': pose_data, 'comparison': comparison, 'expression': expression})


@app.route('/api/analyze/audio', methods=['POST'])
@require_ai
def analyze_audio():
    if 'audio' not in request.files:
        return err('File audio wajib diunggah')

    audio = request.files['audio']
    ext = audio.filename.rsplit('.', 1)[-1].lower() if '.' in (audio.filename or '') else 'wav'
    if ext not in Config.ALLOWED_AUDIO_EXTENSIONS:
        return err(f'Format audio tidak didukung: .{ext}')

    temp_path = os.path.join(
        Config.UPLOAD_FOLDER, f'tmp_{int(datetime.utcnow().timestamp()*1000)}.{ext}'
    )
    audio.save(temp_path)
    try:
        y, _ = AI['rhythm'].load_audio(temp_path)
        analysis = AI['rhythm'].analyze_audio(y)

        karakter = request.form.get('karakter')
        if karakter:
            analysis['tempo_feedback'] = AI['rhythm'].get_tempo_feedback(
                analysis['tempo'], karakter
            )

        if request.form.get('structural') in ('1', 'true'):
            from ai.rhythm_analyzer import GamelanBeatDetector
            analysis['structural'] = GamelanBeatDetector().get_structural_beats(y)

        return ok({'analysis': analysis})
    except Exception as exc:
        traceback.print_exc()
        return err(f'Gagal menganalisis audio: {exc}', 500)
    finally:
        if os.path.exists(temp_path):
            try:
                os.remove(temp_path)
            except OSError:
                pass


@app.route('/api/analyze/sequence', methods=['POST'])
@require_ai
def analyze_sequence():
    """Classify a movement window with the trained Bi-LSTM."""
    bundle = AI.get('deep')
    if not bundle or not bundle.classifier:
        return err('Model klasifikasi gerakan belum tersedia. '
                   'Jalankan: python train_models.py', 503)

    data = request.get_json(silent=True) or {}
    features = data.get('features')
    if not features:
        return err('Field "features" (window x 63) wajib diisi')

    window = np.asarray(features, dtype=np.float32)
    if window.ndim != 2 or window.shape[1] != 63:
        return err(f'Bentuk features harus (window, 63), diterima {list(window.shape)}')

    from ai.deep_models import WINDOW
    from ai.pose_utils import resample_sequence
    if window.shape[0] != WINDOW:
        window = resample_sequence(window, WINDOW)

    response = {'classification': bundle.classifier.predict(window)}
    if bundle.autoencoder:
        response['quality'] = bundle.autoencoder.score(window)
    if bundle.tempo:
        response['tempo_estimate'] = round(bundle.tempo.predict(window), 5)
    return ok(response)


# ---------------------------------------------------------------------------
# Health / status
# ---------------------------------------------------------------------------

@app.route('/api/health', methods=['GET'])
def health():
    db_ok, db_error = True, None
    try:
        from sqlalchemy import text
        db.session.execute(text('SELECT 1'))
    except Exception as exc:
        db_ok, db_error = False, str(exc)

    bundle = AI.get('deep')
    return ok({
        'status': 'ok' if (AI['ready'] and db_ok) else 'degraded',
        'ai_ready': AI['ready'],
        'ai_error': AI.get('error'),
        'database': {'connected': db_ok, 'error': db_error},
        'deep_models': bundle.status if bundle else {'loaded': False},
        'active_sessions': AI['registry'].count() if AI['registry'] else 0,
        'time': datetime.utcnow().isoformat(),
    })


@app.route('/api/maestro/video/<path:filename>', methods=['GET'])
def serve_maestro_video(filename: str):
    return send_from_directory(Config.RAW_VIDEO_FOLDER, filename, conditional=True)


# ---------------------------------------------------------------------------
# WebSocket
# ---------------------------------------------------------------------------

# socket sid -> {'session_id', 'user_id', 'reference_id', 'keyframes'}
_socket_state: Dict[str, Dict] = {}
_socket_lock = threading.Lock()


def _user_id_from_token(token: Optional[str]) -> Optional[int]:
    """
    Verify a JWT minted by either half of the platform.

    Laravel signs its tokens with the same JWT_SECRET_KEY, so a user who is
    already signed in on the web UI can open a socket without logging in again.
    """
    if not token:
        return None
    try:
        import jwt as pyjwt
        payload = pyjwt.decode(
            token.replace('Bearer ', '', 1),
            Config.JWT_SECRET_KEY,
            algorithms=['HS256'],
        )
        sub = payload.get('sub')
        return int(sub) if sub is not None else None
    except Exception as exc:
        print(f'[socket] token rejected: {exc}')
        return None


@socketio.on('connect')
def handle_connect(auth=None):
    user_id = _user_id_from_token((auth or {}).get('token') if isinstance(auth, dict) else None)

    with _socket_lock:
        _socket_state[request.sid] = {
            'session_id': None, 'user_id': user_id,
            'reference_id': None, 'keyframes': [],
        }

    print(f'[socket] connected: {request.sid} (user={user_id})')
    emit('connected', {
        'status': 'connected',
        'sid': request.sid,
        'ai_ready': AI['ready'],
        'authenticated': user_id is not None,
    })


@socketio.on('disconnect')
def handle_disconnect(reason=None):
    with _socket_lock:
        _socket_state.pop(request.sid, None)
    print(f'[socket] disconnected: {request.sid}')


@socketio.on('join_session')
def handle_join_session(data):
    """
    Bind this socket to a practice session.

    The reference keyframes are resolved once here instead of per frame - the
    Klana reference has 943 of them and re-reading it 12 times a second would
    be ruinous.
    """
    session_id = (data or {}).get('session_id')
    if not session_id:
        emit('session_joined', {'error': 'session_id wajib diisi'})
        return

    evaluator = AI['registry'].get(session_id) if AI['registry'] else None
    if evaluator is None:
        emit('session_joined', {'error': 'Sesi tidak ditemukan. Mulai sesi terlebih dahulu.'})
        return

    # If the socket presented a token, it must belong to the session's owner -
    # otherwise anyone who guessed a session id could feed it frames.
    with _socket_lock:
        socket_user = (_socket_state.get(request.sid) or {}).get('user_id')
    if socket_user is not None and socket_user != evaluator.user_id:
        emit('session_joined', {'error': 'Sesi ini bukan milik Anda.'})
        return

    keyframes: List[Dict] = []
    reference_id = (data or {}).get('maestro_reference_id')
    if reference_id:
        reference = MaestroReference.query.get(reference_id)
        if reference:
            keyframes = load_keyframes(reference)

    with _socket_lock:
        _socket_state[request.sid] = {
            'session_id': session_id,
            'user_id': evaluator.user_id,
            'reference_id': reference_id,
            'keyframes': keyframes,
        }

    join_room(session_id)
    emit('session_joined', {
        'session_id': session_id,
        'karakter': evaluator.karakter,
        'keyframe_count': len(keyframes),
    })


@socketio.on('leave_session')
def handle_leave_session(data):
    session_id = (data or {}).get('session_id')
    if session_id:
        leave_room(session_id)
    with _socket_lock:
        _socket_state.pop(request.sid, None)
    emit('session_left', {'session_id': session_id})


@socketio.on('pose_frame')
def handle_pose_frame(data):
    """
    Score one live frame.

    The client may send either a JPEG (server-side MediaPipe) or landmarks it
    already computed in the browser. Sending landmarks is far cheaper and is
    what practice.blade.php does.
    """
    if not AI['ready']:
        emit('pose_result', {'error': 'Modul AI belum siap'})
        return

    data = data or {}
    with _socket_lock:
        state = _socket_state.get(request.sid)

    session_id = data.get('session_id') or (state or {}).get('session_id')
    evaluator = AI['registry'].get(session_id) if session_id else None

    try:
        pose_data = _pose_from_payload(data)
        if pose_data is None:
            emit('pose_result', {'error': 'Frame tidak valid'})
            return

        if not pose_data.get('detected'):
            emit('pose_result', {
                'detected': False,
                'message': 'Pose tidak terdeteksi - pastikan seluruh badan terlihat',
            })
            return

        karakter = data.get('karakter') or (evaluator.karakter if evaluator else 'klana')

        # Reference pose at the current playback position.
        maestro_pose = data.get('maestro_pose')
        if maestro_pose is None and state and state.get('keyframes'):
            maestro_pose = keyframe_at(state['keyframes'], float(data.get('video_time') or 0.0))

        comparison = None
        if maestro_pose:
            comparison = compare_with_maestro(pose_data, maestro_pose)

        expression = AI['expression'].analyze(pose_data, karakter)

        feedback = {}
        if evaluator:
            if comparison:
                evaluator.update_wiraga(comparison, pose_data)
            evaluator.update_wirasa(expression)
            feedback = evaluator.live_feedback()

        emit('pose_result', jsonable({
            'detected': True,
            'pose': {
                'angles': pose_data['angles'],
                'confidence': pose_data['confidence'],
                'orientation': pose_data['body_orientation'],
                'head': pose_data['head_orientation'],
            },
            'comparison': comparison,
            'expression': expression,
            'feedback': feedback,
            'timestamp': datetime.utcnow().isoformat(),
        }))

    except Exception as exc:
        traceback.print_exc()
        emit('pose_result', {'error': str(exc)})


def _pose_from_payload(data: Dict) -> Optional[Dict]:
    """Build a pose dict from browser landmarks, or fall back to the image."""
    from ai.pose_utils import (
        array_to_landmark_dicts, body_orientation, compute_joint_angles,
        head_orientation_from_pose, pose_feature_vector, pose_visibility,
    )

    landmarks = data.get('landmarks')
    if landmarks and len(landmarks) >= 33:
        arr = np.zeros((33, 4), dtype=np.float32)
        for i, lm in enumerate(landmarks[:33]):
            arr[i] = (
                float(lm.get('x', 0.0)), float(lm.get('y', 0.0)),
                float(lm.get('z', 0.0)), float(lm.get('visibility', 1.0)),
            )
        return {
            'detected': True,
            'pose_landmarks': array_to_landmark_dicts(arr),
            'angles': compute_joint_angles(arr),
            'body_orientation': body_orientation(arr),
            'head_orientation': head_orientation_from_pose(arr),
            'confidence': round(pose_visibility(arr), 4),
            'features': pose_feature_vector(arr).tolist(),
            'face_landmarks': data.get('faceLandmarks'),
            'hand_landmarks': [],
        }

    frame = decode_image(data.get('image', ''))
    if frame is None:
        return None
    return process_pose(frame)


@socketio.on('audio_chunk')
def handle_audio_chunk(data):
    """Beat detection over a streamed audio chunk."""
    if not AI['ready']:
        emit('audio_result', {'error': 'Modul AI belum siap'})
        return

    data = data or {}
    try:
        samples = np.asarray(data.get('samples') or [], dtype=np.float32)
        timestamp = data.get('timestamp')
        result = AI['rhythm'].analyze_realtime_chunk(
            samples, float(timestamp) if timestamp is not None else None
        )

        with _socket_lock:
            state = _socket_state.get(request.sid)
        session_id = data.get('session_id') or (state or {}).get('session_id')
        evaluator = AI['registry'].get(session_id) if session_id else None

        if evaluator and result['beat_detected']:
            evaluator.register_beat(timestamp)

        emit('audio_result', jsonable({
            **result,
            'timestamp': datetime.utcnow().isoformat(),
        }))
    except Exception as exc:
        traceback.print_exc()
        emit('audio_result', {'error': str(exc)})


@socketio.on('movement_accent')
def handle_movement_accent(data):
    """Client-detected movement peak, used for Wirama synchronisation."""
    data = data or {}
    with _socket_lock:
        state = _socket_state.get(request.sid)
    session_id = data.get('session_id') or (state or {}).get('session_id')
    evaluator = AI['registry'].get(session_id) if session_id and AI['registry'] else None
    if evaluator:
        evaluator.register_movement_accent(data.get('timestamp'))


@socketio.on('request_feedback')
def handle_request_feedback(data):
    data = data or {}
    with _socket_lock:
        state = _socket_state.get(request.sid)
    session_id = data.get('session_id') or (state or {}).get('session_id')
    evaluator = AI['registry'].get(session_id) if session_id and AI['registry'] else None
    emit('feedback_update', jsonable(evaluator.live_feedback() if evaluator else {}))


# ---------------------------------------------------------------------------
# Error handling
# ---------------------------------------------------------------------------

@app.errorhandler(404)
def handle_404(_):
    return err('Endpoint tidak ditemukan', 404)


@app.errorhandler(413)
def handle_413(_):
    return err('File terlalu besar', 413)


@app.errorhandler(500)
def handle_500(exc):
    traceback.print_exc()
    return err('Terjadi kesalahan pada server', 500, detail=str(exc))


@jwt.expired_token_loader
def handle_expired_token(_header, _payload):
    return err('Sesi login sudah berakhir, silakan masuk kembali', 401)


@jwt.invalid_token_loader
def handle_invalid_token(reason):
    return err(f'Token tidak valid: {reason}', 401)


@jwt.unauthorized_loader
def handle_missing_token(reason):
    return err('Autentikasi diperlukan', 401, detail=reason)


# ---------------------------------------------------------------------------
# Entrypoint
# ---------------------------------------------------------------------------

def verify_schema() -> bool:
    """
    Confirm the Laravel-owned schema is present.

    We deliberately do NOT call ``db.create_all()`` - Laravel migrations own the
    schema, and letting SQLAlchemy create half-matching tables behind their back
    is how the two halves drift apart.
    """
    from sqlalchemy import inspect
    try:
        inspector = inspect(db.engine)
        tables = set(inspector.get_table_names())
    except Exception as exc:
        print(f'[citra] cannot reach the database: {exc}')
        print('[citra] check backend/.env (DB_HOST/DB_PORT) and that MySQL is running.')
        return False

    required = {'users', 'practice_sessions', 'leaderboard', 'maestro_references'}
    missing = required - tables
    if missing:
        print(f'[citra] missing tables: {sorted(missing)}')
        print('[citra] run: cd ../frontend && php artisan migrate --seed')
        return False

    print(f'[citra] database OK ({len(tables)} tables)')
    return True


if __name__ == '__main__':
    with app.app_context():
        verify_schema()

    print(f'[citra] starting on {Config.HOST}:{Config.PORT} (debug={Config.DEBUG})')
    socketio.run(
        app,
        host=Config.HOST,
        port=Config.PORT,
        debug=Config.DEBUG,
        use_reloader=False,   # the reloader would rebuild every MediaPipe graph
        allow_unsafe_werkzeug=True,
    )
