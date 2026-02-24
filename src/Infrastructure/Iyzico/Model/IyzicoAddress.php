<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Model;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKISerializable;

/**
 * Iyzico adres modeli.
 */
class IyzicoAddress implements PKISerializable
{
    public ?string $address = null;

    public ?string $zipCode = null;

    public ?string $contactName = null;

    public ?string $city = null;

    public ?string $country = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->append('address', $this->address)
            ->append('zipCode', $this->zipCode)
            ->append('contactName', $this->contactName)
            ->append('city', $this->city)
            ->append('country', $this->country)
            ->getRequestString();
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }
}
