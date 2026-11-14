<?php // config/security.php
declare(strict_types=1);

return [
    // Content-Security-Policy: direktif => izinli kaynaklar. Satır içi betik/stil YOK.
    'csp' => [
        // Aksi belirtilmedikçe yalnızca kendi kaynağımız
        'default-src' => ["'self'"],
        // Betik: kendi dosyalarımız + PayTR iFrame yardımcı betiği
        'script-src' => ["'self'", 'https://www.paytr.com'],
        // Stil: yalnızca kendi CSS dosyalarımız (style="" yasak)
        'style-src' => ["'self'"],
        // Görsel: kendi görsellerimiz + data: (küçük SVG/önizleme)
        'img-src' => ["'self'", 'data:'],
        // Yazı tipi: yerel dosyalar (dış CDN yok)
        'font-src' => ["'self'"],
        // iFrame: yalnızca PayTR ödeme çerçevesi
        'frame-src' => ["'self'", 'https://www.paytr.com'],
        // fetch/XHR: yalnızca kendi sunucumuz
        'connect-src' => ["'self'"],
        // Formlar yalnızca kendi sitemize gönderilebilir (veri sızdırma formlarını engeller)
        'form-action' => ["'self'"],
        // Site başka sitelerin iFrame'ine gömülemez (clickjacking)
        'frame-ancestors' => ["'self'"],
        // <base> etiketiyle göreli bağlantı kaçırma engellenir
        'base-uri' => ["'self'"],
        // Flash/plugin nesneleri tamamen kapalı
        'object-src' => ["'none'"],
    ],

    // CSRF dışı tutulan TAM yollar. Yalnızca hash doğrulamalı PayTR bildirimi (sunucudan sunucuya).
    'csrf_except' => ['/odeme/paytr/bildirim'],

    // HSTS yalnızca üretimde (yerel HTTP geliştirmeyi kilitlemesin)
    'hsts' => (string) env('APP_ENV', 'production') === 'production',
];