<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookMarkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_and_list_book_marks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->bookmarkPayload([
            'id' => 'mark-1',
            'pairIndex' => 3,
        ]);

        $this->postJson('/api/books/hongloumeng/marks', $payload)
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('mark.id', 'mark-1')
            ->assertJsonPath('mark.bookId', 'hongloumeng')
            ->assertJsonPath('mark.pairIndex', 3);

        $this->getJson('/api/books/hongloumeng/marks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', 'mark-1');
    }

    public function test_same_position_bookmark_is_not_created_twice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/books/hongloumeng/marks', $this->bookmarkPayload([
            'id' => 'mark-1',
            'pairIndex' => 3,
            'excerpt' => '第一次',
        ]))->assertCreated();

        $this->postJson('/api/books/hongloumeng/marks', $this->bookmarkPayload([
            'id' => 'mark-2',
            'scrollTop' => 999,
            'pairIndex' => 3,
            'excerpt' => '重复位置',
        ]))
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('mark.id', 'mark-1');

        $this->assertDatabaseCount('book_marks', 1);
    }

    public function test_book_marks_are_scoped_to_authenticated_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/books/hongloumeng/marks', $this->bookmarkPayload([
            'id' => 'first-user-mark',
        ]))->assertCreated();

        Sanctum::actingAs($secondUser);
        $this->getJson('/api/books/hongloumeng/marks')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_user_can_delete_own_book_mark(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/books/hongloumeng/marks', $this->bookmarkPayload([
            'id' => 'mark-to-delete',
        ]))->assertCreated();

        $this->deleteJson('/api/books/hongloumeng/marks/mark-to-delete')
            ->assertNoContent();

        $this->assertDatabaseCount('book_marks', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bookmarkPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'mark-' . uniqid(),
            'kind' => 'position',
            'chapterId' => 1,
            'chapterTitle' => '第一回',
            'scrollTop' => 120,
            'pairIndex' => null,
            'excerpt' => '甄士隐梦幻识通灵',
            'note' => '',
            'createdAt' => 1766140000000,
        ], $overrides);
    }
}
