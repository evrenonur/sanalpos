<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Helpers\StringHelper;
use EvrenOnur\SanalPos\Helpers\XmlHelper;
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

class ParamPosGateway implements VirtualPOSServiceInterface
{
    private string $urlTest = 'https://testposws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx';

    private string $urlLive = 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $is3D = $request->payment3D?->confirm === true;
        $guid = $this->generateGUID();

        $installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;
        $amount = StringHelper::formatAmount($request->saleInfo->amount);

        // Taksitli ise komisyon tutarını al
        $totalAmount = $amount;
        if ($installment > 1) {
            $commResp = $this->getInstallmentAmount($baseUrl, $auth, $request->saleInfo->cardNumber, $installment, $amount);
            if (! empty($commResp)) {
                $totalAmount = $commResp;
            }
        }

        $hashInput = $auth->merchantID . $guid . $installment . $amount . $totalAmount . $request->orderNumber;
        $hash = base64_encode(hash('sha1', $hashInput, true));

        $securityType = $is3D ? '3D' : 'NS';

        $xml = $this->buildSaleXml(
            $auth,
            $guid,
            $request,
            $hash,
            $securityType,
            $installment,
            $amount,
            $totalAmount
        );

        $soapAction = 'https://turkpos.com.tr/TP_WMD_UCD';
        $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
        $dic = XmlHelper::xmlToDictionary($resp);

        $response->privateResponse = $dic;

        $sonuc = (int) ($dic['Sonuc'] ?? -1);
        $ucdHtml = $dic['UCD_HTML'] ?? '';
        $islemId = (int) ($dic['Islem_ID'] ?? 0);

        if ($sonuc > 0) {
            if (! $is3D && $ucdHtml === 'NONSECURE' && $islemId > 0) {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarılı';
                $response->transactionId = (string) $islemId;
            } elseif ($is3D && ! empty($ucdHtml) && $ucdHtml !== 'NONSECURE') {
                $response->statu = SaleResponseStatu::RedirectHTML;
                $response->message = $ucdHtml;
            } else {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $dic['Sonuc_Str'] ?? 'İşlem sırasında bir hata oluştu';
            }
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['Sonuc_Str'] ?? 'İşlem sırasında bir hata oluştu';
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        $mdStatus = (int) ($request->responseArray['mdStatus'] ?? 0);
        $md = $request->responseArray['md'] ?? '';
        $islemGUID = $request->responseArray['islemGUID'] ?? '';
        $orderId = $request->responseArray['orderId'] ?? '';
        $response->orderNumber = (string) $orderId;

        if ($mdStatus !== 1 || empty($md) || empty($islemGUID)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = '3D doğrulaması başarısız. mdStatus: ' . $mdStatus;

            return $response;
        }

        // TP_WMD_Pay çağrısı
        $baseUrl = $this->getBaseUrl($auth);
        $guid = $this->generateGUID();

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_WMD_Pay xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <UCD_MD>{$md}</UCD_MD>
      <Islem_GUID>{$islemGUID}</Islem_GUID>
      <Siparis_ID>{$orderId}</Siparis_ID>
    </TP_WMD_Pay>
  </soap:Body>
</soap:Envelope>
XML;

        $soapAction = 'https://turkpos.com.tr/TP_WMD_Pay';
        $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
        $dic = XmlHelper::xmlToDictionary($resp);

        $response->privateResponse['response_2'] = $dic;

        $sonuc = (int) ($dic['Sonuc'] ?? -1);
        $dekontId = (int) ($dic['Dekont_ID'] ?? 0);

        if ($sonuc > 0 && $dekontId > 0) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) $dekontId;
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $dic['Sonuc_Str'] ?? 'İşlem tamamlanamadı';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $guid = $this->generateGUID();

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Islem_Iptal_Iade_Kismi2 xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <Durum>IPTAL</Durum>
      <Dekont_ID>{$request->transactionId}</Dekont_ID>
      <Tutar>0.00</Tutar>
      <Siparis_ID>{$request->orderNumber}</Siparis_ID>
    </TP_Islem_Iptal_Iade_Kismi2>
  </soap:Body>
</soap:Envelope>
XML;

        $soapAction = 'https://turkpos.com.tr/TP_Islem_Iptal_Iade_Kismi2';
        $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
        $dic = XmlHelper::xmlToDictionary($resp);

        $response->privateResponse = $dic;

        $sonuc = (int) ($dic['Sonuc'] ?? -1);
        if ($sonuc > 0) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['Sonuc_Str'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $guid = $this->generateGUID();
        $amount = StringHelper::formatAmount($request->refundAmount);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Islem_Iptal_Iade_Kismi2 xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <Durum>IADE</Durum>
      <Dekont_ID>{$request->transactionId}</Dekont_ID>
      <Tutar>{$amount}</Tutar>
      <Siparis_ID>{$request->orderNumber}</Siparis_ID>
    </TP_Islem_Iptal_Iade_Kismi2>
  </soap:Body>
</soap:Envelope>
XML;

        $soapAction = 'https://turkpos.com.tr/TP_Islem_Iptal_Iade_Kismi2';
        $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
        $dic = XmlHelper::xmlToDictionary($resp);

        $response->privateResponse = $dic;

        $sonuc = (int) ($dic['Sonuc'] ?? -1);
        if ($sonuc > 0) {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $dic['Sonuc_Str'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $guid = $this->generateGUID();

        // Önce SanalPOS_ID al
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <BIN_SanalPos xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <BIN>{$request->BIN}</BIN>
    </BIN_SanalPos>
  </soap:Body>
</soap:Envelope>
XML;

        $soapAction = 'https://turkpos.com.tr/BIN_SanalPos';
        $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
        $dic = XmlHelper::xmlToDictionary($resp);

        $response->privateResponse = $dic;

        $sanalPosId = $dic['SanalPOS_ID'] ?? '';
        if (empty($sanalPosId) || $sanalPosId === '0') {
            return $response;
        }

        // Taksit oranlarını sorgula
        $xml2 = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Ozel_Oran_SK_Liste xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <SanalPOS_ID>{$sanalPosId}</SanalPOS_ID>
    </TP_Ozel_Oran_SK_Liste>
  </soap:Body>
</soap:Envelope>
XML;

        $soapAction2 = 'https://turkpos.com.tr/TP_Ozel_Oran_SK_Liste';
        $resp2 = $this->soapRequest($xml2, $baseUrl, $soapAction2);
        $dic2 = XmlHelper::xmlToDictionary($resp2);

        $response->privateResponse['installments'] = $dic2;

        for ($i = 2; $i <= 12; $i++) {
            $key = 'MO_' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $rate = (float) ($dic2[$key] ?? 0);
            if ($rate > 0) {
                $totalAmount = round($request->amount * (1 + $rate / 100), 2);
                $response->installmentList[] = [
                    'installment' => $i,
                    'rate' => $rate,
                    'totalAmount' => $totalAmount,
                ];
            }
        }

        if (! empty($response->installmentList)) {
            $response->confirm = true;
        }

        return $response;
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

    private function getBaseUrl(VirtualPOSAuth $auth): string
    {
        return $auth->testPlatform ? $this->urlTest : $this->urlLive;
    }

    private function generateGUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }

    private function getInstallmentAmount(string $baseUrl, VirtualPOSAuth $auth, string $cardNumber, int $installment, string $amount): string
    {
        try {
            $guid = $this->generateGUID();
            $bin = substr($cardNumber, 0, 6);

            // BIN_SanalPos çağrısı
            $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <BIN_SanalPos xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <BIN>{$bin}</BIN>
    </BIN_SanalPos>
  </soap:Body>
</soap:Envelope>
XML;

            $resp = $this->soapRequest($xml, $baseUrl, 'https://turkpos.com.tr/BIN_SanalPos');
            $dic = XmlHelper::xmlToDictionary($resp);
            $sanalPosId = $dic['SanalPOS_ID'] ?? '';
            if (empty($sanalPosId)) {
                return $amount;
            }

            // Oran sorgula
            $xml2 = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Ozel_Oran_SK_Liste xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <SanalPOS_ID>{$sanalPosId}</SanalPOS_ID>
    </TP_Ozel_Oran_SK_Liste>
  </soap:Body>
</soap:Envelope>
XML;

            $resp2 = $this->soapRequest($xml2, $baseUrl, 'https://turkpos.com.tr/TP_Ozel_Oran_SK_Liste');
            $dic2 = XmlHelper::xmlToDictionary($resp2);
            $key = 'MO_' . str_pad($installment, 2, '0', STR_PAD_LEFT);
            $rate = (float) ($dic2[$key] ?? 0);
            if ($rate > 0) {
                $total = round((float) $amount * (1 + $rate / 100), 2);

                return number_format($total, 2, '.', '');
            }

            return $amount;
        } catch (\Throwable $e) {
            return $amount;
        }
    }

    private function buildSaleXml(VirtualPOSAuth $auth, string $guid, SaleRequest $request, string $hash, string $securityType, int $installment, string $amount, string $totalAmount): string
    {
        $expiry = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . '/' . $request->saleInfo->cardExpiryDateYear;
        $returnUrl = $request->payment3D?->returnURL ?? '';
        $currency = StringHelper::getCurrencyCode($request->saleInfo->currency ?? Currency::TRY);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_WMD_UCD xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchantID}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchantUser}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchantPassword}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <KK_Sahibi>{$request->saleInfo->cardNameSurname}</KK_Sahibi>
      <KK_No>{$request->saleInfo->cardNumber}</KK_No>
      <KK_SK>{$expiry}</KK_SK>
      <KK_CVC>{$request->saleInfo->cardCVV}</KK_CVC>
      <KK_Sahibi_GSM></KK_Sahibi_GSM>
      <Hata_URL>{$returnUrl}</Hata_URL>
      <Basarili_URL>{$returnUrl}</Basarili_URL>
      <Siparis_ID>{$request->orderNumber}</Siparis_ID>
      <Siparis_Aciklama></Siparis_Aciklama>
      <Taksit>{$installment}</Taksit>
      <Islem_Tutar>{$amount}</Islem_Tutar>
      <Toplam_Tutar>{$totalAmount}</Toplam_Tutar>
      <Islem_Hash>{$hash}</Islem_Hash>
      <Islem_Guvenlik_Tip>{$securityType}</Islem_Guvenlik_Tip>
      <Islem_ID></Islem_ID>
      <IPAdr>{$request->customerIPAddress}</IPAdr>
      <Ref_URL></Ref_URL>
      <Data1></Data1>
      <Data2></Data2>
      <Data3></Data3>
      <Data4></Data4>
      <Data5></Data5>
    </TP_WMD_UCD>
  </soap:Body>
</soap:Envelope>
XML;
    }

    private function soapRequest(string $xml, string $url, string $soapAction): string
    {
        try {
            $client = new Client(['verify' => false]);
            $resp = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapAction,
                ],
                'body' => $xml,
            ]);

            return $resp->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
