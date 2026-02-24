<?php

namespace EvrenOnur\SanalPos\Models;

class Installment
{
    public function __construct(
        public int $count = 0,
        public float $customerCostCommissionRate = 0,
    ) {}
}
