<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Thing\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $email
 * @property mixed $is_admin
 * @property mixed $is_active
 * @property mixed $created_by
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Check if the user can moderate a chat room.
     */
    public function canModerate($room = null): bool
    {
        // Admins can moderate any room
        if ($this->isAdmin()) {
            return true;
        }

        // Room creators can moderate their own rooms
        if ($room && $room->created_by === $this->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return match ($role) {
            'admin' => $this->isAdmin(),
            'moderator' => $this->isAdmin(), // For now, only admins are moderators
            default => false,
        };
    }

    /**
     * Get the items that belong to the user.
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get the notes that belong to the user.
     */
    public function notes()
    {
        return $this->hasMany(\App\Models\Note\Note::class);
    }

    /**
     * Get the chat rooms created by the user.
     */
    public function createdRooms()
    {
        return $this->hasMany(\App\Models\Chat\ChatRoom::class, 'created_by');
    }

    /**
     * Get the chat rooms the user has joined.
     */
    public function joinedRooms()
    {
        return $this->belongsToMany(\App\Models\Chat\ChatRoom::class, 'chat_room_users', 'user_id', 'room_id')
            ->withPivot(['joined_at', 'last_seen_at', 'is_online'])
            ->withTimestamps();
    }

    /**
     * Get the chat messages sent by the user.
     */
    public function chatMessages()
    {
        return $this->hasMany(\App\Models\Chat\ChatMessage::class);
    }

    /**
     * Scope to get only active users.
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Get user's full name or email if name is not available.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Get user's initials for avatar.
     */
    public function getInitialsAttribute(): string
    {
        $name = $this->name ?: $this->email;
        $words = explode(' ', $name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Check if user is online in any chat room.
     */
    public function isOnlineInAnyRoom(): bool
    {
        return $this->joinedRooms()->wherePivot('is_online', true)->exists();
    }
}
