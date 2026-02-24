<?php

use EvrenOnur\SanalPos\Support\MakesHttpRequests;

// MakesHttpRequests trait testi için basit bir test sınıfı
class TestHttpClient
{
    use MakesHttpRequests;

    public function getClient(): \GuzzleHttp\Client
    {
        return $this->getHttpClient();
    }

    public function getTimeout(): int
    {
        return $this->httpTimeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->httpConnectTimeout;
    }
}

it('MakesHttpRequests trait varsayılan timeout değerlerini sağlar', function () {
    $client = new TestHttpClient;

    expect($client->getTimeout())->toBe(30)
        ->and($client->getConnectTimeout())->toBe(10);
});

it('MakesHttpRequests trait Guzzle Client oluşturur', function () {
    $client = new TestHttpClient;
    $guzzle = $client->getClient();

    expect($guzzle)->toBeInstanceOf(\GuzzleHttp\Client::class);
});

it('MakesHttpRequests trait aynı Client nesnesini tekrar kullanır', function () {
    $client = new TestHttpClient;
    $guzzle1 = $client->getClient();
    $guzzle2 = $client->getClient();

    expect($guzzle1)->toBe($guzzle2);
});
