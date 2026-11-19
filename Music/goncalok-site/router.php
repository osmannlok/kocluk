<?php
// ============================================================
// GONCA LÖK — Yerel geliştirme yönlendiricisi
// Kullanım: php -S localhost:8000 router.php
// Yerleşik PHP sunucusu .htaccess'i İŞLEMEZ; bu dosya hassas
// yollara 404 döndürür, diğer her şeyi normal sunar.
// ============================================================
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$blocked = [
    '#^/\.#',                 // .env, .git, .htaccess, .env.example, .gitignore...
    '#^/database(/|$)#i',
    '#^/storage(/|$)#i',
    '#^/bin(/|$)#i',
    '#^/migrate\.php$#i',
    '#^/uploads(/|$)#i',      // yükleme klasörü çalıştırılamaz, dosya sunulmaz
    '#\.(sql|log|ini|sh)$#i',
];
foreach ($blocked as $pattern) {
    if (preg_match($pattern, $path) === 1) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Bulunamadı';
        return true;
    }
}
return false;
