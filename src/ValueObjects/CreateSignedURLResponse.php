<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

final readonly class CreateSignedURLResponse
{
    public function __construct(
        public SignedURL $signedUrl,
    ) {}
}
