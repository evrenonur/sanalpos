<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class TamiGateway extends AbstractGateway
{
    private string $urlTest = 'https://sandbox-paymentapi.tami.com.tr';

    private string $urlLive = 'https://paymentapi.tami.com.tr';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $is3D = $request->payment_3d?->confirm === true;

        $amount = StringHelper::formatAmount($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;
        $currencyStr = StringHelper::getCurrencyName($request->sale_info->currency ?? Currency::TRY);

        $body = [
            'orderId' => $request->order_number,
            'amount' => (float) $amount,
            'currency' => $currencyStr,
            'installmentCount' => $installment,
            'paymentGroup' => 'OTHER',
            'card' => [
                'cardHolderName' => $request->sale_info->card_name_surname,
                'cardNumber' => $request->sale_info->card_number,
                'expireMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
                'expireYear' => (string) $request->sale_info->card_expiry_year,
                'cvc' => $request->sale_info->card_cvv,
            ],
            'buyer' => [
                'id' => $request->customer_ip_address,
                'name' => $request->invoice_info?->name ?? 'Müşteri',
                'surname' => $request->invoice_info?->surname ?? '',
                'email' => $request->invoice_info?->email_address ?? '',
                'ip' => $request->customer_ip_address,
                'identityNumber' => $request->invoice_info?->tax_number ?? '11111111111',
                'phoneNumber' => $request->invoice_info?->phone_number ?? '',
                'city' => $request->invoice_info?->city ?? '',
                'country' => 'Turkey',
            ],
            'shippingAddress' => [
                'contactName' => $request->sale_info->card_name_surname,
                'address' => $request->invoice_info?->address_description ?? '',
                'city' => $request->invoice_info?->city ?? '',
                'country' => 'Turkey',
            ],
            'billingAddress' => [
                'contactName' => $request->sale_info->card_name_surname,
                'address' => $request->invoice_info?->address_description ?? '',
                'city' => $request->invoice_info?->city ?? '',
                'country' => 'Turkey',
            ],
        ];

        if ($is3D) {
            $body['callbackUrl'] = $request->payment_3d->return_url;
        }

        $securityHash = $this->generateJWKSignature($auth, $body);
        $body['securityHash'] = $securityHash;

        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $resp = $this->jsonRequest($baseUrl . '/payment/auth', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $success = $dic['success'] ?? false;

        if ($success === true) {
            if ($is3D) {
                $htmlContent = $dic['threeDSHtmlContent'] ?? '';
                if (! empty($htmlContent)) {
                    $decodedHtml = base64_decode($htmlContent);
                    $response->status = SaleResponseStatus::RedirectHTML;
                    $response->message = $decodedHtml;
                } else {
                    $response->status = SaleResponseStatus::Error;
                    $response->message = '3D HTML içeriği alınamadı';
                }
            } else {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarılı';
                $response->transaction_id = (string) ($dic['bankReferenceNumber'] ?? '');
            }
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['errorMessage'] ?? ($dic['message'] ?? 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $orderId = $request->responseArray['orderId'] ?? '';
        $success = $request->responseArray['success'] ?? false;
        $response->order_number = (string) $orderId;

        if ($success !== true && $success !== 'true' && $success !== '1') {
            $response->status = SaleResponseStatus::Error;
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

        $response->private_response['response_2'] = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['bankReferenceNumber'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['errorMessage'] ?? 'İşlem tamamlanamadı';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = ['orderId' => $request->order_number];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/payment/reverse', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['errorMessage'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $headers = $this->buildHeaders($auth);

        $body = [
            'orderId' => $request->order_number,
            'amount' => (float) StringHelper::formatAmount($request->refund_amount),
        ];
        $body['securityHash'] = $this->generateJWKSignature($auth, $body);

        $resp = $this->jsonRequest($baseUrl . '/payment/reverse', $body, $headers);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['success'] ?? false) === true) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $dic['errorMessage'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
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

        $response->private_response = $dic;

        $installments = $dic['installments'] ?? [];
        $isInstallment = $dic['isInstallment'] ?? false;

        if ($isInstallment && is_array($installments)) {
            foreach ($installments as $inst) {
                $count = (int) $inst;
                if ($count > 1) {
                    $response->installment_list[] = [
                        'installment' => $count,
                        'rate' => 0,
                        'totalAmount' => $request->amount,
                    ];
                }
            }
        }

        if (! empty($response->installment_list)) {
            $response->confirm = true;
        }

        return $response;
    }

    // --- Private helpers ---

    private function getBaseUrl(MerchantAuth $auth): string
    {
        return $auth->test_platform ? $this->urlTest : $this->urlLive;
    }

    private function buildHeaders(MerchantAuth $auth): array
    {
        $authHash = base64_encode(hash('sha256', $auth->merchant_id . $auth->merchant_user . $auth->merchant_storekey, true));
        $pgAuthToken = $auth->merchant_id . ':' . $auth->merchant_user . ':' . $authHash;

        return [
            'Content-Type' => 'application/json',
            'PG-Auth-Token' => $pgAuthToken,
            'PG-Api-Version' => 'v3',
            'Accept-Language' => 'tr',
            'correlationId' => 'Correlation' . $this->generateUUID(),
        ];
    }

    private function generateJWKSignature(MerchantAuth $auth, array $payload): string
    {
        // merchant_password formatı: "{kid}|{k}"
        $parts = explode('|', $auth->merchant_password);
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
        return $this->httpPostJson($url, $body, $headers);
    }
}
