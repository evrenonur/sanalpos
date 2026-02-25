<?php

use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Support\StringHelper;

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

// --- Yeni eklenen helper testleri ---

it('toKurus tutarı kuruş formatına çevirir', function (float $input, string $expected) {
    expect(StringHelper::toKurus($input))->toBe($expected);
})->with([
    '100.50 → 10050' => [100.50, '10050'],
    '100.00 → 10000' => [100.00, '10000'],
    '0.01 → 001' => [0.01, '001'],
    '9.99 → 999' => [9.99, '999'],
    '1234.56 → 123456' => [1234.56, '123456'],
]);

it('sha1Base64 doğru hash üretir', function () {
    $data = 'test_data';
    $expected = base64_encode(hash('sha1', $data, true));

    expect(StringHelper::sha1Base64($data))->toBe($expected);
});

it('sha1Base64 boş string için de çalışır', function () {
    $expected = base64_encode(hash('sha1', '', true));

    expect(StringHelper::sha1Base64(''))->toBe($expected);
});

it('parseSemicolonResponse yanıtı doğru parse eder', function () {
    $response = 'ProcReturnCode=00;;AuthCode=123456;;OrderId=ORDER-1;;ErrorMessage=';
    $dic = StringHelper::parseSemicolonResponse($response);

    expect($dic['ProcReturnCode'])->toBe('00')
        ->and($dic['AuthCode'])->toBe('123456')
        ->and($dic['OrderId'])->toBe('ORDER-1')
        ->and($dic['ErrorMessage'])->toBe('');
});

it('parseSemicolonResponse boş string için boş array döner', function () {
    expect(StringHelper::parseSemicolonResponse(''))->toBe([]);
});

it('parseSemicolonResponse tek elemanlı yanıtı parse eder', function () {
    $response = 'Key=Value';
    $dic = StringHelper::parseSemicolonResponse($response);

    expect($dic['Key'])->toBe('Value');
});

it('detectCardType Visa kartlarını tanır', function () {
    expect(StringHelper::detectCardType('4111111111111111'))->toBe('Visa');
    expect(StringHelper::detectCardType('4222222222222'))->toBe('Visa');
});

it('detectCardType MasterCard kartlarını tanır', function () {
    expect(StringHelper::detectCardType('5111111111111111'))->toBe('MasterCard');
    expect(StringHelper::detectCardType('5500000000000004'))->toBe('MasterCard');
    expect(StringHelper::detectCardType('2221000000000000'))->toBe('MasterCard');
    expect(StringHelper::detectCardType('2720000000000000'))->toBe('MasterCard');
});

it('detectCardType AMEX kartlarını tanır', function () {
    expect(StringHelper::detectCardType('341111111111111'))->toBe('AmericanExpress');
    expect(StringHelper::detectCardType('371111111111111'))->toBe('AmericanExpress');
});

it('detectCardType Troy kartlarını tanır', function () {
    expect(StringHelper::detectCardType('6500000000000000'))->toBe('Troy');
    expect(StringHelper::detectCardType('9792000000000000'))->toBe('Troy');
});

it('detectCardType bilinmeyen kartlar için Unknown döner', function () {
    expect(StringHelper::detectCardType('1234567890123456'))->toBe('Unknown');
});

it('detectCardType boş string için Unknown döner', function () {
    expect(StringHelper::detectCardType(''))->toBe('Unknown');
});
