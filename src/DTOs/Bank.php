<?php

namespace EvrenOnur\SanalPos\DTOs;

class Bank
{
    public function __construct(
        public string $bank_code = '',
        public string $bank_name = '',
        public bool $collective_vpos = false,
        public bool $commissionAutoAdd = false,
        public bool $installment_api = false,
        public ?string $gatewayClass = null,
    ) {}
}
