# Changelog

## [1.2.0](https://github.com/evrenonur/sanalpos/compare/v1.1.0...v1.2.0) (2026-04-27)

### 🚀 Yeni Özellikler

* Paynet sanal POS entegrasyonu eklendi
* CCPayment tabanlı ödeme kuruluşları için taksit komisyon politikası desteği eklendi
* CCPayment 3D ödeme akışı `/payment/complete` tamamlama adımı ile referans implementasyonla hizalandı

### 🐛 Hata Düzeltmeleri

* Iyzico 3D response private response yapısı iki aşamalı detay dönecek şekilde düzeltildi
* README Paynet ve komisyon politikası desteğini kapsayacak şekilde güncellendi

## [1.1.0](https://github.com/evrenonur/sanalpos/compare/v1.0.5...v1.1.0) (2026-04-12)

### 🚀 Yeni Özellikler

* **deps:** update PHP requirement to 8.3 and improve composer.json formatting ([1132880](https://github.com/evrenonur/sanalpos/commit/113288085ab9168c35bd5aea81ae33c26b8cbc1f))
* **deps:** upgrade to Laravel 13 and PHP 8.3 ([901b784](https://github.com/evrenonur/sanalpos/commit/901b7849ea7dddefe3fd1ed81bb31d0a5469cc91))

## [1.0.5](https://github.com/evrenonur/sanalpos/compare/v1.0.4...v1.0.5) (2026-03-06)

### 🐛 Hata Düzeltmeleri

* update ZiraatPayGateway test URLs ([5ccfcdd](https://github.com/evrenonur/sanalpos/commit/5ccfcddfd8f94407083aa690ac0996d288ff4081))

## [1.0.4](https://github.com/evrenonur/sanalpos/compare/v1.0.3...v1.0.4) (2026-02-25)

### 🐛 Hata Düzeltmeleri

* **ci:** release workflow changelog interpolation shell syntax hatası düzeltildi\n\nChangelog içeriği doğrudan shell'e interpolate edildiğinde parantez\niçeren commit hash'leri syntax error oluşturuyordu. Changelog artık\nenvironment variable olarak geçiriliyor." ([8241435](https://github.com/evrenonur/sanalpos/commit/824143579cef3a29f404c2afa6b3bf71b2f291b9))

Tüm önemli değişiklikler bu dosyada belgelenir.

Bu proje [Conventional Commits](https://www.conventionalcommits.org/) standardını takip eder
ve [Semantic Versioning](https://semver.org/) kullanır.

## [v1.0.0] - 2026-02-24

### 🚀 İlk Kararlı Sürüm

#### Desteklenen İşlemler
- Satış (3D'siz direkt satış)
- 3D Secure Satış (RedirectURL + RedirectHTML desteği)
- İptal (gün sonu öncesi işlem iptali)
- İade (tam ve kısmi iade)
- BIN Sorgulama (kart bilgi sorgulama)
- Taksit Sorgulama (BIN bazlı taksit seçenekleri)
- Tüm Taksit Listesi (banka bazlı tüm taksit seçenekleri)

#### Desteklenen Bankalar ve Ödeme Kuruluşları (37+)
Akbank, Akbank Nestpay, Alternatif Bank, Anadolubank, Denizbank, QNB Finansbank,
Finansbank Nestpay, Garanti BBVA, Halkbank, ING Bank, İş Bankası, Şekerbank, TEB,
Türkiye Finans, Vakıfbank, Yapı Kredi, Ziraat Bankası, Kuveyt Türk, Vakıf Katılım,
Cardplus, Paratika, Payten (MSU), ZiraatPay, VakıfPayS, Iyzico, Sipay, QNBpay,
ParamPos, PayBull, Parolapara, IQmoney, Ahlpay, Moka, Vepara, Tami, HalkÖde, PayNKolay

#### Framework & Altyapı
- PHP 8.1+ desteği
- Laravel 10, 11 ve 12 uyumu
- ServiceProvider, Facade ve Config ile tam Laravel entegrasyonu
- Laravel olmadan `SanalPosClient` ile bağımsız kullanım
- MIT Lisans
- 217 test, 808 assertion

[v1.0.0]: https://github.com/evrenonur/sanalpos/releases/tag/v1.0.0
