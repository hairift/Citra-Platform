<?php

namespace App\Services;

use App\Models\GerakanProgress;
use App\Models\Leaderboard;
use App\Models\PracticeSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All aggregate statistics for a user in one place.
 *
 * Previously the dashboard, profile and API controllers each computed these
 * differently - and each computed the rank by comparing a per-character best
 * score (0-100) against a cumulative total score (thousands), which produced a
 * meaningless number. There is now exactly one definition of each metric.
 */
class StatsService
{
    /* ------------------------------------------------------------------ */
    /* Headline stats                                                      */
    /* ------------------------------------------------------------------ */

    public function summary(User $user): array
    {
        $agg = PracticeSession::forUser($user->id)
            ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(duration),0) as seconds, '
                .'COALESCE(AVG(total_score),0) as avg_score, '
                .'COALESCE(MAX(total_score),0) as best_score')
            ->first();

        $sessions = (int) ($agg->sessions ?? 0);
        $seconds  = (int) ($agg->seconds ?? 0);

        return [
            'total_score'    => (int) ($user->total_score ?? 0),
            'practice_count' => $sessions,
            'level'          => $user->level ?? 'Pemula',
            'total_seconds'  => $seconds,
            'total_minutes'  => (int) floor($seconds / 60),
            'avg_score'      => round((float) ($agg->avg_score ?? 0), 1),
            'best_score'     => round((float) ($agg->best_score ?? 0), 1),
            'current_streak' => (int) ($user->current_streak ?? 0),
            'longest_streak' => (int) ($user->longest_streak ?? 0),
            'rank'           => $this->globalRank($user),
        ];
    }

    /**
     * Rank across all users by cumulative score.
     *
     * Ties share a rank, so two users on 4,500 points are both #3.
     */
    public function globalRank(User $user): int
    {
        return User::where('total_score', '>', $user->total_score ?? 0)->count() + 1;
    }

    /** Rank within one character, based on that character's best score. */
    public function karakterRank(User $user, string $karakter): ?int
    {
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

    /* ------------------------------------------------------------------ */
    /* Time series                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Per-day averages for the last N days, always returning exactly N entries
     * so the chart never collapses when the user skips a day.
     */
    public function weeklyProgress(User $user, int $days = 7): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $rows = PracticeSession::forUser($user->id)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, AVG(total_score) as avg_score, '
                .'MAX(total_score) as best_score, COUNT(*) as sessions, '
                .'COALESCE(SUM(duration),0) as seconds')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $out = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $key = $date->toDateString();
            $row = $rows->get($key);

            $out[] = [
                'day'      => $dayNames[$date->dayOfWeek],
                'date'     => $date->format('d M'),
                'iso'      => $key,
                'score'    => $row ? round((float) $row->avg_score, 1) : 0.0,
                'best'     => $row ? round((float) $row->best_score, 1) : 0.0,
                'sessions' => $row ? (int) $row->sessions : 0,
                'minutes'  => $row ? (int) floor($row->seconds / 60) : 0,
                'is_today' => $date->isToday(),
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Character mastery                                                   */
    /* ------------------------------------------------------------------ */

    public function characterMastery(User $user): array
    {
        $rows = PracticeSession::forUser($user->id)
            ->selectRaw('karakter, AVG(total_score) as avg_score, '
                .'MAX(total_score) as best_score, COUNT(*) as sessions, '
                .'COALESCE(SUM(duration),0) as seconds')
            ->groupBy('karakter')
            ->get()
            ->keyBy('karakter');

        $completed = GerakanProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->selectRaw('karakter, COUNT(*) as done')
            ->groupBy('karakter')
            ->pluck('done', 'karakter');

        $out = [];

        foreach (config('citra.karakters', []) as $slug => $meta) {
            $row = $rows->get($slug);
            $avg = $row ? (float) $row->avg_score : 0.0;
            $sessions = $row ? (int) $row->sessions : 0;
            $totalGerakan = max(count($meta['gerakan'] ?? []), 1);
            $doneGerakan = (int) ($completed[$slug] ?? 0);

            // Mastery blends score quality, experience and coverage, so one
            // lucky high-scoring run does not read as mastery.
            $mastery = $sessions === 0 ? 0.0 : min(100.0,
                $avg * 0.6
                + min($sessions, 20) * 1.0
                + ($doneGerakan / $totalGerakan) * 20.0
            );

            $out[] = [
                'slug'            => $slug,
                'name'            => $meta['name'],
                'icon'            => $meta['icon'],
                'color'           => $meta['color'] ?? '#E85A20',
                'difficulty'      => $meta['difficulty'],
                'score'           => round($avg, 1),
                'best_score'      => $row ? round((float) $row->best_score, 1) : 0.0,
                'sessions'        => $sessions,
                'minutes'         => $row ? (int) floor($row->seconds / 60) : 0,
                'gerakan_done'    => $doneGerakan,
                'gerakan_total'   => $totalGerakan,
                'mastery'         => round($mastery, 1),
                'level'           => $this->masteryLevel($sessions, $avg),
            ];
        }

        return $out;
    }

    private function masteryLevel(int $sessions, float $avg): string
    {
        if ($sessions === 0) {
            return 'Belum dimulai';
        }
        return match (true) {
            $avg >= 85 => 'Master',
            $avg >= 70 => 'Mahir',
            $avg >= 50 => 'Menengah',
            default    => 'Pemula',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Streaks                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Recompute streaks from the session history.
     *
     * Used by the achievement checker and as a self-heal if the incremental
     * counter on `users` ever drifts.
     */
    public function recalculateStreak(User $user): array
    {
        $days = PracticeSession::forUser($user->id)
            ->selectRaw('DATE(created_at) as day')
            ->distinct()
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($d) => Carbon::parse($d)->startOfDay())
            ->values();

        if ($days->isEmpty()) {
            return ['current' => 0, 'longest' => 0];
        }

        $today = Carbon::today();
        $current = 0;

        if ($days[0]->equalTo($today) || $days[0]->equalTo($today->copy()->subDay())) {
            $current = 1;
            for ($i = 0; $i < $days->count() - 1; $i++) {
                if ($days[$i]->diffInDays($days[$i + 1]) === 1) {
                    $current++;
                } else {
                    break;
                }
            }
        }

        $longest = 1;
        $run = 1;
        for ($i = 0; $i < $days->count() - 1; $i++) {
            if ($days[$i]->diffInDays($days[$i + 1]) === 1) {
                $run++;
                $longest = max($longest, $run);
            } else {
                $run = 1;
            }
        }

        return ['current' => $current, 'longest' => max($longest, $current)];
    }

    /* ------------------------------------------------------------------ */
    /* Misc                                                                */
    /* ------------------------------------------------------------------ */

    public function recentSessions(User $user, int $limit = 5): Collection
    {
        return PracticeSession::forUser($user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Weakest joints across the user's recent sessions - drives the
     * "area perbaikan" panel with something genuinely actionable.
     */
    public function weakestJoints(User $user, int $sessions = 10, int $take = 4): array
    {
        $rows = PracticeSession::forUser($user->id)
            ->whereNotNull('joint_scores')
            ->latest('created_at')
            ->limit($sessions)
            ->pluck('joint_scores');

        $totals = [];
        $counts = [];

        foreach ($rows as $jointScores) {
            foreach ((array) $jointScores as $joint => $score) {
                $totals[$joint] = ($totals[$joint] ?? 0) + (float) $score;
                $counts[$joint] = ($counts[$joint] ?? 0) + 1;
            }
        }

        $averages = [];
        foreach ($totals as $joint => $sum) {
            $averages[$joint] = round($sum / max($counts[$joint], 1), 1);
        }
        asort($averages);

        $labels = config('citra.joint_labels', []);
        $out = [];
        foreach (array_slice($averages, 0, $take, true) as $joint => $score) {
            $out[] = [
                'joint' => $joint,
                'label' => $labels[$joint] ?? $this->humanJoint($joint),
                'score' => $score,
            ];
        }

        return $out;
    }

    private function humanJoint(string $joint): string
    {
        $map = [
            'left_shoulder_left_elbow_left_wrist'      => 'Siku Kiri',
            'right_shoulder_right_elbow_right_wrist'   => 'Siku Kanan',
            'left_elbow_left_shoulder_left_hip'        => 'Lengan Kiri',
            'right_elbow_right_shoulder_right_hip'     => 'Lengan Kanan',
            'left_shoulder_left_hip_left_knee'         => 'Pinggul Kiri',
            'right_shoulder_right_hip_right_knee'      => 'Pinggul Kanan',
            'left_hip_left_knee_left_ankle'            => 'Lutut Kiri',
            'right_hip_right_knee_right_ankle'         => 'Lutut Kanan',
            'left_knee_left_ankle_left_foot_index'     => 'Kaki Kiri',
            'right_knee_right_ankle_right_foot_index'  => 'Kaki Kanan',
            'right_shoulder_left_shoulder_left_elbow'  => 'Bukaan Bahu Kiri',
            'left_shoulder_right_shoulder_right_elbow' => 'Bukaan Bahu Kanan',
        ];

        return $map[$joint] ?? ucwords(str_replace('_', ' ', $joint));
    }

    /** Aggregate platform stats for the public landing page. */
    public function platformStats(): array
    {
        return [
            'users'     => User::count(),
            'sessions'  => PracticeSession::count(),
            'minutes'   => (int) floor((PracticeSession::sum('duration') ?: 0) / 60),
            'karakters' => count(config('citra.karakters', [])),
        ];
    }
}
