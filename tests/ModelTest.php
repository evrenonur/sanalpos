<?php

use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Models\AllInstallment;
use EvrenOnur\SanalPos\Models\Bank;
use EvrenOnur\SanalPos\Models\CancelRequest;
use EvrenOnur\SanalPos\Models\CustomerInfo;
use EvrenOnur\SanalPos\Models\Payment3D;
use EvrenOnur\SanalPos\Models\RefundRequest;
use EvrenOnur\SanalPos\Models\SaleInfo;
use EvrenOnur\SanalPos\Models\SaleRequest;
use EvrenOnur\SanalPos\Models\SaleResponse;
use EvrenOnur\SanalPos\Models\VirtualPOSAuth;

it('SaleRequest doğru şekilde oluşturulur', function () {
    $request = new SaleRequest(
        orderNumber: 'TEST-001',
        saleInfo: new SaleInfo(
            cardNumber: '4111111111111111',
            cardExpiryDateMonth: 12,
            cardExpiryDateYear: 2030,
            cardCVV: '123',
            amount: 100.50,
            currency: Currency::TRY,
            installment: 1,
        ),
        customerIPAddress: '127.0.0.1',
    );

    expect($request->orderNumber)->toBe('TEST-001')
        ->and($request->saleInfo->cardNumber)->toBe('4111111111111111')
        ->and($request->saleInfo->amount)->toBe(100.50)
        ->and($request->saleInfo->currency)->toBe(Currency::TRY)
        ->and($request->saleInfo->installment)->toBe(1)
        ->and($request->customerIPAddress)->toBe('127.0.0.1');
});

it('SaleResponse varsayılan değerleri doğru', function () {
    $response = new SaleResponse(orderNumber: 'TEST-002');

    expect($response->orderNumber)->toBe('TEST-002')
        ->and($response->statu)->toBe(SaleResponseStatu::Error);
});

it('VirtualPOSAuth doğru şekilde oluşturulur', function () {
    $auth = new VirtualPOSAuth(
        bankCode: '0062',
        merchantID: 'MID123',
        merchantUser: 'USER',
        merchantPassword: 'PASS',
        merchantStorekey: 'STOREKEY',
        testPlatform: true,
    );

    expect($auth->bankCode)->toBe('0062')
        ->and($auth->merchantID)->toBe('MID123')
        ->and($auth->testPlatform)->toBeTrue();
});

it('CustomerInfo doğru şekilde oluşturulur', function () {
    $info = new CustomerInfo(
        name: 'Cem',
        surname: 'Pehlivan',
        emailAddress: 'cem@test.com',
        phoneNumber: '5551234567',
        addressDesc: 'Test Adres',
        cityName: 'İstanbul',
        country: Country::TUR,
    );

    expect($info->name)->toBe('Cem')
        ->and($info->surname)->toBe('Pehlivan')
        ->and($info->country)->toBe(Country::TUR);
});

it('CancelRequest doğru şekilde oluşturulur', function () {
    $request = new CancelRequest(
        orderNumber: 'ORD-001',
        transactionId: 'TXN-001',
    );

    expect($request->orderNumber)->toBe('ORD-001')
        ->and($request->transactionId)->toBe('TXN-001');
});

it('RefundRequest doğru şekilde oluşturulur', function () {
    $request = new RefundRequest(
        orderNumber: 'ORD-002',
        refundAmount: 50.00,
        currency: Currency::TRY,
    );

    expect($request->orderNumber)->toBe('ORD-002')
        ->and($request->refundAmount)->toBe(50.00)
        ->and($request->currency)->toBe(Currency::TRY);
});

it('Bank modeli doğru şekilde oluşturulur', function () {
    $bank = new Bank(
        bankCode: '0062',
        bankName: 'Garanti BBVA',
        collectiveVPOS: false,
        installmentAPI: false,
    );

    expect($bank->bankCode)->toBe('0062')
        ->and($bank->bankName)->toBe('Garanti BBVA')
        ->and($bank->collectiveVPOS)->toBeFalse();
});

it('AllInstallment installmentList property içerir', function () {
    $installment = new AllInstallment(
        bankCode: '0062',
        installmentList: [
            ['installment' => 3, 'rate' => 1.5],
            ['installment' => 6, 'rate' => 2.3],
        ],
    );

    expect($installment->bankCode)->toBe('0062')
        ->and($installment->installmentList)->toHaveCount(2)
        ->and($installment->installmentList[0]['installment'])->toBe(3);
});

it('Payment3D doğru şekilde oluşturulur', function () {
    $p3d = new Payment3D(confirm: true, returnURL: 'https://example.com/callback');

    expect($p3d->confirm)->toBeTrue()
        ->and($p3d->returnURL)->toBe('https://example.com/callback');
});
