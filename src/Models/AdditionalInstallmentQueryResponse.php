<?php

namespace EvrenOnur\SanalPos\Models;

class AdditionalInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var AdditionalInstallment[]|null */
        public ?array $installmentList = null,
    ) {}
}
