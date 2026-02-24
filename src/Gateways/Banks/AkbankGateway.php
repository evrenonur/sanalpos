<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\MakesHttpRequests;
use EvrenOnur\SanalPos\Support\StringHelper;

class AkbankGateway implements VirtualPOSServiceInterface
{
    use MakesHttpRequests;

    private string $urlAPITest = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

    private string $urlAPILive = 'https://api.akbank.com/api/v1/payment/virtualpos/transaction/process';

    private string $url3DTest = 'https://virtualpospaymentgatewaypre.akbank.com/securepay';

    private string $url3DLive = 'https://virtualpospaymentgateway.akbank.com/securepay';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $request->sale_info->currency = $request->sale_info->currency ?? \EvrenOnur\SanalPos\Enums\Currency::TRY;
        $request->sale_info->installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;

        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);

        $totalStr = StringHelper::formatAmount($request->sale_info->amount);
        $email = ! empty($request->invoice_info?->email_address) ? $request->invoice_info->email_address : 'test@test.com';

        $req = [
            'version' => '1.00',
            'txnCode' => '1000',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchant_user,
                'terminalSafeId' => $auth->merchant_password,
            ],
            'order' => [
                'orderId' => $request->order_number,
            ],
            'card' => [
                'cardHolderName' => $request->sale_info->card_name_surname,
                'card_number' => $request->sale_info->card_number,
                'cvv2' => $request->sale_info->card_cvv,
                'expireDate' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
            ],
            'transaction' => [
                'amount' => $totalStr,
                'currencyCode' => $request->sale_info->currency->value,
                'motoInd' => 0,
                'installCount' => $request->sale_info->installment,
            ],
            'customer' => [
                'email_address' => $email,
                'ipAddress' => $request->customer_ip_address,
            ],
        ];

        $link = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->private_response = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $transaction_id = '';
            if (isset($responseDic['transaction']['authCode'])) {
                $transaction_id = (string) $responseDic['transaction']['authCode'];
            }
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = $transaction_id;

            return $response;
        }

        $errorMsg = 'İşlem sırasında bir hata oluştu';
        if (! empty($responseDic['responseMessage'])) {
            $errorMsg = $responseDic['responseMessage'];
        } elseif (($responseDic['code'] ?? '') === '401') {
            $errorMsg = 'Sanal pos üye işyeri bilgilerinizi kontrol ediniz';
        }

        $response->status = SaleResponseStatus::Error;
        $response->message = $errorMsg;

        return $response;
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        $totalStr = StringHelper::formatAmount($request->sale_info->amount);
        $email = ! empty($request->invoice_info?->email_address) ? $request->invoice_info->email_address : 'test@test.com';

        $req = [
            'paymentModel' => '3D',
            'txnCode' => '3000',
            'merchantSafeId' => $auth->merchant_user,
            'terminalSafeId' => $auth->merchant_password,
            'orderId' => $request->order_number,
            'lang' => 'TR',
            'amount' => $totalStr,
            'currencyCode' => (string) $request->sale_info->currency->value,
            'installCount' => (string) $request->sale_info->installment,
            'okUrl' => $request->payment_3d->return_url,
            'failUrl' => $request->payment_3d->return_url,
            'email_address' => $email,
            'creditCard' => $request->sale_info->card_number,
            'expiredDate' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT) . substr((string) $request->sale_info->card_expiry_year, 2),
            'cvv' => $request->sale_info->card_cvv,
            'randomNumber' => $this->getRandomHex(128),
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'hash' => '',
        ];

        $hashItems = $req['paymentModel'] . $req['txnCode'] . $req['merchantSafeId'] .
            $req['terminalSafeId'] . $req['orderId'] . $req['lang'] .
            $req['amount'] . $req['currencyCode'] . $req['installCount'] .
            $req['okUrl'] . $req['failUrl'] . $req['email_address'] .
            $req['creditCard'] . $req['expiredDate'] . $req['cvv'] .
            $req['randomNumber'] . $req['requestDateTime'];

        $req['hash'] = $this->hmacHash($hashItems, $auth->merchant_storekey);

        $link = $auth->test_platform ? $this->url3DTest : $this->url3DLive;
        $responseStr = $this->formRequest($req, $link);

        $response->private_response = ['stringResponse' => $responseStr];

        if (str_contains($responseStr, 'action="' . $req['failUrl'] . '"')) {
            $form = StringHelper::getFormParams($responseStr);
            if (! empty($form['responseMessage'])) {
                $response->status = SaleResponseStatus::Error;
                $response->message = $form['responseMessage'];

                return $response;
            }
        }

        $response->status = SaleResponseStatus::RedirectHTML;
        $response->message = $responseStr;

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        if (! empty($request->responseArray['orderId'])) {
            $response->order_number = $request->responseArray['orderId'];
        }

        if (($request->responseArray['responseCode'] ?? '') === 'VPS-0000' && ($request->responseArray['mdStatus'] ?? '') === '1') {
            $req = [
                'version' => '1.00',
                'txnCode' => '1000',
                'requestDateTime' => date('Y-m-d\TH:i:s.v'),
                'randomNumber' => $this->getRandomHex(128),
                'terminal' => [
                    'merchantSafeId' => $auth->merchant_user,
                    'terminalSafeId' => $auth->merchant_password,
                ],
                'order' => [
                    'orderId' => $request->responseArray['orderId'] ?? '',
                ],
                'transaction' => [
                    'amount' => $request->responseArray['amount'] ?? '',
                    'currencyCode' => $request->currency?->value ?? 949,
                    'motoInd' => 0,
                    'installCount' => (int) ($request->responseArray['installCount'] ?? 1),
                ],
                'secureTransaction' => [
                    'secureId' => $request->responseArray['secureId'] ?? '',
                    'secureEcomInd' => $request->responseArray['secureEcomInd'] ?? '',
                    'secureData' => $request->responseArray['secureData'] ?? '',
                    'secureMd' => $request->responseArray['secureMd'] ?? '',
                ],
            ];

            $link = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
            $responseStr = $this->jsonRequest($req, $link, $auth);
            $responseDic = json_decode($responseStr, true) ?? [];

            $response->private_response['response_2'] = $responseDic;

            if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
                $transaction_id = '';
                if (isset($responseDic['transaction']['authCode'])) {
                    $transaction_id = (string) $responseDic['transaction']['authCode'];
                }
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarılı';
                $response->transaction_id = $transaction_id;

                return $response;
            }

            $errorMsg = 'İşlem sırasında bir hata oluştu';
            if (! empty($responseDic['responseMessage'])) {
                $errorMsg = $responseDic['responseMessage'];
            } elseif (($responseDic['code'] ?? '') === '401') {
                $errorMsg = 'Sanal pos üye işyeri bilgilerinizi kontrol ediniz';
            }

            $response->status = SaleResponseStatus::Error;
            $response->message = $errorMsg;

            return $response;
        } elseif (! empty($request->responseArray['responseMessage'])) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $request->responseArray['responseMessage'];
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = '3D doğrulaması başarısız';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);

        $req = [
            'version' => '1.00',
            'txnCode' => '1003',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchant_user,
                'terminalSafeId' => $auth->merchant_password,
            ],
            'order' => ['orderId' => $request->order_number],
            'customer' => ['ipAddress' => $request->customer_ip_address],
        ];

        $link = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->private_response = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($responseDic['responseMessage'])) {
            $response->message = $responseDic['responseMessage'];
        } else {
            $response->message = 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $totalStr = StringHelper::formatAmount($request->refund_amount);

        $req = [
            'version' => '1.00',
            'txnCode' => '1002',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchant_user,
                'terminalSafeId' => $auth->merchant_password,
            ],
            'order' => ['orderId' => $request->order_number],
            'customer' => ['ipAddress' => $request->customer_ip_address],
            'transaction' => [
                'amount' => $totalStr,
                'currencyCode' => $request->currency?->value ?? 949,
            ],
        ];

        $link = $auth->test_platform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->private_response = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } elseif (! empty($responseDic['responseMessage'])) {
            $response->message = $responseDic['responseMessage'];
        } else {
            $response->message = 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        return new BINInstallmentQueryResponse;
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
        return new SaleQueryResponse(
            status: SaleQueryResponseStatus::Error,
            message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor'
        );
    }

    // --- Private helpers ---

    private function jsonRequest(array $params, string $url, MerchantAuth $auth): string
    {
        try {
            $json = json_encode($params, JSON_UNESCAPED_UNICODE);
            $hash = $this->hmacHash($json, $auth->merchant_storekey);

            return $this->httpPostRaw($url, $json, [
                'Content-Type' => 'application/json; charset=utf-8',
                'auth-hash' => $hash,
            ]);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formRequest(array $params, string $url): string
    {
        return $this->httpPostForm($url, $params);
    }

    private function hmacHash(string $message, string $key): string
    {
        return base64_encode(hash_hmac('sha512', $message, $key, true));
    }

    private function getRandomHex(int $length): string
    {
        $bytes = random_bytes($length);
        $result = '';
        foreach (str_split($bytes) as $byte) {
            $result .= strtoupper(dechex(ord($byte) % 16));
        }

        return $result;
    }
}
