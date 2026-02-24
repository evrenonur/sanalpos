<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Currency;

class Sale3DResponseRequest
{
    public function __construct(
        public ?array $responseArray = null,
        public ?Currency $currency = null,
        public ?float $amount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            responseArray: $data['responseArray'] ?? null,
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
        );
    }
}
