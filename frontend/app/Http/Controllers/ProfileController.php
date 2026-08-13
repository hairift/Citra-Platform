<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Services\AchievementService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(
        private StatsService $stats,
        private AchievementService $achievements,
    ) {
    }

    public function index()
    {
        $user = Auth::user();

        $summary          = $this->stats->summary($user);
        $characterMastery = $this->stats->characterMastery($user);
        $recentSessions   = $this->stats->recentSessions($user, 6);
        $weakestJoints    = $this->stats->weakestJoints($user);
        $achievements     = $this->achievements->listForUser($user);

        $unlockedCount = collect($achievements)->where('unlocked', true)->count();

        // Best single session, shown as the "rekor pribadi" card.
        $bestSession = PracticeSession::forUser($user->id)
            ->orderByDesc('total_score')
            ->first();

        return view('profile', compact(
            'user', 'summary', 'characterMastery', 'recentSessions',
            'weakestJoints', 'achievements', 'unlockedCount', 'bestSession'
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'current_password.required_with' => 'Masukkan password lama untuk mengubah password.',
            'password.confirmed'             => 'Konfirmasi password baru tidak cocok.',
        ]);

        // Changing a password requires proving you know the old one.
        if (!empty($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'Password lama salah.'])
                    ->withInput();
            }
            $user->password = $validated['password'];   // 'hashed' cast handles it
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return redirect()->route('profile')->with('success', 'Profil berhasil diperbarui!');
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'avatar.max' => 'Ukuran gambar maksimal 2 MB.',
        ]);

        $user = Auth::user();
        $directory = public_path('avatars');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = $request->file('avatar');
        $filename = 'avatar_'.$user->id.'_'.time().'.'.$file->getClientOriginalExtension();

        // Remove the previous file so the folder does not grow without bound.
        $previous = $user->getRawOriginal('avatar');
        if ($previous && $previous !== 'default-avatar.png') {
            $previousPath = $directory.DIRECTORY_SEPARATOR.$previous;
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        $file->move($directory, $filename);

        $user->avatar = $filename;
        $user->save();

        return redirect()->route('profile')->with('success', 'Foto profil berhasil diperbarui!');
    }

    public function removeAvatar()
    {
        $user = Auth::user();
        $current = $user->getRawOriginal('avatar');

        if ($current && $current !== 'default-avatar.png') {
            $path = public_path('avatars/'.$current);
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $user->avatar = 'default-avatar.png';
        $user->save();

        return redirect()->route('profile')->with('success', 'Foto profil dihapus.');
    }
}
