<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\LearnSkillRequest;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    /**
     * 获取技能列表（单一列表，每项含 is_learned 及已学时的 character_skill 信息）
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $definitions = GameSkillDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($character) {
                $query->where('class_restriction', 'all')
                    ->orWhere('class_restriction', $character->class);
            })
            ->orderBy('id')
            ->get();

        $learnedBySkillId = $character->skills()->get()->keyBy('skill_id');

        $skills = $definitions->map(function (GameSkillDefinition $def) use ($learnedBySkillId) {
            $row = $def->toArray();
            /** @var \App\Models\Game\GameCharacterSkill|null $characterSkill */
            $characterSkill = $learnedBySkillId->get($def->id);
            $row['is_learned'] = $characterSkill !== null;
            if ($characterSkill !== null) {
                $row['character_skill_id'] = $characterSkill->id;
                $row['level'] = $characterSkill->level ?? 1;
                $row['slot_index'] = $characterSkill->slot_index;
            }

            return $row;
        });

        return $this->success([
            'skills' => $skills->values()->all(),
            'skill_points' => $character->skill_points,
        ]);
    }

    /**
     * 学习技能
     */
    public function learn(LearnSkillRequest $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $skill = GameSkillDefinition::findOrFail($request->input('skill_id'));

        // 检查技能点是否足够
        $cost = $skill->skill_points_cost ?? 1;
        if ($character->skill_points < $cost) {
            return $this->error("技能点不足，学习该技能需要 {$cost} 点");
        }

        // 检查职业限制
        if (! $skill->canLearnByClass($character->class)) {
            return $this->error('该技能不适合你的职业');
        }

        // 检查是否已学习
        $existingSkill = $character->skills()->where('skill_id', $skill->id)->first();
        if ($existingSkill) {
            return $this->error('已经学习了该技能');
        }

        // 检查前置技能（优先使用 effect_key 判断，其次使用 skill_id）
        $prereqError = null;
        if ($skill->prerequisite_effect_key) {
            // 根据 effect_key 检查是否已学习前置技能
            $prereqSkill = GameSkillDefinition::where('effect_key', $skill->prerequisite_effect_key)
                ->where(function ($query) use ($character) {
                    $query->where('class_restriction', 'all')
                        ->orWhere('class_restriction', $character->class);
                })
                ->first();
            if ($prereqSkill) {
                $hasPrereq = $character->skills()->where('skill_id', $prereqSkill->id)->exists();
                if (! $hasPrereq) {
                    $prereqError = '需要先学习前置技能: ' . $prereqSkill->name;
                }
            }
        } elseif ($skill->prerequisite_skill_id) {
            // 回退到旧的 skill_id 判断
            $hasPrereq = $character->skills()->where('skill_id', $skill->prerequisite_skill_id)->exists();
            if (! $hasPrereq) {
                $prereqSkill = GameSkillDefinition::find($skill->prerequisite_skill_id);
                $prereqError = '需要先学习前置技能: ' . ($prereqSkill !== null ? $prereqSkill->name : '未知');
            }
        }

        if ($prereqError) {
            return $this->error($prereqError);
        }

        // 学习技能
        $characterSkill = $character->skills()->create([
            'skill_id' => $skill->id,
        ]);
        $characterSkill->load('skill');

        $character->skill_points -= $cost;
        $character->save();

        return $this->success([
            'character' => $character,
            'skill_points' => $character->skill_points,
            'character_skill' => $characterSkill,
        ], '技能学习成功');
    }
}
