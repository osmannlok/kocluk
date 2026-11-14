/**
* Template Name: eStartup
* Template URL: https://bootstrapmade.com/estartup-bootstrap-landing-page-template/
* Updated: Aug 07 2024 with Bootstrap v5.3.3
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

  function mobileNavToogle() {
    document.querySelector('body').classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  }
  mobileNavToggleBtn.addEventListener('click', mobileNavToogle);

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', () => {
      if (document.querySelector('.mobile-nav-active')) {
        mobileNavToogle();
      }
    });

  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(navmenu => {
    navmenu.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentNode.classList.toggle('active');
      this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    AOS.init({
      duration: 600,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    });
  }
  window.addEventListener('load', aosInit);

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: '.glightbox'
  });

  /**
   * Frequently Asked Questions Toggle
   */
  document.querySelectorAll('.faq-item h3, .faq-item .faq-toggle').forEach((faqItem) => {
    faqItem.addEventListener('click', () => {
      faqItem.parentNode.classList.toggle('faq-active');
    });
  });

})();



document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contact-form');
    if (!contactForm) {
        console.error('Hata: contact-form ID\'si bulunamadı. HTML\'de id="contact-form" olduğundan emin olun.');
        return;
    }

    const csrfToken = document.querySelector('[name=csrfmiddlewaretoken]');
    if (!csrfToken) {
        console.error('Hata: CSRF token bulunamadı. HTML\'de {% csrf_token %} olduğundan emin olun.');
        return;
    }

    contactForm.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();

        const form = this;
        const formData = new FormData(form);
        const loading = form.querySelector('.loading');
        const errorMessage = form.querySelector('.error-message');
        const sentMessage = form.querySelector('.sent-message');

        if (loading) loading.classList.add('d-block');
        if (errorMessage) errorMessage.classList.remove('d-block');
        if (sentMessage) sentMessage.classList.remove('d-block');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRFToken': csrfToken.value
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP hatası: ${response.status} ${response.statusText}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`JSON parse hatası: ${text}`);
                }
            });
        })
        .then(data => {
            if (loading) loading.classList.remove('d-block');
            if (data.success) {
                if (sentMessage) {
                    sentMessage.classList.add('d-block');
                    form.reset();
                } else {
                    console.error('Hata: sent-message div bulunamadı. HTML\'de .sent-message sınıfını kontrol edin.');
                }
            } else {
                if (errorMessage) {
                    errorMessage.textContent = data.message || 'Bir hata oluştu.';
                    errorMessage.classList.add('d-block');
                } else {
                    console.error('Hata: error-message div bulunamadı. HTML\'de .error-message sınıfını kontrol edin.');
                }
            }
        })
        .catch(error => {
            if (loading) loading.classList.remove('d-block');
            if (errorMessage) {
                errorMessage.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                errorMessage.classList.add('d-block');
            }
            console.error('AJAX hatası:', error.message);
        });
    });
});



// document.addEventListener('DOMContentLoaded', function() {
//     const contactForm = document.getElementById('contact-form');
//     if (contactForm) {
//         contactForm.addEventListener('submit', function(event) {
//             event.preventDefault();
//             const form = this;
//             const formData = new FormData(form);
//             const loading = form.querySelector('.loading');
//             const errorMessage = form.querySelector('.error-message');
//             const sentMessage = form.querySelector('.sent-message');

//             // Mesaj alanlarını sıfırla
//             if (loading) loading.classList.add('d-block');
//             if (errorMessage) errorMessage.classList.remove('d-block');
//             if (sentMessage) sentMessage.classList.remove('d-block');

//             fetch(form.action, {
//                 method: 'POST',
//                 body: formData,
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest',
//                     'X-CSRFToken': document.querySelector('[name=csrfmiddlewaretoken]').value
//                 }
//             })
//             .then(response => {
//                 if (!response.ok) {
//                     throw new Error('Network response was not ok');
//                 }
//                 return response.json();
//             })
//             .then(data => {
//                 if (loading) loading.classList.remove('d-block');
//                 if (data.success) {
//                     if (sentMessage) {
//                         sentMessage.classList.add('d-block');
//                         form.reset();
//                     } else {
//                         console.error('sent-message div not found');
//                     }
//                 } else {
//                     if (errorMessage) {
//                         errorMessage.textContent = data.message || 'Bir hata oluştu.';
//                         errorMessage.classList.add('d-block');
//                     } else {
//                         console.error('error-message div not found');
//                     }
//                 }
//             })
//             .catch(error => {
//                 if (loading) loading.classList.remove('d-block');
//                 if (errorMessage) {
//                     errorMessage.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
//                     errorMessage.classList.add('d-block');
//                 }
//                 console.error('Fetch error:', error);
//             });
//         });
//     } else {
//         console.error('contact-form not found');
//     }
// });