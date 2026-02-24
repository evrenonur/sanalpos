<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
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

class AhlpayGateway implements VirtualPOSServiceInterface
{
    private string $urlTest = 'https://testahlsanalpos.ahlpay.com.tr';

    private string $urlLive = 'https://ahlsanalpos.ahlpay.com.tr';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);

        $tokenData = $this->authenticate($baseUrl, $auth);
        if (empty($tokenData['token'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $totalAmount = $this->toKurus($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;
        $rnd = 'RND' . $request->orderNumber;
        $hash = $this->generateHash($auth->merchantStorekey, $rnd, $request->orderNumber, $totalAmount, $tokenData['merchantId']);

        $body = [
            'txnType' => 'Auth',
            'totalAmount' => $totalAmount,
            'orderId' => $request->orderNumber,
            'memberId' => $tokenData['merchantId'],
            'rnd' => $rnd,
            'hash' => $hash,
            'cardOwner' => $request->saleInfo->cardNameSurname,
            'cardNumber' => $request->saleInfo->cardNumber,
            'cardExpireMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'cardExpireYear' => (string) $request->saleInfo->cardExpiryDateYear,
            'installment' => $installment,
            'cvv' => $request->saleInfo->cardCVV,
            'currency' => (string) ($request->saleInfo->currency?->value ?? 949),
            'customerIp' => $request->customerIPAddress,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/PaymentNon3D', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['data']['authCode'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);

        $tokenData = $this->authenticate($baseUrl, $auth);
        if (empty($tokenData['token'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $totalAmount = $this->toKurus($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;
        $rnd = 'RND' . $request->orderNumber;
        $hash = $this->generateHash($auth->merchantStorekey, $rnd, $request->orderNumber, $totalAmount, $tokenData['merchantId']);

        $body = [
            'txnType' => 'Auth',
            'totalAmount' => $totalAmount,
            'orderId' => $request->orderNumber,
            'memberId' => $tokenData['merchantId'],
            'rnd' => $rnd,
            'hash' => $hash,
            'cardOwner' => $request->saleInfo->cardNameSurname,
            'cardNumber' => $request->saleInfo->cardNumber,
            'cardExpireMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'cardExpireYear' => (string) $request->saleInfo->cardExpiryDateYear,
            'installment' => $installment,
            'cvv' => $request->saleInfo->cardCVV,
            'currency' => (string) ($request->saleInfo->currency?->value ?? 949),
            'customerIp' => $request->customerIPAddress,
            'okUrl' => $request->payment3D->returnURL,
            'failUrl' => $request->payment3D->returnURL,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Payment3DWithEventRedirect', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->statu = SaleResponseStatu::RedirectHTML;
            $response->message = (string) ($dic['data'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $orderId = $request->responseArray['orderId'] ?? '';
        $rnd = $request->responseArray['rnd'] ?? '';
        $response->orderNumber = (string) $orderId;

        // Ödeme sorgulama
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'orderId' => $orderId,
            'rnd' => $rnd,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/PaymentInquiry', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse['response_2'] = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['data']['authCode'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'txnType' => 'Void',
            'orderId' => $request->orderNumber,
            'totalAmount' => '999999900',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Void', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['message'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'txnType' => 'Refund',
            'orderId' => $request->orderNumber,
            'totalAmount' => $this->toKurus($request->refundAmount),
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Refund', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['message'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        return new BINInstallmentQueryResponse(confirm: false);
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

    private function authenticate(string $baseUrl, VirtualPOSAuth $auth): array
    {
        try {
            $body = [
                'email' => $auth->merchantUser,
                'password' => $auth->merchantPassword,
            ];
            $client = new Client(['verify' => false]);
            $resp = $client->post($baseUrl . '/api/Security/AuthenticationMerchant', [
                'json' => $body,
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $data = json_decode($resp->getBody()->getContents(), true) ?? [];
            $d = $data['data'] ?? [];

            return [
                'token' => $d['token'] ?? '',
                'tokenType' => $d['tokenType'] ?? 'Bearer',
                'merchantId' => (string) ($d['merchantId'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return ['token' => '', 'tokenType' => 'Bearer', 'merchantId' => ''];
        }
    }

    private function generateHash(string $storeKey, string $rnd, string $orderId, string $totalAmount, string $merchantId): string
    {
        $data = $storeKey . $rnd . $orderId . $totalAmount . $merchantId;

        return strtoupper(hash('sha512', $data));
    }

    private function toKurus(float $amount): string
    {
        return str_replace([',', '.'], '', number_format($amount, 2, '.', ''));
    }

    private function jsonRequest(string $url, array $body, array $tokenData = []): string
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if (! empty($tokenData['token'])) {
                $headers['Authorization'] = ($tokenData['tokenType'] ?? 'Bearer') . ' ' . $tokenData['token'];
            }
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
