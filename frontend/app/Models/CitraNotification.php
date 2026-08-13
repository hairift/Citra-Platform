<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitraNotification extends Model
{
    protected $table = 'citra_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }
}
