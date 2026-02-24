<?php

namespace EvrenOnur\SanalPos\DTOs\Responses;

use EvrenOnur\SanalPos\Enums\SaleResponseStatus;

class SaleResponse
{
    public function __construct(
        public SaleResponseStatus $status = SaleResponseStatus::Error,
        public string $message = '',
        public string $order_number = '',
        public ?string $transaction_id = null,
        public ?array $private_response = null,
    ) {}
}
