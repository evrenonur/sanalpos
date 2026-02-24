<?php

namespace EvrenOnur\SanalPos\DTOs\Requests;

class SaleQueryRequest
{
    public function __construct(
        /** Sipariş numarası */
        public string $order_number = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            order_number: $data['order_number'] ?? '',
        );
    }

    public function validate(): void
    {
        if (empty($this->order_number)) {
            throw new \InvalidArgumentException('order_number alanı zorunludur');
        }
    }

    public function toArray(): array
    {
        return [
            'order_number' => $this->order_number,
        ];
    }
}
