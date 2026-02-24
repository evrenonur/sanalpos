<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Currency;

class RefundRequest
{
    public function __construct(
        public string $customerIPAddress = '',
        public string $orderNumber = '',
        public string $transactionId = '',
        public float $refundAmount = 0,
        public ?Currency $currency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerIPAddress: $data['customerIPAddress'] ?? '',
            orderNumber: $data['orderNumber'] ?? '',
            transactionId: $data['transactionId'] ?? '',
            refundAmount: (float) ($data['refundAmount'] ?? 0),
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->orderNumber)) {
            $errors[] = 'Sipariş numarası boş olamaz.';
        }
        if ($this->refundAmount <= 0) {
            $errors[] = 'İade tutarı sıfırdan büyük olmalıdır.';
        }

        return $errors;
    }
}
