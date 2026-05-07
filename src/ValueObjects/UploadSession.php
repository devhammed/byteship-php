<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use DateTimeImmutable;
use Devhammed\Byteship\Enums\UploadMethod;

final readonly class UploadSession
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $id,
        public string $fileId,
        public string $key,
        public array $headers,
        public UploadMethod $method,
        public string $url,
        public DateTimeImmutable $expiresAt,
    ) {}
}
