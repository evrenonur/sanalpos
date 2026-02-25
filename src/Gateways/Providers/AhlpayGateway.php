<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class AhlpayGateway extends AbstractGateway
{

    private string $urlTest = 'https://testahlsanalpos.ahlpay.com.tr';

    private string $urlLive = 'https://ahlsanalpos.ahlpay.com.tr';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);

        $tokenData = $this->authenticate($baseUrl, $auth);
        if (empty($tokenData['token'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $totalAmount = StringHelper::toKurus($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;
        $rnd = 'RND' . $request->order_number;
        $hash = $this->generateHash($auth->merchant_storekey, $rnd, $request->order_number, $totalAmount, $tokenData['merchantId']);

        $body = [
            'txnType' => 'Auth',
            'totalAmount' => $totalAmount,
            'orderId' => $request->order_number,
            'memberId' => $tokenData['merchantId'],
            'rnd' => $rnd,
            'hash' => $hash,
            'cardOwner' => $request->sale_info->card_name_surname,
            'cardNumber' => $request->sale_info->card_number,
            'cardExpireMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'cardExpireYear' => (string) $request->sale_info->card_expiry_year,
            'installment' => $installment,
            'cvv' => $request->sale_info->card_cvv,
            'currency' => (string) ($request->sale_info->currency?->value ?? 949),
            'customerIp' => $request->customer_ip_address,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/PaymentNon3D', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['data']['authCode'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);

        $tokenData = $this->authenticate($baseUrl, $auth);
        if (empty($tokenData['token'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $totalAmount = StringHelper::toKurus($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;
        $rnd = 'RND' . $request->order_number;
        $hash = $this->generateHash($auth->merchant_storekey, $rnd, $request->order_number, $totalAmount, $tokenData['merchantId']);

        $body = [
            'txnType' => 'Auth',
            'totalAmount' => $totalAmount,
            'orderId' => $request->order_number,
            'memberId' => $tokenData['merchantId'],
            'rnd' => $rnd,
            'hash' => $hash,
            'cardOwner' => $request->sale_info->card_name_surname,
            'cardNumber' => $request->sale_info->card_number,
            'cardExpireMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'cardExpireYear' => (string) $request->sale_info->card_expiry_year,
            'installment' => $installment,
            'cvv' => $request->sale_info->card_cvv,
            'currency' => (string) ($request->sale_info->currency?->value ?? 949),
            'customerIp' => $request->customer_ip_address,
            'okUrl' => $request->payment_3d->return_url,
            'failUrl' => $request->payment_3d->return_url,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Payment3DConfigWithEventRedirect', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->status = SaleResponseStatus::RedirectHTML;
            $response->message = (string) ($dic['data'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $orderId = $request->responseArray['orderId'] ?? '';
        $rnd = $request->responseArray['rnd'] ?? '';
        $response->order_number = (string) $orderId;

        // Ödeme sorgulama
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'orderId' => $orderId,
            'rnd' => $rnd,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/PaymentInquiry', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response['response_2'] = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['data']['authCode'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['message'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'txnType' => 'Void',
            'orderId' => $request->order_number,
            'totalAmount' => '999999900',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Void', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['message'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $tokenData = $this->authenticate($baseUrl, $auth);

        $body = [
            'txnType' => 'Refund',
            'orderId' => $request->order_number,
            'totalAmount' => StringHelper::toKurus($request->refund_amount),
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/Payment/Refund', $body, $tokenData);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['isSuccess'] ?? false) === true) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $dic['message'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    // --- Private helpers ---

    private function getBaseUrl(MerchantAuth $auth): string
    {
        return $auth->test_platform ? $this->urlTest : $this->urlLive;
    }

    private function authenticate(string $baseUrl, MerchantAuth $auth): array
    {
        try {
            $body = [
                'email' => $auth->merchant_user,
                'password' => $auth->merchant_password,
            ];
            $resp = $this->httpPostJson($baseUrl . '/api/Security/AuthenticationMerchant', $body);
            $data = json_decode($resp, true) ?? [];
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

    // --- Private helpers ---

    private function jsonRequest(string $url, array $body, array $tokenData = []): string
    {
        $headers = [];
        if (! empty($tokenData['token'])) {
            $headers['Authorization'] = ($tokenData['tokenType'] ?? 'Bearer') . ' ' . $tokenData['token'];
        }

        return $this->httpPostJson($url, $body, $headers);
    }
}
