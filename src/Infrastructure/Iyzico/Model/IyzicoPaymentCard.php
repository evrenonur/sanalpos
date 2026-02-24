<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Model;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKISerializable;

/**
 * Iyzico ödeme kartı modeli.
 */
class IyzicoPaymentCard implements PKISerializable
{
    public ?string $cardHolderName = null;

    public ?string $card_number = null;

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
            ->append('card_number', $this->card_number)
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
