<?php
session_start();
include 'config/koneksi.php';

// Helper function untuk format nomor WhatsApp ke format internasional (628...)
function format_wa($nomor) {
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (strpos($nomor, '0') === 0) {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// Menangkap input dari scan QR atau parameter URL
$keyword = $_GET['id'] ?? $_GET['kode'] ?? '';

if (empty($keyword)) {
    echo "<script>alert('Silakan scan QR Code barang!'); window.location='index.php';</script>";
    exit;
}

// Query data barang menggunakan Prepared Statement
$stmt = mysqli_prepare($conn, "SELECT * FROM barang WHERE kode_barang = ? OR id_barang = ?");
mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "<script>alert('Barang tidak ditemukan di sistem!'); window.location='index.php';</script>";
    exit;
}

$id_barang_asli = $data['id_barang'] ?? $data['kode_barang'];

// CEK STATUS LOGIN ADMIN
$is_admin = isset($_SESSION['login']) || isset($_SESSION['admin']) || isset($_SESSION['username']);

// Menyiapkan Link Redirect ke Halaman Login/Logout secara Aman
$current_url_target = "detail.php?id=" . urlencode($keyword);
$login_url          = "login.php?redirect=" . urlencode($current_url_target);

// CEK DATA PEMINJAMAN JIKA BARANG SEDANG DIPINJAM
$data_peminjam = null;
if (strtolower($data['status']) !== 'tersedia') {
    $q_pinjam = mysqli_prepare($conn, "SELECT * FROM peminjaman WHERE (id_barang = ? OR id_barang = ?) AND (status_pinjam = 'Dipinjam' OR status_pinjam IS NULL OR tgl_kembali IS NULL) ORDER BY id_peminjaman DESC LIMIT 1");
    $kode_brg_ref = $data['kode_barang'];
    mysqli_stmt_bind_param($q_pinjam, "ss", $id_barang_asli, $kode_brg_ref);
    mysqli_stmt_execute($q_pinjam);
    $res_pinjam = mysqli_stmt_get_result($q_pinjam);

    if ($res_pinjam && mysqli_num_rows($res_pinjam) > 0) {
        $data_peminjam = mysqli_fetch_assoc($res_pinjam);
    }
}

// -------------------------------------------------------------
// 1. PROSES PEMINJAMAN (HANYA UNTUK ADMIN)
// -------------------------------------------------------------
if (isset($_POST['proses_pinjam'])) {
    if (!$is_admin) {
        echo "<script>alert('Akses Ditolak! Hanya Admin yang dapat memproses peminjaman.'); window.location='$login_url';</script>";
        exit;
    }

    $nama_peminjam   = trim($_POST['nama_peminjam']);
    $kontak_peminjam = trim($_POST['no_hp']); 
    $divisi_input    = trim($_POST['divisi']);
    $keperluan       = trim($_POST['keperluan']);
    $tgl_pinjam      = date('Y-m-d H:i:s');

    if (empty($nama_peminjam) || empty($divisi_input) || empty($kontak_peminjam)) {
        echo "<script>alert('Nama, Nomor Kontak, dan Divisi wajib diisi!'); window.history.back();</script>";
        exit;
    }

    // Menyimpan peminjaman dengan Prepared Statement
    $stmt_insert = mysqli_prepare($conn, "INSERT INTO peminjaman (id_barang, nama_peminjam, kontak_peminjam, divisi, tgl_pinjam, keperluan, status_pinjam) VALUES (?, ?, ?, ?, ?, ?, 'Dipinjam')");
    mysqli_stmt_bind_param($stmt_insert, "ssssss", $id_barang_asli, $nama_peminjam, $kontak_peminjam, $divisi_input, $tgl_pinjam, $keperluan);
    $insert = mysqli_stmt_execute($stmt_insert);

    if ($insert) {
        $stmt_upd = mysqli_prepare($conn, "UPDATE barang SET status = 'Dipinjam' WHERE kode_barang = ? OR id_barang = ?");
        mysqli_stmt_bind_param($stmt_upd, "ss", $id_barang_asli, $id_barang_asli);
        mysqli_stmt_execute($stmt_upd);

        echo "<script>alert('Peminjaman Berhasil Dipproses!'); window.location='detail.php?id=" . urlencode($keyword) . "';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal memproses peminjaman: " . mysqli_error($conn) . "');</script>";
    }
}

// -------------------------------------------------------------
// 2. PROSES PENGEMBALIAN BARANG (HANYA UNTUK ADMIN)
// -------------------------------------------------------------
if (isset($_POST['proses_kembali'])) {
    if (!$is_admin) {
        echo "<script>alert('Akses Ditolak! Hanya Admin yang dapat mengembalikan barang.'); window.location='$login_url';</script>";
        exit;
    }

    $tgl_sekarang = date('Y-m-d H:i:s');

    $stmt_upd = mysqli_prepare($conn, "UPDATE barang SET status = 'Tersedia' WHERE kode_barang = ? OR id_barang = ?");
    mysqli_stmt_bind_param($stmt_upd, "ss", $id_barang_asli, $id_barang_asli);
    $update = mysqli_stmt_execute($stmt_upd);
    
    if ($data_peminjam) {
        $id_peminjaman = $data_peminjam['id_peminjaman'];
        $stmt_pinjam_upd = mysqli_prepare($conn, "UPDATE peminjaman SET tgl_kembali = ?, status_pinjam = 'Dikembalikan' WHERE id_peminjaman = ?");
        mysqli_stmt_bind_param($stmt_pinjam_upd, "si", $tgl_sekarang, $id_peminjaman);
        mysqli_stmt_execute($stmt_pinjam_upd);
    }

    if ($update) {
        echo "<script>alert('Barang Berhasil Dikembalikan!'); window.location='detail.php?id=" . urlencode($keyword) . "';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal memproses pengembalian: " . mysqli_error($conn) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Barang — <?= htmlspecialchars($data['nama_barang']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f4f6f9; }
        .navbar-custom { background: linear-gradient(135deg, #0d3b66 0%, #0077b6 100%); }
        .card-custom { border-radius: 16px; border: none; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark navbar-custom shadow-sm py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="index.php">
            <img src="https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png" height="32" class="me-2" alt="Logo">
            <span>INVENTARIS <small class="fw-normal opacity-75">| ICONPLUS</small></span>
        </a>
        <?php if ($is_admin): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 d-none d-md-inline">
                    <i class="fa-solid fa-user-check me-1"></i> Admin Logged-in
                </span>
                <!-- TOMBOL LOGOUT MERAH -->
                <a href="logout.php?redirect=<?= urlencode($current_url_target); ?>" 
                   class="btn btn-danger btn-sm fw-bold rounded-3 px-3"
                   onclick="return confirm('Apakah Anda yakin ingin keluar/logout dari akun Admin?');">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </a>
            </div>
        <?php else: ?>
            <a href="<?= $login_url; ?>" class="btn btn-warning btn-sm fw-bold rounded-3">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Login Admin
            </a>
        <?php endif; ?>
    </div>
</nav>

<div class="container mb-5" style="max-width: 600px;">

    <!-- CARD DETAIL BARANG -->
    <div class="card card-custom shadow-sm p-4 mb-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                <?= htmlspecialchars($data['kode_barang'] ?? $data['id_barang']); ?>
            </span>
            <?php if (strtolower($data['status']) === 'tersedia'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                    <i class="fa-solid fa-circle-check me-1"></i> TERSEDIA
                </span>
            <?php else: ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                    <i class="fa-solid fa-hand-holding me-1"></i> DIPINJAM
                </span>
            <?php endif; ?>
        </div>

        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($data['nama_barang']); ?></h4>
        <p class="text-muted small mb-0"><?= htmlspecialchars($data['kategori'] ?? 'Barang Inventaris'); ?></p>
        <?php if (!empty($data['nomor_seri'])): ?>
            <div class="mt-2 text-secondary small font-monospace">S/N: <?= htmlspecialchars($data['nomor_seri']); ?></div>
        <?php endif; ?>
    </div>

    <!-- KONDISI 1: JIKA BARANG SEDANG DIPINJAM -->
    <?php if (strtolower($data['status']) !== 'tersedia'): ?>
        <div class="card card-custom shadow-sm p-4 bg-white mb-4">
            <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-circle-info me-2"></i>Informasi Peminjaman Saat Ini</h6>
            
            <?php if ($data_peminjam): ?>
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Nama Peminjam</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($data_peminjam['nama_peminjam'] ?? '-'); ?></span>
                    </li>
                    
                    <!-- PRIVASI NOMOR KONTAK / WA (KHUSUS ADMIN) -->
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Nomor Kontak / WA</span>
                        <?php if ($is_admin && !empty($data_peminjam['kontak_peminjam'])): ?>
                            <a href="https://wa.me/<?= format_wa($data_peminjam['kontak_peminjam']); ?>" target="_blank" class="fw-bold text-decoration-none text-success">
                                <i class="fa-brands fa-whatsapp me-1 fs-6"></i><?= htmlspecialchars($data_peminjam['kontak_peminjam']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted fst-italic"><i class="fa-solid fa-lock me-1"></i> Disembunyikan (Khusus Admin)</span>
                        <?php endif; ?>
                    </li>

                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Divisi</span>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($data_peminjam['divisi'] ?? '-'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Tanggal Pinjam</span>
                        <span class="fw-semibold">
                            <?= (!empty($data_peminjam['tgl_pinjam']) && $data_peminjam['tgl_pinjam'] !== '0000-00-00 00:00:00') ? date('d/m/Y H:i', strtotime($data_peminjam['tgl_pinjam'])) : '-'; ?>
                        </span>
                    </li>
                    <li class="list-group-item px-0 pt-2">
                        <span class="text-muted d-block mb-1">Keterangan / Keperluan:</span>
                        <div class="p-2 bg-light rounded text-secondary"><?= !empty($data_peminjam['keperluan']) ? nl2br(htmlspecialchars($data_peminjam['keperluan'])) : '-'; ?></div>
                    </li>
                </ul>
            <?php else: ?>
                <p class="small text-muted mb-3">Detail peminjam tidak ditemukan di riwayat.</p>
            <?php endif; ?>

            <!-- HANYA ADMIN YANG DAPAT MENAMPILKAN TOMBOL KEMBALIKAN BARANG -->
            <?php if ($is_admin): ?>
                <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin barang ini sudah dikembalikan?');">
                    <button type="submit" name="proses_kembali" class="btn btn-success w-100 fw-bold py-2 rounded-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> KEMBALIKAN BARANG INI
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-light text-center border rounded-3 mb-0 p-3 small text-muted">
                    <p class="mb-2"><i class="fa-solid fa-lock me-1"></i> Ingin mengembalikan barang ini?</p>
                    <a href="<?= $login_url; ?>" class="btn btn-outline-warning text-dark fw-bold btn-sm rounded-2">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Login Admin untuk Pengembalian
                    </a>
                </div>
            <?php endif; ?>
        </div>

    <!-- KONDISI 2: JIKA BARANG TERSEDIA & USER ADALAH ADMIN ( TAMPILKAN FORM PEMINJAMAN ) -->
    <?php elseif ($is_admin && strtolower($data['status']) === 'tersedia'): ?>
        <div class="card card-custom shadow-sm p-4 bg-white">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-handshake text-primary me-2"></i>Form Transaksi Peminjaman</h5>
            <form action="" method="POST">
                <!-- 1. NAMA PEMINJAM -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">NAMA PEMINJAM</label>
                    <input type="text" name="nama_peminjam" class="form-control rounded-3" required placeholder="Masukkan nama peminjam">
                </div>

                <!-- 2. NOMOR KONTAK / WHATSAPP -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">NOMOR KONTAK / WHATSAPP</label>
                    <input type="tel" name="no_hp" class="form-control rounded-3" required placeholder="Contoh: 081234567890">
                </div>

                <!-- 3. ASAL DIVISI / SUB-UNIT -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">ASAL DIVISI / SUB-UNIT</label>
                    <input type="text" name="divisi" class="form-control rounded-3" required placeholder="Contoh: Operasional / IT">
                </div>

                <!-- 4. KEPERLUAN PEMINJAMAN -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">KEPERLUAN PEMINJAMAN</label>
                    <textarea name="keperluan" class="form-control rounded-3" rows="2" placeholder="Tuliskan alasan peminjaman..."></textarea>
                </div>

                <button type="submit" name="proses_pinjam" class="btn btn-primary w-100 fw-bold py-2 rounded-3 mt-2">
                    <i class="fa-solid fa-paper-plane me-1"></i> PROSES PEMINJAMAN
                </button>
            </form>
        </div>

    <!-- KONDISI 3: JIKA BARANG TERSEDIA TAPI PENGGUNA BELUM LOGIN -->
    <?php else: ?>
        <div class="alert alert-warning text-center rounded-4 shadow-sm p-4">
            <i class="fa-solid fa-lock fs-1 text-warning mb-2"></i>
            <h6 class="fw-bold">Barang Ini Tersedia!</h6>
            <p class="small text-muted mb-3">Untuk memproses peminjaman barang ini, silakan Login sebagai Admin terlebih dahulu.</p>
            <a href="<?= $login_url; ?>" class="btn btn-warning fw-bold px-4 rounded-3">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Login Admin untuk Pinjam
            </a>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
