<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\Currency;

class AllInstallmentQueryRequest
{
    public function __construct(
        public float $amount = 0,
        public ?Currency $currency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) ($data['amount'] ?? 0),
            currency: isset($data['currency']) ? (is_int($data['currency']) ? Currency::from($data['currency']) : $data['currency']) : null,
        );
    }
}
