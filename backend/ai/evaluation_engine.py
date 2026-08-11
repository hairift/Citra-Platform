"""
Central scoring engine: Wiraga + Wirama + Wirasa -> one grade.

Key change from the original implementation
-------------------------------------------
Evaluation state used to live on a single module-level ``PerformanceEvaluator``
shared by every request, so two dancers practising at the same time overwrote
each other's scores. State now lives in :class:`SessionEvaluator`, one instance
per practice session, owned by :class:`SessionRegistry`.
"""

from __future__ import annotations

import threading
from collections import deque
from datetime import datetime, timedelta
from typing import Deque, Dict, List, Optional, Tuple

import numpy as np

from .pose_utils import (
    ANGLE_LABELS_ID,
    dtw_distance,
    landmark_dicts_to_array,
    pose_feature_vector,
    resample_sequence,
)

# ---------------------------------------------------------------------------

SCORE_WEIGHTS = {'wiraga': 0.45, 'wirama': 0.30, 'wirasa': 0.25}

CHARACTER_FOCUS: Dict[str, Dict[str, float]] = {
    'panji':      {'wiraga': 0.40, 'wirama': 0.30, 'wirasa': 0.30},
    'samba':      {'wiraga': 0.50, 'wirama': 0.25, 'wirasa': 0.25},
    'rumyang':    {'wiraga': 0.35, 'wirama': 0.35, 'wirasa': 0.30},
    'tumenggung': {'wiraga': 0.50, 'wirama': 0.30, 'wirasa': 0.20},
    'klana':      {'wiraga': 0.45, 'wirama': 0.35, 'wirasa': 0.20},
}

# Ordered high -> low; the first threshold met wins.
GRADE_THRESHOLDS: List[Tuple[str, float]] = [
    ('A+', 95), ('A', 90), ('A-', 85),
    ('B+', 80), ('B', 75), ('B-', 70),
    ('C+', 65), ('C', 60), ('C-', 55),
    ('D', 50), ('E', 0),
]

LEVEL_TIERS: List[Tuple[str, int, int]] = [
    ('Master', 50, 6000),
    ('Mahir', 30, 3000),
    ('Menengah', 15, 1000),
    ('Dasar', 5, 0),
    ('Pemula', 0, 0),
]


def grade_for(score: float) -> str:
    for grade, threshold in GRADE_THRESHOLDS:
        if score >= threshold:
            return grade
    return 'E'


# ---------------------------------------------------------------------------

class SessionEvaluator:
    """
    Accumulates one dancer's performance for the length of one session.

    Thread-safe: the WebSocket handler and the REST "end session" call can both
    touch it, so every mutation is guarded.
    """

    #: Rolling window used for the live on-screen score (~5 s at 12 fps).
    LIVE_WINDOW = 60

    def __init__(
        self,
        session_id: str,
        user_id: int,
        karakter: str = 'klana',
        gerakan: Optional[str] = None,
        correct_threshold: float = 70.0,
    ):
        self.session_id = session_id
        self.user_id = user_id
        self.karakter = karakter
        self.gerakan = gerakan
        self.correct_threshold = correct_threshold
        self.weights = CHARACTER_FOCUS.get(karakter, SCORE_WEIGHTS)

        self.start_time = datetime.utcnow()
        self.last_activity = datetime.utcnow()

        self._lock = threading.Lock()

        self.wiraga_scores: List[float] = []
        self.wirama_scores: List[float] = []
        self.wirasa_scores: List[float] = []

        self._live_wiraga: Deque[float] = deque(maxlen=self.LIVE_WINDOW)
        self._live_wirama: Deque[float] = deque(maxlen=self.LIVE_WINDOW)
        self._live_wirasa: Deque[float] = deque(maxlen=self.LIVE_WINDOW)

        # Per-joint running accuracy -> improvement-areas panel
        self.joint_totals: Dict[str, float] = {}
        self.joint_counts: Dict[str, int] = {}

        self.frame_count = 0
        self.detected_frames = 0
        self.correct_frames = 0
        self.current_streak = 0
        self.best_streak = 0

        self.feedback_history: List[Dict] = []
        self.timeline: List[Dict] = []
        self.score_series: List[Dict] = []

        # Kept for DTW against the maestro sequence and for tempo estimation.
        self.user_features: List[np.ndarray] = []
        self.pose_timeline: List[Dict] = []
        self.movement_times: List[float] = []
        self.beat_times: List[float] = []

        self._last_series_at = 0.0
        self._last_feedback_key: Optional[str] = None

    # -- helpers -----------------------------------------------------------

    @property
    def elapsed(self) -> float:
        return (datetime.utcnow() - self.start_time).total_seconds()

    def touch(self) -> None:
        self.last_activity = datetime.utcnow()

    def is_idle(self, timeout_seconds: int) -> bool:
        return (datetime.utcnow() - self.last_activity).total_seconds() > timeout_seconds

    # -- ingestion ---------------------------------------------------------

    def update_wiraga(self, comparison: Dict, pose_data: Optional[Dict] = None) -> None:
        """Fold one frame's pose comparison into the running Wiraga score."""
        if not comparison:
            return

        with self._lock:
            self.touch()
            self.frame_count += 1

            score = float(comparison.get('score') or 0.0)
            self.wiraga_scores.append(score)
            self._live_wiraga.append(score)

            if pose_data and pose_data.get('detected'):
                self.detected_frames += 1

            if score >= self.correct_threshold:
                self.correct_frames += 1
                self.current_streak += 1
                self.best_streak = max(self.best_streak, self.current_streak)
            else:
                self.current_streak = 0

            for joint, joint_score in (comparison.get('joint_scores') or {}).items():
                self.joint_totals[joint] = self.joint_totals.get(joint, 0.0) + float(joint_score)
                self.joint_counts[joint] = self.joint_counts.get(joint, 0) + 1

            for message in (comparison.get('feedback') or [])[:2]:
                self._record_feedback('wiraga', message, score)

            if pose_data:
                features = pose_data.get('features')
                if features:
                    self.user_features.append(np.asarray(features, dtype=np.float32))
                landmarks = pose_data.get('pose_landmarks')
                if landmarks and len(self.pose_timeline) < 6000:
                    self.pose_timeline.append({
                        'timestamp': round(self.elapsed, 3),
                        'pose_landmarks': landmarks,
                    })

            self._maybe_sample_series()

    def update_wirama(self, rhythm_result: Dict) -> None:
        if not rhythm_result or 'score' not in rhythm_result:
            return
        with self._lock:
            self.touch()
            score = float(rhythm_result['score'])
            self.wirama_scores.append(score)
            self._live_wirama.append(score)
            for message in (rhythm_result.get('feedback') or [])[:1]:
                self._record_feedback('wirama', message, score)

    def update_wirasa(self, expression_result: Dict) -> None:
        if not expression_result or not expression_result.get('detected'):
            return
        with self._lock:
            self.touch()
            score = float(expression_result.get('score') or 0.0)
            self.wirasa_scores.append(score)
            self._live_wirasa.append(score)
            for message in (expression_result.get('feedback') or [])[:1]:
                self._record_feedback('wirasa', message, score)

    def register_beat(self, timestamp: Optional[float] = None) -> None:
        with self._lock:
            self.beat_times.append(float(timestamp if timestamp is not None else self.elapsed))

    def register_movement_accent(self, timestamp: Optional[float] = None) -> None:
        with self._lock:
            self.movement_times.append(
                float(timestamp if timestamp is not None else self.elapsed)
            )

    # -- internals ---------------------------------------------------------

    def _record_feedback(self, kind: str, message: str, score: float) -> None:
        """De-duplicated feedback log (caller already holds the lock)."""
        key = f'{kind}:{message}'
        if key == self._last_feedback_key:
            return
        self._last_feedback_key = key

        entry = {
            'type': kind,
            'message': message,
            'at': round(self.elapsed, 2),
            'severity': 'success' if score >= 85 else ('warning' if score >= 60 else 'error'),
        }
        self.feedback_history.append(entry)
        if len(self.feedback_history) > 400:
            self.feedback_history = self.feedback_history[-400:]

        # Timeline keeps only meaningful, spaced-out events for the detail page.
        if not self.timeline or (entry['at'] - self.timeline[-1]['at']) >= 3.0:
            self.timeline.append(entry)
            if len(self.timeline) > 120:
                self.timeline = self.timeline[-120:]

    def _maybe_sample_series(self) -> None:
        """Sample the running score once per second for the progress chart."""
        now = self.elapsed
        if now - self._last_series_at < 1.0:
            return
        self._last_series_at = now
        self.score_series.append({
            't': round(now, 1),
            'wiraga': round(float(np.mean(self._live_wiraga)), 1) if self._live_wiraga else 0.0,
            'wirama': round(float(np.mean(self._live_wirama)), 1) if self._live_wirama else 0.0,
            'wirasa': round(float(np.mean(self._live_wirasa)), 1) if self._live_wirasa else 0.0,
        })
        if len(self.score_series) > 1800:  # 30 minutes
            self.score_series = self.score_series[-1800:]

    def _weighted(self, wiraga: float, wirama: float, wirasa: float) -> float:
        """
        Weighted total that ignores aspects with no data.

        Without this, a session where the microphone was off scored 0 for
        Wirama and dragged the total down by 30 points through no fault of the
        dancer.
        """
        parts, weights = [], []
        if self.wiraga_scores:
            parts.append(wiraga)
            weights.append(self.weights['wiraga'])
        if self.wirama_scores:
            parts.append(wirama)
            weights.append(self.weights['wirama'])
        if self.wirasa_scores:
            parts.append(wirasa)
            weights.append(self.weights['wirasa'])
        if not parts:
            return 0.0
        return float(np.average(parts, weights=weights))

    # -- output ------------------------------------------------------------

    def live_feedback(self) -> Dict:
        """Snapshot for the real-time HUD."""
        with self._lock:
            wiraga = float(np.mean(self._live_wiraga)) if self._live_wiraga else 0.0
            wirama = float(np.mean(self._live_wirama)) if self._live_wirama else 0.0
            wirasa = float(np.mean(self._live_wirasa)) if self._live_wirasa else 0.0
            total = self._weighted(wiraga, wirama, wirasa)

            return {
                'wiraga': round(wiraga, 1),
                'wirama': round(wirama, 1),
                'wirasa': round(wirasa, 1),
                'total': round(total, 1),
                'grade': grade_for(total),
                'feedback': [f['message'] for f in self.feedback_history[-5:]][::-1],
                'frame_count': self.frame_count,
                'detected_frames': self.detected_frames,
                'correct_frames': self.correct_frames,
                'accuracy': round(
                    100.0 * self.correct_frames / max(self.frame_count, 1), 1
                ),
                'current_streak': self.current_streak,
                'best_streak': self.best_streak,
                'elapsed': round(self.elapsed, 1),
            }

    def result(self, maestro_features: Optional[np.ndarray] = None) -> Dict:
        """Final result, including the DTW sequence score when available."""
        with self._lock:
            wiraga = float(np.mean(self.wiraga_scores)) if self.wiraga_scores else 0.0
            wirama = float(np.mean(self.wirama_scores)) if self.wirama_scores else 0.0
            wirasa = float(np.mean(self.wirasa_scores)) if self.wirasa_scores else 0.0

            # Sequence-level agreement with the maestro: rewards doing the
            # movements in the right ORDER and rhythm, not just hitting poses.
            sequence_score = None
            if maestro_features is not None and len(self.user_features) >= 8:
                sequence_score = self._sequence_score(maestro_features)
                # Blend: 75% frame-wise accuracy, 25% sequence agreement.
                wiraga = 0.75 * wiraga + 0.25 * sequence_score

            # Derive Wirama from movement/beat sync if it was never fed directly.
            if not self.wirama_scores and self.beat_times and self.movement_times:
                from .rhythm_analyzer import RhythmAnalyzer
                sync = RhythmAnalyzer().evaluate_synchronization(
                    self.movement_times, self.beat_times
                )
                wirama = sync['score']
                self.wirama_scores.append(wirama)

            total = self._weighted(wiraga, wirama, wirasa)
            joint_scores = {
                joint: round(self.joint_totals[joint] / max(self.joint_counts[joint], 1), 1)
                for joint in self.joint_totals
            }

            return {
                'session_id': self.session_id,
                'user_id': self.user_id,
                'karakter': self.karakter,
                'gerakan': self.gerakan,
                'duration': int(self.elapsed),
                'scores': {
                    'wiraga': round(wiraga, 1),
                    'wirama': round(wirama, 1),
                    'wirasa': round(wirasa, 1),
                    'total': round(total, 1),
                },
                'sequence_score': round(sequence_score, 1) if sequence_score is not None else None,
                'grade': grade_for(total),
                'frames_analyzed': self.frame_count,
                'detected_frames': self.detected_frames,
                'correct_frames': self.correct_frames,
                'best_streak': self.best_streak,
                'accuracy': round(100.0 * self.correct_frames / max(self.frame_count, 1), 1),
                'joint_scores': joint_scores,
                'improvement_areas': self._improvement_areas(wiraga, wirama, wirasa, joint_scores),
                'feedback': self._final_feedback(wiraga, wirama, wirasa),
                'timeline': self.timeline,
                'score_series': self.score_series,
                'timestamp': datetime.utcnow().isoformat(),
            }

    def _sequence_score(self, maestro_features: np.ndarray) -> float:
        """DTW agreement between the learner's and the maestro's trajectory."""
        user = np.stack(self.user_features).astype(np.float32)
        # Resample both to a common, tractable length before warping.
        target_len = int(np.clip(min(len(user), len(maestro_features)), 32, 220))
        a = resample_sequence(user, target_len)
        b = resample_sequence(np.asarray(maestro_features, dtype=np.float32), target_len)

        distance = dtw_distance(a, b, band=max(8, target_len // 6))
        if not np.isfinite(distance):
            return 0.0
        # Empirically, a good learner sits near 0.35, a poor one above 1.2.
        return float(np.clip(100.0 * np.exp(-1.5 * distance), 0.0, 100.0))

    def _improvement_areas(
        self, wiraga: float, wirama: float, wirasa: float, joint_scores: Dict[str, float]
    ) -> List[Dict]:
        areas: List[Dict] = []

        aspects = [
            ('wiraga', wiraga, 'Ketepatan Gerakan'),
            ('wirama', wirama, 'Sinkronisasi Irama'),
            ('wirasa', wirasa, 'Ekspresi & Penghayatan'),
        ]
        for key, score, name in sorted(aspects, key=lambda t: t[1]):
            if score < 80:
                areas.append({
                    'aspect': key,
                    'name': name,
                    'current_score': round(score, 1),
                    'target_score': 80,
                    'priority': 'high' if score < 60 else 'medium',
                })

        # Add the two weakest individual joints - far more actionable than
        # "improve your Wiraga".
        for joint, score in sorted(joint_scores.items(), key=lambda t: t[1])[:2]:
            if score < 75:
                areas.append({
                    'aspect': 'joint',
                    'name': ANGLE_LABELS_ID.get(joint, joint).title(),
                    'current_score': round(score, 1),
                    'target_score': 80,
                    'priority': 'high' if score < 55 else 'medium',
                })

        return areas[:4]

    @staticmethod
    def _final_feedback(wiraga: float, wirama: float, wirasa: float) -> List[str]:
        out: List[str] = []

        if wiraga >= 85:
            out.append('Gerakan tubuh sangat presisi dan sesuai pakem!')
        elif wiraga >= 70:
            out.append('Gerakan tubuh cukup baik, beberapa posisi masih perlu diperbaiki.')
        elif wiraga >= 50:
            out.append('Perlu latihan lebih untuk memperbaiki ketepatan posisi tubuh.')
        else:
            out.append('Fokus pelajari gerakan dasar terlebih dahulu sebelum kombinasi.')

        if wirama >= 85:
            out.append('Sinkronisasi dengan irama gamelan sangat baik!')
        elif wirama >= 70:
            out.append('Irama cukup sesuai, tingkatkan kepekaan terhadap ketukan.')
        elif wirama >= 50:
            out.append('Perlu lebih memperhatikan tempo dan ketukan gamelan.')
        else:
            out.append('Latih pendengaran terhadap ritme gamelan sambil menghitung.')

        if wirasa >= 85:
            out.append('Ekspresi dan penghayatan sangat menjiwai karakter!')
        elif wirasa >= 70:
            out.append('Penghayatan sudah baik, tingkatkan intensitasnya.')
        elif wirasa >= 50:
            out.append('Perlu lebih menghayati karakter yang ditarikan.')
        else:
            out.append('Pelajari karakteristik dan filosofi karakter ini lebih dalam.')

        return out


# ---------------------------------------------------------------------------

class SessionRegistry:
    """
    Thread-safe store of the live :class:`SessionEvaluator` instances.

    Replaces the module-level singleton that used to leak one dancer's scores
    into another's session, and reaps abandoned sessions so a browser that is
    simply closed does not pin memory forever.
    """

    def __init__(self, idle_timeout: int = 1800):
        self._sessions: Dict[str, SessionEvaluator] = {}
        self._lock = threading.Lock()
        self.idle_timeout = idle_timeout

    def create(self, session_id: str, user_id: int, karakter: str,
               gerakan: Optional[str] = None,
               correct_threshold: float = 70.0) -> SessionEvaluator:
        evaluator = SessionEvaluator(
            session_id, user_id, karakter, gerakan, correct_threshold
        )
        with self._lock:
            self._sessions[session_id] = evaluator
        self.reap()
        return evaluator

    def get(self, session_id: str) -> Optional[SessionEvaluator]:
        with self._lock:
            return self._sessions.get(session_id)

    def pop(self, session_id: str) -> Optional[SessionEvaluator]:
        with self._lock:
            return self._sessions.pop(session_id, None)

    def reap(self) -> int:
        """Drop sessions that stopped sending frames. Returns how many went."""
        with self._lock:
            stale = [
                sid for sid, ev in self._sessions.items()
                if ev.is_idle(self.idle_timeout)
            ]
            for sid in stale:
                self._sessions.pop(sid, None)
        return len(stale)

    def count(self) -> int:
        with self._lock:
            return len(self._sessions)

    def user_sessions(self, user_id: int) -> List[str]:
        with self._lock:
            return [sid for sid, ev in self._sessions.items() if ev.user_id == user_id]


# ---------------------------------------------------------------------------
# Backwards-compatible facade
# ---------------------------------------------------------------------------

class PerformanceEvaluator:
    """
    Thin wrapper kept so older callers keep working.

    New code should use :class:`SessionRegistry` + :class:`SessionEvaluator`
    directly; this class simply owns one evaluator at a time.
    """

    SCORE_WEIGHTS = SCORE_WEIGHTS
    CHARACTER_FOCUS = CHARACTER_FOCUS

    def __init__(self):
        self._evaluator: Optional[SessionEvaluator] = None

    def start_session(self, karakter: str = 'klana', user_id: int = 0,
                      session_id: str = 'default') -> SessionEvaluator:
        self._evaluator = SessionEvaluator(session_id, user_id, karakter)
        return self._evaluator

    def update_wiraga(self, comparison: Dict, pose_data: Optional[Dict] = None) -> None:
        if self._evaluator:
            self._evaluator.update_wiraga(comparison, pose_data)

    def update_wirama(self, rhythm_result: Dict) -> None:
        if self._evaluator:
            self._evaluator.update_wirama(rhythm_result)

    def update_wirasa(self, expression_result: Dict) -> None:
        if self._evaluator:
            self._evaluator.update_wirasa(expression_result)

    def increment_frame(self) -> None:
        """No-op: frames are counted inside :meth:`update_wiraga` now."""
        return None

    def get_realtime_feedback(self) -> Dict:
        return self._evaluator.live_feedback() if self._evaluator else {}

    def get_session_result(self) -> Dict:
        if not self._evaluator:
            return {'error': 'No session started'}
        return self._evaluator.result()


# ---------------------------------------------------------------------------

class ScoreCalculator:
    """Aggregate statistics over a user's session history."""

    @staticmethod
    def calculate_progress(sessions: List[Dict]) -> Dict:
        if not sessions:
            return {'trend': 'none', 'improvement': 0.0, 'total_sessions': 0,
                    'recent_avg': 0.0, 'total_practice_time': 0}

        ordered = sorted(sessions, key=lambda s: s.get('created_at') or '')
        scores = [float(s.get('total_score') or 0) for s in ordered]

        if len(ordered) == 1:
            return {
                'trend': 'neutral',
                'improvement': 0.0,
                'recent_avg': round(scores[0], 1),
                'total_sessions': 1,
                'total_practice_time': int(ordered[0].get('duration') or 0),
            }

        split = max(1, len(scores) // 2)
        older_avg = float(np.mean(scores[:-split])) if len(scores) > split else float(scores[0])
        recent_avg = float(np.mean(scores[-split:]))
        improvement = recent_avg - older_avg

        if improvement > 5:
            trend = 'improving'
        elif improvement < -5:
            trend = 'declining'
        else:
            trend = 'stable'

        return {
            'trend': trend,
            'improvement': round(improvement, 1),
            'recent_avg': round(recent_avg, 1),
            'best_score': round(max(scores), 1),
            'total_sessions': len(sessions),
            'total_practice_time': int(sum(int(s.get('duration') or 0) for s in sessions)),
        }

    @staticmethod
    def calculate_level(total_score: int, practice_count: int) -> str:
        for name, min_sessions, min_score in LEVEL_TIERS:
            if practice_count >= min_sessions and total_score >= min_score:
                return name
        return 'Pemula'

    @staticmethod
    def calculate_daily_stats(sessions: List[Dict], days: int = 7) -> List[Dict]:
        today = datetime.utcnow().date()
        stats: List[Dict] = []

        for i in range(days - 1, -1, -1):
            day = today - timedelta(days=i)
            prefix = day.isoformat()
            day_sessions = [
                s for s in sessions
                if str(s.get('created_at') or '').startswith(prefix)
            ]
            if day_sessions:
                scores = [float(s.get('total_score') or 0) for s in day_sessions]
                stats.append({
                    'date': prefix,
                    'session_count': len(day_sessions),
                    'avg_score': round(float(np.mean(scores)), 1),
                    'best_score': round(float(np.max(scores)), 1),
                    'total_duration': int(sum(int(s.get('duration') or 0) for s in day_sessions)),
                })
            else:
                stats.append({'date': prefix, 'session_count': 0, 'avg_score': 0.0,
                              'best_score': 0.0, 'total_duration': 0})
        return stats

    @staticmethod
    def get_character_mastery(sessions: List[Dict]) -> Dict[str, Dict]:
        characters = ['panji', 'samba', 'rumyang', 'tumenggung', 'klana']
        mastery: Dict[str, Dict] = {}

        for char in characters:
            char_sessions = [s for s in sessions if s.get('karakter') == char]
            if not char_sessions:
                mastery[char] = {'level': 'Belum dicoba', 'avg_score': 0.0,
                                 'best_score': 0.0, 'session_count': 0, 'mastery': 0.0}
                continue

            scores = [float(s.get('total_score') or 0) for s in char_sessions]
            avg = float(np.mean(scores))
            best = float(np.max(scores))

            if avg >= 85:
                level = 'Master'
            elif avg >= 70:
                level = 'Mahir'
            elif avg >= 50:
                level = 'Menengah'
            else:
                level = 'Pemula'

            mastery[char] = {
                'level': level,
                'avg_score': round(avg, 1),
                'best_score': round(best, 1),
                'session_count': len(char_sessions),
                # Blends quality with experience so a single lucky run does not
                # read as mastery.
                'mastery': round(min(100.0, avg * 0.75 + len(char_sessions) * 2.5), 1),
            }
        return mastery

    @staticmethod
    def calculate_streak(session_dates: List[str]) -> Tuple[int, int]:
        """(current_streak, longest_streak) in consecutive practice days."""
        if not session_dates:
            return 0, 0

        days = sorted({str(d)[:10] for d in session_dates if d}, reverse=True)
        if not days:
            return 0, 0

        parsed = [datetime.strptime(d, '%Y-%m-%d').date() for d in days]
        today = datetime.utcnow().date()

        current = 0
        if parsed[0] in (today, today - timedelta(days=1)):
            current = 1
            for prev, nxt in zip(parsed, parsed[1:]):
                if (prev - nxt).days == 1:
                    current += 1
                else:
                    break

        longest, run = 1, 1
        for prev, nxt in zip(parsed, parsed[1:]):
            if (prev - nxt).days == 1:
                run += 1
                longest = max(longest, run)
            else:
                run = 1

        return current, max(longest, current)
