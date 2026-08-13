<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    public function __construct(private StatsService $stats)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'karakter' => 'nullable|string|in:panji,samba,rumyang,tumenggung,klana',
            'period'   => 'nullable|string|in:today,week,month',
            'sort'     => 'nullable|string|in:newest,oldest,best,worst',
        ]);

        $selectedKarakter = $request->query('karakter');
        $selectedPeriod   = $request->query('period');
        $sort             = $request->query('sort', 'newest');

        $query = PracticeSession::forUser($user->id)
            ->karakter($selectedKarakter)
            ->period($selectedPeriod);

        $query = match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'best'   => $query->orderByDesc('total_score'),
            'worst'  => $query->orderBy('total_score'),
            default  => $query->orderByDesc('created_at'),
        };

        $sessions = $query->paginate(10)->withQueryString();

        // Stats reflect the ACTIVE FILTER, so the numbers agree with the table
        // below them. The previous version always showed lifetime totals.
        $filtered = PracticeSession::forUser($user->id)
            ->karakter($selectedKarakter)
            ->period($selectedPeriod)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(total_score),0) as avg_score, '
                .'COALESCE(MAX(total_score),0) as best_score, COALESCE(SUM(duration),0) as seconds')
            ->first();

        $summary = [
            'total_sessions' => (int) $filtered->total,
            'avg_score'      => round((float) $filtered->avg_score, 1),
            'best_score'     => round((float) $filtered->best_score, 1),
            'total_minutes'  => (int) floor($filtered->seconds / 60),
        ];

        $karakters = config('citra.karakters', []);

        return view('history', compact(
            'sessions', 'summary', 'karakters',
            'selectedKarakter', 'selectedPeriod', 'sort'
        ));
    }

    public function show(int $id)
    {
        $user = Auth::user();

        $session = PracticeSession::forUser($user->id)->findOrFail($id);

        // Previous session with the same character, for the comparison panel.
        $previousSession = PracticeSession::forUser($user->id)
            ->where('karakter', $session->karakter)
            ->where('id', '<', $session->id)
            ->orderByDesc('id')
            ->first();

        $comparison = null;
        if ($previousSession) {
            $comparison = [];
            foreach (['wiraga_score' => 'Wiraga', 'wirama_score' => 'Wirama',
                      'wirasa_score' => 'Wirasa', 'total_score' => 'Total'] as $field => $label) {
                $delta = (float) $session->{$field} - (float) $previousSession->{$field};
                $comparison[] = [
                    'label'   => $label,
                    'delta'   => round($delta, 1),
                    'current' => round((float) $session->{$field}, 1),
                    'previous'=> round((float) $previousSession->{$field}, 1),
                ];
            }
        }

        $karakterMeta = config("citra.karakters.{$session->karakter}", []);
        $jointLabels = $this->jointLabels();

        // Sort the per-joint scores worst-first: that is the actionable order.
        $jointScores = collect($session->joint_scores ?? [])
            ->map(fn ($score, $joint) => [
                'joint' => $joint,
                'label' => $jointLabels[$joint] ?? ucwords(str_replace('_', ' ', $joint)),
                'score' => round((float) $score, 1),
            ])
            ->sortBy('score')
            ->values()
            ->all();

        return view('session-detail', compact(
            'session', 'previousSession', 'comparison', 'karakterMeta', 'jointScores'
        ));
    }

    /** JSON history feed used by the dashboard widget. */
    public function getHistory(Request $request)
    {
        $sessions = PracticeSession::forUser(Auth::id())
            ->karakter($request->query('karakter'))
            ->latest('created_at')
            ->limit(min((int) $request->query('limit', 10), 50))
            ->get()
            ->map(fn (PracticeSession $s) => [
                'id'         => $s->id,
                'title'      => $s->title,
                'icon'       => $s->karakter_icon,
                'karakter'   => $s->karakter,
                'total'      => round($s->total_score, 1),
                'grade'      => $s->resolved_grade,
                'duration'   => $s->duration_for_humans,
                'created_at' => $s->created_at?->toIso8601String(),
                'ago'        => $s->created_at?->diffForHumans(),
                'url'        => route('history.show', $s->id),
            ]);

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    private function jointLabels(): array
    {
        return [
            'left_shoulder_left_elbow_left_wrist'      => 'Siku Kiri',
            'right_shoulder_right_elbow_right_wrist'   => 'Siku Kanan',
            'left_elbow_left_shoulder_left_hip'        => 'Angkatan Lengan Kiri',
            'right_elbow_right_shoulder_right_hip'     => 'Angkatan Lengan Kanan',
            'left_shoulder_left_hip_left_knee'         => 'Pinggul Kiri',
            'right_shoulder_right_hip_right_knee'      => 'Pinggul Kanan',
            'left_hip_left_knee_left_ankle'            => 'Lutut Kiri',
            'right_hip_right_knee_right_ankle'         => 'Lutut Kanan',
            'left_knee_left_ankle_left_foot_index'     => 'Pergelangan Kaki Kiri',
            'right_knee_right_ankle_right_foot_index'  => 'Pergelangan Kaki Kanan',
            'right_shoulder_left_shoulder_left_elbow'  => 'Bukaan Bahu Kiri',
            'left_shoulder_right_shoulder_right_elbow' => 'Bukaan Bahu Kanan',
        ];
    }
}
