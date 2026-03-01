<?php

namespace Tests\Unit\Jobs;

use App\Events\KnowledgeIndexUpdated;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TriggerKnowledgeIndexBuildJobTest extends TestCase
{
    public function test_job_can_be_constructed(): void
    {
        $job = new TriggerKnowledgeIndexBuildJob;

        $this->assertInstanceOf(TriggerKnowledgeIndexBuildJob::class, $job);
        $this->assertEquals(2, $job->tries);
        $this->assertEquals(300, $job->timeout);
        $this->assertEquals(120, $job->uniqueFor);
    }

    public function test_job_implements_should_be_unique(): void
    {
        $job = new TriggerKnowledgeIndexBuildJob;

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new TriggerKnowledgeIndexBuildJob;

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_job_uses_default_queue(): void
    {
        $job = new TriggerKnowledgeIndexBuildJob;

        $this->assertEquals('default', $job->queue);
    }

    public function test_unique_id_returns_knowledge_index_build(): void
    {
        $job = new TriggerKnowledgeIndexBuildJob;

        $this->assertEquals('knowledge-index-build', $job->uniqueId());
    }

    public function test_unique_id_is_consistent(): void
    {
        $job1 = new TriggerKnowledgeIndexBuildJob;
        $job2 = new TriggerKnowledgeIndexBuildJob;

        $this->assertEquals($job1->uniqueId(), $job2->uniqueId());
    }

    public function test_handle_skips_when_url_not_configured(): void
    {
        config(['services.knowledge.build_index_url' => null]);

        Log::spy();
        Http::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口 URL 未配置，跳过构建'
        );
        Http::assertNothingSent();
    }

    public function test_handle_skips_when_url_is_empty_string(): void
    {
        config(['services.knowledge.build_index_url' => '']);

        Log::spy();
        Http::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口 URL 未配置，跳过构建'
        );
        Http::assertNothingSent();
    }

    public function test_handle_skips_when_url_is_false(): void
    {
        config(['services.knowledge.build_index_url' => false]);

        Log::spy();
        Http::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口 URL 未配置，跳过构建'
        );
        Http::assertNothingSent();
    }

    public function test_handle_triggers_build_and_broadcasts_on_success(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake([
            $url => Http::response([], 200),
        ]);

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Http::assertSent(function ($request) use ($url): bool {
            return $request->url() === $url
                && $request['force'] === true;
        });

        Event::assertDispatched(KnowledgeIndexUpdated::class, function ($event): bool {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $event->updatedAt
            );

            return true;
        });

        Log::shouldHaveReceived('info')->with(
            'KnowledgeIndexBuild: 索引构建已触发',
            \Mockery::on(function ($context) use ($url): bool {
                return isset($context['url'], $context['updated_at'])
                    && $context['url'] === $url;
            })
        );
    }

    public function test_handle_uses_post_method(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake([$url => Http::response([], 200)]);

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Http::assertSent(function ($request): bool {
            return strtoupper($request->method()) === 'POST';
        });
    }

    public function test_handle_sends_force_true_in_payload(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake([$url => Http::response([], 200)]);

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return isset($data['force']) && $data['force'] === true;
        });
    }

    public function test_handle_success_broadcasts_event_with_iso8601_format(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response([], 200)]);

        Event::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertDispatched(KnowledgeIndexUpdated::class, function ($event): bool {
            $iso8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

            return (bool) preg_match($iso8601, $event->updatedAt);
        });
    }

    public function test_handle_201_created_is_treated_as_success(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response([], 201)]);

        Event::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertDispatched(KnowledgeIndexUpdated::class);
    }

    public function test_handle_logs_warning_when_response_is_500(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake([
            $url => Http::response('Server Error', 500),
        ]);

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);

        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口响应异常',
            \Mockery::on(function ($context) use ($url): bool {
                return isset($context['url'], $context['status'], $context['body'])
                    && $context['url'] === $url
                    && $context['status'] === 500;
            })
        );
    }

    public function test_handle_logs_warning_when_response_is_404(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response('Not Found', 404)]);

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);
        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口响应异常',
            \Mockery::on(function ($context): bool {
                return $context['status'] === 404;
            })
        );
    }

    public function test_handle_logs_warning_when_response_is_502(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response('Bad Gateway', 502)]);

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);
        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口响应异常',
            \Mockery::on(function ($context): bool {
                return $context['status'] === 502;
            })
        );
    }

    public function test_handle_warning_includes_response_body(): void
    {
        $url = 'https://example.com/api/knowledge/build';
        $body = '{"error":"Something went wrong"}';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response($body, 500)]);

        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口响应异常',
            \Mockery::on(function ($context) use ($body): bool {
                return isset($context['body']) && $context['body'] === $body;
            })
        );
    }

    public function test_handle_logs_error_and_throws_on_generic_exception(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection refused');

        $job->handle();

        Log::shouldHaveReceived('error')->with(
            'KnowledgeIndexBuild: 构建接口请求失败',
            \Mockery::on(function ($context) use ($url): bool {
                return isset($context['url'], $context['message'])
                    && $context['url'] === $url
                    && $context['message'] === 'Connection refused';
            })
        );

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);
    }

    public function test_handle_logs_error_and_throws_on_runtime_exception(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake(function () {
            throw new \RuntimeException('Unexpected error');
        });

        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected error');

        $job->handle();

        Log::shouldHaveReceived('error')->with(
            'KnowledgeIndexBuild: 构建接口请求失败',
            \Mockery::on(function ($context): bool {
                return $context['message'] === 'Unexpected error';
            })
        );
    }

    public function test_handle_logs_error_and_throws_on_connection_exception(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;

        $this->expectException(ConnectionException::class);

        $job->handle();

        Log::shouldHaveReceived('error')->with(
            'KnowledgeIndexBuild: 构建接口请求失败',
            \Mockery::on(function ($context): bool {
                return str_contains($context['message'] ?? '', 'Connection');
            })
        );
    }

    public function test_handle_no_broadcast_on_exception(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake(function () {
            throw new \Exception('fail');
        });

        Event::fake();

        try {
            $job = new TriggerKnowledgeIndexBuildJob;
            $job->handle();
        } catch (\Exception) {
            // expected
        }

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);
    }

    public function test_config_read_at_runtime(): void
    {
        config(['services.knowledge.build_index_url' => null]);

        $job = new TriggerKnowledgeIndexBuildJob;

        config(['services.knowledge.build_index_url' => 'https://new-url.test']);
        Http::fake(['https://new-url.test' => Http::response([], 200)]);

        Event::fake();
        $job->handle();

        Http::assertSent(fn ($req) => $req->url() === 'https://new-url.test');
    }

    public function test_job_can_be_dispatched(): void
    {
        config(['services.knowledge.build_index_url' => 'https://dispatch-test.example']);
        Http::fake(['https://dispatch-test.example' => Http::response([], 200)]);
        Event::fake();

        TriggerKnowledgeIndexBuildJob::dispatch();

        Http::assertSentCount(1);
        Event::assertDispatched(KnowledgeIndexUpdated::class);
    }

    public function test_handle_204_no_content_is_treated_as_success(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response([], 204)]);

        Event::fake();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertDispatched(KnowledgeIndexUpdated::class);
    }

    public function test_handle_400_bad_request_logs_warning(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response('Bad Request', 400)]);

        Event::fake();
        Log::spy();

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Event::assertNotDispatched(KnowledgeIndexUpdated::class);
        Log::shouldHaveReceived('warning')->with(
            'KnowledgeIndexBuild: 构建接口响应异常',
            \Mockery::on(fn ($c) => $c['status'] === 400)
        );
    }

    public function test_handle_sends_exactly_one_http_request_on_success(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response([], 200)]);

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Http::assertSentCount(1);
    }

    public function test_handle_sends_exactly_one_http_request_on_failure(): void
    {
        $url = 'https://example.com/api/knowledge/build';

        config(['services.knowledge.build_index_url' => $url]);
        Http::fake([$url => Http::response([], 500)]);

        $job = new TriggerKnowledgeIndexBuildJob;
        $job->handle();

        Http::assertSentCount(1);
    }
}
