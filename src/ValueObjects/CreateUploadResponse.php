<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class CreateUploadResponse
{
    public function __construct(
        public PendingFile $file,
        public UploadSession $upload,
    ) {}
}
