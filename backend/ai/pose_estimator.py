"""
MediaPipe pose / expression estimation for Tari Topeng Cirebon.

Wiraga (body movement) comes from the pose landmarks, Wirasa (expression and
bearing) from the face mesh with a pose-based fallback - which matters here,
because a masked Topeng dancer often has no detectable face at all.
"""

from typing import Dict, List, Optional, Tuple

import cv2
import numpy as np

from .pose_utils import (
    ANGLE_LABELS_ID,
    ANGLE_NAMES,
    BODY_LANDMARKS,
    BODY_PART_LABELS_ID,
    POSE_CONNECTIONS,
    POSE_LANDMARK_NAMES,
    angle_similarity,
    array_to_landmark_dicts,
    body_orientation,
    compute_joint_angles,
    head_orientation_from_pose,
    landmark_dicts_to_array,
    landmarks_to_array,
    pose_feature_vector,
    pose_visibility,
)


class PoseEstimator:
    """
    Thread-confined MediaPipe wrapper.

    MediaPipe graph objects are *not* thread-safe, so one estimator instance
    must only ever be used from a single worker. ``app.py`` therefore keeps a
    small pool rather than sharing one global instance.
    """

    BODY_LANDMARKS = BODY_LANDMARKS
    LANDMARK_NAMES = POSE_LANDMARK_NAMES

    def __init__(
        self,
        model_complexity: int = 1,
        min_detection_confidence: float = 0.6,
        min_tracking_confidence: float = 0.6,
        enable_face: bool = True,
        enable_hands: bool = True,
        static_image_mode: bool = False,
    ):
        import mediapipe as mp

        self.mp = mp
        self.enable_face = enable_face
        self.enable_hands = enable_hands

        # Holistic gives pose + face + hands from one graph pass, which is both
        # faster and more consistent than running three separate solutions.
        self.holistic = mp.solutions.holistic.Holistic(
            static_image_mode=static_image_mode,
            model_complexity=model_complexity,
            smooth_landmarks=True,
            enable_segmentation=False,
            refine_face_landmarks=False,
            min_detection_confidence=min_detection_confidence,
            min_tracking_confidence=min_tracking_confidence,
        )
        self._closed = False

    # -- inference ---------------------------------------------------------

    def process_frame(self, frame: np.ndarray) -> Dict:
        """Extract every pose signal we need from one BGR frame."""
        result: Dict = {
            'detected': False,
            'pose_landmarks': None,
            'face_landmarks': None,
            'hand_landmarks': [],
            'angles': {},
            'head_orientation': None,
            'body_orientation': None,
            'confidence': 0.0,
            'features': None,
        }

        if frame is None or frame.size == 0:
            return result

        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        rgb.flags.writeable = False
        res = self.holistic.process(rgb)

        if not res.pose_landmarks:
            return result

        arr = landmarks_to_array(res.pose_landmarks.landmark)
        result['detected'] = True
        result['pose_landmarks'] = array_to_landmark_dicts(arr)
        result['angles'] = compute_joint_angles(arr)
        result['body_orientation'] = body_orientation(arr)
        result['head_orientation'] = head_orientation_from_pose(arr)
        result['confidence'] = round(pose_visibility(arr), 4)
        result['features'] = pose_feature_vector(arr).tolist()

        if self.enable_face and getattr(res, 'face_landmarks', None):
            result['face_landmarks'] = self._extract_face_landmarks(
                res.face_landmarks.landmark
            )

        if self.enable_hands:
            for hand in (res.left_hand_landmarks, res.right_hand_landmarks):
                if hand:
                    result['hand_landmarks'].append([
                        {'index': i, 'x': round(float(lm.x), 5),
                         'y': round(float(lm.y), 5), 'z': round(float(lm.z), 5)}
                        for i, lm in enumerate(hand.landmark)
                    ])

        return result

    @staticmethod
    def _extract_face_landmarks(landmarks) -> List[Dict]:
        """Key facial points used by the expression analyser."""
        key_indices = [1, 10, 13, 14, 33, 61, 145, 152, 159, 263, 291, 374, 386]
        out = []
        for idx in key_indices:
            if idx >= len(landmarks):
                continue
            lm = landmarks[idx]
            out.append({
                'index': idx,
                'x': round(float(lm.x), 5),
                'y': round(float(lm.y), 5),
                'z': round(float(lm.z), 5),
            })
        return out

    # -- comparison --------------------------------------------------------

    def compare_poses(
        self,
        user_pose: Dict,
        maestro_pose: Dict,
        angle_threshold: float = 12.0,
    ) -> Dict:
        """
        Score a learner's pose against a maestro keyframe.

        Combines the joint-angle similarity with a normalised-landmark distance
        so both "your elbow is at the wrong angle" and "your whole stance is
        too narrow" are penalised.
        """
        if not user_pose or not user_pose.get('detected'):
            return {
                'score': 0.0,
                'feedback': ['Pose belum terdeteksi - pastikan seluruh badan terlihat kamera'],
                'joint_scores': {},
                'worst_joints': [],
            }

        maestro_angles = (maestro_pose or {}).get('angles') or {}
        if not maestro_angles:
            return {
                'score': 0.0,
                'feedback': ['Referensi maestro belum tersedia untuk gerakan ini'],
                'joint_scores': {},
                'worst_joints': [],
            }

        user_angles = user_pose.get('angles') or {}
        angle_score, diffs = angle_similarity(user_angles, maestro_angles, angle_threshold)

        # Per-joint 0-100 so the UI can show an improvement breakdown.
        joint_scores = {
            name: round(float(100.0 * np.exp(-0.5 * (diff / max(angle_threshold, 1e-6)) ** 2)), 1)
            for name, diff in diffs
        }

        # Posture-shape term: distance between normalised landmark sets.
        shape_score = self._shape_similarity(user_pose, maestro_pose)

        if shape_score is None:
            score = angle_score
        else:
            score = 0.7 * angle_score + 0.3 * shape_score

        # Confidence gate - a barely-visible dancer should not score highly.
        confidence = float(user_pose.get('confidence') or 0.0)
        if confidence < 0.5:
            score *= max(0.4, confidence / 0.5)

        feedback = self._build_feedback(diffs, angle_threshold, score)

        return {
            'score': round(float(np.clip(score, 0.0, 100.0)), 1),
            'angle_score': round(angle_score, 1),
            'shape_score': round(shape_score, 1) if shape_score is not None else None,
            'feedback': feedback,
            'joint_scores': joint_scores,
            'worst_joints': [
                {'joint': name,
                 'label': ANGLE_LABELS_ID.get(name, name),
                 'diff': round(diff, 1)}
                for name, diff in diffs[:3]
            ],
            'avg_angle_diff': round(float(np.mean([d for _, d in diffs])), 2) if diffs else 0.0,
            'joints_compared': len(diffs),
        }

    @staticmethod
    def _shape_similarity(user_pose: Dict, maestro_pose: Dict) -> Optional[float]:
        """0-100 similarity of the normalised feature vectors."""
        user_feat = user_pose.get('features')
        ref_feat = maestro_pose.get('features')

        if ref_feat is None:
            ref_landmarks = maestro_pose.get('landmarks') or maestro_pose.get('pose_landmarks')
            if not ref_landmarks:
                return None
            ref_feat = pose_feature_vector(landmark_dicts_to_array(ref_landmarks))

        if user_feat is None:
            user_landmarks = user_pose.get('pose_landmarks')
            if not user_landmarks:
                return None
            user_feat = pose_feature_vector(landmark_dicts_to_array(user_landmarks))

        a = np.asarray(user_feat, dtype=np.float32)
        b = np.asarray(ref_feat, dtype=np.float32)
        if a.shape != b.shape or a.size == 0:
            return None

        rmse = float(np.sqrt(np.mean((a - b) ** 2)))
        # 0 -> 100, 0.35 -> ~50, 0.8 -> ~11
        return float(np.clip(100.0 * np.exp(-2.0 * rmse), 0.0, 100.0))

    @staticmethod
    def _build_feedback(
        diffs: List[Tuple[str, float]], threshold: float, score: float
    ) -> List[str]:
        """Actionable Indonesian coaching lines, worst joint first."""
        if score >= 88:
            return ['Bagus! Gerakan sudah sesuai pakem']

        messages: List[str] = []
        for name, diff in diffs[:3]:
            if diff <= threshold:
                continue
            label = ANGLE_LABELS_ID.get(name, name)
            if diff > 3 * threshold:
                messages.append(f'Perbaiki {label} - selisih {diff:.0f}° dari maestro')
            elif diff > 1.8 * threshold:
                messages.append(f'Sesuaikan {label} (selisih {diff:.0f}°)')
            else:
                messages.append(f'Sedikit lagi pada {label}')

        if not messages:
            messages.append('Pertahankan, gerakan sudah mendekati referensi')
        return messages

    @staticmethod
    def body_part_name(joint_name: str) -> str:
        for key, value in BODY_PART_LABELS_ID.items():
            if key in joint_name:
                return value
        return joint_name

    # -- drawing -----------------------------------------------------------

    def draw_pose(self, frame: np.ndarray, pose_data: Dict,
                  color: Tuple[int, int, int] = (32, 90, 232)) -> np.ndarray:
        """Overlay the skeleton on a frame (server-side preview / debugging)."""
        landmarks = (pose_data or {}).get('pose_landmarks')
        if not landmarks:
            return frame

        h, w = frame.shape[:2]
        pts = [(int(lm['x'] * w), int(lm['y'] * h)) for lm in landmarks]

        for a, b in POSE_CONNECTIONS:
            if a < len(pts) and b < len(pts):
                cv2.line(frame, pts[a], pts[b], color, 2, cv2.LINE_AA)
        for i, p in enumerate(pts):
            visible = landmarks[i].get('visibility', 1.0) >= 0.5
            cv2.circle(frame, p, 4, (255, 255, 255) if visible else (90, 90, 160),
                       -1, cv2.LINE_AA)
        return frame

    # -- lifecycle ---------------------------------------------------------

    def release(self) -> None:
        if not self._closed:
            try:
                self.holistic.close()
            finally:
                self._closed = True

    def __del__(self):  # pragma: no cover - best effort cleanup
        try:
            self.release()
        except Exception:
            pass


class ExpressionAnalyzer:
    """
    Wirasa scoring.

    Reads facial geometry when a face is visible. Tari Topeng dancers wear a
    mask, so when no face is found it falls back to *bearing*: head carriage,
    torso openness and movement quality - which is what a Topeng judge actually
    grades, since the mask itself is expressionless.
    """

    EXPECTED = {
        'panji':      {'labels': ['tenang', 'khusyuk'],   'pitch': (-15, 10),  'openness': (0.30, 0.60)},
        'samba':      {'labels': ['ceria', 'lincah'],     'pitch': (-10, 20),  'openness': (0.45, 0.85)},
        'rumyang':    {'labels': ['anggun', 'lembut'],    'pitch': (-12, 12),  'openness': (0.35, 0.70)},
        'tumenggung': {'labels': ['tegas', 'berwibawa'],  'pitch': (-8, 15),   'openness': (0.55, 0.95)},
        'klana':      {'labels': ['garang', 'angkuh'],    'pitch': (-5, 25),   'openness': (0.60, 1.00)},
    }

    def analyze(self, pose_data: Dict, karakter: str = 'klana') -> Dict:
        """Score bearing/expression 0-100 for the given character."""
        if not pose_data or not pose_data.get('detected'):
            return {'detected': False, 'score': 0.0, 'expression': 'unknown',
                    'intensity': 0.0, 'feedback': []}

        profile = self.EXPECTED.get(karakter, self.EXPECTED['klana'])
        head = pose_data.get('head_orientation') or {}
        orientation = pose_data.get('body_orientation') or {}

        # 1. Head carriage - is the mask presented to the audience?
        pitch = float(head.get('pitch', 0.0))
        lo, hi = profile['pitch']
        if lo <= pitch <= hi:
            pitch_score = 100.0
        else:
            excess = min(abs(pitch - lo), abs(pitch - hi))
            pitch_score = float(np.clip(100.0 - excess * 2.2, 0.0, 100.0))

        # 2. Torso openness - Klana is broad and imposing, Panji is contained.
        shoulder = float(orientation.get('shoulder_width', 0.0))
        hip = float(orientation.get('hip_width', 0.0)) or 1e-6
        openness = float(np.clip(shoulder / max(hip, 1e-6) / 2.5, 0.0, 1.0))
        o_lo, o_hi = profile['openness']
        if o_lo <= openness <= o_hi:
            openness_score = 100.0
        else:
            excess = min(abs(openness - o_lo), abs(openness - o_hi))
            openness_score = float(np.clip(100.0 - excess * 220.0, 0.0, 100.0))

        # 3. Steadiness - a wobbling head reads as unconvincing.
        roll = abs(float(head.get('roll', 0.0)))
        roll_norm = min(roll, 180.0)
        steadiness = float(np.clip(100.0 - max(0.0, roll_norm - 25.0) * 1.4, 0.0, 100.0))

        # 4. Facial detail, when available (unmasked practice).
        face_score = None
        face_landmarks = pose_data.get('face_landmarks')
        if face_landmarks:
            face_score = self._face_score(face_landmarks)

        parts = [pitch_score, openness_score, steadiness]
        weights = [0.4, 0.35, 0.25]
        if face_score is not None:
            parts.append(face_score)
            weights = [0.3, 0.25, 0.2, 0.25]

        score = float(np.average(parts, weights=weights))
        intensity = round(float(np.clip((openness + (100 - abs(pitch)) / 100) / 2, 0, 1)), 2)

        feedback = []
        if pitch_score < 70:
            feedback.append('Angkat pandangan - arahkan topeng ke penonton'
                            if pitch < lo else 'Turunkan sedikit dagu, jangan terlalu mendongak')
        if openness_score < 70:
            feedback.append('Buka bahu lebih lebar untuk kesan gagah'
                            if openness < o_lo else 'Kurangi bukaan bahu, jaga proporsi')
        if steadiness < 70:
            feedback.append('Jaga kepala tetap stabil, hindari goyangan berlebih')

        return {
            'detected': True,
            'score': round(score, 1),
            'expression': profile['labels'][0],
            'intensity': intensity,
            'head_carriage': round(pitch_score, 1),
            'openness': round(openness_score, 1),
            'steadiness': round(steadiness, 1),
            'face_score': round(face_score, 1) if face_score is not None else None,
            'feedback': feedback,
        }

    @staticmethod
    def _face_score(face_landmarks: List[Dict]) -> Optional[float]:
        """Eye/mouth openness -> engagement score, when a face is visible."""
        by_index = {lm['index']: lm for lm in face_landmarks}
        needed = (13, 14, 61, 291, 159, 145, 386, 374)
        if not all(i in by_index for i in needed):
            return None

        mouth_v = abs(by_index[13]['y'] - by_index[14]['y'])
        mouth_h = abs(by_index[61]['x'] - by_index[291]['x']) or 1e-6
        mouth_open = float(np.clip(mouth_v / mouth_h * 2.0, 0.0, 1.0))

        left_eye = abs(by_index[159]['y'] - by_index[145]['y'])
        right_eye = abs(by_index[386]['y'] - by_index[374]['y'])
        eye_open = float(np.clip((left_eye + right_eye) * 10.0, 0.0, 1.0))

        # Engaged performing face: eyes wide open, mouth composed.
        return float(np.clip(eye_open * 70.0 + (1.0 - abs(mouth_open - 0.15)) * 30.0, 0.0, 100.0))

    def release(self) -> None:
        """Kept for API symmetry - this analyser owns no MediaPipe graph."""
        return None
