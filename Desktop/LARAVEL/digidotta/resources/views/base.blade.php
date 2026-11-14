<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Digidotta - Dijital Ajans')</title>
    <meta name="description" content="Digidotta, web tasarımı, sosyal medya yönetimi, SEO optimizasyonu, grafik tasarım, dijital reklam ve içerik üretimi ile markanızı dijitalde güçlendirir.">
    <meta name="keywords" content="dijital ajans, web tasarımı, sosyal medya, SEO optimizasyonu, grafik tasarım, dijital reklam, içerik üretimi">
    <meta name="author" content="Digidotta">

    <!-- Open Graph -->
    <meta property="og:title" content="@yield('og_title', 'Digidotta - Dijital Ajans')">
    <meta property="og:description" content="Markanızı dijitalde büyütmek için yenilikçi çözümler sunuyoruz. Web tasarımı, SEO, sosyal medya ve daha fazlası!">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('https://www.digidotta.com' . route('index')) }}">
    <meta property="og:image" content="{{ asset('main/assets/img/logo.png') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', 'Digidotta - Dijital Ajans')">
    <meta name="twitter:description" content="Dijital dünyada markanızı güçlendirmek için buradayız. Hemen iletişime geçin!">
    <meta name="twitter:image" content="{{ asset('main/assets/img/logo.png') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('main/assets/img/favicon.png') }}">

    <!-- Vite ile CSS ve JS dosyalarını yükleme -->
    @vite(['resources/sass/app.scss', 'resources/js/main.js'])
    <link rel="stylesheet" href="{{ asset('resources/css/style.css') }}">
    <script src="{{ asset('resources/js/main.js') }}"></script>

    <!-- Vendor CSS -->
    <link href="{{ asset('main/assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('main/assets/vendor/aos/aos.css') }}" rel="stylesheet">
    <link href="{{ asset('main/assets/css/main.css') }}" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header id="header" class="header d-flex align-items-center fixed-top">
        <div class="container-fluid container-xl d-flex align-items-center justify-content-between">
            <a href="{{ route('index') }}" class="logo d-flex align-items-center">
                <img src="{{ asset('main/assets/img/logo.png') }}" alt="Digidotta Logo">
                <h1 class="sitename">Digidotta</h1>
            </a>
            <nav id="navmenu" class="navmenu">
                <ul>
                    <li><a href="{{ route('index') }}" class="{{ request()->routeIs('index') ? 'active' : '' }}">Ana Sayfa</a></li>
                    <li><a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">Hakkımızda</a></li>
                    <li><a href="{{ route('services') }}" class="{{ request()->routeIs('services') ? 'active' : '' }}">Hizmetler</a></li>
                    <li><a href="#features">Özellikler</a></li>
                    <li><a href="#pricing">Fiyatlandırma</a></li>
                    <li><a href="#faq">SSS</a></li>
                    <li><a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'active' : '' }}">İletişim</a></li>
                    <li><a href="{{ route('blog') }}" class="{{ request()->routeIs('blog') ? 'active' : '' }}">Blog</a></li>
                </ul>
                <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
            </nav>
        </div>
    </header><!-- End Header -->

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer id="footer" class="footer position-relative light-background">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-info">
                        <a href="{{ route('index') }}" class="logo d-flex align-items-center">
                            <span>Digidotta</span>
                        </a>
                        <p>Dijital dünyada markanızı güçlendirmek için yenilikçi çözümler sunuyoruz.</p>
                        <div class="social-links d-flex mt-4">
                            <a href="#" class="twitter"><i class="bi bi-twitter-x"></i></a>
                            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 footer-links">
                    <h4>Hızlı Bağlantılar</h4>
                    <ul>
                        <li><a href="{{ route('index') }}">Ana Sayfa</a></li>
                        <li><a href="{{ route('about') }}">Hakkımızda</a></li>
                        <li><a href="{{ route('services') }}">Hizmetler</a></li>
                        <li><a href="{{ route('contact') }}">İletişim</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 footer-links">
                    <h4>Hizmetlerimiz</h4>
                    <ul>
                        <li><a href="{{ route('services') }}">Web Tasarımı</a></li>
                        <li><a href="{{ route('services') }}">Sosyal Medya</a></li>
                        <li><a href="{{ route('services') }}">SEO Optimizasyonu</a></li>
                        <li><a href="{{ route('services') }}">Grafik Tasarım</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 footer-contact">
                    <h4>Bize Ulaşın</h4>
                    <p>
                        A108 Adam Sokağı<br>
                        İstanbul, Türkiye<br><br>
                        <strong>Telefon:</strong> +90 123 456 78 90<br>
                        <strong>E-posta:</strong> info@digidotta.com<br>
                    </p>
                </div>
            </div>
        </div>
        <div class="container copyright text-center mt-4">
            <p>&copy; <span>2025 Digidotta</span> Tüm Hakları Saklıdır</p>
        </div>
    </footer><!-- End Footer -->

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS -->
    <script src="{{ asset('main/assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('main/assets/vendor/aos/aos.js') }}"></script>
</body>
</html>