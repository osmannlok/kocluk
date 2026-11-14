<?php
// config/carriers.php
declare(strict_types=1);

/*
 * Kargo firmaları listesi.
 * Yapı: 'kod' => ['name' => görünen ad, 'tracking_url' => ?string]
 *
 * - shipments.carrier sütununa firmanın GÖRÜNEN ADI (name) yazılır, kod değil;
 *   böylece e-posta ve arayüz bu dosyaya bağımlı kalmaz.
 * - tracking_url yalnızca sunucuda bu şablondan üretilir, yönetici elle URL giremez.
 *   Şablondaki {tracking} yer tutucusu, takip numarası rawurlencode edilerek doldurulur.
 * - Üretilen URL https:// ile başlamıyorsa kaydedilmez (null).
 * - Takip numarası doğrulaması: ^[A-Za-z0-9-]{6,40}$
 * - Doğrulanmamış şablonlar null bırakılmıştır; doğruladıktan sonra doldurun.
 */
return [
    'yurtici' => [
        'name' => 'Yurtiçi Kargo',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'aras' => [
        'name' => 'Aras Kargo',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'mng' => [
        'name' => 'MNG Kargo',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'ptt' => [
        'name' => 'PTT Kargo',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'surat' => [
        'name' => 'Sürat Kargo',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'ups' => [
        'name' => 'UPS',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'hepsijet' => [
        'name' => 'HepsiJET',
        'tracking_url' => null, // DOĞRULANMADI
    ],
    'other' => [
        'name' => 'Diğer',
        'tracking_url' => null, // Takip bağlantısı yok; yalnızca takip numarası gösterilir
    ],
];