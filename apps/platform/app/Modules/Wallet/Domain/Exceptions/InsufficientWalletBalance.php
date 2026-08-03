<?php

namespace App\Modules\Wallet\Domain\Exceptions;

final class InsufficientWalletBalance extends WalletException
{
    public function __construct()
    {
        parent::__construct('Le montant disponible dans ce Wallet est insuffisant.');
    }
}
