<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class VakifKatilimGateway extends AbstractGateway
{

    private string $urlNon3DLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate';

    private string $url3DLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';

    private string $url3DProvisionLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $currencyCode = str_pad((string) $request->sale_info->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64($auth->merchant_id . $request->order_number . $amount . $auth->merchant_user . $hashedPassword);

        $expMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->sale_info->card_expiry_year, 2);
        $cardType = StringHelper::detectCardType($request->sale_info->card_number);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion>1.0.0</APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchant_id}</MerchantId>
<CustomerId>{$auth->merchant_storekey}</CustomerId>
<UserName>{$auth->merchant_user}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<FECCurrencyCode>{$currencyCode}</FECCurrencyCode>
<MerchantOrderId>{$request->order_number}</MerchantOrderId>
<TransactionSecurity>1</TransactionSecurity>
<PaymentType>1</PaymentType>
<CardNumber>{$request->sale_info->card_number}</CardNumber>
<CardCVV2>{$request->sale_info->card_cvv}</CardCVV2>
<CardHolderName>{$request->sale_info->card_name_surname}</CardHolderName>
<CardType>{$cardType}</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customer_ip_address}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->httpPostXml($this->urlNon3DLive, $xml);
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
        $currencyCode = str_pad((string) $request->sale_info->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64(
            $auth->merchant_id . $request->order_number . $amount .
                $request->payment_3d->return_url . $request->payment_3d->return_url .
                $auth->merchant_user . $hashedPassword
        );

        $expMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->sale_info->card_expiry_year, 2);
        $cardType = StringHelper::detectCardType($request->sale_info->card_number);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion>1.0.0</APIVersion>
<HashData>{$hash}</HashData>
<HashPassword>{$hashedPassword}</HashPassword>
<MerchantId>{$auth->merchant_id}</MerchantId>
<CustomerId>{$auth->merchant_storekey}</CustomerId>
<UserName>{$auth->merchant_user}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<FECCurrencyCode>{$currencyCode}</FECCurrencyCode>
<MerchantOrderId>{$request->order_number}</MerchantOrderId>
<TransactionSecurity>3</TransactionSecurity>
<PaymentType>1</PaymentType>
<OkUrl>{$request->payment_3d->return_url}</OkUrl>
<FailUrl>{$request->payment_3d->return_url}</FailUrl>
<CardNumber>{$request->sale_info->card_number}</CardNumber>
<CardCVV2>{$request->sale_info->card_cvv}</CardCVV2>
<CardHolderName>{$request->sale_info->card_name_surname}</CardHolderName>
<CardType>{$cardType}</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customer_ip_address}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->httpPostXml($this->url3DLive, $xml);
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

        $responseMessage = $request->responseArray['ResponseMessage'] ?? '';
        if (! empty($responseMessage)) {
            $responseMessage = urldecode($responseMessage);
        }

        $respDic = StringHelper::xmlToDictionary($responseMessage, 'VPosTransactionResponseContract');
        $response->private_response['response_parsed'] = $respDic;

        if (($respDic['ResponseCode'] ?? '') !== '00') {
            $response->status = SaleResponseStatus::Error;
            $response->message = $respDic['ResponseMessage'] ?? '3D doğrulaması başarısız';
            $response->order_number = $respDic['MerchantOrderId'] ?? '';

            return $response;
        }

        // Provision
        $amount = $respDic['VPosMessage']['Amount'] ?? '';
        $orderId = $respDic['MerchantOrderId'] ?? '';
        $installment = $respDic['VPosMessage']['InstallmentCount'] ?? '0';
        $currencyCode = $respDic['VPosMessage']['CurrencyCode'] ?? '0949';
        $md = $respDic['MD'] ?? '';

        $hashedPassword = StringHelper::sha1Base64($auth->merchant_password);
        $hash = StringHelper::sha1Base64($auth->merchant_id . $orderId . $amount . $auth->merchant_user . $hashedPassword);

        $provXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion></APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchant_id}</MerchantId>
<CustomerId>{$auth->merchant_storekey}</CustomerId>
<UserName>{$auth->merchant_user}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<FECCurrencyCode>{$currencyCode}</FECCurrencyCode>
<MerchantOrderId>{$orderId}</MerchantOrderId>
<TransactionSecurity>3</TransactionSecurity>
<PaymentType>1</PaymentType>
<AdditionalData>
<AdditionalDataList>
<VPosAdditionalData>
<Key>MD</Key>
<Data>{$md}</Data>
</VPosAdditionalData>
</AdditionalDataList>
</AdditionalData>
</VPosMessageContract>
XML;

        $provResp = $this->httpPostXml($this->url3DProvisionLive, $provXml);
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

    // --- Private Helpers ---

    // toKurus, sha1Base64 ve xmlRequest
    // StringHelper ve MakesHttpRequests trait'ü üzerinden sağlanır.
}
