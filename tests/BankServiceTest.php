<?php

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Models\Bank;
use EvrenOnur\SanalPos\Services\BankService;

it('tüm banka listesi boş olmaz', function () {
    $banks = BankService::allBanks();

    expect($banks)->not->toBeEmpty()
        ->and(count($banks))->toBeGreaterThan(30);
});

it('tüm bankalar Bank nesnesidir', function () {
    $banks = BankService::allBanks();

    foreach ($banks as $bank) {
        expect($bank)
            ->toBeInstanceOf(Bank::class)
            ->and($bank->bankCode)->not->toBeEmpty()
            ->and($bank->bankName)->not->toBeEmpty();
    }
});

it('gateway sınıfları mevcut', function (string $code) {
    $class = BankService::getGatewayClass($code);

    expect($class)->not->toBeNull()
        ->and(class_exists($class))->toBeTrue();
})->with([
    'Akbank' => [BankService::AKBANK],
    'Akbank Nestpay' => [BankService::AKBANK_NESTPAY],
    'Denizbank' => [BankService::DENIZBANK],
    'Garanti BBVA' => [BankService::GARANTI_BBVA],
    'Halkbank' => [BankService::HALKBANK],
    'ING Bank' => [BankService::ING_BANK],
    'İş Bankası' => [BankService::IS_BANKASI],
    'Kuveyt Türk' => [BankService::KUVEYT_TURK],
    'QNB Finansbank' => [BankService::QNB_FINANSBANK],
    'Vakıfbank' => [BankService::VAKIFBANK],
    'Yapı Kredi' => [BankService::YAPI_KREDI],
    'Vakıf Katılım' => [BankService::VAKIF_KATILIM],
    'Ziraat Bankası' => [BankService::ZIRAAT_BANKASI],
    'Şekerbank' => [BankService::SEKERBANK],
    'Ahlpay' => [BankService::AHLPAY],
    'Moka' => [BankService::MOKA],
    'PayNKolay' => [BankService::PAYNKOLAY],
    'ParamPos' => [BankService::PARAMPOS],
    'Iyzico' => [BankService::IYZICO],
    'Sipay' => [BankService::SIPAY],
    'QNBpay' => [BankService::QNBPAY],
    'Payten' => [BankService::PAYTEN],
    'Paratika' => [BankService::PARATIKA],
    'Tami' => [BankService::TAMI],
]);

it('gateway oluşturma VirtualPOSServiceInterface döner', function (string $code) {
    $gateway = BankService::createGateway($code);

    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
})->with([
    'Akbank' => [BankService::AKBANK],
    'Garanti BBVA' => [BankService::GARANTI_BBVA],
    'Denizbank' => [BankService::DENIZBANK],
    'Kuveyt Türk' => [BankService::KUVEYT_TURK],
    'QNB Finansbank' => [BankService::QNB_FINANSBANK],
    'Vakıfbank' => [BankService::VAKIFBANK],
    'Yapı Kredi' => [BankService::YAPI_KREDI],
    'Vakıf Katılım' => [BankService::VAKIF_KATILIM],
    'Akbank Nestpay' => [BankService::AKBANK_NESTPAY],
    'Halkbank' => [BankService::HALKBANK],
    'İş Bankası' => [BankService::IS_BANKASI],
    'ING Bank' => [BankService::ING_BANK],
    'Ziraat Bankası' => [BankService::ZIRAAT_BANKASI],
    'Şekerbank' => [BankService::SEKERBANK],
    'Ahlpay' => [BankService::AHLPAY],
    'Moka' => [BankService::MOKA],
    'PayNKolay' => [BankService::PAYNKOLAY],
    'ParamPos' => [BankService::PARAMPOS],
    'Iyzico' => [BankService::IYZICO],
    'Sipay' => [BankService::SIPAY],
    'QNBpay' => [BankService::QNBPAY],
    'Payten' => [BankService::PAYTEN],
    'Paratika' => [BankService::PARATIKA],
    'Tami' => [BankService::TAMI],
]);

it('geçersiz banka kodu için exception fırlatır', function () {
    BankService::createGateway('9999999');
})->throws(InvalidArgumentException::class);

it('getBank doğru banka bilgisini döner', function () {
    $bank = BankService::getBank(BankService::GARANTI_BBVA);

    expect($bank)->not->toBeNull()
        ->and($bank->bankCode)->toBe('0062')
        ->and($bank->bankName)->toContain('Garanti');
});

it('bilinmeyen banka kodu için null döner', function () {
    expect(BankService::getBank('0000'))->toBeNull();
});
