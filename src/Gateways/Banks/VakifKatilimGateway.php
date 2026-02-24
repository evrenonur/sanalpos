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

class VakifKatilimGateway implements VirtualPOSServiceInterface
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

        $amount = $this->toKurus($request->sale_info->amount);
        $currencyCode = str_pad((string) $request->sale_info->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $hashedPassword = $this->sha1Base64($auth->merchant_password);
        $hash = $this->sha1Base64($auth->merchant_id . $request->order_number . $amount . $auth->merchant_user . $hashedPassword);

        $expMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->sale_info->card_expiry_year, 2);

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
<CardType>MasterCard</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customer_ip_address}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->xmlRequest($xml, $this->urlNon3DLive);
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

        $amount = $this->toKurus($request->sale_info->amount);
        $currencyCode = str_pad((string) $request->sale_info->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 0;
        $hashedPassword = $this->sha1Base64($auth->merchant_password);
        $hash = $this->sha1Base64(
            $auth->merchant_id . $request->order_number . $amount .
                $request->payment_3d->return_url . $request->payment_3d->return_url .
                $auth->merchant_user . $hashedPassword
        );

        $expMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->sale_info->card_expiry_year, 2);

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
<CardType>MasterCard</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customer_ip_address}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->xmlRequest($xml, $this->url3DLive);
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

        $hashedPassword = $this->sha1Base64($auth->merchant_password);
        $hash = $this->sha1Base64($auth->merchant_id . $orderId . $amount . $auth->merchant_user . $hashedPassword);

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

        $provResp = $this->xmlRequest($provXml, $this->url3DProvisionLive);
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

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        return new CancelResponse(status: ResponseStatus::Error, message: 'Bu banka için iptal metodu henüz tanımlanmamış!');
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        return new RefundResponse(status: ResponseStatus::Error, message: 'Bu banka için iade metodu henüz tanımlanmamış!');
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

    private function toKurus(float $amount): string
    {
        return str_replace([',', '.'], '', number_format($amount, 2, '.', ''));
    }

    private function sha1Base64(string $data): string
    {
        return base64_encode(hash('sha1', $data, true));
    }

    private function xmlRequest(string $xml, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'body' => $xml,
            'headers' => ['Content-Type' => 'application/xml; charset=utf-8'],
        ]);

        return $response->getBody()->getContents();
    }
}
