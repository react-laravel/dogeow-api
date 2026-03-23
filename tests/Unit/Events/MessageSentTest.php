<?php

namespace Tests\Unit\Events;

use App\Events\Chat\MessageSent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_can_be_constructed()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $event = new MessageSent($message);

        $this->assertInstanceOf(MessageSent::class, $event);
        $this->assertSame($message, $event->message);
    }

    public function test_broadcast_on_returns_correct_channel()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals("chat.room.{$room->id}", $channels[0]->name);
    }

    public function test_broadcast_as_returns_correct_name()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $event = new MessageSent($message);

        $this->assertEquals('message.sent', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_correct_data()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'message' => 'Hello World',
            'message_type' => 'text',
        ]);

        $event = new MessageSent($message);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals($message->id, $data['message']['id']);
        $this->assertEquals($message->room_id, $data['message']['room_id']);
        $this->assertEquals($message->user_id, $data['message']['user_id']);
        $this->assertEquals($message->message, $data['message']['message']);
        $this->assertEquals($message->message_type, $data['message']['message_type']);
        $this->assertEquals($message->created_at->toISOString(), $data['message']['created_at']);

        $this->assertArrayHasKey('user', $data['message']);
        $this->assertEquals($user->id, $data['message']['user']['id']);
        $this->assertEquals($user->name, $data['message']['user']['name']);
        $this->assertEquals($user->email, $data['message']['user']['email']);
    }

    public function test_event_implements_should_broadcast()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $event = new MessageSent($message);

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
    }

    public function test_event_uses_correct_traits()
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $event = new MessageSent($message);

        $this->assertContains(\Illuminate\Foundation\Events\Dispatchable::class, class_uses($event));
        $this->assertContains(\Illuminate\Broadcasting\InteractsWithSockets::class, class_uses($event));
        $this->assertContains(\Illuminate\Queue\SerializesModels::class, class_uses($event));
    }
}
