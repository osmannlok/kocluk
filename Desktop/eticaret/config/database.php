<?php // config/database.php
declare(strict_types=1);

// Veritabanı ayarları. Parola (string) olarak zorlanır: .env'deki sayısal parola int'e dönüşse bile bozulmaz.
return [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_NAME', 'yoresel_market'),
    'username' => (string) env('DB_USER', 'ym_dev'),
    'password' => (string) env('DB_PASS', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    'options' => [
        // Hatalar istisna fırlatır (sessiz hata yok)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // SQL injection'a karşı gerçek prepared statement
        PDO::ATTR_EMULATE_PREPARES => false,
        // Sayılar tip kaybetmeden döner (DECIMAL yine string gelir: para için istenen davranış)
        PDO::ATTR_STRINGIFY_FETCHES => false,
        // Bağlantı açılışında: saat dilimi ve karşılaştırma kuralı
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00', collation_connection = 'utf8mb4_unicode_ci'",
    ],
];