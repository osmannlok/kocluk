<?php
// config/paytr.php

declare(strict_types=1);

/*
 * PayTR iFrame API ayarları.
 * Gizli anahtarlar (merchant_key, merchant_salt) yalnızca .env'den okunur; hiçbir yerde loglanmaz.
 */

$appUrl = rtrim((string) env('APP_URL', ''), '/');

return [
    // API entegrasyon bilgileri (PayTR mağaza paneli > BİLGİ sayfası)
    'merchant_id'   => (string) env('PAYTR_MERCHANT_ID', ''),
    'merchant_key'  => (string) env('PAYTR_MERCHANT_KEY', ''),
    'merchant_salt' => (string) env('PAYTR_MERCHANT_SALT', ''),

    // 0/1: canlı mağazada test işlemi için 1
    'test_mode'     => (int) env('PAYTR_TEST_MODE', 0) === 1 ? 1 : 0,

    // 0/1: entegrasyon sürecinde 1, sonrasında 0
    'debug_on'      => (int) env('PAYTR_DEBUG_ON', 0) === 1 ? 1 : 0,

    // İşlem zaman aşımı (dakika)
    'timeout_limit' => max(1, (int) env('PAYTR_TIMEOUT_LIMIT', 25)),

    // Taksit: 1 = yalnızca tek çekim (bildirimdeki total_amount tutar kontrolü için güvenli varsayılan)
    'no_installment' => (int) env('PAYTR_NO_INSTALLMENT', 1) === 0 ? 0 : 1,

    // 0 = yürürlükteki en yüksek taksit
    'max_installment' => max(0, (int) env('PAYTR_MAX_INSTALLMENT', 0)),

    'currency'      => 'TL',

    // YALNIZCA APP_ENV=local iken kullanılır: yerel makinede 127.0.0.1 gönderilirse
    // PayTR "geçersiz paytr_token" döner, bu yüzden dış IP buraya yazılır.
    // Üretimde güvenlik için değer her zaman boş döner (override yok sayılır).
    'user_ip_override' => ((string) env('APP_ENV', 'production') === 'local')
        ? trim((string) env('PAYTR_USER_IP', ''))
        : '',

    // PayTR uç noktaları (iframe_ornek.php)
    'api_url'         => 'https://www.paytr.com/odeme/api/get-token',
    'iframe_base_url' => 'https://www.paytr.com/odeme/guvenli/',

    // Müşteri yönlendirme adresleri; PaymentController sonuna /{orderNumber} ekler.
    // DİKKAT: Bu adresler yalnızca müşteri yönlendirmesidir, sipariş durumunu DEĞİŞTİRMEZ.
    // Sipariş onayı/iptali yalnızca bildirim URL'sinde (/odeme/paytr/bildirim) yapılır.
    'ok_url'   => $appUrl . '/odeme/basarili',
    'fail_url' => $appUrl . '/odeme/basarisiz',
];