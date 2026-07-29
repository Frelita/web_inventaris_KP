<?php
session_start();
include 'config/koneksi.php';

// Menangkap parameter redirect jika Admin login setelah scan QR
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Jika sudah login dan mencoba akses login.php lagi, langsung kembalikan ke halaman asal/index
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    if (!empty($redirect)) {
        header("Location: " . $redirect);
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

// 1. PROSES LOGIN
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    
    if ($query && mysqli_num_rows($query) === 1) {
        $row = mysqli_fetch_assoc($query);
        
        // Cek kompatibilitas password (md5, password_hash, atau plain-text)
        if (md5($password) === $row['password'] || password_verify($password, $row['password']) || $password === $row['password']) {
            $_SESSION['login']    = true;
            $_SESSION['username'] = $row['username']; // Disamakan dengan pengecekan di detail.php
            $_SESSION['user']     = $row['username'];
            $_SESSION['nama']     = $row['nama_lengkap'] ?? $row['username'];
            
            // JIKA ADA PARAMETER REDIRECT (DARI SCAN QR) -> BALIK KEMBALI KE HALAMAN BARANG TERSEBUT
            if (!empty($redirect)) {
                header("Location: " . $redirect);
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Password yang Anda masukkan salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
}

// 2. PROSES RESET PASSWORD VERIFIKASI EMAIL
if (isset($_POST['reset_password'])) {
    $email_input = strtolower(trim(mysqli_real_escape_string($conn, $_POST['reset_email'])));
    $pass_baru   = $_POST['new_password'];
    
    // Cek apakah email terdaftar
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE LOWER(email) = '$email_input'");
    
    if ($cek && mysqli_num_rows($cek) > 0) {
        $pass_hash = md5($pass_baru);
        $update = mysqli_query($conn, "UPDATE users SET password = '$pass_hash' WHERE LOWER(email) = '$email_input'");
        
        if ($update) {
            $success = 'Password berhasil direset! Silakan login dengan password baru Anda.';
        } else {
            $error = 'Gagal mengupdate password: ' . mysqli_error($conn);
        }
    } else {
        $error = 'Verifikasi Gagal! Email tidak terdaftar dalam sistem.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System — INVENTARIS ICONPLUS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* BACKGROUND GAMBAR DENGAN OVERLAY TRANSPARAN */
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.78) 0%, rgba(15, 23, 42, 0.88) 100%),
                        url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        /* CARD LOGIN MODERN & SEMI-TRANSPARAN */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 28px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 430px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .login-header {
            text-align: center;
            padding: 35px 30px 10px 30px;
        }

        .logo-img {
            height: 52px;
            margin-bottom: 12px;
        }

        .form-control-modern {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.95rem;
        }

        .form-control-modern:focus {
            background-color: #ffffff;
            border-color: #0284c7;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
        }

        .input-group-text {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            border-radius: 12px;
            padding: 13px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(2, 132, 199, 0.3);
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(2, 132, 199, 0.45);
            color: #ffffff;
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
        }

        .modal-content-solid {
            background-color: #ffffff !important;
            opacity: 1 !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png" alt="Logo PLN" class="logo-img">
        <h4 class="fw-bold text-dark m-0">INVENTARIS <span class="text-primary">ICONPLUS</span></h4>
        <small class="text-muted fw-semibold">Sistem Manajemen Inventaris & Peminjaman</small>
    </div>

    <div class="p-4 pt-2">
        <!-- Alert Pesan Error / Success -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 py-2 px-3 small d-flex align-items-center mb-3">
                <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i> <?= $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success rounded-3 py-2 px-3 small d-flex align-items-center mb-3">
                <i class="fa-solid fa-circle-check me-2 fs-6"></i> <?= $success; ?>
            </div>
        <?php endif; ?>

        <!-- Form Login Utama -->
        <form action="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" method="POST" autocomplete="off">
            <!-- Menyimpan URL Redirect agar tidak terlempar ke dashboard -->
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">

            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">Username Admin</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" class="form-control form-control-modern border-start-0" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" id="passInput" class="form-control form-control-modern border-start-0 border-end-0" placeholder="••••••••" required>
                    <button type="button" class="input-group-text bg-light border-start-0 text-muted" onclick="togglePassword('passInput', 'eyeIcon')">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <a href="#" class="text-primary small text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#modalLupaPassword">
                    Lupa Password?
                </a>
            </div>

            <button type="submit" name="login" class="btn btn-gradient w-100">
                <i class="fa-solid fa-right-to-bracket me-2"></i> MASUK WEB
            </button>
        </form>
    </div>

    <div class="text-center py-3 bg-light bg-opacity-75 border-top">
        <small class="text-muted">&copy; 2026 PT PLN Icon Plus — Sumatera Selatan</small>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div class="modal fade" id="modalLupaPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-solid border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0 bg-white rounded-top-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-envelope-circle-check text-primary me-2"></i>Reset Password Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" method="POST" autocomplete="off">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">
                
                <div class="modal-body py-3 bg-white">
                    <p class="text-muted small mb-3">Masukkan Email resmi terdaftar Anda untuk verifikasi kepemilikan akun.</p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Email Terdaftar</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0 text-muted"><i class="fa-solid fa-at"></i></span>
                            <input type="email" name="reset_email" class="form-control form-control-modern border-start-0" placeholder="Masukkan email terdaftar..." required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary">Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0 text-muted"><i class="fa-solid fa-key"></i></span>
                            <input type="password" name="new_password" id="newPassInput" class="form-control form-control-modern border-start-0 border-end-0" placeholder="Masukkan password baru..." required autocomplete="new-password">
                            <button type="button" class="input-group-text bg-light border-start-0 text-muted" onclick="togglePassword('newPassInput', 'newEyeIcon')">
                                <i class="fa-solid fa-eye" id="newEyeIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 bg-white rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-3 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="reset_password" class="btn btn-primary rounded-3 fw-bold px-4">Verifikasi & Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
