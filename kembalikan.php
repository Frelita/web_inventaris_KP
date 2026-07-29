<?php
session_start();
include 'config/koneksi.php';

$id_peminjaman = $_GET['id'] ?? $_POST['id_peminjaman'] ?? null;

if (!empty($id_peminjaman)) {
    // Ambil ID Barang yang bersangkutan
    $q = mysqli_query($conn, "SELECT id_barang FROM peminjaman WHERE id_peminjaman = '$id_peminjaman'");
    $data = mysqli_fetch_assoc($q);

    if ($data) {
        $id_barang = $data['id_barang'];

        // 1. Update Status Riwayat Peminjaman
        $sql1 = "UPDATE peminjaman SET status_pinjam = 'Dikembalikan', tgl_kembali = NOW() WHERE id_peminjaman = '$id_peminjaman'";
        
        // 2. Update Status Master Barang di Dashboard
        $sql2 = "UPDATE barang SET status = 'Tersedia' WHERE id_barang = '$id_barang'";

        if (mysqli_query($conn, $sql1) && mysqli_query($conn, $sql2)) {
            echo "<script>alert('Barang berhasil dikembalikan!'); window.location.href='riwayat.php';</script>";
        } else {
            echo "Gagal mengupdate data: " . mysqli_error($conn);
        }
    }
} else {
    header("Location: riwayat.php");
}
?>
