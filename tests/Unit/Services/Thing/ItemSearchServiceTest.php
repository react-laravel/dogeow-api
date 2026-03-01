<?php

namespace Tests\Unit\Services\Thing;

use App\Services\Thing\ItemSearchService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemSearchServiceTest extends TestCase
{
    private ItemSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemSearchService;
    }

    public function test_build_search_query_returns_query_builder(): void
    {
        $query = $this->service->buildSearchQuery('test');

        $this->assertNotNull($query);
    }

    public function test_build_search_query_with_relations(): void
    {
        $query = $this->service->buildSearchQuery('test', ['category', 'tags']);

        $this->assertNotNull($query);
    }

    public function test_get_suggestions_returns_empty_for_empty_query(): void
    {
        $result = $this->service->getSuggestions('');

        $this->assertEmpty($result);
    }

    public function test_get_suggestions_returns_results(): void
    {
        DB::table('thing_search_history')->insert([
            'user_id' => 1,
            'search_term' => 'test query',
            'results_count' => 5,
            'filters' => '{}',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->getSuggestions('test');

        $this->assertNotEmpty($result);
    }

    public function test_get_user_history_returns_empty_for_no_user_id(): void
    {
        $result = $this->service->getUserHistory(null);

        $this->assertEmpty($result);
    }

    public function test_get_user_history_returns_results(): void
    {
        DB::table('thing_search_history')->insert([
            'user_id' => 1,
            'search_term' => 'my search',
            'results_count' => 3,
            'filters' => '{}',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->getUserHistory(1);

        $this->assertNotEmpty($result);
    }

    public function test_clear_user_history_deletes_records(): void
    {
        DB::table('thing_search_history')->insert([
            'user_id' => 1,
            'search_term' => 'to delete',
            'results_count' => 1,
            'filters' => '{}',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->clearUserHistory(1);

        $this->assertEmpty(DB::table('thing_search_history')->where('user_id', 1)->get());
    }
}
