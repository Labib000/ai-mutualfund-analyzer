<?php

namespace App\Enums;

enum XirrStatus: string
{
    /** Investments span at least a year. */
    case Ok = 'ok';

    /** Between 30 days and a year: annualised from a short period. */
    case ShortPeriod = 'short_period';

    /** The first investment is less than 30 days old; XIRR isn't shown. */
    case TooRecent = 'too_recent';

    /** No rate solves the cash flows (for example, nothing invested yet). */
    case NotMeaningful = 'not_meaningful';
}
