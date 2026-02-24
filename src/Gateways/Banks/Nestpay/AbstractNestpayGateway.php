<?php

namespace EvrenOnur\SanalPos\Gateways\Banks\Nestpay;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryTransactionStatus;
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
use EvrenOnur\SanalPos\Support\MakesHttpRequests;

abstract class AbstractNestpayGateway implements VirtualPOSServiceInterface
{
    use MakesHttpRequests;
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

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'İşlem sırasında bilinmeyen bir hata oluştu.',
            order_number: $request->order_number,
        );

        $installment = $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '';

        $param = [
            'Name' => $auth->merchant_user,
            'Password' => $auth->merchant_password,
            'ClientId' => $auth->merchant_id,
            'Type' => 'Auth',
            'OrderId' => $request->order_number,
            'Taksit' => $installment,
            'Total' => StringHelper::formatAmount($request->sale_info->amount),
            'Currency' => (string) $request->sale_info->currency->value,
            'Number' => $request->sale_info->card_number,
            'Expires' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . '/' . $request->sale_info->card_expiry_year,
            'Cvv2Val' => $request->sale_info->card_cvv,
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->test_platform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->private_response = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline', 'Declined'])) {
                $response->status = SaleResponseStatus::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
                $response->transaction_id = $respDic['TransId'] ?? '';
            }
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(
            status: SaleResponseStatus::Error,
            order_number: $request->responseArray['oid'] ?? '',
            message: 'İşlem sırasında bilinmeyen bir hata oluştu',
        );

        $response->private_response = ['response_1' => $request->responseArray];

        if (isset($request->responseArray['mdStatus']) && $request->responseArray['mdStatus'] === '1') {
            $installment = $request->responseArray['installment'] ?? '';

            $param = [
                'Name' => $auth->merchant_user,
                'Password' => $auth->merchant_password,
                'ClientId' => $auth->merchant_id,
                'IPAddress' => $request->responseArray['clientIp'] ?? '',
                'OrderId' => $response->order_number,
                'Taksit' => $installment,
                'Type' => 'Auth',
                'Number' => $request->responseArray['md'] ?? '',
                'PayerTxnId' => $request->responseArray['xid'] ?? '',
                'PayerSecurityLevel' => $request->responseArray['eci'] ?? '',
                'PayerAuthenticationCode' => $request->responseArray['cavv'] ?? '',
            ];

            $xml = StringHelper::toXml($param);
            $apiUrl = $auth->test_platform ? $this->getUrlAPITest() : $this->getUrlAPILive();
            $resp = $this->xmlRequest($xml, $apiUrl);
            $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

            $response->private_response['response_2'] = $respDic;

            if (isset($respDic['Response'])) {
                if (in_array($respDic['Response'], ['Error', 'Decline', 'Declined'])) {
                    $response->status = SaleResponseStatus::Error;
                    $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
                } elseif ($respDic['Response'] === 'Approved') {
                    $response->status = SaleResponseStatus::Success;
                    $response->message = 'İşlem başarıyla tamamlandı';
                    $response->transaction_id = $respDic['TransId'] ?? '';
                }
            }
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = '3D doğrulaması başarısız.';
        }

        return $response;
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
        $response = new SaleQueryResponse(
            status: SaleQueryResponseStatus::NotFound,
            message: 'Sipariş bulunamadı',
            order_number: $request->order_number,
        );

        $param = [
            'Name' => $auth->merchant_user,
            'Password' => $auth->merchant_password,
            'ClientId' => $auth->merchant_id,
            'OrderId' => $request->order_number,
            'Extra' => ['ORDERSTATUS' => 'QUERY'],
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->test_platform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->private_response = $respDic;

        if (isset($respDic['Response'])) {
            if ($respDic['Response'] === 'Approved') {
                $response->status = SaleQueryResponseStatus::Found;
                $response->message = 'İşlem bulundu';
                $response->transaction_id = $respDic['TransId'] ?? '';
            } else {
                $response->status = SaleQueryResponseStatus::Error;
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
                    'S' => SaleQueryTransactionStatus::Paid,
                    'V' => SaleQueryTransactionStatus::Voided,
                    default => null,
                };
            }
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);

        $param = [
            'Name' => $auth->merchant_user,
            'Password' => $auth->merchant_password,
            'ClientId' => $auth->merchant_id,
            'Type' => 'Void',
            'TransId' => $request->transaction_id,
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->test_platform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->private_response = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline'])) {
                $response->status = ResponseStatus::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->status = ResponseStatus::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
            }
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $param = [
            'Name' => $auth->merchant_user,
            'Password' => $auth->merchant_password,
            'ClientId' => $auth->merchant_id,
            'Type' => 'Credit',
            'TransId' => $request->transaction_id,
            'Total' => StringHelper::formatAmount($request->refund_amount),
        ];

        $xml = StringHelper::toXml($param);
        $apiUrl = $auth->test_platform ? $this->getUrlAPITest() : $this->getUrlAPILive();
        $resp = $this->xmlRequest($xml, $apiUrl);
        $respDic = StringHelper::xmlToDictionary($resp, 'CC5Response');

        $response->private_response = $respDic;

        if (isset($respDic['Response'])) {
            if (in_array($respDic['Response'], ['Error', 'Decline'])) {
                $response->status = ResponseStatus::Error;
                $response->message = $respDic['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
            } elseif ($respDic['Response'] === 'Approved') {
                $response->status = ResponseStatus::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
            }
        }

        return $response;
    }

    /**
     * 3D Secure ile ödeme başlatma
     */
    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;

        $installment = $request->sale_info->installment > 1 ? (string) $request->sale_info->installment : '';

        $param = [
            'pan' => $request->sale_info->card_number,
            'cv2' => $request->sale_info->card_cvv,
            'Ecom_Payment_Card_ExpDate_Year' => substr((string) $request->sale_info->card_expiry_year, 2),
            'Ecom_Payment_Card_ExpDate_Month' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'clientid' => $auth->merchant_id,
            'amount' => StringHelper::formatAmount($request->sale_info->amount),
            'oid' => $request->order_number,
            'okUrl' => $request->payment_3d->return_url,
            'failUrl' => $request->payment_3d->return_url,
            'rnd' => (string) (int) (microtime(true) * 1000),
            'storetype' => '3d',
            'lang' => 'tr',
            'currency' => (string) $request->sale_info->currency->value,
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
        $hashStr = implode('|', $hashValues) . '|' . $auth->merchant_storekey;
        $hash = $this->getHash($hashStr);

        $param['hash'] = $hash;

        $url3D = $auth->test_platform ? $this->getUrl3DTest() : $this->getUrl3DLive();
        $resp = $this->formRequest($param, $url3D);

        $form = StringHelper::getFormParams($resp);

        $response->private_response = $form;
        $response->order_number = $request->order_number;

        if (isset($form['Response']) && in_array($form['Response'], ['Error', 'Decline'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $form['ErrMsg'] ?? 'İşlem sırasında bir hata oluştu.';
        } else {
            $response->status = SaleResponseStatus::RedirectHTML;
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
        return $this->httpPostForm($url, $params, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }

    /**
     * XML POST isteği (DATA= formatında)
     */
    private function xmlRequest(string $xml, string $url): string
    {
        return $this->httpPostForm($url, ['DATA' => $xml], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }
}
