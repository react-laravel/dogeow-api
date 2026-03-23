<?php

namespace Tests\Unit\Models\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_tag_can_be_created()
    {
        $user = User::factory()->create();
        $tag = NoteTag::factory()->create([
            'user_id' => $user->id,
            'name' => 'Important',
            'color' => '#FF0000',
        ]);

        $this->assertDatabaseHas('note_tags', [
            'id' => $tag->id,
            'user_id' => $user->id,
            'name' => 'Important',
            'color' => '#FF0000',
        ]);
    }

    public function test_note_tag_belongs_to_user()
    {
        $user = User::factory()->create();
        $tag = NoteTag::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $tag->user);
        $this->assertEquals($user->id, $tag->user->id);
    }

    public function test_note_tag_can_have_notes()
    {
        $tag = NoteTag::factory()->create();
        $note1 = Note::factory()->create();
        $note2 = Note::factory()->create();

        $tag->notes()->attach([$note1->id, $note2->id]);

        $this->assertCount(2, $tag->notes);
        $this->assertTrue($tag->notes->contains($note1));
        $this->assertTrue($tag->notes->contains($note2));
    }

    public function test_note_tag_can_be_soft_deleted()
    {
        $tag = NoteTag::factory()->create();

        $tag->delete();

        $this->assertSoftDeleted('note_tags', ['id' => $tag->id]);
    }

    public function test_note_tag_fillable_attributes()
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'name' => 'Urgent',
            'color' => '#00FF00',
        ];

        $tag = NoteTag::create($data);

        $this->assertEquals($data['name'], $tag->name);
        $this->assertEquals($data['color'], $tag->color);
        $this->assertEquals($data['user_id'], $tag->user_id);
    }

    public function test_note_tag_has_dates_casted()
    {
        $tag = NoteTag::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $tag->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $tag->updated_at);
    }

    public function test_note_tag_can_have_name()
    {
        $tag = NoteTag::factory()->create(['name' => 'Critical']);

        $this->assertEquals('Critical', $tag->name);
    }

    public function test_note_tag_can_have_color()
    {
        $tag = NoteTag::factory()->create(['color' => '#0000FF']);

        $this->assertEquals('#0000FF', $tag->color);
    }
}
