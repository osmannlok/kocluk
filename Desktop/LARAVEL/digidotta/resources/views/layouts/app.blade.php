<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Digidotta')</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    @include('partials.navbar') {{-- Menü --}}
    <div class="container mt-4">
        @yield('content') {{-- Sayfa içeriği --}}
    </div>
</body>
</html>
