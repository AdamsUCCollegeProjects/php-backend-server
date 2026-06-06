<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use RuntimeException;

final class ImageProcessor
{
    private const STORED_MIME_TYPE = 'image/webp';

    /**
     * @param array{
     *     image_max_dimension: int,
     *     image_thumbnail_dimension: int,
     *     image_webp_quality: int,
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @return array{
     *     main_stream: resource,
     *     main_size: int,
     *     main_width: int,
     *     main_height: int,
     *     thumbnail_stream: resource,
     *     thumbnail_size: int,
     *     thumbnail_width: int,
     *     thumbnail_height: int,
     *     mime_type: string,
     * }
     */
    public function process(string $sourcePath): array
    {
        $sourceImage = $this->loadImage($sourcePath);
        $mainImage = $this->scaleToMaxDimension($sourceImage, $this->config['image_max_dimension']);
        $mainEncoded = $this->encodeWebP($mainImage);

        $thumbnailImage = $this->scaleToMaxDimension(
            $this->cloneImage($mainImage),
            $this->config['image_thumbnail_dimension'],
        );
        $thumbnailEncoded = $this->encodeWebP($thumbnailImage);

        imagedestroy($mainImage);
        imagedestroy($thumbnailImage);

        return [
            'main_stream' => $mainEncoded['stream'],
            'main_size' => $mainEncoded['size'],
            'main_width' => $mainEncoded['width'],
            'main_height' => $mainEncoded['height'],
            'thumbnail_stream' => $thumbnailEncoded['stream'],
            'thumbnail_size' => $thumbnailEncoded['size'],
            'thumbnail_width' => $thumbnailEncoded['width'],
            'thumbnail_height' => $thumbnailEncoded['height'],
            'mime_type' => self::STORED_MIME_TYPE,
        ];
    }

    private function loadImage(string $sourcePath): GdImage
    {
        $imageData = file_get_contents($sourcePath);

        if ($imageData === false) {
            throw new RuntimeException('Unable to read image file.');
        }

        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            throw new RuntimeException('Unable to decode image file.');
        }

        return $image;
    }

    private function cloneImage(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $clone = imagecreatetruecolor($width, $height);
        imagecopy($clone, $image, 0, 0, 0, 0, $width, $height);

        return $clone;
    }

    private function scaleToMaxDimension(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxDimension && $height <= $maxDimension) {
            return $image;
        }

        $scaleRatio = min($maxDimension / $width, $maxDimension / $height);
        $targetWidth = (int) round($width * $scaleRatio);
        $targetHeight = (int) round($height * $scaleRatio);
        $scaledImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $scaledImage,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );
        imagedestroy($image);

        return $scaledImage;
    }

    /**
     * @return array{stream: resource, size: int, width: int, height: int}
     */
    private function encodeWebP(GdImage $image): array
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create image output stream.');
        }

        $encoded = imagewebp($image, $stream, $this->config['image_webp_quality']);

        if ($encoded === false) {
            fclose($stream);
            throw new RuntimeException('Failed to encode image as WebP.');
        }

        rewind($stream);
        $fileStats = fstat($stream);

        return [
            'stream' => $stream,
            'size' => (int) ($fileStats['size'] ?? 0),
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }
}
