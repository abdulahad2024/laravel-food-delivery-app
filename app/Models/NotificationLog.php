<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'event',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?int $userId, string $event, string $channel = 'system', array $payload = []): self
    {
        return self::create([
            'user_id' => $userId,
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
        ]);
    }
}
