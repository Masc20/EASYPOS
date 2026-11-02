<?php

namespace App\Support\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case QR_TRANSFER = 'qr_transfer';
    case VOUCHER = 'voucher';
    case CARD = 'card';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::QR_TRANSFER => 'GCash / Maya QR',
            self::VOUCHER => 'Voucher',
            self::CARD => 'Credit / Debit Card',
        };
    }

    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? ucwords(str_replace('_', ' ', $value));
    }

    /**
     * @return array<int, string>
     */
    public static function referenceRequiredValues(): array
    {
        return [
            self::CREDIT_CARD->value,
            self::DEBIT_CARD->value,
            self::QR_TRANSFER->value,
            self::CARD->value,
        ];
    }
}
