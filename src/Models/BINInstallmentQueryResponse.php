<?php

namespace EvrenOnur\SanalPos\Models;

class BINInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var Installment[]|null */
        public ?array $installmentList = null,
    ) {}
}
