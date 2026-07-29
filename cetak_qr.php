<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    echo "<script>alert('Anda harus Login terlebih dahulu!'); window.location='login.php';</script>";
    exit;
}

// Config URL Target QR Code
$folder_web = "INVENTARISS"; 
$base_url   = "http://192.168.61.155/" . $folder_web . "/detail.php?kode=";

// 1. TANGKAP INPUT FILTER DARI URL
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$search_filter   = isset($_GET['search']) ? trim($_GET['search']) : '';

// 2. QUERY DAFTAR KATEGORI UNTUK DROPDOWN FILTER
$q_kategori = mysqli_query($conn, "SELECT DISTINCT kategori FROM barang WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC");

// 3. SUSUN QUERY BARANG BERDASARKAN FILTER (PREPARED STATEMENT)
$where_conditions = [];
$params           = [];
$types            = "";

if (!empty($kategori_filter)) {
    $where_conditions[] = "kategori = ?";
    $params[]           = $kategori_filter;
    $types             .= "s";
}

if (!empty($search_filter)) {
    $where_conditions[] = "(nama_barang LIKE ? OR kode_barang LIKE ? OR nomor_seri LIKE ?)";
    $search_param       = "%" . $search_filter . "%";
    $params[]           = $search_param;
    $params[]           = $search_param;
    $params[]           = $search_param;
    $types             .= "sss";
}

$sql_barang = "SELECT * FROM barang";
if (count($where_conditions) > 0) {
    $sql_barang .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql_barang .= " ORDER BY id_barang DESC";

$stmt = mysqli_prepare($conn, $sql_barang);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$query      = mysqli_stmt_get_result($stmt);
$total_data = $query ? mysqli_num_rows($query) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak QR Code — PLN ICONPLUS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- QRCode JS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root { --sidebar-width: 260px; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* BACKGROUND DASHBOARD GRADASI GELAP */
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%),
                        url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat fixed;
            color: #0f172a;
        }

        /* SIDEBAR STYLING */
        #sidebar {
            width: var(--sidebar-width); 
            height: 100vh; 
            position: fixed; 
            top: 0; 
            left: 0;
            background: #0f172a; 
            color: #fff; 
            transition: all 0.3s ease; 
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        #sidebar .sidebar-header { 
            padding: 24px 20px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.08); 
        }
        #sidebar ul.components { padding: 20px 0; }
        #sidebar ul li a {
            padding: 13px 24px; 
            font-size: 0.92rem; 
            display: flex; 
            align-items: center;
            color: #94a3b8; 
            text-decoration: none; 
            font-weight: 600; 
            border-left: 4px solid transparent;
        }
        #sidebar ul li a:hover, #sidebar ul li.active a {
            color: #fff; 
            background: rgba(255, 255, 255, 0.1); 
            border-left-color: #38bdf8;
        }
        #sidebar ul li a i { margin-right: 14px; font-size: 1.1rem; width: 20px; text-align: center; }
        
        #content { margin-left: var(--sidebar-width); padding: 30px; min-height: 100vh; }
        
        /* HERO BANNER HEADER */
        .hero-banner {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 50%, #0f172a 100%);
            border-radius: 16px;
            padding: 32px 36px;
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .hero-banner h2 {
            font-weight: 800;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .hero-banner p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.98rem;
            font-weight: 500;
        }

        /* CARD GLASS UNTUK FILTER & ITEM QR */
        .card-glass {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .form-control-custom, .form-select-custom {
            border-radius: 10px; 
            border: 1px solid #cbd5e1; 
            padding: 9px 14px; 
            font-size: 0.88rem; 
            background-color: #f8fafc;
            color: #0f172a !important;
            font-weight: 600;
        }

        /* CARD LABEL QR CODE */
        .qr-card { 
            background: #ffffff; 
            border: 2px dashed #cbd5e1; 
            border-radius: 16px; 
            padding: 18px; 
            text-align: center; 
            transition: all 0.2s ease;
            height: 100%;
        }
        .qr-card:hover {
            border-color: #0284c7;
            transform: translateY(-2px);
        }
        .qr-container { display: flex; justify-content: center; margin: 14px 0; }
        .qr-container img { margin: 0 auto; display: block; }

        .badge-kode {
            background-color: #f8fafc; 
            color: #1e293b !important; 
            font-family: monospace; 
            font-size: 0.78rem;
            padding: 4px 8px; 
            border-radius: 6px; 
            border: 1px solid #cbd5e1; 
            font-weight: 700;
        }

        /* DOKUMEN CETAK (PRINT) */
        .print-header { display: none; }
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }

            #sidebar, 
            .btn-print-hide, 
            .filter-card-hide,
            .hero-banner { 
                display: none !important; 
            }

            body { 
                background: #ffffff !important; 
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            #content { 
                margin-left: 0 !important; 
                padding: 0 !important; 
                width: 100% !important;
            }

            .print-header {
                display: block !important;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }

            .qr-card { 
                border: 1px dashed #000 !important; 
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }

        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #content { margin-left: 0; }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png" height="34" class="me-3" alt="PLN Logo">
        <div>
            <h6 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px;">INVENTARIS</h6>
            <small class="text-info font-monospace" style="font-size: 0.75rem;">ICONPLUS</small>
        </div>
    </div>
    <ul class="list-unstyled components">
        <li><a href="index.php"><i class="fa-solid fa-boxes-stacked"></i> Data Barang</a></li>
        <li><a href="riwayat.php"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</a></li>
        <li class="active"><a href="cetak_qr.php"><i class="fa-solid fa-qrcode"></i> Cetak QR Code</a></li>
    </ul>
</nav>

<div id="content">
    <!-- Header khusus saat di-print -->
    <div class="print-header">
        <h4 class="fw-bold text-uppercase mb-1">PT PLN ICON PLUS</h4>
        <h5 class="fw-semibold text-secondary mb-0">LABEL QR CODE ASET / BARANG INVENTARIS</h5>
        <small class="text-muted">Dicetak pada: <?= date('d-m-Y H:i:s'); ?></small>
    </div>

    <!-- HERO BANNER HEADER -->
    <div class="hero-banner d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 btn-print-hide">
        <div>
            <h2>Cetak Label QR Code</h2>
            <p class="mb-0">Scan QR Code menggunakan kamera HP untuk melihat detail aset</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-3 shadow-sm">
                <i class="fa-solid fa-print me-2"></i> 
                <?= (!empty($kategori_filter) || !empty($search_filter)) ? "Cetak Hasil Filter ($total_data)" : "Cetak Semua Label"; ?>
            </button>
        </div>
    </div>

    <!-- FILTER CARD (PENCARIAN & KATEGORI) -->
    <div class="card card-glass p-4 mb-4 filter-card-hide">
        <form method="GET" action="cetak_qr.php" class="row g-3 align-items-end">
            
            <!-- SEARCH INPUT -->
            <div class="col-lg-5 col-md-6">
                <label class="form-label small fw-bold text-dark mb-1">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 border-slate"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control form-control-custom border-start-0 ms-0" placeholder="Cari nama barang, kode, atau S/N..." value="<?= htmlspecialchars($search_filter); ?>">
                </div>
            </div>

            <!-- DROPDOWN KATEGORI -->
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold text-dark mb-1">Kategori Barang</label>
                <select name="kategori" class="form-select form-select-custom">
                    <option value="">-- Semua Kategori --</option>
                    <?php if ($q_kategori): ?>
                        <?php while ($kat = mysqli_fetch_assoc($q_kategori)): ?>
                            <option value="<?= htmlspecialchars($kat['kategori']); ?>" <?= ($kategori_filter === $kat['kategori']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($kat['kategori']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- BUTTON SUBMIT & RESET -->
            <div class="col-lg-3 col-md-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100 rounded-3">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <?php if (!empty($search_filter) || !empty($kategori_filter)): ?>
                    <a href="cetak_qr.php" class="btn btn-light border fw-bold rounded-3" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- DAFTAR LABEL QR CODE (GRID) -->
    <div class="row g-4">
        <?php if ($query && mysqli_num_rows($query) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($query)): ?>
                <?php 
                    $kode_unik   = htmlspecialchars($row['kode_barang']);
                    $target_url  = $base_url . urlencode($row['kode_barang']); 
                    // Sanitasi id element agar aman dari karakter khusus
                    $element_id  = "qrcode_" . preg_replace('/[^a-zA-Z0-9]/', '_', $row['kode_barang']);
                    $sn_display  = !empty($row['nomor_seri']) ? $row['nomor_seri'] : '-';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="qr-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                            <span class="fw-bold text-primary small">PLN ICON PLUS</span>
                            <span class="badge-kode"><?= $kode_unik; ?></span>
                        </div> 
                        
                        <div class="qr-container">
                            <div id="<?= $element_id; ?>"></div>
                        </div>

                        <h6 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($row['nama_barang']); ?></h6>
                        <small class="text-muted d-block font-monospace fw-semibold">S/N: <?= htmlspecialchars($sn_display); ?></small>
                    </div>
                </div>

                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function() {
                        new QRCode(document.getElementById("<?= $element_id; ?>"), {
                            text: "<?= $target_url; ?>",
                            width: 125,
                            height: 125,
                            colorDark : "#0f172a",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.M
                        });
                    });
                </script>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card card-glass p-5 text-center">
                    <i class="fa-solid fa-triangle-exclamation text-warning fs-1 mb-3"></i>
                    <h5 class="fw-bold text-dark mb-1">Data barang tidak ditemukan!</h5>
                    <p class="text-muted small mb-0">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
