<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\Currency;
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

class YapiKrediBankasiGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://setmpos.ykb.com/PosnetWebService/XML';

    private string $urlAPILive = 'https://posnet.yapikredi.com.tr/PosnetWebService/XML';

    private string $url3DTest = 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService';

    private string $url3DLive = 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = $this->toKurus($request->saleInfo->amount);
        $orderId = $this->toOrderNumber($request->orderNumber, 24);
        $expDate = substr((string) $request->saleInfo->cardExpiryDateYear, 2) .
            str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);
        $currency = $this->toYKBCurrency($request->saleInfo->currency);
        $installment = str_pad((string) ($request->saleInfo->installment > 1 ? $request->saleInfo->installment : 0), 2, '0', STR_PAD_LEFT);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchantID}</mid>
<tid>{$auth->merchantUser}</tid>
<tranDateRequired>1</tranDateRequired>
<sale>
<ccno>{$request->saleInfo->cardNumber}</ccno>
<cvc>{$request->saleInfo->cardCVV}</cvc>
<expDate>{$expDate}</expDate>
<currencyCode>{$currency}</currencyCode>
<amount>{$amount}</amount>
<orderID>{$orderId}</orderID>
<installment>{$installment}</installment>
</sale>
</posnetRequest>
XML;

        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->xmlDataRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'posnetResponse');

        $response->privateResponse = $dic;

        if (($dic['approved'] ?? '') === '1') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $dic['hostlogkey'] ?? '';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['respText'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $amount = $this->toKurus($request->saleInfo->amount);
        $xid = $this->toOrderNumber($request->orderNumber, 20);
        $expDate = substr((string) $request->saleInfo->cardExpiryDateYear, 2) .
            str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);
        $currency = $this->toYKBCurrency($request->saleInfo->currency);
        $installment = str_pad((string) ($request->saleInfo->installment > 1 ? $request->saleInfo->installment : 0), 2, '0', STR_PAD_LEFT);

        // Step 1: OOS Request Data
        $oosXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchantID}</mid>
<tid>{$auth->merchantUser}</tid>
<oosRequestData>
<posnetid>{$auth->merchantPassword}</posnetid>
<XID>{$xid}</XID>
<tranType>Sale</tranType>
<cardHolderName>{$request->saleInfo->cardNameSurname}</cardHolderName>
<ccno>{$request->saleInfo->cardNumber}</ccno>
<cvc>{$request->saleInfo->cardCVV}</cvc>
<expDate>{$expDate}</expDate>
<currencyCode>{$currency}</currencyCode>
<amount>{$amount}</amount>
<installment>{$installment}</installment>
</oosRequestData>
</posnetRequest>
XML;

        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $oosResp = $this->xmlDataRequest($oosXml, $url);
        $oosDic = StringHelper::xmlToDictionary($oosResp, 'posnetResponse');

        if (($oosDic['approved'] ?? '') !== '1') {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $oosDic['respText'] ?? 'OOS veri oluşturulamadı';
            $response->privateResponse = $oosDic;

            return $response;
        }

        $oosData = $oosDic['oosRequestDataResponse'] ?? [];
        $data1 = $oosData['data1'] ?? '';
        $data2 = $oosData['data2'] ?? '';
        $sign = $oosData['sign'] ?? '';

        // Step 2: 3D HTML form
        $url3D = $auth->testPlatform ? $this->url3DTest : $this->url3DLive;

        $html = '<html><body onload="document.frm.submit();">';
        $html .= '<form name="frm" method="POST" action="' . htmlspecialchars($url3D) . '">';
        $html .= '<input type="hidden" name="mid" value="' . htmlspecialchars($auth->merchantID) . '">';
        $html .= '<input type="hidden" name="posnetID" value="' . htmlspecialchars($auth->merchantPassword) . '">';
        $html .= '<input type="hidden" name="posnetData" value="' . htmlspecialchars($data1) . '">';
        $html .= '<input type="hidden" name="posnetData2" value="' . htmlspecialchars($data2) . '">';
        $html .= '<input type="hidden" name="digest" value="' . htmlspecialchars($sign) . '">';
        $html .= '<input type="hidden" name="merchantReturnURL" value="' . htmlspecialchars($request->payment3D->returnURL) . '">';
        $html .= '<input type="hidden" name="lang" value="tr">';
        $html .= '</form></body></html>';

        $response->statu = SaleResponseStatu::RedirectHTML;
        $response->message = $html;
        $response->privateResponse = $oosDic;

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $bankPacket = $request->responseArray['BankPacket'] ?? '';
        $merchantPacket = $request->responseArray['MerchantPacket'] ?? '';
        $sign = $request->responseArray['Sign'] ?? '';
        $xid = $request->responseArray['Xid'] ?? '';
        $amount = $request->responseArray['Amount'] ?? '';
        $currencyCode = $request->responseArray['Currency'] ?? '';

        // Step 1: OOS Resolve Merchant Data
        $firstHash = base64_encode(hash('sha256', $auth->merchantStorekey . ';' . $auth->merchantUser, true));
        $mac = base64_encode(hash('sha256', $xid . ';' . $amount . ';' . $currencyCode . ';' . $auth->merchantID . ';' . $firstHash, true));

        $resolveXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchantID}</mid>
<tid>{$auth->merchantUser}</tid>
<oosResolveMerchantData>
<bankData>{$bankPacket}</bankData>
<merchantData>{$merchantPacket}</merchantData>
<sign>{$sign}</sign>
<mac>{$mac}</mac>
</oosResolveMerchantData>
</posnetRequest>
XML;

        $url = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $resolveResp = $this->xmlDataRequest($resolveXml, $url);
        $resolveDic = StringHelper::xmlToDictionary($resolveResp, 'posnetResponse');

        $response->privateResponse['response_resolve'] = $resolveDic;

        $mdStatus = $resolveDic['oosResolveMerchantDataResponse']['mdStatus'] ?? '';

        $isTest = $auth->testPlatform;
        if ($mdStatus !== '1' && ! ($isTest && $mdStatus === '9')) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = '3D doğrulaması başarısız (mdStatus: ' . $mdStatus . ')';

            return $response;
        }

        // Step 2: OOS Tran Data
        $tranXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchantID}</mid>
<tid>{$auth->merchantUser}</tid>
<oosTranData>
<bankData>{$bankPacket}</bankData>
<wpAmount>0</wpAmount>
<mac>{$mac}</mac>
</oosTranData>
</posnetRequest>
XML;

        $tranResp = $this->xmlDataRequest($tranXml, $url);
        $tranDic = StringHelper::xmlToDictionary($tranResp, 'posnetResponse');

        $response->privateResponse['response_tran'] = $tranDic;
        $response->orderNumber = $xid;

        if (($tranDic['approved'] ?? '') === '1') {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $tranDic['hostlogkey'] ?? '';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $tranDic['respText'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        return new CancelResponse(statu: ResponseStatu::Error, message: 'Bu banka için iptal metodu tanımlanmamış!');
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        return new RefundResponse(statu: ResponseStatu::Error, message: 'Bu banka için iade metodu tanımlanmamış!');
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

    private function toOrderNumber(string $orderNumber, int $length): string
    {
        return str_pad($orderNumber, $length, '0', STR_PAD_LEFT);
    }

    private function toYKBCurrency(?Currency $currency): string
    {
        return match ($currency) {
            Currency::TRY => 'TL',
            Currency::USD => 'US',
            Currency::EUR => 'EU',
            Currency::GBP => 'GB',
            default => 'TL',
        };
    }

    private function xmlDataRequest(string $xml, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'form_params' => ['xmldata' => $xml],
        ]);

        return $response->getBody()->getContents();
    }
}
