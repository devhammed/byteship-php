<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use DateTimeImmutable;
use Devhammed\Byteship\Enums\FileStatus;
use Devhammed\Byteship\Enums\Visibility;

final readonly class File
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int $byteSize,
        public string $contentType,
        public DateTimeImmutable $createdAt,
        public string $filename,
        public string $id,
        public array $metadata,
        public string $path,
        public FileStatus $status,
        public ?string $url,
        public Visibility $visibility,
    ) {}
}
