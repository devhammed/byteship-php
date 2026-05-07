<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class CompleteUploadResponse
{
    public function __construct(
        public UploadedFile $file,
        public CompletedUploadSession $upload,
    ) {}
}
