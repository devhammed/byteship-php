<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\Visibility;

final readonly class UploadedFile
{
    public function __construct(
        public int $byteSize,
        public ?string $etag,
        public string $filename,
        public string $id,
        public string $path,
        public FileStatus $status,
        public ?string $url,
        public Visibility $visibility,
    ) {}
}
