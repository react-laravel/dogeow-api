<?php

namespace App\Listeners\WebPush;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class LogWebPushResult
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $report = $event->report;
        $response = $report->getResponse();

        $context = [
            'endpoint' => $report->getEndpoint(),
            'success' => $report->isSuccess(),
            'reason' => $report->getReason(),
            'expired' => $report->isSubscriptionExpired(),
            'status_code' => $response?->getStatusCode(),
            'response_body' => $report->getResponseContent(),
        ];

        if ($event instanceof NotificationFailed) {
            Log::warning('Web Push 发送失败', $context);
        } else {
            Log::info('Web Push 发送成功', $context);
        }
    }
}
