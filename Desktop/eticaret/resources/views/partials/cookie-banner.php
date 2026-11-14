<?php
declare(strict_types=1);

// resources/views/partials/cookie-banner.php
// Gizli başlayan çerez çubuğu iskeleti; cookie-consent.js tercih yoksa görünür yapar.
?>
<div id="ymCookieBanner" class="ym-cookie" role="region" aria-label="Çerez tercihleri" hidden>
    <p class="mb-0">
        Sitemizin çalışması için zorunlu çerezler kullanılır. Deneyiminizi iyileştirmek için isteğe bağlı çerezleri de kabul edebilirsiniz.
        Ayrıntılar için <a href="<?= e(url('/sayfa/cerez')) ?>">Çerez Politikası</a>'nı inceleyin.
    </p>
    <div class="ym-cookie__actions">
        <button type="button" class="btn btn-accent btn-sm" data-consent="all">Tümünü kabul et</button>
        <button type="button" class="btn btn-outline-light btn-sm" data-consent="necessary">Yalnızca zorunlu</button>
    </div>
</div>
