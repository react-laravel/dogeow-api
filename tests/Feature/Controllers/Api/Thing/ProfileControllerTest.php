<?php

namespace Tests\Feature\Controllers\Api\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function clearAuth(): void
    {
        Auth::forgetGuards();
    }

    /**
     * Test the edit method returns profile data
     */
    public function test_edit_returns_profile_data()
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'message', 'data' => ['user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']],
        ]);
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ],
        ]);
    }

    /**
     * Test the update method with valid data
     */
    public function test_update_profile_with_valid_data()
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'message',
            'data' => ['user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']],
        ]);
        $response->assertJson([
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ],
        ]);

        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
        $this->assertEquals('updated@example.com', $this->user->email);
    }

    /**
     * Test the update method with email change resets verification
     */
    public function test_update_profile_with_email_change_resets_verification()
    {
        $this->user->update(['email_verified_at' => now()]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'newemail@example.com',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertNull($this->user->email_verified_at);
    }

    /**
     * Test the update method without email change keeps verification
     */
    public function test_update_profile_without_email_change_keeps_verification()
    {
        $this->user->update(['email_verified_at' => now()]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => $this->user->email, // Same email
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertNotNull($this->user->email_verified_at);
    }

    /**
     * Test the update method with invalid data
     */
    public function test_update_profile_with_invalid_data()
    {
        $updateData = [
            'name' => '',
            'email' => 'invalid-email',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    /**
     * Test the update method with duplicate email
     */
    public function test_update_profile_with_duplicate_email()
    {
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test the destroy method with valid password
     */
    public function test_destroy_account_with_valid_password()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Account deleted successfully',
        ]);

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    /**
     * Test the destroy method with invalid password
     */
    public function test_destroy_account_with_invalid_password()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    /**
     * Test the destroy method without password
     */
    public function test_destroy_account_without_password()
    {
        $response = $this->deleteJson('/api/profile', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    /**
     * Test the destroy method deletes related data
     */
    public function test_destroy_account_deletes_related_data()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        // Create related data
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Account deleted successfully',
        ]);

        // Verify user is deleted
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);

        // Verify related data is deleted
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('thing_item_images', ['id' => $image->id]);
    }

    /**
     * Test the destroy method logs out user
     */
    public function test_destroy_account_logs_out_user()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    /**
     * Test the destroy method invalidates session
     */
    public function test_destroy_account_invalidates_session()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    /**
     * Test edit method requires authentication
     */
    public function test_edit_requires_authentication()
    {
        $this->clearAuth();

        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    /**
     * Test update method requires authentication
     */
    public function test_update_requires_authentication()
    {
        $this->clearAuth();

        $response = $this->putJson('/api/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test destroy method requires authentication
     */
    public function test_destroy_requires_authentication()
    {
        $this->clearAuth();

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test update method with only name change
     */
    public function test_update_profile_with_only_name_change()
    {
        $originalEmail = $this->user->email;
        $originalVerifiedAt = $this->user->email_verified_at;

        $updateData = [
            'name' => 'New Name Only',
            'email' => $originalEmail,
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile updated successfully',
        ]);

        $this->user->refresh();
        $this->assertEquals('New Name Only', $this->user->name);
        $this->assertEquals($originalEmail, $this->user->email);
        $this->assertEquals($originalVerifiedAt, $this->user->email_verified_at);
    }

    /**
     * Test update method with only email change
     */
    public function test_update_profile_with_only_email_change()
    {
        $originalName = $this->user->name;
        $this->user->update(['email_verified_at' => now()]);

        $updateData = [
            'name' => $originalName,
            'email' => 'newemailonly@example.com',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals($originalName, $this->user->name);
        $this->assertEquals('newemailonly@example.com', $this->user->email);
        $this->assertNull($this->user->email_verified_at);
    }

    /**
     * Test destroy method with multiple items and related data
     */
    public function test_destroy_account_with_multiple_items()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        // Create multiple items with related data
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);

        $image1 = ItemImage::factory()->create(['item_id' => $item1->id]);
        $image2 = ItemImage::factory()->create(['item_id' => $item2->id]);

        $response = $this->deleteJson('/api/profile', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Account deleted successfully',
        ]);

        // Verify user is deleted
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);

        // Verify all items are deleted
        $this->assertDatabaseMissing('thing_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('thing_items', ['id' => $item2->id]);

        // Verify all images are deleted
        $this->assertDatabaseMissing('thing_item_images', ['id' => $image1->id]);
        $this->assertDatabaseMissing('thing_item_images', ['id' => $image2->id]);
    }

    /**
     * Test update method validation rules
     */
    public function test_update_profile_validation_rules()
    {
        // Test empty name
        $response = $this->putJson('/api/profile', [
            'name' => '',
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test name too long
        $response = $this->putJson('/api/profile', [
            'name' => str_repeat('a', 256),
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test invalid email format
        $response = $this->putJson('/api/profile', [
            'name' => 'Test Name',
            'email' => 'invalid-email',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        // Test email too long
        $response = $this->putJson('/api/profile', [
            'name' => 'Test Name',
            'email' => str_repeat('a', 250) . '@example.com',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
