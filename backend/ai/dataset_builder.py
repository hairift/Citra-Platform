"""
Golden-dataset builder for CITRA.

Runs MediaPipe Holistic over a maestro video and produces:

  * ``<slug>_keypoints.json``   full per-frame joint data (33 pose landmarks,
                               21+21 hand landmarks, 12 joint angles, torso and
                               head orientation, visibility)
  * ``<slug>_features.npy``     (T, 63) float32 matrix -> deep-learning input
  * ``frames/<slug>_####.jpg``  annotated stills with the joint dots drawn on
  * ``<slug>_segments.json``    auto-discovered gerakan phases

Accuracy notes
--------------
* ``model_complexity=2`` (MediaPipe's heaviest, most accurate pose model).
* Frames are processed **in temporal order with tracking enabled**, so the
  landmark filter has history - markedly more stable than static-image mode.
* Low-visibility frames are recorded but flagged, and the temporal smoother
  interpolates across them instead of letting a bad detection through.
* A One-Euro filter removes jitter without the lag of a moving average.
"""

from __future__ import annotations

import json
import os
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Tuple

import cv2
import numpy as np

from .pose_utils import (
    ANGLE_NAMES,
    BODY_LANDMARKS,
    FEATURE_DIM,
    POSE_CONNECTIONS,
    POSE_LANDMARK_NAMES,
    array_to_landmark_dicts,
    body_orientation,
    compute_joint_angles,
    head_orientation_from_pose,
    landmarks_to_array,
    pose_feature_vector,
    pose_visibility,
    to_jsonable,
)

# Colour palette for the annotated frames (BGR).
COLOR_SKELETON = (32, 90, 232)      # CITRA orange
COLOR_JOINT = (255, 255, 255)
COLOR_JOINT_LOW = (80, 80, 200)     # low-confidence joint
COLOR_HAND_L = (68, 255, 68)
COLOR_HAND_R = (68, 68, 255)
COLOR_TEXT = (255, 255, 255)

HAND_CONNECTIONS: List[Tuple[int, int]] = [
    (0, 1), (1, 2), (2, 3), (3, 4),
    (0, 5), (5, 6), (6, 7), (7, 8),
    (5, 9), (9, 10), (10, 11), (11, 12),
    (9, 13), (13, 14), (14, 15), (15, 16),
    (13, 17), (17, 18), (18, 19), (19, 20), (0, 17),
]


# ---------------------------------------------------------------------------
# One-Euro filter - jitter removal that keeps fast movement sharp
# ---------------------------------------------------------------------------

class OneEuroFilter:
    """Low-pass filter with a velocity-adaptive cutoff (Casiez et al., 2012)."""

    def __init__(self, freq: float, min_cutoff: float = 1.0,
                 beta: float = 0.35, d_cutoff: float = 1.0):
        self.freq = max(freq, 1e-3)
        self.min_cutoff = min_cutoff
        self.beta = beta
        self.d_cutoff = d_cutoff
        self._x_prev: Optional[np.ndarray] = None
        self._dx_prev: Optional[np.ndarray] = None

    @staticmethod
    def _alpha(cutoff: float, freq: float) -> float:
        tau = 1.0 / (2.0 * np.pi * max(cutoff, 1e-6))
        te = 1.0 / freq
        return float(1.0 / (1.0 + tau / te))

    def __call__(self, x: np.ndarray) -> np.ndarray:
        x = np.asarray(x, dtype=np.float32)
        if self._x_prev is None:
            self._x_prev = x.copy()
            self._dx_prev = np.zeros_like(x)
            return x

        dx = (x - self._x_prev) * self.freq
        a_d = self._alpha(self.d_cutoff, self.freq)
        dx_hat = a_d * dx + (1.0 - a_d) * self._dx_prev

        cutoff = self.min_cutoff + self.beta * np.abs(dx_hat)
        # vectorised alpha
        tau = 1.0 / (2.0 * np.pi * np.maximum(cutoff, 1e-6))
        te = 1.0 / self.freq
        a = (1.0 / (1.0 + tau / te)).astype(np.float32)

        x_hat = a * x + (1.0 - a) * self._x_prev
        self._x_prev = x_hat
        self._dx_prev = dx_hat
        return x_hat

    def reset(self) -> None:
        self._x_prev = None
        self._dx_prev = None


# ---------------------------------------------------------------------------

@dataclass
class FrameRecord:
    frame_index: int
    timestamp: float
    detected: bool
    visibility: float
    landmarks: List[Dict] = field(default_factory=list)
    world_landmarks: List[Dict] = field(default_factory=list)
    angles: Dict[str, float] = field(default_factory=dict)
    orientation: Dict[str, float] = field(default_factory=dict)
    head: Dict[str, float] = field(default_factory=dict)
    left_hand: List[Dict] = field(default_factory=list)
    right_hand: List[Dict] = field(default_factory=list)


class DatasetBuilder:
    """Extracts an accurate, training-ready pose dataset from a dance video."""

    def __init__(
        self,
        sample_fps: float = 6.0,
        min_detection_confidence: float = 0.6,
        min_tracking_confidence: float = 0.6,
        model_complexity: int = 2,
        smooth: bool = True,
    ):
        self.sample_fps = sample_fps
        self.min_detection_confidence = min_detection_confidence
        self.min_tracking_confidence = min_tracking_confidence
        self.model_complexity = model_complexity
        self.smooth = smooth

    # -- public API --------------------------------------------------------

    def process_video(
        self,
        video_path: str,
        out_dir: str,
        slug: str,
        karakter: str = 'klana',
        gerakan_name: str = 'Tari Topeng Klana',
        annotate_every: int = 12,
        max_annotated: int = 120,
        progress_every: int = 200,
    ) -> Dict:
        """
        Process one video end-to-end.

        ``annotate_every`` is counted in *sampled* frames, so with the default
        sample_fps=6 and annotate_every=12 you get one annotated still every
        two seconds, capped at ``max_annotated`` files.
        """
        import mediapipe as mp

        if not os.path.isfile(video_path):
            raise FileNotFoundError(video_path)

        frames_dir = os.path.join(out_dir, 'frames')
        keypoints_dir = os.path.join(out_dir, 'keypoints')
        os.makedirs(frames_dir, exist_ok=True)
        os.makedirs(keypoints_dir, exist_ok=True)

        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            raise RuntimeError(f'Cannot open video: {video_path}')

        src_fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
        total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT) or 0)
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH) or 0)
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT) or 0)
        step = max(1, int(round(src_fps / max(self.sample_fps, 0.1))))

        print(f'[dataset] {os.path.basename(video_path)}  '
              f'{width}x{height} @ {src_fps:.2f}fps  '
              f'{total_frames} frames  -> sampling every {step} frames')

        holistic = mp.solutions.holistic.Holistic(
            static_image_mode=False,
            model_complexity=self.model_complexity,
            smooth_landmarks=True,
            enable_segmentation=False,
            refine_face_landmarks=False,
            min_detection_confidence=self.min_detection_confidence,
            min_tracking_confidence=self.min_tracking_confidence,
        )

        pose_filter = OneEuroFilter(freq=self.sample_fps, min_cutoff=1.2, beta=0.4)

        records: List[FrameRecord] = []
        features: List[np.ndarray] = []
        annotated_files: List[str] = []
        annotated_count = 0
        sampled = 0
        frame_index = 0
        detected_count = 0

        # Downscale huge (4K) source frames before inference: MediaPipe resizes
        # internally anyway, and this keeps 8-minute 4K clips tractable.
        target_width = 960

        try:
            while True:
                ok = cap.grab()
                if not ok:
                    break

                if frame_index % step != 0:
                    frame_index += 1
                    continue

                ok, frame = cap.retrieve()
                if not ok or frame is None:
                    frame_index += 1
                    continue

                # Keep the full-resolution frame for the annotated stills; run
                # inference on a downscaled copy so 4K clips stay tractable.
                full_frame = frame
                if width > target_width:
                    scale = target_width / float(width)
                    frame = cv2.resize(
                        frame, (target_width, int(round(height * scale))),
                        interpolation=cv2.INTER_AREA,
                    )

                timestamp = frame_index / src_fps
                rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                rgb.flags.writeable = False
                results = holistic.process(rgb)

                record, arr = self._build_record(
                    results, frame_index, timestamp, pose_filter
                )
                records.append(record)

                if record.detected:
                    detected_count += 1
                    features.append(pose_feature_vector(arr))
                else:
                    features.append(
                        features[-1] if features else np.zeros(FEATURE_DIM, dtype=np.float32)
                    )

                if (record.detected
                        and sampled % annotate_every == 0
                        and annotated_count < max_annotated):
                    fname = f'{slug}_{annotated_count:04d}.jpg'
                    self._save_annotated(
                        full_frame, arr, record, os.path.join(frames_dir, fname),
                        title=f'{karakter.title()} | t={timestamp:6.2f}s',
                    )
                    annotated_files.append(fname)
                    annotated_count += 1

                sampled += 1
                frame_index += 1

                if progress_every and sampled % progress_every == 0:
                    pct = (100.0 * frame_index / total_frames) if total_frames else 0.0
                    print(f'[dataset]   {slug}: {sampled} sampled '
                          f'({pct:5.1f}%) detected={detected_count}')
        finally:
            cap.release()
            holistic.close()

        feature_matrix = (
            np.stack(features).astype(np.float32)
            if features else np.zeros((0, FEATURE_DIM), dtype=np.float32)
        )

        segments = self.segment_movements(feature_matrix, self.sample_fps)

        # ---- persist -----------------------------------------------------
        npy_path = os.path.join(keypoints_dir, f'{slug}_features.npy')
        np.save(npy_path, feature_matrix)

        meta = {
            'slug': slug,
            'karakter': karakter,
            'gerakan_name': gerakan_name,
            'source_video': os.path.basename(video_path),
            'source_fps': round(float(src_fps), 3),
            'source_resolution': [width, height],
            'source_frames': total_frames,
            'duration_seconds': round(total_frames / src_fps, 2) if src_fps else 0.0,
            'sample_fps': self.sample_fps,
            'sampled_frames': len(records),
            'detected_frames': detected_count,
            'detection_rate': round(detected_count / max(len(records), 1), 4),
            'model_complexity': self.model_complexity,
            'feature_dim': FEATURE_DIM,
            'landmark_names': POSE_LANDMARK_NAMES,
            'angle_names': ANGLE_NAMES,
            'annotated_frames': annotated_files,
            'features_file': os.path.basename(npy_path),
        }

        keypoints_payload = {
            'meta': meta,
            'frames': [self._record_to_dict(r) for r in records],
        }
        json_path = os.path.join(keypoints_dir, f'{slug}_keypoints.json')
        with open(json_path, 'w', encoding='utf-8') as fh:
            json.dump(to_jsonable(keypoints_payload), fh, ensure_ascii=False)

        seg_path = os.path.join(keypoints_dir, f'{slug}_segments.json')
        with open(seg_path, 'w', encoding='utf-8') as fh:
            json.dump(to_jsonable({'meta': meta, 'segments': segments}), fh,
                      ensure_ascii=False, indent=2)

        # Compact "golden keyframes" for runtime comparison (2 per second).
        keyframes = self.build_keyframes(records, every=max(1, int(self.sample_fps // 2)))
        kf_path = os.path.join(keypoints_dir, f'{slug}_keyframes.json')
        with open(kf_path, 'w', encoding='utf-8') as fh:
            json.dump(to_jsonable(keyframes), fh, ensure_ascii=False)

        print(f'[dataset] {slug}: done. sampled={len(records)} '
              f'detected={detected_count} ({100*meta["detection_rate"]:.1f}%) '
              f'annotated={annotated_count} segments={len(segments)}')

        return {
            'meta': meta,
            'segments': segments,
            'keyframes_file': os.path.basename(kf_path),
            'keypoints_file': os.path.basename(json_path),
            'segments_file': os.path.basename(seg_path),
            'features_file': os.path.basename(npy_path),
            'annotated_dir': frames_dir,
        }

    # -- internals ---------------------------------------------------------

    def _build_record(
        self, results, frame_index: int, timestamp: float, pose_filter: OneEuroFilter
    ) -> Tuple[FrameRecord, np.ndarray]:
        if not results.pose_landmarks:
            pose_filter.reset()
            return (
                FrameRecord(frame_index, round(timestamp, 3), False, 0.0),
                np.zeros((len(POSE_LANDMARK_NAMES), 4), dtype=np.float32),
            )

        arr = landmarks_to_array(results.pose_landmarks.landmark)

        if self.smooth:
            xyz = pose_filter(arr[:, :3])
            arr = np.concatenate([xyz, arr[:, 3:4]], axis=1)

        visibility = pose_visibility(arr)

        world = []
        if getattr(results, 'pose_world_landmarks', None):
            world = array_to_landmark_dicts(
                landmarks_to_array(results.pose_world_landmarks.landmark)
            )

        record = FrameRecord(
            frame_index=frame_index,
            timestamp=round(timestamp, 3),
            detected=True,
            visibility=round(visibility, 4),
            landmarks=array_to_landmark_dicts(arr),
            world_landmarks=world,
            angles=compute_joint_angles(arr),
            orientation=body_orientation(arr),
            head=head_orientation_from_pose(arr),
            left_hand=self._hand_to_dicts(results.left_hand_landmarks),
            right_hand=self._hand_to_dicts(results.right_hand_landmarks),
        )
        return record, arr

    @staticmethod
    def _hand_to_dicts(hand_landmarks) -> List[Dict]:
        if not hand_landmarks:
            return []
        return [
            {
                'index': i,
                'x': round(float(lm.x), 5),
                'y': round(float(lm.y), 5),
                'z': round(float(lm.z), 5),
            }
            for i, lm in enumerate(hand_landmarks.landmark)
        ]

    @staticmethod
    def _record_to_dict(r: FrameRecord) -> Dict:
        return {
            'frame_index': r.frame_index,
            'timestamp': r.timestamp,
            'detected': r.detected,
            'visibility': r.visibility,
            'landmarks': r.landmarks,
            'world_landmarks': r.world_landmarks,
            'angles': r.angles,
            'orientation': r.orientation,
            'head': r.head,
            'left_hand': r.left_hand,
            'right_hand': r.right_hand,
        }

    # -- annotated stills --------------------------------------------------

    @staticmethod
    def _person_crop(
        arr: np.ndarray, w: int, h: int, pad: float = 0.35, aspect: float = 16 / 9
    ) -> Tuple[int, int, int, int]:
        """
        Bounding box around the dancer, padded and forced to ``aspect``.

        Wide stage shots leave the dancer tiny in a 4K frame; cropping to the
        body makes the annotated dataset stills actually readable.
        """
        vis = arr[:, 3] >= 0.2
        if not vis.any():
            return 0, 0, w, h

        xs = arr[vis, 0] * w
        ys = arr[vis, 1] * h
        x0, x1 = float(xs.min()), float(xs.max())
        y0, y1 = float(ys.min()), float(ys.max())

        bw, bh = max(x1 - x0, 1.0), max(y1 - y0, 1.0)
        cx, cy = (x0 + x1) / 2.0, (y0 + y1) / 2.0

        bw *= (1.0 + 2 * pad)
        bh *= (1.0 + 2 * pad)

        # Force the requested aspect ratio by growing the short side.
        if bw / bh < aspect:
            bw = bh * aspect
        else:
            bh = bw / aspect

        # Clamp inside the frame without changing the aspect ratio.
        bw = min(bw, float(w))
        bh = min(bh, float(h))
        if bw / bh > aspect:
            bw = bh * aspect
        else:
            bh = bw / aspect

        left = int(round(min(max(cx - bw / 2.0, 0.0), w - bw)))
        top = int(round(min(max(cy - bh / 2.0, 0.0), h - bh)))
        return left, top, int(round(bw)), int(round(bh))

    @staticmethod
    def _save_annotated(
        frame: np.ndarray,
        arr: np.ndarray,
        record: FrameRecord,
        out_path: str,
        title: str = '',
        vis_threshold: float = 0.5,
        crop_to_person: bool = True,
        output_width: int = 960,
    ) -> None:
        """Draw the skeleton + joint dots + angle read-outs onto the frame."""
        src_h, src_w = frame.shape[:2]

        if crop_to_person:
            cx0, cy0, cw, ch = DatasetBuilder._person_crop(arr, src_w, src_h)
        else:
            cx0, cy0, cw, ch = 0, 0, src_w, src_h

        img = frame[cy0:cy0 + ch, cx0:cx0 + cw].copy()
        if img.size == 0:
            img = frame.copy()
            cx0, cy0, cw, ch = 0, 0, src_w, src_h

        # Upscale small crops so the joints are clearly visible.
        scale = output_width / float(cw)
        img = cv2.resize(
            img, (output_width, max(1, int(round(ch * scale)))),
            interpolation=cv2.INTER_CUBIC if scale > 1 else cv2.INTER_AREA,
        )
        h, w = img.shape[:2]

        def px(i: int) -> Tuple[int, int]:
            return (
                int(round((arr[i, 0] * src_w - cx0) * scale)),
                int(round((arr[i, 1] * src_h - cy0) * scale)),
            )

        # Stroke sizes scale with the dancer so close-ups and wide shots look
        # equally clean.
        vis_mask = arr[:, 3] >= 0.2
        body_px = (
            float((arr[vis_mask, 1].max() - arr[vis_mask, 1].min()) * src_h * scale)
            if vis_mask.any() else h
        )
        line_w = max(2, int(round(body_px / 180.0)))
        dot_r = max(3, int(round(body_px / 110.0)))
        hand_r = max(2, dot_r - 2)

        # Skeleton edges - dimmed when either endpoint is uncertain.
        for a, b in POSE_CONNECTIONS:
            va, vb = arr[a, 3], arr[b, 3]
            if va < 0.2 or vb < 0.2:
                continue
            confident = va >= vis_threshold and vb >= vis_threshold
            cv2.line(
                img, px(a), px(b),
                COLOR_SKELETON if confident else (120, 120, 120),
                line_w if confident else max(1, line_w - 1),
                cv2.LINE_AA,
            )

        # Hands
        for hand, colour in ((record.left_hand, COLOR_HAND_L),
                             (record.right_hand, COLOR_HAND_R)):
            if not hand:
                continue
            pts = [
                (int(round((p['x'] * src_w - cx0) * scale)),
                 int(round((p['y'] * src_h - cy0) * scale)))
                for p in hand
            ]
            for a, b in HAND_CONNECTIONS:
                if a < len(pts) and b < len(pts):
                    cv2.line(img, pts[a], pts[b], colour, max(1, line_w - 1), cv2.LINE_AA)
            for p in pts:
                cv2.circle(img, p, hand_r, colour, -1, cv2.LINE_AA)

        # Joint dots - white for confident, muted red for uncertain.
        for i in range(len(POSE_LANDMARK_NAMES)):
            if arr[i, 3] < 0.2:
                continue
            p = px(i)
            confident = arr[i, 3] >= vis_threshold
            cv2.circle(img, p, dot_r + 2, (0, 0, 0), -1, cv2.LINE_AA)
            cv2.circle(img, p, dot_r,
                       COLOR_JOINT if confident else COLOR_JOINT_LOW,
                       -1, cv2.LINE_AA)

        # Label the joints a dancer is actually corrected on.
        labelled = [
            ('left_shoulder', 'bahu.ki'), ('right_shoulder', 'bahu.ka'),
            ('left_elbow', 'siku.ki'), ('right_elbow', 'siku.ka'),
            ('left_wrist', 'tangan.ki'), ('right_wrist', 'tangan.ka'),
            ('left_hip', 'pinggul.ki'), ('right_hip', 'pinggul.ka'),
            ('left_knee', 'lutut.ki'), ('right_knee', 'lutut.ka'),
            ('left_ankle', 'kaki.ki'), ('right_ankle', 'kaki.ka'),
        ]
        font_scale = max(0.32, min(0.5, body_px / 1400.0))
        for name, label in labelled:
            i = BODY_LANDMARKS[name]
            if arr[i, 3] < vis_threshold:
                continue
            x, y = px(i)
            cv2.putText(img, label, (x + dot_r + 3, y - dot_r - 2),
                        cv2.FONT_HERSHEY_SIMPLEX, font_scale, (0, 0, 0), 3, cv2.LINE_AA)
            cv2.putText(img, label, (x + dot_r + 3, y - dot_r - 2),
                        cv2.FONT_HERSHEY_SIMPLEX, font_scale, (180, 255, 255), 1, cv2.LINE_AA)

        # Header bar
        cv2.rectangle(img, (0, 0), (w, 34), (0, 0, 0), -1)
        cv2.putText(img, f'CITRA  {title}', (10, 23),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, COLOR_TEXT, 1, cv2.LINE_AA)
        cv2.putText(img, f'vis {record.visibility:.2f}', (w - 110, 23),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55,
                    (0, 220, 0) if record.visibility > 0.6 else (0, 200, 255),
                    1, cv2.LINE_AA)

        # Key angle read-out (bottom-left)
        readouts = [
            ('Siku Ki', 'left_shoulder_left_elbow_left_wrist'),
            ('Siku Ka', 'right_shoulder_right_elbow_right_wrist'),
            ('Lutut Ki', 'left_hip_left_knee_left_ankle'),
            ('Lutut Ka', 'right_hip_right_knee_right_ankle'),
        ]
        y = h - 12 - 20 * len(readouts)
        cv2.rectangle(img, (0, y - 18), (190, h), (0, 0, 0), -1)
        for label, key in readouts:
            val = record.angles.get(key, 0.0)
            cv2.putText(img, f'{label}: {val:6.1f}deg', (8, y),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.48, COLOR_TEXT, 1, cv2.LINE_AA)
            y += 20

        cv2.imwrite(out_path, img, [int(cv2.IMWRITE_JPEG_QUALITY), 88])

    # -- movement segmentation --------------------------------------------

    @staticmethod
    def segment_movements(
        features: np.ndarray,
        sample_fps: float,
        min_seconds: float = 2.0,
        energy_percentile: float = 35.0,
    ) -> List[Dict]:
        """
        Split a performance into gerakan phases without manual labelling.

        Motion energy (frame-to-frame feature delta) is smoothed, then local
        minima below the ``energy_percentile`` threshold are treated as phase
        boundaries - which is exactly where a dancer holds a pose between
        movements.  Each segment gets a descriptive tag from its kinematics.
        """
        if features.shape[0] < 4:
            return []

        delta = np.linalg.norm(np.diff(features, axis=0), axis=1)
        # Smooth with a ~0.5 s Hann window
        win = max(3, int(round(sample_fps * 0.5)) | 1)
        kernel = np.hanning(win)
        kernel /= kernel.sum()
        energy = np.convolve(delta, kernel, mode='same')

        threshold = float(np.percentile(energy, energy_percentile))
        min_len = max(2, int(round(min_seconds * sample_fps)))

        boundaries: List[int] = [0]
        i = 1
        while i < len(energy) - 1:
            is_min = energy[i] <= energy[i - 1] and energy[i] <= energy[i + 1]
            if is_min and energy[i] <= threshold and (i - boundaries[-1]) >= min_len:
                boundaries.append(i)
            i += 1
        boundaries.append(features.shape[0] - 1)

        segments: List[Dict] = []
        for idx, (start, end) in enumerate(zip(boundaries[:-1], boundaries[1:])):
            if end - start < min_len:
                continue
            seg_energy = float(np.mean(energy[start:end])) if end > start else 0.0
            peak_energy = float(np.max(energy[start:end])) if end > start else 0.0
            segments.append({
                'index': idx,
                'start_frame': int(start),
                'end_frame': int(end),
                'start_time': round(start / sample_fps, 2),
                'end_time': round(end / sample_fps, 2),
                'duration': round((end - start) / sample_fps, 2),
                'mean_energy': round(seg_energy, 5),
                'peak_energy': round(peak_energy, 5),
                'intensity': DatasetBuilder._intensity_label(seg_energy, energy),
            })

        # Re-index after filtering
        for i, seg in enumerate(segments):
            seg['index'] = i
            seg['label'] = f'gerakan_{i + 1:02d}'
        return segments

    @staticmethod
    def _intensity_label(value: float, energy: np.ndarray) -> str:
        lo, hi = np.percentile(energy, [33, 66])
        if value <= lo:
            return 'tenang'      # sustained / holding pose
        if value <= hi:
            return 'sedang'
        return 'dinamis'

    # -- runtime keyframes -------------------------------------------------

    @staticmethod
    def build_keyframes(records: List[FrameRecord], every: int = 3) -> List[Dict]:
        """
        Compact reference poses used by the live comparison engine.

        Only detected frames with usable visibility are kept, and only the
        fields the scorer actually reads - keeping the payload small enough to
        ship to the browser.
        """
        keyframes: List[Dict] = []
        for i, r in enumerate(records):
            if not r.detected or r.visibility < 0.45:
                continue
            if i % max(1, every) != 0:
                continue
            keyframes.append({
                'timestamp': r.timestamp,
                'angles': r.angles,
                'orientation': r.orientation,
                'head': r.head,
                'visibility': r.visibility,
                'landmarks': [
                    {'name': lm['name'], 'x': lm['x'], 'y': lm['y'],
                     'z': lm['z'], 'visibility': lm['visibility']}
                    for lm in r.landmarks
                ],
            })
        return keyframes
