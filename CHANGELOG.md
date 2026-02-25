# Changelog

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
