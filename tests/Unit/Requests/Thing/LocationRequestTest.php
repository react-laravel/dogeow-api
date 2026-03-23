<?php

namespace Tests\Unit\Requests\Thing;

use App\Http\Requests\Thing\LocationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationRequestTest extends TestCase
{
    use RefreshDatabase;

    private LocationRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LocationRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
    }

    public function test_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('area_id.required', $messages);
        $this->assertArrayHasKey('area_id.exists', $messages);
        $this->assertArrayHasKey('room_id.required', $messages);
        $this->assertArrayHasKey('room_id.exists', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('名称不能为空', $messages['name.required']);
    }

    public function test_name_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('名称不能超过 255 个字符', $messages['name.max']);
    }

    public function test_area_id_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('区域 ID 不能为空', $messages['area_id.required']);
    }

    public function test_area_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选区域不存在', $messages['area_id.exists']);
    }

    public function test_room_id_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('房间 ID 不能为空', $messages['room_id.required']);
    }

    public function test_room_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选房间不存在', $messages['room_id.exists']);
    }

    public function test_rules_for_rooms_path_includes_area_id()
    {
        // Mock request with rooms path
        $request = LocationRequest::create('/api/rooms', 'POST', ['name' => 'Test Room']);
        $rules = $request->rules();

        $this->assertArrayHasKey('area_id', $rules);
        $this->assertStringContainsString('required', $rules['area_id']);
    }

    public function test_rules_for_spots_path_includes_room_id()
    {
        // Mock request with spots path
        $request = LocationRequest::create('/api/spots', 'POST', ['name' => 'Test Spot']);
        $rules = $request->rules();

        $this->assertArrayHasKey('room_id', $rules);
        $this->assertStringContainsString('required', $rules['room_id']);
    }

    public function test_rules_for_update_uses_sometimes()
    {
        // Mock PUT request
        $request = LocationRequest::create('/api/rooms/1', 'PUT', ['name' => 'Updated Room']);
        $rules = $request->rules();

        $this->assertStringContainsString('sometimes', $rules['name']);
    }

    public function test_rules_for_areas_path_does_not_include_area_id()
    {
        // Mock request with areas path
        $request = LocationRequest::create('/api/areas', 'POST', ['name' => 'Test Area']);
        $rules = $request->rules();

        $this->assertArrayNotHasKey('area_id', $rules);
        $this->assertArrayNotHasKey('room_id', $rules);
    }

    public function test_rules_for_spot_update_use_sometimes_on_room_id()
    {
        $request = LocationRequest::create('/api/spots/1', 'PATCH', ['name' => 'Updated Spot']);
        $rules = $request->rules();

        $this->assertSame('sometimes|required|string|max:255', $rules['name']);
        $this->assertSame('sometimes|required|exists:thing_rooms,id', $rules['room_id']);
    }

    public function test_messages_returns_expected_translations()
    {
        $messages = $this->request->messages();

        $this->assertSame('名称不能为空', $messages['name.required']);
        $this->assertSame('所选房间不存在', $messages['room_id.exists']);
    }
}
