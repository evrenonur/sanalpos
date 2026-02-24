<?php

/**
 * vpos.com.tr Dokümantasyon Testleri
 *
 * Bu testler https://www.vpos.com.tr/docs/test-ortami-bilgileri sayfasındaki
 * gerçek test ortamı bilgilerini kullanarak SanalPos kütüphanesinin kullanımını gösterir.
 *
 * NOT: Bu testler gateway'lerin doğru oluşturulduğunu, DTO'ların doğru yapılandırıldığını
 * ve validasyonların çalıştığını test eder. Gerçek banka API çağrıları yapılmaz.
 */

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\CustomerInfo;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Payment3DConfig;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\DTOs\SaleInfo;
use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\SanalPosClient;
use EvrenOnur\SanalPos\Services\BankService;
use EvrenOnur\SanalPos\Support\ValidationHelper;

// =====================================================
// Test Ortamı Bilgileri (vpos.com.tr/docs/test-ortami-bilgileri)
// =====================================================

/**
 * Garanti BBVA test ortamı bilgileri
 * Kart: 5289394722895016, 01/2025, 030
 */
function garantiAuth(): MerchantAuth
{
    return new MerchantAuth(
        bank_code: '0062',
        merchant_id: '7000679',
        merchant_user: '30691297',
        merchant_password: '123qweASD/',
        merchant_storekey: '12345678',
        test_platform: true,
    );
}

/**
 * İş Bankası test ortamı bilgileri
 * Kart: 4508034508034509, 12/2026, 000
 */
function isBankasiAuth(): MerchantAuth
{
    return new MerchantAuth(
        bank_code: '0064',
        merchant_id: '700655000200',
        merchant_user: 'ISBANKAPI',
        merchant_password: 'ISBANK07',
        merchant_storekey: 'TRPS0200',
        test_platform: true,
    );
}

/**
 * QNBpay test ortamı bilgileri
 * Kart: 4022780520669303, 01/2050, 988
 */
function qnbPayAuth(): MerchantAuth
{
    return new MerchantAuth(
        bank_code: '9990',
        merchant_id: '20158',
        merchant_user: '07fb70f9d8de575f32baa6518e38c5d6',
        merchant_password: '61d97b2cac247069495be4b16f8604db',
        merchant_storekey: '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK',
        test_platform: true,
    );
}

/**
 * Dokümantasyondaki standart müşteri bilgileri
 */
function testCustomerInfo(): CustomerInfo
{
    return new CustomerInfo(
        name: 'cem',
        surname: 'pehlivan',
        email_address: 'test@test.com',
        phone_number: '1111111111',
        tax_number: '1111111111',
        tax_office: 'maltepe',
        country: Country::TUR,
        city_name: 'istanbul',
        town_name: 'maltepe',
        address_description: 'adres',
        post_code: '34000',
    );
}

// =====================================================
// Test Ortamı Auth Yapılandırması
// =====================================================

it('Garanti BBVA test ortamı auth bilgileri doğru yapılandırılır', function () {
    $auth = garantiAuth();

    expect($auth->bank_code)->toBe('0062')
        ->and($auth->merchant_id)->toBe('7000679')
        ->and($auth->merchant_user)->toBe('30691297')
        ->and($auth->merchant_password)->toBe('123qweASD/')
        ->and($auth->merchant_storekey)->toBe('12345678')
        ->and($auth->test_platform)->toBeTrue();
});

it('İş Bankası test ortamı auth bilgileri doğru yapılandırılır', function () {
    $auth = isBankasiAuth();

    expect($auth->bank_code)->toBe('0064')
        ->and($auth->merchant_id)->toBe('700655000200')
        ->and($auth->merchant_user)->toBe('ISBANKAPI')
        ->and($auth->merchant_password)->toBe('ISBANK07')
        ->and($auth->merchant_storekey)->toBe('TRPS0200')
        ->and($auth->test_platform)->toBeTrue();
});

it('QNBpay test ortamı auth bilgileri doğru yapılandırılır', function () {
    $auth = qnbPayAuth();

    expect($auth->bank_code)->toBe('9990')
        ->and($auth->merchant_id)->toBe('20158')
        ->and($auth->merchant_user)->toBe('07fb70f9d8de575f32baa6518e38c5d6')
        ->and($auth->merchant_password)->toBe('61d97b2cac247069495be4b16f8604db')
        ->and($auth->merchant_storekey)->toBe('$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK')
        ->and($auth->test_platform)->toBeTrue();
});

// =====================================================
// Test Ortamı Gateway Oluşturma
// =====================================================

it('Garanti BBVA bankası için gateway oluşturulabilir', function () {
    $gateway = BankService::createGateway('0062');
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

it('İş Bankası için gateway oluşturulabilir', function () {
    $gateway = BankService::createGateway('0064');
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

it('QNBpay için gateway oluşturulabilir', function () {
    $gateway = BankService::createGateway('9990');
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

// =====================================================
// Satış İşlemi (vpos.com.tr/docs/satis)
// =====================================================

it('QNBpay ile standart satış isteği dokümantasyondaki gibi oluşturulur', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();
    $orderNumber = strtoupper(dechex(time()));

    $saleRequest = new SaleRequest(
        order_number: $orderNumber,
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    // Validasyon geçmeli
    $errors = $saleRequest->validate();
    expect($errors)->toBeEmpty();

    // Auth validasyonu
    $auth->validate();

    // İstek alanları doğrulanır
    expect($saleRequest->order_number)->toBe($orderNumber)
        ->and($saleRequest->customer_ip_address)->toBe('1.1.1.1')
        ->and($saleRequest->sale_info->card_number)->toBe('4022780520669303')
        ->and($saleRequest->sale_info->card_expiry_month)->toBe(1)
        ->and($saleRequest->sale_info->card_expiry_year)->toBe(2050)
        ->and($saleRequest->sale_info->card_cvv)->toBe('988')
        ->and($saleRequest->sale_info->amount)->toBe(10.00)
        ->and($saleRequest->sale_info->currency)->toBe(Currency::TRY)
        ->and($saleRequest->sale_info->installment)->toBe(1)
        ->and($saleRequest->payment_3d->confirm)->toBeFalse()
        ->and($saleRequest->invoice_info->name)->toBe('cem')
        ->and($saleRequest->shipping_info->surname)->toBe('pehlivan');
});

it('Garanti BBVA ile standart satış isteği oluşturulur', function () {
    $auth = garantiAuth();
    $customerInfo = testCustomerInfo();

    $saleRequest = new SaleRequest(
        order_number: 'GRN-' . strtoupper(dechex(time())),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test Kart',
            card_number: '5289394722895016',
            card_expiry_month: 1,
            card_expiry_year: 2025,
            card_cvv: '030',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    $errors = $saleRequest->validate();
    expect($errors)->toBeEmpty()
        ->and($saleRequest->sale_info->card_number)->toBe('5289394722895016')
        ->and($saleRequest->sale_info->card_cvv)->toBe('030');
});

it('İş Bankası ile standart satış isteği oluşturulur', function () {
    $auth = isBankasiAuth();
    $customerInfo = testCustomerInfo();

    $saleRequest = new SaleRequest(
        order_number: 'ISB-' . strtoupper(dechex(time())),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test Kart',
            card_number: '4508034508034509',
            card_expiry_month: 12,
            card_expiry_year: 2026,
            card_cvv: '000',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    $errors = $saleRequest->validate();
    expect($errors)->toBeEmpty()
        ->and($saleRequest->sale_info->card_number)->toBe('4508034508034509')
        ->and($saleRequest->sale_info->card_cvv)->toBe('000');
});

// =====================================================
// 3D Secure Satış İşlemi (vpos.com.tr/docs/satis-3d)
// =====================================================

it('QNBpay ile 3D satış isteği dokümantasyondaki gibi oluşturulur', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();
    $orderNumber = strtoupper(dechex(time()));

    $saleRequest = new SaleRequest(
        order_number: $orderNumber,
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(
            confirm: true,
            return_url: 'https://localhost/Payment/VirtualPOS3DResponse',
        ),
    );

    expect($saleRequest->payment_3d->confirm)->toBeTrue()
        ->and($saleRequest->payment_3d->return_url)->toBe('https://localhost/Payment/VirtualPOS3DResponse')
        ->and($saleRequest->validate())->toBeEmpty();
});

it('3D satış yanıtı Sale3DResponse ile işlenir (2. adım)', function () {
    // Bankadan dönen 3D yanıtı simülasyonu
    $bankResponse = [
        'mdStatus' => '1',
        'md' => 'SOME_MD_VALUE_FROM_BANK',
        'orderId' => 'QNB-3D-ORDER-001',
        'transId' => 'QNB-TRANS-001',
        'procReturnCode' => '00',
        'response' => 'Approved',
    ];

    $sale3DResponse = new Sale3DResponse(responseArray: $bankResponse);

    expect($sale3DResponse->responseArray)->toHaveKey('mdStatus')
        ->and($sale3DResponse->responseArray['mdStatus'])->toBe('1')
        ->and($sale3DResponse->responseArray['procReturnCode'])->toBe('00');
});

it('3D satış yanıtında mdStatus 1 değilse başarısız kabul edilir', function () {
    $failedResponse = new Sale3DResponse(responseArray: [
        'mdStatus' => '0',
        'md' => '',
        'orderId' => 'FAILED-ORDER',
        'ErrMsg' => '3D doğrulama başarısız',
    ]);

    expect($failedResponse->responseArray['mdStatus'])->not->toBe('1')
        ->and($failedResponse->responseArray['ErrMsg'])->toBe('3D doğrulama başarısız');
});

it('3D satış redirect durumları doğru enum değerlerine sahip', function () {
    // RedirectURL durumu
    $redirectUrlResponse = new SaleResponse(
        status: SaleResponseStatus::RedirectURL,
        message: 'https://3d-gate.bank.com/auth?sessionId=xyz123',
        order_number: '3D-URL-ORDER',
    );

    expect($redirectUrlResponse->status)->toBe(SaleResponseStatus::RedirectURL)
        ->and($redirectUrlResponse->status->value)->toBe(2)
        ->and($redirectUrlResponse->message)->toContain('https://');

    // RedirectHTML durumu
    $redirectHtmlResponse = new SaleResponse(
        status: SaleResponseStatus::RedirectHTML,
        message: '<html><body><form action="https://3d-gate.bank.com" method="POST"><input type="hidden" name="PaReq" value="..."/></form><script>document.forms[0].submit();</script></body></html>',
        order_number: '3D-HTML-ORDER',
    );

    expect($redirectHtmlResponse->status)->toBe(SaleResponseStatus::RedirectHTML)
        ->and($redirectHtmlResponse->status->value)->toBe(3)
        ->and($redirectHtmlResponse->message)->toContain('<form');
});

// =====================================================
// İptal İşlemi (vpos.com.tr/docs/iptal)
// =====================================================

it('QNBpay ile iptal isteği dokümantasyondaki gibi oluşturulur', function () {
    $auth = qnbPayAuth();

    $cancelRequest = new CancelRequest(
        customer_ip_address: '1.1.1.1',
        order_number: 'A1B2C3D4',
        transaction_id: '12345678',
        currency: Currency::TRY,
    );

    // Auth ve istek validasyonu
    $auth->validate();
    ValidationHelper::validateCancelRequest($cancelRequest);

    expect($cancelRequest->order_number)->toBe('A1B2C3D4')
        ->and($cancelRequest->transaction_id)->toBe('12345678')
        ->and($cancelRequest->currency)->toBe(Currency::TRY)
        ->and($cancelRequest->customer_ip_address)->toBe('1.1.1.1');
});

it('Garanti BBVA ile iptal isteği oluşturulur', function () {
    $auth = garantiAuth();

    $cancelRequest = new CancelRequest(
        customer_ip_address: '192.168.1.1',
        order_number: 'GRN-CANCEL-001',
        transaction_id: 'GRN-TXN-001',
        currency: Currency::TRY,
    );

    $auth->validate();
    ValidationHelper::validateCancelRequest($cancelRequest);

    expect($cancelRequest->order_number)->toBe('GRN-CANCEL-001');
});

// =====================================================
// İade İşlemi (vpos.com.tr/docs/iade)
// =====================================================

it('QNBpay ile iade isteği dokümantasyondaki gibi oluşturulur', function () {
    $auth = qnbPayAuth();

    $refundRequest = new RefundRequest(
        customer_ip_address: '1.1.1.1',
        order_number: 'A1B2C3D4',
        transaction_id: '12345678',
        refund_amount: 100.50,
        currency: Currency::TRY,
    );

    // Auth ve istek validasyonu
    $auth->validate();
    ValidationHelper::validateRefundRequest($refundRequest);

    expect($refundRequest->order_number)->toBe('A1B2C3D4')
        ->and($refundRequest->transaction_id)->toBe('12345678')
        ->and($refundRequest->refund_amount)->toBe(100.50)
        ->and($refundRequest->currency)->toBe(Currency::TRY);
});

it('Garanti BBVA ile iade isteği oluşturulur', function () {
    $auth = garantiAuth();

    $refundRequest = new RefundRequest(
        customer_ip_address: '192.168.1.1',
        order_number: 'GRN-REFUND-001',
        transaction_id: 'GRN-TXN-002',
        refund_amount: 50.00,
        currency: Currency::TRY,
    );

    $auth->validate();
    ValidationHelper::validateRefundRequest($refundRequest);

    expect($refundRequest->refund_amount)->toBe(50.00);
});

it('İptal ve iade arasındaki farkı doğru DTO ile temsil eder', function () {
    // İptal: Aynı gün, tutar belirtilmez
    $cancel = new CancelRequest(
        order_number: 'SAME-DAY-ORDER',
        transaction_id: 'TXN-001',
    );

    // İade: Gün sonu sonrası, tutar belirtilir
    $refund = new RefundRequest(
        order_number: 'PAST-ORDER',
        transaction_id: 'TXN-002',
        refund_amount: 75.00,
    );

    expect($cancel)->toBeInstanceOf(CancelRequest::class)
        ->and($refund)->toBeInstanceOf(RefundRequest::class)
        ->and($refund->refund_amount)->toBe(75.00);
});

// =====================================================
// Taksit Sorgulama (vpos.com.tr/docs/taksit-sorgulama)
// =====================================================

it('QNBpay ile taksit sorgulama isteği dokümantasyondaki gibi oluşturulur', function () {
    $auth = qnbPayAuth();

    $request = new BINInstallmentQueryRequest(
        BIN: '375624',
        amount: 1000.00,
        currency: Currency::TRY,
    );

    $auth->validate();
    ValidationHelper::validateBINInstallmentQuery($request);

    expect($request->BIN)->toBe('375624')
        ->and($request->amount)->toBe(1000.00)
        ->and($request->currency)->toBe(Currency::TRY);
});

it('6 haneli BIN ile taksit sorgulanabilir', function () {
    $request = new BINInstallmentQueryRequest(BIN: '411111', amount: 500.00);

    expect($request->validate())->toBeEmpty();
});

it('8 haneli BIN ile taksit sorgulanabilir', function () {
    $request = new BINInstallmentQueryRequest(BIN: '41111111', amount: 500.00);

    expect($request->validate())->toBeEmpty();
});

// =====================================================
// Test Kartları Validasyonu
// =====================================================

it('Garanti BBVA test kartı geçerli formattadır', function () {
    $saleInfo = new SaleInfo(
        card_name_surname: 'Test Kart',
        card_number: '5289394722895016',
        card_expiry_month: 1,
        card_expiry_year: 2025,
        card_cvv: '030',
        amount: 10.00,
        installment: 1,
    );

    $errors = $saleInfo->validate();
    expect($errors)->toBeEmpty()
        ->and(strlen($saleInfo->card_number))->toBe(16)
        ->and($saleInfo->card_expiry_month)->toBeBetween(1, 12)
        ->and(strlen($saleInfo->card_cvv))->toBe(3);
});

it('İş Bankası test kartı geçerli formattadır', function () {
    $saleInfo = new SaleInfo(
        card_name_surname: 'Test Kart',
        card_number: '4508034508034509',
        card_expiry_month: 12,
        card_expiry_year: 2026,
        card_cvv: '000',
        amount: 10.00,
        installment: 1,
    );

    $errors = $saleInfo->validate();
    expect($errors)->toBeEmpty()
        ->and(strlen($saleInfo->card_number))->toBe(16)
        ->and($saleInfo->card_expiry_month)->toBe(12);
});

it('QNBpay test kartı geçerli formattadır', function () {
    $saleInfo = new SaleInfo(
        card_name_surname: 'test kart',
        card_number: '4022780520669303',
        card_expiry_month: 1,
        card_expiry_year: 2050,
        card_cvv: '988',
        amount: 10.00,
        installment: 1,
    );

    $errors = $saleInfo->validate();
    expect($errors)->toBeEmpty()
        ->and(strlen($saleInfo->card_number))->toBe(16)
        ->and($saleInfo->card_expiry_year)->toBe(2050);
});

// =====================================================
// Dokümantasyondaki CustomerInfo formatı
// =====================================================

it('Dokümantasyondaki müşteri bilgileri doğru şekilde oluşturulur', function () {
    $info = testCustomerInfo();

    expect($info->name)->toBe('cem')
        ->and($info->surname)->toBe('pehlivan')
        ->and($info->email_address)->toBe('test@test.com')
        ->and($info->phone_number)->toBe('1111111111')
        ->and($info->tax_number)->toBe('1111111111')
        ->and($info->tax_office)->toBe('maltepe')
        ->and($info->country)->toBe(Country::TUR)
        ->and($info->city_name)->toBe('istanbul')
        ->and($info->town_name)->toBe('maltepe')
        ->and($info->address_description)->toBe('adres')
        ->and($info->post_code)->toBe('34000');
});

it('CustomerInfo sanitizasyonu dokümantasyon verileriyle çalışır', function () {
    $info = testCustomerInfo();
    $sanitized = ValidationHelper::sanitizeCustomerInfo($info);

    expect($sanitized->name)->toBe('cem')
        ->and($sanitized->surname)->toBe('pehlivan')
        ->and($sanitized->country)->toBe(Country::TUR);
});

// =====================================================
// SanalPosClient Tam Akış Senaryoları
// =====================================================

it('SanalPosClient.sale validasyon zinciri Garanti BBVA için çalışır', function () {
    $auth = garantiAuth();
    $customerInfo = testCustomerInfo();

    $saleRequest = new SaleRequest(
        order_number: 'GRN-FULL-' . time(),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test Kart',
            card_number: '5289394722895016',
            card_expiry_month: 1,
            card_expiry_year: 2025,
            card_cvv: '030',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    // Validasyonlar geçmeli
    ValidationHelper::validateSaleRequest($saleRequest);
    ValidationHelper::validateAuth($auth);

    // Gateway oluşturulabilmeli
    $gateway = BankService::createGateway($auth->bank_code);
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

it('SanalPosClient.sale validasyon zinciri İş Bankası için çalışır', function () {
    $auth = isBankasiAuth();
    $customerInfo = testCustomerInfo();

    $saleRequest = new SaleRequest(
        order_number: 'ISB-FULL-' . time(),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test Kart',
            card_number: '4508034508034509',
            card_expiry_month: 12,
            card_expiry_year: 2026,
            card_cvv: '000',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    ValidationHelper::validateSaleRequest($saleRequest);
    ValidationHelper::validateAuth($auth);

    $gateway = BankService::createGateway($auth->bank_code);
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

it('SanalPosClient.sale validasyon zinciri QNBpay için çalışır', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();

    $saleRequest = new SaleRequest(
        order_number: 'QNB-FULL-' . time(),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    ValidationHelper::validateSaleRequest($saleRequest);
    ValidationHelper::validateAuth($auth);

    $gateway = BankService::createGateway($auth->bank_code);
    expect($gateway)->toBeInstanceOf(VirtualPOSServiceInterface::class);
});

// =====================================================
// Uçtan Uca Akış: Satış → İptal (Dokümantasyon Verileriyle)
// =====================================================

it('uçtan uca: QNBpay satış → iptal akışı', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();
    $orderNumber = 'QNB-E2E-' . strtoupper(dechex(time()));

    // 1. Satış isteği
    $saleRequest = new SaleRequest(
        order_number: $orderNumber,
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    ValidationHelper::validateSaleRequest($saleRequest);
    ValidationHelper::validateAuth($auth);

    // 2. Satış cevabı simülasyonu
    $saleResponse = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: $orderNumber,
        transaction_id: 'QNB-TXN-001',
    );

    expect($saleResponse->status)->toBe(SaleResponseStatus::Success);

    // 3. İptal isteği
    $cancelRequest = new CancelRequest(
        customer_ip_address: '1.1.1.1',
        order_number: $saleResponse->order_number,
        transaction_id: $saleResponse->transaction_id,
        currency: Currency::TRY,
    );

    ValidationHelper::validateCancelRequest($cancelRequest);

    // 4. İptal cevabı simülasyonu
    $cancelResponse = new CancelResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
    );

    expect($cancelResponse->status)->toBe(ResponseStatus::Success)
        ->and($cancelRequest->order_number)->toBe($orderNumber);
});

// =====================================================
// Uçtan Uca Akış: Satış → İade (Dokümantasyon Verileriyle)
// =====================================================

it('uçtan uca: QNBpay satış → iade akışı', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();
    $orderNumber = 'QNB-REFUND-' . strtoupper(dechex(time()));

    // 1. Satış sonrası iade isteği
    $refundRequest = new RefundRequest(
        customer_ip_address: '1.1.1.1',
        order_number: $orderNumber,
        transaction_id: 'QNB-TXN-002',
        refund_amount: 10.00,
        currency: Currency::TRY,
    );

    ValidationHelper::validateRefundRequest($refundRequest);
    ValidationHelper::validateAuth($auth);

    // 2. İade cevabı simülasyonu
    $refundResponse = new RefundResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
        refund_amount: 10.00,
    );

    expect($refundResponse->status)->toBe(ResponseStatus::Success)
        ->and($refundResponse->refund_amount)->toBe(10.00);
});

// =====================================================
// Uçtan Uca Akış: 3D Satış (Dokümantasyon Verileriyle)
// =====================================================

it('uçtan uca: QNBpay 3D satış tam akışı', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();
    $orderNumber = 'QNB-3D-' . strtoupper(dechex(time()));

    // 1. 3D satış isteği
    $saleRequest = new SaleRequest(
        order_number: $orderNumber,
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: 10.00,
            currency: Currency::TRY,
            installment: 1,
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(
            confirm: true,
            return_url: 'https://localhost/Payment/VirtualPOS3DResponse',
        ),
    );

    ValidationHelper::validateSaleRequest($saleRequest);
    ValidationHelper::validateAuth($auth);

    expect($saleRequest->payment_3d->confirm)->toBeTrue();

    // 2. Banka redirect cevabı simülasyonu
    $redirectResponse = new SaleResponse(
        status: SaleResponseStatus::RedirectHTML,
        message: '<html><body><form action="https://3d.bank.com" method="POST">...</form></body></html>',
        order_number: $orderNumber,
    );

    expect($redirectResponse->status)->toBe(SaleResponseStatus::RedirectHTML);

    // 3. Bankadan 3D doğrulama sonrası dönüş (3D şifresi: 123456)
    $sale3DResponse = new Sale3DResponse(
        responseArray: [
            'mdStatus' => '1',
            'md' => 'BANK_3D_MD_VALUE',
            'orderId' => $orderNumber,
            'transId' => 'QNB-3D-TXN-001',
            'procReturnCode' => '00',
        ],
    );

    expect($sale3DResponse->responseArray['mdStatus'])->toBe('1');

    // 4. Nihai sonuç simülasyonu
    $finalResponse = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: $orderNumber,
        transaction_id: 'QNB-3D-TXN-001',
    );

    expect($finalResponse->status)->toBe(SaleResponseStatus::Success)
        ->and($finalResponse->transaction_id)->toBe('QNB-3D-TXN-001');
});

// =====================================================
// Uçtan Uca: Taksit Sorgulama → Taksitli Satış
// =====================================================

it('uçtan uca: QNBpay taksit sorgulama → taksitli satış', function () {
    $auth = qnbPayAuth();
    $customerInfo = testCustomerInfo();

    // 1. BIN taksit sorgulama
    $binRequest = new BINInstallmentQueryRequest(
        BIN: '402278',
        amount: 1000.00,
        currency: Currency::TRY,
    );

    ValidationHelper::validateBINInstallmentQuery($binRequest);

    // 2. Taksit cevabı simülasyonu
    $binResponse = new BINInstallmentQueryResponse(
        confirm: true,
        installment_list: [
            ['installment' => 2, 'rate' => 1.50, 'totalAmount' => 1015.00],
            ['installment' => 3, 'rate' => 2.80, 'totalAmount' => 1028.00],
            ['installment' => 6, 'rate' => 5.40, 'totalAmount' => 1054.00],
            ['installment' => 9, 'rate' => 8.10, 'totalAmount' => 1081.00],
            ['installment' => 12, 'rate' => 10.80, 'totalAmount' => 1108.00],
        ],
    );

    expect($binResponse->confirm)->toBeTrue()
        ->and($binResponse->installment_list)->toHaveCount(5);

    // 3. Kullanıcı 3 taksit seçiyor
    $selectedInstallment = $binResponse->installment_list[1]; // 3 taksit
    expect($selectedInstallment['installment'])->toBe(3);

    // 4. Taksitli satış isteği
    $saleRequest = new SaleRequest(
        order_number: 'QNB-INSTALLMENT-' . time(),
        customer_ip_address: '1.1.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'test kart',
            card_number: '4022780520669303',
            card_expiry_month: 1,
            card_expiry_year: 2050,
            card_cvv: '988',
            amount: (float) $selectedInstallment['totalAmount'],
            currency: Currency::TRY,
            installment: $selectedInstallment['installment'],
        ),
        invoice_info: $customerInfo,
        shipping_info: $customerInfo,
        payment_3d: new Payment3DConfig(confirm: false),
    );

    ValidationHelper::validateSaleRequest($saleRequest);

    expect($saleRequest->sale_info->installment)->toBe(3)
        ->and($saleRequest->sale_info->amount)->toBe(1028.00);
});

// =====================================================
// testPlatform true/false geçişi (Canlı Ortama Geçiş)
// =====================================================

it('test ortamından canlı ortama geçiş testPlatform ile yapılır', function () {
    // Test ortamı
    $testAuth = new MerchantAuth(
        bank_code: '9990',
        merchant_id: '20158',
        merchant_user: 'TEST_USER',
        merchant_password: 'TEST_PASS',
        test_platform: true,
    );

    // Canlı ortam
    $liveAuth = new MerchantAuth(
        bank_code: '9990',
        merchant_id: 'LIVE_MERCHANT_ID',
        merchant_user: 'LIVE_USER',
        merchant_password: 'LIVE_PASS',
        test_platform: false,
    );

    expect($testAuth->test_platform)->toBeTrue()
        ->and($liveAuth->test_platform)->toBeFalse()
        ->and($testAuth->bank_code)->toBe($liveAuth->bank_code);
});

// =====================================================
// Banka Listesi ve Özellikleri
// =====================================================

it('tüm test ortamı bankaları banka listesinde bulunur', function () {
    $garanti = BankService::getBank('0062');
    $isBankasi = BankService::getBank('0064');
    $qnbPay = BankService::getBank('9990');

    expect($garanti)->not->toBeNull()
        ->and($garanti->bank_name)->toBe('Garanti BBVA')
        ->and($isBankasi)->not->toBeNull()
        ->and($isBankasi->bank_name)->toBe('İş Bankası')
        ->and($qnbPay)->not->toBeNull()
        ->and($qnbPay->bank_name)->toBe('QNBpay');
});

it('QNBpay taksit API ve commission özelliklerini destekler', function () {
    $qnbPay = BankService::getBank('9990');

    expect($qnbPay->collective_vpos)->toBeTrue()
        ->and($qnbPay->installment_api)->toBeTrue()
        ->and($qnbPay->commissionAutoAdd)->toBeTrue();
});

it('allBankList üzerinden test ortamı bankaları filtrelenebilir', function () {
    $testBankCodes = ['0062', '0064', '9990'];

    $filtered = SanalPosClient::allBankList(
        fn ($bank) => in_array($bank->bank_code, $testBankCodes)
    );

    expect($filtered)->toHaveCount(3);
    $codes = array_map(fn ($b) => $b->bank_code, $filtered);
    expect($codes)->toContain('0062')
        ->and($codes)->toContain('0064')
        ->and($codes)->toContain('9990');
});

// =====================================================
// Taksitli satış dataset ile test
// =====================================================

it('farklı taksit sayılarıyla satış isteği oluşturulabilir', function (int $installment) {
    $saleInfo = new SaleInfo(
        card_name_surname: 'test kart',
        card_number: '4022780520669303',
        card_expiry_month: 1,
        card_expiry_year: 2050,
        card_cvv: '988',
        amount: 100.00,
        currency: Currency::TRY,
        installment: $installment,
    );

    expect($saleInfo->validate())->toBeEmpty()
        ->and($saleInfo->installment)->toBe($installment);
})->with([1, 2, 3, 6, 9, 12]);

// =====================================================
// Farklı para birimleriyle satış
// =====================================================

it('farklı para birimleriyle satış isteği oluşturulabilir', function (Currency $currency, int $isoCode) {
    $saleInfo = new SaleInfo(
        card_name_surname: 'Test User',
        card_number: '4022780520669303',
        card_expiry_month: 1,
        card_expiry_year: 2050,
        card_cvv: '988',
        amount: 100.00,
        currency: $currency,
        installment: 1,
    );

    expect($saleInfo->currency->value)->toBe($isoCode);
})->with([
    'TRY' => [Currency::TRY, 949],
    'USD' => [Currency::USD, 840],
    'EUR' => [Currency::EUR, 978],
]);
