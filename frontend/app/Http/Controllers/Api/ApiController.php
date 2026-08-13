<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CitraNotification;
use App\Models\MaestroReference;
use App\Models\PoseDataset;
use App\Models\PracticeSession;
use App\Services\AchievementService;
use App\Services\AiBackendService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * JSON API for the front-end widgets.
 *
 * These routes were previously unreachable: routes/api.php was never
 * registered in bootstrap/app.php and the group required an `auth:sanctum`
 * guard that this project does not install. Both are fixed - the routes now
 * use the session guard, which is what a same-origin Blade UI actually needs.
 */
class ApiController extends Controller
{
    public function __construct(
        private StatsService $stats,
        private AchievementService $achievements,
        private AiBackendService $ai,
    ) {
    }

    public function getUserStats()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->stats->summary($user), [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'avatar' => $user->avatar_url,
                'initial'=> $user->initial,
            ]),
        ]);
    }

    public function getWeeklyProgress(Request $request)
    {
        $days = min(max((int) $request->query('days', 7), 1), 30);

        return response()->json([
            'success' => true,
            'data'    => $this->stats->weeklyProgress(Auth::user(), $days),
        ]);
    }

    public function getCharacterMastery()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->stats->characterMastery(Auth::user()),
        ]);
    }

    public function getPracticeHistory(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 10), 1), 50);

        $sessions = PracticeSession::forUser(Auth::id())
            ->karakter($request->query('karakter'))
            ->period($request->query('period'))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PracticeSession $s) => [
                'id'         => $s->id,
                'title'      => $s->title,
                'icon'       => $s->karakter_icon,
                'karakter'   => $s->karakter,
                'gerakan'    => $s->gerakan_name,
                'wiraga'     => $s->wiraga_score,
                'wirama'     => $s->wirama_score,
                'wirasa'     => $s->wirasa_score,
                'total'      => $s->total_score,
                'grade'      => $s->resolved_grade,
                'accuracy'   => $s->accuracy,
                'duration'   => $s->duration_for_humans,
                'created_at' => $s->created_at?->toIso8601String(),
                'ago'        => $s->created_at?->diffForHumans(),
                'url'        => route('history.show', $s->id),
            ]);

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    public function getMaestroReferences(Request $request)
    {
        $references = MaestroReference::published()
            ->karakter($request->query('karakter'))
            ->ordered()
            ->get()
            ->map(fn (MaestroReference $r) => [
                'id'           => $r->id,
                'slug'         => $r->slug,
                'karakter'     => $r->karakter,
                'gerakan_name' => $r->gerakan_name,
                'gerakan_slug' => $r->gerakan_slug,
                'role'         => $r->role,
                'difficulty'   => $r->difficulty_label,
                'description'  => $r->description,
                'duration'     => $r->duration_for_humans,
                'video_url'    => $r->video_url,
                'poster_url'   => $r->poster_url,
                'frames'       => $r->frame_urls,
                'has_dataset'  => $r->has_dataset,
                'frame_count'  => $r->frame_count,
                'segments'     => $r->segments,
            ]);

        return response()->json(['success' => true, 'data' => $references]);
    }

    /**
     * Reference keyframes for one maestro video.
     *
     * Served by Laravel (reading the file the Python pipeline produced) so the
     * practice screen can score Wiraga in the browser even when the Flask
     * backend is not running. The Python service adds the deep-learning layer
     * on top; it is not required for basic scoring.
     *
     * The raw file holds ~950 keyframes with 33 landmarks each; it is
     * decimated and stripped to angles-only before being sent to the browser.
     */
    public function getMaestroKeyframes(Request $request, int $id)
    {
        $reference = MaestroReference::find($id);

        if (!$reference || empty($reference->keyframes_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Referensi ini belum memiliki dataset pose.',
                'data'    => [],
            ], 404);
        }

        $path = base_path('../backend/maestro_data/'.ltrim($reference->keyframes_path, '/'));

        if (!is_file($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File keyframes tidak ditemukan. Jalankan build_dataset.py.',
                'data'    => [],
            ], 404);
        }

        // Cached: parsing a 20 MB JSON on every practice session start would be
        // needlessly slow, and the file only changes when the pipeline reruns.
        $cacheKey = 'citra.keyframes.'.$id.'.'.(filemtime($path) ?: 0);

        $payload = Cache::remember($cacheKey, now()->addHours(6), function () use ($path) {
            $raw = json_decode(file_get_contents($path), true);
            if (!is_array($raw)) {
                return [];
            }

            // ~2 keyframes/second is plenty for live comparison.
            $limit = 600;
            $stride = max(1, (int) floor(count($raw) / $limit));

            $out = [];
            foreach ($raw as $i => $kf) {
                if ($i % $stride !== 0) {
                    continue;
                }
                $out[] = [
                    't'      => round((float) ($kf['timestamp'] ?? 0), 2),
                    'angles' => array_map(
                        fn ($v) => round((float) $v, 1),
                        $kf['angles'] ?? []
                    ),
                    'vis'    => round((float) ($kf['visibility'] ?? 0), 2),
                ];
            }
            return $out;
        });

        return response()->json([
            'success'  => true,
            'id'       => $reference->id,
            'title'    => $reference->gerakan_name,
            'duration' => $reference->duration_seconds,
            'count'    => count($payload),
            'data'     => $payload,
        ]);
    }

    public function getLeaderboard(Request $request)
    {
        return app(\App\Http\Controllers\LeaderboardController::class)->getData($request);
    }

    public function getAchievements()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->achievements->listForUser(Auth::user()),
        ]);
    }

    public function getNotifications()
    {
        $rows = CitraNotification::where('user_id', Auth::id())
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CitraNotification $n) => [
                'id'      => $n->id,
                'type'    => $n->type,
                'title'   => $n->title,
                'message' => $n->message,
                'icon'    => $n->icon,
                'link'    => $n->link,
                'read'    => $n->is_read,
                'ago'     => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'success' => true,
            'unread'  => CitraNotification::where('user_id', Auth::id())->unread()->count(),
            'data'    => $rows,
        ]);
    }

    public function markNotificationsRead()
    {
        CitraNotification::where('user_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function getDatasets(Request $request)
    {
        $datasets = PoseDataset::karakter($request->query('karakter'))
            ->orderBy('karakter')
            ->get()
            ->map(fn (PoseDataset $d) => [
                'slug'            => $d->slug,
                'karakter'        => $d->karakter,
                'title'           => $d->title,
                'role'            => $d->role,
                'video_url'       => $d->video_url,
                'poster_url'      => $d->poster_url,
                'duration'        => $d->duration_for_humans,
                'sampled_frames'  => $d->sampled_frames,
                'detected_frames' => $d->detected_frames,
                'detection_rate'  => $d->detection_percent,
                'segment_count'   => $d->segment_count,
                'resolution'      => $d->resolution,
                'frames'          => $d->frame_urls,
            ]);

        return response()->json(['success' => true, 'data' => $datasets]);
    }

    /** Health of the Python AI backend, for the status pill in the UI. */
    public function aiStatus()
    {
        $health = $this->ai->health();

        return response()->json([
            'success' => true,
            'online'  => $health !== null,
            'data'    => $health,
        ]);
    }
}
