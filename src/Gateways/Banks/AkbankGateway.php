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

class AkbankGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://apipre.akbank.com/api/v1/payment/virtualpos/transaction/process';

    private string $urlAPILive = 'https://api.akbank.com/api/v1/payment/virtualpos/transaction/process';

    private string $url3DTest = 'https://virtualpospaymentgatewaypre.akbank.com/securepay';

    private string $url3DLive = 'https://virtualpospaymentgateway.akbank.com/securepay';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $request->saleInfo->currency = $request->saleInfo->currency ?? \EvrenOnur\SanalPos\Enums\Currency::TRY;
        $request->saleInfo->installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;

        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $totalStr = StringHelper::formatAmount($request->saleInfo->amount);
        $email = ! empty($request->invoiceInfo?->emailAddress) ? $request->invoiceInfo->emailAddress : 'test@test.com';

        $req = [
            'version' => '1.00',
            'txnCode' => '1000',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchantUser,
                'terminalSafeId' => $auth->merchantPassword,
            ],
            'order' => [
                'orderId' => $request->orderNumber,
            ],
            'card' => [
                'cardHolderName' => $request->saleInfo->cardNameSurname,
                'cardNumber' => $request->saleInfo->cardNumber,
                'cvv2' => $request->saleInfo->cardCVV,
                'expireDate' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            ],
            'transaction' => [
                'amount' => $totalStr,
                'currencyCode' => $request->saleInfo->currency->value,
                'motoInd' => 0,
                'installCount' => $request->saleInfo->installment,
            ],
            'customer' => [
                'emailAddress' => $email,
                'ipAddress' => $request->customerIPAddress,
            ],
        ];

        $link = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->privateResponse = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $transactionId = '';
            if (isset($responseDic['transaction']['authCode'])) {
                $transactionId = (string) $responseDic['transaction']['authCode'];
            }
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = $transactionId;

            return $response;
        }

        $errorMsg = 'İşlem sırasında bir hata oluştu';
        if (! empty($responseDic['responseMessage'])) {
            $errorMsg = $responseDic['responseMessage'];
        } elseif (($responseDic['code'] ?? '') === '401') {
            $errorMsg = 'Sanal pos üye işyeri bilgilerinizi kontrol ediniz';
        }

        $response->statu = SaleResponseStatu::Error;
        $response->message = $errorMsg;

        return $response;
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        $totalStr = StringHelper::formatAmount($request->saleInfo->amount);
        $email = ! empty($request->invoiceInfo?->emailAddress) ? $request->invoiceInfo->emailAddress : 'test@test.com';

        $req = [
            'paymentModel' => '3D',
            'txnCode' => '3000',
            'merchantSafeId' => $auth->merchantUser,
            'terminalSafeId' => $auth->merchantPassword,
            'orderId' => $request->orderNumber,
            'lang' => 'TR',
            'amount' => $totalStr,
            'currencyCode' => (string) $request->saleInfo->currency->value,
            'installCount' => (string) $request->saleInfo->installment,
            'okUrl' => $request->payment3D->returnURL,
            'failUrl' => $request->payment3D->returnURL,
            'emailAddress' => $email,
            'creditCard' => $request->saleInfo->cardNumber,
            'expiredDate' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT) . substr((string) $request->saleInfo->cardExpiryDateYear, 2),
            'cvv' => $request->saleInfo->cardCVV,
            'randomNumber' => $this->getRandomHex(128),
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'hash' => '',
        ];

        $hashItems = $req['paymentModel'] . $req['txnCode'] . $req['merchantSafeId'] .
            $req['terminalSafeId'] . $req['orderId'] . $req['lang'] .
            $req['amount'] . $req['currencyCode'] . $req['installCount'] .
            $req['okUrl'] . $req['failUrl'] . $req['emailAddress'] .
            $req['creditCard'] . $req['expiredDate'] . $req['cvv'] .
            $req['randomNumber'] . $req['requestDateTime'];

        $req['hash'] = $this->hmacHash($hashItems, $auth->merchantStorekey);

        $link = $auth->testPlatform ? $this->url3DTest : $this->url3DLive;
        $responseStr = $this->formRequest($req, $link);

        $response->privateResponse = ['stringResponse' => $responseStr];

        if (str_contains($responseStr, 'action="' . $req['failUrl'] . '"')) {
            $form = StringHelper::getFormParams($responseStr);
            if (! empty($form['responseMessage'])) {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $form['responseMessage'];

                return $response;
            }
        }

        $response->statu = SaleResponseStatu::RedirectHTML;
        $response->message = $responseStr;

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = ['response_1' => $request->responseArray];

        if (! empty($request->responseArray['orderId'])) {
            $response->orderNumber = $request->responseArray['orderId'];
        }

        if (($request->responseArray['responseCode'] ?? '') === 'VPS-0000' && ($request->responseArray['mdStatus'] ?? '') === '1') {
            $req = [
                'version' => '1.00',
                'txnCode' => '1000',
                'requestDateTime' => date('Y-m-d\TH:i:s.v'),
                'randomNumber' => $this->getRandomHex(128),
                'terminal' => [
                    'merchantSafeId' => $auth->merchantUser,
                    'terminalSafeId' => $auth->merchantPassword,
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

            $link = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
            $responseStr = $this->jsonRequest($req, $link, $auth);
            $responseDic = json_decode($responseStr, true) ?? [];

            $response->privateResponse['response_2'] = $responseDic;

            if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
                $transactionId = '';
                if (isset($responseDic['transaction']['authCode'])) {
                    $transactionId = (string) $responseDic['transaction']['authCode'];
                }
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarılı';
                $response->transactionId = $transactionId;

                return $response;
            }

            $errorMsg = 'İşlem sırasında bir hata oluştu';
            if (! empty($responseDic['responseMessage'])) {
                $errorMsg = $responseDic['responseMessage'];
            } elseif (($responseDic['code'] ?? '') === '401') {
                $errorMsg = 'Sanal pos üye işyeri bilgilerinizi kontrol ediniz';
            }

            $response->statu = SaleResponseStatu::Error;
            $response->message = $errorMsg;

            return $response;
        } elseif (! empty($request->responseArray['responseMessage'])) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['responseMessage'];
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = '3D doğrulaması başarısız';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);

        $req = [
            'version' => '1.00',
            'txnCode' => '1003',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchantUser,
                'terminalSafeId' => $auth->merchantPassword,
            ],
            'order' => ['orderId' => $request->orderNumber],
            'customer' => ['ipAddress' => $request->customerIPAddress],
        ];

        $link = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->privateResponse = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } elseif (! empty($responseDic['responseMessage'])) {
            $response->message = $responseDic['responseMessage'];
        } else {
            $response->message = 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $totalStr = StringHelper::formatAmount($request->refundAmount);

        $req = [
            'version' => '1.00',
            'txnCode' => '1002',
            'requestDateTime' => date('Y-m-d\TH:i:s.v'),
            'randomNumber' => $this->getRandomHex(128),
            'terminal' => [
                'merchantSafeId' => $auth->merchantUser,
                'terminalSafeId' => $auth->merchantPassword,
            ],
            'order' => ['orderId' => $request->orderNumber],
            'customer' => ['ipAddress' => $request->customerIPAddress],
            'transaction' => [
                'amount' => $totalStr,
                'currencyCode' => $request->currency?->value ?? 949,
            ],
        ];

        $link = $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive;
        $responseStr = $this->jsonRequest($req, $link, $auth);
        $responseDic = json_decode($responseStr, true) ?? [];

        $response->privateResponse = $responseDic;

        if (($responseDic['responseCode'] ?? '') === 'VPS-0000') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } elseif (! empty($responseDic['responseMessage'])) {
            $response->message = $responseDic['responseMessage'];
        } else {
            $response->message = 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        return new BINInstallmentQueryResponse;
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
        return new SaleQueryResponse(
            statu: SaleQueryResponseStatu::Error,
            message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor'
        );
    }

    // --- Private helpers ---

    private function jsonRequest(array $params, string $url, VirtualPOSAuth $auth): string
    {
        try {
            $json = json_encode($params, JSON_UNESCAPED_UNICODE);
            $hash = $this->hmacHash($json, $auth->merchantStorekey);

            $client = new Client(['verify' => false]);
            $response = $client->post($url, [
                'body' => $json,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'auth-hash' => $hash,
                ],
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formRequest(array $params, string $url): string
    {
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'form_params' => $params,
        ]);

        return $response->getBody()->getContents();
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
