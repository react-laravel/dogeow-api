<?php

namespace Tests\Unit\Models\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_category_can_be_created()
    {
        $user = User::factory()->create();
        $category = NoteCategory::factory()->create([
            'user_id' => $user->id,
            'name' => 'Work',
            'description' => 'Work related notes',
        ]);

        $this->assertDatabaseHas('note_categories', [
            'id' => $category->id,
            'user_id' => $user->id,
            'name' => 'Work',
            'description' => 'Work related notes',
        ]);
    }

    public function test_note_category_belongs_to_user()
    {
        $user = User::factory()->create();
        $category = NoteCategory::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $category->user);
        $this->assertEquals($user->id, $category->user->id);
    }

    public function test_note_category_has_many_notes()
    {
        $category = NoteCategory::factory()->create();
        $note1 = Note::factory()->create(['note_category_id' => $category->id]);
        $note2 = Note::factory()->create(['note_category_id' => $category->id]);

        $this->assertCount(2, $category->notes);
        $this->assertTrue($category->notes->contains($note1));
        $this->assertTrue($category->notes->contains($note2));
    }

    public function test_note_category_can_be_soft_deleted()
    {
        $category = NoteCategory::factory()->create();

        $category->delete();

        $this->assertSoftDeleted('note_categories', ['id' => $category->id]);
    }

    public function test_note_category_fillable_attributes()
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'name' => 'Personal',
            'description' => 'Personal notes',
        ];

        $category = NoteCategory::create($data);

        $this->assertEquals($data['name'], $category->name);
        $this->assertEquals($data['description'], $category->description);
        $this->assertEquals($data['user_id'], $category->user_id);
    }

    public function test_note_category_has_dates_casted()
    {
        $category = NoteCategory::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $category->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $category->updated_at);
    }

    public function test_note_category_can_have_name()
    {
        $category = NoteCategory::factory()->create(['name' => 'Important']);

        $this->assertEquals('Important', $category->name);
    }

    public function test_note_category_can_have_description()
    {
        $category = NoteCategory::factory()->create(['description' => 'Test description']);

        $this->assertEquals('Test description', $category->description);
    }
}
