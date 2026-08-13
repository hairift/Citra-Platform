<?php

namespace App\Http\Controllers;

use App\Models\GerakanProgress;
use App\Services\AiBackendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function __construct(private AiBackendService $ai)
    {
    }

    public function index()
    {
        $user = Auth::user();

        return view('settings', [
            'user'     => $user,
            'settings' => $user->settings(),
            'aiHealth' => $this->ai->health(),
            'aiUrl'    => $this->ai->url(),
        ]);
    }

    /**
     * Persist the practice preferences.
     *
     * Booleans come from checkboxes, so an unchecked box sends nothing at all -
     * `$request->boolean()` correctly yields false for those. The whole set is
     * merged over the config defaults so a newly added preference is not lost.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'camera'         => 'nullable|string|max:50',
            'videoQuality'   => 'nullable|string|in:low,medium,high',
            'musicVolume'    => 'nullable|integer|min:0|max:100',
            'feedbackVolume' => 'nullable|integer|min:0|max:100',
            'difficulty'     => 'nullable|string|in:easy,medium,hard',
            'countdown'      => 'nullable|integer|in:0,3,5,10',
        ]);

        $user = Auth::user();

        $settings = array_merge($user->settings(), [
            'camera'            => $validated['camera'] ?? 'default',
            'videoQuality'      => $validated['videoQuality'] ?? 'medium',
            'musicVolume'       => (int) ($validated['musicVolume'] ?? 70),
            'feedbackVolume'    => (int) ($validated['feedbackVolume'] ?? 50),
            'difficulty'        => $validated['difficulty'] ?? 'medium',
            'countdown'         => (int) ($validated['countdown'] ?? 3),
            'showSkeleton'      => $request->boolean('showSkeleton'),
            'showLandmarks'     => $request->boolean('showLandmarks'),
            'mirrorMode'        => $request->boolean('mirrorMode'),
            'soundFeedback'     => $request->boolean('soundFeedback'),
            'showMaestro'       => $request->boolean('showMaestro'),
            'autoSave'          => $request->boolean('autoSave'),
            'reminderEnabled'   => $request->boolean('reminderEnabled'),
            'leaderboardNotify' => $request->boolean('leaderboardNotify'),
            'achievementNotify' => $request->boolean('achievementNotify'),
        ]);

        $user->settings = $settings;
        $user->save();

        return redirect()->route('settings')->with('success', 'Pengaturan berhasil disimpan!');
    }

    /**
     * Wipe practice history but keep the account.
     *
     * Requires the password: this is destructive and irreversible.
     */
    public function resetProgress(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ], ['password.required' => 'Masukkan password untuk mengonfirmasi.']);

        $user = Auth::user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        DB::transaction(function () use ($user) {
            $user->practiceSessions()->delete();
            $user->leaderboardEntries()->delete();
            GerakanProgress::where('user_id', $user->id)->delete();
            DB::table('user_achievements')->where('user_id', $user->id)->delete();
            $user->citraNotifications()->delete();

            $user->total_score = 0;
            $user->practice_count = 0;
            $user->current_streak = 0;
            $user->longest_streak = 0;
            $user->total_practice_seconds = 0;
            $user->last_practice_at = null;
            $user->level = 'Pemula';
            $user->progress = null;
            $user->save();
        });

        return redirect()->route('settings')
            ->with('success', 'Semua progress latihan telah direset.');
    }

    /**
     * Delete the account permanently.
     *
     * Guarded by password confirmation and an explicit typed confirmation
     * string, because the previous implementation deleted on a single click.
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password'  => 'required|string',
            'confirm'   => 'required|string|in:HAPUS',
        ], [
            'confirm.in' => 'Ketik HAPUS (huruf besar) untuk mengonfirmasi.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        // Remove the uploaded avatar before the row disappears.
        $avatar = $user->getRawOriginal('avatar');
        if ($avatar && $avatar !== 'default-avatar.png') {
            $path = public_path('avatars/'.$avatar);
            if (is_file($path)) {
                @unlink($path);
            }
        }

        DB::transaction(function () use ($user) {
            // The FK cascades cover most of these, but being explicit keeps
            // the behaviour identical on databases without cascade support.
            $user->practiceSessions()->delete();
            $user->leaderboardEntries()->delete();
            GerakanProgress::where('user_id', $user->id)->delete();
            DB::table('user_achievements')->where('user_id', $user->id)->delete();
            $user->citraNotifications()->delete();

            Auth::logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Akun Anda telah dihapus permanen.');
    }

    /** Export the user's own data as JSON (portability / thesis evidence). */
    public function exportData()
    {
        $user = Auth::user();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'name'           => $user->name,
                'email'          => $user->email,
                'level'          => $user->level,
                'total_score'    => $user->total_score,
                'practice_count' => $user->practice_count,
                'current_streak' => $user->current_streak,
                'longest_streak' => $user->longest_streak,
                'total_minutes'  => $user->total_minutes,
                'member_since'   => $user->created_at?->toIso8601String(),
            ],
            'sessions' => $user->practiceSessions()
                ->orderBy('created_at')
                ->get()
                ->map(fn ($s) => [
                    'created_at'   => $s->created_at?->toIso8601String(),
                    'karakter'     => $s->karakter,
                    'gerakan'      => $s->gerakan,
                    'wiraga'       => $s->wiraga_score,
                    'wirama'       => $s->wirama_score,
                    'wirasa'       => $s->wirasa_score,
                    'total'        => $s->total_score,
                    'grade'        => $s->resolved_grade,
                    'duration'     => $s->duration,
                    'accuracy'     => $s->accuracy,
                    'joint_scores' => $s->joint_scores,
                ]),
        ];

        $filename = 'citra-data-'.$user->id.'-'.now()->format('Ymd-His').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
