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
            new UploadedFile(
                $file['byteSize'],
                $file['etag'] ?? null,
                $file['filename'],
                $file['id'],
                $file['path'],
                FileStatus::from($file['status']),
                $file['url'] ?? null,
                Visibility::from($file['visibility']),
            ),
            new CompletedUploadSession(
                $upload['id'],
                UploadSessionStatus::from($upload['status']),
            ),
        );
    }
}
