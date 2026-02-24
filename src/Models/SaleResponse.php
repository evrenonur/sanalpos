<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\SaleResponseStatu;

class SaleResponse
{
    public function __construct(
        public SaleResponseStatu $statu = SaleResponseStatu::Error,
        public string $message = '',
        public string $orderNumber = '',
        public ?string $transactionId = null,
        public ?array $privateResponse = null,
    ) {}
}
