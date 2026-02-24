<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryTransactionStatu;
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

abstract class NestpayAbstractGateway implements VirtualPOSServiceInterface
{
    protected string $urlAPITest = 'https://entegrasyon.asseco-see.com.tr/fim/api';

    protected string $urlAPILive = '';

    protected string $url3DTest = 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate';

    protected string $url3DLive = '';

    abstract protected function getUrlAPILive(): string;

    abstract protected function getUrl3DLive(): string;

    protected function getUrlAPITest(): string
    {
        return 'https://entegrasyon.asseco-see.com.tr/fim/api';
    }

    protected function getUrl3DTest(): string
    {
        return 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate';
    }

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(
            statu: SaleResponseStatu::Error,
            message: 'İşlem sırasında bilinmeyen bir hata oluştu.',
            orderNumber: $request->orderNumber,
        );

        $installment = $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '';

        $param = [
            'Name' => $auth->merchantUser,
            'Password' => $auth->merchantPassword,
            'ClientId' => $auth->merchantID,
            'Type' => 'Auth',
            'OrderId' => $request->orderNumber,
            'Taksit' => $installment,
            'Total' => StringHelper::formatAmount($request->saleInfo->amount),
            'Currency' => (string) $request->saleInfo->currency->value,
            'Number' => $request->saleInfo->cardNumber,
            'Expires' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . '/' . $request->saleInfo->cardExpiryDateYear,
            'Cvv2Val' => $request->saleInfo->cardCVV,
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->testPlatform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->privateResponse = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline', 'Declined'])) {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
                $response->transactionId = $respDic['TransId'] ?? '';
            }
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(
            statu: SaleResponseStatu::Error,
            orderNumber: $request->responseArray['oid'] ?? '',
            message: 'İşlem sırasında bilinmeyen bir hata oluştu',
        );

        $response->privateResponse = ['response_1' => $request->responseArray];

        if (isset($request->responseArray['mdStatus']) && $request->responseArray['mdStatus'] === '1') {
            $installment = $request->responseArray['installment'] ?? '';

            $param = [
                'Name' => $auth->merchantUser,
                'Password' => $auth->merchantPassword,
                'ClientId' => $auth->merchantID,
                'IPAddress' => $request->responseArray['clientIp'] ?? '',
                'OrderId' => $response->orderNumber,
                'Taksit' => $installment,
                'Type' => 'Auth',
                'Number' => $request->responseArray['md'] ?? '',
                'PayerTxnId' => $request->responseArray['xid'] ?? '',
                'PayerSecurityLevel' => $request->responseArray['eci'] ?? '',
                'PayerAuthenticationCode' => $request->responseArray['cavv'] ?? '',
            ];

            $xml = StringHelper::toXml($param);
            $apiUrl = $auth->testPlatform ? $this->getUrlAPITest() : $this->getUrlAPILive();
            $resp = $this->xmlRequest($xml, $apiUrl);
            $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

            $response->privateResponse['response_2'] = $respDic;

            if (isset($respDic['Response'])) {
                if (in_array($respDic['Response'], ['Error', 'Decline', 'Declined'])) {
                    $response->statu = SaleResponseStatu::Error;
                    $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
                } elseif ($respDic['Response'] === 'Approved') {
                    $response->statu = SaleResponseStatu::Success;
                    $response->message = 'İşlem başarıyla tamamlandı';
                    $response->transactionId = $respDic['TransId'] ?? '';
                }
            }
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = '3D doğrulaması başarısız.';
        }

        return $response;
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
        $response = new SaleQueryResponse(
            statu: SaleQueryResponseStatu::NotFound,
            message: 'Sipariş bulunamadı',
            orderNumber: $request->orderNumber,
        );

        $param = [
            'Name' => $auth->merchantUser,
            'Password' => $auth->merchantPassword,
            'ClientId' => $auth->merchantID,
            'OrderId' => $request->orderNumber,
            'Extra' => ['ORDERSTATUS' => 'QUERY'],
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->testPlatform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->privateResponse = $respDic;

        if (isset($respDic['Response'])) {
            if ($respDic['Response'] === 'Approved') {
                $response->statu = SaleQueryResponseStatu::Found;
                $response->message = 'İşlem bulundu';
                $response->transactionId = $respDic['TransId'] ?? '';
            } else {
                $response->statu = SaleQueryResponseStatu::Error;
                $response->message = $respDic['ErrMsg'] ?? 'Sipariş bulunamadı';
            }
        }

        if (isset($respDic['Extra']) && is_array($respDic['Extra'])) {
            $extra = $respDic['Extra'];

            if (isset($extra['CAPTURE_AMT'])) {
                $response->amount = (float) str_replace(',', '.', $extra['CAPTURE_AMT']);
            }

            if (isset($extra['CAPTURE_DTTM'])) {
                $dateStr = $extra['CAPTURE_DTTM'];
                $dotPos = strpos($dateStr, '.');
                if ($dotPos !== false) {
                    $dateStr = substr($dateStr, 0, $dotPos);
                }
                $response->transactionDate = $dateStr;
            }

            if (isset($extra['TRANS_STAT'])) {
                $response->transactionStatu = match ($extra['TRANS_STAT']) {
                    'S' => SaleQueryTransactionStatu::Paid,
                    'V' => SaleQueryTransactionStatu::Voided,
                    default => null,
                };
            }
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);

        $param = [
            'Name' => $auth->merchantUser,
            'Password' => $auth->merchantPassword,
            'ClientId' => $auth->merchantID,
            'Type' => 'Void',
            'TransId' => $request->transactionId,
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->testPlatform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->privateResponse = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline'])) {
                $response->statu = ResponseStatu::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->statu = ResponseStatu::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
            }
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $param = [
            'Name' => $auth->merchantUser,
            'Password' => $auth->merchantPassword,
            'ClientId' => $auth->merchantID,
            'Type' => 'Credit',
            'TransId' => $request->transactionId,
            'Total' => StringHelper::formatAmount($request->refundAmount),
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->testPlatform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->privateResponse = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline'])) {
                $response->statu = ResponseStatu::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->statu = ResponseStatu::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
            }
        }

        return $response;
    }

    /**
     * 3D Secure ile ödeme başlatma
     */
    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;

        $installment = $request->saleInfo->installment > 1 ? (string) $request->saleInfo->installment : '';

        $param = [
            'pan' => $request->saleInfo->cardNumber,
            'cv2' => $request->saleInfo->cardCVV,
            'Ecom_Payment_Card_ExpDate_Year' => substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            'Ecom_Payment_Card_ExpDate_Month' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'clientid' => $auth->merchantID,
            'amount' => StringHelper::formatAmount($request->saleInfo->amount),
            'oid' => $request->orderNumber,
            'okUrl' => $request->payment3D->returnURL,
            'failUrl' => $request->payment3D->returnURL,
            'rnd' => (string) (int) (microtime(true) * 1000),
            'storetype' => '3d',
            'lang' => 'tr',
            'currency' => (string) $request->saleInfo->currency->value,
            'installment' => $installment,
            'taksit' => $installment,
            'islemtipi' => 'Auth',
            'hashAlgorithm' => 'ver3',
        ];

        // ver3 hash oluşturma
        ksort($param);
        $hashValues = [];
        foreach ($param as $value) {
            $escaped = str_replace('\\', '\\\\', str_replace('|', '\\|', (string) $value));
            $hashValues[] = $escaped;
        }
        $hashStr = implode('|', $hashValues) . '|' . $auth->merchantStorekey;
        $hash = $this->getHash($hashStr);

        $param['hash'] = $hash;

        $url3D = $auth->testPlatform ? $this->getUrl3DTest() : $this->getUrl3DLive();
        $resp = $this->formRequest($param, $url3D);

        $form = StringHelper::getFormParams($resp);

        $response->privateResponse = $form;
        $response->orderNumber = $request->orderNumber;

        if (isset($form['Response']) && in_array($form['Response'], ['Error', 'Decline'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $form['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
        } else {
            $response->statu = SaleResponseStatu::RedirectHTML;
            $response->message = $resp;
        }

        return $response;
    }

    /**
     * SHA512 hash oluşturma
     */
    private function getHash(string $hashStr): string
    {
        return base64_encode(hash('sha512', $hashStr, true));
    }

    /**
     * Form-encoded POST isteği
     */
    private function formRequest(array $params, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'form_params' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * XML POST isteği (DATA= formatında)
     */
    private function xmlRequest(string $xml, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'form_params' => ['DATA' => $xml],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        return $response->getBody()->getContents();
    }
}
