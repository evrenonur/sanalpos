<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Request;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;

/**
 * Iyzico tutar bazlı iade isteği.
 */
class CreateAmountBasedRefundRequest extends IyzicoBaseRequest
{
    public ?string $paymentId = null;

    public ?string $price = null;

    public ?string $ip = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->append('paymentId', $this->paymentId)
            ->appendPrice('price', $this->price)
            ->append('ip', $this->ip)
            ->getRequestString();
    }
}
