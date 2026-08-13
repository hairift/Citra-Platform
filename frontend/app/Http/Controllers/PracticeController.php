<?php

namespace App\Http\Controllers;

use App\Models\GerakanProgress;
use App\Models\Leaderboard;
use App\Models\MaestroReference;
use App\Models\PracticeSession;
use App\Services\AchievementService;
use App\Services\AiBackendService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PracticeController extends Controller
{
    public function __construct(
        private AiBackendService $ai,
        private StatsService $stats,
        private AchievementService $achievements,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $karakters = config('citra.karakters', []);

        $selectedKarakter = $request->query('karakter');
        if (!isset($karakters[$selectedKarakter])) {
            $selectedKarakter = config('citra.practice.default_karakter', 'klana');
        }

        $selectedGerakan = $request->query('gerakan');

        $references = MaestroReference::published()
            ->karakter($selectedKarakter)
            ->maestro()
            ->ordered()
            ->get();

        // Pick the reference the engine can actually score against.
        //
        // Priority: a scorable reference for the chosen gerakan -> any scorable
        // reference for this character -> any reference with a video -> any
        // scorable reference at all (e.g. the learner picked Panji but only the
        // Klana dataset has been extracted so far).
        $activeReference =
            $references->first(fn (MaestroReference $r) =>
                $selectedGerakan && $r->gerakan_slug === $selectedGerakan && $r->keyframes_path)
            ?? $references->first(fn (MaestroReference $r) => (bool) $r->keyframes_path)
            ?? $references->first(fn (MaestroReference $r) => (bool) $r->video_path)
            ?? MaestroReference::published()->maestro()->scorable()->ordered()->first();

        $gerakanList = $karakters[$selectedKarakter]['gerakan'] ?? [];

        $progress = GerakanProgress::where('user_id', $user->id)
            ->where('karakter', $selectedKarakter)
            ->get()
            ->keyBy('gerakan');

        $recentBest = PracticeSession::forUser($user->id)
            ->where('karakter', $selectedKarakter)
            ->max('total_score');

        // Last 7 sessions drive the small performance sparkline in the sidebar.
        $recentScores = PracticeSession::forUser($user->id)
            ->latest('created_at')
            ->limit(7)
            ->pluck('total_score')
            ->reverse()
            ->values()
            ->map(fn ($s) => round((float) $s, 1))
            ->all();

        $aiConfig = array_merge($this->ai->clientConfig(), [
            'karakter'          => $selectedKarakter,
            'gerakan'           => $selectedGerakan,
            'maestroReferenceId'=> $activeReference?->id,
            'maestroVideo'      => $activeReference?->video_url,
            'startUrl'          => route('practice.start'),
            'endUrl'            => route('practice.end'),
            'weights'           => $karakters[$selectedKarakter]['weights'] ?? config('citra.weights'),
            'tempo'             => $karakters[$selectedKarakter]['tempo'] ?? [70, 100],
        ]);

        $settings = $user->settings();

        return view('practice', compact(
            'user', 'karakters', 'selectedKarakter', 'selectedGerakan',
            'references', 'activeReference', 'gerakanList', 'progress',
            'recentBest', 'recentScores', 'aiConfig', 'settings'
        ));
    }

    /**
     * Open a practice session.
     *
     * The row is created up front with status "active" so an abandoned session
     * is still visible (and prunable) rather than vanishing, and the AI backend
     * is told about it so live scoring has somewhere to accumulate.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'karakter'             => 'required|string|in:panji,samba,rumyang,tumenggung,klana',
            'gerakan'              => 'nullable|string|max:100',
            'maestro_reference_id' => 'nullable|integer|exists:maestro_references,id',
        ]);

        $user = Auth::user();

        $session = PracticeSession::create([
            'user_id'              => $user->id,
            'karakter'             => $validated['karakter'],
            'gerakan'              => $validated['gerakan'] ?? null,
            'maestro_reference_id' => $validated['maestro_reference_id'] ?? null,
            'wiraga_score'         => 0,
            'wirama_score'         => 0,
            'wirasa_score'         => 0,
            'total_score'          => 0,
            'duration'             => 0,
            'status'               => 'active',
        ]);

        // Ask the Python backend for a live-scoring session. If it is offline
        // the practice screen still works - it just scores in the browser.
        //
        // The request is authenticated with a JWT minted from the shared secret
        // rather than $request->bearerToken(): this is a session-authenticated
        // page, so there is no bearer token on the incoming request and the
        // call used to fail with 401 every time.
        $aiSession = $this->ai->post('/api/practice/start', [
            'karakter'             => $validated['karakter'],
            'gerakan'              => $validated['gerakan'] ?? null,
            'maestro_reference_id' => $validated['maestro_reference_id'] ?? null,
        ], $this->ai->issueToken($user));

        return response()->json([
            'success'        => true,
            'session_id'     => $session->id,
            'ai_session_id'  => $aiSession['session_id'] ?? null,
            'ai_online'      => $aiSession !== null,
            'message'        => 'Sesi latihan dimulai',
        ]);
    }

    /**
     * Close a practice session and commit the scores.
     *
     * Wrapped in a transaction: the session row, the user aggregates, the
     * leaderboard and the gerakan progress must all move together or not at
     * all, otherwise a mid-write failure leaves the leaderboard disagreeing
     * with the history.
     */
    public function end(Request $request)
    {
        $validated = $request->validate([
            'session_id'      => 'required|integer',
            'wiraga_score'    => 'required|numeric|min:0|max:100',
            'wirama_score'    => 'required|numeric|min:0|max:100',
            'wirasa_score'    => 'required|numeric|min:0|max:100',
            'duration'        => 'required|integer|min:0|max:86400',
            'frames_analyzed' => 'nullable|integer|min:0',
            'correct_frames'  => 'nullable|integer|min:0',
            'best_streak'     => 'nullable|integer|min:0',
            'feedback'        => 'nullable|array',
            'timeline'        => 'nullable|array',
            'score_series'    => 'nullable|array',
            'joint_scores'    => 'nullable|array',
        ]);

        $user = Auth::user();

        $session = PracticeSession::forUser($user->id)
            ->where('id', $validated['session_id'])
            ->firstOrFail();

        if ($session->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ini sudah diselesaikan sebelumnya.',
            ], 409);
        }

        $minSeconds = (int) config('citra.practice.min_session_seconds', 10);
        if ($validated['duration'] < $minSeconds) {
            // Too short to be a real attempt - discard rather than pollute the
            // stats and leaderboard with 2-second "sessions".
            $session->delete();

            return response()->json([
                'success'  => false,
                'discarded'=> true,
                'message'  => "Sesi terlalu singkat (minimal {$minSeconds} detik) dan tidak disimpan.",
            ], 422);
        }

        $weights = config("citra.karakters.{$session->karakter}.weights", config('citra.weights'));
        $totalScore = round(
            $validated['wiraga_score'] * $weights['wiraga']
            + $validated['wirama_score'] * $weights['wirama']
            + $validated['wirasa_score'] * $weights['wirasa'],
            1
        );

        $grade = $this->gradeFor($totalScore);
        $unlocked = [];

        DB::transaction(function () use (
            $session, $user, $validated, $totalScore, $grade, &$unlocked
        ) {
            $session->update([
                'wiraga_score'    => round($validated['wiraga_score'], 1),
                'wirama_score'    => round($validated['wirama_score'], 1),
                'wirasa_score'    => round($validated['wirasa_score'], 1),
                'total_score'     => $totalScore,
                'grade'           => $grade,
                'duration'        => $validated['duration'],
                'frames_analyzed' => $validated['frames_analyzed'] ?? 0,
                'correct_frames'  => $validated['correct_frames'] ?? 0,
                'best_streak'     => $validated['best_streak'] ?? 0,
                'feedback'        => $validated['feedback'] ?? [],
                'timeline'        => $validated['timeline'] ?? [],
                'score_series'    => $validated['score_series'] ?? [],
                'joint_scores'    => $validated['joint_scores'] ?? [],
                'status'          => 'completed',
            ]);

            $this->applyToUser($user, $session, $totalScore);
            $this->updateLeaderboard($user->id, $session->karakter, $totalScore);
            $this->updateGerakanProgress($user->id, $session->karakter, $session->gerakan, $totalScore);

            $unlocked = $this->achievements->evaluate($user);
        });

        return response()->json([
            'success'      => true,
            'session'      => [
                'id'       => $session->id,
                'title'    => $session->title,
                'wiraga'   => $session->wiraga_score,
                'wirama'   => $session->wirama_score,
                'wirasa'   => $session->wirasa_score,
                'total'    => $session->total_score,
                'duration' => $session->duration_for_humans,
                'accuracy' => $session->accuracy,
            ],
            'grade'        => $grade,
            'detail_url'   => route('history.show', $session->id),
            'level'        => $user->level,
            'total_score'  => $user->total_score,
            'streak'       => $user->current_streak,
            'achievements' => collect($unlocked)->map(fn ($a) => [
                'name' => $a->name, 'icon' => $a->icon, 'description' => $a->description,
            ])->all(),
            'message'      => 'Sesi latihan selesai!',
        ]);
    }

    /** Discard an abandoned "active" session (browser closed, camera denied). */
    public function abort(Request $request)
    {
        $validated = $request->validate(['session_id' => 'required|integer']);

        PracticeSession::forUser(Auth::id())
            ->where('id', $validated['session_id'])
            ->where('status', 'active')
            ->delete();

        return response()->json(['success' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function applyToUser($user, PracticeSession $session, float $totalScore): void
    {
        $user->practice_count = ($user->practice_count ?? 0) + 1;
        $user->total_score = ($user->total_score ?? 0) + (int) round($totalScore);
        $user->total_practice_seconds = ($user->total_practice_seconds ?? 0) + (int) $session->duration;

        // Streak: same day is a no-op, yesterday extends, anything else resets.
        $today = now()->startOfDay();
        $last = $user->last_practice_at?->startOfDay();

        if ($last === null || $last->lt($today->copy()->subDay())) {
            $user->current_streak = 1;
        } elseif ($last->equalTo($today->copy()->subDay())) {
            $user->current_streak = ($user->current_streak ?? 0) + 1;
        }
        // $last->equalTo($today) => already practised today, streak unchanged.

        $user->longest_streak = max($user->longest_streak ?? 0, $user->current_streak ?? 0);
        $user->last_practice_at = now();

        $user->updateLevel();
        $user->save();
    }

    private function updateLeaderboard(int $userId, string $karakter, float $score): void
    {
        $entry = Leaderboard::firstOrNew(
            ['user_id' => $userId, 'karakter' => $karakter],
            ['best_score' => 0]
        );

        $improved = !$entry->exists || $score > (float) $entry->best_score;
        if (!$improved) {
            return;
        }

        $entry->best_score = $score;
        $entry->save();

        // Re-rank only this character's board.
        $rows = Leaderboard::where('karakter', $karakter)
            ->orderByDesc('best_score')
            ->orderBy('updated_at')
            ->get();

        foreach ($rows as $index => $row) {
            $rank = $index + 1;
            if ($row->rank !== $rank) {
                $row->rank = $rank;
                $row->save();
            }
        }
    }

    private function updateGerakanProgress(
        int $userId, string $karakter, ?string $gerakan, float $score
    ): void {
        if (empty($gerakan)) {
            return;
        }

        $row = GerakanProgress::firstOrNew([
            'user_id'  => $userId,
            'karakter' => $karakter,
            'gerakan'  => $gerakan,
        ]);

        $row->attempts = ($row->attempts ?? 0) + 1;
        $row->best_score = max((float) ($row->best_score ?? 0), $score);

        if ($score >= 75 && !$row->completed) {
            $row->completed = true;
            $row->completed_at = now();
        }

        $row->save();
    }

    private function gradeFor(float $score): string
    {
        foreach (config('citra.grades', []) as $tier) {
            if ($score >= $tier['min']) {
                return $tier['grade'];
            }
        }
        return 'E';
    }
}
