<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class YapiKrediBankasiGateway extends AbstractGateway
{
    private string $urlAPITest = 'https://setmpos.ykb.com/PosnetWebService/XML';

    private string $urlAPILive = 'https://posnet.yapikredi.com.tr/PosnetWebService/XML';

    private string $url3DTest = 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService';

    private string $url3DLive = 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $orderId = $this->toOrderNumber($request->order_number, 24);
        $expDate = substr((string) $request->sale_info->card_expiry_year, 2) .
            str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $currency = $this->toYKBCurrency($request->sale_info->currency);
        $installment = str_pad((string) ($request->sale_info->installment > 1 ? $request->sale_info->installment : 0), 2, '0', STR_PAD_LEFT);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchant_id}</mid>
<tid>{$auth->merchant_user}</tid>
<tranDateRequired>1</tranDateRequired>
<sale>
<ccno>{$request->sale_info->card_number}</ccno>
<cvc>{$request->sale_info->card_cvv}</cvc>
<expDate>{$expDate}</expDate>
<currencyCode>{$currency}</currencyCode>
<amount>{$amount}</amount>
<orderID>{$orderId}</orderID>
<installment>{$installment}</installment>
</sale>
</posnetRequest>
XML;

        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resp = $this->xmlDataRequest($xml, $url);
        $dic = StringHelper::xmlToDictionary($resp, 'posnetResponse');

        $response->private_response = $dic;

        if (($dic['approved'] ?? '') === '1') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $dic['hostlogkey'] ?? '';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['respText'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $amount = StringHelper::toKurus($request->sale_info->amount);
        $xid = $this->toOrderNumber($request->order_number, 20);
        $expDate = substr((string) $request->sale_info->card_expiry_year, 2) .
            str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $currency = $this->toYKBCurrency($request->sale_info->currency);
        $installment = str_pad((string) ($request->sale_info->installment > 1 ? $request->sale_info->installment : 0), 2, '0', STR_PAD_LEFT);

        // Step 1: OOS Request Data
        $oosXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchant_id}</mid>
<tid>{$auth->merchant_user}</tid>
<oosRequestData>
<posnetid>{$auth->merchant_password}</posnetid>
<XID>{$xid}</XID>
<tranType>Sale</tranType>
<cardHolderName>{$request->sale_info->card_name_surname}</cardHolderName>
<ccno>{$request->sale_info->card_number}</ccno>
<cvc>{$request->sale_info->card_cvv}</cvc>
<expDate>{$expDate}</expDate>
<currencyCode>{$currency}</currencyCode>
<amount>{$amount}</amount>
<installment>{$installment}</installment>
</oosRequestData>
</posnetRequest>
XML;

        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $oosResp = $this->xmlDataRequest($oosXml, $url);
        $oosDic = StringHelper::xmlToDictionary($oosResp, 'posnetResponse');

        if (($oosDic['approved'] ?? '') !== '1') {
            $response->status = SaleResponseStatus::Error;
            $response->message = $oosDic['respText'] ?? 'OOS veri oluşturulamadı';
            $response->private_response = $oosDic;

            return $response;
        }

        $oosData = $oosDic['oosRequestDataResponse'] ?? [];
        $data1 = $oosData['data1'] ?? '';
        $data2 = $oosData['data2'] ?? '';
        $sign = $oosData['sign'] ?? '';

        // Step 2: 3D HTML form
        $url3D = $auth->test_platform ? $this->url3DTest : $this->url3DLive;

        $html = '<html><body onload="document.frm.submit();">';
        $html .= '<form name="frm" method="POST" action="' . htmlspecialchars($url3D) . '">';
        $html .= '<input type="hidden" name="mid" value="' . htmlspecialchars($auth->merchant_id) . '">';
        $html .= '<input type="hidden" name="posnetID" value="' . htmlspecialchars($auth->merchant_password) . '">';
        $html .= '<input type="hidden" name="posnetData" value="' . htmlspecialchars($data1) . '">';
        $html .= '<input type="hidden" name="posnetData2" value="' . htmlspecialchars($data2) . '">';
        $html .= '<input type="hidden" name="digest" value="' . htmlspecialchars($sign) . '">';
        $html .= '<input type="hidden" name="merchantReturnURL" value="' . htmlspecialchars($request->payment_3d->return_url) . '">';
        $html .= '<input type="hidden" name="lang" value="tr">';
        $html .= '</form></body></html>';

        $response->status = SaleResponseStatus::RedirectHTML;
        $response->message = $html;
        $response->private_response = $oosDic;

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->currency === null) {
            throw new \InvalidArgumentException('currency alanı Yapı Kredi bankası için zorunludur');
        }

        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $bankPacket = $request->responseArray['BankPacket'] ?? '';
        $merchantPacket = $request->responseArray['MerchantPacket'] ?? '';
        $sign = $request->responseArray['Sign'] ?? '';
        $xid = $request->responseArray['Xid'] ?? '';
        $amount = $request->responseArray['Amount'] ?? '';
        $currencyCode = $request->responseArray['Currency'] ?? '';

        // Step 1: OOS Resolve Merchant Data
        $firstHash = base64_encode(hash('sha256', $auth->merchant_storekey . ';' . $auth->merchant_user, true));
        $mac = base64_encode(hash('sha256', $xid . ';' . $amount . ';' . $currencyCode . ';' . $auth->merchant_id . ';' . $firstHash, true));

        $resolveXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchant_id}</mid>
<tid>{$auth->merchant_user}</tid>
<oosResolveMerchantData>
<bankData>{$bankPacket}</bankData>
<merchantData>{$merchantPacket}</merchantData>
<sign>{$sign}</sign>
<mac>{$mac}</mac>
</oosResolveMerchantData>
</posnetRequest>
XML;

        $url = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $resolveResp = $this->xmlDataRequest($resolveXml, $url);
        $resolveDic = StringHelper::xmlToDictionary($resolveResp, 'posnetResponse');

        $response->private_response['response_resolve'] = $resolveDic;

        $mdStatus = $resolveDic['oosResolveMerchantDataResponse']['mdStatus'] ?? '';

        $isTest = $auth->test_platform;
        if ($mdStatus !== '1' && ! ($isTest && $mdStatus === '9')) {
            $response->status = SaleResponseStatus::Error;
            $response->message = '3D doğrulaması başarısız (mdStatus: ' . $mdStatus . ')';

            return $response;
        }

        // Step 2: OOS Tran Data
        $tranXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<posnetRequest>
<mid>{$auth->merchant_id}</mid>
<tid>{$auth->merchant_user}</tid>
<oosTranData>
<bankData>{$bankPacket}</bankData>
<wpAmount>0</wpAmount>
<mac>{$mac}</mac>
</oosTranData>
</posnetRequest>
XML;

        $tranResp = $this->xmlDataRequest($tranXml, $url);
        $tranDic = StringHelper::xmlToDictionary($tranResp, 'posnetResponse');

        $response->private_response['response_tran'] = $tranDic;
        $response->order_number = $xid;

        if (($tranDic['approved'] ?? '') === '1') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $tranDic['hostlogkey'] ?? '';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $tranDic['respText'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    // --- Private Helpers ---

    private function toOrderNumber(string $order_number, int $length): string
    {
        return str_pad($order_number, $length, '0', STR_PAD_LEFT);
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
        return $this->httpPostForm($url, ['xmldata' => $xml]);
    }
}
