<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Request;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoAddress;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoBasketItem;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoBuyer;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoPaymentCard;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;

/**
 * Iyzico ödeme oluşturma isteği.
 */
class CreatePaymentRequest extends IyzicoBaseRequest
{
    public ?string $price = null;

    public ?string $paidPrice = null;

    public ?int $installment = null;

    public ?string $paymentChannel = null;

    public ?string $basketId = null;

    public ?string $paymentGroup = null;

    public ?IyzicoPaymentCard $paymentCard = null;

    public ?IyzicoBuyer $buyer = null;

    public ?IyzicoAddress $shippingAddress = null;

    public ?IyzicoAddress $billingAddress = null;

    /** @var IyzicoBasketItem[]|null */
    public ?array $basketItems = null;

    public ?string $paymentSource = null;

    public ?string $callbackUrl = null;

    public ?string $posOrderId = null;

    public ?string $connectorName = null;

    public ?string $currency = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->appendPrice('price', $this->price)
            ->appendPrice('paidPrice', $this->paidPrice)
            ->append('installment', $this->installment)
            ->append('paymentChannel', $this->paymentChannel)
            ->append('basketId', $this->basketId)
            ->append('paymentGroup', $this->paymentGroup)
            ->append('paymentCard', $this->paymentCard)
            ->append('buyer', $this->buyer)
            ->append('shippingAddress', $this->shippingAddress)
            ->append('billingAddress', $this->billingAddress)
            ->appendList('basketItems', $this->basketItems)
            ->append('paymentSource', $this->paymentSource)
            ->append('currency', $this->currency)
            ->append('posOrderId', $this->posOrderId)
            ->append('connectorName', $this->connectorName)
            ->append('callbackUrl', $this->callbackUrl)
            ->getRequestString();
    }
}
