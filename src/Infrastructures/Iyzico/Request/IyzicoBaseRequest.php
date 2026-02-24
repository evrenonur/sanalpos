<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico\Request;

use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKISerializable;

/**
 * Iyzico temel istek sınıfı.
 */
class IyzicoBaseRequest implements PKISerializable
{
    public ?string $locale = null;

    public ?string $conversationId = null;

    public function toPKIRequestString(): string
    {
        return PKIRequestStringBuilder::create()
            ->append('locale', $this->locale)
            ->append('conversationId', $this->conversationId)
            ->getRequestString();
    }

    /**
     * JSON serialization için array'e çevirir.
     * Null alanları atlar.
     */
    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value instanceof PKISerializable) {
                $result[$key] = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
            } elseif (is_array($value)) {
                $items = [];
                foreach ($value as $item) {
                    if ($item instanceof PKISerializable && method_exists($item, 'toArray')) {
                        $items[] = $item->toArray();
                    } else {
                        $items[] = $item;
                    }
                }
                $result[$key] = $items;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
