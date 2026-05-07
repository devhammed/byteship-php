<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\FileStatus;

final readonly class DeletedFile
{
    public function __construct(
        public string $id,
        public ?string $path,
        public FileStatus $status,
    ) {}
}
