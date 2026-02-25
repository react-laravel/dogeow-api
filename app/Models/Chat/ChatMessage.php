<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $id
 * @property mixed $room_id
 * @property mixed $user_id
 * @property mixed $message
 * @property mixed $message_type
 * @property \App\Models\User $user
 */
class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'message',
        'message_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Message type constants
     */
    const TYPE_TEXT = 'text';

    const TYPE_SYSTEM = 'system';

    /**
     * Get the chat room this message belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Chat\ChatRoom::class, 'room_id');
    }

    /**
     * Get the user who sent this message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only text messages.
     */
    public function scopeTextMessages($query)
    {
        return $query->where('message_type', self::TYPE_TEXT);
    }

    /**
     * Scope to get only system messages.
     */
    public function scopeSystemMessages($query)
    {
        return $query->where('message_type', self::TYPE_SYSTEM);
    }

    /**
     * Scope to get messages for a specific room.
     */
    public function scopeForRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Check if this is a text message.
     */
    public function isTextMessage(): bool
    {
        return $this->message_type === self::TYPE_TEXT;
    }

    /**
     * Check if this is a system message.
     */
    public function isSystemMessage(): bool
    {
        return $this->message_type === self::TYPE_SYSTEM;
    }
}
