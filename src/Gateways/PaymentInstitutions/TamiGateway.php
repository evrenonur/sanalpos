<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Helpers\StringHelper;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\CancelRequest;
use EvrenOnur\SanalPos\Models\CancelResponse;
use EvrenOnur\SanalPos\Models\RefundRequest;
use EvrenOnur\SanalPos\Models\RefundResponse;
use EvrenOnur\SanalPos\Models\Sale3DResponseRequest;
use EvrenOnur\SanalPos\Models\SaleQueryRequest;
use EvrenOnur\SanalPos\Models\SaleQueryResponse;
use EvrenOnur\SanalPos\Models\SaleRequest;
use EvrenOnur\SanalPos\Models\SaleResponse;
use EvrenOnur\SanalPos\Models\VirtualPOSAuth;
use GuzzleHttp\Client;

class TamiGateway implements VirtualPOSServiceInterface
{
    private string $urlTest = 'https://sandbox-paymentapi.tami.com.tr';

    private string $urlLive = 'https://paymentapi.tami.com.tr';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $is3D = $request->payment3D?->confirm === true;

        $amount = StringHelper::formatAmount($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;
        $currencyStr = StringHelper::getCurrencyName($request->saleInfo->currency ?? Currency::TRY);

        $body = [
            'orderId' => $request->orderNumber,
            'amount' => (float) $amount,
            'currency' => $currencyStr,
            'installmentCount' => $installment,
            'paymentGroup' => 'OTHER',
            'card' => [
                'cardHolderName' => $request->saleInfo->cardNameSurname,
                'cardNumber' => $request->saleInfo->cardNumber,
                'expireMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
                'expireYear' => (string) $request->saleInfo->cardExpiryDateYear,
                'cvc' => $request->saleInfo->cardCVV,
            ],
            'buyer' => [
                'id' => $request->customerIPAddress,
                'name' => $request->invoiceInfo?->name ?? 'Müşteri',
                'surname' => $request->invoiceInfo?->surname ?? '',
                'email' => $request->invoiceInfo?->emailAddress ?? '',
                'ip' => $request->customerIPAddress,
                'identityNumber' => $request->invoiceInfo?->taxNumber ?? '11111111111',
                'phoneNumber' => $request->invoiceInfo?->phoneNumber ?? '',
                'city' => $request->invoiceInfo?->city ?? '',
                'country' => 'Turkey',
            ],
            'shippingAddress' => [
                'contactName' => $request->saleInfo->cardNameSurname,
                'address' => $request->invoiceInfo?->addressDesc ?? '',
                'city' => $request->invoiceInfo?->city ?? '',
                'country' => 'Turkey',
            ],
            'billingAddress' => [
                'contactName' => $request->saleInfo->cardNameSurname,
                'address' => $request->invoiceInfo?->addressDesc ?? '',
                'city' => $request->invoiceInfo?->city ?? '',
                'country' => 'Turkey',
            ],
        ];

        if ($is3D) {
            $body['callbackUrl'] = $request->payment3D->returnURL;
        }

        $securityHash = $this->generateJWKSignature($auth, $body);
        $body['securityHash'] = $securityHash;

        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $resp = $this->jsonRequest($baseUrl . '/payment/auth', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $success = $dic['success'] ?? false;

        if ($success === true) {
            if ($is3D) {
                $htmlContent = $dic['threeDSHtmlContent'] ?? '';
                if (! empty($htmlContent)) {
                    $decodedHtml = base64_decode($htmlContent);
                    $response->statu = SaleResponseStatu::RedirectHTML;
                    $response->message = $decodedHtml;
                } else {
                    $response->statu = SaleResponseStatu::Error;
                    $response->message = '3D HTML içeriği alınamadı';
                }
            } else {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarılı';
                $response->transactionId = (string) ($dic['bankReferenceNumber'] ?? '');
            }
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['errorMessage'] ?? ($dic['message'] ?? 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $orderId = $request->responseArray['orderId'] ?? '';
        $success = $request->responseArray['success'] ?? false;
        $response->orderNumber = (string) $orderId;

        if ($success !== true && $success !== 'true' && $success !== '1') {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['errorMessage'] ?? '3D doğrulaması başarısız';

            return $response;
        }

        // Complete 3DS çağrısı
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = ['orderId' => $orderId];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/payment/complete-3ds', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse['response_2'] = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['bankReferenceNumber'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['errorMessage'] ?? 'İşlem tamamlanamadı';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = ['orderId' => $request->orderNumber];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/payment/reverse', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['errorMessage'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = [
            'orderId' => $request->orderNumber,
            'amount' => (float) StringHelper::formatAmount($request->refundAmount),
        ];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/payment/reverse', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['errorMessage'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = [
            'binNumber' => $request->BIN,
            'amount' => (float) StringHelper::formatAmount($request->amount),
        ];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/installment/installment-info', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $installments = $dic['installments'] ?? [];
        $isInstallment = $dic['isInstallment'] ?? false;

        if ($isInstallment && is_array($installments)) {
            foreach ($installments as $inst) {
                $count = (int) $inst;
                if ($count > 1) {
                    $response->installmentList[] = [
                        'installment' => $count,
                        'rate' => 0,
                        'totalAmount' => $request->amount,
                    ];
                }
            }
        }

        if (! empty($response->installmentList)) {
            $response->confirm = true;
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse
    {
        return new AllInstallmentQueryResponse(confirm: false);
    }

    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, VirtualPOSAuth $auth): AdditionalInstallmentQueryResponse
    {
        return new AdditionalInstallmentQueryResponse(confirm: false);
    }

    public function saleQuery(SaleQueryRequest $request, VirtualPOSAuth $auth): SaleQueryResponse
    {
        return new SaleQueryResponse(statu: SaleQueryResponseStatu::Error, message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor');
    }

    // --- Private helpers ---

    private function getBaseUrl(VirtualPOSAuth $auth): string
    {
        return $auth->testPlatform ? $this->urlTest : $this->urlLive;
    }

    private function buildHeaders(VirtualPOSAuth $auth): array
    {
        $authHash = base64_encode(hash('sha256', $auth->merchantID . $auth->merchantUser . $auth->merchantStorekey, true));
        $pgAuthToken = $auth->merchantID . ':' . $auth->merchantUser . ':' . $authHash;

        return [
            'Content-Type' => 'application/json',
            'PG-Auth-Token' => $pgAuthToken,
            'PG-Api-Version' => 'v3',
            'Accept-Language' => 'tr',
            'correlationId' => 'Correlation' . $this->generateUUID(),
        ];
    }

    private function generateJWKSignature(VirtualPOSAuth $auth, array $payload): string
    {
        // merchantPassword formatı: "{kid}|{k}"
        $parts = explode('|', $auth->merchantPassword);
        $kid = $parts[0] ?? '';
        $k = $parts[1] ?? '';

        // Base64 URL normalization
        $k = str_replace(['-', '_'], ['+', '/'], $k);
        $mod = strlen($k) % 4;
        if ($mod > 0) {
            $k .= str_repeat('=', 4 - $mod);
        }
        $keyBytes = base64_decode($k);

        // Header
        $header = json_encode(['alg' => 'HS512', 'typ' => 'JWT', 'kidValue' => $kid]);
        $headerB64 = $this->base64UrlEncode($header);

        // Payload
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadB64 = $this->base64UrlEncode($payloadJson);

        // Signature
        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = hash_hmac('sha512', $signingInput, $keyBytes, true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return $headerB64 . '.' . $payloadB64 . '.' . $signatureB64;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }

    private function jsonRequest(string $url, array $body, array $headers): string
    {
        try {
            $client = new Client(['verify' => false]);
            $resp = $client->post($url, [
                'json' => $body,
                'headers' => $headers,
            ]);

            return $resp->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
