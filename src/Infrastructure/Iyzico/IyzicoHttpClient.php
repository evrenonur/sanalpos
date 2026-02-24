<?php

namespace EvrenOnur\SanalPos\Infrastructure\Iyzico;

use EvrenOnur\SanalPos\Support\MakesHttpRequests;

/**
 * Iyzico REST HTTP istemcisi.
 */
class IyzicoHttpClient
{
    use MakesHttpRequests;

    private static ?self $instance = null;

    private static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function post(string $url, array $headers, array $body): array
    {
        try {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $content = self::getInstance()->httpPostRaw($url, $jsonBody, $headers);

            return json_decode($content, true) ?? [];
        } catch (\Throwable $e) {
            return ['status' => 'failure', 'errorMessage' => $e->getMessage()];
        }
    }
}
