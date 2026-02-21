<?php

namespace App\Domain\Enums;

enum ValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case NeedsInput = 'needs_input';
}
