<?php

declare(strict_types=1);

namespace App\Storage\Providers;

interface StorageProviderInterface
{
    /**
     * @param resource $stream
     */
    public function storeStream(string $relativePath, $stream): void;

    /**
     * @return resource
     */
    public function openReadStream(string $relativePath);

    public function delete(string $relativePath): void;

    public function exists(string $relativePath): bool;
}
