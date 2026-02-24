<?php

namespace EvrenOnur\SanalPos\Models;

class Bank
{
    public function __construct(
        public string $bankCode = '',
        public string $bankName = '',
        public bool $collectiveVPOS = false,
        public bool $commissionAutoAdd = false,
        public bool $installmentAPI = false,
        public ?string $gatewayClass = null,
    ) {}
}
