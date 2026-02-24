<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Country;

class CustomerInfo
{
    public function __construct(
        public string $name = '',
        public string $surname = '',
        public string $emailAddress = '',
        public string $phoneNumber = '',
        public string $taxNumber = '',
        public string $taxOffice = '',
        public ?Country $country = null,
        public string $cityName = '',
        public string $townName = '',
        public string $addressDesc = '',
        public string $postCode = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            surname: $data['surname'] ?? '',
            emailAddress: $data['emailAddress'] ?? '',
            phoneNumber: $data['phoneNumber'] ?? '',
            taxNumber: $data['taxNumber'] ?? '',
            taxOffice: $data['taxOffice'] ?? '',
            country: isset($data['country']) ? (is_int($data['country']) ? Country::from($data['country']) : $data['country']) : null,
            cityName: $data['cityName'] ?? '',
            townName: $data['townName'] ?? '',
            addressDesc: $data['addressDesc'] ?? '',
            postCode: $data['postCode'] ?? '',
        );
    }
}
