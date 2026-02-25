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
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class MokaGateway extends AbstractGateway
{

    private string $urlTest = 'https://service.refmoka.com';

    private string $urlLive = 'https://service.moka.com';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'CardHolderFullName' => $request->sale_info->card_name_surname,
                'CardNumber' => $request->sale_info->card_number,
                'ExpMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
                'ExpYear' => (string) $request->sale_info->card_expiry_year,
                'CvcNumber' => $request->sale_info->card_cvv,
                'Amount' => StringHelper::formatAmount($request->sale_info->amount),
                'Currency' => StringHelper::getCurrencyCode($request->sale_info->currency),
                'InstallmentNumber' => $request->sale_info->installment > 1 ? $request->sale_info->installment : 1,
                'ClientIP' => $request->customer_ip_address,
                'OtherTrxCode' => $request->order_number,
                'IsPoolPayment' => 0,
                'IsTokenized' => 0,
                'Software' => 'cp.vpos',
                'IsPreAuth' => 0,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoDirectPayment', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $resultCode = strtolower($dic['ResultCode'] ?? '');
        $isSuccessful = $dic['Data']['IsSuccessful'] ?? false;

        if ($resultCode === 'success' && $isSuccessful === true) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['Data']['VirtualPosOrderId'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'CardHolderFullName' => $request->sale_info->card_name_surname,
                'CardNumber' => $request->sale_info->card_number,
                'ExpMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
                'ExpYear' => (string) $request->sale_info->card_expiry_year,
                'CvcNumber' => $request->sale_info->card_cvv,
                'Amount' => StringHelper::formatAmount($request->sale_info->amount),
                'Currency' => StringHelper::getCurrencyCode($request->sale_info->currency),
                'InstallmentNumber' => $request->sale_info->installment > 1 ? $request->sale_info->installment : 1,
                'ClientIP' => $request->customer_ip_address,
                'OtherTrxCode' => $request->order_number,
                'IsPoolPayment' => 0,
                'IsTokenized' => 0,
                'Software' => 'cp.vpos',
                'IsPreAuth' => 0,
                'ReturnHash' => 1,
                'RedirectType' => 0,
                'RedirectUrl' => $request->payment_3d->return_url,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoDirectPaymentThreeD', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $resultCode = strtolower($dic['ResultCode'] ?? '');
        $redirectUrl = $dic['Data']['Url'] ?? ($dic['Data'] ?? '');

        if ($resultCode === 'success' && ! empty($redirectUrl)) {
            $response->status = SaleResponseStatus::RedirectURL;
            $response->message = (string) $redirectUrl;
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $response->order_number = (string) ($request->responseArray['OtherTrxCode'] ?? '');
        $response->transaction_id = (string) ($request->responseArray['trxCode'] ?? '');

        $resultCode = $request->responseArray['resultCode'] ?? '';
        $resultMessage = $request->responseArray['resultMessage'] ?? '';

        if (! empty($resultMessage)) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $resultMessage;

            return $response;
        }

        // Ödeme durumunu doğrula
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'PaymentId' => (int) ($request->responseArray['trxCode'] ?? 0),
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/GetDealerPaymentTrxDetailList', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response['response_2'] = $dic;

        $paymentDetail = $dic['Data']['PaymentDetail'][0] ?? null;
        if ($paymentDetail !== null) {
            $paymentStatus = (int) ($paymentDetail['PaymentStatus'] ?? 0);
            $trxStatus = (int) ($paymentDetail['TrxStatus'] ?? 0);

            if ($paymentStatus === 2 && $trxStatus === 1) {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarılı';

                return $response;
            }
        }

        $response->status = SaleResponseStatus::Error;
        $response->message = '3D doğrulaması başarısız veya ödeme tamamlanamadı';

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'VirtualPosOrderId' => $request->transaction_id,
                'OtherTrxCode' => $request->order_number,
                'VoidRefundReason' => 2,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoVoid', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (strtolower($dic['ResultCode'] ?? '') === 'success') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'VirtualPosOrderId' => $request->transaction_id,
                'OtherTrxCode' => $request->order_number,
                'Amount' => StringHelper::formatAmount($request->refund_amount),
                'VoidRefundReason' => 2,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoCreateRefundRequest', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (strtolower($dic['ResultCode'] ?? '') === 'success') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchant_id,
                'Username' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'BinNumber' => $request->BIN,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/GetIsInstallment', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $bankCards = $dic['Data']['BankCardList'] ?? [];
        foreach ($bankCards as $card) {
            $installments = $card['InstallmentList'] ?? [];
            foreach ($installments as $inst) {
                $count = (int) ($inst['InstallmentNumber'] ?? 0);
                $rate = (float) ($inst['CommissionRate'] ?? 0);
                if ($count > 1) {
                    $totalAmount = round($request->amount * (1 + $rate / 100), 2);
                    $response->installment_list[] = [
                        'installment' => $count,
                        'rate' => $rate,
                        'totalAmount' => $totalAmount,
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

    private function generateCheckKey(MerchantAuth $auth): string
    {
        $data = $auth->merchant_id . 'MK' . $auth->merchant_user . 'PD' . $auth->merchant_password;

        return hash('sha256', $data);
    }

    private function getErrorMessage(string $code): string
    {
        $errors = [
            '001' => 'Kart sahibi veya bankası sisteme kayıtlı değil.',
            '002' => 'Tarih yanlış veya S.K.T. hatası.',
            '003' => 'Üye işyeri kategori kodu hatalı.',
            '004' => 'Kart el konulabilir.',
            '005' => 'İşlem onaylanmadı.',
            '006' => 'Hata!',
            '007' => 'Kart el konulabilir, özel durum.',
            '008' => 'Kimlik kontrolü yapılmalı.',
            '009' => 'Tekrar deneyiniz.',
            '010' => 'Kart kabul edildi.',
            '011' => 'VIP işlem.',
            '012' => 'Geçersiz işlem.',
            '013' => 'Geçersiz miktar.',
            '014' => 'Geçersiz kart numarası.',
            '015' => 'Kart çıkaran banka bilinmiyor.',
        ];

        return $errors[$code] ?? 'İşlem sırasında bir hata oluştu. Hata kodu: ' . $code;
    }

    private function jsonRequest(string $url, array $body): string
    {
        return $this->httpPostJson($url, $body);
    }
}
