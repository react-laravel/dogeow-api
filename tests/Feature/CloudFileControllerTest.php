<?php

namespace Tests\Feature;

use App\Models\Cloud\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudFileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Storage::fake('public');
    }

    public function test_index_filters_root_files_by_search_type_and_sorting(): void
    {
        $folder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'name' => 'Gallery Root',
        ]);

        $firstMatch = File::factory()->image()->create([
            'user_id' => $this->user->id,
            'name' => 'Gallery Alpha',
        ]);
        $secondMatch = File::factory()->image()->create([
            'user_id' => $this->user->id,
            'name' => 'Gallery Zulu',
        ]);
        File::factory()->image()->create([
            'user_id' => $this->user->id,
            'name' => 'Gallery Child',
            'parent_id' => $folder->id,
        ]);
        File::factory()->document()->create([
            'user_id' => $this->user->id,
            'name' => 'Gallery Notes',
        ]);
        File::factory()->image()->create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Gallery Foreign',
        ]);

        $response = $this->getJson('/api/cloud/files?search=Gallery&type=image&sort_by=name&sort_direction=asc');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $firstMatch->id)
            ->assertJsonPath('1.id', $secondMatch->id);
    }

    public function test_create_folder_uses_guest_fallback_user_id(): void
    {
        Auth::logout();
        Auth::forgetGuards();

        $response = $this->postJson('/api/cloud/folders', [
            'name' => 'Guest Folder',
            'description' => 'Created without login',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Guest Folder',
                'user_id' => 1,
                'is_folder' => true,
            ]);

        $this->assertDatabaseHas('cloud_files', [
            'name' => 'Guest Folder',
            'user_id' => 1,
            'is_folder' => true,
        ]);
    }

    public function test_upload_stores_file_and_creates_database_record(): void
    {
        $file = UploadedFile::fake()->create('report.txt', 32, 'text/plain');

        $response = $this->postJson('/api/cloud/files', [
            'file' => $file,
            'description' => 'Quarterly report',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'report')
            ->assertJsonPath('original_name', 'report.txt')
            ->assertJsonPath('extension', 'txt')
            ->assertJsonPath('description', 'Quarterly report')
            ->assertJsonPath('user_id', $this->user->id);

        $path = $response->json('path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('cloud_files', [
            'original_name' => 'report.txt',
            'user_id' => $this->user->id,
            'description' => 'Quarterly report',
        ]);
    }

    public function test_download_returns_error_for_folder(): void
    {
        $folder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/cloud/files/{$folder->id}/download");

        $response->assertStatus(400)
            ->assertJson([
                'error' => '不能下载文件夹',
            ]);
    }

    public function test_download_returns_error_when_storage_file_is_missing(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'path' => 'cloud/missing/report.txt',
        ]);

        $response = $this->getJson("/api/cloud/files/{$file->id}/download");

        $response->assertStatus(404)
            ->assertJson([
                'error' => '文件不存在',
            ]);
    }

    public function test_destroy_removes_nested_folders_and_files(): void
    {
        $rootFolder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'name' => 'Root',
        ]);
        $childFolder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'parent_id' => $rootFolder->id,
            'name' => 'Child',
        ]);
        $nestedFile = File::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $childFolder->id,
            'path' => 'cloud/test/nested.txt',
        ]);
        $siblingFile = File::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $rootFolder->id,
            'path' => 'cloud/test/sibling.txt',
        ]);

        Storage::disk('public')->put($nestedFile->path, 'nested');
        Storage::disk('public')->put($siblingFile->path, 'sibling');

        $response = $this->deleteJson("/api/cloud/files/{$rootFolder->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('cloud_files', ['id' => $rootFolder->id]);
        $this->assertDatabaseMissing('cloud_files', ['id' => $childFolder->id]);
        $this->assertDatabaseMissing('cloud_files', ['id' => $nestedFile->id]);
        $this->assertDatabaseMissing('cloud_files', ['id' => $siblingFile->id]);
        Storage::disk('public')->assertMissing($nestedFile->path);
        Storage::disk('public')->assertMissing($siblingFile->path);
    }

    public function test_show_returns_owned_file_details(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Manual',
            'description' => 'Reference doc',
            'extension' => 'pdf',
        ]);

        $response = $this->getJson("/api/cloud/files/{$file->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $file->id,
                'name' => 'Manual',
                'description' => 'Reference doc',
                'type' => 'pdf',
            ]);
    }

    public function test_update_changes_owned_file_metadata(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Before',
            'description' => 'Old',
        ]);

        $response = $this->putJson("/api/cloud/files/{$file->id}", [
            'name' => 'After',
            'description' => 'Updated',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $file->id,
                'name' => 'After',
                'description' => 'Updated',
            ]);

        $this->assertDatabaseHas('cloud_files', [
            'id' => $file->id,
            'name' => 'After',
            'description' => 'Updated',
        ]);
    }

    public function test_update_returns_not_found_for_other_users_file(): void
    {
        $file = File::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->putJson("/api/cloud/files/{$file->id}", [
            'name' => 'Forbidden',
        ]);

        $response->assertStatus(404);
    }

    public function test_move_updates_parent_for_files_and_can_move_back_to_root(): void
    {
        $targetFolder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
        ]);
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);

        $moveIntoFolder = $this->postJson('/api/cloud/files/move', [
            'file_ids' => [$file->id],
            'target_folder_id' => $targetFolder->id,
        ]);

        $moveIntoFolder->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertDatabaseHas('cloud_files', [
            'id' => $file->id,
            'parent_id' => $targetFolder->id,
        ]);

        $moveToRoot = $this->postJson('/api/cloud/files/move', [
            'file_ids' => [$file->id],
            'target_folder_id' => null,
        ]);

        $moveToRoot->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertDatabaseHas('cloud_files', [
            'id' => $file->id,
            'parent_id' => null,
        ]);
    }

    public function test_move_rejects_target_when_selected_folder_matches_destination(): void
    {
        $folder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/cloud/files/move', [
            'file_ids' => [$folder->id],
            'target_folder_id' => $folder->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => '不能将文件夹移动到自身或其子文件夹中',
            ]);
    }

    public function test_move_requires_target_to_be_a_folder_owned_by_current_user(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $targetFile = File::factory()->create([
            'user_id' => $this->user->id,
            'is_folder' => false,
        ]);

        $response = $this->postJson('/api/cloud/files/move', [
            'file_ids' => [$file->id],
            'target_folder_id' => $targetFile->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_statistics_returns_totals_and_grouped_type_breakdown(): void
    {
        File::factory()->image()->create([
            'user_id' => $this->user->id,
            'size' => 1024,
            'extension' => 'jpg',
        ]);
        File::factory()->create([
            'user_id' => $this->user->id,
            'size' => 2048,
            'extension' => 'pdf',
            'is_folder' => false,
        ]);
        File::factory()->document()->create([
            'user_id' => $this->user->id,
            'size' => 4096,
            'extension' => 'doc',
        ]);
        File::factory()->folder()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/cloud/statistics');

        $response->assertStatus(200)
            ->assertJson([
                'total_size' => 7168,
                'file_count' => 3,
                'folder_count' => 1,
                'human_readable_size' => '7 KB',
            ]);

        $types = $this->keyByFileType(collect($response->json('files_by_type')));
        $this->assertSame(1, $types['图片']['count']);
        $this->assertSame(1024, $types['图片']['total_size']);
        $this->assertSame(1, $types['PDF']['count']);
        $this->assertSame(2048, $types['PDF']['total_size']);
        $this->assertSame(1, $types['文档']['count']);
        $this->assertSame(4096, $types['文档']['total_size']);
    }

    public function test_tree_returns_nested_folder_structure_only(): void
    {
        $rootFolder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'name' => 'Root',
        ]);
        $childFolder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'parent_id' => $rootFolder->id,
            'name' => 'Child',
        ]);
        File::factory()->folder()->create([
            'user_id' => $this->user->id,
            'parent_id' => $childFolder->id,
            'name' => 'Grandchild',
        ]);
        File::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $rootFolder->id,
            'is_folder' => false,
            'name' => 'Loose File',
        ]);

        $response = $this->getJson('/api/cloud/tree');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Root')
            ->assertJsonPath('0.children.0.name', 'Child')
            ->assertJsonPath('0.children.0.children.0.name', 'Grandchild');
    }

    public function test_preview_returns_image_url_for_images(): void
    {
        $file = File::factory()->image()->create([
            'user_id' => $this->user->id,
            'extension' => 'jpg',
            'path' => 'cloud/previews/photo.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        Storage::disk('public')->put($file->path, 'image-bytes');

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview?thumb=true");

        $response->assertStatus(200)
            ->assertJsonPath('type', 'image');
        $this->assertStringEndsWith('/storage/' . $file->path, $response->json('url'));
    }

    public function test_preview_returns_pdf_url_for_pdfs(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'pdf',
            'path' => 'cloud/previews/manual.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('public')->put($file->path, 'pdf-bytes');

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(200)
            ->assertJsonPath('type', 'pdf');
        $this->assertStringEndsWith('/storage/' . $file->path, $response->json('url'));
    }

    public function test_preview_returns_text_content_for_text_files(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'txt',
            'path' => 'cloud/previews/readme.txt',
            'mime_type' => 'text/plain',
        ]);
        Storage::disk('public')->put($file->path, "line one\nline two");

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'text',
                'content' => "line one\nline two",
            ]);
    }

    public function test_preview_returns_apple_document_message(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'pages',
            'path' => 'cloud/previews/design.pages',
        ]);
        Storage::disk('public')->put($file->path, 'pages-bytes');

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'document',
                'message' => '此文件是苹果 PAGES 格式，需要在 Mac 上使用相应的应用程序打开',
            ]);
    }

    public function test_preview_returns_office_document_message(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'xlsx',
            'path' => 'cloud/previews/sheet.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
        Storage::disk('public')->put($file->path, 'xlsx-bytes');

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'document',
                'message' => '此文件是 Microsoft Office 格式，需要使用相应的应用程序打开',
            ]);
    }

    public function test_preview_returns_unknown_message_for_unsupported_files(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'zip',
            'path' => 'cloud/previews/archive.zip',
            'mime_type' => 'application/zip',
        ]);
        Storage::disk('public')->put($file->path, 'zip-bytes');

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'unknown',
                'message' => '此文件类型不支持预览，请下载后查看',
            ]);
    }

    public function test_preview_rejects_folders(): void
    {
        $folder = File::factory()->folder()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/cloud/files/{$folder->id}/preview");

        $response->assertStatus(400)
            ->assertJson([
                'error' => '不能预览文件夹',
            ]);
    }

    public function test_preview_returns_not_found_when_storage_file_is_missing(): void
    {
        $file = File::factory()->create([
            'user_id' => $this->user->id,
            'extension' => 'txt',
            'path' => 'cloud/previews/missing.txt',
            'mime_type' => 'text/plain',
        ]);

        $response = $this->getJson("/api/cloud/files/{$file->id}/preview");

        $response->assertStatus(404)
            ->assertJson([
                'error' => '文件不存在',
            ]);
    }

    private function keyByFileType(Collection $stats): array
    {
        return $stats
            ->mapWithKeys(fn (array $row) => [$row['file_type'] => $row])
            ->all();
    }
}
