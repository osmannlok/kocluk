(function () {
    'use strict';

    var toggle = document.querySelector('.mobile-menu-toggle');
    var nav = document.querySelector('.main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            nav.classList.toggle('is-open');
            toggle.classList.toggle('is-active');
        });
    }
})();