<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\UploadSessionStatus;

final readonly class CompletedUploadSession
{
    public function __construct(
        public string $id,
        public UploadSessionStatus $status,
    ) {}
}
