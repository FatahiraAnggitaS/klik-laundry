<?php

namespace App\Enums;

enum PiiAccessEvent: string
{
    case Granted = 'granted';
    case Accessed = 'accessed';
    case Denied = 'denied';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
