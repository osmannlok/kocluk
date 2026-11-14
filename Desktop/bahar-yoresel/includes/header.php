<?php
/**
 * includes/header.php
 * Aşiroğlu Yöresel - Site Üst Kısmı (Head + Navbar)
 */

session_start();
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Aşiroğlu Yöresel - Kahramanmaraş yöresel ürünleri online satış sitesi">
    <title>Aşiroğlu Yöresel | Kahramanmaraş Yöresel Ürünler</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (opsiyonel, navbar ikonları için) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Site özel CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            <img src="/assets/img/logo.png" alt="Aşiroğlu Yöresel Logo" height="40" class="d-inline-block align-text-top me-2">
            Aşiroğlu Yöresel
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#anaMenu"
                aria-controls="anaMenu" aria-expanded="false" aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="anaMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 text-center">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php">Ana Sayfa</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/products.php">Ürünler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/campaigns.php">Kampanyalar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/announcements.php">Duyurular</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/about.php">Hakkımızda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/contact.php">İletişim</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- /NAVBAR -->

<main>