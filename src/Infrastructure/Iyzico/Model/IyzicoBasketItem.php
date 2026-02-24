<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico\Model;

use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKISerializable;

/**
 * Iyzico sepet ürün modeli.
 */
class IyzicoBasketItem implements PKISerializable
{
    public ?string $id = null;

    public ?string $price = null;

    public ?string $name = null;

    public ?string $category1 = null;

    public ?string $category2 = null;

    public ?string $itemType = null;

    public ?string $subMerchantKey = null;

    public ?string $subMerchantPrice = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->append('id', $this->id)
            ->appendPrice('price', $this->price)
            ->append('name', $this->name)
            ->append('category1', $this->category1)
            ->append('category2', $this->category2)
            ->append('itemType', $this->itemType)
            ->append('subMerchantKey', $this->subMerchantKey)
            ->appendPrice('subMerchantPrice', $this->subMerchantPrice)
            ->getRequestString();
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn ($v) => $v !== null);
    }
}
