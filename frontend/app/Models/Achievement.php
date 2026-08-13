<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'icon',
        'description',
        'rule',
        'threshold',
        'karakter',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'threshold'   => 'integer',
            'order_index' => 'integer',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index')->orderBy('id');
    }
}
