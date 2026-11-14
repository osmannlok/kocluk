<?php
/**
 * includes/admin-header.php
 * Bahar Yöresel - Admin Panel Üst Kısmı (Head + Sidebar/Navbar)
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Giriş yapılmamışsa login sayfasına yönlendir
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Bahar Yöresel</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Admin özel CSS -->
    <link href="/admin/assets/css/admin-style.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
        }
        .admin-sidebar {
            min-height: 100vh;
            background-color: #212529;
        }
        .admin-sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: #fff;
            background-color: #343a40;
            border-radius: 0.375rem;
        }
        .admin-content {
            padding: 2rem;
        }
    </style>
</head>
<body>

<div class="d-flex">

    <!-- SIDEBAR -->
    <nav class="admin-sidebar d-flex flex-column p-3" style="width: 250px;">
        <a href="/admin/dashboard.php" class="d-flex align-items-center mb-4 text-white text-decoration-none">
            <i class="bi bi-flower1 fs-4 me-2"></i>
            <span class="fs-5 fw-bold">Bahar Yöresel</span>
        </a>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/admin/dashboard.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? ' active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Panel
                </a>
            </li>
            <li>
                <a href="/admin/products.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? ' active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i> Ürünler
                </a>
            </li>
            <li>
                <a href="/admin/campaigns.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'campaigns.php') ? ' active' : ''; ?>">
                    <i class="bi bi-megaphone me-2"></i> Kampanyalar
                </a>
            </li>
            <li>
                <a href="/admin/announcements.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? ' active' : ''; ?>">
                    <i class="bi bi-bell me-2"></i> Duyurular
                </a>
            </li>
        </ul>

        <hr class="text-secondary">

        <div class="dropdown">
            <a href="/admin/logout.php" class="d-flex align-items-center text-decoration-none text-danger">
                <i class="bi bi-box-arrow-left me-2"></i>
                Çıkış Yap
            </a>
        </div>
    </nav>
    <!-- /SIDEBAR -->

    <!-- CONTENT WRAPPER -->
    <div class="flex-grow-1">
        <div class="admin-content">