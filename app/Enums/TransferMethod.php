<?php

namespace App\Enums;

enum TransferMethod: string
{
    case BankTransfer = 'bank_transfer';
    case EWallet = 'e_wallet';
    case Cash = 'cash';
    case NoTransferRequired = 'no_transfer_required';
}
