"""
SQLAlchemy models for the CITRA AI backend.

These mirror the Laravel migrations exactly - both halves of the platform read
and write the *same* MySQL database, so a session recorded through the Flask
WebSocket shows up immediately in the Laravel dashboard.

Schema owner: Laravel (``frontend/database/migrations``). Never call
``db.create_all()`` against a live database - run ``php artisan migrate``.
"""

from datetime import datetime

import bcrypt
from flask_sqlalchemy import SQLAlchemy

db = SQLAlchemy()


# ---------------------------------------------------------------------------
# Password interop with Laravel
# ---------------------------------------------------------------------------
# PHP's password_hash() emits the "$2y$" bcrypt prefix while Python's bcrypt
# emits "$2b$". The two are the same algorithm, so translating the prefix lets
# an account created in either half log in through the other.

def hash_password(plain: str) -> str:
    digest = bcrypt.hashpw(plain.encode('utf-8'), bcrypt.gensalt(rounds=12))
    return digest.decode('utf-8').replace('$2b$', '$2y$', 1)


def verify_password(plain: str, hashed: str) -> bool:
    if not hashed:
        return False
    normalised = hashed.replace('$2y$', '$2b$', 1)
    try:
        return bcrypt.checkpw(plain.encode('utf-8'), normalised.encode('utf-8'))
    except (ValueError, TypeError):
        return False


class User(db.Model):
    __tablename__ = 'users'

    id = db.Column(db.BigInteger, primary_key=True)
    name = db.Column(db.String(255), nullable=False)
    email = db.Column(db.String(255), unique=True, nullable=False)
    email_verified_at = db.Column(db.DateTime, nullable=True)
    password = db.Column(db.String(255), nullable=False)
    remember_token = db.Column(db.String(100), nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    progress = db.Column(db.JSON, nullable=True)
    avatar = db.Column(db.String(255), default='default-avatar.png')
    level = db.Column(db.String(255), default='Pemula')
    total_score = db.Column(db.Integer, default=0)
    practice_count = db.Column(db.Integer, default=0)
    current_streak = db.Column(db.Integer, default=0)
    longest_streak = db.Column(db.Integer, default=0)
    total_practice_seconds = db.Column(db.BigInteger, default=0)
    last_practice_at = db.Column(db.DateTime, nullable=True)
    is_admin = db.Column(db.Boolean, default=False)
    settings = db.Column(db.JSON, nullable=True)

    sessions = db.relationship('PracticeSession', backref='user', lazy=True)

    def set_password(self, plain: str) -> None:
        self.password = hash_password(plain)

    def check_password(self, plain: str) -> bool:
        return verify_password(plain, self.password)

    def to_dict(self):
        return {
            'id': self.id,
            'name': self.name,
            'email': self.email,
            'avatar': self.avatar,
            'level': self.level,
            'total_score': self.total_score or 0,
            'practice_count': self.practice_count or 0,
            'current_streak': self.current_streak or 0,
            'longest_streak': self.longest_streak or 0,
            'total_practice_seconds': int(self.total_practice_seconds or 0),
            'is_admin': bool(self.is_admin),
            'progress': self.progress or [],
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }


class PracticeSession(db.Model):
    __tablename__ = 'practice_sessions'

    id = db.Column(db.BigInteger, primary_key=True)
    user_id = db.Column(db.BigInteger, db.ForeignKey('users.id'), nullable=False)
    karakter = db.Column(db.String(50), nullable=False)
    gerakan = db.Column(db.String(100), nullable=True)
    maestro_reference_id = db.Column(db.BigInteger, nullable=True)

    wiraga_score = db.Column(db.Float, default=0.0)
    wirama_score = db.Column(db.Float, default=0.0)
    wirasa_score = db.Column(db.Float, default=0.0)
    total_score = db.Column(db.Float, default=0.0)
    grade = db.Column(db.String(4), nullable=True)

    duration = db.Column(db.Integer, default=0)
    frames_analyzed = db.Column(db.Integer, default=0)
    correct_frames = db.Column(db.Integer, default=0)
    best_streak = db.Column(db.Integer, default=0)

    feedback = db.Column(db.JSON, nullable=True)
    timeline = db.Column(db.JSON, nullable=True)
    score_series = db.Column(db.JSON, nullable=True)
    joint_scores = db.Column(db.JSON, nullable=True)
    status = db.Column(db.String(20), default='completed')
    pose_data = db.Column(db.JSON, nullable=True)

    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'user_id': self.user_id,
            'karakter': self.karakter,
            'gerakan': self.gerakan,
            'wiraga_score': self.wiraga_score,
            'wirama_score': self.wirama_score,
            'wirasa_score': self.wirasa_score,
            'total_score': self.total_score,
            'grade': self.grade,
            'duration': self.duration,
            'frames_analyzed': self.frames_analyzed,
            'correct_frames': self.correct_frames,
            'best_streak': self.best_streak,
            'accuracy': round(
                100.0 * (self.correct_frames or 0) / max(self.frames_analyzed or 0, 1), 1
            ),
            'feedback': self.feedback or [],
            'timeline': self.timeline or [],
            'score_series': self.score_series or [],
            'joint_scores': self.joint_scores or {},
            'status': self.status,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }


class MaestroReference(db.Model):
    __tablename__ = 'maestro_references'

    id = db.Column(db.BigInteger, primary_key=True)
    slug = db.Column(db.String(120), nullable=True)
    karakter = db.Column(db.String(50), nullable=False)
    gerakan_name = db.Column(db.String(100), nullable=False)
    gerakan_slug = db.Column(db.String(100), nullable=True)
    role = db.Column(db.String(20), default='maestro')

    video_path = db.Column(db.String(255), nullable=True)
    poster_path = db.Column(db.String(255), nullable=True)
    pose_keyframes = db.Column(db.JSON, nullable=True)
    keyframes_path = db.Column(db.String(255), nullable=True)
    segments = db.Column(db.JSON, nullable=True)
    duration_seconds = db.Column(db.Float, default=0.0)
    start_time = db.Column(db.Float, default=0.0)
    end_time = db.Column(db.Float, nullable=True)
    frame_count = db.Column(db.Integer, default=0)
    detection_rate = db.Column(db.Float, default=0.0)
    sample_frames = db.Column(db.JSON, nullable=True)

    audio_path = db.Column(db.String(255), nullable=True)
    beat_timestamps = db.Column(db.JSON, nullable=True)
    description = db.Column(db.Text, nullable=True)
    difficulty = db.Column(db.String(20), default='mudah')
    hitungan = db.Column(db.SmallInteger, default=8)
    tips = db.Column(db.JSON, nullable=True)
    instructions = db.Column(db.JSON, nullable=True)
    order_index = db.Column(db.SmallInteger, default=0)
    is_published = db.Column(db.Boolean, default=True)

    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    def to_dict(self, include_keyframes: bool = False):
        data = {
            'id': self.id,
            'slug': self.slug,
            'karakter': self.karakter,
            'gerakan_name': self.gerakan_name,
            'gerakan_slug': self.gerakan_slug,
            'role': self.role,
            'video_path': self.video_path,
            'poster_path': self.poster_path,
            'audio_path': self.audio_path,
            'difficulty': self.difficulty,
            'hitungan': self.hitungan,
            'description': self.description,
            'duration_seconds': self.duration_seconds,
            'start_time': self.start_time,
            'end_time': self.end_time,
            'frame_count': self.frame_count,
            'detection_rate': self.detection_rate,
            'segments': self.segments or [],
            'sample_frames': self.sample_frames or [],
            'tips': self.tips or [],
            'instructions': self.instructions or [],
            'order_index': self.order_index,
        }
        if include_keyframes:
            data['pose_keyframes'] = self.pose_keyframes or []
            data['keyframes_path'] = self.keyframes_path
        return data


class Leaderboard(db.Model):
    __tablename__ = 'leaderboard'

    id = db.Column(db.BigInteger, primary_key=True)
    user_id = db.Column(db.BigInteger, db.ForeignKey('users.id'), nullable=False)
    karakter = db.Column(db.String(50), nullable=False)
    best_score = db.Column(db.Float, default=0.0)
    rank = db.Column(db.Integer, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    user = db.relationship('User', backref='leaderboard_entries')


class GerakanProgress(db.Model):
    __tablename__ = 'gerakan_progress'

    id = db.Column(db.BigInteger, primary_key=True)
    user_id = db.Column(db.BigInteger, db.ForeignKey('users.id'), nullable=False)
    karakter = db.Column(db.String(50), nullable=False)
    gerakan = db.Column(db.String(100), nullable=False)
    best_score = db.Column(db.Float, default=0.0)
    attempts = db.Column(db.Integer, default=0)
    completed = db.Column(db.Boolean, default=False)
    completed_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)


class CitraNotification(db.Model):
    __tablename__ = 'citra_notifications'

    id = db.Column(db.BigInteger, primary_key=True)
    user_id = db.Column(db.BigInteger, db.ForeignKey('users.id'), nullable=False)
    type = db.Column(db.String(40), nullable=False)
    title = db.Column(db.String(150), nullable=False)
    message = db.Column(db.String(500), nullable=True)
    icon = db.Column(db.String(16), default='🔔')
    link = db.Column(db.String(255), nullable=True)
    read_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)


class PoseDataset(db.Model):
    __tablename__ = 'pose_datasets'

    id = db.Column(db.BigInteger, primary_key=True)
    slug = db.Column(db.String(120), unique=True, nullable=False)
    karakter = db.Column(db.String(50), nullable=False)
    title = db.Column(db.String(200), nullable=False)
    role = db.Column(db.String(20), default='maestro')
    source_video = db.Column(db.String(255), nullable=True)
    web_video = db.Column(db.String(255), nullable=True)
    poster = db.Column(db.String(255), nullable=True)
    duration_seconds = db.Column(db.Float, default=0.0)
    sample_fps = db.Column(db.Float, default=6.0)
    sampled_frames = db.Column(db.Integer, default=0)
    detected_frames = db.Column(db.Integer, default=0)
    detection_rate = db.Column(db.Float, default=0.0)
    segment_count = db.Column(db.SmallInteger, default=0)
    resolution = db.Column(db.String(20), nullable=True)
    segments = db.Column(db.JSON, nullable=True)
    frames = db.Column(db.JSON, nullable=True)
    description = db.Column(db.Text, nullable=True)
    built_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'slug': self.slug,
            'karakter': self.karakter,
            'title': self.title,
            'role': self.role,
            'web_video': self.web_video,
            'poster': self.poster,
            'duration_seconds': self.duration_seconds,
            'sample_fps': self.sample_fps,
            'sampled_frames': self.sampled_frames,
            'detected_frames': self.detected_frames,
            'detection_rate': self.detection_rate,
            'segment_count': self.segment_count,
            'resolution': self.resolution,
            'segments': self.segments or [],
            'frames': self.frames or [],
            'description': self.description,
            'built_at': self.built_at.isoformat() if self.built_at else None,
        }
