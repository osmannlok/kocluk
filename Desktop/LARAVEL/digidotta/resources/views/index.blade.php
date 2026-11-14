@extends('base')

@section('title', 'Digidotta - Ana Sayfa')

@section('og_title', 'Digidotta - Ana Sayfa')
@section('twitter_title', 'Digidotta - Ana Sayfa')

@section('content')
    <!-- Hero Bölümü -->
    <div class="container my-5 text-center">
        <h1 class="display-4">Digidotta'ya Hoş Geldiniz!</h1>
        <p class="lead">Sosyal medya gönderileri ve web tasarımı konusunda uzman dijital ajansınız.</p>
        <a href="{{ route('services') }}" class="btn btn-primary">Hizmetlerimizi Keşfet</a>
        <!-- Not: 'hom' rotası hatalı görünüyor, doğru rota belirlenmeli -->
        <a href="{{ route('index') }}" class="btn btn-primary">Hom Keşfet</a>
    </div>

    <!-- Hizmetler Özeti -->
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Sosyal Medya Yönetimi</h5>
                        <p class="card-text">Markanız için yaratıcı içerikler üretiyoruz.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Web Sitesi Tasarımı</h5>
                        <p class="card-text">Modern ve kullanıcı dostu web siteleri.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Grafik Tasarım</h5>
                        <p class="card-text">Göz alıcı görsellerle markanızı öne çıkarın.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection