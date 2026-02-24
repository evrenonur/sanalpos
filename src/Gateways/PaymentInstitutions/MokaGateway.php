<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
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

class MokaGateway implements VirtualPOSServiceInterface
{
    private string $urlTest = 'https://service.refmoka.com';

    private string $urlLive = 'https://service.moka.com';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'CardHolderFullName' => $request->saleInfo->cardNameSurname,
                'CardNumber' => $request->saleInfo->cardNumber,
                'ExpMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
                'ExpYear' => (string) $request->saleInfo->cardExpiryDateYear,
                'CvcNumber' => $request->saleInfo->cardCVV,
                'Amount' => StringHelper::formatAmount($request->saleInfo->amount),
                'Currency' => StringHelper::getCurrencyCode($request->saleInfo->currency),
                'InstallmentNumber' => $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1,
                'ClientIP' => $request->customerIPAddress,
                'OtherTrxCode' => $request->orderNumber,
                'IsPoolPayment' => 0,
                'IsTokenized' => 0,
                'Software' => 'cp.vpos',
                'IsPreAuth' => 0,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoDirectPayment', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $resultCode = strtolower($dic['ResultCode'] ?? '');
        $isSuccessful = $dic['Data']['IsSuccessful'] ?? false;

        if ($resultCode === 'success' && $isSuccessful === true) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['Data']['VirtualPosOrderId'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'CardHolderFullName' => $request->saleInfo->cardNameSurname,
                'CardNumber' => $request->saleInfo->cardNumber,
                'ExpMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
                'ExpYear' => (string) $request->saleInfo->cardExpiryDateYear,
                'CvcNumber' => $request->saleInfo->cardCVV,
                'Amount' => StringHelper::formatAmount($request->saleInfo->amount),
                'Currency' => StringHelper::getCurrencyCode($request->saleInfo->currency),
                'InstallmentNumber' => $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1,
                'ClientIP' => $request->customerIPAddress,
                'OtherTrxCode' => $request->orderNumber,
                'IsPoolPayment' => 0,
                'IsTokenized' => 0,
                'Software' => 'cp.vpos',
                'IsPreAuth' => 0,
                'ReturnHash' => 1,
                'RedirectType' => 0,
                'RedirectUrl' => $request->payment3D->returnURL,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoDirectPaymentThreeD', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $resultCode = strtolower($dic['ResultCode'] ?? '');
        $redirectUrl = $dic['Data']['Url'] ?? ($dic['Data'] ?? '');

        if ($resultCode === 'success' && ! empty($redirectUrl)) {
            $response->statu = SaleResponseStatu::RedirectURL;
            $response->message = (string) $redirectUrl;
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $response->orderNumber = (string) ($request->responseArray['OtherTrxCode'] ?? '');
        $response->transactionId = (string) ($request->responseArray['trxCode'] ?? '');

        $resultCode = $request->responseArray['resultCode'] ?? '';
        $resultMessage = $request->responseArray['resultMessage'] ?? '';

        if (! empty($resultMessage)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $resultMessage;

            return $response;
        }

        // Ödeme durumunu doğrula
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'PaymentId' => (int) ($request->responseArray['trxCode'] ?? 0),
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/GetDealerPaymentTrxDetailList', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse['response_2'] = $dic;

        $paymentDetail = $dic['Data']['PaymentDetail'][0] ?? null;
        if ($paymentDetail !== null) {
            $paymentStatus = (int) ($paymentDetail['PaymentStatus'] ?? 0);
            $trxStatus = (int) ($paymentDetail['TrxStatus'] ?? 0);

            if ($paymentStatus === 2 && $trxStatus === 1) {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarılı';

                return $response;
            }
        }

        $response->statu = SaleResponseStatu::Error;
        $response->message = '3D doğrulaması başarısız veya ödeme tamamlanamadı';

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'VirtualPosOrderId' => $request->transactionId,
                'OtherTrxCode' => $request->orderNumber,
                'VoidRefundReason' => 2,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoVoid', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (strtolower($dic['ResultCode'] ?? '') === 'success') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'VirtualPosOrderId' => $request->transactionId,
                'OtherTrxCode' => $request->orderNumber,
                'Amount' => StringHelper::formatAmount($request->refundAmount),
                'VoidRefundReason' => 2,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/DoCreateRefundRequest', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (strtolower($dic['ResultCode'] ?? '') === 'success') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $this->getErrorMessage($dic['ResultCode'] ?? '');
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $checkKey = $this->generateCheckKey($auth);

        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $auth->merchantID,
                'Username' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => [
                'BinNumber' => $request->BIN,
            ],
        ];

        $resp = $this->jsonRequest($baseUrl . '/PaymentDealer/GetIsInstallment', $body);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $bankCards = $dic['Data']['BankCardList'] ?? [];
        foreach ($bankCards as $card) {
            $installments = $card['InstallmentList'] ?? [];
            foreach ($installments as $inst) {
                $count = (int) ($inst['InstallmentNumber'] ?? 0);
                $rate = (float) ($inst['CommissionRate'] ?? 0);
                if ($count > 1) {
                    $totalAmount = round($request->amount * (1 + $rate / 100), 2);
                    $response->installmentList[] = [
                        'installment' => $count,
                        'rate' => $rate,
                        'totalAmount' => $totalAmount,
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

    private function generateCheckKey(VirtualPOSAuth $auth): string
    {
        $data = $auth->merchantID . 'MK' . $auth->merchantUser . 'PD' . $auth->merchantPassword;

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
        try {
            $client = new Client(['verify' => false]);
            $resp = $client->post($url, [
                'json' => $body,
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            return $resp->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
