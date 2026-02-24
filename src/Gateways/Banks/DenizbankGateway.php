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

class DenizbankGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://test.inter-vpos.com.tr/mpi/Default.aspx';

    private string $urlAPILive = 'https://inter-vpos.com.tr/mpi/Default.aspx';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $req = [
            'ShopCode' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'PurchAmount' => StringHelper::formatAmount($request->saleInfo->amount),
            'Currency' => (string) $request->saleInfo->currency->value,
            'OrderId' => $request->orderNumber,
            'TxnType' => 'Auth',
            'InstallmentCount' => $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '0',
            'SecureType' => 'NonSecure',
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
            $response->message = 'İşlem başarıyla tamamlandı';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = ! empty($dic['ErrorMessage']) ? $dic['ErrorMessage'] : 'İşlem sırasında bir hata oluştu';
        }

        if (isset($dic['TransId'])) {
            $response->transactionId = $dic['TransId'];
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $rnd = str_replace('-', '', bin2hex(random_bytes(16)));

        $req = [
            'ShopCode' => $auth->merchantID,
            'PurchAmount' => StringHelper::formatAmount($request->saleInfo->amount),
            'Currency' => (string) $request->saleInfo->currency->value,
            'OrderId' => $request->orderNumber,
            'OkUrl' => $request->payment3D->returnURL,
            'FailUrl' => $request->payment3D->returnURL,
            'Rnd' => $rnd,
            'TxnType' => 'Auth',
            'InstallmentCount' => $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '0',
            'SecureType' => '3DPay',
            'Pan' => $request->saleInfo->cardNumber,
            'Cvv2' => $request->saleInfo->cardCVV,
            'Expiry' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
        ];

        $hashText = $this->sha1Base64(
            $req['ShopCode'] . $req['OrderId'] . $req['PurchAmount'] . $req['OkUrl'] .
                $req['FailUrl'] . $req['TxnType'] . $req['InstallmentCount'] . $req['Rnd'] .
                $auth->merchantStorekey
        );

        $req['Hash'] = $hashText;

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $form = StringHelper::getFormParams($res);

        $response->privateResponse = $form;

        if (isset($form['ErrorMessage']) || isset($form['ErrorCode'])) {
            $errorMsg = ($form['ErrorCode'] ?? '') . ' - ' . ($form['ErrorMessage'] ?? '');
            $response->statu = SaleResponseStatu::Error;
            $response->message = $errorMsg;
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

        if (isset($request->responseArray['TransId'])) {
            $response->transactionId = $request->responseArray['TransId'];
        }
        if (isset($request->responseArray['OrderId'])) {
            $response->orderNumber = $request->responseArray['OrderId'];
        }

        if (($request->responseArray['ProcReturnCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($request->responseArray['ErrorMessage'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['ErrorMessage'];
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
            'ShopCode' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'orgOrderId' => $request->orderNumber,
            'TxnType' => 'Void',
            'SecureType' => 'NonSecure',
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->privateResponse = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($dic['ErrorMessage'])) {
            $response->message = $dic['ErrorMessage'];
        } else {
            $response->message = 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $req = [
            'ShopCode' => $auth->merchantID,
            'UserCode' => $auth->merchantUser,
            'UserPass' => $auth->merchantPassword,
            'PurchAmount' => StringHelper::formatAmount($request->refundAmount),
            'Currency' => (string) ($request->currency?->value ?? 949),
            'orgOrderId' => $request->orderNumber,
            'TxnType' => 'Refund',
            'SecureType' => 'NonSecure',
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->privateResponse = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } elseif (! empty($dic['ErrorMessage'])) {
            $response->message = $dic['ErrorMessage'];
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

    // --- Private Helpers ---

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
