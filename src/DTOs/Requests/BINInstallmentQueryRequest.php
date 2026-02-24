<?php

namespace EvrenOnur\SanalPos\DTOs\Requests;

use EvrenOnur\SanalPos\Enums\Currency;

class BINInstallmentQueryRequest
{
    public function __construct(
        public string $BIN = '',
        public float $amount = 0,
        public ?Currency $currency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            BIN: $data['BIN'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->BIN) || strlen($this->BIN) < 6 || strlen($this->BIN) > 8) {
            $errors[] = 'BIN 6-8 karakter arasında olmalıdır.';
        }

        return $errors;
    }
}
