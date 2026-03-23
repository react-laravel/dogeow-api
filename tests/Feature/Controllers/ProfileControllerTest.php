<?php

namespace Tests\Feature\Controllers;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_show_profile_edit_page()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'message', 'data' => ['user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']],
        ]);
    }

    #[Test]
    public function it_can_update_user_profile()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updateData = [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ];

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Profile updated successfully',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    #[Test]
    public function it_validates_profile_update_data()
    {
        $user = User::factory()->create();

        $invalidData = [
            'name' => '', // Empty name
            'email' => 'invalid-email', // Invalid email
        ];

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    #[Test]
    public function it_handles_email_change_without_verification()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $updateData = [
            'name' => $user->name,
            'email' => 'new@example.com',
        ];

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('new@example.com', $user->email);
    }

    #[Test]
    public function it_keeps_email_verification_when_email_not_changed()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $updateData = [
            'name' => 'New Name',
            'email' => 'test@example.com', // Same email
        ];

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('New Name', $user->name);
    }

    #[Test]
    public function it_can_delete_user_account()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Create some related data
        $item = Item::factory()->create(['user_id' => $user->id]);
        ItemImage::factory()->create(['item_id' => $item->id]);
        ItemCategory::factory()->create();
        Spot::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Account deleted successfully',
        ]);

        // Check that user is deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Check that related data is cleaned up
        $this->assertDatabaseMissing('thing_items', ['user_id' => $user->id]);
    }

    #[Test]
    public function it_validates_password_for_account_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        // Check that user is not deleted
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_requires_password_for_account_deletion()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        // Check that user is not deleted
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_cleans_up_user_related_data_on_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Create items with related data
        $item1 = Item::factory()->create(['user_id' => $user->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id]);

        // Create related data for items
        ItemImage::factory()->count(2)->create(['item_id' => $item1->id]);
        ItemImage::factory()->count(1)->create(['item_id' => $item2->id]);

        $category = ItemCategory::factory()->create();
        $spot = Spot::factory()->create();

        // Attach related data to items
        $item1->category()->associate($category);
        $item1->spot()->associate($spot);
        $item1->save();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Check that all related data is cleaned up
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('thing_items', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('thing_item_images', ['item_id' => $item1->id]);
        $this->assertDatabaseMissing('thing_item_images', ['item_id' => $item2->id]);
    }

    #[Test]
    public function it_logs_out_user_after_account_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_invalidates_session_after_account_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_handles_profile_update_with_partial_data()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updateData = [
            'name' => 'New Name',
            // Email not provided
        ];

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        $user->refresh();
        $this->assertEquals('Old Name', $user->name);
        $this->assertEquals('old@example.com', $user->email);
    }

    #[Test]
    public function it_prevents_duplicate_email_on_profile_update()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $updateData = [
            'name' => $user1->name,
            'email' => 'user2@example.com', // Try to use user2's email
        ];

        Sanctum::actingAs($user1);

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }
}
