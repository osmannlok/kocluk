// public/assets/js/paytr-frame.js
// PayTR iFrame yüksekliğini otomatik ayarlar (iFrameResizer). Satır içi betik yok (CSP).
(function () {
    'use strict';

    var frame = document.getElementById('paytriframe');
    if (!frame) {
        return;
    }

    // iFrameResizer dosyası PayTR alan adından yüklenir; CSP script-src'de izinli olmalıdır.
    var RESIZER_URL = 'https://www.paytr.com/js/iframeResizer.min.js';

    function init() {
        if (typeof window.iFrameResize === 'function') {
            window.iFrameResize({}, '#paytriframe');
        }
    }

    if (typeof window.iFrameResize === 'function') {
        init();
        return;
    }

    var script = document.createElement('script');
    script.src = RESIZER_URL;
    script.async = true;
    script.addEventListener('load', init);
    script.addEventListener('error', function () {
        if (window.App && typeof window.App.toast === 'function') {
            window.App.toast('Ödeme ekranı yüklenemedi. Lütfen sayfayı yenileyin.', 'error');
        }
    });
    document.head.appendChild(script);
})();