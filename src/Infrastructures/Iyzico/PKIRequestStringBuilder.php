<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico;

/**
 * Iyzico PKI Request String builder.
 * Format: [key=value,key2=value2]
 */
class PKIRequestStringBuilder
{
    private string $requestString = '';

    public static function create(): self
    {
        return new self;
    }

    public function appendSuper(?string $superRequestString): self
    {
        if ($superRequestString !== null) {
            // Remove [ and ]
            $inner = substr($superRequestString, 1, -1);
            if (strlen($inner) > 0) {
                $this->requestString .= $inner . ',';
            }
        }

        return $this;
    }

    public function append(string $key, $value = null): self
    {
        if ($value !== null) {
            if ($value instanceof PKISerializable) {
                $this->appendKeyValue($key, $value->toPKIRequestString());
            } else {
                $this->appendKeyValue($key, (string) $value);
            }
        }

        return $this;
    }

    public function appendPrice(string $key, ?string $value): self
    {
        if ($value !== null) {
            $this->appendKeyValue($key, self::formatPrice($value));
        }

        return $this;
    }

    /**
     * @param  PKISerializable[]  $list
     */
    public function appendList(string $key, ?array $list): self
    {
        if ($list !== null && count($list) > 0) {
            $appendedValue = '';
            foreach ($list as $item) {
                $appendedValue .= $item->toPKIRequestString() . ', ';
            }
            $this->appendKeyValueArray($key, $appendedValue);
        }

        return $this;
    }

    public function getRequestString(): string
    {
        // Remove trailing comma
        if (strlen($this->requestString) > 0) {
            $this->requestString = substr($this->requestString, 0, -1);
        }

        return '[' . $this->requestString . ']';
    }

    private function appendKeyValue(string $key, ?string $value): self
    {
        if ($value !== null) {
            $this->requestString .= $key . '=' . $value . ',';
        }

        return $this;
    }

    private function appendKeyValueArray(string $key, string $value): self
    {
        // Remove trailing ", "
        $value = substr($value, 0, -2);
        $this->requestString .= $key . '=[' . $value . '],';

        return $this;
    }

    /**
     * Iyzico fiyat formatı: trailing zero'ları kaldırır ama en az bir ondalık basamak bırakır.
     */
    public static function formatPrice(string $price): string
    {
        if (strpos($price, '.') === false) {
            return $price . '.0';
        }

        $reversed = strrev($price);
        $subStrIndex = 0;
        for ($i = 0; $i < strlen($reversed); $i++) {
            if ($reversed[$i] === '0') {
                $subStrIndex = $i + 1;
            } elseif ($reversed[$i] === '.') {
                $reversed = '0' . $reversed;
                break;
            } else {
                break;
            }
        }

        return strrev(substr($reversed, $subStrIndex));
    }
}
