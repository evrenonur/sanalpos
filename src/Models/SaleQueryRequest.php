<?php

namespace EvrenOnur\SanalPos\Models;

class SaleQueryRequest
{
    public function __construct(
        /** Sipariş numarası */
        public string $orderNumber = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orderNumber: $data['orderNumber'] ?? '',
        );
    }

    public function validate(): void
    {
        if (empty($this->orderNumber)) {
            throw new \InvalidArgumentException('orderNumber alanı zorunludur');
        }
    }

    public function toArray(): array
    {
        return [
            'orderNumber' => $this->orderNumber,
        ];
    }
}
