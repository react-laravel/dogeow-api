<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\Cloud\FileController;
use App\Models\Cloud\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class FileControllerTest extends TestCase
{
    use RefreshDatabase;

    private FileController $controller;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FileController;
        $this->user = User::factory()->create();
        Auth::login($this->user);
        Storage::fake('public');
    }

    /**
     * Test the private formatSize method using reflection
     */
    public function test_format_size_method()
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('formatSize');
        $method->setAccessible(true);

        // Test different size formats
        $this->assertEquals('0 B', $method->invoke($this->controller, 0));
        $this->assertEquals('1 KB', $method->invoke($this->controller, 1024));
        $this->assertEquals('1 MB', $method->invoke($this->controller, 1024 * 1024));
        $this->assertEquals('1 GB', $method->invoke($this->controller, 1024 * 1024 * 1024));
        $this->assertEquals('1 TB', $method->invoke($this->controller, 1024 * 1024 * 1024 * 1024));
    }

    /**
     * Test the private buildFolderTree method using reflection
     */
    public function test_build_folder_tree_method()
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildFolderTree');
        $method->setAccessible(true);

        // Create a folder structure
        $rootFolder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
            'name' => 'Root',
        ]);

        $childFolder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
            'name' => 'Child',
            'parent_id' => $rootFolder->id,
        ]);

        $result = $method->invoke($this->controller, $rootFolder);

        $this->assertEquals($rootFolder->id, $result['id']);
        $this->assertEquals('Root', $result['name']);
        $this->assertCount(1, $result['children']);
        $this->assertEquals('Child', $result['children'][0]['name']);
    }

    /**
     * Test the private deleteFolderIteratively method using reflection
     */
    public function test_delete_folder_method()
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('deleteFolderIteratively');
        $method->setAccessible(true);

        // Create a folder with children
        $folder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
        ]);

        $childFile = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => false,
            'path' => 'cloud/1/2024/01/child.txt',
            'parent_id' => $folder->id,
        ]);

        Storage::disk('public')->put($childFile->path, 'test content');

        $method->invoke($this->controller, $folder);

        $this->assertDatabaseMissing('cloud_files', ['id' => $folder->id]);
        $this->assertDatabaseMissing('cloud_files', ['id' => $childFile->id]);
        $this->assertFalse(Storage::disk('public')->exists($childFile->path));
    }

    /**
     * Test file type detection in the File model
     */
    public function test_file_type_detection()
    {
        $imageFile = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => false,
            'extension' => 'jpg',
        ]);

        $pdfFile = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => false,
            'extension' => 'pdf',
        ]);

        $folder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
        ]);

        $this->assertEquals('image', $imageFile->type);
        $this->assertEquals('pdf', $pdfFile->type);
        $this->assertEquals('folder', $folder->type);
    }

    /**
     * Test file relationships
     */
    public function test_file_relationships()
    {
        $parentFolder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
        ]);

        $childFile = File::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentFolder->id,
        ]);

        $this->assertInstanceOf(File::class, $childFile->parent);
        $this->assertEquals($parentFolder->id, $childFile->parent->id);

        $children = $parentFolder->children;
        $this->assertCount(1, $children);
        $this->assertEquals($childFile->id, $children->first()->id);
    }

    /**
     * Test file download URL generation
     */
    public function test_file_download_url()
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $url = $file->getDownloadUrl();
        $this->assertStringContainsString('/api/cloud/files/' . $file->id . '/download', $url);
    }

    /**
     * Test file model fillable attributes
     */
    public function test_file_model_fillable_attributes()
    {
        $file = new File;
        $fillable = $file->getFillable();

        $expectedFillable = [
            'name',
            'original_name',
            'path',
            'mime_type',
            'extension',
            'size',
            'parent_id',
            'user_id',
            'is_folder',
            'description',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    /**
     * Test file model casts
     */
    public function test_file_model_casts()
    {
        $file = new File;
        $casts = $file->getCasts();

        $this->assertArrayHasKey('size', $casts);
        $this->assertEquals('integer', $casts['size']);

        $this->assertArrayHasKey('is_folder', $casts);
        $this->assertEquals('boolean', $casts['is_folder']);
    }

    /**
     * Test file model appends
     */
    public function test_file_model_appends()
    {
        $file = new File;
        $appends = $file->getAppends();

        $this->assertContains('type', $appends);
    }

    /**
     * Test user relationship
     */
    public function test_file_user_relationship()
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $file->user);
        $this->assertEquals($this->user->id, $file->user->id);
    }

    /**
     * Test file type detection for all supported types
     */
    public function test_all_file_types()
    {
        $testCases = [
            ['extension' => 'jpg', 'expected_type' => 'image'],
            ['extension' => 'jpeg', 'expected_type' => 'image'],
            ['extension' => 'png', 'expected_type' => 'image'],
            ['extension' => 'gif', 'expected_type' => 'image'],
            ['extension' => 'bmp', 'expected_type' => 'image'],
            ['extension' => 'svg', 'expected_type' => 'image'],
            ['extension' => 'webp', 'expected_type' => 'image'],
            ['extension' => 'pdf', 'expected_type' => 'pdf'],
            ['extension' => 'doc', 'expected_type' => 'document'],
            ['extension' => 'docx', 'expected_type' => 'document'],
            ['extension' => 'txt', 'expected_type' => 'document'],
            ['extension' => 'rtf', 'expected_type' => 'document'],
            ['extension' => 'md', 'expected_type' => 'document'],
            ['extension' => 'pages', 'expected_type' => 'document'],
            ['extension' => 'key', 'expected_type' => 'document'],
            ['extension' => 'numbers', 'expected_type' => 'document'],
            ['extension' => 'xls', 'expected_type' => 'spreadsheet'],
            ['extension' => 'xlsx', 'expected_type' => 'spreadsheet'],
            ['extension' => 'csv', 'expected_type' => 'spreadsheet'],
            ['extension' => 'zip', 'expected_type' => 'archive'],
            ['extension' => 'rar', 'expected_type' => 'archive'],
            ['extension' => '7z', 'expected_type' => 'archive'],
            ['extension' => 'tar', 'expected_type' => 'archive'],
            ['extension' => 'gz', 'expected_type' => 'archive'],
            ['extension' => 'mp3', 'expected_type' => 'audio'],
            ['extension' => 'wav', 'expected_type' => 'audio'],
            ['extension' => 'ogg', 'expected_type' => 'audio'],
            ['extension' => 'flac', 'expected_type' => 'audio'],
            ['extension' => 'mp4', 'expected_type' => 'video'],
            ['extension' => 'avi', 'expected_type' => 'video'],
            ['extension' => 'mov', 'expected_type' => 'video'],
            ['extension' => 'wmv', 'expected_type' => 'video'],
            ['extension' => 'mkv', 'expected_type' => 'video'],
            ['extension' => 'xyz', 'expected_type' => 'other'],
        ];

        foreach ($testCases as $testCase) {
            $file = File::factory()->create([
                'user_id' => $this->user->id,
                'is_folder' => false,
                'extension' => $testCase['extension'],
            ]);

            $this->assertEquals($testCase['expected_type'], $file->type,
                "Failed for extension: {$testCase['extension']}");
        }
    }

    /**
     * Test folder type detection
     */
    public function test_folder_type_detection()
    {
        $folder = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => true,
        ]);

        $this->assertEquals('folder', $folder->type);
    }

    /**
     * Test file size formatting edge cases
     */
    public function test_format_size_edge_cases()
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('formatSize');
        $method->setAccessible(true);

        // Test negative values
        $this->assertEquals('0 B', $method->invoke($this->controller, -100));

        // Test very large values
        $this->assertEquals('1.1 TB', $method->invoke($this->controller, 1024 * 1024 * 1024 * 1024 + 1024 * 1024 * 1024 * 100));

        // Test exact power of 2 values
        $this->assertEquals('1 KB', $method->invoke($this->controller, 1024));
        $this->assertEquals('1 MB', $method->invoke($this->controller, 1024 * 1024));
        $this->assertEquals('1 GB', $method->invoke($this->controller, 1024 * 1024 * 1024));
    }
}
