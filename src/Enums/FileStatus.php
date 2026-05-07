<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Enums;

enum FileStatus: string
{
    case Pending = 'pending';

    case Uploading = 'uploading';

    case Ready = 'ready';

    case Failed = 'failed';

    case Deleted = 'deleted';
}
