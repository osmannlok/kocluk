# Görev 1: Laravel Kurulumu ve Kullanıcı Girişi

## Kapsam

- Laravel 12 projesi oluştur
- MySQL bağlantısını ayarla
- Laravel Breeze ile giriş/kayıt sistemini kur
- users tablosuna role alanı ekle
- Varsayılan yönetici oluştur

## Yapılacaklar

1. `laravel new market-stok-satis`
2. `.env` ayarlarını güncelle
3. `php artisan key:generate`
4. Laravel Breeze kur
5. `add_role_to_users_table` migration’ını oluştur
6. `AdminUserSeeder` oluştur
7. Migration ve seeder’ı çalıştır
8. Uygulamayı test et

## Yapılmayacaklar

- Ürün, barkod, satış, stok veya rapor modülü oluşturma
- Gereksiz paket kurma

## Kabul Kriterleri

- `http://127.0.0.1:8000/login` çalışmalı
- Yönetici ile giriş yapılabilmeli
- `users.role` alanı oluşmalı
