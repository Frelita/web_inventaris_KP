<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_admin = isset($_SESSION['login']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Inventaris - PLN Icon Plus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            color: #333;
        }
        .navbar-pln {
            background: linear-gradient(135deg, #0d3b66 0%, #00a8e8 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .navbar-brand-logo {
            height: 40px;
            object-fit: contain;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
        }
        .card-table {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .btn-pln {
            background-color: #00a8e8;
            color: #fff;
            border: none;
            font-weight: 600;
        }
        .btn-pln:hover {
            background-color: #0086ba;
            color: #fff;
        }
        .bg-pln-gradient {
            background: linear-gradient(135deg, #0d3b66 0%, #0077b6 100%);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-pln sticky-top py-2">
    <div class="container">
        <!-- LOGO & NAMA PERUSAHAAN -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php">
            <img src="assets/img/logo.png" alt="PLN Icon Plus" class="navbar-brand-logo bg-white p-1 rounded" onerror="this.onerror=null; this.src='https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png';">
            <span class="fs-5 text-white ms-1">INVENTARIS <small class="fw-light fs-6 opacity-75">| ICONPLUS</small></span>
        </a>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if($is_admin): ?>
                <div class="text-white me-2 d-none d-lg-block text-end">
                    <small class="d-block text-white-50" style="font-size: 0.75rem;">Logged in as</small>
                    <span class="fw-bold"><i class="fa-solid fa-user-shield me-1"></i> <?= $_SESSION['user'] ?? 'Admin Divisi'; ?></span>
                </div>
                <!-- NAVIGASI ADMIN -->
                <a href="index.php" class="btn btn-sm btn-outline-light fw-bold px-3 rounded-pill">
                    <i class="fa-solid fa-box me-1"></i> Data Barang
                </a>
                <a href="riwayat.php" class="btn btn-sm btn-light fw-bold text-dark px-3 rounded-pill shadow-sm">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat
                </a>
                <a href="cetak_qr.php" class="btn btn-sm btn-light fw-bold text-dark px-3 rounded-pill shadow-sm">
                    <i class="fa-solid fa-print me-1"></i> Cetak QR
                </a>
                <a href="logout.php" class="btn btn-sm btn-danger fw-bold px-3 rounded-pill shadow-sm">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-warning text-dark fw-bold px-3 rounded-pill shadow-sm">
                    <i class="fa-solid fa-lock me-1"></i> Login Admin
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container py-4">
