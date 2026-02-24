<?php

namespace EvrenOnur\SanalPos\Infrastructures\Iyzico;

/**
 * Iyzico hash oluşturucu.
 * Formula: base64(sha1(apiKey + randomString + secretKey + pkiRequestString))
 */
class IyzicoHashGenerator
{
    public static function generateHash(string $apiKey, string $secretKey, string $randomString, PKISerializable $request): string
    {
        $hashStr = $apiKey . $randomString . $secretKey . $request->toPKIRequestString();

        return base64_encode(sha1($hashStr, true));
    }

    /**
     * HTTP header'larını oluşturur.
     */
    public static function getHttpHeaders(PKISerializable $request, IyzicoOptions $options): array
    {
        $randomString = date('dmYHis') . substr(microtime(), 2, 4);

        $hash = self::generateHash($options->apiKey, $options->secretKey, $randomString, $request);
        $authorization = 'IYZWS ' . $options->apiKey . ':' . $hash;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-iyzi-rnd' => $randomString,
            'x-iyzi-client-version' => 'cp-vpos-php-1.0',
            'Authorization' => $authorization,
        ];
    }
}
