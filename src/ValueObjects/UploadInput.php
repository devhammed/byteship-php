<?php

declare(strict_types=1);

namespace Devhammed\Byteship\ValueObjects;

use Devhammed\Byteship\Enums\Visibility;

final readonly class UploadInput
{
    /**
     * @param  resource|string  $file
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public mixed $file,
        public ?string $filename = null,
        public ?string $folder = null,
        public ?string $path = null,
        public ?string $contentType = null,
        public ?int $byteSize = null,
        public ?string $checksumSha256 = null,
        public ?array $metadata = null,
        public ?Visibility $visibility = null,
    ) {}
}
