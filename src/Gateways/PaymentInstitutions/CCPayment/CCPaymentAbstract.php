<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions\CCPayment;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Helpers\StringHelper;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallment;
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

abstract class CCPaymentAbstract implements VirtualPOSServiceInterface
{
    abstract protected function getTestBaseUrl(): string;

    abstract protected function getLiveBaseUrl(): string;

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $request->saleInfo->currency = $request->saleInfo->currency ?? Currency::TRY;
        $request->saleInfo->installment = $request->saleInfo->installment > 1 ? $request->saleInfo->installment : 1;

        if ($request->payment3D?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        if (empty($token)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $total = StringHelper::formatAmount($request->saleInfo->amount);
        $hashKey = $this->generateHashKey($total, (string) $request->saleInfo->installment, (string) $request->saleInfo->currency->value, $auth->merchantStorekey, $request->orderNumber, $auth->merchantPassword);

        $body = [
            'cc_holder_name' => $request->saleInfo->cardNameSurname,
            'cc_no' => $request->saleInfo->cardNumber,
            'expiry_month' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $request->saleInfo->cardExpiryDateYear,
            'cvv' => $request->saleInfo->cardCVV,
            'currency_code' => (string) $request->saleInfo->currency->value,
            'installments_number' => $request->saleInfo->installment,
            'invoice_id' => $request->orderNumber,
            'invoice_description' => '',
            'name' => $request->saleInfo->cardNameSurname,
            'surname' => '',
            'total' => $total,
            'merchant_key' => $auth->merchantStorekey,
            'items' => json_encode([['name' => 'Item', 'price' => $total, 'quantity' => 1, 'description' => '']]),
            'hash_key' => $hashKey,
            'transaction_type' => 'Auth',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/paySmart2D', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->privateResponse = $respDic;

        $statusCode = (string) ($respDic['status_code'] ?? '');
        $paymentStatus = (string) ($respDic['data']['payment_status'] ?? ($respDic['payment_status'] ?? ''));

        if ($statusCode === '100' && ($paymentStatus === '1' || $this->skipPaymentStatusCheck())) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->transactionId = (string) ($respDic['data']['auth_code'] ?? '');
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $respDic['status_description'] ?? ($respDic['message'] ?? 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    /**
     * Sipay gibi bazı gateway'ler payment_status kontrolünü atlar.
     * Override edilebilir.
     */
    protected function skipPaymentStatusCheck(): bool
    {
        return false;
    }

    /**
     * AllInstallmentQuery'de kart program alan adı.
     * Sipay "getpos_card_program" kullanır, diğerleri "card_program".
     */
    protected function getCardProgramFieldName(): string
    {
        return 'card_program';
    }

    private function sale3D(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        if (empty($token)) {
            $response->statu = SaleResponseStatu::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $total = StringHelper::formatAmount($request->saleInfo->amount);
        $hashKey = $this->generateHashKey($total, (string) $request->saleInfo->installment, (string) $request->saleInfo->currency->value, $auth->merchantStorekey, $request->orderNumber, $auth->merchantPassword);

        $body = [
            'cc_holder_name' => $request->saleInfo->cardNameSurname,
            'cc_no' => $request->saleInfo->cardNumber,
            'expiry_month' => str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $request->saleInfo->cardExpiryDateYear,
            'cvv' => $request->saleInfo->cardCVV,
            'currency_code' => (string) $request->saleInfo->currency->value,
            'installments_number' => $request->saleInfo->installment,
            'invoice_id' => $request->orderNumber,
            'invoice_description' => '',
            'name' => $request->saleInfo->cardNameSurname,
            'surname' => '',
            'total' => $total,
            'merchant_key' => $auth->merchantStorekey,
            'items' => json_encode([['name' => 'Item', 'price' => $total, 'quantity' => 1, 'description' => '']]),
            'hash_key' => $hashKey,
            'transaction_type' => 'Auth',
            'response_method' => 'POST',
            'payment_completed_by' => 'app',
            'ip' => $request->customerIPAddress,
            'cancel_url' => $request->payment3D->returnURL,
            'return_url' => $request->payment3D->returnURL,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/paySmart3D', $body, $token);

        $response->privateResponse = ['stringResponse' => $resp];
        $response->statu = SaleResponseStatu::RedirectHTML;
        $response->message = $resp;

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->privateResponse = $request->responseArray;

        $response->transactionId = (string) ($request->responseArray['auth_code'] ?? '');
        $response->orderNumber = (string) ($request->responseArray['invoice_id'] ?? '');

        // Hash doğrulaması
        $hashKey = $request->responseArray['hash_key'] ?? '';
        if (! empty($hashKey)) {
            $validated = $this->validateHashKey($hashKey, $auth->merchantPassword);
            if ($validated === false || (is_array($validated) && ! in_array($response->orderNumber, $validated))) {
                $response->statu = SaleResponseStatu::Error;
                $response->message = 'Hash doğrulanamadı, ödeme onaylanmadı.';

                return $response;
            }
        }

        $paymentStatus = (string) ($request->responseArray['payment_status'] ?? '');
        $statusCode = (string) ($request->responseArray['status_code'] ?? '');

        if ($paymentStatus === '1' || ($this->skipPaymentStatusCheck() && $statusCode === '100')) {
            $response->statu = SaleResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->statu = SaleResponseStatu::Error;
            $response->message = $request->responseArray['error'] ?? ($request->responseArray['status_description'] ?? 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'invoice_id' => $request->orderNumber,
            'amount' => 0,
            'app_id' => $auth->merchantUser,
            'app_secret' => $auth->merchantPassword,
            'merchant_key' => $auth->merchantStorekey,
            'hash_key' => '',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/refund', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->privateResponse = $respDic;

        if ((string) ($respDic['status_code'] ?? '') === '100') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $respDic['status_description'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'invoice_id' => $request->orderNumber,
            'amount' => StringHelper::formatAmount($request->refundAmount),
            'app_id' => $auth->merchantUser,
            'app_secret' => $auth->merchantPassword,
            'merchant_key' => $auth->merchantStorekey,
            'hash_key' => '',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/refund', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->privateResponse = $respDic;

        if ((string) ($respDic['status_code'] ?? '') === '100') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İşlem başarılı';
            $response->refundAmount = $request->refundAmount;
        } else {
            $response->message = $respDic['status_description'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'credit_card' => $request->BIN,
            'amount' => StringHelper::formatAmount($request->amount),
            'currency_code' => (string) ($request->currency?->value ?? 949),
            'merchant_key' => $auth->merchantStorekey,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/getpos', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->privateResponse = $respDic;

        if (isset($respDic['data']) && is_array($respDic['data'])) {
            foreach ($respDic['data'] as $item) {
                $installmentsNumber = (int) ($item['installments_number'] ?? 0);
                if ($installmentsNumber > 1) {
                    $payableAmount = (float) ($item['payable_amount'] ?? 0);
                    $originalAmount = $request->amount;
                    $rate = $originalAmount > 0 ? (($payableAmount - $originalAmount) / $originalAmount) * 100 : 0;

                    $response->installmentList[] = [
                        'installment' => $installmentsNumber,
                        'rate' => round($rate, 2),
                        'totalAmount' => $payableAmount,
                    ];
                }
            }
            if (! empty($response->installmentList)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse
    {
        $response = new AllInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'currency_code' => (string) ($request->currency?->value ?? 949),
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/commissions', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->privateResponse = $respDic;

        $cardProgramField = $this->getCardProgramFieldName();

        if (isset($respDic['data']) && is_array($respDic['data'])) {
            $installmentList = [];
            foreach ($respDic['data'] as $item) {
                $programName = $item[$cardProgramField] ?? '';
                $program = CreditCardProgram::tryFromName($programName) ?? CreditCardProgram::Other;
                if (! isset($installmentList[$programName])) {
                    $installmentList[$programName] = new AllInstallment(
                        cardProgram: $program,
                        installmentList: [],
                    );
                }
                $installmentList[$programName]->installmentList[] = [
                    'installment' => (int) ($item['installments_number'] ?? 0),
                    'rate' => (float) ($item['merchant_commission_rate'] ?? 0),
                ];
            }
            $response->installmentList = array_values($installmentList);
            if (! empty($response->installmentList)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, VirtualPOSAuth $auth): AdditionalInstallmentQueryResponse
    {
        return new AdditionalInstallmentQueryResponse(confirm: false);
    }

    public function saleQuery(SaleQueryRequest $request, VirtualPOSAuth $auth): SaleQueryResponse
    {
        return new SaleQueryResponse(statu: SaleQueryResponseStatu::Error, message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor');
    }

    // --- Protected/Private Helpers ---

    protected function getBaseUrl(VirtualPOSAuth $auth): string
    {
        return $auth->testPlatform ? $this->getTestBaseUrl() : $this->getLiveBaseUrl();
    }

    protected function getToken(string $baseUrl, VirtualPOSAuth $auth): string
    {
        try {
            $body = [
                'app_id' => $auth->merchantUser,
                'app_secret' => $auth->merchantPassword,
            ];
            $resp = $this->jsonRequest($baseUrl . '/api/token', $body);
            $data = json_decode($resp, true) ?? [];

            return $data['data']['token'] ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function generateHashKey(string $total, string $installment, string $currencyCode, string $merchantKey, string $invoiceId, string $appSecret): string
    {
        $data = implode('|', [$total, $installment, $currencyCode, $merchantKey, $invoiceId]);
        $iv = substr(sha1((string) random_int(100000, 999999)), 0, 16);
        $password = sha1($appSecret);
        $salt = substr(sha1((string) random_int(100000, 999999)), 0, 4);
        $saltWithPassword = substr(hash('sha256', $password . $salt), 0, 32);

        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $saltWithPassword, 0, $iv);

        $hashKey = $iv . ':' . $salt . ':' . $encrypted;

        return str_replace('/', '__', $hashKey);
    }

    protected function validateHashKey(string $hashKey, string $appSecret): array|false
    {
        try {
            $hashKey = str_replace('__', '/', $hashKey);
            $password = sha1($appSecret);
            $parts = explode(':', $hashKey, 3);
            if (count($parts) !== 3) {
                return false;
            }
            [$iv, $salt, $encrypted] = $parts;
            $saltWithPassword = substr(hash('sha256', $password . $salt), 0, 32);

            $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $saltWithPassword, 0, $iv);

            if ($decrypted === false) {
                return false;
            }

            return explode('|', $decrypted);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function jsonRequest(string $url, array $body, ?string $token = null): string
    {
        try {
            $headers = ['Content-Type' => 'application/json; charset=utf-8'];
            if (! empty($token)) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            $client = new Client(['verify' => false]);
            $response = $client->post($url, [
                'json' => $body,
                'headers' => $headers,
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
