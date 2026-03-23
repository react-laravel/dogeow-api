<?php

namespace Tests\Unit\Services\Thing;

use App\Services\Thing\ItemSearchService;
use Illuminate\Http\Request;
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

    public function test_record_search_history_inserts_record(): void
    {
        $request = Request::create('/search', 'GET', ['q' => 'test', 'limit' => 10]);
        $request->setMethod('GET');

        $this->service->recordSearchHistory('test query', 5, $request);

        $this->assertDatabaseHas('thing_search_history', [
            'search_term' => 'test query',
            'results_count' => 5,
        ]);
    }

    public function test_record_search_history_handles_exception_gracefully(): void
    {
        // This test verifies the method doesn't throw even if there's an error
        $request = Request::create('/search', 'GET');
        $request->setMethod('GET');

        // Should not throw
        $this->service->recordSearchHistory('test', 0, $request);
        $this->assertTrue(true);
    }

    public function test_record_search_history_logs_error_on_database_failure(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once();

        // Mock DB to throw exception
        DB::shouldReceive('table')->andThrow(new \Exception('Database error'));

        $request = Request::create('/search', 'GET', ['q' => 'test']);
        $request->setMethod('GET');

        // Should not throw even though DB fails
        $this->service->recordSearchHistory('test', 5, $request);
    }
}
