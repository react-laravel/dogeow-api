<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiPatchRouteRegistrationTest extends TestCase
{
    public function test_patch_routes_are_registered_for_put_only_api_endpoints(): void
    {
        $patchRoutes = collect(app('router')->getRoutes()->getRoutesByMethod()['PATCH'] ?? [])
            ->mapWithKeys(fn ($route) => [$route->uri() => $route->getActionName()]);

        $expectedRoutes = [
            'api/nav/items/{item}' => 'App\\Http\\Controllers\\Api\\Nav\\ItemController@update',
            'api/nav/categories/{category}' => 'App\\Http\\Controllers\\Api\\Nav\\CategoryController@update',
            'api/todos/{id}' => 'App\\Http\\Controllers\\Api\\Todo\\TodoListController@update',
            'api/todos/{id}/tasks/reorder' => 'App\\Http\\Controllers\\Api\\Todo\\TodoListController@reorderTasks',
            'api/cloud/files/{id}' => 'App\\Http\\Controllers\\Api\\Cloud\\FileController@update',
            'api/areas/{area}' => 'App\\Http\\Controllers\\Api\\Thing\\LocationAreaController@update',
            'api/rooms/{room}' => 'App\\Http\\Controllers\\Api\\Thing\\LocationRoomController@update',
            'api/spots/{spot}' => 'App\\Http\\Controllers\\Api\\Thing\\LocationSpotController@update',
            'api/profile' => 'App\\Http\\Controllers\\Api\\ProfileController@update',
            'api/things/items/{item}' => 'App\\Http\\Controllers\\Api\\Thing\\ItemController@update',
            'api/things/categories/{category}' => 'App\\Http\\Controllers\\Api\\Thing\\CategoryController@update',
            'api/things/tags/{tag}' => 'App\\Http\\Controllers\\Api\\Thing\\TagController@update',
            'api/word/settings' => 'App\\Http\\Controllers\\Api\\Word\\SettingController@update',
        ];

        foreach ($expectedRoutes as $uri => $action) {
            $this->assertSame(
                $action,
                $patchRoutes->get($uri),
                "PATCH 路由 [{$uri}] 未注册到预期控制器 [{$action}]"
            );
        }
    }
}
