<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\Payten;

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

abstract class PaytenAbstract implements VirtualPOSServiceInterface
{
    abstract protected function getApiTestUrl(): string;

    abstract protected function getApiLiveUrl(): string;

    abstract protected function get3DTestUrl(): string;

    abstract protected function get3DLiveUrl(): string;

    abstract protected function getBrandName(): string;

    /**
     * ThreatMetrix org_id — null ise enjeksiyon yapılmaz.
     */
    protected function getOnlineMetrixOrgId(): ?string
    {
        return null;
    }

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $request->saleInfo->currency = $request->saleInfo->currency ?? Currency::TRY;
        $request->saleInfo->installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;

        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $apiUrl = $auth->testPlatform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $amount = StringHelper::formatAmount($request->saleInfo->amount);
        $expiry = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . '.' . $request->saleInfo->cardExpiryDateYear;

        $params = [
            'ACTION' => 'SALE',
            'MERCHANTPAYMENTID' => $request->orderNumber,
            'MERCHANTUSER' => $auth->merchantUser,
            'MERCHANTPASSWORD' => $auth->merchantPassword,
            'MERCHANT' => $auth->merchantID,
            'CUSTOMER' => $request->customerIPAddress,
            'CUSTOMERNAME' => $request->saleInfo->cardNameSurname,
            'CUSTOMERIP' => $request->customerIPAddress,
            'CUSTOMEREMAIL' => $request->invoiceInfo?->emailAddress ?? '',
            'CUSTOMERPHONE' => $request->invoiceInfo?->phoneNumber ?? '',
            'CARDPAN' => $request->saleInfo->cardNumber,
            'CARDEXPIRY' => $expiry,
            'CARDCVV' => $request->saleInfo->cardCVV,
            'CURRENCY' => StringHelper::getCurrencyCode($request->saleInfo->currency),
            'AMOUNT' => $amount,
            'INSTALLMENTS' => (string) $request->saleInfo->installment,
        ];

        if (! empty($auth->merchantStorekey)) {
            $params['DEALERTYPENAME'] = $auth->merchantStorekey;
        }

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($dic['pgTranId'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $errorMsg = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? '');
            $errorCode = $dic['errorCode'] ?? '';
            $response->message = ! empty($errorCode) ? $this->getErrorDesc($errorCode) : ($errorMsg ?: 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $apiUrl = $auth->testPlatform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        // Session token al
        $sessionToken = $this->getSessionToken($request, $auth, $apiUrl);
        if (empty($sessionToken)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Oturum anahtarı alınamadı';

            return $response;
        }

        // 3D URL oluştur
        $url3D = $auth->testPlatform ? $this->get3DTestUrl() : $this->get3DLiveUrl();
        $url3D = str_replace('{0}', $sessionToken, $url3D);

        $expiry = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . '.' . $request->saleInfo->cardExpiryDateYear;

        $params = [
            'pan' => $request->saleInfo->cardNumber,
            'expiryMonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'expiryYear' => (string) $request->saleInfo->cardExpiryDateYear,
            'cvv' => $request->saleInfo->cardCVV,
            'installmentCount' => (string) $request->saleInfo->installment,
        ];

        $resp = $this->formRequest($params, $url3D);

        // ThreatMetrix enjeksiyonu
        $orgId = $this->getOnlineMetrixOrgId();
        if ($orgId !== null && ! empty($resp)) {
            $resp = $this->injectOnlineMetrix($resp, $orgId, $sessionToken);
        }

        $response->privateResponse = ['htmlResponse' => substr($resp, 0, 500)];
        $response->statu = SaleResponseStatu::RedirectHTML;
        $response->message = $resp;

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = $request->responseArray;

        $responseCode = (string) ($request->responseArray['responseCode'] ?? '');
        $response->orderNumber = (string) ($request->responseArray['merchantPaymentId'] ?? '');
        $response->transactionId = (string) ($request->responseArray['pgTranId'] ?? '');

        if ($responseCode === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $errorCode = (string) ($request->responseArray['errorCode'] ?? '');
            $errorMsg = $request->responseArray['pgTranErrorText'] ?? ($request->responseArray['errorMsg'] ?? '');
            $response->message = ! empty($errorCode) ? $this->getErrorDesc($errorCode) : ($errorMsg ?: 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $apiUrl = $auth->testPlatform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'VOID',
            'MERCHANT' => $auth->merchantID,
            'MERCHANTUSER' => $auth->merchantUser,
            'MERCHANTPASSWORD' => $auth->merchantPassword,
            'PGTRANID' => $request->transactionId,
            'REFLECTCOMMISSION' => 'No',
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? 'İşlem iptal edilemedi');
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $apiUrl = $auth->testPlatform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'REFUND',
            'MERCHANT' => $auth->merchantID,
            'MERCHANTUSER' => $auth->merchantUser,
            'MERCHANTPASSWORD' => $auth->merchantPassword,
            'PGTRANID' => $request->transactionId,
            'AMOUNT' => StringHelper::formatAmount($request->refundAmount),
            'CURRENCY' => StringHelper::getCurrencyCode($request->currency ?? Currency::TRY),
            'REFLECTCOMMISSION' => 'No',
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? 'İşlem iade edilemedi');
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $apiUrl = $auth->testPlatform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'QUERYBIN',
            'MERCHANT' => $auth->merchantID,
            'MERCHANTUSER' => $auth->merchantUser,
            'MERCHANTPASSWORD' => $auth->merchantPassword,
            'BIN' => $request->BIN,
            'AMOUNT' => StringHelper::formatAmount($request->amount),
            'CURRENCY' => StringHelper::getCurrencyCode($request->currency ?? Currency::TRY),
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->privateResponse = $dic;

        if (($dic['responseCode'] ?? '') === '00' && isset($dic['installmentPaymentPlanList'])) {
            $installmentList = $dic['installmentPaymentPlanList'] ?? [];
            foreach ($installmentList as $item) {
                $count = (int) ($item['count'] ?? 0);
                if ($count > 1) {
                    $rate = (float) ($item['customerCostCommissionRate'] ?? 0);
                    $totalAmount = (float) ($item['totalAmount'] ?? 0);
                    $response->installmentList[] = [
                        'installment' => $count,
                        'rate' => $rate,
                        'totalAmount' => $totalAmount,
                    ];
                }
            }
            if (! empty($response->installmentList)) {
                $response->confirm = true;
            }
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

    // --- Protected/Private helpers ---

    protected function getSessionToken(SaleRequest $request, VirtualPOSAuth $auth, string $apiUrl): string
    {
        $amount = StringHelper::formatAmount($request->saleInfo->amount);

        $params = [
            'ACTION' => 'SESSIONTOKEN',
            'SESSIONTYPE' => 'PAYMENTSESSION',
            'MERCHANT' => $auth->merchantID,
            'MERCHANTUSER' => $auth->merchantUser,
            'MERCHANTPASSWORD' => $auth->merchantPassword,
            'CUSTOMER' => $request->customerIPAddress,
            'CUSTOMERNAME' => $request->saleInfo->cardNameSurname,
            'CUSTOMEREMAIL' => $request->invoiceInfo?->emailAddress ?? '',
            'CUSTOMERIP' => $request->customerIPAddress,
            'CUSTOMERPHONE' => $request->invoiceInfo?->phoneNumber ?? '',
            'MERCHANTPAYMENTID' => $request->orderNumber,
            'AMOUNT' => $amount,
            'CURRENCY' => StringHelper::getCurrencyCode($request->saleInfo->currency ?? Currency::TRY),
            'INSTALLMENTS' => (string) $request->saleInfo->installment,
            'RETURNURL' => $request->payment3D->returnURL,
        ];

        if (! empty($auth->merchantStorekey)) {
            $params['DEALERTYPENAME'] = $auth->merchantStorekey;
        }

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        return $dic['sessionToken'] ?? '';
    }

    protected function injectOnlineMetrix(string $html, string $orgId, string $sessionId): string
    {
        $script = <<<SCRIPT
<p style="background:url(https://h.online-metrix.net/fp/clear.png?org_id={$orgId}&session_id={$sessionId}&m=1)"></p>
<img src="https://h.online-metrix.net/fp/clear.png?org_id={$orgId}&session_id={$sessionId}&m=2" alt="">
<script src="https://h.online-metrix.net/fp/check.js?org_id={$orgId}&session_id={$sessionId}" type="text/javascript"></script>
<object type="application/x-shockwave-flash" data="https://h.online-metrix.net/fp/fp.swf?org_id={$orgId}&session_id={$sessionId}" width="1" height="1"><param name="movie" value="https://h.online-metrix.net/fp/fp.swf?org_id={$orgId}&session_id={$sessionId}"></object>
SCRIPT;

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script . '</body>', $html);
        }

        return $html . $script;
    }

    protected function getErrorDesc(string $errorCode): string
    {
        $brandName = $this->getBrandName();
        $errors = [
            'ERR10147' => $brandName . ' tarafından ödeme alınamadı. İşlem reddedildi.',
            'ERR10153' => $brandName . ' tarafından ödeme alınamadı. Token süresi dolmuş.',
            'ERR10170' => $brandName . ' tarafından ödeme alınamadı. Ödeme zaman aşımına uğradı.',
            'ERR10001' => 'Geçersiz istek. Lütfen parametreleri kontrol ediniz.',
            'ERR10003' => 'Yetkilendirme hatası. Üye işyeri bilgileri hatalı.',
            'ERR10004' => 'İşlem bulunamadı.',
            'ERR10005' => 'İşlem durumu uygun değil.',
            'ERR10006' => 'Geçersiz tutar.',
            'ERR10007' => 'Geçersiz kart numarası.',
            'ERR10008' => 'Geçersiz son kullanma tarihi.',
            'ERR10009' => 'Geçersiz CVV.',
            'ERR10010' => 'Geçersiz taksit sayısı.',
            'ERR10011' => 'Geçersiz para birimi.',
        ];

        return $errors[$errorCode] ?? $brandName . ' tarafından ödeme alınamadı. Hata kodu: ' . $errorCode;
    }

    protected function formRequest(array $params, string $url): string
    {
        try {
            $client = new Client(['verify' => false]);
            $response = $client->post($url, ['form_params' => $params]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
