<?php

namespace App\Enums;

enum SchemeType: string
{
    case OpenEnded = 'open_ended';
    case CloseEnded = 'close_ended';
    case Interval = 'interval';
}
