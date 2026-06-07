<?php

declare(strict_types=1);

namespace App\Storage\Providers;

use InvalidArgumentException;
use RuntimeException;

final class LocalStorageProvider implements StorageProviderInterface
{
    public function __construct(
        private readonly string $storageRoot,
    ) {
    }

    /**
     * @param resource $stream
     */
    public function storeStream(string $relativePath, $stream): void
    {
        $absolutePath = $this->resolveAbsolutePath($relativePath);
        $this->ensureParentDirectory($absolutePath);

        $destination = fopen($absolutePath, 'wb');

        if ($destination === false) {
            throw new RuntimeException('Unable to open storage destination for writing.');
        }

        try {
            $copied = stream_copy_to_stream($stream, $destination);

            if ($copied === false) {
                throw new RuntimeException('Failed to write file to storage.');
            }
        } finally {
            fclose($destination);
        }
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

        if (! file_exists($absolutePath)) {
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

    private function resolveAbsolutePath(string $relativePath): string
    {
        $this->assertSafeRelativePath($relativePath);

        $resolvedRoot = $this->resolveStorageRoot();
        $absolutePath = $resolvedRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));

        if (! str_starts_with($absolutePath, $resolvedRoot . DIRECTORY_SEPARATOR)
            && $absolutePath !== $resolvedRoot) {
            throw new InvalidArgumentException('Storage path escapes the configured root.');
        }

        if (file_exists($absolutePath)) {
            $resolvedPath = realpath($absolutePath);

            if ($resolvedPath === false
                || (! str_starts_with($resolvedPath, $resolvedRoot . DIRECTORY_SEPARATOR)
                    && $resolvedPath !== $resolvedRoot)) {
                throw new InvalidArgumentException('Storage path escapes the configured root.');
            }

            return $resolvedPath;
        }

        return $absolutePath;
    }

    private function resolveStorageRoot(): string
    {
        $storageRoot = rtrim($this->storageRoot, '/\\');
        $resolvedRoot = realpath($storageRoot);

        if ($resolvedRoot !== false) {
            return $resolvedRoot;
        }

        if (! mkdir($storageRoot, 0755, true) && ! is_dir($storageRoot)) {
            throw new RuntimeException('Storage root does not exist.');
        }

        $resolvedRoot = realpath($storageRoot);

        if ($resolvedRoot === false) {
            throw new RuntimeException('Storage root does not exist.');
        }

        return $resolvedRoot;
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

        if (preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
            throw new InvalidArgumentException('Storage path must not contain parent directory segments.');
        }
    }

    private function ensureParentDirectory(string $absolutePath): void
    {
        $directory = dirname($absolutePath);

        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory.');
        }
    }
}
