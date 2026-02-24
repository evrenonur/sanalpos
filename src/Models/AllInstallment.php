<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\CreditCardProgram;

class AllInstallment
{
    public function __construct(
        public string $bankCode = '',
        public ?CreditCardProgram $cardProgram = null,
        public int $count = 0,
        public float $customerCostCommissionRate = 0,
        /** @var array<array{installment: int, rate: float}>|null */
        public ?array $installmentList = null,
    ) {}
}
