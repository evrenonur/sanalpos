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

class VakifKatilimGateway implements VirtualPOSServiceInterface
{
    private string $urlNon3DLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/Non3DPayGate';

    private string $url3DLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';

    private string $url3DProvisionLive = 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = $this->toKurus($request->saleInfo->amount);
        $currencyCode = str_pad((string) $request->saleInfo->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 0;
        $hashedPassword = $this->sha1Base64($auth->merchantPassword);
        $hash = $this->sha1Base64($auth->merchantID . $request->orderNumber . $amount . $auth->merchantUser . $hashedPassword);

        $expMonth = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->saleInfo->cardExpiryDateYear, 2);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion>1.0.0</APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchantID}</MerchantId>
<CustomerId>{$auth->merchantStorekey}</CustomerId>
<UserName>{$auth->merchantUser}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<FECCurrencyCode>{$currencyCode}</FECCurrencyCode>
<MerchantOrderId>{$request->orderNumber}</MerchantOrderId>
<TransactionSecurity>1</TransactionSecurity>
<PaymentType>1</PaymentType>
<CardNumber>{$request->saleInfo->cardNumber}</CardNumber>
<CardCVV2>{$request->saleInfo->cardCVV}</CardCVV2>
<CardHolderName>{$request->saleInfo->cardNameSurname}</CardHolderName>
<CardType>MasterCard</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customerIPAddress}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->xmlRequest($xml, $this->urlNon3DLive);
        $dic = StringHelper::xmlToDictionary($resp, 'VPosTransactionResponseContract');

        $response->privateResponse = $dic;

        if (($dic['ResponseCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = ($dic['ProvisionNumber'] ?? '') . '|' . ($dic['OrderId'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = $this->toKurus($request->saleInfo->amount);
        $currencyCode = str_pad((string) $request->saleInfo->currency->value, 4, '0', STR_PAD_LEFT);
        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 0;
        $hashedPassword = $this->sha1Base64($auth->merchantPassword);
        $hash = $this->sha1Base64(
            $auth->merchantID . $request->orderNumber . $amount .
                $request->payment3D->returnURL . $request->payment3D->returnURL .
                $auth->merchantUser . $hashedPassword
        );

        $expMonth = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);
        $expYear = substr((string) $request->saleInfo->cardExpiryDateYear, 2);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion>1.0.0</APIVersion>
<HashData>{$hash}</HashData>
<HashPassword>{$hashedPassword}</HashPassword>
<MerchantId>{$auth->merchantID}</MerchantId>
<CustomerId>{$auth->merchantStorekey}</CustomerId>
<UserName>{$auth->merchantUser}</UserName>
<TransactionType>Sale</TransactionType>
<InstallmentCount>{$installment}</InstallmentCount>
<Amount>{$amount}</Amount>
<DisplayAmount>{$amount}</DisplayAmount>
<CurrencyCode>{$currencyCode}</CurrencyCode>
<FECCurrencyCode>{$currencyCode}</FECCurrencyCode>
<MerchantOrderId>{$request->orderNumber}</MerchantOrderId>
<TransactionSecurity>3</TransactionSecurity>
<PaymentType>1</PaymentType>
<OkUrl>{$request->payment3D->returnURL}</OkUrl>
<FailUrl>{$request->payment3D->returnURL}</FailUrl>
<CardNumber>{$request->saleInfo->cardNumber}</CardNumber>
<CardCVV2>{$request->saleInfo->cardCVV}</CardCVV2>
<CardHolderName>{$request->saleInfo->cardNameSurname}</CardHolderName>
<CardType>MasterCard</CardType>
<CardExpireDateYear>{$expYear}</CardExpireDateYear>
<CardExpireDateMonth>{$expMonth}</CardExpireDateMonth>
<CustomerIPAddress>{$request->customerIPAddress}</CustomerIPAddress>
</VPosMessageContract>
XML;

        $resp = $this->xmlRequest($xml, $this->url3DLive);
        $response->privateResponse = ['htmlResponse' => $resp];

        if (str_contains($resp, 'form') && str_contains($resp, 'action')) {
            $response->statu = SaleResponseStatu::RedirectHTML;
            $response->message = $resp;
        } else {
            $dic = StringHelper::xmlToDictionary($resp, 'VPosTransactionResponseContract');
            $response->privateResponse = $dic;
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $responseMessage = $request->responseArray['ResponseMessage'] ?? '';
        if (! empty($responseMessage)) {
            $responseMessage = urldecode($responseMessage);
        }

        $respDic = StringHelper::xmlToDictionary($responseMessage, 'VPosTransactionResponseContract');
        $response->privateResponse['response_parsed'] = $respDic;

        if (($respDic['ResponseCode'] ?? '') !== '00') {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $respDic['ResponseMessage'] ?? '3D doğrulaması başarısız';
            $response->orderNumber = $respDic['MerchantOrderId'] ?? '';

            return $response;
        }

        // Provision
        $amount = $respDic['VPosMessage']['Amount'] ?? '';
        $orderId = $respDic['MerchantOrderId'] ?? '';
        $installment = $respDic['VPosMessage']['InstallmentCount'] ?? '0';
        $currencyCode = $respDic['VPosMessage']['CurrencyCode'] ?? '0949';
        $md = $respDic['MD'] ?? '';

        $hashedPassword = $this->sha1Base64($auth->merchantPassword);
        $hash = $this->sha1Base64($auth->merchantID . $orderId . $amount . $auth->merchantUser . $hashedPassword);

        $provXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<VPosMessageContract>
<APIVersion></APIVersion>
<HashData>{$hash}</HashData>
<MerchantId>{$auth->merchantID}</MerchantId>
<CustomerId>{$auth->merchantStorekey}</CustomerId>
<UserName>{$auth->merchantUser}</UserName>
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

        $response->privateResponse['response_provision'] = $provDic;
        $response->orderNumber = $orderId;

        if (($provDic['ResponseCode'] ?? '') === '00') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = ($provDic['ProvisionNumber'] ?? '') . '|' . ($provDic['OrderId'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $provDic['ResponseMessage'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        return new CancelResponse(statu: ResponseStatu::Error, message: 'Bu banka için iptal metodu henüz tanımlanmamış!');
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        return new RefundResponse(statu: ResponseStatu::Error, message: 'Bu banka için iade metodu henüz tanımlanmamış!');
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
