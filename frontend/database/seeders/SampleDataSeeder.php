<?php

namespace Database\Seeders;

use App\Models\GerakanProgress;
use App\Models\Leaderboard;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo accounts with a believable practice history.
 *
 * Sessions are generated with a rising trend so the progress charts, streaks
 * and leaderboards all have something meaningful to show on a fresh install.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $karakters = array_keys(config('citra.karakters', []));
        if (empty($karakters)) {
            $this->command?->warn('No karakters configured; skipping sample data.');
            return;
        }

        $profiles = [
            ['name' => 'Ahmad Santoso', 'email' => 'ahmad@citra.test', 'sessions' => 42, 'skill' => 0.82],
            ['name' => 'Siti Rahayu',   'email' => 'siti@citra.test',  'sessions' => 58, 'skill' => 0.90],
            ['name' => 'Budi Prasetyo', 'email' => 'budi@citra.test',  'sessions' => 21, 'skill' => 0.65],
            ['name' => 'Dewi Lestari',  'email' => 'dewi@citra.test',  'sessions' => 35, 'skill' => 0.78],
            ['name' => 'Eko Wijaya',    'email' => 'eko@citra.test',   'sessions' => 9,  'skill' => 0.55],
        ];

        foreach ($profiles as $profile) {
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                [
                    'name'     => $profile['name'],
                    'password' => 'password123',   // hashed by the model cast
                    'avatar'   => 'default-avatar.png',
                    'settings' => config('citra.default_settings'),
                ]
            );

            // Re-running the seeder should not stack duplicate history.
            PracticeSession::where('user_id', $user->id)->delete();
            Leaderboard::where('user_id', $user->id)->delete();
            GerakanProgress::where('user_id', $user->id)->delete();
            DB::table('user_achievements')->where('user_id', $user->id)->delete();

            $this->generateHistory($user, $profile, $karakters);
        }

        $this->recomputeRanks($karakters);

        // Award achievements against the freshly generated history.
        $service = app(AchievementService::class);
        foreach ($profiles as $profile) {
            $user = User::where('email', $profile['email'])->first();
            if ($user) {
                $service->evaluate($user);
            }
        }

        $this->command?->info('Sample data seeded for '.count($profiles).' users.');
    }

    private function generateHistory(User $user, array $profile, array $karakters): void
    {
        $count = $profile['sessions'];
        $skill = $profile['skill'];

        $totalScore = 0;
        $totalSeconds = 0;
        $practiceDays = [];

        for ($i = 0; $i < $count; $i++) {
            // Older sessions are weaker; progress improves toward `skill`.
            $progress = $count > 1 ? $i / ($count - 1) : 1.0;
            $base = 100 * $skill * (0.62 + 0.38 * $progress);

            $wiraga = $this->clamp($base + rand(-70, 70) / 10);
            $wirama = $this->clamp($base + rand(-90, 60) / 10);
            $wirasa = $this->clamp($base + rand(-80, 70) / 10);

            $karakter = $karakters[array_rand($karakters)];
            $weights = config("citra.karakters.{$karakter}.weights", config('citra.weights'));
            $total = round(
                $wiraga * $weights['wiraga'] + $wirama * $weights['wirama'] + $wirasa * $weights['wirasa'],
                1
            );

            $gerakanList = config("citra.karakters.{$karakter}.gerakan", []);
            $gerakan = $gerakanList ? $gerakanList[array_rand($gerakanList)]['slug'] : null;

            // Spread sessions backwards from today over ~45 days.
            $daysAgo = (int) round((1 - $progress) * 45);
            $createdAt = Carbon::now()
                ->subDays($daysAgo)
                ->setTime(rand(7, 21), rand(0, 59));

            $duration = rand(120, 600);
            $frames = (int) round($duration * 12);
            $correct = (int) round($frames * ($total / 130));

            PracticeSession::create([
                'user_id'         => $user->id,
                'karakter'        => $karakter,
                'gerakan'         => $gerakan,
                'wiraga_score'    => round($wiraga, 1),
                'wirama_score'    => round($wirama, 1),
                'wirasa_score'    => round($wirasa, 1),
                'total_score'     => $total,
                'grade'           => $this->gradeFor($total),
                'duration'        => $duration,
                'frames_analyzed' => $frames,
                'correct_frames'  => $correct,
                'best_streak'     => rand(5, 60),
                'feedback'        => $this->feedbackFor($total),
                'timeline'        => [],
                'score_series'    => [],
                'joint_scores'    => $this->jointScores($total),
                'status'          => 'completed',
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            $totalScore += (int) round($total);
            $totalSeconds += $duration;
            $practiceDays[$createdAt->toDateString()] = true;

            $this->bumpLeaderboard($user->id, $karakter, $total);
            $this->bumpGerakan($user->id, $karakter, $gerakan, $total);
        }

        $user->total_score = $totalScore;
        $user->practice_count = $count;
        $user->total_practice_seconds = $totalSeconds;
        $user->last_practice_at = PracticeSession::where('user_id', $user->id)->max('created_at');

        $streak = app(\App\Services\StatsService::class)->recalculateStreak($user);
        $user->current_streak = $streak['current'];
        $user->longest_streak = $streak['longest'];

        $user->updateLevel();
        $user->save();
    }

    private function bumpLeaderboard(int $userId, string $karakter, float $score): void
    {
        $entry = Leaderboard::firstOrNew(
            ['user_id' => $userId, 'karakter' => $karakter],
            ['best_score' => 0]
        );

        if (!$entry->exists || $score > (float) $entry->best_score) {
            $entry->best_score = $score;
            $entry->save();
        }
    }

    private function bumpGerakan(int $userId, string $karakter, ?string $gerakan, float $score): void
    {
        if (!$gerakan) {
            return;
        }

        $row = GerakanProgress::firstOrNew([
            'user_id' => $userId, 'karakter' => $karakter, 'gerakan' => $gerakan,
        ]);

        $row->attempts = ($row->attempts ?? 0) + 1;
        $row->best_score = max((float) ($row->best_score ?? 0), $score);

        if ($score >= 75 && !$row->completed) {
            $row->completed = true;
            $row->completed_at = now();
        }

        $row->save();
    }

    private function recomputeRanks(array $karakters): void
    {
        foreach ($karakters as $karakter) {
            $rows = Leaderboard::where('karakter', $karakter)
                ->orderByDesc('best_score')
                ->get();

            foreach ($rows as $index => $row) {
                $row->rank = $index + 1;
                $row->save();
            }
        }
    }

    private function jointScores(float $total): array
    {
        $joints = [
            'left_shoulder_left_elbow_left_wrist',
            'right_shoulder_right_elbow_right_wrist',
            'left_elbow_left_shoulder_left_hip',
            'right_elbow_right_shoulder_right_hip',
            'left_shoulder_left_hip_left_knee',
            'right_shoulder_right_hip_right_knee',
            'left_hip_left_knee_left_ankle',
            'right_hip_right_knee_right_ankle',
            'left_knee_left_ankle_left_foot_index',
            'right_knee_right_ankle_right_foot_index',
            'right_shoulder_left_shoulder_left_elbow',
            'left_shoulder_right_shoulder_right_elbow',
        ];

        $out = [];
        foreach ($joints as $joint) {
            $out[$joint] = $this->clamp($total + rand(-150, 150) / 10);
        }
        return $out;
    }

    private function feedbackFor(float $total): array
    {
        if ($total >= 85) {
            return [
                'Gerakan tubuh sangat presisi dan sesuai pakem!',
                'Sinkronisasi dengan irama gamelan sangat baik!',
                'Ekspresi dan penghayatan sangat menjiwai karakter!',
            ];
        }
        if ($total >= 70) {
            return [
                'Gerakan tubuh cukup baik, beberapa posisi masih perlu diperbaiki.',
                'Irama cukup sesuai, tingkatkan kepekaan terhadap ketukan.',
                'Penghayatan sudah baik, tingkatkan intensitasnya.',
            ];
        }
        return [
            'Perlu latihan lebih untuk memperbaiki ketepatan posisi tubuh.',
            'Perlu lebih memperhatikan tempo dan ketukan gamelan.',
            'Perlu lebih menghayati karakter yang ditarikan.',
        ];
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

    private function clamp(float $value): float
    {
        return round(max(0, min(100, $value)), 1);
    }
}
