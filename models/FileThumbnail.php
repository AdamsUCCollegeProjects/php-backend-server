<?php

declare(strict_types=1);

namespace App\Models;

final class FileThumbnail
{
    public function __construct(
        public readonly int $id,
        public readonly string $fileId,
        public readonly string $mimeType,
        public readonly int $fileSize,
        public readonly string $storagePath,
        public readonly int $width,
        public readonly int $height,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['thumbnail_id'],
            fileId: (string) $row['file_id'],
            mimeType: (string) $row['thumbnail_mime_type'],
            fileSize: (int) $row['thumbnail_file_size'],
            storagePath: (string) $row['thumbnail_storage_path'],
            width: (int) $row['thumbnail_width'],
            height: (int) $row['thumbnail_height'],
            createdAt: (string) $row['thumbnail_created_at'],
            updatedAt: (string) $row['thumbnail_updated_at'],
        );
    }
}
