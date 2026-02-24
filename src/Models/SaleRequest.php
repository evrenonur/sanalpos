<?php

namespace EvrenOnur\SanalPos\Models;

class SaleRequest
{
    public function __construct(
        public string $orderNumber = '',
        public string $customerIPAddress = '',
        public ?SaleInfo $saleInfo = null,
        public ?CustomerInfo $invoiceInfo = null,
        public ?CustomerInfo $shippingInfo = null,
        public ?Payment3D $payment3D = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orderNumber: $data['orderNumber'] ?? '',
            customerIPAddress: $data['customerIPAddress'] ?? '',
            saleInfo: isset($data['saleInfo']) ? SaleInfo::fromArray($data['saleInfo']) : null,
            invoiceInfo: isset($data['invoiceInfo']) ? CustomerInfo::fromArray($data['invoiceInfo']) : null,
            shippingInfo: isset($data['shippingInfo']) ? CustomerInfo::fromArray($data['shippingInfo']) : null,
            payment3D: isset($data['payment3D']) ? Payment3D::fromArray($data['payment3D']) : null,
        );
    }

    /**
     * Doğrulama
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->orderNumber)) {
            $errors[] = 'Sipariş numarası boş olamaz.';
        }

        if (empty($this->customerIPAddress)) {
            $errors[] = 'Müşteri IP adresi boş olamaz.';
        }

        if ($this->saleInfo === null) {
            $errors[] = 'Satış bilgileri boş olamaz.';
        } else {
            $errors = array_merge($errors, $this->saleInfo->validate());
        }

        return $errors;
    }
}
