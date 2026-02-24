<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebSocketDisconnectService
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 处理 WebSocket 断开连接
     */
    public function handleDisconnect(int $userId, ?string $connectionId = null): void
    {
        try {
            Log::info("WebSocket disconnect detected for user: {$userId}, connection: {$connectionId}");

            // 获取用户当前在线的所有房间
            $onlineRooms = ChatRoomUser::where('user_id', $userId)
                ->where('is_online', true)
                ->with(['room:id,name'])
                ->get();

            if ($onlineRooms->isEmpty()) {
                Log::info("User {$userId} was not in any rooms");

                return;
            }

            DB::beginTransaction();

            foreach ($onlineRooms as $roomUser) {
                $roomId = $roomUser->room_id;

                Log::info("Marking user {$userId} as offline in room {$roomId}");

                // 更新用户状态为离线
                $roomUser->markAsOffline();

                // 获取当前在线人数
                $onlineCount = ChatRoomUser::where('room_id', $roomId)
                    ->where('is_online', true)
                    ->count();

                // 广播用户离开事件
                $user = User::find($userId);
                if ($user) {
                    broadcast(new \App\Events\Chat\UserLeft($user, $roomId));
                    broadcast(new \App\Events\Chat\UserLeftRoom($roomId, $userId, $user->name, $onlineCount));

                    Log::info("Broadcasted user left event for user {$userId} in room {$roomId}");
                }
            }

            DB::commit();
            Log::info("Successfully processed disconnect for user {$userId} in " . $onlineRooms->count() . ' rooms');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process WebSocket disconnect: ' . $e->getMessage(), [
                'user_id' => $userId,
                'connection_id' => $connectionId,
                'error' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 检查并清理长时间未活跃的连接
     */
    public function cleanupInactiveConnections(int $inactiveMinutes = 5): int
    {
        try {
            Log::info("Starting cleanup of inactive connections for {$inactiveMinutes} minutes");

            // 获取长时间未活跃的用户
            $inactiveUsers = ChatRoomUser::online()
                ->inactiveSince($inactiveMinutes)
                ->with(['user:id,name', 'room:id,name'])
                ->get();

            $cleanedCount = 0;
            foreach ($inactiveUsers as $roomUser) {
                Log::info("Cleaning up inactive user {$roomUser->user->name} in room {$roomUser->room->name}");

                $this->handleDisconnect($roomUser->user_id);
                $cleanedCount++;
            }

            Log::info("Cleanup completed. Processed {$cleanedCount} inactive users");

            return $cleanedCount;

        } catch (\Exception $e) {
            Log::error('Failed to cleanup inactive connections: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * 获取房间的实时在线用户数
     */
    public function getRoomOnlineCount(int $roomId): int
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('is_online', true)
            ->count();
    }

    /**
     * 检查用户是否在房间中在线
     */
    public function isUserOnlineInRoom(int $userId, int $roomId): bool
    {
        return ChatRoomUser::where('user_id', $userId)
            ->where('room_id', $roomId)
            ->where('is_online', true)
            ->exists();
    }
}
