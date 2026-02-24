<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Request;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;

/**
 * Iyzico iptal isteği.
 */
class CreateCancelRequest extends IyzicoBaseRequest
{
    public ?string $paymentId = null;

    public ?string $ip = null;

    public ?string $reason = null;

    public ?string $description = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->append('paymentId', $this->paymentId)
            ->append('ip', $this->ip)
            ->append('reason', $this->reason)
            ->append('description', $this->description)
            ->getRequestString();
    }
}
