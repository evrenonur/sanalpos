<?php

namespace EvrenOnur\SanalPos\Gateways\Providers\CCPayment;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Support\StringHelper;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\AllInstallment;
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
use GuzzleHttp\Client;

abstract class AbstractCCPaymentGateway implements VirtualPOSServiceInterface
{
    abstract protected function getTestBaseUrl(): string;

    abstract protected function getLiveBaseUrl(): string;

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $request->sale_info->currency = $request->sale_info->currency ?? Currency::TRY;
        $request->sale_info->installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;

        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        if (empty($token)) {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $total = StringHelper::formatAmount($request->sale_info->amount);
        $hashKey = $this->generateHashKey($total, (string) $request->sale_info->installment, (string) $request->sale_info->currency->value, $auth->merchant_storekey, $request->order_number, $auth->merchant_password);

        $body = [
            'cc_holder_name' => $request->sale_info->card_name_surname,
            'cc_no' => $request->sale_info->card_number,
            'expiry_month' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $request->sale_info->card_expiry_year,
            'cvv' => $request->sale_info->card_cvv,
            'currency_code' => (string) $request->sale_info->currency->value,
            'installments_number' => $request->sale_info->installment,
            'invoice_id' => $request->order_number,
            'invoice_description' => '',
            'name' => $request->sale_info->card_name_surname,
            'surname' => '',
            'total' => $total,
            'merchant_key' => $auth->merchant_storekey,
            'items' => json_encode([['name' => 'Item', 'price' => $total, 'quantity' => 1, 'description' => '']]),
            'hash_key' => $hashKey,
            'transaction_type' => 'Auth',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/paySmart2D', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->private_response = $respDic;

        $statusCode = (string) ($respDic['status_code'] ?? '');
        $paymentStatus = (string) ($respDic['data']['payment_status'] ?? ($respDic['payment_status'] ?? ''));

        if ($statusCode === '100' && ($paymentStatus === '1' || $this->skipPaymentStatusCheck())) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($respDic['data']['auth_code'] ?? '');
        } else {
            $response->status = SaleResponseStatus::Error;
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

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        if (empty($token)) {
            $response->status = SaleResponseStatus::Error;
            $response->message = 'Token alınamadı';

            return $response;
        }

        $total = StringHelper::formatAmount($request->sale_info->amount);
        $hashKey = $this->generateHashKey($total, (string) $request->sale_info->installment, (string) $request->sale_info->currency->value, $auth->merchant_storekey, $request->order_number, $auth->merchant_password);

        $body = [
            'cc_holder_name' => $request->sale_info->card_name_surname,
            'cc_no' => $request->sale_info->card_number,
            'expiry_month' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $request->sale_info->card_expiry_year,
            'cvv' => $request->sale_info->card_cvv,
            'currency_code' => (string) $request->sale_info->currency->value,
            'installments_number' => $request->sale_info->installment,
            'invoice_id' => $request->order_number,
            'invoice_description' => '',
            'name' => $request->sale_info->card_name_surname,
            'surname' => '',
            'total' => $total,
            'merchant_key' => $auth->merchant_storekey,
            'items' => json_encode([['name' => 'Item', 'price' => $total, 'quantity' => 1, 'description' => '']]),
            'hash_key' => $hashKey,
            'transaction_type' => 'Auth',
            'response_method' => 'POST',
            'payment_completed_by' => 'app',
            'ip' => $request->customer_ip_address,
            'cancel_url' => $request->payment_3d->return_url,
            'return_url' => $request->payment_3d->return_url,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/paySmart3D', $body, $token);

        $response->private_response = ['stringResponse' => $resp];
        $response->status = SaleResponseStatus::RedirectHTML;
        $response->message = $resp;

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = $request->responseArray;

        $response->transaction_id = (string) ($request->responseArray['auth_code'] ?? '');
        $response->order_number = (string) ($request->responseArray['invoice_id'] ?? '');

        // Hash doğrulaması
        $hashKey = $request->responseArray['hash_key'] ?? '';
        if (! empty($hashKey)) {
            $validated = $this->validateHashKey($hashKey, $auth->merchant_password);
            if ($validated === false || (is_array($validated) && ! in_array($response->order_number, $validated))) {
                $response->status = SaleResponseStatus::Error;
                $response->message = 'Hash doğrulanamadı, ödeme onaylanmadı.';

                return $response;
            }
        }

        $paymentStatus = (string) ($request->responseArray['payment_status'] ?? '');
        $statusCode = (string) ($request->responseArray['status_code'] ?? '');

        if ($paymentStatus === '1' || ($this->skipPaymentStatusCheck() && $statusCode === '100')) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $request->responseArray['error'] ?? ($request->responseArray['status_description'] ?? 'İşlem sırasında bir hata oluştu');
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'invoice_id' => $request->order_number,
            'amount' => 0,
            'app_id' => $auth->merchant_user,
            'app_secret' => $auth->merchant_password,
            'merchant_key' => $auth->merchant_storekey,
            'hash_key' => '',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/refund', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->private_response = $respDic;

        if ((string) ($respDic['status_code'] ?? '') === '100') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $respDic['status_description'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'invoice_id' => $request->order_number,
            'amount' => StringHelper::formatAmount($request->refund_amount),
            'app_id' => $auth->merchant_user,
            'app_secret' => $auth->merchant_password,
            'merchant_key' => $auth->merchant_storekey,
            'hash_key' => '',
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/refund', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->private_response = $respDic;

        if ((string) ($respDic['status_code'] ?? '') === '100') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $respDic['status_description'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'credit_card' => $request->BIN,
            'amount' => StringHelper::formatAmount($request->amount),
            'currency_code' => (string) ($request->currency?->value ?? 949),
            'merchant_key' => $auth->merchant_storekey,
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/getpos', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->private_response = $respDic;

        if (isset($respDic['data']) && is_array($respDic['data'])) {
            foreach ($respDic['data'] as $item) {
                $installmentsNumber = (int) ($item['installments_number'] ?? 0);
                if ($installmentsNumber > 1) {
                    $payableAmount = (float) ($item['payable_amount'] ?? 0);
                    $originalAmount = $request->amount;
                    $rate = $originalAmount > 0 ? (($payableAmount - $originalAmount) / $originalAmount) * 100 : 0;

                    $response->installment_list[] = [
                        'installment' => $installmentsNumber,
                        'rate' => round($rate, 2),
                        'totalAmount' => $payableAmount,
                    ];
                }
            }
            if (! empty($response->installment_list)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse
    {
        $response = new AllInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);
        $token = $this->getToken($baseUrl, $auth);

        $body = [
            'currency_code' => (string) ($request->currency?->value ?? 949),
        ];

        $resp = $this->jsonRequest($baseUrl . '/api/commissions', $body, $token);
        $respDic = json_decode($resp, true) ?? [];

        $response->private_response = $respDic;

        $cardProgramField = $this->getCardProgramFieldName();

        if (isset($respDic['data']) && is_array($respDic['data'])) {
            $installment_list = [];
            foreach ($respDic['data'] as $item) {
                $programName = $item[$cardProgramField] ?? '';
                $program = CreditCardProgram::tryFromName($programName) ?? CreditCardProgram::Other;
                if (! isset($installment_list[$programName])) {
                    $installment_list[$programName] = new AllInstallment(
                        cardProgram: $program,
                        installment_list: [],
                    );
                }
                $installment_list[$programName]->installment_list[] = [
                    'installment' => (int) ($item['installments_number'] ?? 0),
                    'rate' => (float) ($item['merchant_commission_rate'] ?? 0),
                ];
            }
            $response->installment_list = array_values($installment_list);
            if (! empty($response->installment_list)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, MerchantAuth $auth): AdditionalInstallmentQueryResponse
    {
        return new AdditionalInstallmentQueryResponse(confirm: false);
    }

    public function saleQuery(SaleQueryRequest $request, MerchantAuth $auth): SaleQueryResponse
    {
        return new SaleQueryResponse(status: SaleQueryResponseStatus::Error, message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor');
    }

    // --- Protected/Private Helpers ---

    protected function getBaseUrl(MerchantAuth $auth): string
    {
        return $auth->test_platform ? $this->getTestBaseUrl() : $this->getLiveBaseUrl();
    }

    protected function getToken(string $baseUrl, MerchantAuth $auth): string
    {
        try {
            $body = [
                'app_id' => $auth->merchant_user,
                'app_secret' => $auth->merchant_password,
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
