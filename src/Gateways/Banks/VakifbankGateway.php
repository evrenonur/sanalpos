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

class VakifbankGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';

    private string $urlAPILive = 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';

    private string $url3DTest = 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx';

    private string $url3DLive = 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = StringHelper::formatAmount($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 0;
        $expiry = $request->saleInfo->cardExpiryDateYear . str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);

        $xmlParts = [
            'MerchantId' => $auth->merchantID,
            'Password' => $auth->merchantPassword,
            'TerminalNo' => $auth->merchantUser,
            'TransactionType' => 'Sale',
            'TransactionId' => '',
            'CurrencyAmount' => $amount,
            'CurrencyCode' => (string) $request->saleInfo->currency->value,
            'Pan' => $request->saleInfo->cardNumber,
            'Cvv' => $request->saleInfo->cardCVV,
            'Expiry' => $expiry,
            'OrderId' => $request->orderNumber,
            'ClientIp' => $request->customerIPAddress,
            'TransactionDeviceSource' => '0',
        ];

        if ($installment > 0) {
            $xmlParts['NumberOfInstallments'] = (string) $installment;
        }

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->privateResponse = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $dic['TransactionId'] ?? '';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['ResultDetail'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = StringHelper::formatAmount($request->saleInfo->amount);
        $installment = $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '';
        $expDate = substr((string) $request->saleInfo->cardExpiryDateYear, 2) .
            str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);

        $req = [
            'MerchantId' => $auth->merchantID,
            'MerchantPassword' => $auth->merchantPassword,
            'VerifyEnrollmentRequestId' => bin2hex(random_bytes(16)),
            'Pan' => $request->saleInfo->cardNumber,
            'ExpiryDate' => $expDate,
            'PurchaseAmount' => $amount,
            'Currency' => (string) $request->saleInfo->currency->value,
            'SuccessUrl' => $request->payment3D->returnURL,
            'FailureUrl' => $request->payment3D->returnURL,
            'SessionInfo' => $request->orderNumber,
        ];

        if (! empty($installment)) {
            $req['InstallmentCount'] = $installment;
        }

        $url3D = $auth->testPlatform ? $this->url3DTest : $this->url3DLive;
        $resp = $this->formRequest($req, $url3D);
        $dic = StringHelper::xmlToDictionary($resp);

        $response->privateResponse = $dic;

        $status = $dic['Message']['VERes']['Status'] ?? $dic['IPaySecure']['Message']['VERes']['Status'] ?? '';

        if ($status === 'Y') {
            $pareq = $dic['Message']['VERes']['PaReq'] ?? $dic['IPaySecure']['Message']['VERes']['PaReq'] ?? '';
            $acsUrl = $dic['Message']['VERes']['ACSUrl'] ?? $dic['IPaySecure']['Message']['VERes']['ACSUrl'] ?? '';
            $termUrl = $request->payment3D->returnURL;
            $md = $req['VerifyEnrollmentRequestId'];

            $html = '<html><body onload="document.frm.submit();">';
            $html .= '<form name="frm" method="POST" action="' . htmlspecialchars($acsUrl) . '">';
            $html .= '<input type="hidden" name="PaReq" value="' . htmlspecialchars($pareq) . '">';
            $html .= '<input type="hidden" name="TermUrl" value="' . htmlspecialchars($termUrl) . '">';
            $html .= '<input type="hidden" name="MD" value="' . htmlspecialchars($md) . '">';
            $html .= '</form></body></html>';

            $response->statu = SaleResponseStatu::RedirectHTML;
            $response->message = $html;
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Bu kart 3D Secure ile kullanılamaz';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $status = $request->responseArray['Status'] ?? '';

        if ($status !== 'Y') {
            $response->statu = SaleResponseStatu::Error;
            $response->message = '3D doğrulaması başarısız';

            return $response;
        }

        $orderId = $request->responseArray['SessionInfo'] ?? $request->responseArray['orderNumber'] ?? '';
        $eci = $request->responseArray['Eci'] ?? '';
        $cavv = $request->responseArray['Cavv'] ?? '';
        $mpiTransactionId = $request->responseArray['VerifyEnrollmentRequestId'] ?? '';
        $purchAmount = $request->responseArray['PurchAmount'] ?? '0';
        $amount = StringHelper::formatAmount((float) $purchAmount / 100);
        $installment = $request->responseArray['InstallmentCount'] ?? '';

        $expiry = $request->responseArray['Expiry'] ?? '';
        if (strlen($expiry) === 4) {
            $expiry = '20' . $expiry;
        }

        $xmlParts = [
            'MerchantId' => $auth->merchantID,
            'Password' => $auth->merchantPassword,
            'TerminalNo' => $auth->merchantUser,
            'TransactionType' => 'Sale',
            'TransactionId' => '',
            'CurrencyAmount' => $amount,
            'CurrencyCode' => (string) ($request->currency?->value ?? 949),
            'Pan' => $request->responseArray['Pan'] ?? '',
            'Cvv' => '',
            'Expiry' => $expiry,
            'OrderId' => $orderId,
            'ECI' => $eci,
            'CAVV' => $cavv,
            'MpiTransactionId' => $mpiTransactionId,
            'ClientIp' => '1.1.1.1',
            'TransactionDeviceSource' => '0',
        ];

        if (! empty($installment) && $installment !== '0') {
            $xmlParts['NumberOfInstallments'] = $installment;
        }

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->privateResponse['response_2'] = $dic;
        $response->orderNumber = $orderId;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $dic['TransactionId'] ?? '';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['ResultDetail'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);

        $xmlParts = [
            'MerchantId' => $auth->merchantID,
            'Password' => $auth->merchantPassword,
            'TerminalNo' => $auth->merchantUser,
            'TransactionType' => 'Cancel',
            'ReferenceTransactionId' => $request->transactionId,
            'ClientIp' => $request->customerIPAddress,
        ];

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->privateResponse = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['ResultDetail'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $xmlParts = [
            'MerchantId' => $auth->merchantID,
            'Password' => $auth->merchantPassword,
            'TerminalNo' => $auth->merchantUser,
            'TransactionType' => 'Refund',
            'ReferenceTransactionId' => $request->transactionId,
            'CurrencyAmount' => StringHelper::formatAmount($request->refundAmount),
            'ClientIp' => $request->customerIPAddress,
        ];

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->privateResponse = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['ResultDetail'] ?? 'İşlem iade edilemedi';
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

    private function prmStrRequest(string $xml, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'form_params' => ['prmstr' => $xml],
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
