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
use GuzzleHttp\Client;

class DenizbankGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://test.inter-vpos.com.tr/mpi/Default.aspx';

    private string $urlAPILive = 'https://inter-vpos.com.tr/mpi/Default.aspx';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $req = [
            'ShopCode' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'PurchAmount' => StringHelper::formatAmount($request->sale_info->amount),
            'Currency' => (string) $request->sale_info->currency->value,
            'OrderId' => $request->order_number,
            'TxnType' => 'Auth',
            'InstallmentCount' => $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '0',
            'SecureType' => 'NonSecure',
            'Pan' => $request->sale_info->card_number,
            'Cvv2' => $request->sale_info->card_cvv,
            'Expiry' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->test_platform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);

        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarıyla tamamlandı';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = ! empty($dic['ErrorMessage']) ? $dic['ErrorMessage'] : 'İşlem sırasında bir hata oluştu';
        }

        if (isset($dic['TransId'])) {
            $response->transaction_id = $dic['TransId'];
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $rnd = str_replace('-', '', bin2hex(random_bytes(16)));

        $req = [
            'ShopCode' => $auth->merchant_id,
            'PurchAmount' => StringHelper::formatAmount($request->sale_info->amount),
            'Currency' => (string) $request->sale_info->currency->value,
            'OrderId' => $request->order_number,
            'OkUrl' => $request->payment_3d->return_url,
            'FailUrl' => $request->payment_3d->return_url,
            'Rnd' => $rnd,
            'TxnType' => 'Auth',
            'InstallmentCount' => $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '0',
            'SecureType' => '3DPay',
            'Pan' => $request->sale_info->card_number,
            'Cvv2' => $request->sale_info->card_cvv,
            'Expiry' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
        ];

        $hashText = $this->sha1Base64(
            $req['ShopCode'] . $req['OrderId'] . $req['PurchAmount'] . $req['OkUrl'] .
                $req['FailUrl'] . $req['TxnType'] . $req['InstallmentCount'] . $req['Rnd'] .
                $auth->merchant_storekey
        );

        $req['Hash'] = $hashText;

        $res = $this->formRequest($req, $auth->test_platform ? $this->urlAPITest : $this->urlAPILive);
        $form = StringHelper::getFormParams($res);

        $response->private_response = $form;

        if (isset($form['ErrorMessage']) || isset($form['ErrorCode'])) {
            $errorMsg = ($form['ErrorCode'] ?? '') . ' - ' . ($form['ErrorMessage'] ?? '');
            $response->status = SaleResponseStatus::Error;
            $response->message = $errorMsg;
        } else {
            $response->status = SaleResponseStatus::RedirectHTML;
            $response->message = $res;
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = $request->responseArray;

        if (isset($request->responseArray['TransId'])) {
            $response->transaction_id = $request->responseArray['TransId'];
        }
        if (isset($request->responseArray['OrderId'])) {
            $response->order_number = $request->responseArray['OrderId'];
        }

        if (($request->responseArray['ProcReturnCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($request->responseArray['ErrorMessage'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $request->responseArray['ErrorMessage'];
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);

        $req = [
            'ShopCode' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'orgOrderId' => $request->order_number,
            'TxnType' => 'Void',
            'SecureType' => 'NonSecure',
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->test_platform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($dic['ErrorMessage'])) {
            $response->message = $dic['ErrorMessage'];
        } else {
            $response->message = 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $req = [
            'ShopCode' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'PurchAmount' => StringHelper::formatAmount($request->refund_amount),
            'Currency' => (string) ($request->currency?->value ?? 949),
            'orgOrderId' => $request->order_number,
            'TxnType' => 'Refund',
            'SecureType' => 'NonSecure',
            'Lang' => 'TR',
        ];

        $res = $this->formRequest($req, $auth->test_platform ? $this->urlAPITest : $this->urlAPILive);
        $dic = $this->parseSemicolonResponse($res);
        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } elseif (! empty($dic['ErrorMessage'])) {
            $response->message = $dic['ErrorMessage'];
        } else {
            $response->message = 'İşlem iade edilemedi';
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
