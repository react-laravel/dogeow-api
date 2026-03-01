<?php

namespace Tests\Unit\Models\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_link_can_be_created(): void
    {
        $user = User::factory()->create();
        $source = Note::factory()->create(['user_id' => $user->id]);
        $target = Note::factory()->create(['user_id' => $user->id]);

        $link = NoteLink::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $this->assertDatabaseHas('note_links', [
            'id' => $link->id,
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);
    }

    public function test_note_link_belongs_to_source_note(): void
    {
        $user = User::factory()->create();
        $source = Note::factory()->create(['user_id' => $user->id]);
        $target = Note::factory()->create(['user_id' => $user->id]);

        $link = NoteLink::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $this->assertInstanceOf(Note::class, $link->sourceNote);
        $this->assertSame($source->id, $link->sourceNote->id);
    }

    public function test_note_link_belongs_to_target_note(): void
    {
        $user = User::factory()->create();
        $source = Note::factory()->create(['user_id' => $user->id]);
        $target = Note::factory()->create(['user_id' => $user->id]);

        $link = NoteLink::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => 'reference',
        ]);

        $this->assertInstanceOf(Note::class, $link->targetNote);
        $this->assertSame($target->id, $link->targetNote->id);
    }

    public function test_note_link_has_fillable_attributes(): void
    {
        $link = new NoteLink;

        $this->assertContains('source_id', $link->getFillable());
        $this->assertContains('target_id', $link->getFillable());
        $this->assertContains('type', $link->getFillable());
    }
}
