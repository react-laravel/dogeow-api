<?php

namespace Tests\Unit\Services\File;

use App\Services\File\RmbgItemImageLinkerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RmbgItemImageLinkerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_link_and_lookup_upload_path_and_item_image(): void
    {
        $service = app(RmbgItemImageLinkerService::class);
        $uploadPath = 'uploads/1/abc.jpg';

        $service->link($uploadPath, 42);

        $this->assertSame(42, $service->getItemImageIdForUploadPath($uploadPath));
        $this->assertSame($uploadPath, $service->getUploadPathForItemImage(42));
    }

    public function test_unlink_removes_both_directions(): void
    {
        $service = app(RmbgItemImageLinkerService::class);
        $uploadPath = 'uploads/1/abc.jpg';

        $service->link($uploadPath, 42);
        $service->unlink($uploadPath);

        $this->assertNull($service->getItemImageIdForUploadPath($uploadPath));
        $this->assertNull($service->getUploadPathForItemImage(42));
    }

    public function test_lookup_returns_null_when_not_linked(): void
    {
        Cache::flush();

        $service = app(RmbgItemImageLinkerService::class);

        $this->assertNull($service->getItemImageIdForUploadPath('uploads/1/missing.jpg'));
        $this->assertNull($service->getUploadPathForItemImage(999));
    }
}
