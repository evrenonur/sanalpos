<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\StringHelper;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\Support\MakesHttpRequests;

class GarantiBBVAGateway implements VirtualPOSServiceInterface
{
    use MakesHttpRequests;
    private string $urlAPITest = 'https://sanalposprovtest.garantibbva.com.tr/VPServlet';

    private string $urlAPILive = 'https://sanalposprov.garanti.com.tr/VPServlet';

    private string $url3DTest = 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine';

    private string $url3DLive = 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : '';

        $hashedPassword = strtoupper($this->getSHA1($auth->merchant_password . str_pad((string) ((int) $auth->merchant_user), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(
            $request->order_number . $auth->merchant_user . $request->sale_info->card_number . $amount . $hashedPassword
        ));

        $param = [
            'Mode' => $auth->test_platform ? 'TEST' : 'PROD',
            'Version' => 'v0.00',
            'Terminal' => [
                'ProvUserID' => 'PROVAUT',
                'HashData' => $hash,
                'MerchantID' => $auth->merchant_id,
                'UserID' => 'PROVAUT',
                'ID' => $auth->merchant_user,
            ],
            'Customer' => [
                'IPAddress' => $request->customer_ip_address,
                'EmailAddress' => $request->invoice_info?->email_address ?? '',
            ],
            'Card' => [
                'Number' => $request->sale_info->card_number,
                'ExpireDate' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
                'CVV2' => $request->sale_info->card_cvv,
            ],
            'Order' => [
                'OrderID' => $request->order_number,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type' => 'sales',
                'InstallmentCnt' => (string) $installment,
                'Amount' => $amount,
                'CurrencyCode' => (string) $request->sale_info->currency->value,
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
            ],
        ];

        $xml = StringHelper::toXml($param, 'GVPSRequest', 'utf-8');
        $resp = $this->httpPostRaw($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $xml, ['Content-Type' => 'application/x-www-form-urlencoded']);
        $dic = StringHelper::xmlToDictionary($resp, 'GVPSResponse');
        $dic['originalResponseXML'] = $resp;

        if (isset($dic['Transaction']['Response']['Code'])) {
            if ($dic['Transaction']['Response']['Code'] === '00') {
                return new SaleResponse(
                    status: SaleResponseStatus::Success,
                    message: 'İşlem başarılı',
                    order_number: $request->order_number,
                    transaction_id: $dic['Transaction']['RetrefNum'] ?? '',
                    private_response: $dic,
                );
            }

            return new SaleResponse(
                status: SaleResponseStatus::Error,
                message: $dic['Transaction']['Response']['ErrorMsg'] ?? 'İşlem sırasında bir hata oluştu',
                order_number: $request->order_number,
                private_response: $dic,
            );
        }

        return new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            order_number: $request->order_number,
            private_response: $dic,
        );
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $amount = StringHelper::toKurus($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '';

        $hashedPassword = strtoupper($this->getSHA1($auth->merchant_password . str_pad((string) ((int) $auth->merchant_user), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(
            $auth->merchant_user . $request->order_number . $amount .
                $request->payment_3d->return_url . $request->payment_3d->return_url .
                'sales' . $installment . $auth->merchant_storekey . $hashedPassword
        ));

        $param = [
            'mode' => $auth->test_platform ? 'TEST' : 'PROD',
            'apiversion' => 'v0.01',
            'version' => 'v0.01',
            'secure3dsecuritylevel' => '3D',
            'terminalprovuserid' => 'PROVAUT',
            'terminaluserid' => 'PROVAUT',
            'terminalmerchantid' => $auth->merchant_id,
            'terminalid' => $auth->merchant_user,
            'txntype' => 'sales',
            'txnamount' => $amount,
            'txncurrencycode' => (string) $request->sale_info->currency->value,
            'txninstallmentcount' => $installment,
            'customeripaddress' => $request->customer_ip_address,
            'customeremailaddress' => $request->invoice_info?->email_address ?? '',
            'orderid' => $request->order_number,
            'cardnumber' => $request->sale_info->card_number,
            'cardexpiredatemonth' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'cardexpiredateyear' => substr((string) $request->sale_info->card_expiry_year, 2),
            'cardcvv2' => $request->sale_info->card_cvv,
            'successurl' => $request->payment_3d->return_url,
            'errorurl' => $request->payment_3d->return_url,
            'secure3dhash' => $hash,
        ];

        $resp = $this->httpPostForm($auth->test_platform ? $this->url3DTest : $this->url3DLive, $param);
        $cleanResp = str_replace(' value ="', ' value="', $resp);
        $form = StringHelper::getFormParams($cleanResp);
        $form['originalResponseHTML'] = $resp;

        if (isset($form['response']) && strtolower($form['response']) === 'error') {
            return new SaleResponse(
                status: SaleResponseStatus::Error,
                message: $form['errmsg'] ?? 'İşlem sırasında hata oluştu.',
                order_number: $request->order_number,
                private_response: $form,
            );
        }

        if (str_contains($resp, 'action="' . $request->payment_3d->return_url . '"')) {
            return $this->sale3DResponse(new Sale3DResponse(
                responseArray: $form,
                currency: $request->sale_info->currency,
            ), $auth);
        }

        if ((isset($form['TermUrl']) && isset($form['MD']) && isset($form['PaReq'])) || (str_contains($resp, '<form ') && str_contains($resp, 'action='))) {
            return new SaleResponse(
                status: SaleResponseStatus::RedirectHTML,
                message: $resp,
                order_number: $request->order_number,
                private_response: $form,
            );
        }

        return new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'İşlem sırasında hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            order_number: $request->order_number,
            private_response: $form,
        );
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $ra = $request->responseArray;

        if (! isset($ra['mdstatus']) || $ra['mdstatus'] !== '1') {
            $messages = [
                '0' => '3-D doğrulama başarısız',
                '2' => 'Kart sahibi veya bankası sisteme kayıtlı değil',
                '3' => 'Kartın bankası sisteme kayıtlı değil',
                '4' => 'Doğrulama denemesi, kart sahibi sisteme daha sonra kayıt olmayı seçmiş',
                '5' => 'Doğrulama yapılamıyor',
                '6' => '3-D Secure hatası',
                '7' => 'Sistem hatası',
                '8' => 'Bilinmeyen kart no',
                '9' => 'Üye İşyeri 3D-Secure sistemine kayıtlı değil',
            ];
            $message = $messages[$ra['mdstatus'] ?? ''] ?? '3-D Secure doğrulanamadı';

            return new SaleResponse(
                status: SaleResponseStatus::Error,
                message: $message,
                order_number: $ra['oid'] ?? '',
                private_response: $ra,
            );
        }

        $amount = $ra['txnamount'] ?? '';
        $hashedPassword = strtoupper($this->getSHA1($auth->merchant_password . str_pad((string) ((int) $auth->merchant_user), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(($ra['oid'] ?? '') . $auth->merchant_user . $amount . $hashedPassword));

        $param = [
            'Mode' => $auth->test_platform ? 'TEST' : 'PROD',
            'Version' => 'v0.00',
            'Terminal' => [
                'ProvUserID' => 'PROVAUT',
                'HashData' => $hash,
                'MerchantID' => $auth->merchant_id,
                'UserID' => 'PROVAUT',
                'ID' => $auth->merchant_user,
            ],
            'Customer' => [
                'IPAddress' => $ra['customeripaddress'] ?? '',
                'EmailAddress' => $ra['customeremailaddress'] ?? '',
            ],
            'Card' => ['Number' => '', 'ExpireDate' => '', 'CVV2' => ''],
            'Order' => ['OrderID' => $ra['oid'] ?? '', 'GroupID' => '', 'Description' => ''],
            'Transaction' => [
                'Type' => 'sales',
                'InstallmentCnt' => $ra['txninstallmentcount'] ?? '',
                'Amount' => $amount,
                'CurrencyCode' => $ra['txncurrencycode'] ?? '',
                'CardholderPresentCode' => '13',
                'MotoInd' => 'N',
                'Secure3D' => [
                    'AuthenticationCode' => $ra['cavv'] ?? '',
                    'SecurityLevel' => $ra['eci'] ?? '',
                    'TxnID' => $ra['xid'] ?? '',
                    'Md' => $ra['md'] ?? '',
                ],
            ],
        ];

        $xml = StringHelper::toXml($param, 'GVPSRequest', 'utf-8');
        $resp = $this->httpPostRaw($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $xml, ['Content-Type' => 'application/x-www-form-urlencoded']);
        $dic = StringHelper::xmlToDictionary($resp, 'GVPSResponse');

        if (isset($dic['Transaction']['Response']['Code'])) {
            if ($dic['Transaction']['Response']['Code'] === '00') {
                return new SaleResponse(
                    status: SaleResponseStatus::Success,
                    message: 'İşlem başarılı',
                    order_number: $ra['oid'] ?? '',
                    transaction_id: $dic['Transaction']['RetrefNum'] ?? '',
                    private_response: $dic,
                );
            }

            return new SaleResponse(
                status: SaleResponseStatus::Error,
                message: $dic['Transaction']['Response']['ErrorMsg'] ?? 'İşlem sırasında bir hata oluştu',
                order_number: $ra['oid'] ?? '',
                private_response: $dic,
            );
        }

        return new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            order_number: $ra['oid'] ?? '',
            private_response: $dic,
        );
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        return new CancelResponse(status: ResponseStatus::Error, message: 'Bu banka için iptal metodu tanımlanmamış!');
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        return new RefundResponse(status: ResponseStatus::Error, message: 'Bu banka için iade metodu tanımlanmamış!');
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        return new BINInstallmentQueryResponse(confirm: false);
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

    // --- Private helpers ---


    private function getSHA1(string $data): string
    {
        return hash('sha1', $data);
    }
}
