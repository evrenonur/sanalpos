<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Model;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKISerializable;

/**
 * Iyzico alıcı modeli.
 */
class IyzicoBuyer implements PKISerializable
{
    public ?string $id = null;

    public ?string $name = null;

    public ?string $surname = null;

    public ?string $identityNumber = null;

    public ?string $email = null;

    public ?string $gsmNumber = null;

    public ?string $registrationDate = null;

    public ?string $lastLoginDate = null;

    public ?string $registrationAddress = null;

    public ?string $city = null;

    public ?string $country = null;

    public ?string $zipCode = null;

    public ?string $ip = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->append('id', $this->id)
            ->append('name', $this->name)
            ->append('surname', $this->surname)
            ->append('identityNumber', $this->identityNumber)
            ->append('email', $this->email)
            ->append('gsmNumber', $this->gsmNumber)
            ->append('registrationDate', $this->registrationDate)
            ->append('lastLoginDate', $this->lastLoginDate)
            ->append('registrationAddress', $this->registrationAddress)
            ->append('city', $this->city)
            ->append('country', $this->country)
            ->append('zipCode', $this->zipCode)
            ->append('ip', $this->ip)
            ->getRequestString();
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }
}
