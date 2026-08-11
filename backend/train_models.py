"""
Train the CITRA deep-learning models on the extracted pose dataset.

Run ``build_dataset.py`` first - this script consumes the ``*_features.npy``
matrices and ``*_segments.json`` phase boundaries it produces.

Usage
-----
    python train_models.py                     # train all three models
    python train_models.py --skip-tempo
    python train_models.py --epochs 40 --karakter klana

Labels
------
There is no hand-annotated gerakan ground truth for these recordings, so the
labels come from the motion-energy segmentation in ``DatasetBuilder`` refined
by K-Means over the segment-level posture signature.  Segments that look alike
kinematically end up in the same class, which is what lets the Bi-LSTM learn
*"this is the same movement the maestro does at 02:14"* rather than memorising
timestamps.  The mapping is written to ``models/label_map.json`` so it can be
renamed to proper gerakan names later without retraining.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
from typing import Dict, List, Tuple

import numpy as np

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE_DIR)

os.environ.setdefault('TF_CPP_MIN_LOG_LEVEL', '3')

DATASET_DIR = os.path.join(BASE_DIR, 'maestro_data', 'dataset')
MODEL_DIR = os.path.join(BASE_DIR, 'models')


# ---------------------------------------------------------------------------

def load_dataset(karakter: str) -> List[Dict]:
    """Load every built entry for a character."""
    manifest_path = os.path.join(DATASET_DIR, 'manifest.json')
    if not os.path.isfile(manifest_path):
        raise FileNotFoundError(
            f'{manifest_path} not found - run build_dataset.py first.'
        )
    with open(manifest_path, 'r', encoding='utf-8') as fh:
        manifest = json.load(fh)

    entries = []
    for entry in manifest['entries']:
        if karakter and entry['karakter'] != karakter:
            continue
        kp_dir = os.path.join(DATASET_DIR, entry['karakter'], 'keypoints')
        feat_path = os.path.join(kp_dir, f'{entry["slug"]}_features.npy')
        seg_path = os.path.join(kp_dir, f'{entry["slug"]}_segments.json')
        if not os.path.isfile(feat_path):
            print(f'[train] skip {entry["slug"]}: no features file')
            continue

        features = np.load(feat_path)
        segments = []
        if os.path.isfile(seg_path):
            with open(seg_path, 'r', encoding='utf-8') as fh:
                segments = json.load(fh).get('segments', [])

        entries.append({
            'slug': entry['slug'],
            'role': entry.get('role', 'maestro'),
            'features': features,
            'segments': segments,
            'sample_fps': entry['meta'].get('sample_fps', 6.0),
        })
        print(f'[train] loaded {entry["slug"]}: {features.shape} '
              f'({len(segments)} segments, role={entry.get("role")})')
    return entries


def segment_signature(features: np.ndarray, seg: Dict) -> np.ndarray:
    """
    Compact descriptor of one movement phase.

    Mean posture + posture spread + mean/peak motion energy.  Two performances
    of the same gerakan land close together even if their duration differs.
    """
    chunk = features[seg['start_frame']:seg['end_frame']]
    if chunk.shape[0] < 2:
        chunk = features[max(0, seg['start_frame'] - 1):seg['start_frame'] + 2]
    if chunk.shape[0] == 0:
        return np.zeros(features.shape[1] * 2 + 2, dtype=np.float32)

    delta = np.linalg.norm(np.diff(chunk, axis=0), axis=1) if chunk.shape[0] > 1 else np.zeros(1)
    return np.concatenate([
        chunk.mean(axis=0),
        chunk.std(axis=0),
        [float(delta.mean()), float(delta.max())],
    ]).astype(np.float32)


def cluster_segments(entries: List[Dict], n_clusters: int) -> Tuple[Dict, List[str]]:
    """
    K-Means over segment signatures -> a consistent label per movement phase,
    shared across all videos of the character.
    """
    from sklearn.cluster import KMeans
    from sklearn.preprocessing import StandardScaler

    signatures, index = [], []
    for e_i, entry in enumerate(entries):
        for s_i, seg in enumerate(entry['segments']):
            signatures.append(segment_signature(entry['features'], seg))
            index.append((e_i, s_i))

    if not signatures:
        raise RuntimeError('No segments found - cannot train the classifier.')

    x = StandardScaler().fit_transform(np.stack(signatures))
    n_clusters = int(max(2, min(n_clusters, len(signatures) // 3 or 2)))

    km = KMeans(n_clusters=n_clusters, n_init=20, random_state=42)
    assignment = km.fit_predict(x)

    mapping: Dict[Tuple[int, int], int] = {
        idx: int(lbl) for idx, lbl in zip(index, assignment)
    }
    labels = [f'gerakan_{i + 1:02d}' for i in range(n_clusters)]
    counts = np.bincount(assignment, minlength=n_clusters)
    print(f'[train] clustered {len(signatures)} segments into {n_clusters} classes: '
          f'{dict(zip(labels, counts.tolist()))}')
    return mapping, labels


def build_frame_labels(entries: List[Dict], mapping: Dict) -> None:
    """Expand segment-level cluster labels down to per-frame labels."""
    for e_i, entry in enumerate(entries):
        frame_labels = np.full(entry['features'].shape[0], -1, dtype=np.int32)
        for s_i, seg in enumerate(entry['segments']):
            lbl = mapping.get((e_i, s_i))
            if lbl is None:
                continue
            frame_labels[seg['start_frame']:seg['end_frame']] = lbl
        entry['frame_labels'] = frame_labels


# ---------------------------------------------------------------------------

def main() -> int:
    p = argparse.ArgumentParser(description='Train CITRA deep-learning models')
    p.add_argument('--karakter', default='klana')
    p.add_argument('--clusters', type=int, default=8,
                   help='number of gerakan classes to discover')
    p.add_argument('--epochs', type=int, default=60)
    p.add_argument('--batch-size', type=int, default=32)
    p.add_argument('--skip-classifier', action='store_true')
    p.add_argument('--skip-autoencoder', action='store_true')
    p.add_argument('--skip-tempo', action='store_true')
    args = p.parse_args()

    from ai.deep_models import (
        GerakanClassifier, PoseAutoencoder, TempoRegressor,
        make_windows, movement_energy, WINDOW, STRIDE,
    )

    os.makedirs(MODEL_DIR, exist_ok=True)
    entries = load_dataset(args.karakter)
    if not entries:
        print('[train] nothing to train on.')
        return 1

    report: Dict = {
        'trained_at': time.strftime('%Y-%m-%dT%H:%M:%S'),
        'karakter': args.karakter,
        'sources': [
            {'slug': e['slug'], 'role': e['role'], 'frames': int(e['features'].shape[0])}
            for e in entries
        ],
    }

    # ---- 1. Gerakan classifier ------------------------------------------
    if not args.skip_classifier:
        print('\n=== Training gerakan classifier (Bi-LSTM) ===')
        mapping, labels = cluster_segments(entries, args.clusters)
        build_frame_labels(entries, mapping)

        xs, ys = [], []
        for entry in entries:
            x, y = make_windows(entry['features'], entry['frame_labels'], WINDOW, STRIDE)
            if y is None or x.shape[0] == 0:
                continue
            keep = y >= 0          # drop windows that fall outside any segment
            xs.append(x[keep])
            ys.append(y[keep])

        x = np.concatenate(xs) if xs else np.zeros((0, WINDOW, 63), dtype=np.float32)
        y = np.concatenate(ys) if ys else np.zeros((0,), dtype=np.int32)
        print(f'[train] classifier dataset: {x.shape}, classes={len(labels)}')

        if x.shape[0] < 40:
            print('[train] not enough windows for the classifier - skipping.')
        else:
            clf = GerakanClassifier()
            hist = clf.fit(x, y, labels, epochs=args.epochs, batch_size=args.batch_size)
            clf.save(MODEL_DIR)
            report['gerakan_classifier'] = {
                'windows': int(x.shape[0]),
                'classes': labels,
                'final_accuracy': hist['accuracy'][-1],
                'final_val_accuracy': hist.get('val_accuracy', [0])[-1],
            }
            with open(os.path.join(MODEL_DIR, 'label_map.json'), 'w', encoding='utf-8') as fh:
                json.dump({
                    'labels': labels,
                    'note': 'Auto-discovered movement phases. Rename the values '
                            'here to real gerakan names without retraining.',
                    'display_names': {lbl: lbl.replace('_', ' ').title() for lbl in labels},
                }, fh, ensure_ascii=False, indent=2)
            print(f'[train] classifier val_accuracy = '
                  f'{report["gerakan_classifier"]["final_val_accuracy"]:.3f}')

    # ---- 2. Autoencoder (maestro only) ----------------------------------
    if not args.skip_autoencoder:
        print('\n=== Training pose autoencoder (maestro reference) ===')
        maestro = [e for e in entries if e['role'] == 'maestro'] or entries
        xs = []
        for entry in maestro:
            x, _ = make_windows(entry['features'], None, WINDOW, STRIDE)
            if x.shape[0]:
                xs.append(x)
        x = np.concatenate(xs) if xs else np.zeros((0, WINDOW, 63), dtype=np.float32)
        print(f'[train] autoencoder dataset: {x.shape}')

        if x.shape[0] < 40:
            print('[train] not enough windows for the autoencoder - skipping.')
        else:
            ae = PoseAutoencoder()
            hist = ae.fit(x, epochs=args.epochs, batch_size=args.batch_size)
            ae.save(MODEL_DIR)
            report['pose_autoencoder'] = {
                'windows': int(x.shape[0]),
                'final_loss': hist['loss'][-1],
                'final_val_loss': hist.get('val_loss', [0])[-1],
                'error_median': ae.error_median,
                'error_scale': ae.error_scale,
            }
            print(f'[train] autoencoder val_loss = '
                  f'{report["pose_autoencoder"]["final_val_loss"]:.5f}')

    # ---- 3. Tempo regressor ---------------------------------------------
    if not args.skip_tempo:
        print('\n=== Training tempo regressor (1D-CNN) ===')
        xs, ys = [], []
        for entry in entries:
            energy = movement_energy(entry['features'])
            x, _ = make_windows(entry['features'], None, WINDOW, STRIDE)
            targets = []
            for start in range(0, entry['features'].shape[0] - WINDOW + 1, STRIDE):
                targets.append(float(np.mean(energy[start:start + WINDOW])))
            n = min(x.shape[0], len(targets))
            if n:
                xs.append(x[:n])
                ys.append(np.array(targets[:n], dtype=np.float32))

        x = np.concatenate(xs) if xs else np.zeros((0, WINDOW, 63), dtype=np.float32)
        y = np.concatenate(ys) if ys else np.zeros((0,), dtype=np.float32)
        print(f'[train] tempo dataset: {x.shape}')

        if x.shape[0] < 40:
            print('[train] not enough windows for the tempo model - skipping.')
        else:
            tr = TempoRegressor()
            hist = tr.fit(x, y, epochs=min(args.epochs, 50), batch_size=args.batch_size)
            tr.save(MODEL_DIR)
            report['tempo_regressor'] = {
                'windows': int(x.shape[0]),
                'final_mae': hist.get('mae', [0])[-1],
                'final_val_mae': hist.get('val_mae', [0])[-1],
                'target_scale': tr.target_scale,
            }
            print(f'[train] tempo val_mae = {report["tempo_regressor"]["final_val_mae"]:.5f}')

    with open(os.path.join(MODEL_DIR, 'training_report.json'), 'w', encoding='utf-8') as fh:
        json.dump(report, fh, ensure_ascii=False, indent=2)
    print(f'\n[train] report -> {os.path.join(MODEL_DIR, "training_report.json")}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
