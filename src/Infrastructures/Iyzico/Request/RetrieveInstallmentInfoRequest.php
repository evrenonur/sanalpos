<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico\Request;

use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKIRequestStringBuilder;

/**
 * Iyzico taksit bilgisi sorgulama isteği.
 */
class RetrieveInstallmentInfoRequest extends IyzicoBaseRequest
{
    public ?string $binNumber = null;

    public ?string $price = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->append('binNumber', $this->binNumber)
            ->appendPrice('price', $this->price)
            ->getRequestString();
    }
}
