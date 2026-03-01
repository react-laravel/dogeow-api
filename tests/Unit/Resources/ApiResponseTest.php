<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_returns_json_response(): void
    {
        $response = ApiResponse::success(['name' => 'test']);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Success', $data['message']);
        $this->assertEquals(['name' => 'test'], $data['data']);
    }

    public function test_success_with_custom_message(): void
    {
        $response = ApiResponse::success(null, 'Custom message');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Custom message', $data['message']);
    }

    public function test_success_with_custom_status(): void
    {
        $response = ApiResponse::success(null, 'Created', 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_error_returns_json_response(): void
    {
        $response = ApiResponse::error('Error occurred');

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Error occurred', $data['message']);
    }

    public function test_error_with_errors(): void
    {
        $response = ApiResponse::error('Validation failed', ['name' => 'Required']);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['name' => 'Required'], $data['errors']);
    }

    public function test_error_with_custom_status(): void
    {
        $response = ApiResponse::error('Not found', null, 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_created_returns_201_status(): void
    {
        $response = ApiResponse::created(['id' => 1]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Resource created successfully', $data['message']);
    }

    public function test_updated_returns_success_message(): void
    {
        $response = ApiResponse::updated(['id' => 1]);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Resource updated successfully', $data['message']);
    }

    public function test_deleted_returns_success_message(): void
    {
        $response = ApiResponse::deleted();

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Resource deleted successfully', $data['message']);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function test_not_found_returns_404(): void
    {
        $response = ApiResponse::notFound();

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_unauthorized_returns_401(): void
    {
        $response = ApiResponse::unauthorized();

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_forbidden_returns_403(): void
    {
        $response = ApiResponse::forbidden();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_validation_error_returns_422(): void
    {
        $errors = ['email' => ['The email is required']];
        $response = ApiResponse::validationError($errors);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($errors, $data['errors']);
    }

    public function test_server_error_returns_500(): void
    {
        $response = ApiResponse::serverError();

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_rate_limited_returns_429(): void
    {
        $response = ApiResponse::rateLimited();

        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_rate_limited_with_meta(): void
    {
        $response = ApiResponse::rateLimited('Too many requests', ['retry_after' => 60]);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(60, $data['meta']['retry_after']);
    }

    public function test_paginated_returns_pagination_data(): void
    {
        $paginator = new LengthAwarePaginator(
            ['item1', 'item2'],
            20,
            10,
            1
        );

        $response = ApiResponse::paginated($paginator);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(['item1', 'item2'], $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(2, $data['pagination']['last_page']);
        $this->assertEquals(10, $data['pagination']['per_page']);
        $this->assertEquals(20, $data['pagination']['total']);
    }

    public function test_collection_with_meta(): void
    {
        $response = ApiResponse::collection(['a', 'b'], 'Success', ['total' => 2]);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['a', 'b'], $data['data']);
        $this->assertEquals(['total' => 2], $data['meta']);
    }
}
