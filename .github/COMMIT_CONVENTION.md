# Conventional Commits Kuralları
#
# Commit mesaj formatı:
#   <type>(<scope>): <description>
#
# Tipler:
#   feat     → Yeni özellik (MINOR versiyon artışı)
#   fix      → Hata düzeltme (PATCH versiyon artışı)
#   perf     → Performans iyileştirme (PATCH versiyon artışı)
#   refactor → Kod yeniden düzenleme (PATCH versiyon artışı)
#   docs     → Dokümantasyon değişikliği (release tetiklemez)
#   style    → Kod stili değişikliği (release tetiklemez)
#   test     → Test ekleme/düzenleme (release tetiklemez)
#   chore    → Bakım işleri (release tetiklemez)
#   ci       → CI/CD değişiklikleri (release tetiklemez)
#   build    → Build sistemi değişiklikleri (release tetiklemez)
#
# Breaking Change (MAJOR versiyon artışı):
#   feat!: breaking change açıklaması
#   veya commit body'de: BREAKING CHANGE: açıklama
#
# Örnekler:
#   feat: Enpara gateway desteği eklendi
#   feat(gateway): Enpara sanal pos entegrasyonu
#   fix(akbank): 3D secure hash hesaplama hatası düzeltildi
#   fix: empty response handling for cancel request
#   perf(http): connection pooling eklendi
#   refactor(nestpay): ortak kodlar trait'e taşındı
#   docs: README güncellendi
#   test: VakifbankGateway unit testleri eklendi
#   chore: dependency güncellemeleri
#   feat!: MerchantAuth constructor parametreleri değişti
#
# Scope örnekleri:
#   gateway, akbank, garanti, nestpay, qnbpay, iyzico,
#   http, dto, enum, config, test, docs
