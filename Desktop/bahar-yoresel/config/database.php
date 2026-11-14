<?php
/**
 * config/database.php
 * Bahar Yöresel - Veritabanı Bağlantı Dosyası
 * PDO kullanılarak MySQL bağlantısı kurulur.
 */

// Hata raporlamayı geliştirme aşamasında açık tutmak için (canlıya alırken kapatılabilir)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'bahar.yoresel');
define('DB_USER', 'root');
define('DB_PASS', 'CompScience10#');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $db = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Global erişim için (fonksiyon içinden de kullanılabilmesi amacıyla)
    $GLOBALS['db'] = $db;

} catch (PDOException $e) {
    // Üretim ortamında hassas bilgi göstermemek için log tutmak daha güvenlidir
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}