<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\UploadSessionStatus;
use Devhammed\Byteship\Enums\Visibility;

final readonly class CompleteUploadResponse
{
    public function __construct(
        public UploadedFile $file,
        public CompletedUploadSession $upload,
    ) {}

    public static function fromArray(array $data): self
    {
        $file = $data['file'];
        $upload = $data['upload'];

        return new CompleteUploadResponse(
            file: new UploadedFile(
                id: $file['id'],
                filename: $file['filename'],
                path: $file['path'],
                byteSize: $file['byteSize'],
                etag: $file['etag'] ?? null,
                status: FileStatus::from($file['status']),
                url: $file['url'] ?? null,
                visibility: Visibility::from($file['visibility']),
            ),
            upload: new CompletedUploadSession(
                id: $upload['id'],
                status: UploadSessionStatus::from($upload['status']),
            ),
        );
    }
}
