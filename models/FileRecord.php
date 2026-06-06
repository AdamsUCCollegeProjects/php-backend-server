<?php

declare(strict_types=1);

namespace App\Models;

final class FileRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalFilename,
        public readonly string $storedFilename,
        public readonly string $mimeType,
        public readonly int $fileSize,
        public readonly string $storagePath,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?FileThumbnail $thumbnail,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $thumbnail = isset($row['thumbnail_id']) && $row['thumbnail_id'] !== null
            ? FileThumbnail::fromRow($row)
            : null;

        return new self(
            id: (string) $row['id'],
            originalFilename: (string) $row['original_filename'],
            storedFilename: (string) $row['stored_filename'],
            mimeType: (string) $row['mime_type'],
            fileSize: (int) $row['file_size'],
            storagePath: (string) $row['storage_path'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
            thumbnail: $thumbnail,
        );
    }
}
