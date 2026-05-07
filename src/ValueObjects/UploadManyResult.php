<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\UploadManyResultStatus;
use Throwable;

final readonly class UploadManyResult
{
    public function __construct(
        public UploadInput $input,
        public UploadManyResultStatus $status,
        public ?Throwable $error = null,
        public ?UploadedFile $file = null,
    ) {}
}
