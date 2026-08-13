<?php

namespace App\Console\Commands;

use App\Models\MaestroReference;
use App\Models\PoseDataset;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Import the output of backend/build_dataset.py into the database.
 *
 * The Python pipeline writes:
 *   backend/maestro_data/dataset/manifest.json          (full manifest)
 *   frontend/public/dataset/manifest.json               (web-safe manifest)
 *   frontend/public/dataset/<karakter>/frames/*.jpg     (annotated stills)
 *   frontend/public/videos/maestro/<slug>.mp4|.jpg      (transcoded video)
 *
 * This command reads the manifest and creates/updates the matching
 * `pose_datasets` and `maestro_references` rows, so the tutorial and practice
 * screens can use the extracted joint data.
 */
class SyncDataset extends Command
{
    protected $signature = 'citra:sync-dataset
                            {--publish : mark the imported references as published}
                            {--fresh : delete existing dataset rows first}';

    protected $description = 'Import the extracted pose dataset from backend/maestro_data into the database';

    public function handle(): int
    {
        $manifestPath = base_path('../backend/maestro_data/dataset/manifest.json');

        if (!is_file($manifestPath)) {
            $this->error("Manifest not found: {$manifestPath}");
            $this->line('Run this first:  cd backend && python build_dataset.py');
            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['entries'])) {
            $this->error('Manifest is empty or invalid.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            PoseDataset::query()->delete();
            $this->warn('Existing pose_datasets rows deleted.');
        }

        $builtAt = isset($manifest['generated_at'])
            ? Carbon::parse($manifest['generated_at'])
            : now();

        $imported = 0;
        $published = 0;

        foreach ($manifest['entries'] as $entry) {
            $meta = $entry['meta'] ?? [];
            $slug = $entry['slug'] ?? null;
            if (!$slug) {
                continue;
            }

            $karakter = $entry['karakter'] ?? 'klana';
            $segments = $entry['segments'] ?? [];
            $frames = $this->publishedFrames($karakter, $slug);
            $resolution = isset($meta['source_resolution'])
                ? implode('x', $meta['source_resolution'])
                : null;

            $webVideo = $entry['web_video'] ?? null;
            $poster = $webVideo ? preg_replace('/\.mp4$/i', '.jpg', $webVideo) : null;
            if ($poster && !file_exists(public_path('videos/maestro/'.$poster))) {
                $poster = null;
            }

            PoseDataset::updateOrCreate(
                ['slug' => $slug],
                [
                    'karakter'         => $karakter,
                    'title'            => $entry['gerakan_name'] ?? $slug,
                    'role'             => $entry['role'] ?? 'maestro',
                    'source_video'     => $meta['source_video'] ?? ($entry['file'] ?? null),
                    'web_video'        => $webVideo,
                    'poster'           => $poster,
                    'duration_seconds' => (float) ($meta['duration_seconds'] ?? 0),
                    'sample_fps'       => (float) ($meta['sample_fps'] ?? 6),
                    'sampled_frames'   => (int) ($meta['sampled_frames'] ?? 0),
                    'detected_frames'  => (int) ($meta['detected_frames'] ?? 0),
                    'detection_rate'   => (float) ($meta['detection_rate'] ?? 0),
                    'segment_count'    => count($segments),
                    'resolution'       => $resolution,
                    // Only the first 60 segments are stored: enough for the UI
                    // timeline without bloating the JSON column.
                    'segments'         => array_slice($segments, 0, 60),
                    'frames'           => $frames,
                    'description'      => $entry['description'] ?? null,
                    'built_at'         => $builtAt,
                ]
            );
            $imported++;

            // Mirror into maestro_references so the practice screen can score
            // against it. keyframes_path is relative to backend/maestro_data.
            $keyframesRelative = "dataset/{$karakter}/keypoints/{$slug}_keyframes.json";
            $keyframesAbsolute = base_path('../backend/maestro_data/'.$keyframesRelative);

            $reference = MaestroReference::updateOrCreate(
                ['slug' => $slug],
                [
                    'karakter'         => $karakter,
                    'gerakan_name'     => $entry['gerakan_name'] ?? $slug,
                    'gerakan_slug'     => $entry['gerakan_slug'] ?? null,
                    'role'             => $entry['role'] ?? 'maestro',
                    'video_path'       => $webVideo,
                    'poster_path'      => $poster,
                    'keyframes_path'   => is_file($keyframesAbsolute) ? $keyframesRelative : null,
                    'segments'         => array_slice($segments, 0, 60),
                    'duration_seconds' => (float) ($meta['duration_seconds'] ?? 0),
                    'frame_count'      => (int) ($meta['sampled_frames'] ?? 0),
                    'detection_rate'   => (float) ($meta['detection_rate'] ?? 0),
                    'sample_frames'    => $frames,
                    'description'      => $entry['description'] ?? null,
                    'difficulty'       => $entry['difficulty'] ?? 'menengah',
                    'is_published'     => $this->option('publish')
                        ? true
                        : (bool) is_file($keyframesAbsolute),
                ]
            );

            if ($reference->is_published) {
                $published++;
            }

            $this->line(sprintf(
                '  %-28s %5d frames  %5.1f%% detected  %3d segments  %s',
                $slug,
                (int) ($meta['sampled_frames'] ?? 0),
                100 * (float) ($meta['detection_rate'] ?? 0),
                count($segments),
                $webVideo && file_exists(public_path('videos/maestro/'.$webVideo))
                    ? 'video OK' : 'no web video'
            ));
        }

        $this->newLine();
        $this->info("Imported {$imported} dataset(s); {$published} reference(s) published.");

        return self::SUCCESS;
    }

    /**
     * Annotated stills actually present in public/dataset for this slug.
     *
     * Reading the directory rather than trusting the manifest means a frame
     * deleted by hand never becomes a broken image in the gallery.
     */
    private function publishedFrames(string $karakter, string $slug): array
    {
        // public/pose-frames, not public/dataset: a real directory named
        // "dataset" would be served by the web server and shadow the /dataset
        // route.
        $dir = public_path("pose-frames/{$karakter}/frames");
        if (!is_dir($dir)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir($dir) ?: [],
            fn ($f) => str_starts_with($f, $slug) && str_ends_with(strtolower($f), '.jpg')
        ));

        sort($files);
        return $files;
    }
}
