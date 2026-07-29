<?php
session_start();
include 'config/koneksi.php';

// ----------------------------------------------------
// PROTEKSI ADMIN
// ----------------------------------------------------
if (!isset($_SESSION['login'])) {
    echo "<script>alert('Anda harus Login terlebih dahulu!'); window.location='login.php';</script>";
    exit;
}

// ----------------------------------------------------
// 1. PROSES KEMBALIKAN BARANG (TRANSACTION SINKRON 2 TABEL)
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'kembalikan' && !empty($_GET['id'])) {
    $id_barang = (int)$_GET['id'];
    $tgl_kembali_sekarang = date('Y-m-d H:i:s');

    // Mulai Transaction agar sinkronisasi 100% aman
    mysqli_begin_transaction($conn);

    try {
        // A. Update status di tabel barang
        $stmt1 = mysqli_prepare($conn, "UPDATE barang SET status = 'Tersedia' WHERE id_barang = ?");
        mysqli_stmt_bind_param($stmt1, "i", $id_barang);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // B. Update status & tgl_kembali di tabel peminjaman
        $stmt2 = mysqli_prepare($conn, "UPDATE peminjaman SET tgl_kembali = ?, status_pinjam = 'Dikembalikan' WHERE id_barang = ? AND (status_pinjam = 'Dipinjam' OR status_pinjam IS NULL OR tgl_kembali IS NULL)");
        mysqli_stmt_bind_param($stmt2, "si", $tgl_kembali_sekarang, $id_barang);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        // Commit transaksi jika keduanya berhasil
        mysqli_commit($conn);

        echo "<script>alert('Barang berhasil dikembalikan dan status berhasil diperbarui!'); window.location.href='index.php';</script>";
        exit;
    } catch (Exception $e) {
        // Rollback jika salah satu query gagal
        mysqli_rollback($conn);
        echo "<script>alert('Gagal memproses pengembalian: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        exit;
    }
}

// ----------------------------------------------------
// 2. PROSES TAMBAH BARANG BARU
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_barang'])) {
    $kode_barang    = trim($_POST['kode_barang'] ?? '');
    $nama_barang    = trim($_POST['nama_barang'] ?? '');
    $kategori       = trim($_POST['kategori'] ?? '');
    $merk_tipe      = trim($_POST['merk_tipe'] ?? '');
    $nomor_seri     = trim($_POST['nomor_seri'] ?? '');
    $lokasi         = trim($_POST['lokasi'] ?? '');
    $deskripsi      = trim($_POST['deskripsi'] ?? '');
    $status_default = 'Tersedia';

    if (!empty($kode_barang) && !empty($nama_barang)) {
        // Cek duplikasi kode_barang sebelum insert
        $check_stmt = mysqli_prepare($conn, "SELECT id_barang FROM barang WHERE kode_barang = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $kode_barang);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo "<script>alert('Kode Barang sudah ada! Silakan gunakan kode lain.'); window.history.back();</script>";
            mysqli_stmt_close($check_stmt);
            exit;
        }
        mysqli_stmt_close($check_stmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO barang (kode_barang, nama_barang, kategori, merk_tipe, nomor_seri, lokasi, deskripsi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssssss", $kode_barang, $nama_barang, $kategori, $merk_tipe, $nomor_seri, $lokasi, $deskripsi, $status_default);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                echo "<script>alert('Barang Berhasil Ditambahkan!'); window.location.href='index.php';</script>";
                exit;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        echo "<script>alert('Kode dan Nama Barang wajib diisi!');</script>";
    }
}

// ----------------------------------------------------
// 3. FITUR SEARCH REAL-TIME / FILTER
// ----------------------------------------------------
$keyword = trim($_GET['keyword'] ?? '');
if (!empty($keyword)) {
    $search_param = "%" . $keyword . "%";
    $stmt = mysqli_prepare($conn, "SELECT * FROM barang WHERE nama_barang LIKE ? OR kode_barang LIKE ? OR nomor_seri LIKE ? OR lokasi LIKE ? ORDER BY id_barang DESC");
    mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $query = mysqli_stmt_get_result($stmt);
} else {
    $query = mysqli_query($conn, "SELECT * FROM barang ORDER BY id_barang DESC");
}

// ----------------------------------------------------
// 4. COUNTER AKURAT DASHBOARD
// ----------------------------------------------------
$count_total    = mysqli_num_rows(mysqli_query($conn, "SELECT id_barang FROM barang"));
$count_tersedia = mysqli_num_rows(mysqli_query($conn, "SELECT id_barang FROM barang WHERE LOWER(status)='tersedia'"));
$count_dipinjam = mysqli_num_rows(mysqli_query($conn, "SELECT id_barang FROM barang WHERE LOWER(status)!='tersedia'"));

// ----------------------------------------------------
// 5. QUERY BARANG TERSEDIA UNTUK MODAL PINJAM
// ----------------------------------------------------
$barang_tersedia = mysqli_query($conn, "SELECT * FROM barang WHERE LOWER(status)='tersedia' ORDER BY nama_barang ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Inventaris — PLN ICONPLUS</title>
    
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* BACKGROUND DASHBOARD GRADASI UNIFORM */
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%),
                        url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat fixed;
            color: #0f172a;
            overflow-x: hidden;
        }

        /* SIDEBAR UNIFORM */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: #0f172a;
            color: #fff;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
            transition: all 0.2s ease;
        }

        #sidebar ul li a:hover, #sidebar ul li.active a {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #38bdf8;
        }

        #sidebar ul li a i { margin-right: 14px; font-size: 1.1rem; width: 20px; text-align: center; }

        /* CONTENT AREA */
        #content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* HERO BANNER HEADER UNIFORM */
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

        /* CARD GLASS UNIFORM */
        .card-glass {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        /* KARTU STATISTIK */
        .card-stat {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* CUSTOM TABLE */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-bottom: 0;
        }

        .table-custom th {
            padding: 12px 16px;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            border: none;
            white-space: nowrap;
        }

        .table-custom tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .table-custom tbody tr:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .table-custom td {
            padding: 14px 16px;
            vertical-align: middle;
            border: none;
            white-space: nowrap;
            color: #0f172a;
        }

        .table-custom td:first-child { border-radius: 12px 0 0 12px; }
        .table-custom td:last-child { border-radius: 0 12px 12px 0; }

        /* BADGES FIXED UNIFORM */
        .badge-code {
            display: inline-block;
            white-space: nowrap;
            background: #f8fafc;
            color: #1e293b;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 0.78rem;
            font-family: monospace;
        }

        .badge-status-tersedia {
            background: #dcfce7;
            color: #15803d;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-status-dipinjam {
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .form-control-custom {
            border-radius: 10px; 
            border: 1px solid #cbd5e1; 
            padding: 8px 14px; 
            font-size: 0.88rem; 
            background-color: #f8fafc;
            color: #0f172a !important;
            font-weight: 600;
        }

        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #sidebar.active { margin-left: 0; }
            #content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<nav id="sidebar">
    <div>
        <div class="sidebar-header d-flex align-items-center">
            <img src="https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png" height="34" class="me-3" alt="PLN Logo">
            <div>
                <h6 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px;">INVENTARIS</h6>
                <small class="text-info font-monospace" style="font-size: 0.75rem;">ICONPLUS</small>
            </div>
        </div>

        <ul class="list-unstyled components mb-0">
            <li class="active"><a href="index.php"><i class="fa-solid fa-boxes-stacked"></i> Data Barang</a></li>
            <li><a href="riwayat.php"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</a></li>
            <li><a href="cetak_qr.php"><i class="fa-solid fa-qrcode"></i> Cetak QR Code</a></li>
        </ul>
    </div>

    <!-- PROFILE FOOTER -->
    <div class="p-3 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center mb-3 px-2 gap-3">
            <div class="bg-primary bg-gradient rounded-circle text-white d-flex align-items-center justify-content-center shadow" style="width: 36px; height: 36px; flex-shrink:0;">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <div class="overflow-hidden">
                <p class="mb-0 fw-bold text-white text-truncate small"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin Inventaris'); ?></p>
                <small class="text-success fw-semibold" style="font-size: 0.72rem;"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem;"></i> Online</small>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 rounded-3 text-start px-3 fw-bold">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>
</nav>

<!-- CONTENT AREA -->
<div id="content">
    
    <!-- HERO BANNER HEADER -->
    <div class="hero-banner d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <button type="button" id="sidebarCollapse" class="btn btn-light d-lg-none rounded-3 shadow-sm">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h2>Sistem Peminjaman Barang</h2>
                <p class="mb-0">Divisi Operasional & Teknologi Informasi — PT PLN Icon Plus</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-warning text-dark fw-bold px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPinjam">
                <i class="fa-solid fa-hand-holding me-1"></i> Pinjam Barang
            </button>
            <button type="button" class="btn btn-light text-primary fw-bold px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAktivasi">
                <i class="fa-solid fa-plus me-1"></i> Tambah Barang
            </button>
        </div>
    </div>

    <!-- CARDS STATISTIK SUMMARY -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-stat d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Total Inventaris</small>
                    <h3 class="fw-bold text-dark mb-0"><?= $count_total; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-stat d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Barang Tersedia</small>
                    <h3 class="fw-bold text-dark mb-0"><?= $count_tersedia; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-stat d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fa-solid fa-hand-holding"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Barang Dipinjam</small>
                    <h3 class="fw-bold text-dark mb-0"><?= $count_dipinjam; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="card card-glass p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="fa-solid fa-list-check text-primary"></i> Daftar Barang Inventaris
            </h5>
            
            <form action="index.php" method="GET" class="d-flex" style="max-width: 320px; width: 100%;">
                <div class="input-group">
                    <input type="text" name="keyword" class="form-control form-control-custom border-end-0" placeholder="Cari nama, SN, kode..." value="<?= htmlspecialchars($keyword); ?>">
                    <button type="submit" class="btn btn-primary px-3 rounded-end-3"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if(!empty($keyword)): ?>
                        <a href="index.php" class="btn btn-secondary px-3 ms-1 rounded-3"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr class="text-uppercase">
                        <th style="width: 130px;">KODE</th>
                        <th>NAMA BARANG</th>
                        <th style="width: 150px;">S/N (SERIAL)</th>
                        <th style="width: 180px;">LOKASI</th>
                        <th style="width: 130px;">STATUS</th>
                        <th class="text-center" style="width: 150px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query && mysqli_num_rows($query) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td>
                                <span class="badge-code"><?= htmlspecialchars($row['kode_barang']); ?></span>
                            </td>
                            <td class="fw-bold text-dark">
                                <?= htmlspecialchars($row['nama_barang']); ?>
                            </td>
                            <td class="font-monospace text-secondary small fw-semibold">
                                <?= !empty($row['nomor_seri']) ? htmlspecialchars($row['nomor_seri']) : '-'; ?>
                            </td>
                            <td class="text-secondary small fw-semibold">
                                <i class="fa-solid fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($row['lokasi']); ?>
                            </td>
                            <td>
                                <?php if(strtolower($row['status'] ?? 'tersedia') === 'tersedia'): ?>
                                    <span class="badge-status-tersedia"><i class="fa-solid fa-check"></i> Tersedia</span>
                                <?php else: ?>
                                    <span class="badge-status-dipinjam"><i class="fa-solid fa-clock"></i> Dipinjam</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if(strtolower($row['status'] ?? '') !== 'tersedia'): ?>
                                        <a href="index.php?action=kembalikan&id=<?= $row['id_barang']; ?>" class="btn btn-success fw-semibold px-2" title="Kembalikan Barang" onclick="return confirm('Kembalikan barang ini? Status di Dashboard dan Riwayat akan otomatis ter-update.');">
                                            <i class="fa-solid fa-rotate-left me-1"></i> Kembalikan
                                        </a>
                                    <?php endif; ?>

                                    <a href="edit.php?id=<?= $row['id_barang']; ?>" class="btn btn-outline-warning text-dark px-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="hapus.php?id=<?= $row['id_barang']; ?>" class="btn btn-outline-danger px-2" title="Hapus" onclick="return confirm('Hapus barang ini?');"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fa-solid fa-folder-open fs-1 text-muted opacity-50 mb-2 d-block"></i> Data barang tidak ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER -->
        <footer class="text-center text-muted mt-4 pt-3 border-top small">
            © 2026 PT PLN Icon Plus — Divisi Operasional & Teknologi Informasi.
        </footer>
    </div>
</div>

<!-- MODAL PINJAM BARANG -->
<div class="modal fade" id="modalPinjam" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-warning text-dark border-0">
        <h6 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding me-2"></i>Transaksi Peminjaman Barang</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="simpan_pinjam.php" method="POST">
          <div class="modal-body p-4 bg-white">
              <div class="row g-3">
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">PILIH BARANG</label>
                      <select name="id_barang" class="form-select form-control-custom" required>
                          <option value="">-- Pilih Barang Tersedia --</option>
                          <?php if($barang_tersedia && mysqli_num_rows($barang_tersedia) > 0): ?>
                              <?php while($b = mysqli_fetch_assoc($barang_tersedia)): ?>
                                  <option value="<?= $b['id_barang']; ?>"><?= htmlspecialchars($b['nama_barang']); ?> (<?= $b['kode_barang']; ?>)</option>
                              <?php endwhile; ?>
                          <?php else: ?>
                              <option value="" disabled>Semua barang sedang dipinjam</option>
                          <?php endif; ?>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">NAMA PEMINJAM</label>
                      <input type="text" name="nama_peminjam" class="form-control form-control-custom" placeholder="Nama Lengkap" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">DIVISI / UNIT</label>
                      <input type="text" name="divisi_peminjam" class="form-control form-control-custom" placeholder="Contoh: Umum / Logistik" required>
                  </div>
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">NO. KONTAK / WA</label>
                      <input type="text" name="kontak_peminjam" class="form-control form-control-custom" placeholder="0812xxxxxxx" required>
                  </div>
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">KEPERLUAN / ALASAN PINJAM</label>
                      <textarea name="keperluan" class="form-control form-control-custom" rows="3" placeholder="Contoh: Maintenance jaringan / Penanganan insiden site A..." required></textarea>
                  </div>
              </div>
          </div>
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-warning rounded-3 fw-bold px-4">Simpan Peminjaman</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL TAMBAH BARANG -->
<div class="modal fade" id="modalAktivasi" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-primary text-white border-0">
        <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i>Tambah Barang Baru</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="index.php" method="POST">
          <div class="modal-body p-4 bg-white">
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">KODE BARANG</label>
                      <input type="text" name="kode_barang" class="form-control form-control-custom" value="BRG-<?= sprintf('%04d', rand(1, 9999)); ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-secondary">NAMA BARANG</label>
                      <input type="text" name="nama_barang" class="form-control form-control-custom" placeholder="Contoh: Switch Mikrotik" required>
                  </div>
                  <div class="col-md-4">
                      <label class="form-label small fw-bold text-secondary">KATEGORI</label>
                      <input type="text" name="kategori" class="form-control form-control-custom" placeholder="Elektronik">
                  </div>
                  <div class="col-md-4">
                      <label class="form-label small fw-bold text-secondary">MERK / TIPE</label>
                      <input type="text" name="merk_tipe" class="form-control form-control-custom">
                  </div>
                  <div class="col-md-4">
                      <label class="form-label small fw-bold text-secondary">NOMOR SERI (S/N)</label>
                      <input type="text" name="nomor_seri" class="form-control form-control-custom">
                  </div>
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">LOKASI PENYIMPANAN</label>
                      <input type="text" name="lokasi" class="form-control form-control-custom" required>
                  </div>
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-secondary">DESKRIPSI BARANG</label>
                      <textarea name="deskripsi" class="form-control form-control-custom" rows="2"></textarea>
                  </div>
              </div>
          </div>
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="simpan_barang" class="btn btn-primary rounded-3 fw-bold px-4">Simpan Barang</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TOGGLE SIDEBAR MOBILE
document.getElementById('sidebarCollapse')?.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('active');
});
</script>

</body>
</html>
