<?php

declare(strict_types=1);

$allowedMimeTypes = $_ENV['STORAGE_ALLOWED_MIME_TYPES']
    ?? 'image/jpeg,image/png,image/gif,image/webp,application/pdf';

return [
    'path' => $_ENV['STORAGE_PATH'] ?? '/var/www/html/storage',
    'max_upload_bytes' => (int) ($_ENV['STORAGE_MAX_UPLOAD_BYTES'] ?? 10485760),
    'allowed_mime_types' => array_values(array_filter(array_map(
        static fn (string $mimeType): string => trim($mimeType),
        explode(',', $allowedMimeTypes),
    ))),
    'image_max_dimension' => (int) ($_ENV['IMAGE_MAX_DIMENSION'] ?? 1920),
    'image_thumbnail_dimension' => (int) ($_ENV['IMAGE_THUMBNAIL_DIMENSION'] ?? 300),
    'image_webp_quality' => (int) ($_ENV['IMAGE_WEBP_QUALITY'] ?? 80),
];
