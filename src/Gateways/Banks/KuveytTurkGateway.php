<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class KuveytTurkGateway extends AbstractGateway
{
    private string $urlNon3DTest = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate';

    private string $urlNon3DLive = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate';

    private string $url3DTest = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate';

    private string $url3DLive = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate';

    private string $url3DProvisionTest = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate';

    private string $url3DProvisionLive = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64($auth->merchant_id . $request->order_number . $amount . $auth->merchant_user . $hashedPassword);

        $xml = $this->buildSaleXml($request, $auth, $hash, $amount, 1);

        $url = $auth->test_platform ? $this->urlNon3DTest : $this->urlNon3DLive;
        $resp = $this->httpPostXml($url, $xml);
        $dic = StringHelper::xmlToDictionary($resp, 'VPosTransactionResponseContract');

        $response->private_response = $dic;

        if (($dic['ResponseCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = ($dic['ProvisionNumber'] ?? '') . '|' . ($dic['OrderId'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64(
            $auth->merchant_id . $request->order_number . $amount .
                $request->payment_3d->return_url . $request->payment_3d->return_url .
                $auth->merchant_user . $hashedPassword
        );

        $xml = $this->buildSaleXml($request, $auth, $hash, $amount, 3);

        $url = $auth->test_platform ? $this->url3DTest : $this->url3DLive;
        $resp = $this->httpPostXml($url, $xml);

        $response->private_response = ['htmlResponse' => $resp];

        if (str_contains($resp, 'form') && str_contains($resp, 'action')) {
            $response->status = SaleResponseStatus::RedirectHTML;
            $response->message = $resp;
        } else {
            $dic = StringHelper::xmlToDictionary($resp, 'VPosTransactionResponseContract');
            $response->private_response = $dic;
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $authResponse = $request->responseArray['AuthenticationResponse'] ?? '';
        if (! empty($authResponse)) {
            $authResponse = urldecode($authResponse);
        }

        $respDic = StringHelper::xmlToDictionary($authResponse, 'VPosTransactionResponseContract');
        $response->private_response['response_parsed'] = $respDic;

        if (($respDic['ResponseCode'] ?? '') !== '00') {
            $response->status = SaleResponseStatus::Error;
            $response->message = $respDic['ResponseMessage'] ?? '3D doğrulaması başarısız';
            $response->order_number = $respDic['MerchantOrderId'] ?? '';

            return $response;
        }

        // Provision request
        $amount = $respDic['VPosMessage']['Amount'] ?? '';
        $orderId = $respDic['MerchantOrderId'] ?? '';
        $installment = $respDic['VPosMessage']['InstallmentCount'] ?? '0';
        $currencyCode = $respDic['VPosMessage']['CurrencyCode'] ?? '0949';
        $md = $respDic['MD'] ?? '';

        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64($auth->merchant_id . $orderId . $amount . $auth->merchant_user . $hashedPassword);

        $provXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<APIVersion>TDV2.0.0</APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchant_id}</MerchantId>
<CustomerId>{$auth->merchant_storekey}</CustomerId>
<UserName>{$auth->merchant_user}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<MerchantOrderId>{$orderId}</MerchantOrderId>
<TransactionSecurity>3</TransactionSecurity>
<KuveytTurkVPosAdditionalData>
<AdditionalData>
<Key>MD</Key>
<Data>{$md}</Data>
</AdditionalData>
</KuveytTurkVPosAdditionalData>
</KuveytTurkVPosMessage>
XML;

        $provUrl = $auth->test_platform ? $this->url3DProvisionTest : $this->url3DProvisionLive;
        $provResp = $this->httpPostXml($provUrl, $provXml);
        $provDic = StringHelper::xmlToDictionary($provResp, 'VPosTransactionResponseContract');

        $response->private_response['response_provision'] = $provDic;
        $response->order_number = $orderId;

        if (($provDic['ResponseCode'] ?? '') === '00') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = ($provDic['ProvisionNumber'] ?? '') . '|' . ($provDic['OrderId'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $provDic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    // --- Private helpers ---

    private function buildSaleXml(SaleRequest $request, MerchantAuth $auth, string $hash, string $amount, int $transactionSecurity): string
    {
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $currencyCode = str_pad((string) $request->sale_info->currency->value, 4, '0', STR_PAD_LEFT);
        $expMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->sale_info->card_expiry_year, 2);
        $okUrl = $request->payment_3d?->return_url ?? '';
        $failUrl = $request->payment_3d?->return_url ?? '';
        $cardType = StringHelper::detectCardType($request->sale_info->card_number);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<APIVersion>TDV2.0.0</APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchant_id}</MerchantId>
<CustomerId>{$auth->merchant_storekey}</CustomerId>
<UserName>{$auth->merchant_user}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<MerchantOrderId>{$request->order_number}</MerchantOrderId>
<TransactionSecurity>{$transactionSecurity}</TransactionSecurity>
<OkUrl>{$okUrl}</OkUrl>
<FailUrl>{$failUrl}</FailUrl>
<CardNumber>{$request->sale_info->card_number}</CardNumber>
<CardCVV2>{$request->sale_info->card_cvv}</CardCVV2>
<CardHolderName>{$request->sale_info->card_name_surname}</CardHolderName>
<CardType>{$cardType}</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
</KuveytTurkVPosMessage>
XML;

        return $xml;
    }
}
