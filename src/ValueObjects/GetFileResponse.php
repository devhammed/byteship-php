<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class GetFileResponse
{
    public function __construct(
        public File $file,
    ) {}
}
