<?php

namespace EvrenOnur\SanalPos\Models;

use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryTransactionStatu;

class SaleQueryResponse
{
    public function __construct(
        /** İşlem durumu */
        public ?SaleQueryResponseStatu $statu = null,
        /** İşlem sonuç mesajı */
        public string $message = '',
        /** Sipariş numarası */
        public string $orderNumber = '',
        /** Banka işlem numarası */
        public string $transactionId = '',
        /** İşlem tarihi */
        public ?string $transactionDate = null,
        /** İşlem son durumu */
        public ?SaleQueryTransactionStatu $transactionStatu = null,
        /** Karttan çekilen tutar */
        public ?float $amount = null,
        /** Bankanın ham cevabı */
        public ?array $privateResponse = null,
    ) {}

    public function toArray(): array
    {
        return [
            'statu' => $this->statu?->value,
            'message' => $this->message,
            'orderNumber' => $this->orderNumber,
            'transactionId' => $this->transactionId,
            'transactionDate' => $this->transactionDate,
            'transactionStatu' => $this->transactionStatu?->value,
            'amount' => $this->amount,
            'privateResponse' => $this->privateResponse,
        ];
    }
}
