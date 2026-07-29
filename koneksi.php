<?php
// Set zona waktu PHP ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_peminjaman";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// Sinkronkan juga zona waktu database MySQL ke GMT+7 (WIB)
// Agar fungsi NOW() atau CURRENT_TIMESTAMP di MySQL ikut waktu Indonesia
mysqli_query($conn, "SET time_zone = '+07:00'");
?>
