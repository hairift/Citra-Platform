<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoseDataset extends Model
{
    protected $fillable = [
        'slug',
        'karakter',
        'title',
        'role',
        'source_video',
        'web_video',
        'poster',
        'duration_seconds',
        'sample_fps',
        'sampled_frames',
        'detected_frames',
        'detection_rate',
        'segment_count',
        'resolution',
        'segments',
        'frames',
        'description',
        'built_at',
    ];

    protected function casts(): array
    {
        return [
            'segments'         => 'array',
            'frames'           => 'array',
            'duration_seconds' => 'float',
            'sample_fps'       => 'float',
            'sampled_frames'   => 'integer',
            'detected_frames'  => 'integer',
            'detection_rate'   => 'float',
            'segment_count'    => 'integer',
            'built_at'         => 'datetime',
        ];
    }

    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->web_video)) {
            return null;
        }
        $relative = 'videos/maestro/'.ltrim($this->web_video, '/');
        return file_exists(public_path($relative)) ? asset($relative) : null;
    }

    public function getPosterUrlAttribute(): ?string
    {
        if (empty($this->poster)) {
            return null;
        }
        $relative = 'videos/maestro/'.ltrim($this->poster, '/');
        return file_exists(public_path($relative)) ? asset($relative) : null;
    }

    /** Published annotated stills, filtered to files that really exist. */
    public function getFrameUrlsAttribute(): array
    {
        $urls = [];
        foreach ($this->frames ?? [] as $file) {
            $relative = 'pose-frames/'.$this->karakter.'/frames/'.$file;
            if (file_exists(public_path($relative))) {
                $urls[] = ['url' => asset($relative), 'file' => $file];
            }
        }
        return $urls;
    }

    public function getDurationForHumansAttribute(): string
    {
        $seconds = (int) round($this->duration_seconds ?? 0);
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function getDetectionPercentAttribute(): float
    {
        return round(($this->detection_rate ?? 0) * 100, 1);
    }

    public function scopeKarakter($query, ?string $karakter)
    {
        return $karakter ? $query->where('karakter', $karakter) : $query;
    }
}
