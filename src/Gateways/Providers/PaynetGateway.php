<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\DTOs\AllInstallment;
use EvrenOnur\SanalPos\DTOs\Installment;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class PaynetGateway extends AbstractGateway
{
    private string $urlTest = 'https://pts-api.paynet.com.tr';

    private string $urlLive = 'https://api.paynet.com.tr';

    private const REPRESENTATIVE_BINS = [
        CreditCardProgram::Axess->name => '413252',
        CreditCardProgram::Bankkart->name => '404591',
        CreditCardProgram::Bonus->name => '374421',
        CreditCardProgram::CardFinans->name => '401072',
        CreditCardProgram::Maximum->name => '418342',
        CreditCardProgram::MilesAndSmiles->name => '374422',
        CreditCardProgram::Neo->name => '474853',
        CreditCardProgram::Paraf->name => '415514',
        CreditCardProgram::ShopAndFly->name => '377596',
        CreditCardProgram::Wings->name => '432071',
        CreditCardProgram::World->name => '401622',
    ];

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        if ($request->payment_3d?->confirm === true) {
            return $this->sale3D($request, $auth);
        }

        $response = new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'Bilinmeyen bir hata oluştu',
            order_number: $request->order_number,
        );

        $body = [
            'amount' => $this->formatSaleAmount($request->sale_info->amount),
            'reference_no' => $request->order_number,
            'domain' => $this->resolveHost($request->payment_3d?->return_url),
            'card_holder' => $request->sale_info->card_name_surname,
            'pan' => $request->sale_info->card_number,
            'month' => $request->sale_info->card_expiry_month,
            'year' => $request->sale_info->card_expiry_year,
            'cvc' => $request->sale_info->card_cvv,
            'card_holder_phone' => $request->invoice_info?->phone_number ?? '',
            'card_holder_mail' => $request->invoice_info?->email_address ?? '',
            'instalment' => max(1, $request->sale_info->installment),
            'add_commission' => max(1, $request->sale_info->installment) > 1,
            'transaction_type' => 1,
        ];

        $result = $this->requestJson($this->getBaseUrl($auth) . '/v2/transaction/payment', $body, $auth);
        $response->private_response = $result;

        if (($result['is_succeed'] ?? false) === true) {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($result['xact_id'] ?? '');
        } elseif (! empty($result['paynet_error_message'] ?? '')) {
            $response->message = (string) $result['paynet_error_message'];
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'İşlem sırasında bilinmeyen bir hata oluştu.',
        );
        $response->private_response = ['response_1' => $request->responseArray];

        $responseArray = $request->responseArray ?? [];
        if (isset($responseArray['session_id'], $responseArray['token_id'])) {
            $body = [
                'session_id' => (string) $responseArray['session_id'],
                'token_id' => (string) $responseArray['token_id'],
                'transaction_type' => 1,
            ];

            $result = $this->requestJson($this->getBaseUrl($auth) . '/v2/transaction/tds_charge', $body, $auth);
            $response->private_response['response_2'] = $result;
            $response->order_number = (string) ($result['reference_no'] ?? '');

            if (($result['is_succeed'] ?? false) === true) {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarılı';
                $response->transaction_id = (string) ($result['xact_id'] ?? '');
            } elseif (! empty($result['paynet_error_message'] ?? '')) {
                $response->message = (string) $result['paynet_error_message'];
            }

            return $response;
        }

        if (! empty($responseArray['message'] ?? '')) {
            $response->message = (string) $responseArray['message'];
        } elseif (! empty($responseArray['paynet_error_message'] ?? '')) {
            $response->message = (string) $responseArray['paynet_error_message'];
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error, message: 'İptal işlemi sırasında bir hata oluştu');

        $result = $this->requestJson(
            $this->getBaseUrl($auth) . '/v1/transaction/reversed_request',
            ['xact_id' => $request->transaction_id],
            $auth,
        );

        $response->private_response = $result;
        if (in_array((int) ($result['code'] ?? -1), [0, 100], true)) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İptal işlemi başarılı';
        } elseif (! empty($result['message'] ?? '')) {
            $response->message = (string) $result['message'];
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error, message: 'İade işlemi sırasında bir hata oluştu');

        $result = $this->requestJson(
            $this->getBaseUrl($auth) . '/v1/transaction/reversed_request',
            [
                'xact_id' => $request->transaction_id,
                'amount' => StringHelper::toKurus($request->refund_amount),
            ],
            $auth,
        );

        $response->private_response = $result;
        if (in_array((int) ($result['code'] ?? -1), [0, 100], true)) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İade işlemi başarılı';
            $response->refund_amount = $request->refund_amount;
        } elseif (! empty($result['message'] ?? '')) {
            $response->message = (string) $result['message'];
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse
    {
        $response = new AllInstallmentQueryResponse(confirm: false, installment_list: []);

        foreach (self::REPRESENTATIVE_BINS as $programName => $bin) {
            $program = CreditCardProgram::tryFromName($programName);
            if ($program === null) {
                continue;
            }

            $binResponse = $this->binInstallmentQuery(
                new BINInstallmentQueryRequest(
                    BIN: $bin,
                    amount: $request->amount,
                    currency: $request->currency,
                ),
                $auth,
            );

            if (! $binResponse->confirm || empty($binResponse->installment_list)) {
                continue;
            }

            foreach ($binResponse->installment_list as $installment) {
                if (! $installment instanceof Installment) {
                    continue;
                }

                $response->installment_list[] = new AllInstallment(
                    bank_code: '9977',
                    cardProgram: $program,
                    count: $installment->count,
                    customerCostCommissionRate: $installment->customerCostCommissionRate,
                );
            }
        }

        $response->confirm = ! empty($response->installment_list);

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false, installment_list: []);

        $result = $this->requestJson(
            $this->getBaseUrl($auth) . '/v1/ratio/Get',
            [
                'bin' => $request->BIN,
                'amount' => StringHelper::toKurus($request->amount),
                'addcomission_to_amount' => true,
            ],
            $auth,
        );

        $response->private_response = $result;
        $ratios = $result['data'][0]['ratio'] ?? [];
        if ((int) ($result['code'] ?? -1) === 0 && is_array($ratios)) {
            foreach ($ratios as $ratio) {
                $installmentCount = (int) ($ratio['instalment'] ?? 0);
                if ($installmentCount <= 1) {
                    continue;
                }

                $totalAmount = (float) ($ratio['total_amount'] ?? 0);
                $commissionRate = $request->amount > 0 && $totalAmount > $request->amount
                    ? (float) (((100 * $totalAmount) / $request->amount) - 100)
                    : 0.0;

                $response->installment_list[] = new Installment(
                    count: $installmentCount,
                    customerCostCommissionRate: $commissionRate,
                );
            }
        }

        $response->confirm = ! empty($response->installment_list);

        return $response;
    }

    protected function requestJson(string $url, array $body, MerchantAuth $auth): array
    {
        $headers = ['Authorization' => 'Basic ' . $auth->merchant_password];
        $response = $this->httpPostJson($url, $body, $headers);

        return json_decode($response, true) ?? [];
    }

    private function sale3D(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(
            status: SaleResponseStatus::Error,
            message: 'Bilinmeyen bir hata oluştu',
            order_number: $request->order_number,
        );

        $body = [
            'amount' => $this->formatSaleAmount($request->sale_info->amount),
            'reference_no' => $request->order_number,
            'return_url' => $request->payment_3d?->return_url ?? '',
            'domain' => $this->resolveHost($request->payment_3d?->return_url),
            'card_holder' => $request->sale_info->card_name_surname,
            'pan' => $request->sale_info->card_number,
            'month' => $request->sale_info->card_expiry_month,
            'year' => $request->sale_info->card_expiry_year,
            'cvc' => $request->sale_info->card_cvv,
            'card_holder_phone' => $request->invoice_info?->phone_number ?? '',
            'card_holder_mail' => $request->invoice_info?->email_address ?? '',
            'instalment' => max(1, $request->sale_info->installment),
            'add_commission' => max(1, $request->sale_info->installment) > 1,
            'transaction_type' => 1,
        ];

        $result = $this->requestJson($this->getBaseUrl($auth) . '/v2/transaction/tds_initial', $body, $auth);
        $response->private_response = $result;

        if (in_array((int) ($result['code'] ?? -1), [0, 100], true)) {
            $response->status = SaleResponseStatus::RedirectHTML;
            $response->message = (string) ($result['html_content'] ?? '');
        } elseif (! empty($result['message'] ?? '')) {
            $response->message = (string) $result['message'];
        }

        return $response;
    }

    private function getBaseUrl(MerchantAuth $auth): string
    {
        return $auth->test_platform ? $this->urlTest : $this->urlLive;
    }

    private function resolveHost(?string $returnUrl): string
    {
        if (empty($returnUrl)) {
            return 'cp.vpos.local';
        }

        try {
            return parse_url($returnUrl, PHP_URL_HOST) ?: 'cp.vpos.local';
        } catch (\Throwable $e) {
            return 'cp.vpos.local';
        }
    }

    private function formatSaleAmount(float $amount): string
    {
        return str_replace('.', '', number_format($amount, 2, ',', '.'));
    }
}
