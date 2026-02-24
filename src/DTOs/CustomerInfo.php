<?php

namespace EvrenOnur\SanalPos\DTOs;

use EvrenOnur\SanalPos\Enums\Country;

class CustomerInfo
{
    public function __construct(
        public string $name = '',
        public string $surname = '',
        public string $email_address = '',
        public string $phone_number = '',
        public string $tax_number = '',
        public string $tax_office = '',
        public ?Country $country = null,
        public string $city_name = '',
        public string $town_name = '',
        public string $address_description = '',
        public string $post_code = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            surname: $data['surname'] ?? '',
            email_address: $data['email_address'] ?? '',
            phone_number: $data['phone_number'] ?? '',
            tax_number: $data['tax_number'] ?? '',
            tax_office: $data['tax_office'] ?? '',
            country: isset($data['country']) ? (is_int($data['country']) ? Country::from($data['country']) : $data['country']) : null,
            city_name: $data['city_name'] ?? '',
            town_name: $data['town_name'] ?? '',
            address_description: $data['address_description'] ?? '',
            post_code: $data['post_code'] ?? '',
        );
    }
}
