<?php

namespace Database\Factories\Cloud;

use App\Models\Cloud\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cloud\File>
 */
class FileFactory extends Factory
{
    /** @var class-string<\App\Models\Cloud\File> */
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extensions = ['jpg', 'png', 'pdf', 'doc', 'txt', 'zip'];
        $extension = $this->faker->randomElement($extensions);
        $name = $this->faker->word() . '.' . $extension;

        return [
            'name' => $name,
            'original_name' => $this->faker->word() . '.' . $extension,
            'path' => '/path/to/' . $name,
            'mime_type' => $this->faker->mimeType(),
            'extension' => $extension,
            'size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'parent_id' => null,
            'user_id' => User::factory(),
            'is_folder' => false,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the file is a folder.
     */
    public function folder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_folder' => true,
            'extension' => null,
            'size' => 0,
            'mime_type' => null,
        ]);
    }

    /**
     * Indicate that the file is an image.
     */
    public function image(): static
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];

        return $this->state(fn (array $attributes) => [
            'extension' => $this->faker->randomElement($imageExtensions),
            'mime_type' => 'image/' . $this->faker->randomElement(['jpeg', 'png', 'gif']),
        ]);
    }

    /**
     * Indicate that the file is a document.
     */
    public function document(): static
    {
        $documentExtensions = ['doc', 'docx', 'txt', 'rtf', 'md', 'pdf'];

        return $this->state(fn (array $attributes) => [
            'extension' => $this->faker->randomElement($documentExtensions),
            'mime_type' => 'application/' . $this->faker->randomElement(['pdf', 'msword', 'plain']),
        ]);
    }
}
