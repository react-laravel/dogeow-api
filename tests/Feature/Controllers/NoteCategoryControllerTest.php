<?php

namespace Tests\Feature\Controllers;

use App\Models\Note\NoteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NoteCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Auth::login($this->user);
    }

    public function test_index_returns_user_categories()
    {
        // 创建用户的分类
        $userCategory = NoteCategory::factory()->create(['user_id' => $this->user->id]);
        $otherUserCategory = NoteCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/notes/categories');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userCategory->id])
            ->assertJsonMissing(['id' => $otherUserCategory->id]);
    }

    public function test_store_creates_new_category()
    {
        $data = [
            'name' => 'Test Category',
            'description' => 'Test description',
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Category',
                'description' => 'Test description',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('note_categories', [
            'name' => 'Test Category',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_category_without_description()
    {
        $data = [
            'name' => 'Test Category',
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Category',
                'description' => null,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/notes/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = [
            'name' => str_repeat('a', 51), // 超过 50 字符限制
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_description()
    {
        $data = [
            'name' => 'Test Category',
            'description' => str_repeat('a', 201), // 超过 200 字符限制
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_show_returns_category_with_notes()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/notes/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => $category->name,
                'user_id' => $this->user->id,
            ])
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'user_id',
                'created_at',
                'updated_at',
                'notes',
            ]);
    }

    public function test_show_returns_404_for_other_user_category()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/notes/categories/{$category->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_category()
    {
        $response = $this->getJson('/api/notes/categories/999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_category()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => 'Updated Category',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => 'Updated Category',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('note_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'description' => 'Updated description',
        ]);
    }

    public function test_update_partial_fields()
    {
        $category = NoteCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Category',
            'description' => 'Original description',
        ]);

        $data = [
            'name' => 'Updated Category',
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Category',
                'description' => 'Original description', // 保持原值
            ]);
    }

    public function test_update_returns_404_for_other_user_category()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $data = [
            'name' => 'Updated Category',
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(404);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => str_repeat('a', 51), // 超过 50 字符限制
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_long_description()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => 'Test Category',
            'description' => str_repeat('a', 201), // 超过 200 字符限制
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_destroy_deletes_category()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/notes/categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('note_categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_404_for_other_user_category()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/notes/categories/{$category->id}");

        $response->assertStatus(404);
    }

    public function test_index_orders_by_name_asc()
    {
        $categoryB = NoteCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'B Category',
        ]);
        $categoryA = NoteCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'A Category',
        ]);
        $categoryC = NoteCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'C Category',
        ]);

        $response = $this->getJson('/api/notes/categories');

        $response->assertStatus(200)
            ->assertJsonCount(3);

        $categories = $response->json();
        $this->assertEquals($categoryA->id, $categories[0]['id']);
        $this->assertEquals($categoryB->id, $categories[1]['id']);
        $this->assertEquals($categoryC->id, $categories[2]['id']);
    }

    public function test_show_includes_notes_relationship()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/notes/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'user_id',
                'created_at',
                'updated_at',
                'notes',
            ]);
    }

    public function test_destroy_returns_404_for_nonexistent_category()
    {
        $response = $this->deleteJson('/api/notes/categories/999');

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_nonexistent_category()
    {
        $data = [
            'name' => 'Updated Category',
        ];

        $response = $this->putJson('/api/notes/categories/999', $data);

        $response->assertStatus(404);
    }

    public function test_store_validation_fails_with_empty_name()
    {
        $data = [
            'name' => '',
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_empty_name()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => '',
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_non_string_name()
    {
        $data = [
            'name' => 123,
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_non_string_name()
    {
        $category = NoteCategory::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'name' => 123,
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_accepts_null_description()
    {
        $data = [
            'name' => 'Test Category',
            'description' => null,
        ];

        $response = $this->postJson('/api/notes/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Category',
                'description' => null,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_update_accepts_null_description()
    {
        $category = NoteCategory::factory()->create([
            'user_id' => $this->user->id,
            'description' => 'Original description',
        ]);

        $data = [
            'name' => 'Updated Category',
            'description' => null,
        ];

        $response = $this->putJson("/api/notes/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Category',
                'description' => null,
            ]);
    }
}
