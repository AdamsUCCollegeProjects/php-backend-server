<?php

declare(strict_types=1);

namespace App\Storage\Providers;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class LocalStorageProvider implements StorageProviderInterface
{
    private const DIRECTORY_MODE = 0755;
    private const TEMP_PREFIX = '.tmp-';

    private readonly string $resolvedRoot;

    public function __construct(string $storageRoot)
    {
        $this->resolvedRoot = $this->initializeStorageRoot($storageRoot);
    }

    /**
     * @param resource $stream
     */
    public function storeStream(string $relativePath, $stream): void
    {
        $this->assertReadableStream($stream);

        $absolutePath = $this->resolveAbsolutePath($relativePath);
        $this->ensureParentDirectory($absolutePath);
        $this->writeAtomically($absolutePath, $stream);
        $this->assertResolvedFileUnderRoot($absolutePath);
    }

    /**
     * @return resource
     */
    public function openReadStream(string $relativePath)
    {
        $absolutePath = $this->resolveAbsolutePath($relativePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Storage file not found.');
        }

        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open storage file for reading.');
        }

        return $stream;
    }

    public function delete(string $relativePath): void
    {
        $absolutePath = $this->resolveAbsolutePath($relativePath);

        if (! is_file($absolutePath)) {
            return;
        }

        if (! unlink($absolutePath)) {
            throw new RuntimeException('Failed to delete storage file.');
        }
    }

    public function exists(string $relativePath): bool
    {
        try {
            $absolutePath = $this->resolveAbsolutePath($relativePath);
        } catch (InvalidArgumentException) {
            return false;
        }

        return is_file($absolutePath);
    }

    private function initializeStorageRoot(string $storageRoot): string
    {
        $storageRoot = rtrim($storageRoot, '/\\');
        $resolvedRoot = realpath($storageRoot);

        if ($resolvedRoot !== false) {
            return $resolvedRoot;
        }

        if (! mkdir($storageRoot, self::DIRECTORY_MODE, true) && ! is_dir($storageRoot)) {
            throw new RuntimeException('Storage root does not exist and could not be created.');
        }

        $resolvedRoot = realpath($storageRoot);

        if ($resolvedRoot === false) {
            throw new RuntimeException('Storage root does not exist.');
        }

        return $resolvedRoot;
    }

    private function resolveAbsolutePath(string $relativePath): string
    {
        $this->assertSafeRelativePath($relativePath);

        $absolutePath = $this->resolvedRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));

        $this->assertPathStaysUnderRoot($absolutePath);

        if (file_exists($absolutePath)) {
            $resolvedPath = realpath($absolutePath);

            if ($resolvedPath === false) {
                throw new InvalidArgumentException('Storage path could not be resolved.');
            }

            $this->assertPathStaysUnderRoot($resolvedPath);

            return $resolvedPath;
        }

        return $absolutePath;
    }

    private function assertSafeRelativePath(string $relativePath): void
    {
        if ($relativePath === '') {
            throw new InvalidArgumentException('Storage path must not be empty.');
        }

        if (str_contains($relativePath, "\0")) {
            throw new InvalidArgumentException('Storage path must not contain null bytes.');
        }

        if (str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
            throw new InvalidArgumentException('Storage path must be relative.');
        }

        $normalized = str_replace('\\', '/', $relativePath);

        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            throw new InvalidArgumentException('Storage path must not contain parent directory segments.');
        }
    }

    private function assertPathStaysUnderRoot(string $absolutePath): void
    {
        $rootPrefix = $this->resolvedRoot . DIRECTORY_SEPARATOR;

        if ($absolutePath !== $this->resolvedRoot && ! str_starts_with($absolutePath, $rootPrefix)) {
            throw new InvalidArgumentException('Storage path escapes the configured root.');
        }
    }

    private function assertResolvedFileUnderRoot(string $absolutePath): void
    {
        $resolved = realpath($absolutePath);

        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException('Stored file could not be verified.');
        }

        $this->assertPathStaysUnderRoot($resolved);
    }

    private function ensureParentDirectory(string $absolutePath): void
    {
        $directory = dirname($absolutePath);

        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, self::DIRECTORY_MODE, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory.');
        }
    }

    /**
     * @param resource $stream
     */
    private function writeAtomically(string $absolutePath, $stream): void
    {
        $directory = dirname($absolutePath);
        $tempPath = $directory . DIRECTORY_SEPARATOR . self::TEMP_PREFIX . bin2hex(random_bytes(8));
        $destination = fopen($tempPath, 'wb');

        if ($destination === false) {
            throw new RuntimeException('Unable to open temp file for writing.');
        }

        try {
            if (stream_copy_to_stream($stream, $destination) === false) {
                throw new RuntimeException('Failed to write file to storage.');
            }
        } catch (Throwable $exception) {
            @unlink($tempPath);

            throw $exception;
        } finally {
            fclose($destination);
        }

        if (! rename($tempPath, $absolutePath)) {
            @unlink($tempPath);

            throw new RuntimeException('Failed to finalize storage file.');
        }
    }

    private function assertReadableStream(mixed $stream): void
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('Expected a readable stream resource.');
        }

        $mode = stream_get_meta_data($stream)['mode'] ?? '';
        $isReadable = str_contains($mode, '+') || str_starts_with($mode, 'r');

        if ($mode === '' || ! $isReadable) {
            throw new InvalidArgumentException('Stream is not opened for reading.');
        }
    }
}
