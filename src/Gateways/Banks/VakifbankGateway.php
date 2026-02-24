<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

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
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\MakesHttpRequests;
use EvrenOnur\SanalPos\Support\StringHelper;

class VakifbankGateway implements VirtualPOSServiceInterface
{
    use MakesHttpRequests;

    private string $urlAPITest = 'https://onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';

    private string $urlAPILive = 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';

    private string $url3DTest = 'https://3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx';

    private string $url3DLive = 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::formatAmount($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $expiry = $request->sale_info->card_expiry_year . str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);

        $xmlParts = [
            'MerchantId' => $auth->merchant_id,
            'Password' => $auth->merchant_password,
            'TerminalNo' => $auth->merchant_user,
            'TransactionType' => 'Sale',
            'TransactionId' => '',
            'CurrencyAmount' => $amount,
            'CurrencyCode' => (string) $request->sale_info->currency->value,
            'Pan' => $request->sale_info->card_number,
            'Cvv' => $request->sale_info->card_cvv,
            'Expiry' => $expiry,
            'OrderId' => $request->order_number,
            'ClientIp' => $request->customer_ip_address,
            'TransactionDeviceSource' => '0',
        ];

        if ($installment > 0) {
            $xmlParts['NumberOfInstallments'] = (string) $installment;
        }

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->private_response = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $dic['TransactionId'] ?? '';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['ResultDetail'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::formatAmount($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '';
        $expDate = substr((string) $request->sale_info->card_expiry_year, 2) .
            str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);

        $req = [
            'MerchantId' => $auth->merchant_id,
            'MerchantPassword' => $auth->merchant_password,
            'VerifyEnrollmentRequestId' => bin2hex(random_bytes(16)),
            'Pan' => $request->sale_info->card_number,
            'ExpiryDate' => $expDate,
            'PurchaseAmount' => $amount,
            'Currency' => (string) $request->sale_info->currency->value,
            'SuccessUrl' => $request->payment_3d->return_url,
            'FailureUrl' => $request->payment_3d->return_url,
            'SessionInfo' => $request->order_number,
        ];

        if (! empty($installment)) {
            $req['InstallmentCount'] = $installment;
        }

        $url3D = $auth->test_platform ? $this->url3DTest : $this->url3DLive;
        $resp = $this->httpPostForm($url3D, $req);
        $dic = StringHelper::xmlToDictionary($resp);

        $response->private_response = $dic;

        $status = $dic['Message']['VERes']['Status'] ?? $dic['IPaySecure']['Message']['VERes']['Status'] ?? '';

        if ($status === 'Y') {
            $pareq = $dic['Message']['VERes']['PaReq'] ?? $dic['IPaySecure']['Message']['VERes']['PaReq'] ?? '';
            $acsUrl = $dic['Message']['VERes']['ACSUrl'] ?? $dic['IPaySecure']['Message']['VERes']['ACSUrl'] ?? '';
            $termUrl = $request->payment_3d->return_url;
            $md = $req['VerifyEnrollmentRequestId'];

            $html = '<html><body onload="document.frm.submit();">';
            $html .= '<form name="frm" method="POST" action="' . htmlspecialchars($acsUrl) . '">';
            $html .= '<input type="hidden" name="PaReq" value="' . htmlspecialchars($pareq) . '">';
            $html .= '<input type="hidden" name="TermUrl" value="' . htmlspecialchars($termUrl) . '">';
            $html .= '<input type="hidden" name="MD" value="' . htmlspecialchars($md) . '">';
            $html .= '</form></body></html>';

            $response->status = SaleResponseStatus::RedirectHTML;
            $response->message = $html;
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Bu kart 3D Secure ile kullanılamaz';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $status = $request->responseArray['Status'] ?? '';

        if ($status !== 'Y') {
            $response->status = SaleResponseStatus::Error;
            $response->message = '3D doğrulaması başarısız';

            return $response;
        }

        $orderId = $request->responseArray['SessionInfo'] ?? $request->responseArray['order_number'] ?? '';
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
            'MerchantId' => $auth->merchant_id,
            'Password' => $auth->merchant_password,
            'TerminalNo' => $auth->merchant_user,
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
        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->private_response['response_2'] = $dic;
        $response->order_number = $orderId;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $dic['TransactionId'] ?? '';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['ResultDetail'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);

        $xmlParts = [
            'MerchantId' => $auth->merchant_id,
            'Password' => $auth->merchant_password,
            'TerminalNo' => $auth->merchant_user,
            'TransactionType' => 'Cancel',
            'ReferenceTransactionId' => $request->transaction_id,
            'ClientIp' => $request->customer_ip_address,
        ];

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->private_response = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['ResultDetail'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $xmlParts = [
            'MerchantId' => $auth->merchant_id,
            'Password' => $auth->merchant_password,
            'TerminalNo' => $auth->merchant_user,
            'TransactionType' => 'Refund',
            'ReferenceTransactionId' => $request->transaction_id,
            'CurrencyAmount' => StringHelper::formatAmount($request->refund_amount),
            'ClientIp' => $request->customer_ip_address,
        ];

        $xml = StringHelper::toXml($xmlParts, 'VposRequest');
        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->prmStrRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'VposResponse');

        $response->private_response = $dic;

        if (($dic['ResultCode'] ?? '') === '0000') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $dic['ResultDetail'] ?? 'İşlem iade edilemedi';
        }

        return $response;
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

    private function prmStrRequest(string $xml, string $url): string
    {
        return $this->httpPostForm($url, ['prmstr' => $xml]);
    }
}
