<?php

namespace App\Http\Controllers\Api\Chat;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateRoomRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\UpdateRoomRequest;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoomUser;
use App\Services\Chat\ChatCacheService;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    use ChatControllerHelpers;

    protected ChatService $chatService;

    protected ChatCacheService $cacheService;

    private const RATE_LIMIT_MESSAGES_PER_MINUTE = 10;

    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    public function __construct(ChatService $chatService, ChatCacheService $cacheService)
    {
        $this->chatService = $chatService;
        $this->cacheService = $cacheService;
    }

    /**
     * Get all rooms
     */
    public function getRooms(): JsonResponse
    {
        try {
            $rooms = $this->chatService->getActiveRooms($this->getCurrentUserId());

            return $this->success(['rooms' => $rooms], 'Rooms retrieved successfully');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to retrieve rooms',
                $e,
                ['user_id' => $this->getCurrentUserId()],
                'Failed to retrieve rooms'
            );
        }
    }

    /**
     * Create room
     */
    public function createRoom(CreateRoomRequest $request): JsonResponse
    {
        try {
            $result = $this->chatService->createRoom([
                'name' => $request->name,
                'description' => $request->description,
                'is_private' => $request->boolean('is_private'),
            ], $this->getCurrentUserId());

            if (empty($result['success'])) {
                return $this->error('Failed to create room', $result['errors'] ?? []);
            }

            Log::info('Room created', [
                'room_id' => $result['room']->id,
                'room_name' => $result['room']->name,
                'created_by' => $this->getCurrentUserId(),
            ]);

            return $this->success(['room' => $result['room']], 'Room created successfully', 201);
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to create room',
                $e,
                [
                    'user_id' => $this->getCurrentUserId(),
                    'room_name' => $request->name ?? 'unknown',
                ],
                'Failed to create room'
            );
        }
    }

    /**
     * Update room
     */
    public function updateRoom(UpdateRoomRequest $request, $roomId): JsonResponse
    {
        $resolvedRoomId = $this->normalizeRoomId($roomId);

        try {
            $result = $this->chatService->updateRoom($resolvedRoomId, $request->validated(), $this->getCurrentUserId());

            if (empty($result['success'])) {
                return $this->error('Failed to update room', $result['errors'] ?? []);
            }

            return $this->success(['room' => $result['room']->load('creator:id,name,email')], 'Room updated successfully');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to update room',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $this->getCurrentUserId()),
                'Failed to update room'
            );
        }
    }

    /**
     * Join room
     */
    public function joinRoom($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $result = $this->chatService->joinRoom($resolvedRoomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to join room', $result['errors'] ?? []);
            }

            $this->clearCacheAndLogActivity($resolvedRoomId, 'user_joined', $userId);

            Log::info('User joined room', [
                'room_id' => $resolvedRoomId,
                'user_id' => $userId,
            ]);

            return $this->success([
                'room' => $result['room'],
                'room_user' => $result['room_user'],
            ], 'Successfully joined the room');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to join room',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to join room'
            );
        }
    }

    /**
     * Leave room
     */
    public function leaveRoom($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $result = $this->chatService->leaveRoom($resolvedRoomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to leave room', $result['errors'] ?? []);
            }

            $this->clearCacheAndLogActivity($resolvedRoomId, 'user_left', $userId);

            Log::info('User left room', [
                'room_id' => $resolvedRoomId,
                'user_id' => $userId,
            ]);

            return $this->success([], $result['message'] ?? 'Left room');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to leave room',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to leave room'
            );
        }
    }

    /**
     * Delete room (creator only)
     */
    public function deleteRoom($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $result = $this->chatService->deleteRoom($resolvedRoomId, $userId);

            if (empty($result['success'])) {
                $statusCode = (isset($result['errors']) && in_array('You do not have permission to delete this room', $result['errors'])) ? 403 : 422;

                return $this->error('Failed to delete room', $result['errors'] ?? [], $statusCode);
            }

            Log::info('Room deleted', [
                'room_id' => $resolvedRoomId,
                'deleted_by' => $userId,
            ]);

            return $this->success([], $result['message'] ?? 'Room deleted');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to delete room',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to delete room'
            );
        }
    }

    /**
     * Get room messages (paginated)
     */
    public function getMessages($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $room = $this->findActiveRoom($resolvedRoomId);

            $guard = $this->ensureUserInRoom($resolvedRoomId, $userId, 'You must join the room to view messages');
            if ($guard) {
                return $guard;
            }

            $paginated = $this->chatService->getMessageHistoryPaginated($resolvedRoomId);

            return response()->json($paginated);
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to retrieve messages',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to retrieve messages'
            );
        }
    }

    /**
     * Send message
     */
    public function sendMessage(SendMessageRequest $request, $roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $room = $this->findActiveRoom($resolvedRoomId);

            $roomUser = $this->fetchRoomUser($resolvedRoomId, $userId);
            if (! $roomUser || ! $roomUser->is_online) {
                return $this->error('You must be online in the room to send messages', [], 403);
            }

            $perm = $this->checkUserPermission($roomUser, $room);
            if (! $perm['allowed']) {
                return $this->error($perm['message'], [], 403);
            }

            $rate = $this->checkRate($userId, $resolvedRoomId);
            if (! $rate['allowed']) {
                return $this->error($rate['message'], $rate['data'] ?? [], 429);
            }

            $result = $this->chatService->processMessage(
                $resolvedRoomId,
                $userId,
                $request->message,
                $request->message_type ?? ChatMessage::TYPE_TEXT
            );

            if (empty($result['success'])) {
                $errors = $result['errors'] ?? [];
                $message = is_array($errors) && $errors !== [] ? $errors[0] : 'Failed to send message';

                return $this->error($message, ['errors' => $errors]);
            }

            $roomUser->updateLastSeen();

            $this->clearRoomCache($resolvedRoomId);
            $this->cacheService->invalidateMessageHistory($resolvedRoomId);
            $this->logRoomActivity($resolvedRoomId, 'message_sent', $userId);

            broadcast(new MessageSent($result['message']));

            Log::info('Message sent', [
                'message_id' => $result['message']->id,
                'room_id' => $resolvedRoomId,
                'user_id' => $userId,
                'message_type' => $result['message']->message_type,
            ]);

            return $this->success([
                'data' => new ChatMessageResource($result['message']),
                'mentions' => $result['mentions'] ?? [],
            ], 'Message sent successfully', 201);
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to send message',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to send message'
            );
        }
    }

    /**
     * Check user permission (mute/ban). Admin or room owner is not affected by mute/ban.
     */
    private function checkUserPermission(ChatRoomUser $roomUser, \App\Models\Chat\ChatRoom $room): array
    {
        $user = auth()->user();
        if ($user && $user->canModerate($room)) {
            return ['allowed' => true];
        }

        if (! $roomUser->canSendMessages()) {
            if ($roomUser->isBanned()) {
                $msg = 'You are banned from this room';
                if ($roomUser->banned_until) {
                    $msg .= ' until ' . $roomUser->banned_until->format('Y-m-d H:i:s');
                }

                return ['allowed' => false, 'message' => $msg];
            }
            if ($roomUser->isMuted()) {
                $msg = 'You are muted in this room';
                if ($roomUser->muted_until) {
                    $msg .= ' until ' . $roomUser->muted_until->format('Y-m-d H:i:s');
                }

                return ['allowed' => false, 'message' => $msg];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Check rate limit
     */
    private function checkRate(int $userId, int $roomId): array
    {
        $key = "send_message:{$userId}:{$roomId}";
        $res = $this->cacheService->checkRateLimit(
            $key,
            self::RATE_LIMIT_MESSAGES_PER_MINUTE,
            self::RATE_LIMIT_WINDOW_SECONDS
        );
        if (empty($res['allowed'])) {
            $reset = $res['reset_time']->diffInSeconds(now());

            return [
                'allowed' => false,
                'message' => "Too many messages. Please wait {$reset} seconds before sending another message.",
                'data' => [
                    'rate_limit' => [
                        'attempts' => $res['attempts'],
                        'remaining' => $res['remaining'],
                        'reset_time' => $res['reset_time']->toISOString(),
                    ],
                ],
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Delete message (admin)
     */
    public function deleteMessage($roomId, $messageId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $messageId = (int) $messageId;

            $room = $this->findActiveRoom($resolvedRoomId);
            $message = ChatMessage::where('room_id', $resolvedRoomId)->findOrFail($messageId);

            $canDelete = $message->user_id === $userId || $room->created_by === $userId;
            if (! $canDelete) {
                return $this->error('You are not authorized to delete this message', [], 403);
            }

            DB::beginTransaction();
            $deletedMessageId = $message->id;
            $deletedRoomId = $message->room_id;
            $message->delete();
            DB::commit();

            broadcast(new MessageDeleted($deletedMessageId, $deletedRoomId, $userId));

            Log::info('Message deleted', [
                'message_id' => $deletedMessageId,
                'room_id' => $deletedRoomId,
                'deleted_by' => $userId,
            ]);

            return $this->success([], 'Message deleted successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to delete message',
                $e,
                [
                    'message_id' => $messageId,
                    ...$this->buildRoomErrorContext($resolvedRoomId, $userId),
                ],
                'Failed to delete message'
            );
        }
    }

    /**
     * Get room online users
     */
    public function getOnlineUsers($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $room = $this->findActiveRoom($resolvedRoomId);

            $guard = $this->ensureUserInRoom($resolvedRoomId, $userId, 'You must join the room to view online users');
            if ($guard) {
                return $guard;
            }

            $onlineUsers = $this->chatService->getOnlineUsers($resolvedRoomId);

            return $this->success([
                'online_users' => $onlineUsers,
                'count' => $onlineUsers->count(),
            ], 'Online users retrieved successfully');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to retrieve online users',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to retrieve online users'
            );
        }
    }

    /**
     * Update user status (heartbeat)
     */
    public function updateUserStatus($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $room = $this->findActiveRoom($resolvedRoomId);

            $result = $this->chatService->processHeartbeat($resolvedRoomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to update status', $result['errors'] ?? [], 404);
            }

            return $this->success([
                'last_seen_at' => $result['last_seen_at'],
            ], 'Status updated successfully');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to update user status',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to update status'
            );
        }
    }

    /**
     * Clean up offline users
     */
    public function cleanupDisconnectedUsers(): JsonResponse
    {
        try {
            $result = $this->chatService->cleanupInactiveUsers();

            if (empty($result['success'])) {
                return $this->error('Failed to cleanup disconnected users', $result['errors'] ?? [], 500);
            }

            Log::info('Disconnected users cleanup', [
                'cleaned_users_count' => $result['cleaned_count'],
                'initiated_by' => $this->getCurrentUserId(),
            ]);

            return $this->success([
                'cleaned_users_count' => $result['cleaned_count'],
            ], $result['message'] ?? 'Cleanup done');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to cleanup disconnected users',
                $e,
                ['initiated_by' => $this->getCurrentUserId()],
                'Failed to cleanup disconnected users'
            );
        }
    }

    /**
     * Get user presence status in room
     */
    public function getUserPresenceStatus($roomId): JsonResponse
    {
        $userId = null;
        $resolvedRoomId = null;

        try {
            [$userId, $resolvedRoomId] = $this->resolveUserAndRoomId($roomId);
            $room = $this->findActiveRoom($resolvedRoomId);

            $roomUser = $this->fetchRoomUser($resolvedRoomId, $userId);

            if (! $roomUser) {
                return $this->success([
                    'is_in_room' => false,
                    'is_online' => false,
                ], 'You are not in this room');
            }

            return $this->success([
                'is_in_room' => true,
                'is_online' => $roomUser->is_online,
                'joined_at' => $roomUser->joined_at,
                'last_seen_at' => $roomUser->last_seen_at,
                'is_inactive' => $roomUser->isInactive(),
            ], 'User presence status retrieved successfully');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'Failed to get user presence status',
                $e,
                $this->buildRoomErrorContext($resolvedRoomId, $userId),
                'Failed to get user presence status'
            );
        }
    }

    /**
     * @param  mixed  $roomId
     * @return array{0: int, 1: int}
     */
    private function resolveUserAndRoomId($roomId): array
    {
        $userId = $this->getCurrentUserId();

        return [$userId, $this->normalizeRoomId($roomId)];
    }

    /**
     * @return array{room_id: int|null, user_id: int|null}
     */
    private function buildRoomErrorContext(?int $roomId, ?int $userId = null): array
    {
        return [
            'room_id' => $roomId,
            'user_id' => $userId,
        ];
    }
}
