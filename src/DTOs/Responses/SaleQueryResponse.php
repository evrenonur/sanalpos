<?php

namespace EvrenOnur\SanalPos\DTOs\Responses;

use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryTransactionStatus;

class SaleQueryResponse
{
    public function __construct(
        /** İşlem durumu */
        public ?SaleQueryResponseStatus $status = null,
        /** İşlem sonuç mesajı */
        public string $message = '',
        /** Sipariş numarası */
        public string $order_number = '',
        /** Banka işlem numarası */
        public string $transaction_id = '',
        /** İşlem tarihi */
        public ?string $transactionDate = null,
        /** İşlem son durumu */
        public ?SaleQueryTransactionStatus $transactionStatus = null,
        /** Karttan çekilen tutar */
        public ?float $amount = null,
        /** Bankanın ham cevabı */
        public ?array $private_response = null,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status?->value,
            'message' => $this->message,
            'order_number' => $this->order_number,
            'transaction_id' => $this->transaction_id,
            'transactionDate' => $this->transactionDate,
            'transactionStatus' => $this->transactionStatus?->value,
            'amount' => $this->amount,
            'private_response' => $this->private_response,
        ];
    }
}
