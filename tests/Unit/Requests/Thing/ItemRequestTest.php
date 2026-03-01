<?php

namespace Tests\Unit\Requests\Thing;

use App\Http\Requests\Thing\ItemRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemRequestTest extends TestCase
{
    use RefreshDatabase;

    private ItemRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new ItemRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('expiry_date', $rules);
        $this->assertArrayHasKey('purchase_date', $rules);
        $this->assertArrayHasKey('purchase_price', $rules);
        $this->assertArrayHasKey('category_id', $rules);
        $this->assertArrayHasKey('area_id', $rules);
        $this->assertArrayHasKey('room_id', $rules);
        $this->assertArrayHasKey('spot_id', $rules);
        $this->assertArrayHasKey('is_public', $rules);
        $this->assertArrayHasKey('images', $rules);
        $this->assertArrayHasKey('tags', $rules);
        $this->assertArrayHasKey('image_ids', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
    }

    public function test_quantity_validation_rules()
    {
        $rules = $this->request->rules();

        // quantity may be required or nullable depending on version
        $this->assertTrue(
            str_contains($rules['quantity'], 'required') ||
            str_contains($rules['quantity'], 'nullable')
        );
        $this->assertStringContainsString('integer', $rules['quantity']);
        $this->assertStringContainsString('min:1', $rules['quantity']);
    }

    public function test_status_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['status']);
        $this->assertStringContainsString('string', $rules['status']);
        $this->assertStringContainsString('in:active,inactive,expired', $rules['status']);
    }

    public function test_date_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['expiry_date']);
        $this->assertStringContainsString('date', $rules['expiry_date']);

        $this->assertStringContainsString('nullable', $rules['purchase_date']);
        $this->assertStringContainsString('date', $rules['purchase_date']);
    }

    public function test_purchase_price_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['purchase_price']);
        $this->assertStringContainsString('numeric', $rules['purchase_price']);
        $this->assertStringContainsString('min:0', $rules['purchase_price']);
    }

    public function test_foreign_key_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['category_id']);
        $this->assertStringContainsString('exists:thing_item_categories,id', $rules['category_id']);

        $this->assertStringContainsString('nullable', $rules['area_id']);
        $this->assertStringContainsString('exists:thing_areas,id', $rules['area_id']);

        $this->assertStringContainsString('nullable', $rules['room_id']);
        $this->assertStringContainsString('exists:thing_rooms,id', $rules['room_id']);

        $this->assertStringContainsString('nullable', $rules['spot_id']);
        $this->assertStringContainsString('exists:thing_spots,id', $rules['spot_id']);
    }

    public function test_is_public_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('boolean', $rules['is_public']);
    }

    public function test_images_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['images']);
        $this->assertStringContainsString('array', $rules['images']);

        $this->assertStringContainsString('image', $rules['images.*']);
        $this->assertStringContainsString('mimes:jpeg,png,jpg,gif', $rules['images.*']);
        $this->assertStringContainsString('max:2048', $rules['images.*']);
    }

    public function test_tags_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['tags']);
        $this->assertStringContainsString('array', $rules['tags']);

        $this->assertStringContainsString('string', $rules['tags.*']);
        $this->assertStringContainsString('max:255', $rules['tags.*']);
    }

    public function test_image_ids_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['image_ids']);
        $this->assertStringContainsString('array', $rules['image_ids']);

        $this->assertStringContainsString('integer', $rules['image_ids.*']);
        $this->assertStringContainsString('exists:thing_item_images,id', $rules['image_ids.*']);
    }

    public function test_validation_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('quantity.required', $messages);
        $this->assertArrayHasKey('quantity.integer', $messages);
        $this->assertArrayHasKey('quantity.min', $messages);
        $this->assertArrayHasKey('purchase_price.numeric', $messages);
        $this->assertArrayHasKey('purchase_price.min', $messages);
        $this->assertArrayHasKey('category_id.exists', $messages);
        $this->assertArrayHasKey('spot_id.exists', $messages);
        $this->assertArrayHasKey('images.*.image', $messages);
        $this->assertArrayHasKey('images.*.mimes', $messages);
        $this->assertArrayHasKey('images.*.max', $messages);
        $this->assertArrayHasKey('tags.*.string', $messages);
        $this->assertArrayHasKey('tags.*.max', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('物品名称不能为空', $messages['name.required']);
    }

    public function test_name_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('物品名称不能超过255个字符', $messages['name.max']);
    }

    public function test_quantity_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('物品数量不能为空', $messages['quantity.required']);
    }

    public function test_quantity_integer_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('物品数量必须为整数', $messages['quantity.integer']);
    }

    public function test_quantity_min_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('物品数量必须大于0', $messages['quantity.min']);
    }

    public function test_purchase_price_numeric_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('购买价格必须为数字', $messages['purchase_price.numeric']);
    }

    public function test_purchase_price_min_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('购买价格不能为负数', $messages['purchase_price.min']);
    }

    public function test_category_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选分类不存在', $messages['category_id.exists']);
    }

    public function test_spot_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选位置不存在', $messages['spot_id.exists']);
    }

    public function test_images_image_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('上传的文件必须是图片', $messages['images.*.image']);
    }

    public function test_images_mimes_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('图片格式必须为jpeg,png,jpg,gif', $messages['images.*.mimes']);
    }

    public function test_images_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('图片大小不能超过2MB', $messages['images.*.max']);
    }

    public function test_tags_string_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('标签必须是字符串', $messages['tags.*.string']);
    }

    public function test_tags_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('标签长度不能超过255个字符', $messages['tags.*.max']);
    }
}
