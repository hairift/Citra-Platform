<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\CitraNotification;
use App\Models\GerakanProgress;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates the achievement rules declared in config/citra.php.
 *
 * Called after every finished session; each newly satisfied achievement is
 * recorded once and raises an in-app notification.
 */
class AchievementService
{
    public function __construct(private StatsService $stats)
    {
    }

    /**
     * Check every achievement for a user.
     *
     * @return array<int, Achievement> the achievements unlocked by this call
     */
    public function evaluate(User $user): array
    {
        $achievements = Achievement::ordered()->get();
        if ($achievements->isEmpty()) {
            return [];
        }

        $alreadyUnlocked = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->pluck('achievement_id')
            ->all();

        $metrics = $this->metrics($user);
        $newlyUnlocked = [];

        foreach ($achievements as $achievement) {
            if (in_array($achievement->id, $alreadyUnlocked, true)) {
                continue;
            }

            if (!$this->satisfies($achievement, $metrics)) {
                continue;
            }

            DB::table('user_achievements')->insert([
                'user_id'        => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            CitraNotification::create([
                'user_id' => $user->id,
                'type'    => 'achievement',
                'title'   => 'Pencapaian baru: '.$achievement->name,
                'message' => $achievement->description,
                'icon'    => $achievement->icon,
                'link'    => route('profile'),
            ]);

            $newlyUnlocked[] = $achievement;
        }

        return $newlyUnlocked;
    }

    /**
     * Everything the rules can be evaluated against, computed once per call.
     */
    private function metrics(User $user): array
    {
        $streak = $this->stats->recalculateStreak($user);

        $perKarakter = PracticeSession::forUser($user->id)
            ->selectRaw('karakter, AVG(total_score) as avg_score, COUNT(*) as sessions')
            ->groupBy('karakter')
            ->get()
            ->keyBy('karakter');

        return [
            'sessions'    => (int) PracticeSession::forUser($user->id)->count(),
            'best_score'  => (float) (PracticeSession::forUser($user->id)->max('total_score') ?? 0),
            'minutes'     => (int) floor((PracticeSession::forUser($user->id)->sum('duration') ?: 0) / 60),
            'streak'      => (int) $streak['longest'],
            'rank'        => $this->stats->globalRank($user),
            'karakter_variety' => (int) PracticeSession::forUser($user->id)
                ->distinct()->count('karakter'),
            'karakter_avg' => $perKarakter->map(fn ($r) => (float) $r->avg_score)->all(),
            'gerakan_done' => (int) GerakanProgress::where('user_id', $user->id)
                ->where('completed', true)->count(),
        ];
    }

    private function satisfies(Achievement $achievement, array $metrics): bool
    {
        $threshold = (int) $achievement->threshold;

        return match ($achievement->rule) {
            'sessions'         => $metrics['sessions'] >= $threshold,
            'best_score'       => $metrics['best_score'] >= $threshold,
            'minutes'          => $metrics['minutes'] >= $threshold,
            'streak'           => $metrics['streak'] >= $threshold,
            'karakter_variety' => $metrics['karakter_variety'] >= $threshold,
            'gerakan_done'     => $metrics['gerakan_done'] >= $threshold,
            // Rank counts *down*: rank 3 satisfies a "top 10" achievement.
            'rank'             => $metrics['rank'] > 0 && $metrics['rank'] <= $threshold,
            'karakter_avg'     => $achievement->karakter
                && ($metrics['karakter_avg'][$achievement->karakter] ?? 0) >= $threshold,
            default            => false,
        };
    }

    /**
     * Full achievement list with per-user unlock state - what the profile page
     * renders. Locked entries are included so the user can see what to aim for.
     */
    public function listForUser(User $user): array
    {
        $unlocked = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->pluck('unlocked_at', 'achievement_id');

        return Achievement::ordered()->get()->map(fn (Achievement $a) => [
            'slug'        => $a->slug,
            'name'        => $a->name,
            'icon'        => $a->icon,
            'description' => $a->description,
            'unlocked'    => $unlocked->has($a->id),
            'unlocked_at' => $unlocked->get($a->id),
        ])->all();
    }

    public function unlockedCount(User $user): int
    {
        return DB::table('user_achievements')->where('user_id', $user->id)->count();
    }
}
