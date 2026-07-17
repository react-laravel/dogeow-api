<?php

namespace Tests\Feature\Controllers;

use App\Models\KnowledgeGraph;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeGraphControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_knowledge_graphs(): void
    {
        $this->getJson('/api/knowledge-graphs')->assertStatus(401);
    }

    public function test_user_can_list_only_own_graphs(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = KnowledgeGraph::query()->create([
            'user_id' => $user->id,
            'name' => 'My Graph',
            'description' => 'mine',
            'data' => ['nodes' => []],
        ]);
        KnowledgeGraph::query()->create([
            'user_id' => $other->id,
            'name' => 'Other Graph',
            'description' => 'theirs',
            'data' => ['nodes' => []],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/knowledge-graphs');

        $response->assertOk();
        $graphs = $response->json();
        $this->assertCount(1, $graphs);
        $this->assertSame($own->id, $graphs[0]['id']);
        $this->assertSame('My Graph', $graphs[0]['name']);
    }

    public function test_user_can_create_graph(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/knowledge-graphs', [
            'name' => 'Architecture',
            'description' => 'services',
            'data' => ['nodes' => [['id' => 'api']], 'edges' => []],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', '图谱创建成功')
            ->assertJsonPath('graph.name', 'Architecture')
            ->assertJsonPath('graph.user_id', $user->id);

        $this->assertDatabaseHas('knowledge_graphs', [
            'user_id' => $user->id,
            'name' => 'Architecture',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/knowledge-graphs', [
            'description' => 'missing name',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_show_update_and_delete_own_graph(): void
    {
        $user = User::factory()->create();
        $graph = KnowledgeGraph::query()->create([
            'user_id' => $user->id,
            'name' => 'Draft',
            'description' => null,
            'data' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/knowledge-graphs/{$graph->id}")
            ->assertOk()
            ->assertJsonPath('id', $graph->id)
            ->assertJsonPath('name', 'Draft');

        $this->putJson("/api/knowledge-graphs/{$graph->id}", [
            'name' => 'Published',
            'description' => 'ready',
            'data' => ['nodes' => []],
        ])->assertOk()
            ->assertJsonPath('message', '图谱更新成功')
            ->assertJsonPath('graph.name', 'Published');

        $this->deleteJson("/api/knowledge-graphs/{$graph->id}")
            ->assertOk()
            ->assertJsonPath('message', '图谱删除成功');

        $this->assertSoftDeleted('knowledge_graphs', ['id' => $graph->id]);
    }

    public function test_user_cannot_access_another_users_graph(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $graph = KnowledgeGraph::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
            'description' => null,
            'data' => ['secret' => true],
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/knowledge-graphs/{$graph->id}")
            ->assertStatus(403);

        $this->putJson("/api/knowledge-graphs/{$graph->id}", [
            'name' => 'Hijacked',
        ])->assertStatus(403);

        $this->deleteJson("/api/knowledge-graphs/{$graph->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('knowledge_graphs', [
            'id' => $graph->id,
            'name' => 'Private',
            'deleted_at' => null,
        ]);
    }
}
