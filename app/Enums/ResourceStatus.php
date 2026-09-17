<?php

namespace App\Enums;

enum ResourceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
