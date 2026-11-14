# Market Stok/Satış Sistemi

Kahramanmaraş’taki yöresel market için Laravel + MySQL tabanlı barkodlu stok ve satış yönetim sistemi.

## Özellikler

- Barkodlu ürün satışı
- Stok takibi
- Ürün, kategori ve tedarikçi yönetimi
- Satış, iade ve fire kayıtları
- Günlük ciro ve kâr raporları
- Kritik stok uyarısı
- Müşteri ve veresiye takibi
- Kullanıcı yetkilendirme

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Tarayıcıdan:

```text
http://127.0.0.1:8000
```

## Varsayılan Yönetici

```text
E-posta: admin@market.local
Şifre: password
Rol: admin
```

## Proje Dokümanları

- `CLAUDE.md`: Claude Code çalışma kuralları
- `DATABASE_SCHEMA.md`: Veritabanı şeması
- `BUSINESS_RULES.md`: İşletme kuralları
- `UI_GUIDELINES.md`: Arayüz kuralları
- `API_CONTRACT.md`: API sözleşmesi
- `prompts/`: Claude Code görev promptları
- `docs/`: Ekran akışları, örnek veri ve test senaryoları
