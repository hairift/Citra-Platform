<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    protected $table = 'leaderboard';
    
    protected $fillable = [
        'user_id',
        'karakter',
        'best_score',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'best_score' => 'float',
            'rank' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
