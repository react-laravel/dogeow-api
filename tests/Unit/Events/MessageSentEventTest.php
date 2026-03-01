<?php

namespace Tests\Unit\Events;

use App\Events\Chat\MessageSent;
use App\Models\Chat\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSentEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_can_be_instantiated(): void
    {
        $message = new ChatMessage;
        $message->id = 1;
        $message->room_id = 1;
        $message->user_id = 1;
        $message->message = 'Test message';
        $message->message_type = 'text';

        $event = new MessageSent($message);

        $this->assertInstanceOf(MessageSent::class, $event);
        $this->assertEquals($message, $event->message);
    }

    public function test_broadcast_on_returns_correct_channel(): void
    {
        $message = new ChatMessage;
        $message->id = 1;
        $message->room_id = 5;
        $message->user_id = 1;
        $message->message = 'Test';

        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('chat.room.5', $channels[0]->name);
    }

    public function test_broadcast_as_returns_message_sent(): void
    {
        $message = new ChatMessage;
        $message->id = 1;
        $message->room_id = 1;
        $message->user_id = 1;
        $message->message = 'Test';

        $event = new MessageSent($message);

        $this->assertEquals('message.sent', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_message_data(): void
    {
        $user = User::factory()->create(['name' => 'TestUser', 'email' => 'test@test.com']);

        $message = new ChatMessage;
        $message->id = 1;
        $message->room_id = 1;
        $message->user_id = $user->id;
        $message->message = 'Hello World';
        $message->message_type = 'text';
        $message->created_at = now();
        $message->setRelation('user', $user);

        $event = new MessageSent($message);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(1, $data['message']['id']);
        $this->assertEquals(1, $data['message']['room_id']);
        $this->assertEquals(1, $data['message']['user_id']);
        $this->assertEquals('Hello World', $data['message']['message']);
        $this->assertEquals('text', $data['message']['message_type']);
        $this->assertArrayHasKey('user', $data['message']);
        $this->assertEquals('TestUser', $data['message']['user']['name']);
    }
}
