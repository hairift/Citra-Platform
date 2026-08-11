"""AI package for the CITRA platform."""

from .pose_utils import (
    ANGLE_NAMES,
    BODY_LANDMARKS,
    FEATURE_DIM,
    POSE_LANDMARK_NAMES,
    compute_joint_angles,
    dtw_distance,
    landmark_dicts_to_array,
    normalize_pose,
    pose_feature_vector,
    to_jsonable,
)
from .pose_estimator import ExpressionAnalyzer, PoseEstimator
from .rhythm_analyzer import GamelanBeatDetector, RhythmAnalyzer
from .evaluation_engine import (
    PerformanceEvaluator,
    ScoreCalculator,
    SessionEvaluator,
    SessionRegistry,
    grade_for,
)

__all__ = [
    # pose geometry
    'POSE_LANDMARK_NAMES', 'BODY_LANDMARKS', 'ANGLE_NAMES', 'FEATURE_DIM',
    'compute_joint_angles', 'normalize_pose', 'pose_feature_vector',
    'landmark_dicts_to_array', 'dtw_distance', 'to_jsonable',
    # estimators
    'PoseEstimator', 'ExpressionAnalyzer',
    'RhythmAnalyzer', 'GamelanBeatDetector',
    # scoring
    'PerformanceEvaluator', 'ScoreCalculator',
    'SessionEvaluator', 'SessionRegistry', 'grade_for',
]

# DatasetBuilder and the deep models pull in mediapipe / tensorflow, which are
# slow to import. They are intentionally NOT re-exported here - import them
# directly (``from ai.deep_models import GerakanClassifier``) so the Flask app
# starts fast.
