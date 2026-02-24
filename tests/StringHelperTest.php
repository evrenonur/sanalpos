<?php

use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Helpers\StringHelper;

it('maxLength string kırpar', function () {
    expect(StringHelper::maxLength('abcdef', 3))->toBe('abc');
});

it('maxLength null için boş string döner', function () {
    expect(StringHelper::maxLength(null, 5))->toBe('');
});

it('clearString özel karakterleri kaldırır', function () {
    expect(StringHelper::clearString('<test>'))->toBe('test');
});

it('clearNumber sadece rakamları bırakır', function () {
    expect(StringHelper::clearNumber('12-34 56'))->toBe('123456');
});

it('clearNumber null için boş string döner', function () {
    expect(StringHelper::clearNumber(null))->toBe('');
});

it('geçerli kart numarasını doğrular', function () {
    expect(StringHelper::isCardNumberValid('4111111111111111'))->toBeTrue();
});

it('geçersiz kart numarasını reddeder', function () {
    expect(StringHelper::isCardNumberValid('1234567890123456'))->toBeFalse();
});

it('kısa kart numarasını reddeder', function () {
    expect(StringHelper::isCardNumberValid('123'))->toBeFalse();
});

it('formatAmount doğru formatlama yapar', function (float $input, string $expected) {
    expect(StringHelper::formatAmount($input))->toBe($expected);
})->with([
    '100.50' => [100.5, '100.50'],
    '100.00' => [100, '100.00'],
    '0.01' => [0.01, '0.01'],
]);

it('isNullOrEmpty doğru çalışır', function (mixed $input, bool $expected) {
    expect(StringHelper::isNullOrEmpty($input))->toBe($expected);
})->with([
    'null' => [null, true],
    'boş string' => ['', true],
    'boşluklu string' => ['   ', true],
    'dolu string' => ['test', false],
]);

it('getCurrencyCode doğru ISO kodu döner', function (Currency $currency, string $expected) {
    expect(StringHelper::getCurrencyCode($currency))->toBe($expected);
})->with([
    'TRY' => [Currency::TRY, '949'],
    'USD' => [Currency::USD, '840'],
    'EUR' => [Currency::EUR, '978'],
]);

it('getCurrencyName enum adını döner', function (Currency $currency, string $expected) {
    expect(StringHelper::getCurrencyName($currency))->toBe($expected);
})->with([
    'TRY' => [Currency::TRY, 'TRY'],
    'USD' => [Currency::USD, 'USD'],
    'EUR' => [Currency::EUR, 'EUR'],
]);

it('toXml geçerli XML üretir', function () {
    $data = ['Name' => 'Test', 'Value' => '123'];
    $xml = StringHelper::toXml($data, 'Root', 'UTF-8');

    expect($xml)
        ->toContain('<Root>')
        ->toContain('<Name>Test</Name>')
        ->toContain('<Value>123</Value>');
});

it('xmlToDictionary XML veriyi array olarak döner', function () {
    $xml = '<?xml version="1.0"?><Root><Name>Test</Name><Value>123</Value></Root>';
    $result = StringHelper::xmlToDictionary($xml);

    expect($result['Name'])->toBe('Test')
        ->and($result['Value'])->toBe('123');
});

it('toHtmlForm auto-submit form üretir', function () {
    $params = ['key1' => 'value1', 'key2' => 'value2'];
    $html = StringHelper::toHtmlForm($params, 'https://example.com/pay');

    expect($html)
        ->toContain('action="https://example.com/pay"')
        ->toContain('name="key1"')
        ->toContain('value="value1"')
        ->toContain('submit()');
});

it('getFormParams hidden input değerlerini çıkarır', function () {
    $html = '<form><input type="hidden" name="field1" value="val1" /><input type="hidden" name="field2" value="val2" /></form>';
    $params = StringHelper::getFormParams($html);

    expect($params['field1'])->toBe('val1')
        ->and($params['field2'])->toBe('val2');
});
