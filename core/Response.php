<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Response
{
    /**
     * @param array<string, mixed>|null $data
     * @param resource|null $stream
     */
    private function __construct(
        private readonly ?array $data,
        private readonly int $statusCode,
        private $stream,
        private readonly ?string $mimeType,
        private readonly ?string $downloadFilename,
        private readonly bool $inline,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode, null, null, null, true);
    }

    /**
     * @param array<string, string> $details
     */
    public static function error(string $message, int $statusCode, array $details = []): self
    {
        $payload = ['error' => $message];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return new self($payload, $statusCode, null, null, null, true);
    }

    /**
     * @param resource $stream
     */
    public static function download(
        $stream,
        string $mimeType,
        string $downloadFilename,
        int $statusCode = 200,
        bool $inline = true,
    ): self {
        return new self(null, $statusCode, $stream, $mimeType, $downloadFilename, $inline);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        if ($this->stream !== null) {
            $this->sendStream();

            return;
        }

        header('Content-Type: application/json');
        echo json_encode($this->data ?? [], JSON_UNESCAPED_SLASHES);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    private function sendStream(): void
    {
        if ($this->mimeType === null || $this->downloadFilename === null) {
            throw new RuntimeException('Stream response is missing download metadata.');
        }

        header('Content-Type: ' . $this->mimeType);

        $stat = fstat($this->stream);

        if ($stat !== false && isset($stat['size'])) {
            header('Content-Length: ' . (string) $stat['size']);
        }

        $disposition = $this->inline ? 'inline' : 'attachment';
        $safeFilename = str_replace('"', '', $this->downloadFilename);
        header(sprintf('%s: %s; filename="%s"', 'Content-Disposition', $disposition, $safeFilename));

        fpassthru($this->stream);
        fclose($this->stream);
    }
}
