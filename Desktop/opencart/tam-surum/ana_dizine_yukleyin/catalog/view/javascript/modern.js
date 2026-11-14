(function () {
  function onReady(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function () {
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.classList.add('has-modern-theme');

    // Lenis smooth scroll
    var lenis = null;
    if (!prefersReduced && window.Lenis) {
      lenis = new Lenis({
        duration: 1.1,
        smoothWheel: true,
        anchors: true
      });

      function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
      }
      requestAnimationFrame(raf);

      if (window.ScrollTrigger) {
        lenis.on('scroll', ScrollTrigger.update);
      }
    }

    // GSAP animations
    if (!prefersReduced && window.gsap) {
      var gsap = window.gsap;
      if (window.ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);
      }

      // Hero / top content reveal
      var heroTargets = document.querySelectorAll('#common-home #content > *:first-child, #logo, #search, #header-cart, #menu');
      if (heroTargets.length) {
        gsap.from(heroTargets, {
          opacity: 0,
          y: 24,
          duration: 0.8,
          stagger: 0.08,
          ease: 'power2.out',
          clearProps: 'transform'
        });
      }

      // Scroll reveals
      if (window.ScrollTrigger) {
        var revealSets = [
          { sel: '.product-thumb', y: 30, stagger: 0.06 },
          { sel: '#content h1, #content h2, #content h3', y: 20, stagger: 0.05 },
          { sel: 'footer .row > div', y: 20, stagger: 0.08 },
          { sel: '.breadcrumb, #product-info .row > div, .card, .list-group, .table', y: 18, stagger: 0.04 }
        ];

        revealSets.forEach(function (cfg) {
          var els = gsap.utils.toArray(cfg.sel).filter(function (el) {
            return !el.closest('#menu') && !el.closest('#top');
          });
          if (!els.length) return;
          gsap.from(els, {
            opacity: 0,
            y: cfg.y,
            duration: 0.7,
            stagger: cfg.stagger,
            ease: 'power2.out',
            clearProps: 'transform',
            scrollTrigger: {
              trigger: els[0],
              start: 'top 88%',
              once: true
            }
          });
        });

        ScrollTrigger.refresh();
      }

      // Subtle hover lift for cards/products
      var hoverEls = document.querySelectorAll('.product-thumb, .card, .category-card');
      hoverEls.forEach(function (el) {
        el.addEventListener('mouseenter', function () {
          gsap.to(el, { y: -6, duration: 0.25, ease: 'power2.out' });
        });
        el.addEventListener('mouseleave', function () {
          gsap.to(el, { y: 0, duration: 0.3, ease: 'power2.out' });
        });
      });
    }

    // Header scroll state
    var topBar = document.querySelector('header');
    function onScroll() {
      var y = window.scrollY || document.documentElement.scrollTop;
      if (y > 10) document.body.classList.add('is-scrolled');
      else document.body.classList.remove('is-scrolled');
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  });
})();
