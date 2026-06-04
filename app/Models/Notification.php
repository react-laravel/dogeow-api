<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class Notification extends DatabaseNotification
{
    use HasUuids;

    protected static function booting(): void
    {
        static::creating(function (self $notification) {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
        ];
    }
}
