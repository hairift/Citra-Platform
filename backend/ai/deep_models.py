"""
Deep-learning models for CITRA.

Three TensorFlow/Keras models, all trained on the pose sequences extracted from
the maestro recordings by ``build_dataset.py``:

1. ``GerakanClassifier``  - Bi-LSTM that names the gerakan phase a dancer is
   currently performing, from a sliding window of pose features.
2. ``PoseAutoencoder``    - LSTM autoencoder trained *only* on maestro frames.
   Its reconstruction error is a "how un-maestro-like is this movement"
   signal, which gives a Wiraga score even for gerakan the classifier has
   never seen.
3. ``TempoRegressor``     - small 1D-CNN mapping a movement window to its
   normalised speed, used to tell a dancer they are rushing or dragging.

All three degrade gracefully: if TensorFlow or the weights are unavailable the
loader returns ``None`` and the evaluation engine falls back to the geometric
(DTW + joint-angle) scorer, so the platform never hard-fails on a missing model.
"""

from __future__ import annotations

import json
import os
from typing import Dict, List, Optional, Tuple

import numpy as np

from .pose_utils import FEATURE_DIM

# TensorFlow is heavy and optional at request time - import lazily.
_TF = None


def _tf():
    global _TF
    if _TF is None:
        os.environ.setdefault('TF_CPP_MIN_LOG_LEVEL', '3')
        import tensorflow as tf  # noqa: WPS433 (deliberate lazy import)
        tf.get_logger().setLevel('ERROR')
        _TF = tf
    return _TF


WINDOW = 24          # 4 seconds at 6 fps
STRIDE = 4


# ---------------------------------------------------------------------------
# Windowing helpers
# ---------------------------------------------------------------------------

def make_windows(
    features: np.ndarray,
    labels: Optional[np.ndarray] = None,
    window: int = WINDOW,
    stride: int = STRIDE,
) -> Tuple[np.ndarray, Optional[np.ndarray]]:
    """Slice a (T, D) sequence into overlapping (N, window, D) windows."""
    features = np.asarray(features, dtype=np.float32)
    if features.shape[0] < window:
        pad = np.repeat(features[-1:] if features.shape[0] else
                        np.zeros((1, features.shape[1] if features.ndim > 1 else FEATURE_DIM),
                                 dtype=np.float32),
                        window - features.shape[0], axis=0)
        features = np.concatenate([features, pad], axis=0)

    xs, ys = [], []
    for start in range(0, features.shape[0] - window + 1, stride):
        xs.append(features[start:start + window])
        if labels is not None:
            # Majority label inside the window.
            seg = labels[start:start + window]
            vals, counts = np.unique(seg, return_counts=True)
            ys.append(vals[int(np.argmax(counts))])

    if not xs:
        return np.zeros((0, window, features.shape[1]), dtype=np.float32), None

    x = np.stack(xs).astype(np.float32)
    y = np.array(ys, dtype=np.int32) if labels is not None else None
    return x, y


def movement_energy(features: np.ndarray) -> np.ndarray:
    """Per-frame motion magnitude, used as the tempo-regression target."""
    if features.shape[0] < 2:
        return np.zeros((features.shape[0],), dtype=np.float32)
    delta = np.linalg.norm(np.diff(features, axis=0), axis=1)
    return np.concatenate([delta[:1], delta]).astype(np.float32)


# ---------------------------------------------------------------------------
# 1. Gerakan classifier
# ---------------------------------------------------------------------------

class GerakanClassifier:
    """Bi-LSTM sequence classifier over pose-feature windows."""

    def __init__(self, model=None, labels: Optional[List[str]] = None,
                 mean: Optional[np.ndarray] = None, std: Optional[np.ndarray] = None):
        self.model = model
        self.labels = labels or []
        self.mean = mean
        self.std = std

    # -- build / train -----------------------------------------------------

    @staticmethod
    def build(num_classes: int, window: int = WINDOW, feature_dim: int = FEATURE_DIM):
        tf = _tf()
        layers = tf.keras.layers
        model = tf.keras.Sequential([
            layers.Input(shape=(window, feature_dim)),
            layers.Masking(mask_value=0.0),
            layers.Bidirectional(layers.LSTM(96, return_sequences=True)),
            layers.Dropout(0.3),
            layers.Bidirectional(layers.LSTM(64)),
            layers.Dropout(0.3),
            layers.Dense(64, activation='relu'),
            layers.BatchNormalization(),
            layers.Dense(num_classes, activation='softmax'),
        ], name='gerakan_classifier')
        model.compile(
            optimizer=tf.keras.optimizers.Adam(1e-3),
            loss='sparse_categorical_crossentropy',
            metrics=['accuracy'],
        )
        return model

    def fit(self, x: np.ndarray, y: np.ndarray, labels: List[str],
            epochs: int = 60, batch_size: int = 32, validation_split: float = 0.2) -> Dict:
        tf = _tf()
        self.labels = labels
        self.mean = x.reshape(-1, x.shape[-1]).mean(axis=0)
        self.std = x.reshape(-1, x.shape[-1]).std(axis=0) + 1e-6
        xn = (x - self.mean) / self.std

        self.model = self.build(len(labels), x.shape[1], x.shape[2])
        callbacks = [
            tf.keras.callbacks.EarlyStopping(
                monitor='val_accuracy', patience=12, restore_best_weights=True, mode='max'),
            tf.keras.callbacks.ReduceLROnPlateau(
                monitor='val_loss', factor=0.5, patience=6, min_lr=1e-5),
        ]
        hist = self.model.fit(
            xn, y, epochs=epochs, batch_size=batch_size,
            validation_split=validation_split, callbacks=callbacks, verbose=2,
        )
        return {k: [float(v) for v in vals] for k, vals in hist.history.items()}

    # -- inference ---------------------------------------------------------

    def predict(self, window_features: np.ndarray) -> Dict:
        """Classify one (window, D) sequence. Returns label + confidence."""
        if self.model is None:
            return {'label': None, 'confidence': 0.0, 'probabilities': {}}

        x = np.asarray(window_features, dtype=np.float32)
        if x.ndim == 2:
            x = x[None, ...]
        if self.mean is not None:
            x = (x - self.mean) / self.std

        probs = self.model.predict(x, verbose=0)[0]
        idx = int(np.argmax(probs))
        return {
            'label': self.labels[idx] if idx < len(self.labels) else str(idx),
            'confidence': round(float(probs[idx]), 4),
            'probabilities': {
                self.labels[i] if i < len(self.labels) else str(i): round(float(p), 4)
                for i, p in enumerate(probs)
            },
        }

    # -- persistence -------------------------------------------------------

    def save(self, model_dir: str, name: str = 'gerakan_classifier') -> str:
        os.makedirs(model_dir, exist_ok=True)
        path = os.path.join(model_dir, f'{name}.keras')
        self.model.save(path)
        with open(os.path.join(model_dir, f'{name}_meta.json'), 'w', encoding='utf-8') as fh:
            json.dump({
                'labels': self.labels,
                'mean': self.mean.tolist() if self.mean is not None else None,
                'std': self.std.tolist() if self.std is not None else None,
                'window': WINDOW,
                'feature_dim': FEATURE_DIM,
            }, fh)
        return path

    @classmethod
    def load(cls, model_dir: str, name: str = 'gerakan_classifier') -> Optional['GerakanClassifier']:
        path = os.path.join(model_dir, f'{name}.keras')
        meta_path = os.path.join(model_dir, f'{name}_meta.json')
        if not (os.path.isfile(path) and os.path.isfile(meta_path)):
            return None
        try:
            tf = _tf()
            model = tf.keras.models.load_model(path, compile=False)
            with open(meta_path, 'r', encoding='utf-8') as fh:
                meta = json.load(fh)
            return cls(
                model=model,
                labels=meta.get('labels', []),
                mean=np.array(meta['mean'], dtype=np.float32) if meta.get('mean') else None,
                std=np.array(meta['std'], dtype=np.float32) if meta.get('std') else None,
            )
        except Exception as exc:  # pragma: no cover - defensive
            print(f'[deep_models] could not load {name}: {exc}')
            return None


# ---------------------------------------------------------------------------
# 2. Pose autoencoder - "how maestro-like is this movement?"
# ---------------------------------------------------------------------------

class PoseAutoencoder:
    """
    LSTM autoencoder trained on maestro sequences only.

    At inference the reconstruction error of a learner's window is compared
    against the error distribution measured on held-out maestro data, giving a
    calibrated 0-100 quality score that does not need a labelled reference.
    """

    def __init__(self, model=None, mean=None, std=None,
                 error_median: float = 0.0, error_scale: float = 1.0):
        self.model = model
        self.mean = mean
        self.std = std
        self.error_median = error_median
        self.error_scale = max(error_scale, 1e-6)

    @staticmethod
    def build(window: int = WINDOW, feature_dim: int = FEATURE_DIM, latent: int = 32):
        tf = _tf()
        layers = tf.keras.layers
        model = tf.keras.Sequential([
            layers.Input(shape=(window, feature_dim)),
            layers.LSTM(96, return_sequences=False),
            layers.Dense(latent, activation='tanh', name='latent'),
            layers.RepeatVector(window),
            layers.LSTM(96, return_sequences=True),
            layers.TimeDistributed(layers.Dense(feature_dim)),
        ], name='pose_autoencoder')
        model.compile(optimizer=tf.keras.optimizers.Adam(1e-3), loss='mse')
        return model

    def fit(self, x: np.ndarray, epochs: int = 80, batch_size: int = 32,
            validation_split: float = 0.15) -> Dict:
        tf = _tf()
        self.mean = x.reshape(-1, x.shape[-1]).mean(axis=0)
        self.std = x.reshape(-1, x.shape[-1]).std(axis=0) + 1e-6
        xn = (x - self.mean) / self.std

        self.model = self.build(x.shape[1], x.shape[2])
        hist = self.model.fit(
            xn, xn, epochs=epochs, batch_size=batch_size,
            validation_split=validation_split, verbose=2,
            callbacks=[
                tf.keras.callbacks.EarlyStopping(
                    monitor='val_loss', patience=12, restore_best_weights=True),
                tf.keras.callbacks.ReduceLROnPlateau(
                    monitor='val_loss', factor=0.5, patience=6, min_lr=1e-5),
            ],
        )

        # Calibrate the score mapping on the training distribution.
        recon = self.model.predict(xn, verbose=0)
        errors = np.mean((recon - xn) ** 2, axis=(1, 2))
        self.error_median = float(np.median(errors))
        self.error_scale = float(np.percentile(errors, 90) - np.percentile(errors, 10)) or 1e-3
        return {k: [float(v) for v in vals] for k, vals in hist.history.items()}

    def score(self, window_features: np.ndarray) -> Dict:
        """0-100 'maestro-likeness' for one window."""
        if self.model is None:
            return {'score': None, 'error': None}

        x = np.asarray(window_features, dtype=np.float32)
        if x.ndim == 2:
            x = x[None, ...]
        if self.mean is not None:
            x = (x - self.mean) / self.std

        recon = self.model.predict(x, verbose=0)
        error = float(np.mean((recon - x) ** 2))

        # Logistic mapping: at the maestro median -> 85, one scale unit worse -> ~50
        z = (error - self.error_median) / self.error_scale
        score = 100.0 / (1.0 + np.exp(1.6 * z - 1.7))
        return {'score': round(float(np.clip(score, 0.0, 100.0)), 2),
                'error': round(error, 6)}

    def save(self, model_dir: str, name: str = 'pose_autoencoder') -> str:
        os.makedirs(model_dir, exist_ok=True)
        path = os.path.join(model_dir, f'{name}.keras')
        self.model.save(path)
        with open(os.path.join(model_dir, f'{name}_meta.json'), 'w', encoding='utf-8') as fh:
            json.dump({
                'mean': self.mean.tolist() if self.mean is not None else None,
                'std': self.std.tolist() if self.std is not None else None,
                'error_median': self.error_median,
                'error_scale': self.error_scale,
                'window': WINDOW,
                'feature_dim': FEATURE_DIM,
            }, fh)
        return path

    @classmethod
    def load(cls, model_dir: str, name: str = 'pose_autoencoder') -> Optional['PoseAutoencoder']:
        path = os.path.join(model_dir, f'{name}.keras')
        meta_path = os.path.join(model_dir, f'{name}_meta.json')
        if not (os.path.isfile(path) and os.path.isfile(meta_path)):
            return None
        try:
            tf = _tf()
            model = tf.keras.models.load_model(path, compile=False)
            with open(meta_path, 'r', encoding='utf-8') as fh:
                meta = json.load(fh)
            return cls(
                model=model,
                mean=np.array(meta['mean'], dtype=np.float32) if meta.get('mean') else None,
                std=np.array(meta['std'], dtype=np.float32) if meta.get('std') else None,
                error_median=float(meta.get('error_median', 0.0)),
                error_scale=float(meta.get('error_scale', 1.0)),
            )
        except Exception as exc:  # pragma: no cover
            print(f'[deep_models] could not load {name}: {exc}')
            return None


# ---------------------------------------------------------------------------
# 3. Tempo regressor
# ---------------------------------------------------------------------------

class TempoRegressor:
    """1D-CNN predicting the normalised movement speed of a window."""

    def __init__(self, model=None, mean=None, std=None, target_scale: float = 1.0):
        self.model = model
        self.mean = mean
        self.std = std
        self.target_scale = max(target_scale, 1e-6)

    @staticmethod
    def build(window: int = WINDOW, feature_dim: int = FEATURE_DIM):
        tf = _tf()
        layers = tf.keras.layers
        model = tf.keras.Sequential([
            layers.Input(shape=(window, feature_dim)),
            layers.Conv1D(64, 5, activation='relu', padding='same'),
            layers.MaxPooling1D(2),
            layers.Conv1D(64, 3, activation='relu', padding='same'),
            layers.GlobalAveragePooling1D(),
            layers.Dense(32, activation='relu'),
            layers.Dense(1),
        ], name='tempo_regressor')
        model.compile(optimizer=tf.keras.optimizers.Adam(1e-3), loss='huber', metrics=['mae'])
        return model

    def fit(self, x: np.ndarray, y: np.ndarray, epochs: int = 50,
            batch_size: int = 32, validation_split: float = 0.2) -> Dict:
        tf = _tf()
        self.mean = x.reshape(-1, x.shape[-1]).mean(axis=0)
        self.std = x.reshape(-1, x.shape[-1]).std(axis=0) + 1e-6
        self.target_scale = float(np.percentile(y, 95)) or 1.0

        xn = (x - self.mean) / self.std
        yn = y / self.target_scale

        self.model = self.build(x.shape[1], x.shape[2])
        hist = self.model.fit(
            xn, yn, epochs=epochs, batch_size=batch_size,
            validation_split=validation_split, verbose=2,
            callbacks=[tf.keras.callbacks.EarlyStopping(
                monitor='val_loss', patience=10, restore_best_weights=True)],
        )
        return {k: [float(v) for v in vals] for k, vals in hist.history.items()}

    def predict(self, window_features: np.ndarray) -> float:
        if self.model is None:
            return 0.0
        x = np.asarray(window_features, dtype=np.float32)
        if x.ndim == 2:
            x = x[None, ...]
        if self.mean is not None:
            x = (x - self.mean) / self.std
        return float(self.model.predict(x, verbose=0)[0][0] * self.target_scale)

    def save(self, model_dir: str, name: str = 'tempo_regressor') -> str:
        os.makedirs(model_dir, exist_ok=True)
        path = os.path.join(model_dir, f'{name}.keras')
        self.model.save(path)
        with open(os.path.join(model_dir, f'{name}_meta.json'), 'w', encoding='utf-8') as fh:
            json.dump({
                'mean': self.mean.tolist() if self.mean is not None else None,
                'std': self.std.tolist() if self.std is not None else None,
                'target_scale': self.target_scale,
            }, fh)
        return path

    @classmethod
    def load(cls, model_dir: str, name: str = 'tempo_regressor') -> Optional['TempoRegressor']:
        path = os.path.join(model_dir, f'{name}.keras')
        meta_path = os.path.join(model_dir, f'{name}_meta.json')
        if not (os.path.isfile(path) and os.path.isfile(meta_path)):
            return None
        try:
            tf = _tf()
            model = tf.keras.models.load_model(path, compile=False)
            with open(meta_path, 'r', encoding='utf-8') as fh:
                meta = json.load(fh)
            return cls(
                model=model,
                mean=np.array(meta['mean'], dtype=np.float32) if meta.get('mean') else None,
                std=np.array(meta['std'], dtype=np.float32) if meta.get('std') else None,
                target_scale=float(meta.get('target_scale', 1.0)),
            )
        except Exception as exc:  # pragma: no cover
            print(f'[deep_models] could not load {name}: {exc}')
            return None


# ---------------------------------------------------------------------------
# Bundle loader used by the Flask app
# ---------------------------------------------------------------------------

class DeepModelBundle:
    """Holds whatever models are available and reports what is missing."""

    def __init__(self, model_dir: str):
        self.model_dir = model_dir
        self.classifier: Optional[GerakanClassifier] = None
        self.autoencoder: Optional[PoseAutoencoder] = None
        self.tempo: Optional[TempoRegressor] = None
        self.loaded = False

    def load(self) -> 'DeepModelBundle':
        self.classifier = GerakanClassifier.load(self.model_dir)
        self.autoencoder = PoseAutoencoder.load(self.model_dir)
        self.tempo = TempoRegressor.load(self.model_dir)
        self.loaded = any([self.classifier, self.autoencoder, self.tempo])
        return self

    @property
    def status(self) -> Dict:
        return {
            'model_dir': self.model_dir,
            'gerakan_classifier': self.classifier is not None,
            'pose_autoencoder': self.autoencoder is not None,
            'tempo_regressor': self.tempo is not None,
            'labels': self.classifier.labels if self.classifier else [],
        }
