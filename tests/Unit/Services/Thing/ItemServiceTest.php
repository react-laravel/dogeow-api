<?php

namespace Tests\Unit\Services\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Tag;
use App\Services\File\ImageUploadService;
use App\Services\Thing\ItemService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    private ImageUploadService $imageUploadService;

    private ItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->service = new ItemService($this->imageUploadService);
    }

    #[Test]
    public function it_processes_uploaded_images_and_existing_image_paths_when_creating_an_item(): void
    {
        $item = Item::factory()->create();
        $request = Request::create('/', 'POST', [
            'image_paths' => ['stored/a.jpg', 'stored/b.jpg'],
        ], [], [
            'images' => [
                UploadedFile::fake()->image('photo-1.jpg'),
                UploadedFile::fake()->image('photo-2.jpg'),
            ],
        ]);

        $this->imageUploadService->shouldReceive('processUploadedImages')
            ->once()
            ->with(Mockery::on(fn ($files) => is_array($files) && count($files) === 2), $item);
        $this->imageUploadService->shouldReceive('processImagePaths')
            ->once()
            ->with(['stored/a.jpg', 'stored/b.jpg'], $item);

        $this->service->processItemImages($request, $item);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_early_when_no_image_updates_are_present(): void
    {
        $request = Request::create('/', 'POST');
        $item = Item::factory()->create();

        $this->service->processItemImageUpdates($request, $item);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_processes_all_image_update_operations_for_an_item(): void
    {
        $item = Item::factory()->create();
        $firstImage = ItemImage::factory()->create(['item_id' => $item->id]);
        $secondImage = ItemImage::factory()->create(['item_id' => $item->id]);
        $thirdImage = ItemImage::factory()->create(['item_id' => $item->id]);

        $request = Request::create('/', 'POST', [
            'image_ids' => [$secondImage->id, $thirdImage->id],
            'image_paths' => ['stored/c.jpg'],
            'image_order' => [$thirdImage->id, $secondImage->id],
            'primary_image_id' => $thirdImage->id,
            'delete_images' => [9, 10],
        ]);

        $this->imageUploadService->shouldReceive('deleteImagesByIds')
            ->once()
            ->with(Mockery::on(fn ($ids) => array_values($ids) === [$firstImage->id]), $item);
        $this->imageUploadService->shouldReceive('processImagePaths')
            ->once()
            ->with(['stored/c.jpg'], $item);
        $this->imageUploadService->shouldReceive('updateImageOrder')
            ->once()
            ->with([$thirdImage->id, $secondImage->id], $item);
        $this->imageUploadService->shouldReceive('setPrimaryImage')
            ->once()
            ->with($thirdImage->id, $item);
        $this->imageUploadService->shouldReceive('deleteImagesByIds')
            ->once()
            ->with([9, 10], $item);

        $this->service->processItemImageUpdates($request, $item);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_syncs_tags_when_tags_are_present(): void
    {
        $item = Item::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $request = Request::create('/', 'POST', [
            'tags' => $tags->pluck('id')->all(),
        ]);

        $this->service->handleTags($request, $item);
        $this->assertEqualsCanonicalizing($tags->pluck('id')->all(), $item->tags()->pluck('thing_tags.id')->all());
    }

    #[Test]
    public function it_falls_back_to_tag_ids_when_tags_are_not_present(): void
    {
        $item = Item::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $request = Request::create('/', 'POST', [
            'tag_ids' => $tags->pluck('id')->all(),
        ]);

        $this->service->handleTags($request, $item);
        $this->assertEqualsCanonicalizing($tags->pluck('id')->all(), $item->tags()->pluck('thing_tags.id')->all());
    }
}
