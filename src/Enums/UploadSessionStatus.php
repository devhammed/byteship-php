<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Enums;

enum UploadSessionStatus: string
{
    case Pending = 'pending';

    case Completed = 'completed';

    case Aborted = 'aborted';

    case Expired = 'expired';
}
