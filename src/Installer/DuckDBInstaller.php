<?php

namespace LaravelDuckDB\Installer;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * DuckDBInstaller
 *
 * Automatically downloads and installs the DuckDB native library and FFI header
 * into a configured directory. Supports Windows, Linux (amd64/aarch64), and macOS.
 * Idempotent — skips installation if files are already present.
 */
class DuckDBInstaller
{
    // Native library filenames per platform
    public const LIB_WINDOWS = 'duckdb.dll';
    public const LIB_LINUX   = 'libduckdb.so';
    public const LIB_MACOS   = 'libduckdb.dylib';

    // Header filename expected by the FFI binding
    public const HEADER_FILENAME = 'duckdb-ffi.h';

    protected string $installPath;
    protected string $version;

    public function __construct(string $installPath, string $version)
    {
        $this->installPath = rtrim($installPath, DIRECTORY_SEPARATOR);
        $this->version     = $version;
    }

    /**
     * Run the installer.
     * Idempotent — skips if both the native library and header already exist.
     */
    public function install(): void
    {
        if ($this->isInstalled()) {
            Log::debug('[DuckDB Installer] Binaries already present, skipping.', [
                'path' => $this->installPath,
            ]);

            $this->configureEnvironment();
            return;
        }

        Log::info('[DuckDB Installer] Starting installation.', [
            'path'    => $this->installPath,
            'version' => $this->version,
            'os'      => PHP_OS_FAMILY,
            'arch'    => php_uname('m'),
        ]);

        $this->ensureDirectoryExists($this->installPath);
        $this->downloadLibrary();
        $this->downloadHeader();
        $this->cleanHeader();
        $this->configureEnvironment();

        Log::info('[DuckDB Installer] Installation complete.', [
            'path' => $this->installPath,
        ]);
    }

    /**
     * Returns true when the native library and header are both present.
     */
    public function isInstalled(): bool
    {
        return file_exists($this->libPath()) && file_exists($this->headerPath());
    }

    /**
     * Set runtime environment variables expected by the Saturio DuckDB binding.
     * Called even on a cache-hit so variables are always available.
     */
    public function configureEnvironment(): void
    {
        $vars = [
            'DUCKDB_LIB_PATH'    => $this->installPath,
            'DUCKDB_HEADER_PATH' => $this->installPath,
            'DUCKDB_PHP_PATH'    => $this->installPath,
        ];

        foreach ($vars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        Log::debug('[DuckDB Installer] Environment variables configured.', $vars);
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    protected function downloadLibrary(): void
    {
        $url     = $this->libraryDownloadUrl();
        $zipPath = $this->installPath . DIRECTORY_SEPARATOR . '_duckdb_tmp.zip';

        Log::debug('[DuckDB Installer] Downloading native library.', ['url' => $url]);

        try {
            $this->downloadFile($url, $zipPath);
            $this->extractLibFromZip($zipPath);
        } finally {
            // Always clean up the temp archive
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }

        Log::debug('[DuckDB Installer] Native library saved.', ['file' => $this->libPath()]);
    }

    protected function downloadHeader(): void
    {
        $url  = "https://raw.githubusercontent.com/duckdb/duckdb/refs/tags/v{$this->version}/src/include/duckdb.h";
        $dest = $this->headerPath();

        Log::debug('[DuckDB Installer] Downloading FFI header.', ['url' => $url]);

        $this->downloadFile($url, $dest);

        Log::debug('[DuckDB Installer] Header saved.', ['file' => $dest]);
    }

    /**
     * Cleans the downloaded duckdb.h to make it compatible with PHP FFI.
     */
    protected function cleanHeader(): void
    {
        $path = $this->headerPath();
        if (!file_exists($path)) {
            return;
        }

        Log::debug('[DuckDB Installer] Cleaning FFI header for compatibility.');

        $content = file_get_contents($path);

        // 1. Remove #include lines
        $content = preg_replace('/^#include\s+.*$/m', '', $content);

        // 2. Remove DUCKDB_*_API macro usage
        $content = preg_replace('/DUCKDB_[A-Z_]*API/', '', $content);
        $content = str_replace('DUCKDB_API', '', $content);

        // 3. Remove extern "C" blocks and C++ guards
        $content = preg_replace('/#ifdef\s+__cplusplus\s+extern\s+"C"\s+\{\s+#endif/s', '', $content);
        $content = preg_replace('/#ifdef\s+__cplusplus\s+\}\s+#endif/s', '', $content);

        // 4. Handle some common macros if they are still there
        $content = preg_replace('/#if defined\(_WIN32\).*?#endif/s', '', $content);
        
        // 5. Add standard types if they were removed or are missing
        $prefix = "typedef _Bool bool;\n";
        $prefix .= "typedef char int8_t;\n";
        $prefix .= "typedef short int16_t;\n";
        $prefix .= "typedef int int32_t;\n";
        $prefix .= "typedef long long int64_t;\n";
        $prefix .= "typedef unsigned char uint8_t;\n";
        $prefix .= "typedef unsigned short uint16_t;\n";
        $prefix .= "typedef unsigned int uint32_t;\n";
        $prefix .= "typedef unsigned long long uint64_t;\n\n";

        file_put_contents($path, $prefix . $content);
    }

    protected function libraryDownloadUrl(): string
    {
        $base = "https://github.com/duckdb/duckdb/releases/download/v{$this->version}";
        $arch = strtolower(php_uname('m'));

        return match (true) {
            PHP_OS_FAMILY === 'Windows'
                => "{$base}/libduckdb-windows-amd64.zip",
            PHP_OS_FAMILY === 'Linux' && str_contains($arch, 'aarch64')
                => "{$base}/libduckdb-linux-aarch64.zip",
            PHP_OS_FAMILY === 'Linux'
                => "{$base}/libduckdb-linux-amd64.zip",
            PHP_OS_FAMILY === 'Darwin'
                => "{$base}/libduckdb-osx-universal.zip",
            default => throw new RuntimeException(
                '[DuckDB Installer] Unsupported operating system: ' . PHP_OS_FAMILY
            ),
        };
    }

    protected function getLibFilename(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => self::LIB_WINDOWS,
            'Darwin'  => self::LIB_MACOS,
            default   => self::LIB_LINUX,
        };
    }

    protected function libPath(): string
    {
        return $this->installPath . DIRECTORY_SEPARATOR . $this->getLibFilename();
    }

    protected function headerPath(): string
    {
        return $this->installPath . DIRECTORY_SEPARATOR . self::HEADER_FILENAME;
    }

    protected function downloadFile(string $url, string $destination): void
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => true,
                'timeout'         => 120,
                'user_agent'      => 'filipefernandes/laravel-duckdb',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        if ($data === false) {
            throw new RuntimeException(
                "[DuckDB Installer] Failed to download: {$url}"
            );
        }

        if (file_put_contents($destination, $data) === false) {
            throw new RuntimeException(
                "[DuckDB Installer] Failed to write file: {$destination}"
            );
        }
    }

    protected function extractLibFromZip(string $zipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                '[DuckDB Installer] PHP extension "ext-zip" is required. Enable it in your php.ini.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(
                "[DuckDB Installer] Unable to open archive: {$zipPath}"
            );
        }

        $libFilename = $this->getLibFilename();
        $extracted   = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (basename($entryName) !== $libFilename) {
                continue;
            }

            $zip->extractTo($this->installPath, $entryName);

            // Flatten to root of installPath if the entry was inside a subdirectory
            $extractedPath = $this->installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryName);
            $targetPath    = $this->libPath();

            if ($extractedPath !== $targetPath && file_exists($extractedPath)) {
                rename($extractedPath, $targetPath);

                $subdir = dirname($extractedPath);
                if ($subdir !== $this->installPath && is_dir($subdir)) {
                    @rmdir($subdir);
                }
            }

            $extracted = true;
            break;
        }

        $zip->close();

        if (! $extracted) {
            throw new RuntimeException(
                "[DuckDB Installer] Could not find '{$libFilename}' in the downloaded archive."
            );
        }
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException(
                "[DuckDB Installer] Failed to create install directory: {$path}"
            );
        }
    }
}
