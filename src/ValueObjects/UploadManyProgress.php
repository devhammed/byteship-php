<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class UploadManyProgress
{
    public function __construct(
        public UploadInput $file,
        public int $index,
        public int $loaded,
        public float $percent,
        public int $total,
    ) {}
}
