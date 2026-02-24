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

class QNBFinansbankGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx';

    private string $urlAPILive = 'https://vpos.qnbfinansbank.com/Gateway/Default.aspx';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'TxnType' => 'Auth',
            'SecureType' => 'NonSecure',
            'InstallmentCount' => $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '0',
            'PurchAmount' => StringHelper::formatAmount($request->saleInfo->amount),
            'Currency' => (string) $request->saleInfo->currency->value,
            'OrderId' => $request->orderNumber,
            'OrgOrderId' => '',
            'Pan' => $request->saleInfo->cardNumber,
            'Cvv2' => $request->saleInfo->cardCVV,
            'Expiry' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);

        $response->privateResponse = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $dic['AuthCode'] ?? '';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = ! empty($dic['ErrMsg']) ? $dic['ErrMsg'] : 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $rnd = str_replace('-', '', bin2hex(random_bytes(16)));
        $installment = $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '0';

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'TxnType' => 'Auth',
            'SecureType' => '3DPay',
            'InstallmentCount' => $installment,
            'PurchAmount' => StringHelper::formatAmount($request->saleInfo->amount),
            'Currency' => (string) $request->saleInfo->currency->value,
            'OrderId' => $request->orderNumber,
            'OkUrl' => $request->payment3D->returnURL,
            'FailUrl' => $request->payment3D->returnURL,
            'Rnd' => $rnd,
            'Pan' => $request->saleInfo->cardNumber,
            'Cvv2' => $request->saleInfo->cardCVV,
            'Expiry' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            'Lang' => 'TR',
            'Hash' => '',
        ];

        $hashText = $this->sha1Base64(
            $req['MbrId'] . $req['OrderId'] . $req['PurchAmount'] .
                $req['OkUrl'] . $req['FailUrl'] . $req['TxnType'] .
                $req['InstallmentCount'] . $req['Rnd'] . $auth->merchantStorekey
        );

        $req['Hash'] = $hashText;

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $form = StringHelper::getFormParams($res);

        $response->privateResponse = $form;

        if (isset($form['ErrMsg']) || isset($form['ErrorCode'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = ($form['ErrorCode'] ?? '') . ' - ' . ($form['ErrMsg'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::RedirectHTML;
            $response->message = $res;
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = $request->responseArray;
        $response->orderNumber = $request->responseArray['OrderId'] ?? '';
        $response->transactionId = $request->responseArray['AuthCode'] ?? '';

        if (($request->responseArray['ProcReturnCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($request->responseArray['ErrMsg'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['ErrMsg'];
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'TxnType' => 'Void',
            'SecureType' => 'NonSecure',
            'OrgOrderId' => $request->orderNumber,
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->privateResponse = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($dic['ErrMsg'])) {
            $response->message = $dic['ErrMsg'];
        } else {
            $response->message = 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'TxnType' => 'Refund',
            'SecureType' => 'NonSecure',
            'PurchAmount' => StringHelper::formatAmount($request->refundAmount),
            'Currency' => (string) ($request->currency?->value ?? 949),
            'OrgOrderId' => $request->orderNumber,
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->privateResponse = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } elseif (! empty($dic['ErrMsg'])) {
            $response->message = $dic['ErrMsg'];
        } else {
            $response->message = 'İşlem iade edilemedi';
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

    private function sha1Base64(string $text): string
    {
        return base64_encode(hash('sha1', $text, true));
    }

    private function parseSemicolonResponse(string $response): array
    {
        $dic = [];
        $parts = array_filter(explode(';;', $response));
        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $dic[$kv[0]] = $kv[1];
            }
        }

        return $dic;
    }

    private function formRequest(array $params, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, ['form_params' => $params]);

        return $response->getBody()->getContents();
    }
}
