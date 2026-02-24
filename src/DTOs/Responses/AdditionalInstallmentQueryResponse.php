<?php

namespace EvrenOnur\SanalPos\DTOs\Responses;

use EvrenOnur\SanalPos\DTOs\AdditionalInstallment;

class AdditionalInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var AdditionalInstallment[]|null */
        public ?array $installment_list = null,
    ) {}
}
