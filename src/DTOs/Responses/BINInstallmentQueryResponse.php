<?php

namespace EvrenOnur\SanalPos\DTOs\Responses;

use EvrenOnur\SanalPos\DTOs\Installment;

class BINInstallmentQueryResponse
{
    public function __construct(
        public bool $confirm = false,
        /** @var Installment[]|null */
        public ?array $installment_list = null,
        public ?array $private_response = null,
    ) {}
}
