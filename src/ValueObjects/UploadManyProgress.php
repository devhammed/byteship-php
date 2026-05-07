<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class UploadManyProgress
{
    public function __construct(
        public int $index,
        public UploadInput $file,
        public int $loaded,
        public float $percent,
        public int $total,
    ) {}
}
