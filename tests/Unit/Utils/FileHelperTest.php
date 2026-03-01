<?php

namespace Tests\Unit\Utils;

use App\Utils\FileHelper;
use PHPUnit\Framework\TestCase;

class FileHelperTest extends TestCase
{
    private string $testDir;

    private string $testFile;

    private string $nonExistentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/filehelper_test_' . uniqid();
        $this->testFile = $this->testDir . '/test_file.txt';
        $this->nonExistentFile = $this->testDir . '/non_existent_file.txt';

        // Ensure test directory exists
        if (! is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files and directories
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }

        // Clean up any subdirectories created during tests
        $this->cleanupDirectory($this->testDir);

        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }

        parent::tearDown();
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * Test formatBytes method with various byte sizes
     */
    public function test_format_bytes(): void
    {
        // Test zero bytes
        $this->assertEquals('0 B', FileHelper::formatBytes(0));

        // Test bytes
        $this->assertEquals('512 B', FileHelper::formatBytes(512));

        // Test kilobytes
        $this->assertEquals('1.5 KB', FileHelper::formatBytes(1536));
        $this->assertEquals('1 MB', FileHelper::formatBytes(1024 * 1024));

        // Test megabytes
        $this->assertEquals('1.5 MB', FileHelper::formatBytes(1024 * 1024 * 1.5));

        // Test gigabytes
        $this->assertEquals('1.5 GB', FileHelper::formatBytes(1024 * 1024 * 1024 * 1.5));

        // Test terabytes
        $this->assertEquals('1.5 TB', FileHelper::formatBytes(1024 * 1024 * 1024 * 1024 * 1.5));

        // Test negative values (should be treated as 0)
        $this->assertEquals('0 B', FileHelper::formatBytes(-100));
    }

    /**
     * Test formatBytes with edge cases
     */
    public function test_format_bytes_edge_cases(): void
    {
        // Test very large numbers
        $this->assertEquals('1 TB', FileHelper::formatBytes(1024 * 1024 * 1024 * 1024));

        // Test values just below unit thresholds
        $this->assertEquals('1023 B', FileHelper::formatBytes(1023));
        $this->assertEquals('1024 KB', FileHelper::formatBytes(1024 * 1024 - 1));

        // Test values just above unit thresholds
        $this->assertEquals('1 KB', FileHelper::formatBytes(1024));
        $this->assertEquals('1 MB', FileHelper::formatBytes(1024 * 1024));
    }

    /**
     * Test getFileSize method
     */
    public function test_get_file_size(): void
    {
        // Test with non-existent file (returns false)
        $this->assertFalse(FileHelper::getFileSize($this->nonExistentFile));

        // Test with existing file
        $content = 'Hello World!';
        file_put_contents($this->testFile, $content);
        $this->assertEquals(strlen($content), FileHelper::getFileSize($this->testFile));
    }

    /**
     * Test getFormattedFileSize method
     */
    public function test_get_formatted_file_size(): void
    {
        // Test with non-existent file
        $this->assertEquals('0 B', FileHelper::getFormattedFileSize($this->nonExistentFile));

        // Test with existing file
        $content = 'Hello World!';
        file_put_contents($this->testFile, $content);
        $expectedSize = FileHelper::formatBytes(strlen($content));
        $this->assertEquals($expectedSize, FileHelper::getFormattedFileSize($this->testFile));
    }

    /**
     * Test isValidFile method
     */
    public function test_is_valid_file(): void
    {
        // Test with non-existent file
        $this->assertFalse(FileHelper::isValidFile($this->nonExistentFile));

        // Test with existing but empty file
        file_put_contents($this->testFile, '');
        $this->assertFalse(FileHelper::isValidFile($this->testFile));

        // Test with existing file with content
        $content = 'Hello World!';
        file_put_contents($this->testFile, $content);
        $this->assertTrue(FileHelper::isValidFile($this->testFile));

        // Test with directory (returns true if directory exists and is readable)
        $this->assertTrue(FileHelper::isValidFile($this->testDir));
    }

    /**
     * Test ensureDirectoryExists method
     */
    public function test_ensure_directory_exists(): void
    {
        // Test creating new directory
        $newDir = $this->testDir . '/subdir';
        $this->assertTrue(FileHelper::ensureDirectoryExists($newDir));
        $this->assertTrue(is_dir($newDir));

        // Test with existing directory
        $this->assertTrue(FileHelper::ensureDirectoryExists($newDir));

        // Test creating nested directories
        $nestedDir = $newDir . '/deep/nested/directory';
        $this->assertTrue(FileHelper::ensureDirectoryExists($nestedDir));
        $this->assertTrue(is_dir($nestedDir));
    }

    /**
     * Test ensureDirectoryExists with custom permissions
     */
    public function test_ensure_directory_exists_with_custom_permissions(): void
    {
        $customDir = $this->testDir . '/custom_perms';
        $this->assertTrue(FileHelper::ensureDirectoryExists($customDir, 0777));
        $this->assertTrue(is_dir($customDir));

        // Check if permissions are set correctly (may vary by system)
        $perms = fileperms($customDir) & 0777;
        $this->assertTrue($perms >= 0755); // Should be at least readable and executable
    }

    /**
     * Test integration between methods
     */
    public function test_integration_between_methods(): void
    {
        // Create a file and test all methods together
        $content = 'Test content for integration testing';
        file_put_contents($this->testFile, $content);

        // Test that all methods work together
        $this->assertTrue(FileHelper::isValidFile($this->testFile));
        $this->assertEquals(strlen($content), FileHelper::getFileSize($this->testFile));
        $this->assertEquals(FileHelper::formatBytes(strlen($content)), FileHelper::getFormattedFileSize($this->testFile));
    }

    /**
     * Test with special characters in file paths
     */
    public function test_with_special_characters(): void
    {
        $specialFile = $this->testDir . '/test file with spaces.txt';
        $content = 'Test content';
        file_put_contents($specialFile, $content);

        $this->assertTrue(FileHelper::isValidFile($specialFile));
        $this->assertEquals(strlen($content), FileHelper::getFileSize($specialFile));
        $this->assertEquals(FileHelper::formatBytes(strlen($content)), FileHelper::getFormattedFileSize($specialFile));

        // Clean up
        unlink($specialFile);
    }

    /**
     * Test with very large file sizes
     */
    public function test_with_large_file_sizes(): void
    {
        // Create a file with known size
        $size = 1024 * 1024; // 1MB
        $content = str_repeat('A', $size);
        file_put_contents($this->testFile, $content);

        $this->assertEquals($size, FileHelper::getFileSize($this->testFile));
        $this->assertEquals('1 MB', FileHelper::getFormattedFileSize($this->testFile));
    }
}
