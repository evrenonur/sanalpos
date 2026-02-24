<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\ResponseStatu;

class RefundResponse
{
    public function __construct(
        public ResponseStatu $statu = ResponseStatu::Error,
        public string $message = '',
        public float $refundAmount = 0,
        public ?array $privateResponse = null,
    ) {}
}
