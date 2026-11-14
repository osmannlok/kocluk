# Güvenlik Düzeltmeleri

Tarih: 2026-11-13
Oturum: `[17-Güvenlik denetimi]` takip düzeltmesi
Kapsam: Statik tarama sonrası doğrulanan tek kod düzeltmesi ve denetim dokümanı düzeltmeleri.

## Yapılan Değişiklikler

### 1. `resources/views/partials/pagination.php`
- Doğrudan `$_GET` erişimi kaldırıldı.
- Mevcut sorgu dizesi artık `Request::capture()->query()` üzerinden okunuyor.
- `page` parametresi yine her zaman yeniden hesaplanıyor; diğer sorgu parametreleri korunuyor.
- Böylece `security_audit.php` içindeki `INPUT` / “Request dışında süperglobal kullanımı” bulgusu kapandı.
- `07-on-yuz-sozlesme.md` sözleşmesindeki “mevcut query korunur” davranışı değişmedi.

### 2. `docs/sozlesmeler/17-sozlesme-ozeti.md`
- Scanner bulgu sayıları güncellendi: 147 satır çıktı, 145 bulgu.
- `pagination.php` için “gerçek bulgu” ifadesi kaldırıldı; düzeltme sonrası durum yazıldı.
- Kalan SQL/XSS bulgularının triyaj niteliği açıklandı.

### 3. `docs/guvenlik-duzeltmeleri.md`
- Bu değişiklik kaydı oluşturuldu.

## Doğrulama

```bash
php -l resources/views/partials/pagination.php
php scripts/security_audit.php
```

Sonuçlar:
- `php -l`: “No syntax errors detected”.
- `security_audit.php`: çıkış kodu `1`; 147 satır çıktı; 145 bulgu.
- `pagination.php` artık tarama çıktısında yok.
- Bulgu dağılımı: `Kritik=36`, `Yüksek=15`, `Orta=2`, `Düşük=92`.

## Kalan Bulguların Durumu

- **36 Kritik SQL:** Dinamik tablo/kolon adları `BaseModel` içinde beyaz liste, biçim kontrolü ve backtick ile sınırlandırılıyor; değerler parametreli. İncelenen bulgular gerçek SQL enjeksiyonu değil.
- **15 Yüksek XSS:** Çoğu `(int)` cast edilmiş kimlik/sayaç veya `e()` ile kaçırılmış çıktı. `page.php` içeriği HTMLPurifier’dan geçiyor. Doğrulanmış XSS bulunmadı.
- **2 Orta `file_put_contents`:** `Logger` ve `MailService` içindeki log/e-posta dosyası yazımı; yollar kullanıcı girdisiyle belirlenmiyor. Scanner’ın 17.1 kuralı bu kullanımları da işaretliyor.
- **92 Düşük CSP:** E-posta şablonlarındaki satır içi stiller. E-postalarda sayfa CSP’si uygulanmıyor; işlevsel gereklilik.
- Bu düzeltme tam bir manuel pentest yerine geçmez; `docs/pentest-checklist.md` uygulanmalıdır.
