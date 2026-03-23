<?php

namespace Tests\Unit\Listeners\WebPush;

use App\Listeners\WebPush\LogWebPushResult;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Mockery;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class LogWebPushResultTest extends TestCase
{
    public function test_listener_can_be_instantiated(): void
    {
        $listener = new LogWebPushResult;
        $this->assertInstanceOf(LogWebPushResult::class, $listener);
    }

    public function test_handle_method_exists(): void
    {
        $listener = new LogWebPushResult;
        $this->assertTrue(method_exists($listener, 'handle'));
    }

    public function test_handle_method_has_correct_return_type(): void
    {
        $listener = new LogWebPushResult;
        $reflection = new \ReflectionMethod($listener, 'handle');

        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }

    public function test_handle_accepts_union_type_parameter(): void
    {
        $listener = new LogWebPushResult;
        $reflection = new \ReflectionMethod($listener, 'handle');

        $params = $reflection->getParameters();

        $this->assertCount(1, $params);

        $paramType = $params[0]->getType();
        $this->assertNotNull($paramType);

        // Check it's a union type
        $this->assertInstanceOf(\ReflectionUnionType::class, $paramType);
    }

    public function test_handle_has_union_type_with_both_event_classes(): void
    {
        $listener = new LogWebPushResult;
        $reflection = new \ReflectionMethod($listener, 'handle');

        $paramType = $reflection->getParameters()[0]->getType();

        // It's a union type
        $this->assertInstanceOf(\ReflectionUnionType::class, $paramType);

        $types = $paramType->getTypes();

        $this->assertCount(2, $types);

        $typeNames = array_map(fn ($t) => $t->getName(), $types);

        $this->assertContains(NotificationSent::class, $typeNames);
        $this->assertContains(NotificationFailed::class, $typeNames);
    }

    public function test_handle_logs_info_when_notification_sent(): void
    {
        $request = new Request('POST', 'https://push.example.com/endpoint-id');
        $response = new Response(201);
        $report = new MessageSentReport($request, $response, true, 'OK');
        $subscription = Mockery::mock(PushSubscription::class);
        $message = new WebPushMessage;

        Log::shouldReceive('info')
            ->once()
            ->with('Web Push 发送成功', Mockery::on(function (array $ctx): bool {
                return $ctx['endpoint'] === 'https://push.example.com/endpoint-id'
                    && $ctx['success'] === true
                    && $ctx['status_code'] === 201;
            }));

        $listener = new LogWebPushResult;
        $listener->handle(new NotificationSent($report, $subscription, $message));
    }

    public function test_handle_logs_warning_when_notification_failed(): void
    {
        $request = new Request('POST', 'https://push.example.com/endpoint-fail');
        $response = new Response(410);
        $report = new MessageSentReport($request, $response, false, 'Gone');
        $subscription = Mockery::mock(PushSubscription::class);
        $message = new WebPushMessage;

        Log::shouldReceive('warning')
            ->once()
            ->with('Web Push 发送失败', Mockery::on(function (array $ctx): bool {
                return $ctx['endpoint'] === 'https://push.example.com/endpoint-fail'
                    && $ctx['success'] === false
                    && $ctx['status_code'] === 410;
            }));

        $listener = new LogWebPushResult;
        $listener->handle(new NotificationFailed($report, $subscription, $message));
    }

    public function test_handle_passes_correct_context_on_success(): void
    {
        $request = new Request('POST', 'https://push.example.com/success');
        $response = new Response(201, [], 'created');
        $report = new MessageSentReport($request, $response, true, 'OK');
        $subscription = Mockery::mock(PushSubscription::class);
        $message = new WebPushMessage;

        Log::shouldReceive('info')
            ->once()
            ->with('Web Push 发送成功', Mockery::on(function (array $ctx): bool {
                return isset(
                    $ctx['endpoint'],
                    $ctx['success'],
                    $ctx['reason'],
                    $ctx['expired'],
                    $ctx['status_code'],
                    $ctx['response_body']
                )
                    && $ctx['endpoint'] === 'https://push.example.com/success'
                    && $ctx['success'] === true
                    && $ctx['reason'] === 'OK'
                    && $ctx['expired'] === false
                    && $ctx['status_code'] === 201
                    && $ctx['response_body'] === 'created';
            }));

        $listener = new LogWebPushResult;
        $listener->handle(new NotificationSent($report, $subscription, $message));
    }

    public function test_handle_passes_correct_context_on_failure(): void
    {
        $request = new Request('POST', 'https://push.example.com/expired');
        $response = new Response(410, [], 'subscription expired');
        $report = new MessageSentReport($request, $response, false, 'Gone');
        $subscription = Mockery::mock(PushSubscription::class);
        $message = new WebPushMessage;

        Log::shouldReceive('warning')
            ->once()
            ->with('Web Push 发送失败', Mockery::on(function (array $ctx): bool {
                return isset(
                    $ctx['endpoint'],
                    $ctx['success'],
                    $ctx['reason'],
                    $ctx['expired'],
                    $ctx['status_code'],
                    $ctx['response_body']
                )
                    && $ctx['endpoint'] === 'https://push.example.com/expired'
                    && $ctx['success'] === false
                    && $ctx['reason'] === 'Gone'
                    && $ctx['expired'] === true
                    && $ctx['status_code'] === 410
                    && $ctx['response_body'] === 'subscription expired';
            }));

        $listener = new LogWebPushResult;
        $listener->handle(new NotificationFailed($report, $subscription, $message));
    }

    public function test_handle_handles_null_response(): void
    {
        $request = new Request('POST', 'https://push.example.com/no-response');
        $report = new MessageSentReport($request, null, false, 'Connection error');
        $subscription = Mockery::mock(PushSubscription::class);
        $message = new WebPushMessage;

        Log::shouldReceive('warning')
            ->once()
            ->with('Web Push 发送失败', Mockery::on(function (array $ctx): bool {
                return $ctx['endpoint'] === 'https://push.example.com/no-response'
                    && $ctx['success'] === false
                    && $ctx['reason'] === 'Connection error'
                    && $ctx['status_code'] === null
                    && $ctx['response_body'] === null
                    && $ctx['expired'] === false;
            }));

        $listener = new LogWebPushResult;
        $listener->handle(new NotificationFailed($report, $subscription, $message));
    }
}
