<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class CreateUploadTokenResponse
{
    public function __construct(
        public UploadToken $uploadToken,
    ) {}
}
