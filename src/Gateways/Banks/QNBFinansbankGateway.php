<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class QNBFinansbankGateway extends AbstractGateway
{

    private string $urlAPITest = 'https://vpostest.qnbfinansbank.com/Gateway/Default.aspx';

    private string $urlAPILive = 'https://vpos.qnbfinansbank.com/Gateway/Default.aspx';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'TxnType' => 'Auth',
            'SecureType' => 'NonSecure',
            'InstallmentCount' => $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '0',
            'PurchAmount' => StringHelper::formatAmount($request->sale_info->amount),
            'Currency' => (string) $request->sale_info->currency->value,
            'OrderId' => $request->order_number,
            'OrgOrderId' => '',
            'Pan' => $request->sale_info->card_number,
            'Cvv2' => $request->sale_info->card_cvv,
            'Expiry' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
            'Lang' => 'TR',
        ];

        $res = $this->httpPostForm($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $req);
        $dic = StringHelper::parseSemicolonResponse($res);

        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $dic['AuthCode'] ?? '';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = ! empty($dic['ErrMsg']) ? $dic['ErrMsg'] : 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $rnd = str_replace('-', '', bin2hex(random_bytes(16)));
        $installment = $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '0';

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'TxnType' => 'Auth',
            'SecureType' => '3DPay',
            'InstallmentCount' => $installment,
            'PurchAmount' => StringHelper::formatAmount($request->sale_info->amount),
            'Currency' => (string) $request->sale_info->currency->value,
            'OrderId' => $request->order_number,
            'OkUrl' => $request->payment_3d->return_url,
            'FailUrl' => $request->payment_3d->return_url,
            'Rnd' => $rnd,
            'Pan' => $request->sale_info->card_number,
            'Cvv2' => $request->sale_info->card_cvv,
            'Expiry' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
            'Lang' => 'TR',
            'Hash' => '',
        ];

        $hashText = StringHelper::sha1Base64(
            $req['MbrId'] . $req['OrderId'] . $req['PurchAmount'] .
                $req['OkUrl'] . $req['FailUrl'] . $req['TxnType'] .
                $req['InstallmentCount'] . $req['Rnd'] . $auth->merchant_storekey
        );

        $req['Hash'] = $hashText;

        $res = $this->httpPostForm($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $req);
        $form = StringHelper::getFormParams($res);

        $response->private_response = $form;

        if (isset($form['ErrMsg']) || isset($form['ErrorCode'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = ($form['ErrorCode'] ?? '') . ' - ' . ($form['ErrMsg'] ?? '');
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
        $response->order_number = $request->responseArray['OrderId'] ?? '';
        $response->transaction_id = $request->responseArray['AuthCode'] ?? '';

        if (($request->responseArray['ProcReturnCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($request->responseArray['ErrMsg'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $request->responseArray['ErrMsg'];
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
            'MbrId' => '5',
            'MerchantId' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'TxnType' => 'Void',
            'SecureType' => 'NonSecure',
            'OrgOrderId' => $request->order_number,
            'Lang' => 'TR',
        ];

        $res = $this->httpPostForm($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $req);
        $dic = StringHelper::parseSemicolonResponse($res);
        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($dic['ErrMsg'])) {
            $response->message = $dic['ErrMsg'];
        } else {
            $response->message = 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $req = [
            'MbrId' => '5',
            'MerchantId' => $auth->merchant_id,
            'UserCode' => $auth->merchant_user,
            'UserPass' => $auth->merchant_password,
            'TxnType' => 'Refund',
            'SecureType' => 'NonSecure',
            'PurchAmount' => StringHelper::formatAmount($request->refund_amount),
            'Currency' => (string) ($request->currency?->value ?? 949),
            'OrgOrderId' => $request->order_number,
            'Lang' => 'TR',
        ];

        $res = $this->httpPostForm($auth->test_platform ? $this->urlAPITest : $this->urlAPILive, $req);
        $dic = StringHelper::parseSemicolonResponse($res);
        $response->private_response = $dic;

        if (($dic['ProcReturnCode'] ?? '') === '00') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } elseif (! empty($dic['ErrMsg'])) {
            $response->message = $dic['ErrMsg'];
        } else {
            $response->message = 'İşlem iade edilemedi';
        }

        return $response;
    }

    // --- Private helpers ---

    // sha1Base64, parseSemicolonResponse ve formRequest
    // StringHelper ve MakesHttpRequests trait'ü üzerinden sağlanır.
}
