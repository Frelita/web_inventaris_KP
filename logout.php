<?php
session_start();

// 1. Hapus semua variabel session
$_SESSION = array();

// 2. Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Hancurkan session
session_destroy();

// 4. Cek apakah ada parameter redirect (misal kembali ke detail.php?id=XXX)
$redirect = $_GET['redirect'] ?? 'login.php';

// Sanitiilasi URL redirect sederhana agar aman dari header injection
$redirect = str_replace(array("\r", "\n"), '', $redirect);

// 5. Redirect ke tujuan
header("Location: " . $redirect);
exit;
?>
