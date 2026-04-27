<?php

use EvrenOnur\SanalPos\DTOs\CustomerInfo;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Payment3DConfig;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\SaleInfo;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\InstallmentCommissionPolicy;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\AbstractCCPaymentGateway;
use EvrenOnur\SanalPos\Gateways\Providers\IyzicoGateway;
use EvrenOnur\SanalPos\Gateways\Providers\PaynetGateway;

function createCommitPortAuth(InstallmentCommissionPolicy $policy = InstallmentCommissionPolicy::Default): MerchantAuth
{
    return new MerchantAuth(
        bank_code: '9999',
        merchant_id: 'merchant-id',
        merchant_user: 'merchant-user',
        merchant_password: 'merchant-password',
        merchant_storekey: 'merchant-storekey',
        test_platform: true,
        installment_commission_policy: $policy,
    );
}

function createCommitPortSaleRequest(bool $is3D = false, int $installment = 3): SaleRequest
{
    return new SaleRequest(
        order_number: 'ORDER-1',
        customer_ip_address: '127.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test User',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            currency: Currency::TRY,
            amount: 100,
            installment: $installment,
        ),
        invoice_info: new CustomerInfo(
            name: 'Test',
            surname: 'User',
            email_address: 'test@example.com',
            phone_number: '5551112233',
        ),
        shipping_info: new CustomerInfo(name: 'Test', surname: 'User'),
        payment_3d: $is3D ? new Payment3DConfig(confirm: true, return_url: 'https://merchant.example/callback') : null,
    );
}

it('MerchantAuth komisyon politikasını fromArray ile parse eder', function () {
    $auth = MerchantAuth::fromArray([
        'bank_code' => '9977',
        'merchant_id' => 'merchant-id',
        'merchant_user' => 'merchant-user',
        'merchant_password' => 'merchant-password',
        'merchant_storekey' => 'merchant-storekey',
        'installment_commission_policy' => InstallmentCommissionPolicy::ChargeToCustomer->value,
    ]);

    expect($auth->installment_commission_policy)->toBe(InstallmentCommissionPolicy::ChargeToCustomer);
});

it('CCPayment satış isteğine komisyon politikası ekler', function () {
    $gateway = new class extends AbstractCCPaymentGateway
    {
        public array $requests = [];

        protected function getTestBaseUrl(): string
        {
            return 'https://ccpayment.test';
        }

        protected function getLiveBaseUrl(): string
        {
            return 'https://ccpayment.live';
        }

        protected function getToken(string $baseUrl, MerchantAuth $auth): string
        {
            return 'token';
        }

        protected function jsonRequest(string $url, array $body, ?string $token = null): string
        {
            $this->requests[] = compact('url', 'body', 'token');

            return json_encode([
                'status_code' => '100',
                'data' => ['payment_status' => '1', 'auth_code' => 'AUTH-1'],
            ]);
        }
    };

    $response = $gateway->sale(
        createCommitPortSaleRequest(),
        createCommitPortAuth(InstallmentCommissionPolicy::ChargeToCustomer),
    );

    expect($response->status)->toBe(SaleResponseStatus::Success)
        ->and($gateway->requests[0]['body']['is_comission_from_user'])->toBe('1');
});

it('CCPayment 3D isteğinde merchant completion modeli kullanır', function () {
    $gateway = new class extends AbstractCCPaymentGateway
    {
        public array $requests = [];

        protected function getTestBaseUrl(): string
        {
            return 'https://ccpayment.test';
        }

        protected function getLiveBaseUrl(): string
        {
            return 'https://ccpayment.live';
        }

        protected function getToken(string $baseUrl, MerchantAuth $auth): string
        {
            return 'token';
        }

        protected function jsonRequest(string $url, array $body, ?string $token = null): string
        {
            $this->requests[] = compact('url', 'body', 'token');

            return '<form id="3d"></form>';
        }
    };

    $response = $gateway->sale(
        createCommitPortSaleRequest(true),
        createCommitPortAuth(InstallmentCommissionPolicy::AbsorbByMerchant),
    );

    expect($response->status)->toBe(SaleResponseStatus::RedirectHTML)
        ->and($gateway->requests[0]['body']['payment_completed_by'])->toBe('merchant')
        ->and($gateway->requests[0]['body']['is_comission_from_user'])->toBe('2');
});

it('CCPayment 3D response complete çağrısı yapar ve iki aşamalı private response döner', function () {
    $gateway = new class extends AbstractCCPaymentGateway
    {
        public array $requests = [];

        protected function getTestBaseUrl(): string
        {
            return 'https://ccpayment.test';
        }

        protected function getLiveBaseUrl(): string
        {
            return 'https://ccpayment.live';
        }

        protected function getToken(string $baseUrl, MerchantAuth $auth): string
        {
            return 'token';
        }

        protected function validateHashKey(string $hashKey, string $appSecret): array|false
        {
            return ['100.00', '3', '949', 'merchant-storekey', 'ORDER-1'];
        }

        protected function jsonRequest(string $url, array $body, ?string $token = null): string
        {
            $this->requests[] = compact('url', 'body', 'token');

            return json_encode([
                'status_code' => '100',
                'data' => ['auth_code' => 'AUTH-3D'],
            ]);
        }
    };

    $response = $gateway->sale3DResponse(
        new Sale3DResponse(responseArray: [
            'invoice_id' => 'ORDER-1',
            'order_id' => 'ORDER-ID-1',
            'md_status' => '1',
            'hash_key' => 'hash',
        ]),
        createCommitPortAuth(),
    );

    expect($response->status)->toBe(SaleResponseStatus::Success)
        ->and($response->transaction_id)->toBe('AUTH-3D')
        ->and($response->private_response)->toHaveKeys(['response_1', 'response_2'])
        ->and($gateway->requests[0]['url'])->toContain('/payment/complete');
});

it('CCPayment allInstallmentQuery merchant komisyonu üstlenince oranı sıfırlar', function () {
    $gateway = new class extends AbstractCCPaymentGateway
    {
        protected function getTestBaseUrl(): string
        {
            return 'https://ccpayment.test';
        }

        protected function getLiveBaseUrl(): string
        {
            return 'https://ccpayment.live';
        }

        protected function getToken(string $baseUrl, MerchantAuth $auth): string
        {
            return 'token';
        }

        protected function jsonRequest(string $url, array $body, ?string $token = null): string
        {
            return json_encode([
                'data' => [[
                    'card_program' => 'Bonus',
                    'installments_number' => 3,
                    'user_commission_percentage' => 4.25,
                ]],
            ]);
        }
    };

    $response = $gateway->allInstallmentQuery(
        new AllInstallmentQueryRequest(amount: 100, currency: Currency::TRY),
        createCommitPortAuth(InstallmentCommissionPolicy::AbsorbByMerchant),
    );

    expect($response->confirm)->toBeTrue()
        ->and($response->installment_list[0]->installment_list[0]['rate'])->toBe(0.0);
});

it('Iyzico 3D response responseArray boşsa anlamlı hata döner', function () {
    $response = (new IyzicoGateway)->sale3DResponse(
        new Sale3DResponse(responseArray: null),
        createCommitPortAuth(),
    );

    expect($response->status)->toBe(SaleResponseStatus::Error)
        ->and($response->message)->toBe('responseArray boş olamaz');
});

it('Iyzico 3D hata akışında response_1 detayını korur', function () {
    $response = (new IyzicoGateway)->sale3DResponse(
        new Sale3DResponse(responseArray: [
            'conversationId' => 'ORDER-1',
            'paymentId' => 'PAY-1',
            'status' => 'failure',
            'mdStatus' => 7,
        ]),
        createCommitPortAuth(),
    );

    expect($response->status)->toBe(SaleResponseStatus::Error)
        ->and($response->message)->toBe('Sistem hatası')
        ->and($response->private_response)->toHaveKey('response_1');
});

it('Paynet satış ve 3D charge akışını işler', function () {
    $gateway = new class extends PaynetGateway
    {
        public array $requests = [];

        protected function requestJson(string $url, array $body, MerchantAuth $auth): array
        {
            $this->requests[] = compact('url', 'body');

            return match (true) {
                str_contains($url, '/v2/transaction/payment') => [
                    'is_succeed' => true,
                    'xact_id' => 'PAYNET-TXN',
                ],
                str_contains($url, '/v2/transaction/tds_charge') => [
                    'is_succeed' => true,
                    'xact_id' => 'PAYNET-3D-TXN',
                    'reference_no' => 'ORDER-1',
                ],
                default => [],
            };
        }
    };

    $saleResponse = $gateway->sale(createCommitPortSaleRequest(), createCommitPortAuth());
    $threeDResponse = $gateway->sale3DResponse(
        new Sale3DResponse(responseArray: [
            'session_id' => 'SESSION',
            'token_id' => 'TOKEN',
        ]),
        createCommitPortAuth(),
    );

    expect($saleResponse->status)->toBe(SaleResponseStatus::Success)
        ->and($saleResponse->transaction_id)->toBe('PAYNET-TXN')
        ->and($threeDResponse->status)->toBe(SaleResponseStatus::Success)
        ->and($threeDResponse->private_response)->toHaveKeys(['response_1', 'response_2']);
});

it('Paynet bin ve tüm taksit sorgusunu temsilci BIN setiyle üretir', function () {
    $gateway = new class extends PaynetGateway
    {
        protected function requestJson(string $url, array $body, MerchantAuth $auth): array
        {
            return [
                'code' => 0,
                'data' => [[
                    'ratio' => [
                        ['instalment' => 2, 'total_amount' => 102.5],
                        ['instalment' => 3, 'total_amount' => 105.0],
                    ],
                ]],
            ];
        }
    };

    $binResponse = $gateway->binInstallmentQuery(
        new BINInstallmentQueryRequest(BIN: '413252', amount: 100, currency: Currency::TRY),
        createCommitPortAuth(),
    );
    $allResponse = $gateway->allInstallmentQuery(
        new AllInstallmentQueryRequest(amount: 100, currency: Currency::TRY),
        createCommitPortAuth(),
    );

    expect($binResponse->confirm)->toBeTrue()
        ->and($binResponse->installment_list)->toHaveCount(2)
        ->and($allResponse->confirm)->toBeTrue()
        ->and($allResponse->installment_list)->not->toBeEmpty();
});
