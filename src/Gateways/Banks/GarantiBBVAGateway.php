<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

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

class GarantiBBVAGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://sanalposprovtest.garantibbva.com.tr/VPServlet';

    private string $urlAPILive = 'https://sanalposprov.garanti.com.tr/VPServlet';

    private string $url3DTest = 'https://sanalposprovtest.garantibbva.com.tr/servlet/gt3dengine';

    private string $url3DLive = 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $amount = $this->to2Digit($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : '';

        $hashedPassword = strtoupper($this->getSHA1($auth->merchantPassword . str_pad((string) ((int) $auth->merchantUser), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(
            $request->orderNumber . $auth->merchantUser . $request->saleInfo->cardNumber . $amount . $hashedPassword
        ));

        $param = [
            'Mode' => $auth->testPlatform ? 'TEST' : 'PROD',
            'Version' => 'v0.00',
            'Terminal' => [
                'ProvUserID' => 'PROVAUT',
                'HashData' => $hash,
                'MerchantID' => $auth->merchantID,
                'UserID' => 'PROVAUT',
                'ID' => $auth->merchantUser,
            ],
            'Customer' => [
                'IPAddress' => $request->customerIPAddress,
                'EmailAddress' => $request->invoiceInfo?->emailAddress ?? '',
            ],
            'Card' => [
                'Number' => $request->saleInfo->cardNumber,
                'ExpireDate' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
                'CVV2' => $request->saleInfo->cardCVV,
            ],
            'Order' => [
                'OrderID' => $request->orderNumber,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type' => 'sales',
                'InstallmentCnt' => (string) $installment,
                'Amount' => $amount,
                'CurrencyCode' => (string) $request->saleInfo->currency->value,
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
            ],
        ];

        $xml = StringHelper::toXml($param, 'GVPSRequest', 'utf-8');
        $resp = $this->xmlRequest($xml, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = StringHelper::xmlToDictionary($resp, 'GVPSResponse');
        $dic['originalResponseXML'] = $resp;

        if (isset($dic['Transaction']['Response']['Code'])) {
            if ($dic['Transaction']['Response']['Code'] === '00') {
                return new SaleResponse(
                    statu: SaleResponseStatu::Success,
                    message: 'İşlem başarılı',
                    orderNumber: $request->orderNumber,
                    transactionId: $dic['Transaction']['RetrefNum'] ?? '',
                    privateResponse: $dic,
                );
            }

            return new SaleResponse(
                statu: SaleResponseStatu::Error,
                message: $dic['Transaction']['Response']['ErrorMsg'] ?? 'İşlem sırasında bir hata oluştu',
                orderNumber: $request->orderNumber,
                privateResponse: $dic,
            );
        }

        return new SaleResponse(
            statu: SaleResponseStatu::Error,
            message: 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            orderNumber: $request->orderNumber,
            privateResponse: $dic,
        );
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $amount = $this->to2Digit($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '';

        $hashedPassword = strtoupper($this->getSHA1($auth->merchantPassword . str_pad((string) ((int) $auth->merchantUser), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(
            $auth->merchantUser . $request->orderNumber . $amount .
                $request->payment3D->returnURL . $request->payment3D->returnURL .
                'sales' . $installment . $auth->merchantStorekey . $hashedPassword
        ));

        $param = [
            'mode' => $auth->testPlatform ? 'TEST' : 'PROD',
            'apiversion' => 'v0.01',
            'version' => 'v0.01',
            'secure3dsecuritylevel' => '3D',
            'terminalprovuserid' => 'PROVAUT',
            'terminaluserid' => 'PROVAUT',
            'terminalmerchantid' => $auth->merchantID,
            'terminalid' => $auth->merchantUser,
            'txntype' => 'sales',
            'txnamount' => $amount,
            'txncurrencycode' => (string) $request->saleInfo->currency->value,
            'txninstallmentcount' => $installment,
            'customeripaddress' => $request->customerIPAddress,
            'customeremailaddress' => $request->invoiceInfo?->emailAddress ?? '',
            'orderid' => $request->orderNumber,
            'cardnumber' => $request->saleInfo->cardNumber,
            'cardexpiredatemonth' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'cardexpiredateyear' => substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            'cardcvv2' => $request->saleInfo->cardCVV,
            'successurl' => $request->payment3D->returnURL,
            'errorurl' => $request->payment3D->returnURL,
            'secure3dhash' => $hash,
        ];

        $resp = $this->formRequest($param, $auth->testPlatform ? $this->url3DTest : $this->url3DLive);
        $cleanResp = str_replace(' value ="', ' value="', $resp);
        $form = StringHelper::getFormParams($cleanResp);
        $form['originalResponseHTML'] = $resp;

        if (isset($form['response']) && strtolower($form['response']) === 'error') {
            return new SaleResponse(
                statu: SaleResponseStatu::Error,
                message: $form['errmsg'] ?? 'İşlem sırasında hata oluştu.',
                orderNumber: $request->orderNumber,
                privateResponse: $form,
            );
        }

        if (str_contains($resp, 'action="' . $request->payment3D->returnURL . '"')) {
            return $this->sale3DResponse(new Sale3DResponseRequest(
                responseArray: $form,
                currency: $request->saleInfo->currency,
            ), $auth);
        }

        if ((isset($form['TermUrl']) && isset($form['MD']) && isset($form['PaReq'])) || (str_contains($resp, '<form ') && str_contains($resp, 'action='))) {
            return new SaleResponse(
                statu: SaleResponseStatu::RedirectHTML,
                message: $resp,
                orderNumber: $request->orderNumber,
                privateResponse: $form,
            );
        }

        return new SaleResponse(
            statu: SaleResponseStatu::Error,
            message: 'İşlem sırasında hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            orderNumber: $request->orderNumber,
            privateResponse: $form,
        );
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
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
                statu: SaleResponseStatu::Error,
                message: $message,
                orderNumber: $ra['oid'] ?? '',
                privateResponse: $ra,
            );
        }

        $amount = $ra['txnamount'] ?? '';
        $hashedPassword = strtoupper($this->getSHA1($auth->merchantPassword . str_pad((string) ((int) $auth->merchantUser), 9, '0', STR_PAD_LEFT)));
        $hash = strtoupper($this->getSHA1(($ra['oid'] ?? '') . $auth->merchantUser . $amount . $hashedPassword));

        $param = [
            'Mode' => $auth->testPlatform ? 'TEST' : 'PROD',
            'Version' => 'v0.00',
            'Terminal' => [
                'ProvUserID' => 'PROVAUT',
                'HashData' => $hash,
                'MerchantID' => $auth->merchantID,
                'UserID' => 'PROVAUT',
                'ID' => $auth->merchantUser,
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
        $resp = $this->xmlRequest($xml, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = StringHelper::xmlToDictionary($resp, 'GVPSResponse');

        if (isset($dic['Transaction']['Response']['Code'])) {
            if ($dic['Transaction']['Response']['Code'] === '00') {
                return new SaleResponse(
                    statu: SaleResponseStatu::Success,
                    message: 'İşlem başarılı',
                    orderNumber: $ra['oid'] ?? '',
                    transactionId: $dic['Transaction']['RetrefNum'] ?? '',
                    privateResponse: $dic,
                );
            }

            return new SaleResponse(
                statu: SaleResponseStatu::Error,
                message: $dic['Transaction']['Response']['ErrorMsg'] ?? 'İşlem sırasında bir hata oluştu',
                orderNumber: $ra['oid'] ?? '',
                privateResponse: $dic,
            );
        }

        return new SaleResponse(
            statu: SaleResponseStatu::Error,
            message: 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.',
            orderNumber: $ra['oid'] ?? '',
            privateResponse: $dic,
        );
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        return new CancelResponse(statu: ResponseStatu::Error, message: 'Bu banka için iptal metodu tanımlanmamış!');
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        return new RefundResponse(statu: ResponseStatu::Error, message: 'Bu banka için iade metodu tanımlanmamış!');
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

    /** Garanti BBVA formatı: 100.50 → "10050" (kuruş) */
    private function to2Digit(float $amount): string
    {
        return str_replace([',', '.'], '', number_format($amount, 2, '.', ''));
    }

    private function getSHA1(string $data): string
    {
        return hash('sha1', $data);
    }

    private function xmlRequest(string $xml, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'body' => $xml,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        return $response->getBody()->getContents();
    }

    private function formRequest(array $params, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, ['form_params' => $params]);

        return $response->getBody()->getContents();
    }
}
