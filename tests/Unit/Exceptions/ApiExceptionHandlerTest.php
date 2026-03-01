<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiExceptionHandler;
use App\Exceptions\GameException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ApiExceptionHandlerTest extends TestCase
{
    private function createRequest(bool $isApi = true, bool $expectsJson = false): Request
    {
        $request = Request::create(
            $isApi ? '/api/test' : '/test',
            'GET'
        );

        if ($expectsJson) {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }

    public function test_handle_returns_null_for_non_api_request(): void
    {
        $request = $this->createRequest(false);
        $exception = new \Exception('Test exception');

        $result = ApiExceptionHandler::handle($exception, $request);

        $this->assertNull($result);
    }

    public function test_handle_processes_exception_when_request_expects_json(): void
    {
        $request = $this->createRequest(false, true);
        $exception = new NotFoundHttpException;

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertNotNull($response);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_handle_validation_exception(): void
    {
        $request = $this->createRequest();
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['name' => ''],
            ['name' => 'required']
        );
        $validationException = new ValidationException($validator);

        $response = ApiExceptionHandler::handle($validationException, $request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Validation failed', $data['message']);
    }

    public function test_handle_game_exception(): void
    {
        $request = $this->createRequest();
        $gameException = new GameException(400, 'Test game error');

        $response = ApiExceptionHandler::handle($gameException, $request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_handle_model_not_found_exception(): void
    {
        $request = $this->createRequest();
        $modelException = new ModelNotFoundException('Model not found');

        $response = ApiExceptionHandler::handle($modelException, $request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_handle_not_found_http_exception(): void
    {
        $request = $this->createRequest();
        $exception = new NotFoundHttpException;

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_handle_authentication_exception(): void
    {
        $request = $this->createRequest();
        $exception = new AuthenticationException;

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_handle_http_exception(): void
    {
        $request = $this->createRequest();
        $exception = new HttpException(403, 'Forbidden');

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Forbidden', $data['message']);
    }

    public function test_handle_generic_exception_in_production(): void
    {
        $request = $this->createRequest();
        $exception = new \Exception('Detailed error message');

        $this->app['env'] = 'production';

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Internal server error', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
    }

    public function test_handle_generic_exception_in_local(): void
    {
        $request = $this->createRequest();
        $exception = new \Exception('Detailed error message');

        $this->app['env'] = 'local';

        $response = ApiExceptionHandler::handle($exception, $request);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('debug', $data);
    }
}
