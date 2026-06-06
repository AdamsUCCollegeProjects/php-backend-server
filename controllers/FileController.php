<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\FileService;

final class FileController
{
    private const UPLOAD_FIELD = 'file';
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly FileService $fileService)
    {
    }

    public function store(Request $request): Response
    {
        $uploadedFile = $request->getUploadedFile(self::UPLOAD_FIELD);
        $result = $this->fileService->upload($uploadedFile);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json($result['data'], 201);
    }

    public function show(Request $request): Response
    {
        return $this->streamFile($request, thumbnail: false);
    }

    public function showThumbnail(Request $request): Response
    {
        return $this->streamFile($request, thumbnail: true);
    }

    public function destroy(Request $request): Response
    {
        $fileId = $this->extractFileId($request);

        if ($fileId === null) {
            return Response::error('Invalid file id', 400);
        }

        $result = $this->fileService->delete($fileId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['message' => 'File deleted']);
    }

    private function streamFile(Request $request, bool $thumbnail): Response
    {
        $fileId = $this->extractFileId($request);

        if ($fileId === null) {
            return Response::error('Invalid file id', 400);
        }

        $result = $this->fileService->openForDownload($fileId, $thumbnail);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        $inline = $request->getQueryValue('download') !== '1';

        return Response::download(
            $result['stream'],
            $result['mime_type'],
            $result['filename'],
            200,
            $inline,
        );
    }

    private function extractFileId(Request $request): ?string
    {
        $params = $request->getAttribute(Router::ATTRIBUTE_ROUTE_PARAMS, []);

        if (! is_array($params) || ! isset($params['id'])) {
            return null;
        }

        $id = $params['id'];

        if (! is_string($id) || ! preg_match(self::UUID_PATTERN, $id)) {
            return null;
        }

        return $id;
    }
}
