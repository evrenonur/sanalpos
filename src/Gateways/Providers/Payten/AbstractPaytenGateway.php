<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\Payten;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\MakesHttpRequests;
use EvrenOnur\SanalPos\Support\StringHelper;

abstract class AbstractPaytenGateway implements VirtualPOSServiceInterface
{
    use MakesHttpRequests;

    abstract protected function getApiTestUrl(): string;

    abstract protected function getApiLiveUrl(): string;

    abstract protected function get3DTestUrl(): string;

    abstract protected function get3DLiveUrl(): string;

    abstract protected function getBrandName(): string;

    /**
     * ThreatMetrix org_id â€” null ise enjeksiyon yapılmaz.
     */
    protected function getOnlineMetrixOrgId(): ?string
    {
        return null;
    }

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $request->sale_info->currency = $request->sale_info->currency ?? Currency::TRY;
        $request->sale_info->installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;

        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);
        $apiUrl = $auth->test_platform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $amount = StringHelper::formatAmount($request->sale_info->amount);
        $expiry = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . '.' . $request->sale_info->card_expiry_year;

        $params = [
            'ACTION' => 'SALE',
            'MERCHANTPAYMENTID' => $request->order_number,
            'MERCHANTUSER' => $auth->merchant_user,
            'MERCHANTPASSWORD' => $auth->merchant_password,
            'MERCHANT' => $auth->merchant_id,
            'CUSTOMER' => $request->customer_ip_address,
            'CUSTOMERNAME' => $request->sale_info->card_name_surname,
            'CUSTOMERIP' => $request->customer_ip_address,
            'CUSTOMEREMAIL' => $request->invoice_info?->email_address ?? '',
            'CUSTOMERPHONE' => $request->invoice_info?->phone_number ?? '',
            'CARDPAN' => $request->sale_info->card_number,
            'CARDEXPIRY' => $expiry,
            'CARDCVV' => $request->sale_info->card_cvv,
            'CURRENCY' => StringHelper::getCurrencyCode($request->sale_info->currency),
            'AMOUNT' => $amount,
            'INSTALLMENTS' => (string) $request->sale_info->installment,
        ];

        if (! empty($auth->merchant_storekey)) {
            $params['DEALERTYPENAME'] = $auth->merchant_storekey;
        }

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['pgTranId'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $errorMsg = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? '');
            $errorCode = $dic['errorCode'] ?? '';
            $response->message = ! empty($errorCode) ? $this->getErrorDesc($errorCode) : ($errorMsg ?: 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $apiUrl = $auth->test_platform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        // Session token al
        $sessionToken = $this->getSessionToken($request, $auth, $apiUrl);
        if (empty($sessionToken)) {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Oturum anahtarı alınamadı';

            return $response;
        }

        // 3D URL oluştur
        $url3D = $auth->test_platform ? $this->get3DTestUrl() : $this->get3DLiveUrl();
        $url3D = str_replace('{0}', $sessionToken, $url3D);

        $expiry = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . '.' . $request->sale_info->card_expiry_year;

        $params = [
            'pan' => $request->sale_info->card_number,
            'expiryMonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'expiryYear' => (string) $request->sale_info->card_expiry_year,
            'cvv' => $request->sale_info->card_cvv,
            'installmentCount' => (string) $request->sale_info->installment,
        ];

        $resp = $this->formRequest($params, $url3D);

        // ThreatMetrix enjeksiyonu
        $orgId = $this->getOnlineMetrixOrgId();
        if ($orgId !== null && ! empty($resp)) {
            $resp = $this->injectOnlineMetrix($resp, $orgId, $sessionToken);
        }

        $response->private_response = ['htmlResponse' => substr($resp, 0, 500)];
        $response->status = SaleResponseStatus::RedirectHTML;
        $response->message = $resp;

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = $request->responseArray;

        $responseCode = (string) ($request->responseArray['responseCode'] ?? '');
        $response->order_number = (string) ($request->responseArray['merchantPaymentId'] ?? '');
        $response->transaction_id = (string) ($request->responseArray['pgTranId'] ?? '');

        if ($responseCode === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->status = SaleResponseStatus::Error;
            $errorCode = (string) ($request->responseArray['errorCode'] ?? '');
            $errorMsg = $request->responseArray['pgTranErrorText'] ?? ($request->responseArray['errorMsg'] ?? '');
            $response->message = ! empty($errorCode) ? $this->getErrorDesc($errorCode) : ($errorMsg ?: 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $apiUrl = $auth->test_platform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'VOID',
            'MERCHANT' => $auth->merchant_id,
            'MERCHANTUSER' => $auth->merchant_user,
            'MERCHANTPASSWORD' => $auth->merchant_password,
            'PGTRANID' => $request->transaction_id,
            'REFLECTCOMMISSION' => 'No',
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? 'İşlem iptal edilemedi');
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $apiUrl = $auth->test_platform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'REFUND',
            'MERCHANT' => $auth->merchant_id,
            'MERCHANTUSER' => $auth->merchant_user,
            'MERCHANTPASSWORD' => $auth->merchant_password,
            'PGTRANID' => $request->transaction_id,
            'AMOUNT' => StringHelper::formatAmount($request->refund_amount),
            'CURRENCY' => StringHelper::getCurrencyCode($request->currency ?? Currency::TRY),
            'REFLECTCOMMISSION' => 'No',
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['responseCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $dic['responseMsg'] ?? ($dic['errorMsg'] ?? 'İşlem iade edilemedi');
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $apiUrl = $auth->test_platform ? $this->getApiTestUrl() : $this->getApiLiveUrl();

        $params = [
            'ACTION' => 'QUERYBIN',
            'MERCHANT' => $auth->merchant_id,
            'MERCHANTUSER' => $auth->merchant_user,
            'MERCHANTPASSWORD' => $auth->merchant_password,
            'BIN' => $request->BIN,
            'AMOUNT' => StringHelper::formatAmount($request->amount),
            'CURRENCY' => StringHelper::getCurrencyCode($request->currency ?? Currency::TRY),
        ];

        $resp = $this->formRequest($params, $apiUrl);
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if (($dic['responseCode'] ?? '') === '00' && isset($dic['installmentPaymentPlanList'])) {
            $installment_list = $dic['installmentPaymentPlanList'] ?? [];
            foreach ($installment_list as $item) {
                $count = (int) ($item['count'] ?? 0);
                if ($count > 1) {
                    $rate = (float) ($item['customerCostCommissionRate'] ?? 0);
                    $totalAmount = (float) ($item['totalAmount'] ?? 0);
                    $response->installment_list[] = [
                        'installment' => $count,
                        'rate' => $rate,
                        'totalAmount' => $totalAmount,
                    ];
                }
            }
            if (! empty($response->installment_list)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse
    {
        return new AllInstallmentQueryResponse(confirm: false);
    }

    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, MerchantAuth $auth): AdditionalInstallmentQueryResponse
    {
        return new AdditionalInstallmentQueryResponse(confirm: false);
    }

    public function saleQuery(SaleQueryRequest $request, MerchantAuth $auth): SaleQueryResponse
    {
        return new SaleQueryResponse(status: SaleQueryResponseStatus::Error, message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor');
    }

    // --- Protected/Private helpers ---

    protected function getSessionToken(SaleRequest $request, MerchantAuth $auth, string $apiUrl): string
    {
        $amount = StringHelper::formatAmount($request->sale_info->amount);

        $params = [
            'ACTION' => 'SESSIONTOKEN',
            'SESSIONTYPE' => 'PAYMENTSESSION',
            'MERCHANT' => $auth->merchant_id,
            'MERCHANTUSER' => $auth->merchant_user,
            'MERCHANTPASSWORD' => $auth->merchant_password,
            'CUSTOMER' => $request->customer_ip_address,
            'CUSTOMERNAME' => $request->sale_info->card_name_surname,
            'CUSTOMEREMAIL' => $request->invoice_info?->email_address ?? '',
            'CUSTOMERIP' => $request->customer_ip_address,
            'CUSTOMERPHONE' => $request->invoice_info?->phone_number ?? '',
            'MERCHANTPAYMENTID' => $request->order_number,
            'AMOUNT' => $amount,
            'CURRENCY' => StringHelper::getCurrencyCode($request->sale_info->currency ?? Currency::TRY),
            'INSTALLMENTS' => (string) $request->sale_info->installment,
            'RETURNURL' => $request->payment_3d->return_url,
        ];

        if (! empty($auth->merchant_storekey)) {
            $params['DEALERTYPENAME'] = $auth->merchant_storekey;
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
        return $this->httpPostForm($url, $params);
    }
}
