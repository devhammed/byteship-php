<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use DateTimeImmutable;

final readonly class SignedURL
{
    public function __construct(
        public DateTimeImmutable $expiresAt,
        public string $fileId,
        public ?string $path,
        public string $url,
    ) {}
}
