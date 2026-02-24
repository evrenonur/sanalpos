<?php

use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\CreditCardBrand;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;

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

it('SaleResponseStatus Success ve Error mevcut', function () {
    expect(SaleResponseStatus::Success)->not->toBeNull()
        ->and(SaleResponseStatus::Error)->not->toBeNull();
});

it('ResponseStatus Success ve Error mevcut', function () {
    expect(ResponseStatus::Success)->not->toBeNull()
        ->and(ResponseStatus::Error)->not->toBeNull();
});
