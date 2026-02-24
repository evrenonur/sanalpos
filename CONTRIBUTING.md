# Katkıda Bulunma Rehberi

SanalPos projesine katkıda bulunmak istediğiniz için teşekkürler! 🎉

## Nasıl Katkıda Bulunulur?

### 1. Fork & Clone

```bash
# Projeyi fork'layın (GitHub'da sağ üstteki "Fork" butonu)
# Fork'unuzu klonlayın
git clone https://github.com/KULLANICI_ADINIZ/sanalpos.git
cd sanalpos

# Upstream remote ekleyin
git remote add upstream https://github.com/evrenonur/sanalpos.git
```

### 2. Branch Oluşturun

```bash
# Güncel main'den yeni branch açın
git checkout main
git pull upstream main
git checkout -b feat/yeni-ozellik
```

Branch isimlendirme kuralları:
- `feat/ozellik-adi` → Yeni özellik
- `fix/hata-aciklamasi` → Hata düzeltme
- `docs/dokumantasyon` → Dokümantasyon
- `refactor/aciklama` → Yeniden düzenleme

### 3. Geliştirme

```bash
# Bağımlılıkları kurun
composer install

# Testleri çalıştırın
composer test

# Kod stilini kontrol edin
composer lint-test

# Kod stilini otomatik düzeltin
composer lint
```

### 4. Commit Kuralları

Bu proje **[Conventional Commits](https://www.conventionalcommits.org/)** standardını kullanır.

```
<tip>(<kapsam>): <açıklama>
```

| Tip | Açıklama | Versiyon Etkisi |
|-----|----------|-----------------|
| `feat` | Yeni özellik | MINOR (1.x.0) |
| `fix` | Hata düzeltme | PATCH (1.0.x) |
| `perf` | Performans iyileştirme | PATCH |
| `refactor` | Kod düzenleme | PATCH |
| `docs` | Dokümantasyon | - |
| `test` | Test ekleme/düzenleme | - |
| `style` | Kod stili | - |
| `chore` | Bakım | - |
| `ci` | CI/CD değişiklikleri | - |

**Örnekler:**
```bash
git commit -m "feat(gateway): Enpara sanal pos entegrasyonu"
git commit -m "fix(akbank): 3D secure hash hesaplama düzeltildi"
git commit -m "test: VakifbankGateway unit testleri eklendi"
git commit -m "docs: kurulum adımları güncellendi"
```

**Breaking Change:**
```bash
git commit -m "feat!: MerchantAuth constructor parametreleri değişti"
```

### 5. Pull Request Gönderin

```bash
git push origin feat/yeni-ozellik
```

GitHub'da "Compare & pull request" butonuyla PR oluşturun.

## Yeni Banka/Ödeme Kuruluşu Ekleme

Yeni bir gateway eklemek istiyorsanız:

1. `src/Enums/Bank.php` → Banka enum'una yeni değer ekleyin
2. `src/Gateways/Banks/` veya `src/Gateways/Providers/` → Gateway sınıfı oluşturun
3. `VirtualPOSServiceInterface` kontratını implemente edin
4. `SanalPosClient.php` → Gateway'i `createGateway()` metoduna ekleyin
5. `config/sanalpos.php` → Gerekli config değerlerini ekleyin
6. Unit test yazın
7. README'ye banka bilgisini ekleyin

### Gateway Şablonu

```php
<?php

namespace EvrenOnur\SanalPos\Gateways\Banks;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;

class YeniBankaGateway implements VirtualPOSServiceInterface
{
    // Tüm interface metodlarını implemente edin
}
```

## Geliştirme Ortamı

### Gereksinimler
- PHP 8.2+
- Composer
- ext-simplexml, ext-openssl, ext-json

### Testler

```bash
# Tüm testleri çalıştır
composer test

# Belirli bir test dosyasını çalıştır
vendor/bin/pest tests/Unit/BankServiceTest.php

# Kod coverage
composer test-coverage
```

### Kod Stili

Proje [Laravel Pint](https://github.com/laravel/pint) kullanır:

```bash
# Kontrol et
composer lint-test

# Otomatik düzelt
composer lint
```

## Kurallar

- Her PR için **en az bir test** yazın
- Mevcut testlerin geçtiğinden emin olun (`composer test`)
- Kod stilini kontrol edin (`composer lint-test`)
- Conventional Commits formatını kullanın
- Tek bir PR'da tek bir konu/özellik üzerinde çalışın
- Türkçe veya İngilizce commit mesajları kabul edilir

## Hata Bildirimi

Hata bildirmek için [Issues](https://github.com/evrenonur/sanalpos/issues) sayfasını kullanın:

1. Önce benzer bir issue var mı kontrol edin
2. Yeni issue oluştururken şablon doldurun
3. Hata adımlarını detaylı anlatın
4. PHP versiyonu, Laravel versiyonu ve banka bilgisini ekleyin

## Lisans

Katkıda bulunarak, katkılarınızın projenin [MIT Lisansı](LICENSE) altında lisanslanacağını kabul etmiş olursunuz.
