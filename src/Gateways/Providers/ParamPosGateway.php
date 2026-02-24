<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\StringHelper;
use EvrenOnur\SanalPos\Support\XmlHelper;
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
use EvrenOnur\SanalPos\Support\MakesHttpRequests;

class ParamPosGateway implements VirtualPOSServiceInterface
{
  use MakesHttpRequests;
  private string $urlTest = 'https://testposws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx';

  private string $urlLive = 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx';

  public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
  {
    $response = new SaleResponse(order_number: $request->order_number);
    $baseUrl = $this->getBaseUrl($auth);
    $is3D = $request->payment_3d?->confirm === true;
    $guid = $this->generateGUID();

    $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;
    $amount = StringHelper::formatAmount($request->sale_info->amount);

    // Taksitli ise komisyon tutarını al
    $totalAmount = $amount;
    if ($installment > 1) {
      $commResp = $this->getInstallmentAmount($baseUrl, $auth, $request->sale_info->card_number, $installment, $amount);
      if (! empty($commResp)) {
        $totalAmount = $commResp;
      }
    }

    $hashInput = $auth->merchant_id . $guid . $installment . $amount . $totalAmount . $request->order_number;
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

    $response->private_response = $dic;

    $sonuc = (int) ($dic['Sonuc'] ?? -1);
    $ucdHtml = $dic['UCD_HTML'] ?? '';
    $islemId = (int) ($dic['Islem_ID'] ?? 0);

    if ($sonuc > 0) {
      if (! $is3D && $ucdHtml === 'NONSECURE' && $islemId > 0) {
        $response->status = SaleResponseStatus::Success;
        $response->message = 'İşlem başarılı';
        $response->transaction_id = (string) $islemId;
      } elseif ($is3D && ! empty($ucdHtml) && $ucdHtml !== 'NONSECURE') {
        $response->status = SaleResponseStatus::RedirectHTML;
        $response->message = $ucdHtml;
      } else {
        $response->status = SaleResponseStatus::Error;
        $response->message = $dic['Sonuc_Str'] ?? 'İşlem sırasında bir hata oluştu';
      }
    } else {
      $response->status = SaleResponseStatus::Error;
      $response->message = $dic['Sonuc_Str'] ?? 'İşlem sırasında bir hata oluştu';
    }

    return $response;
  }

  public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
  {
    $response = new SaleResponse;
    $response->private_response = ['response_1' => $request->responseArray];

    $mdStatus = (int) ($request->responseArray['mdStatus'] ?? 0);
    $md = $request->responseArray['md'] ?? '';
    $islemGUID = $request->responseArray['islemGUID'] ?? '';
    $orderId = $request->responseArray['orderId'] ?? '';
    $response->order_number = (string) $orderId;

    if ($mdStatus !== 1 || empty($md) || empty($islemGUID)) {
      $response->status = SaleResponseStatus::Error;
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
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
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

    $response->private_response['response_2'] = $dic;

    $sonuc = (int) ($dic['Sonuc'] ?? -1);
    $dekontId = (int) ($dic['Dekont_ID'] ?? 0);

    if ($sonuc > 0 && $dekontId > 0) {
      $response->status = SaleResponseStatus::Success;
      $response->message = 'İşlem başarılı';
      $response->transaction_id = (string) $dekontId;
    } else {
      $response->status = SaleResponseStatus::Error;
      $response->message = $dic['Sonuc_Str'] ?? 'İşlem tamamlanamadı';
    }

    return $response;
  }

  public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
  {
    $response = new CancelResponse(status: ResponseStatus::Error);
    $baseUrl = $this->getBaseUrl($auth);
    $guid = $this->generateGUID();

    $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Islem_Iptal_Iade_Kismi2 xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <Durum>IPTAL</Durum>
      <Dekont_ID>{$request->transaction_id}</Dekont_ID>
      <Tutar>0.00</Tutar>
      <Siparis_ID>{$request->order_number}</Siparis_ID>
    </TP_Islem_Iptal_Iade_Kismi2>
  </soap:Body>
</soap:Envelope>
XML;

    $soapAction = 'https://turkpos.com.tr/TP_Islem_Iptal_Iade_Kismi2';
    $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
    $dic = XmlHelper::xmlToDictionary($resp);

    $response->private_response = $dic;

    $sonuc = (int) ($dic['Sonuc'] ?? -1);
    if ($sonuc > 0) {
      $response->status = ResponseStatus::Success;
      $response->message = 'İşlem başarılı';
    } else {
      $response->message = $dic['Sonuc_Str'] ?? 'İşlem iptal edilemedi';
    }

    return $response;
  }

  public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
  {
    $response = new RefundResponse(status: ResponseStatus::Error);
    $baseUrl = $this->getBaseUrl($auth);
    $guid = $this->generateGUID();
    $amount = StringHelper::formatAmount($request->refund_amount);

    $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_Islem_Iptal_Iade_Kismi2 xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <Durum>IADE</Durum>
      <Dekont_ID>{$request->transaction_id}</Dekont_ID>
      <Tutar>{$amount}</Tutar>
      <Siparis_ID>{$request->order_number}</Siparis_ID>
    </TP_Islem_Iptal_Iade_Kismi2>
  </soap:Body>
</soap:Envelope>
XML;

    $soapAction = 'https://turkpos.com.tr/TP_Islem_Iptal_Iade_Kismi2';
    $resp = $this->soapRequest($xml, $baseUrl, $soapAction);
    $dic = XmlHelper::xmlToDictionary($resp);

    $response->private_response = $dic;

    $sonuc = (int) ($dic['Sonuc'] ?? -1);
    if ($sonuc > 0) {
      $response->status = ResponseStatus::Success;
      $response->message = 'İşlem başarılı';
      $response->refund_amount = $request->refund_amount;
    } else {
      $response->message = $dic['Sonuc_Str'] ?? 'İşlem iade edilemedi';
    }

    return $response;
  }

  public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
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
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
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

    $response->private_response = $dic;

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
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
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

    $response->private_response['installments'] = $dic2;

    for ($i = 2; $i <= 12; $i++) {
      $key = 'MO_' . str_pad($i, 2, '0', STR_PAD_LEFT);
      $rate = (float) ($dic2[$key] ?? 0);
      if ($rate > 0) {
        $totalAmount = round($request->amount * (1 + $rate / 100), 2);
        $response->installment_list[] = [
          'installment' => $i,
          'rate' => $rate,
          'totalAmount' => $totalAmount,
        ];
      }
    }

    if (! empty($response->installment_list)) {
      $response->confirm = true;
    }

    return $response;
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

  // --- Private helpers ---

  private function getBaseUrl(MerchantAuth $auth): string
  {
    return $auth->test_platform ? $this->urlTest : $this->urlLive;
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

  private function getInstallmentAmount(string $baseUrl, MerchantAuth $auth, string $card_number, int $installment, string $amount): string
  {
    try {
      $guid = $this->generateGUID();
      $bin = substr($card_number, 0, 6);

      // BIN_SanalPos çağrısı
      $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <BIN_SanalPos xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
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
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
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

  private function buildSaleXml(MerchantAuth $auth, string $guid, SaleRequest $request, string $hash, string $securityType, int $installment, string $amount, string $totalAmount): string
  {
    $expiry = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . '/' . $request->sale_info->card_expiry_year;
    $returnUrl = $request->payment_3d?->return_url ?? '';
    $currency = StringHelper::getCurrencyCode($request->sale_info->currency ?? Currency::TRY);

    return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TP_WMD_UCD xmlns="https://turkpos.com.tr/">
      <G>
        <CLIENT_CODE>{$auth->merchant_id}</CLIENT_CODE>
        <CLIENT_USERNAME>{$auth->merchant_user}</CLIENT_USERNAME>
        <CLIENT_PASSWORD>{$auth->merchant_password}</CLIENT_PASSWORD>
      </G>
      <GUID>{$guid}</GUID>
      <KK_Sahibi>{$request->sale_info->card_name_surname}</KK_Sahibi>
      <KK_No>{$request->sale_info->card_number}</KK_No>
      <KK_SK>{$expiry}</KK_SK>
      <KK_CVC>{$request->sale_info->card_cvv}</KK_CVC>
      <KK_Sahibi_GSM></KK_Sahibi_GSM>
      <Hata_URL>{$returnUrl}</Hata_URL>
      <Basarili_URL>{$returnUrl}</Basarili_URL>
      <Siparis_ID>{$request->order_number}</Siparis_ID>
      <Siparis_Aciklama></Siparis_Aciklama>
      <Taksit>{$installment}</Taksit>
      <Islem_Tutar>{$amount}</Islem_Tutar>
      <Toplam_Tutar>{$totalAmount}</Toplam_Tutar>
      <Islem_Hash>{$hash}</Islem_Hash>
      <Islem_Guvenlik_Tip>{$securityType}</Islem_Guvenlik_Tip>
      <Islem_ID></Islem_ID>
      <IPAdr>{$request->customer_ip_address}</IPAdr>
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
    return $this->httpPostRaw($url, $xml, [
      'Content-Type' => 'text/xml; charset=utf-8',
      'SOAPAction' => $soapAction,
    ]);
  }
}
