<?php

namespace App\Listeners\Notifications;

use App\Events\UserNotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notifiable instanceof User) {
            return;
        }

        if (! in_array($event->channel, ['database', DatabaseChannel::class], true)) {
            return;
        }

        $notification = $event->response;
        if (! $notification instanceof DatabaseNotification) {
            $notification = $event->notifiable->notifications()->latest()->first();
        }

        if (! $notification instanceof DatabaseNotification) {
            return;
        }

        if ($notification->read_at !== null) {
            return;
        }

        broadcast(new UserNotificationCreated(
            userId: (int) $event->notifiable->id,
            notificationId: (string) $notification->id,
            type: (string) $notification->type,
            data: $notification->data,
            createdAt: $notification->created_at?->toIso8601String() ?? now()->toIso8601String(),
            unreadCount: $event->notifiable->unreadNotifications()->count()
        ));
    }
}
