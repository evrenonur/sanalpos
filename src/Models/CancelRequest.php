<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Currency;

class CancelRequest
{
    public function __construct(
        public string $customerIPAddress = '',
        public string $orderNumber = '',
        public string $transactionId = '',
        public ?Currency $currency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerIPAddress: $data['customerIPAddress'] ?? '',
            orderNumber: $data['orderNumber'] ?? '',
            transactionId: $data['transactionId'] ?? '',
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->orderNumber)) {
            $errors[] = 'Sipariş numarası boş olamaz.';
        }

        return $errors;
    }
}
