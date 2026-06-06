<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @param array<string, string> $query
     * @param array<string, UploadedFile> $uploadedFiles
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers,
        private readonly array $body,
        private readonly array $query = [],
        private readonly array $uploadedFiles = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = self::parsePath($uri);
        $headers = self::parseHeaders();
        $isMultipart = self::isMultipartRequest($headers);
        $uploadedFiles = $isMultipart ? self::parseUploadedFiles() : [];
        $body = $isMultipart ? self::parseFormBody() : self::parseJsonBody($headers);
        $query = self::parseQuery($uri);

        return new self($method, $path, $headers, $body, $query, $uploadedFiles);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);

        return $this->headers[$normalized] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    public function getBodyValue(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getQueryValue(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getUploadedFile(string $field): ?UploadedFile
    {
        return $this->uploadedFiles[$field] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private static function parsePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * @return array<string, string>
     */
    private static function parseQuery(string $uri): array
    {
        $queryString = parse_url($uri, PHP_URL_QUERY);

        if (! is_string($queryString) || $queryString === '') {
            return [];
        }

        parse_str($queryString, $parsed);

        if (! is_array($parsed)) {
            return [];
        }

        $query = [];

        foreach ($parsed as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $query[$key] = (string) $value;
            }
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (! is_string($value) || ! str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }

        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function isMultipartRequest(array $headers): bool
    {
        $contentType = $headers['content-type'] ?? '';

        return str_contains($contentType, 'multipart/form-data');
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseJsonBody(array $headers): array
    {
        $contentType = $headers['content-type'] ?? '';

        if (! str_contains($contentType, 'application/json')) {
            return [];
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseFormBody(): array
    {
        $body = [];

        foreach ($_POST as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_string($value) || is_numeric($value) || is_bool($value)) {
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /**
     * @return array<string, UploadedFile>
     */
    private static function parseUploadedFiles(): array
    {
        if ($_FILES === []) {
            return [];
        }

        $uploadedFiles = [];

        foreach ($_FILES as $fieldName => $fileData) {
            if (! is_string($fieldName) || ! is_array($fileData)) {
                continue;
            }

            $uploadedFile = self::createUploadedFile($fileData);

            if ($uploadedFile !== null) {
                $uploadedFiles[$fieldName] = $uploadedFile;
            }
        }

        return $uploadedFiles;
    }

    /**
     * @param array<string, mixed> $fileData
     */
    private static function createUploadedFile(array $fileData): ?UploadedFile
    {
        $error = $fileData['error'] ?? UPLOAD_ERR_NO_FILE;
        $tempPath = $fileData['tmp_name'] ?? '';
        $clientFilename = $fileData['name'] ?? '';
        $clientMimeType = $fileData['type'] ?? '';
        $size = $fileData['size'] ?? 0;

        if ($error !== UPLOAD_ERR_OK) {
            return null;
        }

        if (! is_string($tempPath) || $tempPath === '' || ! is_uploaded_file($tempPath)) {
            return null;
        }

        if (! is_string($clientFilename) || $clientFilename === '') {
            return null;
        }

        if (! is_string($clientMimeType)) {
            $clientMimeType = 'application/octet-stream';
        }

        if (! is_int($size)) {
            $size = (int) $size;
        }

        return new UploadedFile($clientFilename, $clientMimeType, $tempPath, $size);
    }
}
