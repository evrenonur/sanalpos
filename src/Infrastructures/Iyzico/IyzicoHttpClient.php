<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico;

use GuzzleHttp\Client;

/**
 * Iyzico REST HTTP istemcisi.
 */
class IyzicoHttpClient
{
    public static function post(string $url, array $headers, array $body): array
    {
        try {
            $client = new Client(['verify' => false]);

            $guzzleHeaders = [];
            foreach ($headers as $key => $value) {
                $guzzleHeaders[$key] = $value;
            }

            $response = $client->post($url, [
                'headers' => $guzzleHeaders,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $content = $response->getBody()->getContents();

            return json_decode($content, true) ?? [];
        } catch (\Throwable $e) {
            return ['status' => 'failure', 'errorMessage' => $e->getMessage()];
        }
    }
}
