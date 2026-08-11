"""
Gamelan rhythm analysis - the Wirama half of the score.

Fixes over the original implementation
--------------------------------------
* librosa >= 0.10 returns ``tempo`` as an ndarray; every value is coerced with
  :func:`_scalar` so ``float(...)`` never raises.
* Every returned number is a plain Python type - numpy scalars are not JSON
  serialisable and used to crash the ``audio_result`` socket emit.
* ``peak_pick`` is called with the keyword signature current librosa requires.
* Real-time onset detection now keeps a rolling baseline instead of comparing
  a chunk against its own mean, which previously fired on almost every chunk.
"""

from collections import deque
from typing import Deque, Dict, List, Optional, Tuple

import numpy as np


def _scalar(value) -> float:
    """Coerce a numpy scalar/0-d/1-element array into a Python float."""
    arr = np.asarray(value, dtype=np.float64).ravel()
    return float(arr[0]) if arr.size else 0.0


class RhythmAnalyzer:
    """Tempo, beat and synchronisation analysis for gamelan accompaniment."""

    # Characteristic tempo band (BPM) for each Topeng character.
    TEMPO_RANGES: Dict[str, Tuple[int, int]] = {
        'panji': (60, 80),
        'samba': (80, 100),
        'rumyang': (70, 90),
        'tumenggung': (90, 110),
        'klana': (100, 130),
    }

    def __init__(self, sample_rate: int = 22050, history: int = 64):
        self.sample_rate = sample_rate
        self.hop_length = 512
        self.current_tempo = 0.0
        self.beat_times: List[float] = []
        self.onset_times: List[float] = []
        # Rolling onset-strength baseline for the real-time detector.
        self._onset_history: Deque[float] = deque(maxlen=history)
        self._last_beat_at: Optional[float] = None

    # -- offline analysis --------------------------------------------------

    def load_audio(self, audio_path: str) -> Tuple[np.ndarray, int]:
        import librosa
        y, sr = librosa.load(audio_path, sr=self.sample_rate, mono=True)
        return y, int(sr)

    def analyze_audio(self, y: np.ndarray) -> Dict:
        """Full analysis of a gamelan recording."""
        import librosa

        y = np.asarray(y, dtype=np.float32)
        if y.size < self.hop_length * 4:
            return {
                'tempo': 0.0, 'beat_times': [], 'onset_times': [],
                'duration': 0.0, 'total_beats': 0,
                'avg_spectral_centroid': 0.0, 'avg_rms': 0.0,
            }

        tempo, beat_frames = librosa.beat.beat_track(
            y=y, sr=self.sample_rate, hop_length=self.hop_length,
        )
        beat_times = librosa.frames_to_time(
            beat_frames, sr=self.sample_rate, hop_length=self.hop_length,
        )
        onset_frames = librosa.onset.onset_detect(
            y=y, sr=self.sample_rate, hop_length=self.hop_length,
        )
        onset_times = librosa.frames_to_time(
            onset_frames, sr=self.sample_rate, hop_length=self.hop_length,
        )
        spectral_centroid = librosa.feature.spectral_centroid(y=y, sr=self.sample_rate)
        rms = librosa.feature.rms(y=y)

        self.current_tempo = _scalar(tempo)
        self.beat_times = [float(t) for t in np.atleast_1d(beat_times)]
        self.onset_times = [float(t) for t in np.atleast_1d(onset_times)]

        return {
            'tempo': round(self.current_tempo, 2),
            'beat_times': self.beat_times,
            'onset_times': self.onset_times,
            'duration': round(float(len(y)) / self.sample_rate, 3),
            'total_beats': len(self.beat_times),
            'avg_spectral_centroid': round(float(np.mean(spectral_centroid)), 2),
            'avg_rms': round(float(np.mean(rms)), 5),
        }

    # -- real-time ---------------------------------------------------------

    def analyze_realtime_chunk(self, audio_chunk: np.ndarray,
                               timestamp: Optional[float] = None) -> Dict:
        """
        Detect a beat inside a short chunk streamed from the browser.

        Compares the chunk's onset strength against a rolling baseline of
        previous chunks, so a steady loud passage does not register as a
        continuous stream of beats.
        """
        import librosa

        chunk = np.asarray(audio_chunk, dtype=np.float32).ravel()
        if chunk.size < 2048:
            return {'beat_detected': False, 'energy': 0.0, 'onset_strength': 0.0,
                    'threshold': 0.0}

        rms = float(np.sqrt(np.mean(chunk ** 2)))

        onset_env = librosa.onset.onset_strength(
            y=chunk, sr=self.sample_rate, hop_length=self.hop_length,
        )
        peak = float(np.max(onset_env)) if onset_env.size else 0.0

        if len(self._onset_history) >= 8:
            baseline = float(np.mean(self._onset_history))
            spread = float(np.std(self._onset_history)) or 1e-6
            threshold = baseline + 1.3 * spread
        else:
            threshold = float('inf')  # warm-up: never fire until we have context

        beat = bool(peak > threshold and rms > 1e-4)

        # Refractory period: a gamelan beat cannot repeat faster than ~150 ms.
        if beat and timestamp is not None:
            if self._last_beat_at is not None and (timestamp - self._last_beat_at) < 0.15:
                beat = False
            else:
                self._last_beat_at = timestamp

        self._onset_history.append(peak)

        return {
            'beat_detected': beat,                      # plain bool, JSON safe
            'energy': round(rms, 6),
            'onset_strength': round(peak, 4),
            'threshold': round(threshold, 4) if np.isfinite(threshold) else None,
        }

    def reset_realtime(self) -> None:
        self._onset_history.clear()
        self._last_beat_at = None

    # -- synchronisation scoring ------------------------------------------

    def evaluate_synchronization(
        self,
        movement_times: List[float],
        beat_times: List[float],
        tolerance: float = 0.15,
    ) -> Dict:
        """
        How well do the dancer's accents land on the gamelan beats?

        Every beat is matched to its nearest movement accent; the score is the
        share of beats hit inside ``tolerance`` seconds, with a smooth partial
        credit for near misses so a consistently-slightly-late dancer still
        scores better than a random one.
        """
        movement_times = [float(t) for t in (movement_times or [])]
        beat_times = [float(t) for t in (beat_times or [])]

        if not movement_times or not beat_times:
            return {
                'score': 0.0,
                'feedback': ['Belum ada data gerakan atau musik untuk dinilai'],
                'synced_count': 0,
                'total_beats': len(beat_times),
                'avg_timing_error': 0.0,
                'drift': 0.0,
            }

        moves = np.array(sorted(movement_times), dtype=np.float64)
        synced = 0
        errors: List[float] = []
        signed: List[float] = []

        for beat in beat_times:
            idx = int(np.argmin(np.abs(moves - beat)))
            delta = float(moves[idx] - beat)
            errors.append(abs(delta))
            signed.append(delta)
            if abs(delta) <= tolerance:
                synced += 1

        errors_arr = np.array(errors)
        # Partial credit: |e| <= tol -> 1.0, decaying to 0 by 3x tolerance.
        credit = np.clip(1.0 - (errors_arr - tolerance) / (2.0 * tolerance), 0.0, 1.0)
        credit[errors_arr <= tolerance] = 1.0
        score = float(np.mean(credit) * 100.0)

        avg_error = float(np.mean(errors_arr))
        drift = float(np.mean(signed))

        feedback: List[str] = []
        if score >= 90:
            feedback.append('Sinkronisasi dengan gamelan sangat baik!')
        elif score >= 75:
            feedback.append('Sinkronisasi cukup baik, pertahankan')
        elif score >= 55:
            feedback.append('Perhatikan ketukan gamelan lebih seksama')
        else:
            feedback.append('Latih kepekaan terhadap irama gamelan')

        if drift > 0.12:
            feedback.append(f'Gerakan cenderung terlambat {drift:.2f} detik dari ketukan')
        elif drift < -0.12:
            feedback.append(f'Gerakan cenderung mendahului ketukan {abs(drift):.2f} detik')

        return {
            'score': round(score, 1),
            'synced_count': synced,
            'total_beats': len(beat_times),
            'avg_timing_error': round(avg_error, 3),
            'drift': round(drift, 3),
            'feedback': feedback,
        }

    def get_tempo_feedback(self, detected_tempo: float, karakter: str) -> Dict:
        """Is the dancer moving at the tempo this character calls for?"""
        detected_tempo = _scalar(detected_tempo)
        lo, hi = self.TEMPO_RANGES.get(karakter, (70, 100))

        if detected_tempo <= 0:
            status, message, score = 'unknown', 'Tempo belum terdeteksi', 0.0
        elif detected_tempo < lo:
            status = 'slow'
            message = f'Tempo terlalu lambat untuk {karakter.title()}. Percepat gerakan.'
            score = float(np.clip(100.0 - (lo - detected_tempo) * 2.5, 0.0, 100.0))
        elif detected_tempo > hi:
            status = 'fast'
            message = f'Tempo terlalu cepat untuk {karakter.title()}. Perlambat gerakan.'
            score = float(np.clip(100.0 - (detected_tempo - hi) * 2.5, 0.0, 100.0))
        else:
            status, score = 'good', 100.0
            message = f'Tempo sudah pas untuk karakter {karakter.title()}!'

        return {
            'status': status,
            'score': round(score, 1),
            'detected_tempo': round(detected_tempo, 1),
            'expected_range': [lo, hi],
            'message': message,
        }

    # -- movement accents --------------------------------------------------

    def detect_movement_peaks(
        self, pose_sequence: List[Dict], threshold: Optional[float] = None
    ) -> List[float]:
        """
        Timestamps where the dancer produces a movement accent.

        Accents are local maxima of frame-to-frame landmark velocity. The
        threshold adapts to the performance (75th percentile) rather than being
        a fixed constant, so it works for both the slow Panji and the fast
        Klana.
        """
        if len(pose_sequence) < 3:
            return []

        velocities: List[float] = [0.0]
        for prev, curr in zip(pose_sequence, pose_sequence[1:]):
            p_lm = prev.get('pose_landmarks') or prev.get('landmarks')
            c_lm = curr.get('pose_landmarks') or curr.get('landmarks')
            if not p_lm or not c_lm:
                velocities.append(0.0)
                continue

            n = min(len(p_lm), len(c_lm))
            total, count = 0.0, 0
            for i in range(n):
                dx = float(c_lm[i].get('x', 0.0)) - float(p_lm[i].get('x', 0.0))
                dy = float(c_lm[i].get('y', 0.0)) - float(p_lm[i].get('y', 0.0))
                dz = float(c_lm[i].get('z', 0.0)) - float(p_lm[i].get('z', 0.0))
                total += float(np.sqrt(dx * dx + dy * dy + dz * dz))
                count += 1
            velocities.append(total / count if count else 0.0)

        v = np.array(velocities, dtype=np.float32)
        if v.size < 3 or not np.any(v > 0):
            return []

        if threshold is None:
            threshold = float(np.percentile(v[v > 0], 75)) if np.any(v > 0) else 0.0

        peaks: List[float] = []
        for i in range(1, len(v) - 1):
            if v[i] >= v[i - 1] and v[i] > v[i + 1] and v[i] > threshold:
                ts = pose_sequence[i].get('timestamp')
                peaks.append(float(ts) if ts is not None else i * 0.033)
        return peaks


class GamelanBeatDetector:
    """
    Structural beat detection for gamelan: gong (low), kenong (mid).

    Tari Topeng is choreographed against the *gongan* cycle, not against a
    metronome, so these structural markers matter more than the raw BPM.
    """

    def __init__(self, sample_rate: int = 22050):
        self.sample_rate = sample_rate
        self.hop_length = 512

    def _band_peaks(self, y: np.ndarray, lo_bin: int, hi_bin: int,
                    pre: int, post: int, delta: float, wait: int) -> List[float]:
        import librosa

        y = np.asarray(y, dtype=np.float32)
        if y.size < self.hop_length * 8:
            return []

        y_perc = librosa.effects.percussive(y)
        spec = np.abs(librosa.stft(y_perc, hop_length=self.hop_length))
        hi_bin = min(hi_bin, spec.shape[0])
        if lo_bin >= hi_bin:
            return []

        band = spec[lo_bin:hi_bin, :]
        flux = np.sum(np.diff(band, axis=1).clip(min=0), axis=0)
        if flux.size < 4:
            return []

        # Normalise so `delta` means the same thing for any recording level.
        peak = float(np.max(flux)) or 1.0
        flux = flux / peak

        peaks = librosa.util.peak_pick(
            x=flux, pre_max=pre, post_max=post, pre_avg=pre * 3,
            post_avg=post * 3, delta=delta, wait=wait,
        )
        if len(peaks) == 0:
            return []

        # peak_pick indexes into the diff array, which is offset by one frame.
        times = librosa.frames_to_time(
            np.asarray(peaks) + 1, sr=self.sample_rate, hop_length=self.hop_length,
        )
        return [float(t) for t in np.atleast_1d(times)]

    def detect_gong_hits(self, y: np.ndarray) -> List[float]:
        """Low-frequency structural accents (gong ageng / suwukan)."""
        return self._band_peaks(y, 0, 20, pre=10, post=10, delta=0.25, wait=30)

    def detect_kenong_pattern(self, y: np.ndarray) -> List[float]:
        """Mid-frequency metallic strokes (kenong / kempul)."""
        return self._band_peaks(y, 20, 100, pre=5, post=5, delta=0.15, wait=15)

    def get_structural_beats(self, y: np.ndarray) -> Dict:
        import librosa

        y = np.asarray(y, dtype=np.float32)
        gong_hits = self.detect_gong_hits(y)
        kenong_hits = self.detect_kenong_pattern(y)

        tempo, beat_frames = librosa.beat.beat_track(
            y=y, sr=self.sample_rate, hop_length=self.hop_length,
        )
        beat_times = librosa.frames_to_time(
            beat_frames, sr=self.sample_rate, hop_length=self.hop_length,
        )

        return {
            'tempo': round(_scalar(tempo), 2),
            'regular_beats': [float(t) for t in np.atleast_1d(beat_times)],
            'gong_hits': gong_hits,
            'kenong_hits': kenong_hits,
            'total_gongs': len(gong_hits),
            'total_kenongs': len(kenong_hits),
        }
