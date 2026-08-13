<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeSession extends Model
{
    protected $fillable = [
        'user_id',
        'karakter',
        'gerakan',
        'maestro_reference_id',
        'wiraga_score',
        'wirama_score',
        'wirasa_score',
        'total_score',
        'grade',
        'duration',
        'frames_analyzed',
        'correct_frames',
        'best_streak',
        'feedback',
        'timeline',
        'score_series',
        'joint_scores',
        'status',
        'pose_data',
    ];

    protected function casts(): array
    {
        return [
            'wiraga_score'    => 'float',
            'wirama_score'    => 'float',
            'wirasa_score'    => 'float',
            'total_score'     => 'float',
            'duration'        => 'integer',
            'frames_analyzed' => 'integer',
            'correct_frames'  => 'integer',
            'best_streak'     => 'integer',
            'feedback'        => 'array',
            'timeline'        => 'array',
            'score_series'    => 'array',
            'joint_scores'    => 'array',
            'pose_data'       => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function maestroReference()
    {
        return $this->belongsTo(MaestroReference::class, 'maestro_reference_id');
    }

    /* ---------------------------------------------------------------- */
    /* Presentation helpers                                              */
    /* ---------------------------------------------------------------- */

    public function getKarakterNameAttribute(): string
    {
        return config("citra.karakters.{$this->karakter}.name", ucfirst((string) $this->karakter));
    }

    public function getKarakterIconAttribute(): string
    {
        return config("citra.karakters.{$this->karakter}.icon", '🎭');
    }

    /**
     * Human name of the practised gerakan; falls back to the raw slug so a
     * session recorded before a gerakan was renamed still reads sensibly.
     */
    public function getGerakanNameAttribute(): string
    {
        if (empty($this->gerakan)) {
            return 'Semua Gerakan';
        }

        foreach (config("citra.karakters.{$this->karakter}.gerakan", []) as $item) {
            if ($item['slug'] === $this->gerakan) {
                return $item['name'];
            }
        }

        return ucwords(str_replace('_', ' ', $this->gerakan));
    }

    public function getTitleAttribute(): string
    {
        return $this->karakter_name.' - '.$this->gerakan_name;
    }

    public function getDurationForHumansAttribute(): string
    {
        $seconds = max(0, (int) $this->duration);
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function getAccuracyAttribute(): float
    {
        $frames = (int) $this->frames_analyzed;
        if ($frames <= 0) {
            return 0.0;
        }
        return round(100 * $this->correct_frames / $frames, 1);
    }

    public function getScoreClassAttribute(): string
    {
        return match (true) {
            $this->total_score >= 80 => 'high',
            $this->total_score >= 60 => 'medium',
            default                  => 'low',
        };
    }

    public function getResolvedGradeAttribute(): string
    {
        if (!empty($this->grade)) {
            return $this->grade;
        }

        foreach (config('citra.grades', []) as $tier) {
            if ($this->total_score >= $tier['min']) {
                return $tier['grade'];
            }
        }

        return 'E';
    }

    /* ---------------------------------------------------------------- */
    /* Scopes                                                            */
    /* ---------------------------------------------------------------- */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeKarakter($query, ?string $karakter)
    {
        return $karakter ? $query->where('karakter', $karakter) : $query;
    }

    public function scopePeriod($query, ?string $period)
    {
        return match ($period) {
            'today' => $query->whereDate('created_at', today()),
            'week'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => $query,
        };
    }
}
