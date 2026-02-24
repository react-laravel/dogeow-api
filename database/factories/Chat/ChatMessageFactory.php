<?php

namespace Database\Factories\Chat;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => \App\Models\Chat\ChatRoom::factory(),
            'user_id' => \App\Models\User::factory(),
            'message' => $this->faker->sentence(),
            'message_type' => \App\Models\Chat\ChatMessage::TYPE_TEXT,
        ];
    }

    /**
     * Indicate that the message is a text message.
     */
    public function textMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => \App\Models\Chat\ChatMessage::TYPE_TEXT,
            'message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the message is a system message.
     */
    public function systemMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => \App\Models\Chat\ChatMessage::TYPE_SYSTEM,
            'message' => $this->faker->randomElement([
                'User joined the room',
                'User left the room',
                'Room was created',
                'Room settings updated',
            ]),
        ]);
    }
}
