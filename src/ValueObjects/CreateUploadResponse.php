<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use DateTimeImmutable;
use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\UploadMethod;

final readonly class CreateUploadResponse
{
    public function __construct(
        public PendingFile $file,
        public UploadSession $upload,
    ) {}

    public static function fromArray(array $data): self
    {
        $file = $data['file'];
        $upload = $data['upload'];

        return new CreateUploadResponse(
            file: new PendingFile(
                id: $file['id'],
                path: $file['path'],
                status: FileStatus::from($file['status']),
                url: $file['url'] ?? null,
            ),
            upload: new UploadSession(
                id: $upload['id'],
                fileId: $upload['fileId'],
                key: $upload['key'],
                headers: $upload['headers'] ?? [],
                method: UploadMethod::from($upload['method']),
                url: $upload['url'],
                expiresAt: new DateTimeImmutable($upload['expiresAt']),
            ),
        );
    }
}
