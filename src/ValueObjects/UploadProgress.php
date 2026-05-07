<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class UploadProgress
{
    public function __construct(
        public int $loaded,
        public float $percent,
        public int $total,
    ) {}
}
