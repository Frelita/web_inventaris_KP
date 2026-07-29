<?php
session_start();
include 'config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang       = $_POST['id_barang'] ?? '';
    $nama_peminjam   = trim($_POST['nama_peminjam'] ?? '');
    $divisi          = trim($_POST['divisi'] ?? $_POST['divisi_peminjam'] ?? '');
    $kontak_peminjam = trim($_POST['kontak_peminjam'] ?? $_POST['no_hp'] ?? '');
    $keperluan       = trim($_POST['keperluan'] ?? $_POST['catatan'] ?? '');

    // Validasi input wajib
    if (empty($id_barang) || empty($nama_peminjam) || empty($divisi) || empty($kontak_peminjam)) {
        echo "<script>alert('Barang, Nama Peminjam, Divisi, dan No. Kontak wajib diisi!'); window.history.back();</script>";
        exit;
    }

    // CEK APAKAH BARANG TERSEDIA ATAU SEDANG DIPINJAM
    $id_clean   = mysqli_real_escape_string($conn, $id_barang);
    $cek_status = mysqli_query($conn, "SELECT id_barang, status, kode_barang FROM barang WHERE id_barang = '$id_clean' OR kode_barang = '$id_clean'");
    $b          = mysqli_fetch_assoc($cek_status);
    
    if (!$b) {
        echo "<script>alert('Barang tidak ditemukan di sistem!'); window.history.back();</script>";
        exit;
    }

    if (strtolower($b['status']) === 'dipinjam') {
        echo "<script>alert('Barang ini sedang DIPINJAM! Pilih barang lain.'); window.history.back();</script>";
        exit;
    }

    $id_barang_asli = $b['id_barang'];
    $tgl_pinjam     = date('Y-m-d H:i:s');

    // Mulai Transaksi Database agar sinkron
    mysqli_begin_transaction($conn);

    try {
        // 1. Simpan Transaksi Ke Tabel peminjaman (Menggunakan kolom: divisi & kontak_peminjam)
        $sql_pinjam = "INSERT INTO peminjaman (id_barang, nama_peminjam, divisi, kontak_peminjam, tgl_pinjam, status_pinjam, keperluan) 
                       VALUES (?, ?, ?, ?, ?, 'Dipinjam', ?)";
        
        $stmt = mysqli_prepare($conn, $sql_pinjam);
        mysqli_stmt_bind_param($stmt, "isssss", $id_barang_asli, $nama_peminjam, $divisi, $kontak_peminjam, $tgl_pinjam, $keperluan);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 2. Update Status Fisik Barang di Tabel barang Menjadi 'Dipinjam'
        $stmt_update = mysqli_prepare($conn, "UPDATE barang SET status = 'Dipinjam' WHERE id_barang = ?");
        mysqli_stmt_bind_param($stmt_update, "i", $id_barang_asli);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Jika semua sukses, simpan transaksi
        mysqli_commit($conn);

        echo "<script>alert('Peminjaman berhasil dicatat!'); window.location.href='riwayat.php';</script>";
        exit;

    } catch (Exception $e) {
        // Jika ada kesalahan, batalkan seluruh query
        mysqli_rollback($conn);
        echo "<script>alert('Gagal memproses data: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }

} else {
    header("Location: index.php");
    exit;
}
?>
