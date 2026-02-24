<?php

namespace EvrenOnur\SanalPos\Models;

class AllInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var AllInstallment[]|null */
        public ?array $installmentList = null,
    ) {}
}
