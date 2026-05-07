<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use DateTimeImmutable;
use Devhammed\Byteship\Enums\UploadMethod;

final readonly class UploadSession
{
    /** @param array<string, string> $headers */
    public function __construct(
        public DateTimeImmutable $expiresAt,
        public string $fileId,
        public array $headers,
        public string $id,
        public string $key,
        public UploadMethod $method,
        public string $url,
    ) {}
}
