<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class DeleteFileResponse
{
    public function __construct(
        public DeletedFile $file,
    ) {}
}
