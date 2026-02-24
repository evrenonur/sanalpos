<?php

namespace EvrenOnur\SanalPos\DTOs\Requests;

use EvrenOnur\SanalPos\Enums\Currency;

class CancelRequest
{
    public function __construct(
        public string $customer_ip_address = '',
        public string $order_number = '',
        public string $transaction_id = '',
        public ?Currency $currency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customer_ip_address: $data['customer_ip_address'] ?? '',
            order_number: $data['order_number'] ?? '',
            transaction_id: $data['transaction_id'] ?? '',
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->order_number)) {
            $errors[] = 'Sipariş numarası boş olamaz.';
        }

        return $errors;
    }
}
