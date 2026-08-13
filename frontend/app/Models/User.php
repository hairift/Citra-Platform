<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'level',
        'total_score',
        'practice_count',
        'current_streak',
        'longest_streak',
        'total_practice_seconds',
        'last_practice_at',
        'is_admin',
        'progress',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'last_practice_at'       => 'datetime',
            'password'               => 'hashed',
            'progress'               => 'array',
            'settings'               => 'array',
            'total_score'            => 'integer',
            'practice_count'         => 'integer',
            'current_streak'         => 'integer',
            'longest_streak'         => 'integer',
            'total_practice_seconds' => 'integer',
            'is_admin'               => 'boolean',
        ];
    }

    protected $attributes = [
        'level'          => 'Pemula',
        'total_score'    => 0,
        'practice_count' => 0,
    ];

    /* ---------------------------------------------------------------- */
    /* Relations                                                         */
    /* ---------------------------------------------------------------- */

    public function practiceSessions()
    {
        return $this->hasMany(PracticeSession::class);
    }

    public function leaderboardEntries()
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function citraNotifications()
    {
        return $this->hasMany(CitraNotification::class);
    }

    public function gerakanProgress()
    {
        return $this->hasMany(GerakanProgress::class);
    }

    /* ---------------------------------------------------------------- */
    /* Accessors                                                         */
    /* ---------------------------------------------------------------- */

    public function getInitialAttribute(): string
    {
        return strtoupper(mb_substr($this->name ?? 'U', 0, 1));
    }

    /**
     * Resolvable avatar URL, or null when the user still has the placeholder
     * so the UI can fall back to the coloured initial tile.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar) || $this->avatar === 'default-avatar.png') {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return file_exists(public_path('avatars/'.$this->avatar))
            ? asset('avatars/'.$this->avatar)
            : null;
    }

    public function getTotalMinutesAttribute(): int
    {
        return (int) floor(($this->total_practice_seconds ?? 0) / 60);
    }

    /**
     * User settings merged over the configured defaults, so a key added to
     * config/citra.php is immediately available to every existing account.
     */
    public function settings(): array
    {
        $defaults = config('citra.default_settings', []);
        $stored = $this->settings;

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function setting(string $key, $fallback = null)
    {
        return $this->settings()[$key] ?? $fallback;
    }

    /* ---------------------------------------------------------------- */
    /* Behaviour                                                         */
    /* ---------------------------------------------------------------- */

    /**
     * Recompute the level from config/citra.php.
     *
     * Note this only mutates the model - the caller decides when to persist,
     * which avoids the double-save the old implementation performed.
     */
    public function updateLevel(): string
    {
        $sessions = $this->practice_count ?? 0;
        $score = $this->total_score ?? 0;

        foreach (config('citra.levels', []) as $tier) {
            if ($sessions >= $tier['min_sessions'] && $score >= $tier['min_score']) {
                $this->level = $tier['name'];
                return $this->level;
            }
        }

        $this->level = 'Pemula';
        return $this->level;
    }

    /**
     * Global rank by cumulative score. Ranks are 1-based and users with an
     * identical score share the same rank.
     */
    public function globalRank(): int
    {
        return static::where('total_score', '>', $this->total_score ?? 0)->count() + 1;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
