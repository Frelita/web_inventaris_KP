<?php
session_start();
include 'config/koneksi.php';

// Helper function untuk mengambil query string secara aman
function get_param($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$keyword        = get_param('keyword');
$mulai_tanggal  = get_param('mulai_tanggal');
$sampai_tanggal = get_param('sampai_tanggal');
$status_filter  = get_param('status_filter');

$where_clause = [];
$params       = [];
$types        = "";

// PENCARIAN
if (!empty($keyword)) {
    $where_clause[] = "(b.nama_barang LIKE ? OR b.kode_barang LIKE ? OR p.nama_peminjam LIKE ? OR p.keperluan LIKE ? OR p.kontak_peminjam LIKE ? OR p.divisi LIKE ?)";
    $search_param   = "%" . $keyword . "%";
    $params         = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $types         .= "ssssss";
}

if (!empty($mulai_tanggal) && !empty($sampai_tanggal)) {
    $where_clause[] = "(DATE(p.tgl_pinjam) BETWEEN ? AND ?)";
    $params[]       = $mulai_tanggal;
    $params[]       = $sampai_tanggal;
    $types         .= "ss";
}

if (!empty($status_filter)) {
    $where_clause[] = "p.status_pinjam = ?";
    $params[]       = $status_filter;
    $types         .= "s";
}

// QUERY UTAMA
$query_sql = "SELECT p.*, b.nama_barang, b.kode_barang, b.nomor_seri 
              FROM peminjaman p 
              LEFT JOIN barang b ON (p.id_barang = b.id_barang OR p.id_barang = b.kode_barang)";

if (count($where_clause) > 0) {
    $query_sql .= " WHERE " . implode(' AND ', $where_clause);
}

$query_sql .= " ORDER BY p.id_peminjaman DESC";

$stmt = mysqli_prepare($conn, $query_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_rows = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman — PLN ICONPLUS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --sidebar-width: 260px; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* BACKGROUND DASHBOARD GELAP */
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
        
        /* HERO HEADER BANNER (SESUAI DESAIN DARI GAMBAR) */
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

        /* STYLING TABEL (FONT TEGAS & KONTRAS SANGAT TINGGI) */
        .table-custom { margin-bottom: 0; white-space: nowrap; }
        .table-custom th {
            color: #0f172a !important; 
            font-size: 0.78rem; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            font-weight: 800; 
            padding: 14px 16px; 
            border-bottom: 2px solid #cbd5e1; 
            background: #f1f5f9;
        }
        .table-custom td { 
            padding: 14px 16px; 
            vertical-align: middle; 
            color: #0f172a !important; 
            font-size: 0.88rem; 
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0; 
        }

        /* ELEMEN BADGE & KONTAK */
        .text-dark-bold { color: #0f172a !important; font-weight: 700; }
        .text-wa-kontak { color: #1e7e34 !important; font-weight: 700; text-decoration: none; }
        .text-wa-kontak:hover { color: #145a23 !important; }
        
        .badge-divisi {
            background-color: #f1f5f9; 
            color: #334155 !important; 
            font-weight: 700; 
            font-size: 0.75rem;
            padding: 4px 10px; 
            border-radius: 6px; 
            border: 1px solid #cbd5e1;
            display: inline-block; 
            text-transform: uppercase;
        }
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
        .badge-status-kembali { 
            background-color: #dcfce7; 
            color: #15803d !important; 
            font-weight: 800; 
            padding: 5px 12px; 
            border-radius: 50px; 
            font-size: 0.75rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px;
            border: 1px solid #86efac;
        }
        .badge-status-pinjam { 
            background-color: #fee2e2; 
            color: #b91c1c !important; 
            font-weight: 800; 
            padding: 5px 12px; 
            border-radius: 50px; 
            font-size: 0.75rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px;
            border: 1px solid #fca5a5;
        }

        /* DOKUMEN CETAK (PRINT) */
        .print-header { display: none; }
        @media print {
            @page {
                size: A4 landscape;
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

            .card-glass { 
                box-shadow: none !important; 
                border: none !important; 
                background: #ffffff !important; 
                padding: 0 !important;
                margin: 0 !important;
            }

            .table-responsive { 
                overflow: visible !important; 
                display: block !important;
            }

            .table-custom { 
                width: 100% !important; 
                white-space: normal !important; 
                border-collapse: collapse !important;
            }

            .table-custom th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                font-size: 0.65rem !important;
                padding: 6px 4px !important;
                border: 1px solid #94a3b8 !important;
            }

            .table-custom td {
                font-size: 0.7rem !important;
                padding: 5px 4px !important;
                border: 1px solid #cbd5e1 !important;
                word-wrap: break-word !important;
                color: #000000 !important;
            }

            .badge-divisi, .badge-kode, .badge-status-kembali, .badge-status-pinjam {
                border: 1px solid #94a3b8 !important;
                font-size: 0.65rem !important;
                padding: 2px 5px !important;
            }
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
        <li class="active"><a href="riwayat.php"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</a></li>
        <li><a href="cetak_qr.php"><i class="fa-solid fa-qrcode"></i> Cetak QR Code</a></li>
    </ul>
</nav>

<div id="content">
    <!-- Header khusus saat di-print -->
    <div class="print-header">
        <h4 class="fw-bold text-uppercase mb-1">PT PLN ICON PLUS</h4>
        <h5 class="fw-semibold text-secondary mb-0">LAPORAN RIWAYAT PEMINJAMAN ASET / BARANG INVENTARIS</h5>
        <small class="text-muted">Dicetak pada: <?= date('d-m-Y H:i:s'); ?></small>
    </div>

    <!-- HERO BANNER HEADER -->
    <div class="hero-banner d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 btn-print-hide">
        <div>
            <h2>Laporan Riwayat Peminjaman</h2>
            <p class="mb-0">Rekapitulasi transaksi peminjaman & pengembalian aset inventaris</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-3 shadow-sm">
                <i class="fa-solid fa-print me-2"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="card card-glass p-4 mb-4 filter-card-hide">
        <form action="riwayat.php" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-dark mb-1">Pencarian</label>
                <input type="text" name="keyword" class="form-control form-control-custom" placeholder="Nama, barang, divisi..." value="<?= htmlspecialchars($keyword); ?>">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label small fw-bold text-dark mb-1">Mulai Tanggal</label>
                <input type="date" name="mulai_tanggal" class="form-control form-control-custom" value="<?= htmlspecialchars($mulai_tanggal); ?>">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label small fw-bold text-dark mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai_tanggal" class="form-control form-control-custom" value="<?= htmlspecialchars($sampai_tanggal); ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-dark mb-1">Status Barang</label>
                <select name="status_filter" class="form-select form-select-custom">
                    <option value="">-- Semua Status --</option>
                    <option value="Dipinjam" <?= $status_filter === 'Dipinjam' ? 'selected' : ''; ?>>Dipinjam</option>
                    <option value="Dikembalikan" <?= $status_filter === 'Dikembalikan' ? 'selected' : ''; ?>>Dikembalikan</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold w-100 rounded-3">Filter</button>
                <a href="riwayat.php" class="btn btn-light border fw-bold rounded-3" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- TABEL UTAMA -->
    <div class="card card-glass p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">NO</th>
                        <th>PEMINJAM</th>
                        <th>DIVISI</th>
                        <th>KONTAK</th>
                        <th>NAMA BARANG</th>
                        <th>KODE BARANG</th>
                        <th>NOMOR SERI</th>
                        <th>KEPERLUAN</th>
                        <th>TANGGAL PINJAM</th>
                        <th>TANGGAL KEMBALI</th>
                        <th class="text-center">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_rows > 0): ?>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php 
                            $status_real     = $row['status_pinjam'] ?? 'Dipinjam';
                            $is_dikembalikan = (strtolower($status_real) === 'dikembalikan');
                            
                            $divisi          = !empty($row['divisi']) ? $row['divisi'] : '-';
                            $telp            = !empty($row['kontak_peminjam']) ? $row['kontak_peminjam'] : '-';
                            $keperluan       = !empty($row['keperluan']) ? $row['keperluan'] : '-';
                        ?>
                        <tr>
                            <!-- 1. NO -->
                            <td class="text-center fw-bold text-secondary"><?= $no++; ?></td>
                            
                            <!-- 2. PEMINJAM -->
                            <td class="text-dark-bold"><?= htmlspecialchars($row['nama_peminjam'] ?? '-'); ?></td>

                            <!-- 3. DIVISI -->
                            <td>
                                <?php if($divisi !== '-'): ?>
                                    <span class="badge-divisi"><?= htmlspecialchars($divisi); ?></span>
                                <?php else: ?>
                                    <span class="text-secondary">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 4. KONTAK -->
                            <td>
                                <?php if($telp !== '-'): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $telp); ?>" target="_blank" class="text-wa-kontak">
                                        <i class="fa-solid fa-phone me-1 btn-print-hide"></i><?= htmlspecialchars($telp); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-secondary">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. NAMA BARANG -->
                            <td class="text-dark-bold"><?= htmlspecialchars($row['nama_barang'] ?? 'Barang'); ?></td>

                            <!-- 6. KODE BARANG -->
                            <td><span class="badge-kode"><?= htmlspecialchars($row['kode_barang'] ?? '-'); ?></span></td>

                            <!-- 7. NOMOR SERI -->
                            <td class="font-monospace text-dark fw-bold small"><?= !empty($row['nomor_seri']) ? htmlspecialchars($row['nomor_seri']) : '-'; ?></td>

                            <!-- 8. KEPERLUAN -->
                            <td style="max-width: 220px; white-space: normal;">
                                <span class="text-dark fw-semibold small"><?= htmlspecialchars($keperluan); ?></span>
                            </td>

                            <!-- 9. TANGGAL PINJAM -->
                            <td class="small text-dark fw-bold">
                                <?= (!empty($row['tgl_pinjam']) && $row['tgl_pinjam'] != '0000-00-00 00:00:00') ? date('d/m/Y H:i', strtotime($row['tgl_pinjam'])) : '-'; ?>
                            </td>

                            <!-- 10. TANGGAL KEMBALI -->
                            <td class="small">
                                <?php 
                                    $tgl_kembali = $row['tgl_kembali'] ?? null;
                                    if ($is_dikembalikan && !empty($tgl_kembali) && $tgl_kembali != '0000-00-00 00:00:00' && $tgl_kembali != '1970-01-01 00:00:00'): 
                                ?>
                                    <span class="text-success fw-bold"><?= date('d/m/Y H:i', strtotime($tgl_kembali)); ?></span>
                                <?php else: ?>
                                    <span class="text-secondary fw-semibold">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 11. STATUS -->
                            <td class="text-center">
                                <?php if ($is_dikembalikan): ?>
                                    <span class="badge-status-kembali"><i class="fa-solid fa-check btn-print-hide"></i> Dikembalikan</span>
                                <?php else: ?>
                                    <span class="badge-status-pinjam"><i class="fa-solid fa-arrow-right-from-bracket btn-print-hide"></i> Dipinjam</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center text-dark fw-bold py-5">
                                <i class="fa-solid fa-magnifying-glass fs-1 mb-2 d-block opacity-50"></i>
                                Data riwayat tidak ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
