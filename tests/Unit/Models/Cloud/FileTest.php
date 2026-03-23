<?php

namespace Tests\Unit\Models\Cloud;

use App\Models\Cloud\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_can_be_created()
    {
        $user = User::factory()->create();
        $file = File::factory()->create([
            'user_id' => $user->id,
            'name' => 'test_file.txt',
            'original_name' => 'original_test_file.txt',
            'path' => '/path/to/file.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 1024,
            'is_folder' => false,
        ]);

        $this->assertDatabaseHas('cloud_files', [
            'id' => $file->id,
            'name' => 'test_file.txt',
            'user_id' => $user->id,
        ]);
    }

    public function test_file_belongs_to_user()
    {
        $user = User::factory()->create();
        $file = File::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $file->user);
        $this->assertEquals($user->id, $file->user->id);
    }

    public function test_file_can_have_parent()
    {
        $parentFile = File::factory()->create(['is_folder' => true]);
        $childFile = File::factory()->create(['parent_id' => $parentFile->id]);

        $this->assertInstanceOf(File::class, $childFile->parent);
        $this->assertEquals($parentFile->id, $childFile->parent->id);
    }

    public function test_file_can_have_children()
    {
        $parentFile = File::factory()->create(['is_folder' => true]);
        $child1 = File::factory()->create(['parent_id' => $parentFile->id]);
        $child2 = File::factory()->create(['parent_id' => $parentFile->id]);

        $children = $parentFile->children;
        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($child1));
        $this->assertTrue($children->contains($child2));
    }

    public function test_get_download_url()
    {
        $file = File::factory()->create();
        $downloadUrl = $file->getDownloadUrl();

        $this->assertStringContainsString('cloud/files', $downloadUrl);
        $this->assertStringContainsString($file->id, $downloadUrl);
    }

    public function test_get_type_attribute_for_folder()
    {
        $folder = File::factory()->create(['is_folder' => true]);

        $this->assertEquals('folder', $folder->type);
    }

    public function test_get_type_attribute_for_image_files()
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];

        foreach ($imageExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('image', $file->type);
        }
    }

    public function test_get_type_attribute_for_pdf_files()
    {
        $file = File::factory()->create([
            'is_folder' => false,
            'extension' => 'pdf',
        ]);

        $this->assertEquals('pdf', $file->type);
    }

    public function test_get_type_attribute_for_document_files()
    {
        $documentExtensions = ['doc', 'docx', 'txt', 'rtf', 'md', 'pages', 'key', 'numbers'];

        foreach ($documentExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('document', $file->type);
        }
    }

    public function test_get_type_attribute_for_spreadsheet_files()
    {
        $spreadsheetExtensions = ['xls', 'xlsx', 'csv'];

        foreach ($spreadsheetExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('spreadsheet', $file->type);
        }
    }

    public function test_get_type_attribute_for_archive_files()
    {
        $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz'];

        foreach ($archiveExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('archive', $file->type);
        }
    }

    public function test_get_type_attribute_for_audio_files()
    {
        $audioExtensions = ['mp3', 'wav', 'ogg', 'flac'];

        foreach ($audioExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('audio', $file->type);
        }
    }

    public function test_get_type_attribute_for_video_files()
    {
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'mkv'];

        foreach ($videoExtensions as $extension) {
            $file = File::factory()->create([
                'is_folder' => false,
                'extension' => $extension,
            ]);

            $this->assertEquals('video', $file->type);
        }
    }

    public function test_get_type_attribute_for_unknown_extension()
    {
        $file = File::factory()->create([
            'is_folder' => false,
            'extension' => 'unknown',
        ]);

        $this->assertEquals('other', $file->type);
    }

    public function test_get_type_attribute_case_insensitive()
    {
        $file = File::factory()->create([
            'is_folder' => false,
            'extension' => 'JPG',
        ]);

        $this->assertEquals('image', $file->type);
    }

    public function test_get_extensions_by_type_returns_expected_extension_lists()
    {
        $this->assertSame(['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'], File::getExtensionsByType('image'));
        $this->assertSame(['pdf'], File::getExtensionsByType('pdf'));
        $this->assertSame(['doc', 'docx', 'txt', 'rtf', 'md'], File::getExtensionsByType('document'));
        $this->assertSame(['xls', 'xlsx', 'csv'], File::getExtensionsByType('spreadsheet'));
        $this->assertSame(['zip', 'rar', '7z', 'tar', 'gz'], File::getExtensionsByType('archive'));
        $this->assertSame(['mp3', 'wav', 'ogg', 'flac'], File::getExtensionsByType('audio'));
        $this->assertSame(['mp4', 'avi', 'mov', 'wmv', 'mkv'], File::getExtensionsByType('video'));
        $this->assertSame([], File::getExtensionsByType('unknown'));
    }

    public function test_scope_where_has_file_type_filters_folders_known_extensions_and_unknown_types()
    {
        $folder = File::factory()->create(['is_folder' => true]);
        $image = File::factory()->create([
            'is_folder' => false,
            'extension' => 'jpg',
        ]);
        $document = File::factory()->create([
            'is_folder' => false,
            'extension' => 'txt',
        ]);

        $folderIds = File::query()->whereHasFileType('folder')->pluck('id')->all();
        $imageIds = File::query()->whereHasFileType('image')->pluck('id')->all();
        $unknownTypeIds = File::query()->whereHasFileType('unknown')->pluck('id')->all();

        $this->assertSame([$folder->id], $folderIds);
        $this->assertSame([$image->id], $imageIds);
        $this->assertContains($image->id, $unknownTypeIds);
        $this->assertContains($document->id, $unknownTypeIds);
        $this->assertNotContains($folder->id, $unknownTypeIds);
    }

    public function test_get_all_descendants_returns_nested_folder_ids_only()
    {
        $rootFolder = File::factory()->create(['is_folder' => true]);
        $childFolder = File::factory()->create([
            'is_folder' => true,
            'parent_id' => $rootFolder->id,
        ]);
        $grandchildFolder = File::factory()->create([
            'is_folder' => true,
            'parent_id' => $childFolder->id,
        ]);
        File::factory()->create([
            'is_folder' => false,
            'parent_id' => $rootFolder->id,
        ]);

        $actual = $rootFolder->getAllDescendants();
        sort($actual);
        $expected = [$childFolder->id, $grandchildFolder->id];
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_file_casts_size_to_integer()
    {
        $file = File::factory()->create(['size' => '1024']);

        $this->assertIsInt($file->size);
        $this->assertEquals(1024, $file->size);
    }

    public function test_file_casts_is_folder_to_boolean()
    {
        $file = File::factory()->create(['is_folder' => 1]);

        $this->assertIsBool($file->is_folder);
        $this->assertTrue($file->is_folder);
    }

    public function test_file_casts_dates()
    {
        $file = File::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $file->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $file->updated_at);
    }

    public function test_file_fillable_attributes()
    {
        $data = [
            'name' => 'test.txt',
            'original_name' => 'original_test.txt',
            'path' => '/path/to/file.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 1024,
            'parent_id' => null,
            'user_id' => User::factory()->create()->id,
            'is_folder' => false,
            'description' => 'Test file description',
        ];

        $file = File::create($data);

        $this->assertEquals($data['name'], $file->name);
        $this->assertEquals($data['original_name'], $file->original_name);
        $this->assertEquals($data['path'], $file->path);
        $this->assertEquals($data['mime_type'], $file->mime_type);
        $this->assertEquals($data['extension'], $file->extension);
        $this->assertEquals($data['size'], $file->size);
        $this->assertEquals($data['is_folder'], $file->is_folder);
        $this->assertEquals($data['description'], $file->description);
    }

    public function test_file_has_type_appended()
    {
        $file = File::factory()->create();

        $this->assertArrayHasKey('type', $file->toArray());
    }
}
