<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageReport;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Services\Chat\ContentFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatReportController extends Controller
{
    protected ContentFilterService $contentFilterService;

    public function __construct(ContentFilterService $contentFilterService)
    {
        $this->contentFilterService = $contentFilterService;
    }

    /**
     * 获取活跃房间
     */
    private function findActiveRoom(int $roomId): ChatRoom
    {
        return ChatRoom::active()->findOrFail($roomId);
    }

    /**
     * 获取当前用户
     */
    private function getUser(): User
    {
        return Auth::user();
    }

    /**
     * 检查是否有房间管理权限
     */
    private function ensureCanModerate(User $user, ChatRoom $room, string $message): ?JsonResponse
    {
        if (! $user->canModerate($room)) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        return null;
    }

    /**
     * 检查是否是管理员
     */
    private function ensureAdmin(User $user, string $message): ?JsonResponse
    {
        if (! $user->hasRole('admin')) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        return null;
    }

    /**
     * Report a message.
     */
    public function reportMessage(Request $request, int $roomId, int $messageId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);
        $reporter = $this->getUser();

        // Prevent self-reporting
        if ($message->user_id === $reporter->id) {
            return response()->json([
                'message' => 'You cannot report your own message',
            ], 422);
        }

        $request->validate([
            'report_type' => [
                'required',
                Rule::in([
                    ChatMessageReport::TYPE_INAPPROPRIATE_CONTENT,
                    ChatMessageReport::TYPE_SPAM,
                    ChatMessageReport::TYPE_HARASSMENT,
                    ChatMessageReport::TYPE_HATE_SPEECH,
                    ChatMessageReport::TYPE_VIOLENCE,
                    ChatMessageReport::TYPE_SEXUAL_CONTENT,
                    ChatMessageReport::TYPE_MISINFORMATION,
                    ChatMessageReport::TYPE_OTHER,
                ]),
            ],
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            // Check if user has already reported this message
            $existingReport = ChatMessageReport::where('message_id', $messageId)
                ->where('reported_by', $reporter->id)
                ->whereNotNull('message_id') // Only check for existing messages
                ->first();

            if ($existingReport) {
                return response()->json([
                    'message' => 'You have already reported this message',
                    'existing_report' => $existingReport,
                ], 422);
            }

            $report = DB::transaction(function () use ($messageId, $roomId, $reporter, $request, $message) {
                // Create the report
                $report = ChatMessageReport::create([
                    'message_id' => $messageId,
                    'reported_by' => $reporter->id,
                    'room_id' => $roomId,
                    'report_type' => $request->report_type,
                    'reason' => $request->reason,
                    'status' => ChatMessageReport::STATUS_PENDING,
                    'metadata' => [
                        'reporter_ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'message_content' => $message->message,
                        'message_created_at' => $message->created_at->toISOString(),
                    ],
                ]);

                // Load relationships
                $report->load(['reporter:id,name,email', 'message.user:id,name,email']);

                // Check if this message should be auto-moderated based on report count
                $this->checkAutoModeration($messageId, $roomId);

                return $report;
            });

            return response()->json([
                'message' => 'Message reported successfully',
                'report' => $report,
                'report_id' => $report->id,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to report message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reports for a room (moderators only).
     */
    public function getRoomReports(Request $request, int $roomId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $user = $this->getUser();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($user, $room, 'You are not authorized to view reports for this room');
        if ($guard) {
            return $guard;
        }

        $status = $request->get('status');
        $reportType = $request->get('report_type');

        $query = ChatMessageReport::forRoom($roomId)
            ->with([
                'reporter:id,name,email',
                'message.user:id,name,email',
                'reviewer:id,name,email',
            ])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($reportType) {
            $query->ofType($reportType);
        }

        $paged = $query->jsonPaginate();
        [$data, $meta] = $this->normalizePagination($paged);

        return response()->json([
            'reports' => $data,
            'pagination' => $meta,
        ]);
    }

    /**
     * Get all reports (admin only).
     */
    public function getAllReports(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $guard = $this->ensureAdmin($user, 'You are not authorized to view all reports');
        if ($guard) {
            return $guard;
        }

        $status = $request->get('status');
        $reportType = $request->get('report_type');
        $roomId = $request->get('room_id');

        $query = ChatMessageReport::with([
            'reporter:id,name,email',
            'message.user:id,name,email',
            'room:id,name',
            'reviewer:id,name,email',
        ])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($reportType) {
            $query->ofType($reportType);
        }

        if ($roomId) {
            $query->forRoom($roomId);
        }

        $paged = $query->jsonPaginate();
        [$data, $meta] = $this->normalizePagination($paged);

        return response()->json([
            'reports' => $data,
            'pagination' => $meta,
        ]);
    }

    /**
     * Review a report (moderators only).
     */
    public function reviewReport(Request $request, int $reportId): JsonResponse
    {
        $report = ChatMessageReport::with(['room', 'message'])->findOrFail($reportId);
        $reviewer = $this->getUser();

        $room = $report->room;
        if (! $room instanceof ChatRoom) {
            return response()->json(['message' => 'Report or room not found'], 404);
        }

        // Check if user can moderate
        $guard = $this->ensureCanModerate($reviewer, $room, 'You are not authorized to review this report');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'action' => ['required', Rule::in(['resolve', 'dismiss', 'escalate'])],
            'notes' => 'nullable|string|max:1000',
            'delete_message' => 'boolean',
            'mute_user' => 'boolean',
            'mute_duration' => 'nullable|integer|min:1|max:10080', // Max 1 week
        ]);

        try {
            DB::beginTransaction();

            $action = $request->action;
            $notes = $request->notes;

            // Update report status
            switch ($action) {
                case 'resolve':
                    $report->markAsResolved($reviewer->id, $notes);
                    break;
                case 'dismiss':
                    $report->markAsDismissed($reviewer->id, $notes);
                    break;
                case 'escalate':
                    $report->markAsReviewed($reviewer->id, $notes);
                    break;
            }

            $actionsPerformed = [];

            // Delete message if requested
            if ($request->delete_message && $report->message) {
                $report->message->delete();
                $actionsPerformed[] = 'message_deleted';
            }

            // Mute user if requested
            if ($request->mute_user && $report->message) {
                $roomUser = \App\Models\Chat\ChatRoomUser::where('room_id', $report->room_id)
                    ->where('user_id', $report->message->user_id)
                    ->first();

                if ($roomUser) {
                    $muteDuration = $request->mute_duration ?? 60; // Default 1 hour
                    $roomUser->update([
                        'is_muted' => true,
                        'muted_until' => now()->addMinutes($muteDuration),
                        'muted_by' => $reviewer->id,
                    ]);
                    $actionsPerformed[] = 'user_muted';
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Report reviewed successfully',
                'report' => $report->fresh(['reporter:id,name,email', 'reviewer:id,name,email']),
                'action' => $action,
                'actions_performed' => $actionsPerformed,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to review report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get report statistics.
     */
    public function getReportStats(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $roomId = $request->get('room_id');
        $days = $request->get('days', 7);

        // Check permissions
        if ($roomId) {
            $room = $this->findActiveRoom($roomId);
            $guard = $this->ensureCanModerate($user, $room, 'You are not authorized to view stats for this room');
            if ($guard) {
                return $guard;
            }
        } else {
            $guard = $this->ensureAdmin($user, 'You are not authorized to view global report stats');
            if ($guard) {
                return $guard;
            }
        }

        $query = ChatMessageReport::where('created_at', '>=', now()->subDays($days));

        if ($roomId) {
            $query->forRoom($roomId);
        }

        $reports = $query->get();

        $stats = [
            'total_reports' => $reports->count(),
            'pending_reports' => $reports->where('status', ChatMessageReport::STATUS_PENDING)->count(),
            'resolved_reports' => $reports->where('status', ChatMessageReport::STATUS_RESOLVED)->count(),
            'dismissed_reports' => $reports->where('status', ChatMessageReport::STATUS_DISMISSED)->count(),
            'report_types' => [],
            'severity_breakdown' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'top_reporters' => [],
            'top_reported_users' => [],
            'period_days' => $days,
        ];

        // Report type breakdown
        $reportTypes = $reports->groupBy('report_type')->map->count();
        foreach ($reportTypes as $type => $count) {
            $stats['report_types'][$type] = $count;
        }

        // Severity breakdown
        foreach ($reports as $report) {
            $severity = $report->getSeverityLevel();
            $stats['severity_breakdown'][$severity]++;
        }

        // Top reporters (users who report the most)
        $topReporters = $reports->groupBy('reported_by')
            ->map(function ($userReports) {
                return [
                    'user_id' => $userReports->first()->reported_by,
                    'user_name' => $userReports->first()->reporter->name ?? 'Unknown',
                    'report_count' => $userReports->count(),
                ];
            })
            ->sortByDesc('report_count')
            ->take(5)
            ->values();

        $stats['top_reporters'] = $topReporters;

        // Top reported users (users who get reported the most)
        $topReportedUsers = $reports->groupBy('message.user_id')
            ->map(function ($userReports) {
                $firstReport = $userReports->first();

                return [
                    'user_id' => $firstReport->message->user_id ?? null,
                    'user_name' => $firstReport->message->user->name ?? 'Unknown',
                    'report_count' => $userReports->count(),
                ];
            })
            ->filter(function ($item) {
                return $item['user_id'] !== null;
            })
            ->sortByDesc('report_count')
            ->take(5)
            ->values();

        $stats['top_reported_users'] = $topReportedUsers;

        // Get content filter stats
        $filterStats = $this->contentFilterService->getFilterStats($roomId, $days);
        $stats['content_filter'] = $filterStats;

        return response()->json($stats);
    }

    /**
     * Check if a message should be auto-moderated based on report count.
     */
    private function checkAutoModeration(int $messageId, int $roomId): void
    {
        $reportCount = ChatMessageReport::where('message_id', $messageId)
            ->where('status', ChatMessageReport::STATUS_PENDING)
            ->count();

        // Auto-hide message if it gets 3 or more reports
        if ($reportCount >= 3) {
            $message = ChatMessage::find($messageId);
            if ($message) {
                // Log the auto-moderation action BEFORE deleting the message
                \App\Models\Chat\ChatModerationAction::create([
                    'room_id' => $roomId,
                    'moderator_id' => 1, // System user
                    'target_user_id' => $message->user_id,
                    'message_id' => $messageId,
                    'action_type' => \App\Models\Chat\ChatModerationAction::ACTION_DELETE_MESSAGE,
                    'reason' => 'Automatic deletion due to multiple reports',
                    'metadata' => [
                        'report_count' => $reportCount,
                        'auto_action' => true,
                        'original_message' => $message->message,
                    ],
                ]);

                // Get all pending reports for this message BEFORE deletion
                $pendingReports = ChatMessageReport::where('message_id', $messageId)
                    ->where('status', ChatMessageReport::STATUS_PENDING)
                    ->get();

                // Auto-resolve all reports that were for this message BEFORE deleting
                foreach ($pendingReports as $pendingReport) {
                    $pendingReport->update([
                        'status' => ChatMessageReport::STATUS_RESOLVED,
                        'reviewed_by' => 1, // System user
                        'reviewed_at' => now(),
                        'review_notes' => 'Auto-resolved due to message deletion from multiple reports',
                    ]);
                }

                // Delete the message (this will set message_id to null in reports due to foreign key)
                $message->delete();
            }
        }
    }

    /**
     * Normalize JsonApiPaginate output into a stable data/meta pair.
     *
     * @return array{0: mixed, 1: array}
     */
    private function normalizePagination(mixed $paged): array
    {
        if (is_array($paged) && isset($paged['data'])) {
            return [$paged['data'], $paged['meta'] ?? []];
        }

        if ($paged instanceof LengthAwarePaginator) {
            return [
                $paged->items(),
                [
                    'current_page' => $paged->currentPage(),
                    'per_page' => $paged->perPage(),
                    'total' => $paged->total(),
                ],
            ];
        }

        if (is_object($paged) && method_exists($paged, 'toArray')) {
            $arr = $paged->toArray();

            return [
                $arr['data'] ?? (method_exists($paged, 'items') ? $paged->items() : []),
                $arr['meta'] ?? (
                    method_exists($paged, 'currentPage')
                        ? [
                            'current_page' => $paged->currentPage(),
                            'per_page' => $paged->perPage(),
                            'total' => $paged->total(),
                        ]
                        : []
                ),
            ];
        }

        return [is_object($paged) && method_exists($paged, 'items') ? $paged->items() : [], []];
    }
}
