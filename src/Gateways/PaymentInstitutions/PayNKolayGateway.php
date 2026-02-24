<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Helpers\StringHelper;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallment;
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

class PayNKolayGateway implements VirtualPOSServiceInterface
{
    private string $urlTest = 'https://paynkolaytest.nkolayislem.com.tr';

    private string $urlLive = 'https://paynkolay.nkolayislem.com.tr';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $is3D = $request->payment3D?->confirm === true;

        $amount = StringHelper::formatAmount($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;
        $rnd = date('d.m.Y H:i:s');
        $customerKey = $auth->merchantStorekey;

        $hashData = implode('|', [
            $auth->merchantID,
            $request->orderNumber,
            $amount,
            $request->payment3D?->returnURL ?? '',
            $request->payment3D?->returnURL ?? '',
            $rnd,
            $customerKey,
            $auth->merchantPassword,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchantID,
            'clientRefCode' => $request->orderNumber,
            'cardHolderName' => $request->saleInfo->cardNameSurname,
            'cardNo' => $request->saleInfo->cardNumber,
            'month' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'year' => (string) $request->saleInfo->cardExpiryDateYear,
            'cvc' => $request->saleInfo->cardCVV,
            'amount' => $amount,
            'currency' => StringHelper::getCurrencyCode($request->saleInfo->currency ?? Currency::TRY),
            'installmentCount' => (string) $installment,
            'transactionType' => 'SALES',
            'environment' => 'API',
            'customerKey' => $customerKey,
            'rnd' => $rnd,
            'hash' => $hash,
            'use3D' => $is3D ? 'true' : 'false',
        ];

        if ($is3D) {
            $params['successUrl'] = $request->payment3D->returnURL;
            $params['failUrl'] = $request->payment3D->returnURL;
        }

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/Payment');
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $responseCode = (int) ($dic['RESPONSE_CODE'] ?? 0);

        if ($responseCode === 2) {
            if ($is3D) {
                $use3D = $dic['USE_3D'] ?? '';
                if ($use3D === 'true') {
                    $html = $dic['BANK_REQUEST_MESSAGE'] ?? '';
                    $response->statu = SaleResponseStatu::RedirectHTML;
                    $response->message = $this->cleanHtml($html);

                    return $response;
                }
            }

            $authCode = $dic['AUTH_CODE'] ?? '0';
            if (! empty($authCode) && $authCode !== '0') {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarılı';
                $response->transactionId = (string) ($dic['REFERENCE_CODE'] ?? '');

                return $response;
            }
        }

        $response->statu = SaleResponseStatu::Error;
        $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem sırasında bir hata oluştu';

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $response->orderNumber = (string) ($request->responseArray['CLIENT_REFERENCE_CODE'] ?? '');
        $responseCode = (int) ($request->responseArray['RESPONSE_CODE'] ?? 0);
        $referenceCode = $request->responseArray['REFERENCE_CODE'] ?? '';

        if ($responseCode !== 2 || empty($referenceCode)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['RESPONSE_MSG'] ?? '3D doğrulaması başarısız';

            return $response;
        }

        // CompletePayment çağrısı
        $baseUrl = $this->getBaseUrl($auth);

        $params = [
            'sx' => $auth->merchantPassword, // Cancel/Refund'da merchantPassword kullanılıyor
            'referenceCode' => $referenceCode,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CompletePayment');
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse['response_2'] = $dic;

        $completeCode = (int) ($dic['RESPONSE_CODE'] ?? 0);
        $authCode = $dic['AUTH_CODE'] ?? '0';

        if ($completeCode === 2 && ! empty($authCode) && $authCode !== '0') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['REFERENCE_CODE'] ?? $referenceCode);
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem tamamlanamadı';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);

        $rnd = date('d.m.Y H:i:s');
        $hashData = implode('|', [
            $auth->merchantPassword,
            $request->transactionId,
            'cancel',
            '0',
            '',
            $auth->merchantStorekey,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchantPassword,
            'referenceCode' => $request->transactionId,
            'type' => 'cancel',
            'amount' => '0',
            'trxDate' => '',
            'hash' => $hash,
            'rnd' => $rnd,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CancelRefundPayment');
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if ((int) ($dic['RESPONSE_CODE'] ?? 0) === 2) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);

        $amount = StringHelper::formatAmount($request->refundAmount);
        $rnd = date('d.m.Y H:i:s');
        $hashData = implode('|', [
            $auth->merchantPassword,
            $request->transactionId,
            'refund',
            $amount,
            '',
            $auth->merchantStorekey,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchantPassword,
            'referenceCode' => $request->transactionId,
            'type' => 'refund',
            'amount' => $amount,
            'trxDate' => '',
            'hash' => $hash,
            'rnd' => $rnd,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CancelRefundPayment');
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if ((int) ($dic['RESPONSE_CODE'] ?? 0) === 2) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        return new BINInstallmentQueryResponse(confirm: false);
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse
    {
        $response = new AllInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);

        $params = [
            'sx' => $auth->merchantID,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/Payment/GetMerchandInformation');
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        $commissions = $dic['COMMISSIONS'] ?? [];
        if (is_array($commissions)) {
            $installmentList = [];
            foreach ($commissions as $comm) {
                $programName = $comm['CARD_PROGRAM'] ?? 'Other';
                $program = CreditCardProgram::tryFromName($programName) ?? CreditCardProgram::Other;
                $installment = (int) ($comm['INSTALLMENT'] ?? 0);
                $rate = (float) ($comm['COMMISSION_RATE'] ?? 0);

                if (! isset($installmentList[$programName])) {
                    $installmentList[$programName] = new AllInstallment(
                        cardProgram: $program,
                        installmentList: [],
                    );
                }
                $installmentList[$programName]->installmentList[] = [
                    'installment' => $installment,
                    'rate' => $rate,
                ];
            }
            $response->installmentList = array_values($installmentList);
            if (! empty($response->installmentList)) {
                $response->confirm = true;
            }
        }

        return $response;
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

    private function cleanHtml(string $html): string
    {
        // URL-encoded HTML temizleme
        $html = urldecode($html);

        return $html;
    }

    private function formRequest(array $params, string $url): string
    {
        try {
            $client = new Client(['verify' => false]);
            $resp = $client->post($url, ['form_params' => $params]);

            return $resp->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
