<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Unsettled = 'unsettled';
    case Settled = 'settled';
}
