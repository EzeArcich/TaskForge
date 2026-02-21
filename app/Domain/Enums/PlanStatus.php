<?php

namespace App\Domain\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Publishing = 'publishing';
    case Published = 'published';
    case NeedsUpdate = 'needs_update';
}
