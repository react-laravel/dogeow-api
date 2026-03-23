<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\WebPushTestCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class WebPushTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_returns_failure_when_user_not_found(): void
    {
        $command = $this->app->make(WebPushTestCommand::class);
        $input = new ArrayInput(['user_id' => '99999']);
        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, new NullOutput));

        $exitCode = $command->handle();

        $this->assertSame(1, $exitCode);
    }

    public function test_handle_returns_failure_when_user_has_no_push_subscriptions(): void
    {
        $user = User::factory()->create();

        $command = $this->app->make(WebPushTestCommand::class);
        $input = new ArrayInput(['user_id' => (string) $user->id]);
        $input->bind($command->getDefinition());
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, new NullOutput));

        $exitCode = $command->handle();

        $this->assertSame(1, $exitCode);
    }
}
