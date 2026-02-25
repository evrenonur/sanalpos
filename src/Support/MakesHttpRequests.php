<?php

namespace EvrenOnur\SanalPos\Support;

use EvrenOnur\SanalPos\Exceptions\HttpRequestException;
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
     * Son HTTP hata mesajı
     */
    protected ?string $lastHttpError = null;

    /**
     * HTTP timeout süresi (saniye)
     */
    protected int $httpTimeout = 30;

    /**
     * HTTP bağlantı kurma timeout süresi (saniye)
     */
    protected int $httpConnectTimeout = 10;

    /**
     * Config'den timeout ve SSL verify değerlerini yükler.
     */
    private function loadConfigValues(): void
    {
        if (function_exists('config')) {
            $this->httpTimeout = (int) config('sanalpos.timeout', $this->httpTimeout);
            $this->httpConnectTimeout = (int) config('sanalpos.connect_timeout', $this->httpConnectTimeout);
            $this->httpVerifySSL = config('sanalpos.verify_ssl', $this->httpVerifySSL);
        }
    }

    /**
     * SSL doğrulama (production'da true olmalıdır)
     */
    protected bool $httpVerifySSL = true;

    /**
     * Guzzle Client nesnesi döndürür.
     * Tekrarlı oluşturma yerine tek nesne kullanır.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->loadConfigValues();
            $this->httpClient = new Client([
                'verify' => $this->httpVerifySSL,
                'timeout' => $this->httpTimeout,
                'connect_timeout' => $this->httpConnectTimeout,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Son HTTP hata mesajını döndürür.
     */
    public function getLastHttpError(): ?string
    {
        return $this->lastHttpError;
    }

    /**
     * Form-encoded POST isteği yapar.
     *
     * @throws HttpRequestException
     */
    protected function httpPostForm(string $url, array $params, array $headers = []): string
    {
        $this->lastHttpError = null;

        try {
            $options = ['form_params' => $params];
            if (! empty($headers)) {
                $options['headers'] = $headers;
            }

            $response = $this->getHttpClient()->post($url, $options);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->lastHttpError = $e->getMessage();

            throw new HttpRequestException($e->getMessage(), $url, $e->getCode(), $e);
        }
    }

    /**
     * JSON body ile POST isteği yapar.
     *
     * @throws HttpRequestException
     */
    protected function httpPostJson(string $url, array $body, array $headers = []): string
    {
        $this->lastHttpError = null;

        try {
            $defaultHeaders = ['Content-Type' => 'application/json; charset=utf-8'];
            $options = [
                'json' => $body,
                'headers' => array_merge($defaultHeaders, $headers),
            ];

            $response = $this->getHttpClient()->post($url, $options);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->lastHttpError = $e->getMessage();

            throw new HttpRequestException($e->getMessage(), $url, $e->getCode(), $e);
        }
    }

    /**
     * XML body ile POST isteği yapar.
     *
     * @throws HttpRequestException
     */
    protected function httpPostXml(string $url, string $xml, string $contentType = 'application/xml; charset=utf-8'): string
    {
        $this->lastHttpError = null;

        try {
            $response = $this->getHttpClient()->post($url, [
                'body' => $xml,
                'headers' => ['Content-Type' => $contentType],
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->lastHttpError = $e->getMessage();

            throw new HttpRequestException($e->getMessage(), $url, $e->getCode(), $e);
        }
    }

    /**
     * Ham body ile POST isteği yapar (SOAP vb. için).
     *
     * @throws HttpRequestException
     */
    protected function httpPostRaw(string $url, string $body, array $headers = []): string
    {
        $this->lastHttpError = null;

        try {
            $response = $this->getHttpClient()->post($url, [
                'body' => $body,
                'headers' => $headers,
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->lastHttpError = $e->getMessage();

            throw new HttpRequestException($e->getMessage(), $url, $e->getCode(), $e);
        }
    }
}
