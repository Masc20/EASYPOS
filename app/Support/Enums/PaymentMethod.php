<?php

namespace App\Support\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case QR_TRANSFER = 'qr_transfer';
    case CARD = 'card';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash Payment',
            self::QR_TRANSFER => 'GCash / Maya QR',
            self::CARD => 'Credit / Debit Card',
        };
    }
}
