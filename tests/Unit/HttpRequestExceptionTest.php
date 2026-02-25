<?php

use EvrenOnur\SanalPos\Exceptions\HttpRequestException;

it('HttpRequestException url property taşır', function () {
    $ex = new HttpRequestException('Connection failed', 'https://example.com/api', 0);

    expect($ex->getMessage())->toBe('Connection failed');
    expect($ex->url)->toBe('https://example.com/api');
});

it('HttpRequestException önceki exception\'ı saklar', function () {
    $prev = new \RuntimeException('Original error');
    $ex = new HttpRequestException('Wrapped error', '', 0, $prev);

    expect($ex->getPrevious())->toBe($prev);
});
