<?php

namespace App\Http\Controllers;

use App\Models\CitraNotification;
use App\Models\MaestroReference;
use App\Services\AchievementService;
use App\Services\AiBackendService;
use App\Services\StatsService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private StatsService $stats,
        private AchievementService $achievements,
        private AiBackendService $ai,
    ) {
    }

    public function index()
    {
        $user = Auth::user();

        $summary         = $this->stats->summary($user);
        $weeklyProgress  = $this->stats->weeklyProgress($user);
        $characterMastery = $this->stats->characterMastery($user);
        $recentSessions  = $this->stats->recentSessions($user, 5);
        $weakestJoints   = $this->stats->weakestJoints($user);

        // Highest weekly score sets the chart's 100% mark, so a beginner's
        // 40-point week still renders as a readable bar chart.
        $chartMax = max(
            100,
            (int) ceil(collect($weeklyProgress)->max('score') ?: 0)
        );

        $unlockedAchievements = collect($this->achievements->listForUser($user))
            ->where('unlocked', true)
            ->take(6)
            ->values()
            ->all();

        $notifications = CitraNotification::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        // Only offer a reference that actually has footage - the curriculum
        // rows seeded from config have no video and would render an empty
        // player.
        $featured = MaestroReference::published()
            ->maestro()
            ->withVideo()
            ->karakter(config('citra.practice.default_karakter', 'klana'))
            ->ordered()
            ->first()
            ?? MaestroReference::published()->maestro()->withVideo()->ordered()->first();

        $quickActions = [
            ['title' => 'Mulai Latihan', 'desc' => 'Latih gerakan dengan AI real-time', 'icon' => '🎭', 'route' => 'practice',    'color' => '#E85A20'],
            ['title' => 'Tutorial',      'desc' => 'Pelajari gerakan dari maestro',    'icon' => '📚', 'route' => 'tutorial',    'color' => '#3B82F6'],
            ['title' => 'Leaderboard',   'desc' => 'Bandingkan skor Anda',             'icon' => '🏆', 'route' => 'leaderboard', 'color' => '#22C55E'],
            ['title' => 'Riwayat',       'desc' => 'Tinjau sesi latihan sebelumnya',   'icon' => '📋', 'route' => 'history',     'color' => '#8B5CF6'],
        ];

        return view('dashboard', compact(
            'user', 'summary', 'weeklyProgress', 'characterMastery',
            'recentSessions', 'weakestJoints', 'chartMax', 'unlockedAchievements',
            'notifications', 'featured', 'quickActions'
        ));
    }

    /** JSON stats for the dashboard's auto-refresh. */
    public function getStats()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'summary'          => $this->stats->summary($user),
                'weekly_progress'  => $this->stats->weeklyProgress($user),
                'character_mastery'=> $this->stats->characterMastery($user),
                'ai_online'        => $this->ai->isOnline(),
            ],
        ]);
    }
}
