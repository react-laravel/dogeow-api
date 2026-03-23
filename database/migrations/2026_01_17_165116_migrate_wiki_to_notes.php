<?php

use App\Models\Note\Note;
use App\Models\Note\NoteLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查是否有 wiki 数据需要迁移
        if (! Schema::hasTable('wiki_nodes') || ! Schema::hasTable('wiki_links')) {
            return;
        }

        $wikiNodes = DB::table('wiki_nodes')->get();
        $wikiLinks = DB::table('wiki_links')->get();

        if ($wikiNodes->isEmpty()) {
            return;
        }

        // ID 映射：wiki_nodes.id -> notes.id
        $idMap = [];

        // 迁移 wiki_nodes 到 notes
        foreach ($wikiNodes as $wikiNode) {
            $note = Note::create([
                'user_id' => null, // wiki 节点没有用户关联
                'title' => $wikiNode->title,
                'slug' => $wikiNode->slug,
                'summary' => $wikiNode->summary,
                'content' => $wikiNode->content,
                'content_markdown' => $wikiNode->content_markdown,
                'is_wiki' => true,
                'is_draft' => false,
                'created_at' => $wikiNode->created_at,
                'updated_at' => $wikiNode->updated_at,
            ]);

            $idMap[$wikiNode->id] = $note->id;
        }

        // 迁移 wiki_links 到 note_links
        foreach ($wikiLinks as $wikiLink) {
            $sourceId = $idMap[$wikiLink->source_id] ?? null;
            $targetId = $idMap[$wikiLink->target_id] ?? null;

            if ($sourceId && $targetId) {
                // 检查是否已存在相同的链接
                $exists = NoteLink::where('source_id', $sourceId)
                    ->where('target_id', $targetId)
                    ->exists();

                if (! $exists) {
                    NoteLink::create([
                        'source_id' => $sourceId,
                        'target_id' => $targetId,
                        'type' => $wikiLink->type,
                        'created_at' => $wikiLink->created_at,
                        'updated_at' => $wikiLink->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 删除迁移的 wiki 节点(is_wiki = true 且 user_id 为 null)
        Note::where('is_wiki', true)
            ->whereNull('user_id')
            ->delete();
    }
};
