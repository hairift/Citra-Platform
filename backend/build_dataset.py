"""
CITRA dataset pipeline.

Reads the maestro recordings in ``maestro_data/raw`` and produces the golden
pose dataset consumed by the practice evaluator, the tutorial page and the
deep-learning trainer.

Usage
-----
    python build_dataset.py                # process every configured video
    python build_dataset.py --only klana_maestro_cam1
    python build_dataset.py --sample-fps 8 --max-annotated 200
    python build_dataset.py --publish-frames   # also copy stills into Laravel

Outputs (under ``maestro_data/dataset/<karakter>/``)
    keypoints/<slug>_keypoints.json    per-frame joints, angles, orientation
    keypoints/<slug>_keyframes.json    compact reference poses for scoring
    keypoints/<slug>_segments.json     auto-detected gerakan phases
    keypoints/<slug>_features.npy      (T, 63) training matrix
    frames/<slug>_####.jpg             annotated stills with joint dots
    manifest.json                      index of everything above
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import sys
import time
from typing import Dict, List

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE_DIR)

RAW_DIR = os.path.join(BASE_DIR, 'maestro_data', 'raw')
DATASET_DIR = os.path.join(BASE_DIR, 'maestro_data', 'dataset')

# NOTE: this folder must NOT be named "dataset" - Laravel's public directory is
# served before the router, so a public/dataset folder would shadow the
# /dataset page route and return 404.
PUBLIC_DATASET_DIR = os.path.abspath(
    os.path.join(BASE_DIR, '..', 'frontend', 'public', 'pose-frames')
)

# ---------------------------------------------------------------------------
# Source catalogue.  `web_video` must match the transcoded file that lives in
# frontend/public/videos/maestro/ so the tutorial player can line up with the
# extracted keyframes.
# ---------------------------------------------------------------------------
SOURCES: List[Dict] = [
    {
        'slug': 'klana_maestro_full_cam1',
        'file': 'C0186.MP4',
        'karakter': 'klana',
        'gerakan_name': 'Tari Topeng Klana - Penyajian Utuh (Kamera 1)',
        'role': 'maestro',
        'web_video': 'klana_maestro_full_cam1.mp4',
        'description': 'Rekaman utuh Tari Topeng Klana dari kamera utama, '
                       'dipakai sebagai referensi emas (golden reference) '
                       'untuk penilaian Wiraga.',
        'difficulty': 'sulit',
    },
    {
        'slug': 'klana_maestro_full_cam2',
        'file': 'C0189.MP4',
        'karakter': 'klana',
        'gerakan_name': 'Tari Topeng Klana - Penyajian Utuh (Kamera 2)',
        'role': 'maestro',
        'web_video': 'klana_maestro_full_cam2.mp4',
        'description': 'Rekaman utuh Tari Topeng Klana dari sudut kedua, '
                       'melengkapi referensi tiga dimensi gerakan.',
        'difficulty': 'sulit',
    },
    {
        'slug': 'klana_latihan_sesi1',
        'file': 'VID_20260619_150254.mp4',
        'karakter': 'klana',
        'gerakan_name': 'Tari Topeng Klana - Sesi Latihan 1',
        'role': 'latihan',
        'web_video': 'klana_latihan_sesi1.mp4',
        'description': 'Sesi latihan Tari Topeng Klana, dipakai sebagai data '
                       'tambahan untuk pelatihan model klasifikasi gerakan.',
        'difficulty': 'menengah',
    },
    {
        'slug': 'klana_latihan_sesi2',
        'file': 'VID_20260619_152338.mp4',
        'karakter': 'klana',
        'gerakan_name': 'Tari Topeng Klana - Sesi Latihan 2',
        'role': 'latihan',
        'web_video': 'klana_latihan_sesi2.mp4',
        'description': 'Sesi latihan lanjutan Tari Topeng Klana untuk '
                       'memperkaya variasi gerakan pada dataset.',
        'difficulty': 'menengah',
    },
]


def build(args) -> Dict:
    from ai.dataset_builder import DatasetBuilder

    builder = DatasetBuilder(
        sample_fps=args.sample_fps,
        model_complexity=args.model_complexity,
        min_detection_confidence=args.min_confidence,
        min_tracking_confidence=args.min_confidence,
    )

    manifest_entries: List[Dict] = []
    started = time.time()

    for src in SOURCES:
        if args.only and src['slug'] not in args.only:
            continue

        video_path = os.path.join(RAW_DIR, src['file'])
        if not os.path.isfile(video_path):
            print(f'[dataset] SKIP {src["slug"]}: {src["file"]} not found in {RAW_DIR}')
            continue

        out_dir = os.path.join(DATASET_DIR, src['karakter'])
        os.makedirs(out_dir, exist_ok=True)

        existing = os.path.join(out_dir, 'keypoints', f'{src["slug"]}_keypoints.json')
        if os.path.isfile(existing) and not args.force:
            print(f'[dataset] SKIP {src["slug"]}: already built (use --force to redo)')
            with open(existing, 'r', encoding='utf-8') as fh:
                meta = json.load(fh)['meta']
            manifest_entries.append({**src, 'meta': meta, 'reused': True})
            continue

        t0 = time.time()
        result = builder.process_video(
            video_path=video_path,
            out_dir=out_dir,
            slug=src['slug'],
            karakter=src['karakter'],
            gerakan_name=src['gerakan_name'],
            annotate_every=args.annotate_every,
            max_annotated=args.max_annotated,
        )
        elapsed = time.time() - t0
        print(f'[dataset] {src["slug"]} finished in {elapsed / 60:.1f} min')

        manifest_entries.append({
            **src,
            'meta': result['meta'],
            'segments': result['segments'],
            'files': {
                'keypoints': result['keypoints_file'],
                'keyframes': result['keyframes_file'],
                'segments': result['segments_file'],
                'features': result['features_file'],
            },
            'reused': False,
        })

    manifest = {
        'generated_at': time.strftime('%Y-%m-%dT%H:%M:%S'),
        'sample_fps': args.sample_fps,
        'model_complexity': args.model_complexity,
        'total_build_seconds': round(time.time() - started, 1),
        'entries': manifest_entries,
    }

    os.makedirs(DATASET_DIR, exist_ok=True)
    manifest_path = os.path.join(DATASET_DIR, 'manifest.json')
    with open(manifest_path, 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=2)
    print(f'[dataset] manifest -> {manifest_path}')

    if args.publish_frames:
        publish_frames(manifest)

    return manifest


def publish_frames(manifest: Dict, per_video: int = 12) -> None:
    """
    Copy a representative slice of the annotated stills into Laravel's public
    folder so the tutorial / dataset gallery can display them directly.
    """
    for entry in manifest['entries']:
        karakter = entry['karakter']
        slug = entry['slug']
        src_dir = os.path.join(DATASET_DIR, karakter, 'frames')
        dst_dir = os.path.join(PUBLIC_DATASET_DIR, karakter, 'frames')
        if not os.path.isdir(src_dir):
            continue
        os.makedirs(dst_dir, exist_ok=True)

        files = sorted(f for f in os.listdir(src_dir) if f.startswith(slug) and f.endswith('.jpg'))
        if not files:
            continue
        stride = max(1, len(files) // per_video)
        chosen = files[::stride][:per_video]
        for f in chosen:
            shutil.copy2(os.path.join(src_dir, f), os.path.join(dst_dir, f))
        print(f'[dataset] published {len(chosen)} frames for {slug} -> {dst_dir}')

    # A trimmed manifest for the web layer (no giant per-frame payloads).
    web_manifest = {
        'generated_at': manifest['generated_at'],
        'sample_fps': manifest['sample_fps'],
        'entries': [
            {
                'slug': e['slug'],
                'karakter': e['karakter'],
                'gerakan_name': e['gerakan_name'],
                'role': e['role'],
                'description': e['description'],
                'difficulty': e['difficulty'],
                'web_video': e['web_video'],
                'duration_seconds': e['meta'].get('duration_seconds'),
                'sampled_frames': e['meta'].get('sampled_frames'),
                'detected_frames': e['meta'].get('detected_frames'),
                'detection_rate': e['meta'].get('detection_rate'),
                'source_resolution': e['meta'].get('source_resolution'),
                'segment_count': len(e.get('segments', [])),
                'segments': e.get('segments', [])[:40],
                'frames': sorted(
                    f for f in os.listdir(
                        os.path.join(PUBLIC_DATASET_DIR, e['karakter'], 'frames')
                    )
                    if f.startswith(e['slug'])
                ) if os.path.isdir(
                    os.path.join(PUBLIC_DATASET_DIR, e['karakter'], 'frames')
                ) else [],
            }
            for e in manifest['entries']
        ],
    }
    os.makedirs(PUBLIC_DATASET_DIR, exist_ok=True)
    with open(os.path.join(PUBLIC_DATASET_DIR, 'manifest.json'), 'w', encoding='utf-8') as fh:
        json.dump(web_manifest, fh, ensure_ascii=False, indent=2)
    print(f'[dataset] web manifest -> {os.path.join(PUBLIC_DATASET_DIR, "manifest.json")}')


def main() -> int:
    p = argparse.ArgumentParser(description='Build the CITRA golden pose dataset')
    p.add_argument('--sample-fps', type=float, default=6.0,
                   help='frames per second sampled from each video (default 6)')
    p.add_argument('--model-complexity', type=int, default=2, choices=[0, 1, 2],
                   help='MediaPipe pose model complexity (2 = most accurate)')
    p.add_argument('--min-confidence', type=float, default=0.6)
    p.add_argument('--annotate-every', type=int, default=12,
                   help='save an annotated still every N sampled frames')
    p.add_argument('--max-annotated', type=int, default=120)
    p.add_argument('--only', nargs='*', default=None, help='slugs to (re)build')
    p.add_argument('--force', action='store_true', help='rebuild even if output exists')
    p.add_argument('--publish-frames', action='store_true', default=True,
                   help='copy sample frames into frontend/public/dataset')
    p.add_argument('--no-publish-frames', dest='publish_frames', action='store_false')
    args = p.parse_args()

    if not os.path.isdir(RAW_DIR):
        print(f'ERROR: raw video folder not found: {RAW_DIR}')
        return 1

    build(args)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
