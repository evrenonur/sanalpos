<?php

/**
 * Örnek Kullanım Testleri
 *
 * Bu test dosyası SanalPos kütüphanesinin nasıl kullanılacağını gösteren
 * örnek senaryoları içerir. Validasyon, DTO oluşturma, fromArray dönüşümleri,
 * SanalPosClient çağrıları ve hata senaryoları test edilir.
 */

use EvrenOnur\SanalPos\DTOs\CustomerInfo;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Payment3DConfig;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\DTOs\SaleInfo;
use EvrenOnur\SanalPos\Enums\Country;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\SanalPosClient;
use EvrenOnur\SanalPos\Services\BankService;
use EvrenOnur\SanalPos\Support\ValidationHelper;

// =====================================================
// SaleRequest oluşturma ve validasyon senaryoları
// =====================================================

it('SaleRequest tüm alanlarıyla oluşturulabilir', function () {
    $request = new SaleRequest(
        order_number: 'ORD-2025-001',
        customer_ip_address: '192.168.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Ahmet Yılmaz',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            currency: Currency::TRY,
            amount: 150.75,
            installment: 3,
        ),
        invoice_info: new CustomerInfo(
            name: 'Ahmet',
            surname: 'Yılmaz',
            email_address: 'ahmet@example.com',
            phone_number: '5551234567',
            country: Country::TUR,
            city_name: 'İstanbul',
            town_name: 'Kadıköy',
            address_description: 'Moda Cad. No:1',
            post_code: '34710',
        ),
        shipping_info: new CustomerInfo(
            name: 'Ahmet',
            surname: 'Yılmaz',
            email_address: 'ahmet@example.com',
            phone_number: '5551234567',
            country: Country::TUR,
            city_name: 'İstanbul',
            town_name: 'Kadıköy',
            address_description: 'Moda Cad. No:1',
            post_code: '34710',
        ),
    );

    expect($request->order_number)->toBe('ORD-2025-001')
        ->and($request->customer_ip_address)->toBe('192.168.1.1')
        ->and($request->sale_info->card_name_surname)->toBe('Ahmet Yılmaz')
        ->and($request->sale_info->card_number)->toBe('4111111111111111')
        ->and($request->sale_info->amount)->toBe(150.75)
        ->and($request->sale_info->installment)->toBe(3)
        ->and($request->sale_info->currency)->toBe(Currency::TRY)
        ->and($request->invoice_info->name)->toBe('Ahmet')
        ->and($request->invoice_info->country)->toBe(Country::TUR)
        ->and($request->shipping_info->city_name)->toBe('İstanbul');
});

it('SaleRequest 3D ödeme yapılandırmasıyla oluşturulabilir', function () {
    $request = new SaleRequest(
        order_number: 'ORD-3D-001',
        customer_ip_address: '10.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Mehmet Demir',
            card_number: '5400360000000003',
            card_expiry_month: 6,
            card_expiry_year: 2028,
            card_cvv: '456',
            currency: Currency::TRY,
            amount: 250.00,
            installment: 1,
        ),
        invoice_info: new CustomerInfo(name: 'Mehmet', surname: 'Demir'),
        shipping_info: new CustomerInfo(name: 'Mehmet', surname: 'Demir'),
        payment_3d: new Payment3DConfig(
            confirm: true,
            return_url: 'https://example.com/3d-callback',
            is_desktop: true,
        ),
    );

    expect($request->payment_3d)->not->toBeNull()
        ->and($request->payment_3d->confirm)->toBeTrue()
        ->and($request->payment_3d->return_url)->toBe('https://example.com/3d-callback')
        ->and($request->payment_3d->is_desktop)->toBeTrue();
});

it('SaleRequest fromArray ile oluşturulabilir', function () {
    $request = SaleRequest::fromArray([
        'order_number' => 'ORD-FROM-ARRAY',
        'customer_ip_address' => '172.16.0.1',
        'sale_info' => [
            'card_name_surname' => 'Ali Veli',
            'card_number' => '4111111111111111',
            'card_expiry_month' => 3,
            'card_expiry_year' => 2027,
            'card_cvv' => '789',
            'currency' => 949,
            'amount' => 99.99,
            'installment' => 6,
        ],
        'invoice_info' => [
            'name' => 'Ali',
            'surname' => 'Veli',
            'email_address' => 'ali@test.com',
        ],
        'shipping_info' => [
            'name' => 'Ali',
            'surname' => 'Veli',
        ],
        'payment_3d' => [
            'confirm' => true,
            'return_url' => 'https://site.com/callback',
        ],
    ]);

    expect($request->order_number)->toBe('ORD-FROM-ARRAY')
        ->and($request->sale_info->card_name_surname)->toBe('Ali Veli')
        ->and($request->sale_info->currency)->toBe(Currency::TRY)
        ->and($request->sale_info->amount)->toBe(99.99)
        ->and($request->sale_info->installment)->toBe(6)
        ->and($request->invoice_info->email_address)->toBe('ali@test.com')
        ->and($request->payment_3d->confirm)->toBeTrue()
        ->and($request->payment_3d->return_url)->toBe('https://site.com/callback');
});

it('SaleRequest validate boş order_number için hata döner', function () {
    $request = new SaleRequest(
        order_number: '',
        customer_ip_address: '127.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 100,
        ),
    );

    $errors = $request->validate();
    expect($errors)->toContain('Sipariş numarası boş olamaz.');
});

it('SaleInfo validate geçersiz kart bilgileri için hata döner', function () {
    $info = new SaleInfo(
        card_name_surname: '',
        card_number: '123',
        card_expiry_month: 13,
        card_expiry_year: 2018,
        card_cvv: '1',
        amount: -10,
        installment: 20,
    );

    $errors = $info->validate();
    expect($errors)->toHaveCount(7)
        ->and($errors)->toContain('Kart üzerindeki isim boş olamaz.')
        ->and($errors)->toContain('Kart numarası 15-19 karakter arasında olmalıdır.')
        ->and($errors)->toContain('Son kullanma ayı 1-12 arasında olmalıdır.')
        ->and($errors)->toContain('Son kullanma yılı geçersiz.')
        ->and($errors)->toContain('CVV 3-4 karakter olmalıdır.')
        ->and($errors)->toContain('Tutar sıfırdan büyük olmalıdır.')
        ->and($errors)->toContain('Taksit sayısı 1-15 arasında olmalıdır.');
});

it('SaleInfo validate geçerli bilgilerle boş hata döner', function () {
    $info = new SaleInfo(
        card_name_surname: 'Test User',
        card_number: '4111111111111111',
        card_expiry_month: 12,
        card_expiry_year: 2030,
        card_cvv: '123',
        amount: 100.50,
        installment: 3,
    );

    expect($info->validate())->toBeEmpty();
});

// =====================================================
// MerchantAuth oluşturma senaryoları
// =====================================================

it('MerchantAuth tüm bankalar için oluşturulabilir', function () {
    $auth = new MerchantAuth(
        bank_code: '0062',
        merchant_id: '7000679',
        merchant_user: 'PROVAUT',
        merchant_password: '123qweASD/',
        merchant_storekey: '12345678',
        test_platform: true,
    );

    expect($auth->bank_code)->toBe('0062')
        ->and($auth->test_platform)->toBeTrue();
});

it('MerchantAuth fromArray ile oluşturulabilir', function () {
    $auth = MerchantAuth::fromArray([
        'bank_code' => '0046',
        'merchant_id' => 'MERCHANT_ID',
        'merchant_user' => 'USERNAME',
        'merchant_password' => 'PASSWORD',
        'merchant_storekey' => 'STOREKEY',
        'test_platform' => false,
    ]);

    expect($auth->bank_code)->toBe('0046')
        ->and($auth->merchant_id)->toBe('MERCHANT_ID')
        ->and($auth->test_platform)->toBeFalse();
});

it('MerchantAuth boş bank_code için exception fırlatır', function () {
    $auth = new MerchantAuth(bank_code: '');
    $auth->validate();
})->throws(\InvalidArgumentException::class, 'Banka kodu boş olamaz.');

// =====================================================
// CancelRequest senaryoları
// =====================================================

it('CancelRequest oluşturulabilir ve validate edilebilir', function () {
    $request = new CancelRequest(
        customer_ip_address: '192.168.1.1',
        order_number: 'ORD-CANCEL-001',
        transaction_id: 'TXN-12345',
        currency: Currency::TRY,
    );

    expect($request->order_number)->toBe('ORD-CANCEL-001')
        ->and($request->transaction_id)->toBe('TXN-12345')
        ->and($request->currency)->toBe(Currency::TRY)
        ->and($request->validate())->toBeEmpty();
});

it('CancelRequest fromArray ile oluşturulabilir', function () {
    $request = CancelRequest::fromArray([
        'customer_ip_address' => '10.0.0.1',
        'order_number' => 'ORD-100',
        'transaction_id' => 'TXN-500',
        'currency' => 949,
    ]);

    expect($request->order_number)->toBe('ORD-100')
        ->and($request->currency)->toBe(Currency::TRY);
});

// =====================================================
// RefundRequest senaryoları
// =====================================================

it('RefundRequest tam ve kısmi iade için oluşturulabilir', function () {
    // Tam iade
    $fullRefund = new RefundRequest(
        customer_ip_address: '192.168.1.1',
        order_number: 'ORD-REFUND-001',
        transaction_id: 'TXN-789',
        refund_amount: 250.00,
        currency: Currency::TRY,
    );

    expect($fullRefund->refund_amount)->toBe(250.00)
        ->and($fullRefund->validate())->toBeEmpty();

    // Kısmi iade
    $partialRefund = new RefundRequest(
        order_number: 'ORD-REFUND-002',
        refund_amount: 75.50,
    );

    expect($partialRefund->refund_amount)->toBe(75.50)
        ->and($partialRefund->validate())->toBeEmpty();
});

it('RefundRequest sıfır tutar için hata döner', function () {
    $request = new RefundRequest(
        order_number: 'ORD-001',
        refund_amount: 0,
    );

    $errors = $request->validate();
    expect($errors)->toContain('İade tutarı sıfırdan büyük olmalıdır.');
});

// =====================================================
// BINInstallmentQueryRequest senaryoları
// =====================================================

it('BINInstallmentQueryRequest oluşturulabilir', function () {
    $request = new BINInstallmentQueryRequest(
        BIN: '411111',
        amount: 500.00,
        currency: Currency::TRY,
    );

    expect($request->BIN)->toBe('411111')
        ->and($request->amount)->toBe(500.00)
        ->and($request->validate())->toBeEmpty();
});

it('BINInstallmentQueryRequest geçersiz BIN için hata döner', function () {
    $request = new BINInstallmentQueryRequest(BIN: '123');
    $errors = $request->validate();
    expect($errors)->toContain('BIN 6-8 karakter arasında olmalıdır.');
});

// =====================================================
// Sale3DResponse senaryoları
// =====================================================

it('Sale3DResponse bankadan dönen yanıtla oluşturulabilir', function () {
    $response = new Sale3DResponse(
        responseArray: [
            'mdStatus' => '1',
            'md' => 'SOME_MD_VALUE',
            'orderId' => 'ORD-3D-001',
            'transId' => 'TXN-3D-001',
        ],
        currency: Currency::TRY,
        amount: 100.00,
    );

    expect($response->responseArray)->toHaveKey('mdStatus')
        ->and($response->responseArray['orderId'])->toBe('ORD-3D-001')
        ->and($response->currency)->toBe(Currency::TRY)
        ->and($response->amount)->toBe(100.00);
});

it('Sale3DResponse fromArray ile oluşturulabilir', function () {
    $response = Sale3DResponse::fromArray([
        'responseArray' => ['mdStatus' => '1', 'md' => 'XYZ'],
        'currency' => 949,
        'amount' => 200.00,
    ]);

    expect($response->responseArray['mdStatus'])->toBe('1')
        ->and($response->currency)->toBe(Currency::TRY);
});

// =====================================================
// SaleQueryRequest senaryoları
// =====================================================

it('SaleQueryRequest oluşturulabilir ve toArray destekler', function () {
    $request = new SaleQueryRequest(order_number: 'ORD-QUERY-001');

    expect($request->order_number)->toBe('ORD-QUERY-001')
        ->and($request->toArray())->toBe(['order_number' => 'ORD-QUERY-001']);
});

it('SaleQueryRequest boş order_number için exception fırlatır', function () {
    $request = new SaleQueryRequest(order_number: '');
    $request->validate();
})->throws(\InvalidArgumentException::class, 'order_number alanı zorunludur');

// =====================================================
// AllInstallmentQueryRequest ve AdditionalInstallmentQueryRequest
// =====================================================

it('AllInstallmentQueryRequest tutar ve para birimiyle oluşturulabilir', function () {
    $request = new AllInstallmentQueryRequest(
        amount: 1000.00,
        currency: Currency::USD,
    );

    expect($request->amount)->toBe(1000.00)
        ->and($request->currency)->toBe(Currency::USD);
});

it('AdditionalInstallmentQueryRequest SaleInfo ile oluşturulabilir', function () {
    $request = new AdditionalInstallmentQueryRequest(
        sale_info: new SaleInfo(
            card_name_surname: 'Test Kart',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 500.00,
            installment: 6,
        ),
    );

    expect($request->sale_info)->not->toBeNull()
        ->and($request->sale_info->amount)->toBe(500.00)
        ->and($request->sale_info->installment)->toBe(6);
});

// =====================================================
// Response DTO senaryoları
// =====================================================

it('SaleResponse başarılı senaryo simülasyonu', function () {
    $response = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: 'ORD-SUCCESS-001',
        transaction_id: 'TXN-98765',
        private_response: ['authCode' => '12345', 'hostReferenceNumber' => 'REF001'],
    );

    expect($response->status)->toBe(SaleResponseStatus::Success)
        ->and($response->message)->toBe('İşlem başarılı')
        ->and($response->transaction_id)->toBe('TXN-98765')
        ->and($response->private_response)->toHaveKey('authCode');
});

it('SaleResponse 3D redirect senaryo simülasyonu', function () {
    $response = new SaleResponse(
        status: SaleResponseStatus::RedirectHTML,
        message: '<html><body><form>...</form></body></html>',
        order_number: 'ORD-3D-002',
    );

    expect($response->status)->toBe(SaleResponseStatus::RedirectHTML)
        ->and($response->message)->toContain('<html>');
});

it('SaleResponse hata senaryo simülasyonu', function () {
    $response = new SaleResponse(
        status: SaleResponseStatus::Error,
        message: 'Kart numarası geçersiz',
        order_number: 'ORD-FAIL-001',
    );

    expect($response->status)->toBe(SaleResponseStatus::Error)
        ->and($response->message)->toBe('Kart numarası geçersiz')
        ->and($response->transaction_id)->toBeNull();
});

it('CancelResponse başarılı ve hatalı senaryolar', function () {
    $success = new CancelResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
        private_response: ['cancelCode' => 'CC001'],
    );

    $error = new CancelResponse(
        status: ResponseStatus::Error,
        message: 'İşlem iptal edilemedi',
    );

    expect($success->status)->toBe(ResponseStatus::Success)
        ->and($success->private_response)->toHaveKey('cancelCode')
        ->and($error->status)->toBe(ResponseStatus::Error)
        ->and($error->private_response)->toBeNull();
});

it('RefundResponse başarılı iade senaryosu', function () {
    $response = new RefundResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
        refund_amount: 75.50,
        private_response: ['refundCode' => 'RF001'],
    );

    expect($response->status)->toBe(ResponseStatus::Success)
        ->and($response->refund_amount)->toBe(75.50);
});

it('BINInstallmentQueryResponse taksit listesiyle oluşturulabilir', function () {
    $response = new BINInstallmentQueryResponse(
        confirm: true,
        installment_list: [
            ['installment' => 2, 'rate' => 1.10, 'totalAmount' => 101.10],
            ['installment' => 3, 'rate' => 2.20, 'totalAmount' => 102.20],
            ['installment' => 6, 'rate' => 4.50, 'totalAmount' => 104.50],
        ],
        private_response: ['SanalPOS_ID' => '12345'],
    );

    expect($response->confirm)->toBeTrue()
        ->and($response->installment_list)->toHaveCount(3)
        ->and($response->private_response)->toHaveKey('SanalPOS_ID');
});

it('SaleQueryResponse toArray dönüşümü doğru çalışır', function () {
    $response = new SaleQueryResponse(
        status: SaleQueryResponseStatus::Found,
        message: 'İşlem bulundu',
        order_number: 'ORD-QUERY-001',
        transaction_id: 'TXN-QRY-001',
        transactionDate: '2025-01-15 14:30:00',
        amount: 150.75,
    );

    $array = $response->toArray();

    expect($array['statu'])->toBe(SaleQueryResponseStatus::Found->value)
        ->and($array['order_number'])->toBe('ORD-QUERY-001')
        ->and($array['amount'])->toBe(150.75)
        ->and($array['transactionDate'])->toBe('2025-01-15 14:30:00');
});

// =====================================================
// ValidationHelper senaryoları
// =====================================================

it('ValidationHelper sale validasyonu boş alanlar için exception fırlatır', function () {
    $request = new SaleRequest(order_number: '');

    expect(fn () => ValidationHelper::validateSaleRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'order_number alanı zorunludur');
});

it('ValidationHelper sale validasyonu eksik sale_info için exception fırlatır', function () {
    $request = new SaleRequest(
        order_number: 'ORD-001',
        customer_ip_address: '127.0.0.1',
    );

    expect(fn () => ValidationHelper::validateSaleRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'sale_info alanı zorunludur');
});

it('ValidationHelper sale validasyonu eksik invoice_info için exception fırlatır', function () {
    $request = new SaleRequest(
        order_number: 'ORD-001',
        customer_ip_address: '127.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 100,
        ),
    );

    expect(fn () => ValidationHelper::validateSaleRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'invoice_info alanı zorunludur');
});

it('ValidationHelper geçersiz kart numarası için exception fırlatır', function () {
    $request = new SaleRequest(
        order_number: 'ORD-001',
        customer_ip_address: '127.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test',
            card_number: '1234567890123456',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 100,
        ),
        invoice_info: new CustomerInfo(name: 'Test'),
        shipping_info: new CustomerInfo(name: 'Test'),
    );

    expect(fn () => ValidationHelper::validateSaleRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'Geçersiz kart numarası');
});

it('ValidationHelper cancel validasyonu her iki alan boşsa exception fırlatır', function () {
    $request = new CancelRequest(order_number: '', transaction_id: '');

    expect(fn () => ValidationHelper::validateCancelRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'order_number veya transaction_id alanlarından en az biri zorunludur');
});

it('ValidationHelper refund validasyonu sıfır tutar için exception fırlatır', function () {
    $request = new RefundRequest(order_number: 'ORD-001', refund_amount: 0);

    expect(fn () => ValidationHelper::validateRefundRequest($request))
        ->toThrow(\InvalidArgumentException::class, 'refund_amount sıfırdan büyük olmalıdır');
});

it('ValidationHelper BIN validasyonu kısa BIN için exception fırlatır', function () {
    $request = new BINInstallmentQueryRequest(BIN: '411');

    expect(fn () => ValidationHelper::validateBINInstallmentQuery($request))
        ->toThrow(\InvalidArgumentException::class, 'BIN 6-8 karakter olmalıdır');
});

it('ValidationHelper CustomerInfo sanitizasyonu uzun stringleri kırpar', function () {
    $info = new CustomerInfo(
        name: str_repeat('A', 100),
        surname: str_repeat('B', 100),
        email_address: str_repeat('c', 200) . '@test.com',
        city_name: str_repeat('D', 50),
        address_description: str_repeat('E', 300),
    );

    $sanitized = ValidationHelper::sanitizeCustomerInfo($info);

    expect(strlen($sanitized->name))->toBeLessThanOrEqual(50)
        ->and(strlen($sanitized->surname))->toBeLessThanOrEqual(50)
        ->and(strlen($sanitized->email_address))->toBeLessThanOrEqual(100)
        ->and(strlen($sanitized->city_name))->toBeLessThanOrEqual(25)
        ->and(strlen($sanitized->address_description))->toBeLessThanOrEqual(200);
});

// =====================================================
// SanalPosClient validasyon senaryoları
// =====================================================

it('SanalPosClient sale boş bank_code için exception fırlatır', function () {
    $request = new SaleRequest(
        order_number: 'ORD-001',
        customer_ip_address: '127.0.0.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Test User',
            card_number: '4111111111111111',
            card_expiry_month: 12,
            card_expiry_year: 2030,
            card_cvv: '123',
            amount: 100.00,
        ),
        invoice_info: new CustomerInfo(name: 'Test'),
        shipping_info: new CustomerInfo(name: 'Test'),
    );
    $auth = new MerchantAuth(bank_code: '');

    expect(fn () => SanalPosClient::sale($request, $auth))
        ->toThrow(\InvalidArgumentException::class);
});

it('SanalPosClient cancel boş alanlar için exception fırlatır', function () {
    $request = new CancelRequest;
    $auth = new MerchantAuth(bank_code: '0062');

    expect(fn () => SanalPosClient::cancel($request, $auth))
        ->toThrow(\InvalidArgumentException::class);
});

it('SanalPosClient refund sıfır tutar için exception fırlatır', function () {
    $request = new RefundRequest(order_number: 'ORD-001', refund_amount: 0);
    $auth = new MerchantAuth(bank_code: '0062');

    expect(fn () => SanalPosClient::refund($request, $auth))
        ->toThrow(\InvalidArgumentException::class);
});

it('SanalPosClient sale3DResponse Yapı Kredi için currency zorunlu', function () {
    $response = new Sale3DResponse(responseArray: ['mdStatus' => '1']);
    $auth = new MerchantAuth(bank_code: '0067');

    expect(fn () => SanalPosClient::sale3DResponse($response, $auth))
        ->toThrow(\InvalidArgumentException::class, 'currency alanı Yapı Kredi bankası için zorunludur');
});

// =====================================================
// BankService ve allBankList senaryoları
// =====================================================

it('SanalPosClient allBankList tüm bankaları döner', function () {
    $banks = SanalPosClient::allBankList();

    expect($banks)->toBeArray()
        ->and(count($banks))->toBeGreaterThan(10);

    // Her banka bank_code ve bank_name içermeli
    foreach ($banks as $bank) {
        expect($bank->bank_code)->not->toBeEmpty()
            ->and($bank->bank_name)->not->toBeEmpty();
    }
});

it('SanalPosClient allBankList filtre ile çalışır', function () {
    // Sadece taksit API desteği olan bankalar
    $banks = SanalPosClient::allBankList(fn ($bank) => $bank->installment_api === true);

    expect($banks)->toBeArray();
    foreach ($banks as $bank) {
        expect($bank->installment_api)->toBeTrue();
    }
});

it('SanalPosClient allBankList collective_vpos filtresiyle çalışır', function () {
    $collectiveBanks = SanalPosClient::allBankList(fn ($bank) => $bank->collective_vpos === true);

    expect($collectiveBanks)->toBeArray();
    foreach ($collectiveBanks as $bank) {
        expect($bank->collective_vpos)->toBeTrue();
    }
});

it('BankService bilinen banka kodu için doğru banka döner', function () {
    $bank = BankService::getBank('0062');

    expect($bank)->not->toBeNull()
        ->and($bank->bank_code)->toBe('0062')
        ->and($bank->bank_name)->toBe('Garanti BBVA');
});

it('BankService gateway destekli bankalar için gateway oluşturabilir', function () {
    $banks = BankService::allBanks();

    foreach ($banks as $bank) {
        try {
            $gateway = BankService::createGateway($bank->bank_code);
            expect($gateway)->toBeInstanceOf(\EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface::class);
        } catch (\InvalidArgumentException $e) {
            // Henüz entegrasyonu olmayan bankalar atlanır
            expect($e->getMessage())->toContain('entegrasyon bulunamadı');
        }
    }
});

// =====================================================
// Para birimi ve ülke enum senaryoları
// =====================================================

it('Currency enum tüm para birimlerini destekler', function () {
    expect(Currency::TRY->value)->toBe(949)
        ->and(Currency::USD->value)->toBe(840)
        ->and(Currency::EUR->value)->toBe(978);
});

it('Currency from ile oluşturulabilir', function () {
    $currency = Currency::from(949);
    expect($currency)->toBe(Currency::TRY);
});

it('SaleInfo USD para birimiyle oluşturulabilir', function () {
    $info = new SaleInfo(
        card_name_surname: 'John Doe',
        card_number: '4111111111111111',
        card_expiry_month: 12,
        card_expiry_year: 2030,
        card_cvv: '123',
        currency: Currency::USD,
        amount: 50.00,
        installment: 1,
    );

    expect($info->currency)->toBe(Currency::USD)
        ->and($info->currency->value)->toBe(840);
});

// =====================================================
// Uçtan uca (end-to-end) senaryo simülasyonları
// =====================================================

it('uçtan uca: satış isteği oluştur → validate → response', function () {
    // 1. İstek oluştur
    $request = new SaleRequest(
        order_number: 'E2E-001',
        customer_ip_address: '192.168.1.100',
        sale_info: new SaleInfo(
            card_name_surname: 'Fatma Kaya',
            card_number: '4111111111111111',
            card_expiry_month: 6,
            card_expiry_year: 2029,
            card_cvv: '321',
            currency: Currency::TRY,
            amount: 350.00,
            installment: 3,
        ),
        invoice_info: new CustomerInfo(
            name: 'Fatma',
            surname: 'Kaya',
            email_address: 'fatma@kaya.com',
            phone_number: '5559876543',
            country: Country::TUR,
            city_name: 'Ankara',
            town_name: 'Çankaya',
            address_description: 'Kızılay Mah. Atatürk Blv. No:10',
        ),
        shipping_info: new CustomerInfo(
            name: 'Fatma',
            surname: 'Kaya',
            country: Country::TUR,
            city_name: 'Ankara',
        ),
    );

    // 2. Validate
    $errors = $request->validate();
    expect($errors)->toBeEmpty();

    // 3. Auth oluştur
    $auth = new MerchantAuth(
        bank_code: '0062',
        merchant_id: 'TEST_MID',
        merchant_user: 'TEST_USER',
        merchant_password: 'TEST_PASS',
        merchant_storekey: 'TEST_KEY',
        test_platform: true,
    );
    $auth->validate(); // Exception fırlatmamalı

    // 4. Response simülasyonu
    $response = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: $request->order_number,
        transaction_id: 'GRN-TXN-12345',
    );

    expect($response->status)->toBe(SaleResponseStatus::Success)
        ->and($response->order_number)->toBe('E2E-001');
});

it('uçtan uca: 3D satış → redirect → geri dönüş akışı', function () {
    // 1. 3D İstek oluştur
    $request = new SaleRequest(
        order_number: 'E2E-3D-001',
        customer_ip_address: '10.0.0.50',
        sale_info: new SaleInfo(
            card_name_surname: 'Can Özdemir',
            card_number: '5400360000000003',
            card_expiry_month: 9,
            card_expiry_year: 2028,
            card_cvv: '999',
            currency: Currency::TRY,
            amount: 1500.00,
            installment: 6,
        ),
        invoice_info: new CustomerInfo(name: 'Can', surname: 'Özdemir'),
        shipping_info: new CustomerInfo(name: 'Can', surname: 'Özdemir'),
        payment_3d: new Payment3DConfig(
            confirm: true,
            return_url: 'https://shop.example.com/payment/callback',
        ),
    );

    expect($request->payment_3d->confirm)->toBeTrue();

    // 2. Redirect HTML cevabı simülasyonu
    $redirectResponse = new SaleResponse(
        status: SaleResponseStatus::RedirectHTML,
        message: '<html><body><form action="https://3d-gate.bank.com/auth" method="POST">...</form></body></html>',
        order_number: 'E2E-3D-001',
    );

    expect($redirectResponse->status)->toBe(SaleResponseStatus::RedirectHTML)
        ->and($redirectResponse->message)->toContain('3d-gate.bank.com');

    // 3. Bankadan geri dönüş simülasyonu
    $callbackData = new Sale3DResponse(
        responseArray: [
            'mdStatus' => '1',
            'md' => 'BANK_MD_VALUE_XYZ',
            'orderId' => 'E2E-3D-001',
            'transId' => 'BANK-TXN-99999',
            'procReturnCode' => '00',
        ],
        currency: Currency::TRY,
        amount: 1500.00,
    );

    expect($callbackData->responseArray['mdStatus'])->toBe('1')
        ->and($callbackData->responseArray['procReturnCode'])->toBe('00');

    // 4. Başarılı 3D sonuç simülasyonu
    $finalResponse = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: 'E2E-3D-001',
        transaction_id: 'BANK-TXN-99999',
    );

    expect($finalResponse->status)->toBe(SaleResponseStatus::Success);
});

it('uçtan uca: satış → iptal akışı', function () {
    // 1. Başarılı satış cevabı
    $saleResponse = new SaleResponse(
        status: SaleResponseStatus::Success,
        message: 'İşlem başarılı',
        order_number: 'E2E-CANCEL-001',
        transaction_id: 'TXN-TO-CANCEL',
    );

    // 2. İptal isteği
    $cancelRequest = new CancelRequest(
        customer_ip_address: '192.168.1.1',
        order_number: $saleResponse->order_number,
        transaction_id: $saleResponse->transaction_id,
    );

    expect($cancelRequest->order_number)->toBe('E2E-CANCEL-001')
        ->and($cancelRequest->transaction_id)->toBe('TXN-TO-CANCEL');

    // 3. İptal cevabı simülasyonu
    $cancelResponse = new CancelResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
    );

    expect($cancelResponse->status)->toBe(ResponseStatus::Success);
});

it('uçtan uca: satış → kısmi iade akışı', function () {
    // 1. Başarılı satış
    $saleResponse = new SaleResponse(
        status: SaleResponseStatus::Success,
        order_number: 'E2E-REFUND-001',
        transaction_id: 'TXN-TO-REFUND',
    );

    // 2. Kısmi iade isteği (toplam 500 TL'den 150 TL iade)
    $refundRequest = new RefundRequest(
        customer_ip_address: '192.168.1.1',
        order_number: $saleResponse->order_number,
        transaction_id: $saleResponse->transaction_id,
        refund_amount: 150.00,
        currency: Currency::TRY,
    );

    expect($refundRequest->refund_amount)->toBe(150.00);

    // 3. ValidationHelper ile kontrol
    ValidationHelper::validateRefundRequest($refundRequest);

    // 4. İade cevabı simülasyonu
    $refundResponse = new RefundResponse(
        status: ResponseStatus::Success,
        message: 'İşlem başarılı',
        refund_amount: 150.00,
    );

    expect($refundResponse->status)->toBe(ResponseStatus::Success)
        ->and($refundResponse->refund_amount)->toBe(150.00);
});

it('uçtan uca: BIN sorgulama → taksitli satış akışı', function () {
    // 1. BIN sorgula
    $binRequest = new BINInstallmentQueryRequest(
        BIN: '411111',
        amount: 1000.00,
        currency: Currency::TRY,
    );

    ValidationHelper::validateBINInstallmentQuery($binRequest);

    // 2. Taksit cevabı simülasyonu
    $binResponse = new BINInstallmentQueryResponse(
        confirm: true,
        installment_list: [
            ['installment' => 2, 'rate' => 1.20, 'totalAmount' => 1012.00],
            ['installment' => 3, 'rate' => 2.40, 'totalAmount' => 1024.00],
            ['installment' => 6, 'rate' => 5.10, 'totalAmount' => 1051.00],
            ['installment' => 9, 'rate' => 7.80, 'totalAmount' => 1078.00],
            ['installment' => 12, 'rate' => 10.50, 'totalAmount' => 1105.00],
        ],
    );

    expect($binResponse->confirm)->toBeTrue()
        ->and($binResponse->installment_list)->toHaveCount(5);

    // 3. Kullanıcı 6 taksit seçti → satış isteği oluştur
    $selectedInstallment = $binResponse->installment_list[2];
    expect($selectedInstallment['installment'])->toBe(6);

    $saleRequest = new SaleRequest(
        order_number: 'E2E-INSTALLMENT-001',
        customer_ip_address: '192.168.1.1',
        sale_info: new SaleInfo(
            card_name_surname: 'Zeynep Arslan',
            card_number: '4111111111111111',
            card_expiry_month: 3,
            card_expiry_year: 2029,
            card_cvv: '456',
            currency: Currency::TRY,
            amount: $selectedInstallment['totalAmount'],
            installment: $selectedInstallment['installment'],
        ),
        invoice_info: new CustomerInfo(name: 'Zeynep', surname: 'Arslan'),
        shipping_info: new CustomerInfo(name: 'Zeynep', surname: 'Arslan'),
    );

    expect($saleRequest->sale_info->installment)->toBe(6)
        ->and($saleRequest->sale_info->amount)->toBe(1051.00);
});
