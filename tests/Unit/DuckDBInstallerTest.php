<?php

namespace LaravelDuckDB\Tests\Unit;

use LaravelDuckDB\Installer\DuckDBInstaller;
use LaravelDuckDB\Tests\TestCase;
use ReflectionMethod;

class DuckDBInstallerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a dedicated temp directory per test so tests don't interfere
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duckdb_installer_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp directory after each test
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // isInstalled()
    // -------------------------------------------------------------------------

    public function test_is_not_installed_when_directory_is_empty(): void
    {
        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');

        $this->assertFalse($installer->isInstalled());
    }

    public function test_is_not_installed_when_only_lib_exists(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $this->getLibFilename(), 'fake');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');

        $this->assertFalse($installer->isInstalled());
    }

    public function test_is_not_installed_when_only_header_exists(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . DuckDBInstaller::HEADER_FILENAME, 'fake');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');

        $this->assertFalse($installer->isInstalled());
    }

    public function test_is_installed_when_both_files_exist(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $this->getLibFilename(), 'fake');
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . DuckDBInstaller::HEADER_FILENAME, 'fake');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');

        $this->assertTrue($installer->isInstalled());
    }

    // -------------------------------------------------------------------------
    // configureEnvironment()
    // -------------------------------------------------------------------------

    public function test_configure_environment_sets_env_variables(): void
    {
        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $installer->configureEnvironment();

        $this->assertSame($this->tmpDir, getenv('DUCKDB_LIB_PATH'));
        $this->assertSame($this->tmpDir, getenv('DUCKDB_HEADER_PATH'));
        $this->assertSame($this->tmpDir, $_ENV['DUCKDB_LIB_PATH']);
        $this->assertSame($this->tmpDir, $_ENV['DUCKDB_HEADER_PATH']);
        $this->assertSame($this->tmpDir, $_SERVER['DUCKDB_LIB_PATH']);
        $this->assertSame($this->tmpDir, $_SERVER['DUCKDB_HEADER_PATH']);
    }

    // -------------------------------------------------------------------------
    // install() — skips when already installed
    // -------------------------------------------------------------------------

    public function test_install_skips_download_when_already_installed(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $this->getLibFilename(), 'original_lib');
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . DuckDBInstaller::HEADER_FILENAME, 'original_header');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $installer->install();

        // Files should be untouched
        $this->assertSame('original_lib', file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . $this->getLibFilename()));
        $this->assertSame('original_header', file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . DuckDBInstaller::HEADER_FILENAME));
    }

    public function test_install_sets_env_even_when_already_installed(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $this->getLibFilename(), 'fake');
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . DuckDBInstaller::HEADER_FILENAME, 'fake');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $installer->install();

        $this->assertSame($this->tmpDir, getenv('DUCKDB_LIB_PATH'));
        $this->assertSame($this->tmpDir, getenv('DUCKDB_HEADER_PATH'));
    }

    // -------------------------------------------------------------------------
    // libraryDownloadUrl() — via reflection
    // -------------------------------------------------------------------------

    public function test_library_download_url_contains_version(): void
    {
        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'libraryDownloadUrl');
        $method->setAccessible(true);

        $url = $method->invoke($installer);

        $this->assertStringContainsString('v1.2.1', $url);
        $this->assertStringContainsString('github.com/duckdb/duckdb/releases/download', $url);
        $this->assertStringEndsWith('.zip', $url);
    }

    public function test_library_download_url_varies_with_version(): void
    {
        $installer110 = new DuckDBInstaller($this->tmpDir, '1.1.0');
        $installer121 = new DuckDBInstaller($this->tmpDir, '1.2.1');

        $method = new ReflectionMethod(DuckDBInstaller::class, 'libraryDownloadUrl');
        $method->setAccessible(true);

        $this->assertStringContainsString('v1.1.0', $method->invoke($installer110));
        $this->assertStringContainsString('v1.2.1', $method->invoke($installer121));
    }

    // -------------------------------------------------------------------------
    // getLibFilename() — via reflection
    // -------------------------------------------------------------------------

    public function test_lib_filename_matches_current_os(): void
    {
        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'getLibFilename');
        $method->setAccessible(true);

        $filename = $method->invoke($installer);

        $expected = match (PHP_OS_FAMILY) {
            'Windows' => DuckDBInstaller::LIB_WINDOWS,
            'Darwin'  => DuckDBInstaller::LIB_MACOS,
            default   => DuckDBInstaller::LIB_LINUX,
        };

        $this->assertSame($expected, $filename);
    }

    // -------------------------------------------------------------------------
    // ensureDirectoryExists() — via reflection
    // -------------------------------------------------------------------------

    public function test_ensure_directory_creates_nested_directories(): void
    {
        $nested = $this->tmpDir . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'c';

        $installer = new DuckDBInstaller($nested, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'ensureDirectoryExists');
        $method->setAccessible(true);
        $method->invoke($installer, $nested);

        $this->assertDirectoryExists($nested);
    }

    public function test_ensure_directory_is_idempotent_when_already_exists(): void
    {
        mkdir($this->tmpDir, 0755, true);

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'ensureDirectoryExists');
        $method->setAccessible(true);

        // Should not throw when directory already exists
        $method->invoke($installer, $this->tmpDir);
        $this->assertDirectoryExists($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // downloadFile() — via reflection with a local file:// URL
    // -------------------------------------------------------------------------

    public function test_download_file_writes_content_to_destination(): void
    {
        mkdir($this->tmpDir, 0755, true);

        // Create a local source file and use a file:// URL to simulate HTTP
        $sourceFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'source.txt';
        file_put_contents($sourceFile, 'hello world');

        $destFile  = $this->tmpDir . DIRECTORY_SEPARATOR . 'dest.txt';
        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'downloadFile');
        $method->setAccessible(true);
        $method->invoke($installer, 'file://' . $sourceFile, $destFile);

        $this->assertFileExists($destFile);
        $this->assertSame('hello world', file_get_contents($destFile));
    }

    public function test_download_file_throws_on_invalid_url(): void
    {
        mkdir($this->tmpDir, 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to download/');

        $installer = new DuckDBInstaller($this->tmpDir, '1.2.1');
        $method    = new ReflectionMethod(DuckDBInstaller::class, 'downloadFile');
        $method->setAccessible(true);
        $method->invoke($installer, 'file:///this/does/not/exist.txt', $this->tmpDir . '/out.txt');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getLibFilename(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => DuckDBInstaller::LIB_WINDOWS,
            'Darwin'  => DuckDBInstaller::LIB_MACOS,
            default   => DuckDBInstaller::LIB_LINUX,
        };
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
