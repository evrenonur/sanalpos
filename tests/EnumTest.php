<?php

use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\CreditCardBrand;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;

it('Currency::TRY değeri 949 olmalı', function () {
    expect(Currency::TRY->value)->toBe(949);
});

it('Currency::USD değeri 840 olmalı', function () {
    expect(Currency::USD->value)->toBe(840);
});

it('Currency::EUR değeri 978 olmalı', function () {
    expect(Currency::EUR->value)->toBe(978);
});

it('Country::TUR mevcut olmalı', function () {
    expect(Country::TUR)->not->toBeNull();
});

it('CreditCardBrand enum değerleri mevcut', function () {
    expect(CreditCardBrand::Visa)->not->toBeNull()
        ->and(CreditCardBrand::MasterCard)->not->toBeNull()
        ->and(CreditCardBrand::Amex)->not->toBeNull();
});

it('CreditCardProgram::tryFromName doğru eşleşme yapar', function (string $name, CreditCardProgram $expected) {
    expect(CreditCardProgram::tryFromName($name))->toBe($expected);
})->with([
    'Axess' => ['Axess', CreditCardProgram::Axess],
    'bonus (lowercase)' => ['bonus', CreditCardProgram::Bonus],
    'WORLD (uppercase)' => ['WORLD', CreditCardProgram::World],
]);

it('CreditCardProgram::tryFromName bilinmeyen isim için null döner', function () {
    expect(CreditCardProgram::tryFromName('NonExistent'))->toBeNull();
});

it('CreditCardProgram::Other mevcut ve değeri -2', function () {
    expect(CreditCardProgram::Other->value)->toBe(-2);
});

it('SaleResponseStatu Success ve Error mevcut', function () {
    expect(SaleResponseStatu::Success)->not->toBeNull()
        ->and(SaleResponseStatu::Error)->not->toBeNull();
});

it('ResponseStatu Success ve Error mevcut', function () {
    expect(ResponseStatu::Success)->not->toBeNull()
        ->and(ResponseStatu::Error)->not->toBeNull();
});
