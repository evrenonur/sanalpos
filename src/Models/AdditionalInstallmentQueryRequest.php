<?php

namespace EvrenOnur\SanalPos\Models;

class AdditionalInstallmentQueryRequest
{
    public function __construct(
        public ?SaleInfo $saleInfo = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            saleInfo: isset($data['saleInfo']) ? SaleInfo::fromArray($data['saleInfo']) : null,
        );
    }
}
