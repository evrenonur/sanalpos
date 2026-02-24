<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico\Model;

use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKISerializable;

/**
 * Iyzico ödeme kartı modeli.
 */
class IyzicoPaymentCard implements PKISerializable
{
    public ?string $cardHolderName = null;

    public ?string $cardNumber = null;

    public ?string $expireYear = null;

    public ?string $expireMonth = null;

    public ?string $cvc = null;

    public ?int $registerCard = null;

    public ?string $cardAlias = null;

    public ?string $cardToken = null;

    public ?string $cardUserKey = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->append('cardHolderName', $this->cardHolderName)
            ->append('cardNumber', $this->cardNumber)
            ->append('expireYear', $this->expireYear)
            ->append('expireMonth', $this->expireMonth)
            ->append('cvc', $this->cvc)
            ->append('registerCard', $this->registerCard)
            ->append('cardAlias', $this->cardAlias)
            ->append('cardToken', $this->cardToken)
            ->append('cardUserKey', $this->cardUserKey)
            ->getRequestString();
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }
}
