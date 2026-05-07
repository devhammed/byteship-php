<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Enums;

enum UploadManyResultStatus: string
{
    case Fulfilled = 'fulfilled';

    case Rejected = 'rejected';
}
