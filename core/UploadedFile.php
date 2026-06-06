<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class UploadedFile
{
    public function __construct(
        private readonly string $clientFilename,
        private readonly string $clientMimeType,
        private readonly string $tempPath,
        private readonly int $size,
    ) {
    }

    public function getClientFilename(): string
    {
        return $this->clientFilename;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getClientMimeType(): string
    {
        return $this->clientMimeType;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        $stream = fopen($this->tempPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open uploaded file stream.');
        }

        return $stream;
    }

    public function moveTo(string $path): void
    {
        if (! move_uploaded_file($this->tempPath, $path)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }
    }
}
