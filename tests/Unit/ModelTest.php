<?php

use EvrenOnur\SanalPos\DTOs\AllInstallment;
use EvrenOnur\SanalPos\DTOs\Bank;
use EvrenOnur\SanalPos\DTOs\CustomerInfo;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Payment3DConfig;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\DTOs\SaleInfo;
use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;

it('SaleRequest doğru şekilde oluşturulur', function () {
    $request = new SaleRequest(
        order_number: 'TEST-001',
        sale_info: new SaleInfo(
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 100.50,
            currency: Currency::TRY,
            installment: 1,
        ),
        customer_ip_address: '127.0.0.1',
    );

    expect($request->order_number)->toBe('TEST-001')
        ->and($request->sale_info->card_number)->toBe('4111111111111111')
        ->and($request->sale_info->amount)->toBe(100.50)
        ->and($request->sale_info->currency)->toBe(Currency::TRY)
        ->and($request->sale_info->installment)->toBe(1)
        ->and($request->customer_ip_address)->toBe('127.0.0.1');
});

it('SaleResponse varsayılan değerleri doğru', function () {
    $response = new SaleResponse(order_number: 'TEST-002');

    expect($response->order_number)->toBe('TEST-002')
        ->and($response->status)->toBe(SaleResponseStatus::Error);
});

it('MerchantAuth doğru şekilde oluşturulur', function () {
    $auth = new MerchantAuth(
        bank_code: '0062',
        merchant_id: 'MID123',
        merchant_user: 'USER',
        merchant_password: 'PASS',
        merchant_storekey: 'STOREKEY',
        test_platform: true,
    );

    expect($auth->bank_code)->toBe('0062')
        ->and($auth->merchant_id)->toBe('MID123')
        ->and($auth->test_platform)->toBeTrue();
});

it('CustomerInfo doğru şekilde oluşturulur', function () {
    $info = new CustomerInfo(
        name: 'Cem',
        surname: 'Pehlivan',
        email_address: 'cem@test.com',
        phone_number: '5551234567',
        address_description: 'Test Adres',
        city_name: 'İstanbul',
        country: Country::TUR,
    );

    expect($info->name)->toBe('Cem')
        ->and($info->surname)->toBe('Pehlivan')
        ->and($info->country)->toBe(Country::TUR);
});

it('CancelRequest doğru şekilde oluşturulur', function () {
    $request = new CancelRequest(
        order_number: 'ORD-001',
        transaction_id: 'TXN-001',
    );

    expect($request->order_number)->toBe('ORD-001')
        ->and($request->transaction_id)->toBe('TXN-001');
});

it('RefundRequest doğru şekilde oluşturulur', function () {
    $request = new RefundRequest(
        order_number: 'ORD-002',
        refund_amount: 50.00,
        currency: Currency::TRY,
    );

    expect($request->order_number)->toBe('ORD-002')
        ->and($request->refund_amount)->toBe(50.00)
        ->and($request->currency)->toBe(Currency::TRY);
});

it('Bank modeli doğru şekilde oluşturulur', function () {
    $bank = new Bank(
        bank_code: '0062',
        bank_name: 'Garanti BBVA',
        collective_vpos: false,
        installment_api: false,
    );

    expect($bank->bank_code)->toBe('0062')
        ->and($bank->bank_name)->toBe('Garanti BBVA')
        ->and($bank->collective_vpos)->toBeFalse();
});

it('AllInstallment installment_list property içerir', function () {
    $installment = new AllInstallment(
        bank_code: '0062',
        installment_list: [
            ['installment' => 3, 'rate' => 1.5],
            ['installment' => 6, 'rate' => 2.3],
        ],
    );

    expect($installment->bank_code)->toBe('0062')
        ->and($installment->installment_list)->toHaveCount(2)
        ->and($installment->installment_list[0]['installment'])->toBe(3);
});

it('Payment3DConfig doğru şekilde oluşturulur', function () {
    $p3d = new Payment3DConfig(confirm: true, return_url: 'https://example.com/callback');

    expect($p3d->confirm)->toBeTrue()
        ->and($p3d->return_url)->toBe('https://example.com/callback');
});
