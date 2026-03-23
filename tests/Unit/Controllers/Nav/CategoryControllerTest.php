<?php

namespace Tests\Unit\Controllers\Nav;

use App\Http\Controllers\Api\Nav\CategoryController;
use App\Models\Nav\Category;
use App\Models\Nav\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private CategoryController $controller;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CategoryController;
        $this->user = User::factory()->create();
    }

    /**
     * Test the index method with default behavior
     */
    public function test_index_method_with_default_behavior()
    {
        $visibleCategory = Category::factory()->visible()->create();
        $hiddenCategory = Category::factory()->hidden()->create();

        Item::factory()->count(3)->create(['nav_category_id' => $visibleCategory->id]);

        $request = new Request;
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
        $this->assertEquals($visibleCategory->id, $data[0]->id);
    }

    /**
     * Test the index method with show_all parameter
     */
    public function test_index_method_with_show_all_parameter()
    {
        $visibleCategory = Category::factory()->visible()->create();
        $hiddenCategory = Category::factory()->hidden()->create();

        $request = new Request(['show_all' => '1']);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(2, $data);
    }

    /**
     * Test the index method with name filter
     */
    public function test_index_method_with_name_filter()
    {
        $category = Category::factory()->visible()->create();

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Test Item',
        ]);

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Other Item',
        ]);

        $request = new Request([
            'filter' => ['name' => 'Test'],
        ]);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
        $this->assertEquals($category->id, $data[0]->id);
    }

    /**
     * Test the all method
     */
    public function test_all_method()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Item::factory()->count(3)->create(['nav_category_id' => $category1->id]);
        Item::factory()->count(1)->create(['nav_category_id' => $category2->id]);

        $response = $this->controller->all();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(2, $data);

        // Check that items_count is included
        $category1Data = collect($data)->firstWhere('id', $category1->id);
        $this->assertEquals(3, $category1Data->items_count);

        $category2Data = collect($data)->firstWhere('id', $category2->id);
        $this->assertEquals(1, $category2Data->items_count);
    }

    /**
     * Test the show method
     */
    public function test_show_method()
    {
        $category = Category::factory()->create();
        Item::factory()->count(3)->create(['nav_category_id' => $category->id]);

        $response = $this->controller->show($category);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals($category->id, $data->id);
        $this->assertCount(3, $data->items);
    }

    /**
     * Test the destroy method with empty category
     */
    public function test_destroy_method_with_empty_category()
    {
        Auth::login($this->user);

        $category = Category::factory()->create();

        $response = $this->controller->destroy($category);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals('分类删除成功', $data->message);

        $this->assertSoftDeleted('nav_categories', ['id' => $category->id]);
    }

    /**
     * Test the destroy method with category that has items
     */
    public function test_destroy_method_with_category_has_items()
    {
        Auth::login($this->user);

        $category = Category::factory()->create();
        Item::factory()->create(['nav_category_id' => $category->id]);

        $response = $this->controller->destroy($category);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals('该分类下存在导航项，无法删除', $data->message);

        $this->assertDatabaseHas('nav_categories', ['id' => $category->id]);
    }

    /**
     * Test the destroy method with category that has multiple items
     */
    public function test_destroy_method_with_category_has_multiple_items()
    {
        Auth::login($this->user);

        $category = Category::factory()->create();
        Item::factory()->count(5)->create(['nav_category_id' => $category->id]);

        $response = $this->controller->destroy($category);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals('该分类下存在导航项，无法删除', $data->message);

        $this->assertDatabaseHas('nav_categories', ['id' => $category->id]);
    }

    /**
     * Test index method with empty result
     */
    public function test_index_method_with_empty_result()
    {
        $request = new Request;
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(0, $data);
    }

    /**
     * Test all method with empty result
     */
    public function test_all_method_with_empty_result()
    {
        $response = $this->controller->all();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(0, $data);
    }

    /**
     * Test index method with name filter but no matching items
     */
    public function test_index_method_with_name_filter_no_matches()
    {
        $category = Category::factory()->visible()->create();
        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Other Item',
        ]);

        $request = new Request([
            'filter' => ['name' => 'NonExistent'],
        ]);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(0, $data);
    }

    /**
     * Test index method with name filter and partial matches
     */
    public function test_index_method_with_name_filter_partial_matches()
    {
        $category = Category::factory()->visible()->create();

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Test Item One',
        ]);

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Test Item Two',
        ]);

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Other Item',
        ]);

        $request = new Request([
            'filter' => ['name' => 'Test'],
        ]);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
        $this->assertCount(2, $data[0]->items);
    }

    /**
     * Test index method with case insensitive filter
     */
    public function test_index_method_with_case_insensitive_filter()
    {
        $category = Category::factory()->visible()->create();

        Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Test Item',
        ]);

        $request = new Request([
            'filter' => ['name' => 'test'],
        ]);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
    }

    /**
     * Test index method with show_all and items count
     */
    public function test_index_method_with_show_all_and_items_count()
    {
        $category = Category::factory()->create();
        Item::factory()->count(3)->create(['nav_category_id' => $category->id]);

        $request = new Request(['show_all' => '1']);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
        $this->assertEquals(3, $data[0]->items_count);
    }

    /**
     * Test index method with only hidden categories
     */
    public function test_index_method_with_only_hidden_categories()
    {
        Category::factory()->hidden()->create();
        Category::factory()->hidden()->create();

        $request = new Request;
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(0, $data);
    }

    /**
     * Test index method with mixed visible and hidden categories
     */
    public function test_index_method_with_mixed_visible_and_hidden_categories()
    {
        $visibleCategory = Category::factory()->visible()->create();
        $hiddenCategory = Category::factory()->hidden()->create();

        Item::factory()->create(['nav_category_id' => $visibleCategory->id]);
        Item::factory()->create(['nav_category_id' => $hiddenCategory->id]);

        $request = new Request;
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(1, $data);
        $this->assertEquals($visibleCategory->id, $data[0]->id);
    }

    /**
     * Test index method with sorting
     */
    public function test_index_method_with_sorting()
    {
        $category3 = Category::factory()->create(['sort_order' => 3]);
        $category1 = Category::factory()->create(['sort_order' => 1]);
        $category2 = Category::factory()->create(['sort_order' => 2]);

        $request = new Request(['show_all' => '1']);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertCount(3, $data);
        $this->assertEquals($category1->id, $data[0]->id);
        $this->assertEquals($category2->id, $data[1]->id);
        $this->assertEquals($category3->id, $data[2]->id);
    }

    /**
     * Test show method with category that has no items
     */
    public function test_show_method_with_category_no_items()
    {
        $category = Category::factory()->create();

        $response = $this->controller->show($category);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals($category->id, $data->id);
        $this->assertCount(0, $data->items);
    }

    /**
     * Test show method with category that has multiple items
     */
    public function test_show_method_with_category_multiple_items()
    {
        $category = Category::factory()->create();
        Item::factory()->count(5)->create(['nav_category_id' => $category->id]);

        $response = $this->controller->show($category);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals($category->id, $data->id);
        $this->assertCount(5, $data->items);
    }
}
