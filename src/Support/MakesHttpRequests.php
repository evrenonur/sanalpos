<?php

namespace EvrenOnur\SanalPos\Support;

use GuzzleHttp\Client;

/**
 * HTTP istekleri için ortak trait.
 * Tüm gateway'ler bu trait'i kullanarak HTTP isteklerini merkezi bir yerden yönetir.
 * Try-catch, timeout ve SSL verify yapılandırması bu trait üzerinden sağlanır.
 */
trait MakesHttpRequests
{
    private ?Client $httpClient = null;

    /**
     * HTTP timeout süresi (saniye)
     */
    protected int $httpTimeout = 30;

    /**
     * HTTP bağlantı kurma timeout süresi (saniye)
     */
    protected int $httpConnectTimeout = 10;

    /**
     * Guzzle Client nesnesi döndürür.
     * Tekrarlı oluşturma yerine tek nesne kullanır.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'verify' => false,
                'timeout' => $this->httpTimeout,
                'connect_timeout' => $this->httpConnectTimeout,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Form-encoded POST isteği yapar.
     */
    protected function httpPostForm(string $url, array $params, array $headers = []): string
    {
        try {
            $options = ['form_params' => $params];
            if (! empty($headers)) {
                $options['headers'] = $headers;
            }

            $response = $this->getHttpClient()->post($url, $options);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * JSON body ile POST isteği yapar.
     */
    protected function httpPostJson(string $url, array $body, array $headers = []): string
    {
        try {
            $defaultHeaders = ['Content-Type' => 'application/json; charset=utf-8'];
            $options = [
                'json' => $body,
                'headers' => array_merge($defaultHeaders, $headers),
            ];

            $response = $this->getHttpClient()->post($url, $options);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * XML body ile POST isteği yapar.
     */
    protected function httpPostXml(string $url, string $xml, string $contentType = 'application/xml; charset=utf-8'): string
    {
        try {
            $response = $this->getHttpClient()->post($url, [
                'body' => $xml,
                'headers' => ['Content-Type' => $contentType],
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Ham body ile POST isteği yapar (SOAP vb. için).
     */
    protected function httpPostRaw(string $url, string $body, array $headers = []): string
    {
        try {
            $response = $this->getHttpClient()->post($url, [
                'body' => $body,
                'headers' => $headers,
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
