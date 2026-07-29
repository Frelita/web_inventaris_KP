<?php
include 'config/koneksi.php';

$id_barang = $_GET['id'] ?? '';

if (!empty($id_barang)) {
    // Hapus barang (peminjaman terkait akan ikut terhapus otomatis karena CASCADE)
    $q = mysqli_query($conn, "DELETE FROM barang WHERE id_barang = '$id_barang'");
    if ($q) {
        echo "<script>alert('Barang berhasil dihapus!'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus barang!'); window.location='index.php';</script>";
    }
} else {
    header("Location: index.php");
}
?>
