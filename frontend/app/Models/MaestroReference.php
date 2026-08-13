<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaestroReference extends Model
{
    protected $fillable = [
        'slug',
        'karakter',
        'gerakan_name',
        'gerakan_slug',
        'role',
        'video_path',
        'poster_path',
        'pose_keyframes',
        'keyframes_path',
        'segments',
        'duration_seconds',
        'start_time',
        'end_time',
        'frame_count',
        'detection_rate',
        'sample_frames',
        'audio_path',
        'beat_timestamps',
        'description',
        'difficulty',
        'hitungan',
        'tips',
        'instructions',
        'order_index',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'pose_keyframes'   => 'array',
            'beat_timestamps'  => 'array',
            'segments'         => 'array',
            'sample_frames'    => 'array',
            'tips'             => 'array',
            'instructions'     => 'array',
            'duration_seconds' => 'float',
            'start_time'       => 'float',
            'end_time'         => 'float',
            'detection_rate'   => 'float',
            'frame_count'      => 'integer',
            'hitungan'         => 'integer',
            'order_index'      => 'integer',
            'is_published'     => 'boolean',
        ];
    }

    public function practiceSessions()
    {
        return $this->hasMany(PracticeSession::class, 'maestro_reference_id');
    }

    /* ---------------------------------------------------------------- */
    /* URLs                                                              */
    /* ---------------------------------------------------------------- */

    /**
     * Web-playable video URL, or null when only the raw (untranscoded) source
     * exists - the tutorial player then shows its "belum tersedia" state
     * instead of a broken <video> element.
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->video_path)) {
            return null;
        }

        if (str_starts_with($this->video_path, 'http')) {
            return $this->video_path;
        }

        $relative = 'videos/maestro/'.ltrim($this->video_path, '/');
        return file_exists(public_path($relative)) ? asset($relative) : null;
    }

    public function getPosterUrlAttribute(): ?string
    {
        if (empty($this->poster_path)) {
            return null;
        }

        $relative = 'videos/maestro/'.ltrim($this->poster_path, '/');
        return file_exists(public_path($relative)) ? asset($relative) : null;
    }

    /**
     * Annotated dataset stills (joint dots drawn on) published for this
     * reference. Only files that actually exist are returned.
     */
    public function getFrameUrlsAttribute(): array
    {
        $urls = [];
        foreach ($this->sample_frames ?? [] as $file) {
            $relative = 'pose-frames/'.$this->karakter.'/frames/'.$file;
            if (file_exists(public_path($relative))) {
                $urls[] = asset($relative);
            }
        }
        return $urls;
    }

    public function getHasDatasetAttribute(): bool
    {
        return $this->frame_count > 0 && !empty($this->keyframes_path);
    }

    public function getDurationForHumansAttribute(): string
    {
        $seconds = (int) round($this->duration_seconds ?? 0);
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function getDifficultyLabelAttribute(): string
    {
        return match ($this->difficulty) {
            'mudah'    => 'Mudah',
            'menengah' => 'Menengah',
            'sulit'    => 'Sulit',
            default    => ucfirst((string) $this->difficulty),
        };
    }

    /* ---------------------------------------------------------------- */
    /* Scopes                                                            */
    /* ---------------------------------------------------------------- */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeKarakter($query, ?string $karakter)
    {
        return $karakter ? $query->where('karakter', $karakter) : $query;
    }

    public function scopeMaestro($query)
    {
        return $query->where('role', 'maestro');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index')->orderBy('id');
    }

    /** Only references that have a playable video attached. */
    public function scopeWithVideo($query)
    {
        return $query->whereNotNull('video_path');
    }

    /**
     * Only references the practice engine can actually score against, i.e.
     * those whose pose dataset has been extracted.
     *
     * The curriculum rows seeded from config/citra.php exist so the tutorial
     * shows a complete syllabus, but they carry no keyframes - picking one of
     * those as the active reference would leave the practice screen with no
     * maestro video and no comparison data.
     */
    public function scopeScorable($query)
    {
        return $query->whereNotNull('keyframes_path');
    }
}
