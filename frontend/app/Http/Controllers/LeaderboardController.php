<?php

namespace App\Http\Controllers;

use App\Models\Leaderboard;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'karakter' => 'nullable|string|in:all,panji,samba,rumyang,tumenggung,klana',
            'period'   => 'nullable|string|in:all,week,month',
        ]);

        $selectedKarakter = $request->query('karakter', 'all');
        $selectedPeriod   = $request->query('period', 'all');

        // A single normalised row shape regardless of which board is shown, so
        // the view does not have to branch between User and Leaderboard models
        // (which is what made the old version unusable).
        $rows = $this->rows($selectedKarakter, $selectedPeriod);

        $topThree = $rows->take(3)->values();
        $rest = $rows->slice(3)->values();

        $me = Auth::user();
        $myRow = $rows->firstWhere('user_id', $me->id);
        $myRank = $myRow['rank'] ?? $this->fallbackRank($me, $selectedKarakter);

        $karakters = array_merge(
            ['all' => ['name' => 'Semua', 'icon' => '🏆']],
            collect(config('citra.karakters', []))
                ->map(fn ($k) => ['name' => $k['name'], 'icon' => $k['icon']])
                ->all()
        );

        $periods = [
            'all'   => 'Sepanjang Waktu',
            'week'  => 'Minggu Ini',
            'month' => 'Bulan Ini',
        ];

        return view('leaderboard', compact(
            'rows', 'topThree', 'rest', 'myRank', 'myRow',
            'karakters', 'selectedKarakter', 'periods', 'selectedPeriod'
        ));
    }

    /**
     * Build the ranking list.
     *
     * - "all" + "all"   -> lifetime cumulative score per user
     * - "all" + period  -> sum of scores in that period
     * - karakter + all  -> that character's best score (leaderboard table)
     * - karakter+period -> best score for that character within the period
     */
    private function rows(string $karakter, string $period, int $limit = 50)
    {
        if ($karakter === 'all' && $period === 'all') {
            return User::query()
                ->select('id', 'name', 'avatar', 'level', 'total_score', 'practice_count')
                ->where('total_score', '>', 0)
                ->orderByDesc('total_score')
                ->orderByDesc('practice_count')
                ->limit($limit)
                ->get()
                ->values()
                ->map(fn (User $u, int $i) => [
                    'rank'     => $i + 1,
                    'user_id'  => $u->id,
                    'name'     => $u->name,
                    'initial'  => $u->initial,
                    'avatar'   => $u->avatar_url,
                    'level'    => $u->level,
                    'score'    => (float) $u->total_score,
                    'sessions' => (int) $u->practice_count,
                    'karakter' => 'Semua',
                ]);
        }

        if ($karakter === 'all') {
            $query = PracticeSession::query()
                ->selectRaw('user_id, SUM(total_score) as score, COUNT(*) as sessions')
                ->groupBy('user_id')
                ->orderByDesc('score');

            $this->applyPeriod($query, $period);

            return $query->with('user:id,name,avatar,level')
                ->limit($limit)
                ->get()
                ->filter(fn ($r) => $r->user !== null)
                ->values()
                ->map(fn ($r, int $i) => [
                    'rank'     => $i + 1,
                    'user_id'  => $r->user_id,
                    'name'     => $r->user->name,
                    'initial'  => $r->user->initial,
                    'avatar'   => $r->user->avatar_url,
                    'level'    => $r->user->level,
                    'score'    => round((float) $r->score, 1),
                    'sessions' => (int) $r->sessions,
                    'karakter' => 'Semua',
                ]);
        }

        $karakterName = config("citra.karakters.{$karakter}.name", ucfirst($karakter));

        if ($period === 'all') {
            return Leaderboard::query()
                ->where('karakter', $karakter)
                ->with('user:id,name,avatar,level,practice_count')
                ->orderByDesc('best_score')
                ->limit($limit)
                ->get()
                ->filter(fn ($e) => $e->user !== null)
                ->values()
                ->map(fn ($e, int $i) => [
                    'rank'     => $i + 1,
                    'user_id'  => $e->user_id,
                    'name'     => $e->user->name,
                    'initial'  => $e->user->initial,
                    'avatar'   => $e->user->avatar_url,
                    'level'    => $e->user->level,
                    'score'    => round((float) $e->best_score, 1),
                    'sessions' => (int) $e->user->practice_count,
                    'karakter' => $karakterName,
                ]);
        }

        $query = PracticeSession::query()
            ->where('karakter', $karakter)
            ->selectRaw('user_id, MAX(total_score) as score, COUNT(*) as sessions')
            ->groupBy('user_id')
            ->orderByDesc('score');

        $this->applyPeriod($query, $period);

        return $query->with('user:id,name,avatar,level')
            ->limit($limit)
            ->get()
            ->filter(fn ($r) => $r->user !== null)
            ->values()
            ->map(fn ($r, int $i) => [
                'rank'     => $i + 1,
                'user_id'  => $r->user_id,
                'name'     => $r->user->name,
                'initial'  => $r->user->initial,
                'avatar'   => $r->user->avatar_url,
                'level'    => $r->user->level,
                'score'    => round((float) $r->score, 1),
                'sessions' => (int) $r->sessions,
                'karakter' => $karakterName,
            ]);
    }

    private function applyPeriod($query, string $period): void
    {
        match ($period) {
            'week'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => null,
        };
    }

    /**
     * Rank for a user who is outside the displayed top-N, so the "peringkat
     * Anda" box still shows a real number instead of nothing.
     */
    private function fallbackRank(User $user, string $karakter): ?int
    {
        if ($karakter === 'all') {
            return User::where('total_score', '>', $user->total_score ?? 0)->count() + 1;
        }

        $entry = Leaderboard::where('user_id', $user->id)
            ->where('karakter', $karakter)
            ->first();

        if (!$entry) {
            return null;
        }

        return Leaderboard::where('karakter', $karakter)
            ->where('best_score', '>', $entry->best_score)
            ->count() + 1;
    }

    /** JSON feed used by the filter tabs (no full page reload). */
    public function getData(Request $request)
    {
        $karakter = $request->query('karakter', 'all');
        $period   = $request->query('period', 'all');
        $limit    = min((int) $request->query('limit', 20), 50);

        return response()->json([
            'success'  => true,
            'karakter' => $karakter,
            'period'   => $period,
            'data'     => $this->rows($karakter, $period, $limit),
        ]);
    }
}
