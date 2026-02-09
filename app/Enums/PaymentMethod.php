<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Qris = 'qris';
    case EWallet = 'e_wallet';
    case Edc = 'edc';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Transfer Bank',
            self::Qris => 'QRIS',
            self::EWallet => 'E-Wallet',
            self::Edc => 'EDC / Kartu',
        };
    }
}
