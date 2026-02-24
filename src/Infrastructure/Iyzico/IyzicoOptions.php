<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico;

/**
 * Iyzico API seçenekleri.
 */
class IyzicoOptions
{
    public string $apiKey;

    public string $secretKey;

    public string $baseUrl;

    public function __construct(string $apiKey = '', string $secretKey = '', string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = $baseUrl;
    }
}
