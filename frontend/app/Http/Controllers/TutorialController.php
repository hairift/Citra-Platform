<?php

namespace App\Http\Controllers;

use App\Models\GerakanProgress;
use App\Models\MaestroReference;
use App\Models\PoseDataset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TutorialController extends Controller
{
    public function index(Request $request)
    {
        $karakters = config('citra.karakters', []);

        $selectedKarakter = $request->query('karakter');
        if (!isset($karakters[$selectedKarakter])) {
            $selectedKarakter = config('citra.practice.default_karakter', 'klana');
        }

        $references = MaestroReference::published()
            ->karakter($selectedKarakter)
            ->ordered()
            ->get();

        $datasets = PoseDataset::karakter($selectedKarakter)->get();

        $progress = GerakanProgress::where('user_id', Auth::id())
            ->where('karakter', $selectedKarakter)
            ->get()
            ->keyBy('gerakan');

        // Merge the static curriculum from config with whatever real maestro
        // footage exists, so a gerakan without video still appears (with its
        // written instructions) instead of silently disappearing.
        $gerakanList = collect($karakters[$selectedKarakter]['gerakan'] ?? [])
            ->map(function (array $g, int $index) use ($references, $progress) {
                $reference = $references->firstWhere('gerakan_slug', $g['slug']);
                $row = $progress->get($g['slug']);

                return array_merge($g, [
                    'index'        => $index + 1,
                    'reference'    => $reference,
                    'video_url'    => $reference?->video_url,
                    'poster_url'   => $reference?->poster_url,
                    'frames'       => $reference?->frame_urls ?? [],
                    'has_video'    => (bool) $reference?->video_url,
                    'best_score'   => $row ? round((float) $row->best_score, 1) : 0.0,
                    'attempts'     => $row ? (int) $row->attempts : 0,
                    'completed'    => (bool) ($row?->completed),
                ]);
            })
            ->values();

        // Full-performance recordings (not tied to a single gerakan).
        $fullPerformances = $references->whereNull('gerakan_slug')->values();

        $completedCount = $gerakanList->where('completed', true)->count();
        $totalCount = max($gerakanList->count(), 1);

        $overview = [
            'completed'  => $completedCount,
            'total'      => $gerakanList->count(),
            'percent'    => round(100 * $completedCount / $totalCount),
            'videos'     => $references->filter(fn ($r) => $r->video_url)->count(),
            'dataset_frames' => $datasets->sum('sampled_frames'),
        ];

        return view('tutorial', compact(
            'karakters', 'selectedKarakter', 'gerakanList', 'references',
            'datasets', 'fullPerformances', 'overview'
        ));
    }

    /**
     * Detail page for one gerakan.
     *
     * The old implementation rendered a "tutorial-detail" view that did not
     * exist, so every call here returned a 500.
     */
    public function show(string $karakter, string $gerakan)
    {
        $karakters = config('citra.karakters', []);
        abort_unless(isset($karakters[$karakter]), 404, 'Karakter tidak ditemukan');

        $meta = collect($karakters[$karakter]['gerakan'] ?? [])
            ->firstWhere('slug', $gerakan);
        abort_if($meta === null, 404, 'Gerakan tidak ditemukan');

        $reference = MaestroReference::published()
            ->karakter($karakter)
            ->where('gerakan_slug', $gerakan)
            ->first();

        $progress = GerakanProgress::where('user_id', Auth::id())
            ->where('karakter', $karakter)
            ->where('gerakan', $gerakan)
            ->first();

        $all = collect($karakters[$karakter]['gerakan'] ?? []);
        $position = $all->search(fn ($g) => $g['slug'] === $gerakan);

        $previous = $position > 0 ? $all[$position - 1] : null;
        $next = $position < $all->count() - 1 ? $all[$position + 1] : null;

        return view('tutorial-detail', [
            'karakterSlug' => $karakter,
            'karakter'     => $karakters[$karakter],
            'gerakan'      => $meta,
            'reference'    => $reference,
            'progress'     => $progress,
            'previous'     => $previous,
            'next'         => $next,
            'position'     => $position + 1,
            'total'        => $all->count(),
        ]);
    }

    /** Dataset gallery: the extracted joint-point stills. */
    public function dataset(Request $request)
    {
        $karakter = $request->query('karakter');
        $datasets = PoseDataset::karakter($karakter)
            ->orderBy('karakter')
            ->orderByDesc('role')
            ->get();

        $totals = [
            'videos'    => $datasets->count(),
            'frames'    => $datasets->sum('sampled_frames'),
            'detected'  => $datasets->sum('detected_frames'),
            'minutes'   => (int) floor($datasets->sum('duration_seconds') / 60),
            'segments'  => $datasets->sum('segment_count'),
        ];
        $totals['detection_rate'] = $totals['frames'] > 0
            ? round(100 * $totals['detected'] / $totals['frames'], 1)
            : 0.0;

        return view('dataset', compact('datasets', 'totals', 'karakter'));
    }
}
