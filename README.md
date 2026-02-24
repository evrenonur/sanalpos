# SanalPos

[![Latest Version on Packagist](https://img.shields.io/packagist/v/evrenonur/sanalpos)](https://packagist.org/packages/evrenonur/sanalpos)
[![License](https://img.shields.io/github/license/evrenonur/sanalpos)](LICENSE)

## SanalPos: Sanal Pos Entegrasyonlarını Basitleştirin

SanalPos, Türkiye'deki birçok bankanın ve ödeme kuruluşunun sanal POS entegrasyonlarını tek bir kod tabanı ile kullanmayı mümkün kılan PHP/Laravel kütüphanesidir. Bu sayede geliştiriciler, her banka için ayrı ayrı kod yazmak zorunda kalmadan, tüm sanal POS işlemlerini tek bir kütüphane üzerinden gerçekleştirebilirler.

> Bu paket, [CP.VPOS](https://github.com/cempehlivan/CP.VPOS) (.NET) kütüphanesinin PHP/Laravel portudur.

## Özellikler

+ **Tek Kod Tabanı:** Farklı bankaların sanal pos entegrasyonları için ayrı ayrı kod yazmaya gerek kalmadan, tek bir API ile tüm işlemleri gerçekleştirebilirsiniz.
+ **Basitleştirilmiş İşlem Akışı:** Sanal pos işlemleri için gerekli tüm adımlar kütüphane tarafından otomatik olarak halledilir.
+ **3D Güvenli Ödeme Desteği:** 3D Güvenli Ödeme işlemleri için gerekli tüm adımlar desteklenir.
+ **Geniş Banka Kapsamı:** 35+ banka ve ödeme kuruluşu desteği.
+ **Laravel Entegrasyonu:** ServiceProvider, Facade ve config dosyası ile tam Laravel uyumluluğu.
+ **Bağımsız Kullanım:** Laravel olmadan da `SanalPosClient` statik sınıfı ile kullanılabilir.

## Gereksinimler

- PHP >= 8.1
- Laravel 10, 11 veya 12
- ext-simplexml, ext-openssl, ext-json

## Kurulum

Composer ile projenize ekleyin:

```bash
composer require evrenonur/sanalpos
```

Laravel otomatik olarak ServiceProvider'ı ve Facade'ı kaydeder. Config dosyasını yayınlamak için:

```bash
php artisan vendor:publish --provider="EvrenOnur\SanalPos\SanalPosServiceProvider"
```

## Kullanılabilir Sanal POS'lar

| Sanal POS | Satış | Satış 3D | İptal | İade |
| --------- | :---: | :------: | :---: | :---: |
| Akbank | ✔️ | ✔️ | ✔️ | ✔️ |
| Akbank Nestpay | ✔️ | ✔️ | ✔️ | ✔️ |
| Alternatif Bank | ✔️ | ✔️ | ✔️ | ✔️ |
| Anadolubank | ✔️ | ✔️ | ✔️ | ✔️ |
| Denizbank | ✔️ | ✔️ | ✔️ | ✔️ |
| QNB Finansbank | ✔️ | ✔️ | ✔️ | ✔️ |
| Finansbank Nestpay | ✔️ | ✔️ | ✔️ | ✔️ |
| Garanti BBVA | ✔️ | ✔️ | ❌ | ❌ |
| Halkbank | ✔️ | ✔️ | ✔️ | ✔️ |
| ING Bank | ✔️ | ✔️ | ✔️ | ✔️ |
| İş Bankası | ✔️ | ✔️ | ✔️ | ✔️ |
| Şekerbank | ✔️ | ✔️ | ✔️ | ✔️ |
| Türk Ekonomi Bankası | ✔️ | ✔️ | ✔️ | ✔️ |
| Türkiye Finans | ✔️ | ✔️ | ✔️ | ✔️ |
| Vakıfbank | ✔️ | ✔️ | ✔️ | ✔️ |
| Yapı Kredi Bankası | ✔️ | ✔️ | ❌ | ❌ |
| Ziraat Bankası | ✔️ | ✔️ | ✔️ | ✔️ |
| Kuveyt Türk | ✔️ | ✔️ | ❌ | ❌ |
| Vakıf Katılım | ✔️ | ✔️ | ❌ | ❌ |
| Cardplus | ✔️ | ✔️ | ✔️ | ✔️ |
| Paratika | ✔️ | ✔️ | ✔️ | ✔️ |
| Payten (MSU) | ✔️ | ✔️ | ✔️ | ✔️ |
| ZiraatPay | ✔️ | ✔️ | ✔️ | ✔️ |
| VakıfPayS | ✔️ | ✔️ | ✔️ | ✔️ |
| Iyzico | ✔️ | ✔️ | ✔️ | ✔️ |
| Sipay | ✔️ | ✔️ | ✔️ | ✔️ |
| QNBpay | ✔️ | ✔️ | ✔️ | ✔️ |
| ParamPos | ✔️ | ✔️ | ✔️ | ✔️ |
| PayBull | ✔️ | ✔️ | ✔️ | ✔️ |
| Parolapara | ✔️ | ✔️ | ✔️ | ✔️ |
| IQmoney | ✔️ | ✔️ | ✔️ | ✔️ |
| Ahlpay | ✔️ | ✔️ | ✔️ | ✔️ |
| Moka | ✔️ | ✔️ | ✔️ | ✔️ |
| Vepara | ✔️ | ✔️ | ✔️ | ✔️ |
| Tami | ✔️ | ✔️ | ✔️ | ✔️ |
| HalkÖde | ✔️ | ✔️ | ✔️ | ✔️ |
| PayNKolay | ✔️ | ✔️ | ✔️ | ✔️ |

## API Bilgilerinin Ayarlanması - `VirtualPOSAuth`

| Alan | Tür | Açıklama |
| ---- | --- | -------- |
| `bankCode` | `string` | Banka/ödeme kuruluşu kodu. `BankService` sabitlerini kullanın. |
| `merchantID` | `string` | Firma kodu / Üye işyeri numarası |
| `merchantUser` | `string` | API kullanıcı adı |
| `merchantPassword` | `string` | API kullanıcı şifresi |
| `merchantStorekey` | `string` | 3D store key / güvenlik anahtarı |
| `testPlatform` | `bool` | `true` → test ortamı, `false` → canlı ortam |

## Kullanım Örnekleri

### 3D'siz Direkt Satış İşlemi

`payment3D.confirm = false` gönderilmesi halinde 3D'siz çekim işlemi yapılır ve direkt olarak nihai sonuç döner.

```php
use EvrenOnur\SanalPos\SanalPosClient;
use EvrenOnur\SanalPos\Models\{VirtualPOSAuth, SaleRequest, SaleInfo, CustomerInfo, Payment3D};
use EvrenOnur\SanalPos\Services\BankService;
use EvrenOnur\SanalPos\Enums\{Currency, Country};

$auth = new VirtualPOSAuth(
    bankCode: BankService::QNBPAY,
    merchantID: '20158',
    merchantUser: '07fb70f9d8de575f32baa6518e38c5d6',
    merchantPassword: '61d97b2cac247069495be4b16f8604db',
    merchantStorekey: '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK',
    testPlatform: true,
);

$customerInfo = new CustomerInfo(
    taxNumber: '1111111111',
    emailAddress: 'test@test.com',
    name: 'cem',
    surname: 'pehlivan',
    phoneNumber: '1111111111',
    addressDesc: 'adres',
    cityName: 'istanbul',
    country: Country::TUR,
    postCode: '34000',
    taxOffice: 'maltepe',
    townName: 'maltepe',
);

$saleRequest = new SaleRequest(
    orderNumber: dechex(time()),
    saleInfo: new SaleInfo(
        cardNameSurname: 'test kart',
        cardNumber: '4022780520669303',
        cardExpiryDateMonth: 1,
        cardExpiryDateYear: 2050,
        cardCVV: '988',
        amount: 10.00,
        currency: Currency::TRY,
        installment: 1,
    ),
    payment3D: new Payment3D(confirm: false),
    customerIPAddress: '1.1.1.1',
    invoiceInfo: $customerInfo,
    shippingInfo: $customerInfo,
);

$response = SanalPosClient::sale($saleRequest, $auth);

echo "Statu: {$response->statu->name}\n";
echo "Mesaj: {$response->message}\n";
echo "İşlem ID: {$response->transactionId}\n";
```

### 3D Secure Satış İşlemi

`payment3D.confirm = true` gönderilmesi halinde 3D'li satış işlemi başlatılır. `payment3D.returnURL` alanına 3D'den gelecek olan cevabın iletilmesi istenen URL girilmelidir.

```php
$saleRequest = new SaleRequest(
    orderNumber: dechex(time()),
    saleInfo: new SaleInfo(
        cardNameSurname: 'test kart',
        cardNumber: '4022780520669303',
        cardExpiryDateMonth: 1,
        cardExpiryDateYear: 2050,
        cardCVV: '988',
        amount: 10.00,
        currency: Currency::TRY,
        installment: 1,
    ),
    payment3D: new Payment3D(
        confirm: true,
        returnURL: 'https://example.com/payment/3d-response',
    ),
    customerIPAddress: request()->ip(),
    invoiceInfo: $customerInfo,
    shippingInfo: $customerInfo,
);

$response = SanalPosClient::sale($saleRequest, $auth);

// statu RedirectURL ise → $response->message içindeki URL'e yönlendirin
// statu RedirectHTML ise → $response->message içindeki HTML'i tarayıcıda gösterin
```

### 3D Secure Satış İşlemi - 2. Adım

```php
use EvrenOnur\SanalPos\Models\Sale3DResponseRequest;

// Controller method - 3D'den gelen callback
public function virtualPOS3DResponse(Request $request)
{
    $responseArray = $request->all();

    $response = SanalPosClient::sale3DResponse(
        new Sale3DResponseRequest(responseArray: $responseArray),
        $auth
    );

    // $response->statu → SaleResponseStatu::Success veya Error
    // $response->message → Sonuç mesajı
    // $response->transactionId → İşlem ID
}
```

### İptal İşlemi

```php
use EvrenOnur\SanalPos\Models\CancelRequest;

$cancelRequest = new CancelRequest(
    orderNumber: 'ORDER-001',
    transactionId: 'TXN-001',
);

$response = SanalPosClient::cancel($cancelRequest, $auth);
```

### İade İşlemi

```php
use EvrenOnur\SanalPos\Models\RefundRequest;

$refundRequest = new RefundRequest(
    orderNumber: 'ORDER-001',
    refundAmount: 50.00,
    currency: Currency::TRY,
);

$response = SanalPosClient::refund($refundRequest, $auth);
```

### BIN Taksit Sorgulama

```php
use EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest;

$request = new BINInstallmentQueryRequest(
    BIN: '411111',  // Kart numarasının ilk 6 hanesi
    currency: Currency::TRY,
);

$response = SanalPosClient::binInstallmentQuery($request, $auth);
```

### Laravel Facade ile Kullanım

```php
use EvrenOnur\SanalPos\Facades\SanalPos;

$response = SanalPos::sale($saleRequest, $auth);
$response = SanalPos::cancel($cancelRequest, $auth);
$response = SanalPos::refund($refundRequest, $auth);
```

## Sanal POS API Bilgileri

| Sanal POS | bankCode | merchantID | merchantUser | merchantPassword | merchantStorekey |
| --------- | -------- | ---------- | ------------ | ---------------- | ---------------- |
| Akbank | `BankService::AKBANK` | İş Yeri No | merchantSafeId | Terminal Safe ID | Secret Key |
| Akbank Nestpay | `BankService::AKBANK_NESTPAY` | Mağaza Kodu | Api Kullanıcısı | Api Şifresi | 3D Storekey |
| Garanti BBVA | `BankService::GARANTI_BBVA` | Firma Kodu | Terminal No | PROVAUT Şifresi | 3D Anahtarı |
| Vakıfbank | `BankService::VAKIFBANK` | Üye İşyeri No | POS No | Api Şifresi | — |
| Yapı Kredi | `BankService::YAPI_KREDI` | Firma Kodu | Terminal No | PosNet ID | ENCKEY |
| Iyzico | `BankService::IYZICO` | Üye İşyeri No | API Anahtarı | Güvenlik Anahtarı | — |
| Sipay | `BankService::SIPAY` | Üye İşyeri ID | Uygulama Anahtarı | Uygulama Parolası | Merchant Key |
| QNBpay | `BankService::QNBPAY` | Üye İşyeri ID | Uygulama Anahtarı | Uygulama Parolası | Merchant Key |
| ParamPos | `BankService::PARAMPOS` | Client Code | Kullanıcı Adı | Şifre | Guid Anahtar |
| Moka | `BankService::MOKA` | Bayi Kodu | Api Kullanıcısı | Api Şifresi | — |
| Ahlpay | `BankService::AHLPAY` | Member ID | Api Kullanıcısı | Api Şifresi | API Key |
| Payten (MSU) | `BankService::PAYTEN` | Firma Kodu | Api Kullanıcısı | Api Şifresi | — |
| Tami | `BankService::TAMI` | Üye İşyeri No | Terminal No | KidValue\|KValue | Secret Key |
| PayNKolay | `BankService::PAYNKOLAY` | sx (Token) | sx list | sx iptal | Secret Key |

> Nestpay bankalarında (Alternatif Bank, Anadolubank, Denizbank, Halkbank, ING Bank, İş Bankası, Şekerbank, TEB, Türkiye Finans, Ziraat Bankası, Cardplus) alan eşlemesi aynıdır: Mağaza Kodu, Api Kullanıcısı, Api Şifresi, 3D Storekey.

## Proje Yapısı

```
src/
├── Contracts/          # VirtualPOSServiceInterface
├── Enums/              # Currency, Country, ResponseStatu, vb.
├── Facades/            # Laravel Facade
├── Gateways/
│   ├── Banks/          # Banka gateway'leri
│   │   └── Nestpay/    # Nestpay altyapılı bankalar
│   └── PaymentInstitutions/
│       ├── CCPayment/  # CCPayment altyapılı kuruluşlar
│       └── Payten/     # Payten altyapılı kuruluşlar
├── Helpers/            # StringHelper, ValidationHelper, XmlHelper
├── Infrastructures/
│   └── Iyzico/         # Iyzico özel altyapı sınıfları
├── Models/             # Request/Response DTO'ları
├── Services/           # BankService, BinService
├── SanalPosClient.php  # Ana statik istemci
└── SanalPosServiceProvider.php
```

## Test

```bash
composer test
# veya
vendor/bin/phpunit --testdox
```

## Lisans

MIT lisansı altında dağıtılmaktadır. Detaylar için [LICENSE](LICENSE) dosyasına bakınız.

## Referans

Bu PHP paketi, [cempehlivan/CP.VPOS](https://github.com/cempehlivan/CP.VPOS) (.NET) kütüphanesinin mimari yapısı ve iş akışları temel alınarak geliştirilmiştir.
