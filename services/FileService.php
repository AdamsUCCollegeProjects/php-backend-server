<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\UploadedFile;
use App\Core\UuidGenerator;
use App\Models\FileRecord;
use App\Repositories\FileRepository;
use App\Storage\Providers\StorageProviderInterface;
use finfo;
use Monolog\Logger;
use RuntimeException;

final class FileService
{
    private const MAX_FILENAME_LENGTH = 255;
    private const STORED_IMAGE_EXTENSION = 'webp';
    private const FILE_URL_PREFIX = '/api/files/';
    private const THUMBNAIL_URL_SUFFIX = '/thumbnail';
    private const ERROR_NOT_FOUND = 'File not found';
    private const ERROR_NO_THUMBNAIL = 'Thumbnail not available';
    private const ERROR_FILE_REQUIRED = 'File is required';
    private const ERROR_FILE_TOO_LARGE = 'File exceeds maximum upload size';
    private const ERROR_UNSUPPORTED_MEDIA = 'Unsupported media type';
    private const ERROR_INVALID_IMAGE = 'Invalid image file';
    private const ERROR_UPLOAD_FAILED = 'File upload failed';

    /** @var array<string, string> */
    private const MIME_EXTENSION_MAP = [
        'application/pdf' => 'pdf',
    ];

    /**
     * @param array{
     *     max_upload_bytes: int,
     *     allowed_mime_types: list<string>,
     * } $storageConfig
     */
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly StorageProviderInterface $storageProvider,
        private readonly ImageProcessor $imageProcessor,
        private readonly array $storageConfig,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    public function upload(?UploadedFile $uploadedFile): array
    {
        if ($uploadedFile === null) {
            return $this->failure(400, self::ERROR_FILE_REQUIRED);
        }

        $sizeFailure = $this->validateUploadSize($uploadedFile);

        if ($sizeFailure !== null) {
            return $sizeFailure;
        }

        $mimeType = $this->detectMimeType($uploadedFile->getTempPath());
        $mimeFailure = $this->validateMimeType($mimeType, $uploadedFile->getTempPath());

        if ($mimeFailure !== null) {
            return $mimeFailure;
        }

        $originalFilename = $this->sanitizeFilename($uploadedFile->getClientFilename());
        $fileId = UuidGenerator::generate();

        try {
            if ($this->isImageMimeType($mimeType)) {
                return $this->uploadImage($uploadedFile, $fileId, $originalFilename);
            }

            return $this->uploadBinary($uploadedFile, $fileId, $originalFilename, $mimeType);
        } catch (RuntimeException $exception) {
            $this->logger->error('File upload failed', [
                'file_id' => $fileId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure(500, self::ERROR_UPLOAD_FAILED);
        }
    }

    /**
     * @return array{
     *     ok: true,
     *     stream: resource,
     *     mime_type: string,
     *     filename: string,
     *     file_size: int,
     * }|array{ok: false, status: int, error: string}
     */
    public function openForDownload(string $id, bool $thumbnail = false): array
    {
        $fileRecord = $this->fileRepository->findById($id);

        if ($fileRecord === null) {
            return $this->failure(404, self::ERROR_NOT_FOUND);
        }

        if ($thumbnail) {
            return $this->openThumbnailDownload($fileRecord);
        }

        return $this->openMainDownload($fileRecord);
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string}
     */
    public function delete(string $id): array
    {
        $fileRecord = $this->fileRepository->findById($id);

        if ($fileRecord === null) {
            return $this->failure(404, self::ERROR_NOT_FOUND);
        }

        $this->deleteStoredFiles($fileRecord);
        $this->fileRepository->delete($id);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    private function uploadImage(
        UploadedFile $uploadedFile,
        string $fileId,
        string $originalFilename,
    ): array {
        $processed = $this->imageProcessor->process($uploadedFile->getTempPath());
        $storedFilename = $fileId . '.' . self::STORED_IMAGE_EXTENSION;
        $mainPath = $this->buildStoragePath($fileId, self::STORED_IMAGE_EXTENSION);
        $thumbnailPath = $this->buildThumbnailStoragePath($fileId);

        $this->storageProvider->storeStream($mainPath, $processed['main_stream']);
        $this->storageProvider->storeStream($thumbnailPath, $processed['thumbnail_stream']);
        $this->closeStream($processed['main_stream']);
        $this->closeStream($processed['thumbnail_stream']);

        $fileRecord = $this->fileRepository->create(
            [
                'id' => $fileId,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'mime_type' => $processed['mime_type'],
                'file_size' => $processed['main_size'],
                'storage_path' => $mainPath,
            ],
            [
                'mime_type' => $processed['mime_type'],
                'file_size' => $processed['thumbnail_size'],
                'storage_path' => $thumbnailPath,
                'width' => $processed['thumbnail_width'],
                'height' => $processed['thumbnail_height'],
            ],
        );

        return ['ok' => true, 'data' => $this->formatFileRecord($fileRecord)];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    private function uploadBinary(
        UploadedFile $uploadedFile,
        string $fileId,
        string $originalFilename,
        string $mimeType,
    ): array {
        $extension = $this->resolveExtension($mimeType);
        $storedFilename = $fileId . '.' . $extension;
        $storagePath = $this->buildStoragePath($fileId, $extension);
        $stream = $uploadedFile->getStream();

        try {
            $this->storageProvider->storeStream($storagePath, $stream);
        } finally {
            $this->closeStream($stream);
        }

        $fileRecord = $this->fileRepository->create([
            'id' => $fileId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'file_size' => $uploadedFile->getSize(),
            'storage_path' => $storagePath,
        ]);

        return ['ok' => true, 'data' => $this->formatFileRecord($fileRecord)];
    }

    /**
     * @return array{
     *     ok: true,
     *     stream: resource,
     *     mime_type: string,
     *     filename: string,
     *     file_size: int,
     * }|array{ok: false, status: int, error: string}
     */
    private function openMainDownload(FileRecord $fileRecord): array
    {
        $stream = $this->storageProvider->openReadStream($fileRecord->storagePath);

        return [
            'ok' => true,
            'stream' => $stream,
            'mime_type' => $fileRecord->mimeType,
            'filename' => $fileRecord->originalFilename,
            'file_size' => $fileRecord->fileSize,
        ];
    }

    /**
     * @return array{
     *     ok: true,
     *     stream: resource,
     *     mime_type: string,
     *     filename: string,
     *     file_size: int,
     * }|array{ok: false, status: int, error: string}
     */
    private function openThumbnailDownload(FileRecord $fileRecord): array
    {
        $thumbnail = $fileRecord->thumbnail;

        if ($thumbnail === null) {
            return $this->failure(404, self::ERROR_NO_THUMBNAIL);
        }

        $stream = $this->storageProvider->openReadStream($thumbnail->storagePath);

        return [
            'ok' => true,
            'stream' => $stream,
            'mime_type' => $thumbnail->mimeType,
            'filename' => $this->buildThumbnailFilename($fileRecord->originalFilename),
            'file_size' => $thumbnail->fileSize,
        ];
    }

    private function deleteStoredFiles(FileRecord $fileRecord): void
    {
        $this->storageProvider->delete($fileRecord->storagePath);

        if ($fileRecord->thumbnail !== null) {
            $this->storageProvider->delete($fileRecord->thumbnail->storagePath);
        }
    }

    /**
     * @return array{ok: false, status: int, error: string}|null
     */
    private function validateUploadSize(UploadedFile $uploadedFile): ?array
    {
        if ($uploadedFile->getSize() > $this->storageConfig['max_upload_bytes']) {
            return $this->failure(413, self::ERROR_FILE_TOO_LARGE);
        }

        return null;
    }

    /**
     * @return array{ok: false, status: int, error: string}|null
     */
    private function validateMimeType(string $mimeType, string $tempPath): ?array
    {
        if (! in_array($mimeType, $this->storageConfig['allowed_mime_types'], true)) {
            return $this->failure(415, self::ERROR_UNSUPPORTED_MEDIA);
        }

        if ($this->isImageMimeType($mimeType) && getimagesize($tempPath) === false) {
            return $this->failure(415, self::ERROR_INVALID_IMAGE);
        }

        return null;
    }

    private function detectMimeType(string $tempPath): string
    {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($tempPath);

        if ($mimeType === false) {
            throw new RuntimeException('Unable to detect file MIME type.');
        }

        return $mimeType;
    }

    private function sanitizeFilename(string $filename): string
    {
        $sanitized = basename(str_replace("\0", '', $filename));
        $sanitized = preg_replace('/[\x00-\x1f\x7f]/', '', $sanitized) ?? '';

        if ($sanitized === '') {
            return 'upload';
        }

        if (strlen($sanitized) > self::MAX_FILENAME_LENGTH) {
            return substr($sanitized, 0, self::MAX_FILENAME_LENGTH);
        }

        return $sanitized;
    }

    private function isImageMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    private function buildStoragePath(string $fileId, string $extension): string
    {
        $shardOne = substr($fileId, 0, 2);
        $shardTwo = substr($fileId, 2, 2);

        return $shardOne . '/' . $shardTwo . '/' . $fileId . '.' . $extension;
    }

    private function buildThumbnailStoragePath(string $fileId): string
    {
        return $this->buildStoragePath($fileId, 'thumb.' . self::STORED_IMAGE_EXTENSION);
    }

    private function resolveExtension(string $mimeType): string
    {
        return self::MIME_EXTENSION_MAP[$mimeType]
            ?? throw new RuntimeException('No file extension mapping for MIME type.');
    }

    private function buildThumbnailFilename(string $originalFilename): string
    {
        $pathInfo = pathinfo($originalFilename);
        $basename = $pathInfo['filename'] !== '' ? $pathInfo['filename'] : 'upload';

        return $basename . '-thumbnail.webp';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFileRecord(FileRecord $fileRecord): array
    {
        $payload = [
            'id' => $fileRecord->id,
            'original_filename' => $fileRecord->originalFilename,
            'stored_filename' => $fileRecord->storedFilename,
            'mime_type' => $fileRecord->mimeType,
            'file_size' => $fileRecord->fileSize,
            'storage_path' => $fileRecord->storagePath,
            'url' => self::FILE_URL_PREFIX . $fileRecord->id,
            'created_at' => $fileRecord->createdAt,
        ];

        if ($fileRecord->thumbnail === null) {
            return $payload;
        }

        $payload['thumbnail'] = [
            'mime_type' => $fileRecord->thumbnail->mimeType,
            'file_size' => $fileRecord->thumbnail->fileSize,
            'width' => $fileRecord->thumbnail->width,
            'height' => $fileRecord->thumbnail->height,
            'url' => self::FILE_URL_PREFIX . $fileRecord->id . self::THUMBNAIL_URL_SUFFIX,
        ];

        return $payload;
    }

    /**
     * @return array{ok: false, status: int, error: string}
     */
    private function failure(int $status, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error];
    }

    /**
     * @param resource $stream
     */
    private function closeStream($stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
