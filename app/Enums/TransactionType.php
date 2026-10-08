<?php

namespace App\Enums;

enum TransactionType: string
{
    case Purchase = 'purchase';
    case SipInstallment = 'sip_installment';
    case Redemption = 'redemption';

    /**
     * Whether this transaction adds units to the holding.
     */
    public function addsUnits(): bool
    {
        return $this !== self::Redemption;
    }
}
