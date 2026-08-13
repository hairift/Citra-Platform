<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GerakanProgress extends Model
{
    protected $table = 'gerakan_progress';

    protected $fillable = [
        'user_id',
        'karakter',
        'gerakan',
        'best_score',
        'attempts',
        'completed',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'best_score'   => 'float',
            'attempts'     => 'integer',
            'completed'    => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
