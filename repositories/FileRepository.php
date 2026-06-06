<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FileRecord;
use PDO;
use RuntimeException;

final class FileRepository
{
    private const FILE_COLUMNS = 'f.id, f.original_filename, f.stored_filename, f.mime_type,
        f.file_size, f.storage_path, f.created_at, f.updated_at';
    private const THUMBNAIL_COLUMNS = 't.id AS thumbnail_id, t.file_id, t.mime_type AS thumbnail_mime_type,
        t.file_size AS thumbnail_file_size, t.storage_path AS thumbnail_storage_path,
        t.width AS thumbnail_width, t.height AS thumbnail_height,
        t.created_at AS thumbnail_created_at, t.updated_at AS thumbnail_updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?FileRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::FILE_COLUMNS . ', ' . self::THUMBNAIL_COLUMNS . '
             FROM files f
             LEFT JOIN file_thumbnails t ON t.file_id = f.id
             WHERE f.id = :id
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return FileRecord::fromRow($row);
    }

    public function exists(string $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM files WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array{
     *     id: string,
     *     original_filename: string,
     *     stored_filename: string,
     *     mime_type: string,
     *     file_size: int,
     *     storage_path: string,
     * } $fileData
     * @param array{
     *     mime_type: string,
     *     file_size: int,
     *     storage_path: string,
     *     width: int,
     *     height: int,
     * }|null $thumbnailData
     */
    public function create(array $fileData, ?array $thumbnailData = null): FileRecord
    {
        $this->pdo->beginTransaction();

        try {
            $this->insertFile($fileData);

            if ($thumbnailData !== null) {
                $this->insertThumbnail($fileData['id'], $thumbnailData);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        $fileRecord = $this->findById($fileData['id']);

        if ($fileRecord === null) {
            throw new RuntimeException('Failed to load file record after insert.');
        }

        return $fileRecord;
    }

    public function delete(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM files WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array{
     *     id: string,
     *     original_filename: string,
     *     stored_filename: string,
     *     mime_type: string,
     *     file_size: int,
     *     storage_path: string,
     * } $fileData
     */
    private function insertFile(array $fileData): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO files (id, original_filename, stored_filename, mime_type, file_size, storage_path)
             VALUES (:id, :original_filename, :stored_filename, :mime_type, :file_size, :storage_path)',
        );
        $statement->execute($fileData);
    }

    /**
     * @param array{
     *     mime_type: string,
     *     file_size: int,
     *     storage_path: string,
     *     width: int,
     *     height: int,
     * } $thumbnailData
     */
    private function insertThumbnail(string $fileId, array $thumbnailData): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO file_thumbnails (file_id, mime_type, file_size, storage_path, width, height)
             VALUES (:file_id, :mime_type, :file_size, :storage_path, :width, :height)',
        );
        $statement->execute(['file_id' => $fileId] + $thumbnailData);
    }
}
