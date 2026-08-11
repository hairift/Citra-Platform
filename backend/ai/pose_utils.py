"""
Shared pose geometry helpers for the CITRA platform.

Everything that converts raw MediaPipe landmarks into the normalised,
scale/translation invariant representation used by the evaluation engine,
the dataset builder and the deep-learning models lives here so that the
*exact same* maths is used at training time and at inference time.
"""

from typing import Dict, List, Optional, Sequence, Tuple

import numpy as np

# ---------------------------------------------------------------------------
# Landmark naming (MediaPipe Pose - 33 points)
# ---------------------------------------------------------------------------

POSE_LANDMARK_NAMES: List[str] = [
    'nose', 'left_eye_inner', 'left_eye', 'left_eye_outer',
    'right_eye_inner', 'right_eye', 'right_eye_outer',
    'left_ear', 'right_ear', 'mouth_left', 'mouth_right',
    'left_shoulder', 'right_shoulder', 'left_elbow', 'right_elbow',
    'left_wrist', 'right_wrist', 'left_pinky', 'right_pinky',
    'left_index', 'right_index', 'left_thumb', 'right_thumb',
    'left_hip', 'right_hip', 'left_knee', 'right_knee',
    'left_ankle', 'right_ankle', 'left_heel', 'right_heel',
    'left_foot_index', 'right_foot_index',
]

BODY_LANDMARKS: Dict[str, int] = {name: i for i, name in enumerate(POSE_LANDMARK_NAMES)}

# Indonesian labels used in user-facing feedback
BODY_PART_LABELS_ID: Dict[str, str] = {
    'nose': 'hidung',
    'left_shoulder': 'bahu kiri', 'right_shoulder': 'bahu kanan',
    'left_elbow': 'siku kiri', 'right_elbow': 'siku kanan',
    'left_wrist': 'pergelangan tangan kiri', 'right_wrist': 'pergelangan tangan kanan',
    'left_hip': 'pinggul kiri', 'right_hip': 'pinggul kanan',
    'left_knee': 'lutut kiri', 'right_knee': 'lutut kanan',
    'left_ankle': 'pergelangan kaki kiri', 'right_ankle': 'pergelangan kaki kanan',
    'left_heel': 'tumit kiri', 'right_heel': 'tumit kanan',
    'left_foot_index': 'ujung kaki kiri', 'right_foot_index': 'ujung kaki kanan',
}

# The 12 joint angles that actually matter for Tari Topeng scoring.
# Each entry is (a, b, c) -> angle at vertex b.
ANGLE_JOINTS: List[Tuple[str, str, str]] = [
    ('left_shoulder', 'left_elbow', 'left_wrist'),        # siku kiri
    ('right_shoulder', 'right_elbow', 'right_wrist'),     # siku kanan
    ('left_elbow', 'left_shoulder', 'left_hip'),          # ketiak kiri
    ('right_elbow', 'right_shoulder', 'right_hip'),       # ketiak kanan
    ('left_shoulder', 'left_hip', 'left_knee'),           # pinggul kiri
    ('right_shoulder', 'right_hip', 'right_knee'),        # pinggul kanan
    ('left_hip', 'left_knee', 'left_ankle'),              # lutut kiri
    ('right_hip', 'right_knee', 'right_ankle'),           # lutut kanan
    ('left_knee', 'left_ankle', 'left_foot_index'),       # pergelangan kaki kiri
    ('right_knee', 'right_ankle', 'right_foot_index'),    # pergelangan kaki kanan
    ('right_shoulder', 'left_shoulder', 'left_elbow'),    # bukaan bahu kiri
    ('left_shoulder', 'right_shoulder', 'right_elbow'),   # bukaan bahu kanan
]

ANGLE_NAMES: List[str] = [f'{a}_{b}_{c}' for a, b, c in ANGLE_JOINTS]

# Human readable (Indonesian) name for each angle, used in feedback strings.
ANGLE_LABELS_ID: Dict[str, str] = {
    'left_shoulder_left_elbow_left_wrist': 'tekukan siku kiri',
    'right_shoulder_right_elbow_right_wrist': 'tekukan siku kanan',
    'left_elbow_left_shoulder_left_hip': 'angkatan lengan kiri',
    'right_elbow_right_shoulder_right_hip': 'angkatan lengan kanan',
    'left_shoulder_left_hip_left_knee': 'kemiringan pinggul kiri',
    'right_shoulder_right_hip_right_knee': 'kemiringan pinggul kanan',
    'left_hip_left_knee_left_ankle': 'tekukan lutut kiri',
    'right_hip_right_knee_right_ankle': 'tekukan lutut kanan',
    'left_knee_left_ankle_left_foot_index': 'pergelangan kaki kiri',
    'right_knee_right_ankle_right_foot_index': 'pergelangan kaki kanan',
    'right_shoulder_left_shoulder_left_elbow': 'bukaan bahu kiri',
    'left_shoulder_right_shoulder_right_elbow': 'bukaan bahu kanan',
}

# Skeleton edges drawn on the annotated dataset frames.
POSE_CONNECTIONS: List[Tuple[int, int]] = [
    (11, 12),                                  # bahu
    (11, 13), (13, 15),                        # lengan kiri
    (12, 14), (14, 16),                        # lengan kanan
    (15, 17), (15, 19), (15, 21), (17, 19),    # telapak kiri
    (16, 18), (16, 20), (16, 22), (18, 20),    # telapak kanan
    (11, 23), (12, 24), (23, 24),              # badan
    (23, 25), (25, 27),                        # kaki kiri
    (24, 26), (26, 28),                        # kaki kanan
    (27, 29), (29, 31), (27, 31),              # telapak kaki kiri
    (28, 30), (30, 32), (28, 32),              # telapak kaki kanan
    (0, 11), (0, 12),                          # leher
]

# Subset of landmarks kept in the compact feature vector (torso + limbs, no face
# detail).  Face micro-landmarks add noise without helping Wiraga scoring.
FEATURE_LANDMARKS: List[str] = [
    'nose',
    'left_shoulder', 'right_shoulder',
    'left_elbow', 'right_elbow',
    'left_wrist', 'right_wrist',
    'left_index', 'right_index',
    'left_hip', 'right_hip',
    'left_knee', 'right_knee',
    'left_ankle', 'right_ankle',
    'left_foot_index', 'right_foot_index',
]
FEATURE_LANDMARK_IDX: List[int] = [BODY_LANDMARKS[n] for n in FEATURE_LANDMARKS]

# 17 landmarks * 3 coords + 12 angles (normalised) = 63 features per frame
FEATURE_DIM: int = len(FEATURE_LANDMARKS) * 3 + len(ANGLE_JOINTS)

_EPS = 1e-8


# ---------------------------------------------------------------------------
# Conversion helpers
# ---------------------------------------------------------------------------

def landmarks_to_array(landmarks) -> np.ndarray:
    """MediaPipe landmark list -> (33, 4) float32 array of [x, y, z, visibility]."""
    out = np.zeros((len(POSE_LANDMARK_NAMES), 4), dtype=np.float32)
    for i, lm in enumerate(landmarks):
        if i >= out.shape[0]:
            break
        out[i] = (lm.x, lm.y, lm.z, getattr(lm, 'visibility', 1.0))
    return out


def array_to_landmark_dicts(arr: np.ndarray) -> List[Dict]:
    """(33, 4) array -> JSON-serialisable list of named landmark dicts."""
    return [
        {
            'name': POSE_LANDMARK_NAMES[i],
            'index': i,
            'x': round(float(arr[i, 0]), 5),
            'y': round(float(arr[i, 1]), 5),
            'z': round(float(arr[i, 2]), 5),
            'visibility': round(float(arr[i, 3]), 4),
        }
        for i in range(min(arr.shape[0], len(POSE_LANDMARK_NAMES)))
    ]


def landmark_dicts_to_array(dicts: Sequence[Dict]) -> np.ndarray:
    """Inverse of :func:`array_to_landmark_dicts`, tolerant of missing points."""
    out = np.zeros((len(POSE_LANDMARK_NAMES), 4), dtype=np.float32)
    for item in dicts or []:
        idx = item.get('index')
        if idx is None:
            idx = BODY_LANDMARKS.get(item.get('name', ''))
        if idx is None or idx >= out.shape[0]:
            continue
        out[idx] = (
            float(item.get('x', 0.0)),
            float(item.get('y', 0.0)),
            float(item.get('z', 0.0)),
            float(item.get('visibility', 1.0)),
        )
    return out


# ---------------------------------------------------------------------------
# Geometry
# ---------------------------------------------------------------------------

def joint_angle(a: np.ndarray, b: np.ndarray, c: np.ndarray) -> float:
    """Angle in degrees at vertex ``b`` formed by points a-b-c (3D)."""
    ba = a[:3] - b[:3]
    bc = c[:3] - b[:3]
    denom = float(np.linalg.norm(ba) * np.linalg.norm(bc))
    if denom < _EPS:
        return 0.0
    cosine = float(np.dot(ba, bc) / denom)
    return float(np.degrees(np.arccos(np.clip(cosine, -1.0, 1.0))))


def compute_joint_angles(arr: np.ndarray) -> Dict[str, float]:
    """All 12 tracked joint angles, in degrees, keyed by ``ANGLE_NAMES``."""
    angles: Dict[str, float] = {}
    for (a, b, c), name in zip(ANGLE_JOINTS, ANGLE_NAMES):
        angles[name] = round(
            joint_angle(arr[BODY_LANDMARKS[a]], arr[BODY_LANDMARKS[b]], arr[BODY_LANDMARKS[c]]),
            2,
        )
    return angles


def normalize_pose(arr: np.ndarray) -> np.ndarray:
    """
    Translation + scale + roll invariant pose.

    Origin is the mid-hip, the vertical axis is aligned with the mid-hip ->
    mid-shoulder vector, and everything is divided by the torso length.  Two
    dancers of different height standing at different spots in the frame
    therefore produce near-identical vectors for the same posture.
    """
    out = arr.copy().astype(np.float32)
    lh, rh = BODY_LANDMARKS['left_hip'], BODY_LANDMARKS['right_hip']
    ls, rs = BODY_LANDMARKS['left_shoulder'], BODY_LANDMARKS['right_shoulder']

    mid_hip = (out[lh, :3] + out[rh, :3]) / 2.0
    mid_shoulder = (out[ls, :3] + out[rs, :3]) / 2.0

    out[:, :3] -= mid_hip

    torso = float(np.linalg.norm(mid_shoulder - mid_hip))
    if torso < _EPS:
        # Degenerate detection - fall back to the overall spread so we never
        # divide by zero and blow the feature vector up to infinity.
        torso = float(np.linalg.norm(out[:, :3].std(axis=0))) or 1.0
    out[:, :3] /= torso

    # De-rotate around the image plane so a tilted camera does not change the
    # posture signature (roll only; yaw/pitch carry real dance information).
    spine = out[ls, :3] + out[rs, :3]
    spine = spine / 2.0
    roll = float(np.arctan2(spine[0], -spine[1])) if np.linalg.norm(spine[:2]) > _EPS else 0.0
    cos_r, sin_r = float(np.cos(-roll)), float(np.sin(-roll))
    x = out[:, 0].copy()
    y = out[:, 1].copy()
    out[:, 0] = x * cos_r - y * sin_r
    out[:, 1] = x * sin_r + y * cos_r
    return out


def pose_feature_vector(arr: np.ndarray) -> np.ndarray:
    """
    Fixed-length (``FEATURE_DIM``,) descriptor for one frame.

    Layout: 17 normalised landmark triplets followed by the 12 joint angles
    scaled to [0, 1].  This is what the LSTM classifier and the autoencoder
    consume, and what DTW compares.
    """
    norm = normalize_pose(arr)
    coords = norm[FEATURE_LANDMARK_IDX, :3].reshape(-1)
    angles = np.array(
        [compute_joint_angles(arr)[name] for name in ANGLE_NAMES], dtype=np.float32
    ) / 180.0
    return np.concatenate([coords, angles]).astype(np.float32)


def pose_visibility(arr: np.ndarray, landmarks: Optional[Sequence[str]] = None) -> float:
    """Mean visibility over the landmarks that matter (default: feature set)."""
    idx = (
        [BODY_LANDMARKS[n] for n in landmarks]
        if landmarks is not None
        else FEATURE_LANDMARK_IDX
    )
    return float(np.mean(arr[idx, 3]))


def body_orientation(arr: np.ndarray) -> Dict[str, float]:
    """Yaw / lean / openness of the torso, in degrees."""
    ls, rs = arr[BODY_LANDMARKS['left_shoulder']], arr[BODY_LANDMARKS['right_shoulder']]
    lh, rh = arr[BODY_LANDMARKS['left_hip']], arr[BODY_LANDMARKS['right_hip']]

    shoulder_vec = rs[:3] - ls[:3]
    hip_vec = rh[:3] - lh[:3]
    mid_shoulder = (ls[:3] + rs[:3]) / 2.0
    mid_hip = (lh[:3] + rh[:3]) / 2.0
    spine = mid_shoulder - mid_hip

    yaw = float(np.degrees(np.arctan2(shoulder_vec[2], shoulder_vec[0] + _EPS)))
    lean = float(np.degrees(np.arctan2(spine[0], -spine[1] + _EPS)))
    twist = float(
        np.degrees(
            np.arctan2(shoulder_vec[2], shoulder_vec[0] + _EPS)
            - np.arctan2(hip_vec[2], hip_vec[0] + _EPS)
        )
    )
    return {
        'yaw': round(yaw, 2),
        'lean': round(lean, 2),
        'twist': round(((twist + 180.0) % 360.0) - 180.0, 2),
        'shoulder_width': round(float(np.linalg.norm(shoulder_vec[:2])), 4),
        'hip_width': round(float(np.linalg.norm(hip_vec[:2])), 4),
    }


def head_orientation_from_pose(arr: np.ndarray) -> Dict[str, float]:
    """
    Head yaw/pitch/roll estimated from the pose landmarks alone.

    Used as a fallback when the face mesh is not available (far shots, masked
    dancers - which is exactly the Tari Topeng case).
    """
    nose = arr[BODY_LANDMARKS['nose']]
    le, re = arr[BODY_LANDMARKS['left_ear']], arr[BODY_LANDMARKS['right_ear']]
    ls, rs = arr[BODY_LANDMARKS['left_shoulder']], arr[BODY_LANDMARKS['right_shoulder']]

    ear_vec = re[:3] - le[:3]
    roll = float(np.degrees(np.arctan2(ear_vec[1], ear_vec[0] + _EPS)))

    mid_ear = (le[:3] + re[:3]) / 2.0
    mid_shoulder = (ls[:3] + rs[:3]) / 2.0
    neck = mid_ear - mid_shoulder
    pitch = float(np.degrees(np.arctan2(neck[2], -neck[1] + _EPS)))

    # How far the nose sits from the midpoint of the ears => left/right turn
    ear_span = float(np.linalg.norm(ear_vec[:2])) or _EPS
    yaw = float(np.degrees(np.arcsin(np.clip((nose[0] - mid_ear[0]) / ear_span, -1.0, 1.0)))) * 2.0

    return {
        'yaw': round(yaw, 2),
        'pitch': round(((pitch + 180.0) % 360.0) - 180.0, 2),
        'roll': round(((roll + 180.0) % 360.0) - 180.0, 2),
    }


# ---------------------------------------------------------------------------
# Comparison
# ---------------------------------------------------------------------------

def angle_similarity(
    user_angles: Dict[str, float],
    ref_angles: Dict[str, float],
    tolerance: float = 12.0,
) -> Tuple[float, List[Tuple[str, float]]]:
    """
    Compare two angle dictionaries.

    Returns ``(score_0_100, [(angle_name, abs_diff_deg), ...])`` with the
    deviations sorted worst-first.  The score decays smoothly rather than
    linearly so that a single bad joint does not destroy an otherwise good
    pose, while a systematically wrong posture still scores low.
    """
    diffs: List[Tuple[str, float]] = []
    for name in ANGLE_NAMES:
        if name not in user_angles or name not in ref_angles:
            continue
        diffs.append((name, abs(float(user_angles[name]) - float(ref_angles[name]))))

    if not diffs:
        return 0.0, []

    errors = np.array([d for _, d in diffs], dtype=np.float32)
    # Gaussian falloff: 0 deg -> 100, `tolerance` deg -> ~61, 2x -> ~14
    per_joint = 100.0 * np.exp(-0.5 * (errors / max(tolerance, _EPS)) ** 2)
    score = float(np.mean(per_joint))

    diffs.sort(key=lambda t: t[1], reverse=True)
    return round(score, 2), diffs


def dtw_distance(seq_a: np.ndarray, seq_b: np.ndarray, band: Optional[int] = None) -> float:
    """
    Normalised Dynamic Time Warping distance between two (T, D) sequences.

    A Sakoe-Chiba band keeps it O(T * band) so it stays real-time friendly.
    Returns the accumulated cost divided by the warping path length, so the
    value is comparable across sequences of different duration.
    """
    a = np.asarray(seq_a, dtype=np.float32)
    b = np.asarray(seq_b, dtype=np.float32)
    if a.ndim == 1:
        a = a[None, :]
    if b.ndim == 1:
        b = b[None, :]
    n, m = a.shape[0], b.shape[0]
    if n == 0 or m == 0:
        return float('inf')

    if band is None:
        band = max(10, int(0.2 * max(n, m)))

    inf = np.float32(np.inf)
    prev = np.full(m + 1, inf, dtype=np.float32)
    curr = np.full(m + 1, inf, dtype=np.float32)
    prev[0] = 0.0

    for i in range(1, n + 1):
        curr[:] = inf
        j_start = max(1, i - band)
        j_end = min(m, i + band)
        for j in range(j_start, j_end + 1):
            cost = float(np.linalg.norm(a[i - 1] - b[j - 1]))
            curr[j] = cost + min(prev[j], curr[j - 1], prev[j - 1])
        prev, curr = curr, prev

    total = float(prev[m])
    if not np.isfinite(total):
        return float('inf')
    return total / float(n + m)


def resample_sequence(seq: np.ndarray, length: int) -> np.ndarray:
    """Linearly resample a (T, D) sequence to exactly ``length`` timesteps."""
    seq = np.asarray(seq, dtype=np.float32)
    if seq.ndim == 1:
        seq = seq[:, None]
    t = seq.shape[0]
    if t == 0:
        return np.zeros((length, seq.shape[1]), dtype=np.float32)
    if t == length:
        return seq
    src = np.linspace(0.0, 1.0, t)
    dst = np.linspace(0.0, 1.0, length)
    return np.stack(
        [np.interp(dst, src, seq[:, d]) for d in range(seq.shape[1])], axis=1
    ).astype(np.float32)


def to_jsonable(value):
    """Recursively convert numpy scalars/arrays into plain Python types."""
    if isinstance(value, (np.integer,)):
        return int(value)
    if isinstance(value, (np.floating,)):
        return float(value)
    if isinstance(value, (np.bool_,)):
        return bool(value)
    if isinstance(value, np.ndarray):
        return [to_jsonable(v) for v in value.tolist()]
    if isinstance(value, dict):
        return {k: to_jsonable(v) for k, v in value.items()}
    if isinstance(value, (list, tuple)):
        return [to_jsonable(v) for v in value]
    return value
