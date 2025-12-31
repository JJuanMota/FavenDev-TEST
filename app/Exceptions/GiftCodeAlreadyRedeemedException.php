<?php

namespace App\Exceptions;

use RuntimeException;

class GiftCodeAlreadyRedeemedException extends RuntimeException
{
    public function __construct(private array $record)
    {
        parent::__construct('Gift code already redeemed');
    }

    public function record(): array
    {
        return $this->record;
    }
}
