<?php

declare(strict_types=1);

namespace Devhammed\Byteship\Enums;

enum Visibility: string
{
    case Private = 'private';

    case Public = 'public';
}
