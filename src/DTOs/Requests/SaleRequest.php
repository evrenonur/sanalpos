<?php

namespace EvrenOnur\SanalPos\DTOs\Requests;

use EvrenOnur\SanalPos\DTOs\CustomerInfo;
use EvrenOnur\SanalPos\DTOs\Payment3DConfig;
use EvrenOnur\SanalPos\DTOs\SaleInfo;

class SaleRequest
{
    public function __construct(
        public string $order_number = '',
        public string $customer_ip_address = '',
        public ?SaleInfo $sale_info = null,
        public ?CustomerInfo $invoice_info = null,
        public ?CustomerInfo $shipping_info = null,
        public ?Payment3DConfig $payment_3d = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            order_number: $data['order_number'] ?? '',
            customer_ip_address: $data['customer_ip_address'] ?? '',
            sale_info: isset($data['sale_info']) ? SaleInfo::fromArray($data['sale_info']) : null,
            invoice_info: isset($data['invoice_info']) ? CustomerInfo::fromArray($data['invoice_info']) : null,
            shipping_info: isset($data['shipping_info']) ? CustomerInfo::fromArray($data['shipping_info']) : null,
            payment_3d: isset($data['payment_3d']) ? Payment3DConfig::fromArray($data['payment_3d']) : null,
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->order_number)) {
            $errors[] = 'Sipariş numarası boş olamaz.';
        }

        if (empty($this->customer_ip_address)) {
            $errors[] = 'Müşteri IP adresi boş olamaz.';
        }

        if ($this->sale_info === null) {
            $errors[] = 'Satış bilgileri boş olamaz.';
        } else {
            $errors = array_merge($errors, $this->sale_info->validate());
        }

        return $errors;
    }
}
