<?php

namespace EvrenOnur\SanalPos\DTOs\Responses;

use EvrenOnur\SanalPos\DTOs\AllInstallment;

class AllInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var AllInstallment[]|null */
        public ?array $installment_list = null,
    ) {}
}
