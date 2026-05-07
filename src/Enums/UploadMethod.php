<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Enums;

enum UploadMethod: string
{
    case Auto = 'auto';

    case Single = 'single';

    case Multipart = 'multipart';
}
