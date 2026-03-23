<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\CreateCharacterRequest;
use ReflectionMethod;
use Tests\TestCase;

class CreateCharacterRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new CreateCharacterRequest;

        $this->assertTrue($request->authorize());
    }

    public function test_rules_and_messages_match_expected_contract(): void
    {
        $request = new CreateCharacterRequest;

        $this->assertSame([
            'name' => 'required|string|max:32|alpha_num',
            'class' => 'required|in:warrior,mage,ranger',
            'gender' => 'nullable|in:male,female',
        ], $request->rules());

        $messages = $request->messages();
        $this->assertSame('请输入角色名称', $messages['name.required']);
        $this->assertSame('角色名称不能超过 32 个字符', $messages['name.max']);
        $this->assertSame('角色名称只能包含字母和数字', $messages['name.alpha_num']);
        $this->assertSame('请选择职业', $messages['class.required']);
        $this->assertSame('职业选择无效', $messages['class.in']);
        $this->assertSame('性别选择无效', $messages['gender.in']);
    }

    public function test_prepare_for_validation_trims_and_normalizes_case(): void
    {
        $request = CreateCharacterRequest::create('/', 'POST', [
            'name' => '  Hero01  ',
            'class' => 'MAGE',
            'gender' => 'FEMALE',
        ]);

        $method = new ReflectionMethod(CreateCharacterRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('Hero01', $request->input('name'));
        $this->assertSame('mage', $request->input('class'));
        $this->assertSame('female', $request->input('gender'));
    }
}
