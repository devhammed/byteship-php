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
            new PendingFile(
                $file['id'],
                $file['path'],
                FileStatus::from($file['status']),
                $file['url'] ?? null,
            ),
            new UploadSession(
                new DateTimeImmutable($upload['expiresAt']),
                $upload['fileId'],
                $upload['headers'] ?? [],
                $upload['id'],
                $upload['key'],
                UploadMethod::from($upload['method']),
                $upload['url'],
            ),
        );
    }
}
