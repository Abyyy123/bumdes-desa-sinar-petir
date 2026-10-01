<?php
session_start();
include('koneksi/koneksi.php');

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password_input = $_POST['password'];

    // Ambil data user berdasarkan username dari tabel pengguna
    $result = mysqli_query($conn, "SELECT * FROM pengguna WHERE username = '$username' AND status = 'aktif'");

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Verifikasi password
        if (password_verify($password_input, $user['password'])) {
            // Simpan data ke sesi
            $_SESSION['pengguna_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Cek apakah user adalah pelanggan
            $cek_pelanggan = mysqli_query($conn, "SELECT * FROM pelanggan WHERE pengguna_id = " . $user['id']);
            if (mysqli_num_rows($cek_pelanggan) > 0) {
                $_SESSION['role'] = 'pelanggan';
                header('Location: pelanggan/produk.php');
                exit;
            }

            // Cek apakah user adalah penjual
            $cek_penjual = mysqli_query($conn, "SELECT * FROM penjual WHERE pengguna_id = " . $user['id']);
            if (mysqli_num_rows($cek_penjual) > 0) {
                $_SESSION['role'] = 'penjual';
                header('Location: penjual/dashboard_penjual.php');
                exit;
            }

            // Jika bukan pelanggan/penjual, ambil role dari tabel pengguna
            $_SESSION['role'] = $user['role'];

            // Arahkan sesuai role
            switch ($user['role']) {
                case 'admin': header('Location: admin/dashboard_admin.php'); break;
                case 'pengurus': header('Location: pengurus/dashboard_pengurus.php'); break;
                case 'ketua': header('Location: ketua/dashboard_ketua.php'); break;
                case 'bendahara': header('Location: bendahara/dashboard_bendahara.php'); break;
                case 'operator_unit': header('Location: operator/dashboard_operator.php'); break;
                default:
                    $error_message = "Peran pengguna tidak dikenali.";
                    break;
            }
            exit;
        } else {
            $error_message = "Password salah!";
        }
    } else {
        // Jika username tidak ditemukan di tabel 'pengguna', coba di tabel 'kurir'
        $result_kurir = mysqli_query($conn, "SELECT * FROM kurir WHERE username = '$username' AND status = 'aktif'");
        if (mysqli_num_rows($result_kurir) > 0) {
            $kurir = mysqli_fetch_assoc($result_kurir);
            // Verifikasi password untuk kurir
            if (password_verify($password_input, $kurir['password'])) {
                $_SESSION['pengguna_id'] = $kurir['id']; // ID kurir
                $_SESSION['username'] = $kurir['username'];
                $_SESSION['role'] = 'kurir'; // Tetapkan role sebagai 'kurir'
                header('Location: kurir/dashboard_kurir.php'); // Arahkan ke dashboard kurir
                exit;
            } else {
                $error_message = "Password salah!";
            }
        } else {
            $error_message = "Username tidak ditemukan atau akun nonaktif!";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Pengguna BUMDES</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <style>
        /* General Body Styling (Modified to allow login form to overlay background image) */
        body {
            background-image: url('images/hp.avif');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column; /* Added for footer positioning */
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            color: #333; /* Default text color */
        }

        /* Navbar Styling (Copied from artikel.php) */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            /* ADDED: Ensure navbar itself spans full width */
            width: 100%;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* ADDED: Ensure container-fluid within navbar has proper padding */
        /* MENYESUAIKAN PADDING UNTUK JARAK BUMDES DAN MENU NAVIGASI */
        .navbar .container-fluid {
            padding-left: 18rem; /* Mengatur padding kiri */
            padding-right: 19rem; /* Mengatur padding kanan */
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            margin-right: 8px;
            max-width: 30px;
        }

        .nav-link {
            color: white !important; /* Adjusted to match artikel.php */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important; /* Slightly brighter hover color, consistent with promo.php */
            font-weight: bold;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .form-control-sm {
            border-radius: 0.25rem 0 0 0.25rem;
        }

        .btn-outline-light {
            border-radius: 0 0.25rem 0.25rem 0;
            border-color: #fff;
            color: #fff;
        }

        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #FF4500;
        }

        .navbar-nav .badge {
            font-size: 0.75em;
            transform: translateY(-50%);
            top: 40%;
            right: -18px;
            padding: 0.4em 0.7em;
            vertical-align: super; /* Added this for consistency */
        }

        .navbar-nav .dropdown-menu {
            background-color: #FF4500; /* Consistent with navbar color */
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        .navbar-nav .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar-nav .dropdown-item:hover {
            background-color: #ffe0b2; /* Light orange on hover, consistent with promo.php */
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* Navbar badge styles */
        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        /* Ensure heart icon in NAVBAR remains white */
        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important; /* Ensure always white */
        }

        /* Make nama_pelanggan text solid white */
        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important; /* Added for consistency */
        }

        /* Login Form Specific Styles */
        .login-wrapper {
            flex-grow: 1; /* Allow wrapper to take available space */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container.login-container { /* Renamed to avoid conflict */
            background: rgba(255, 255, 255, 0.95);
            padding: 40px 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            margin: 50px auto; /* Added margin to center it vertically when content is small */
        }
        .form-control:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 0.2rem rgba(249, 115, 22, 0.25);
        }
        .form-group {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .form-group i {
            position: absolute;
            top: 50%; /* Adjusted from 72% for better alignment */
            left: 12px;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #888;
            z-index: 2;
            pointer-events: none;
            line-height: 1;
        }
        .form-control {
            padding-left: 40px !important;
            height: 45px;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .btn-primary {
            background-color: #f97316;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #d8570c;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
        }
        .password-container {
            position: relative;
            width: 100%;
        }
        .password-container .form-control {
            padding-right: 40px !important;
        }
        .password-container i.fa-lock {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #888;
            z-index: 2;
            pointer-events: none;
            line-height: 1;
        }
        .password-container .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ccc;
            z-index: 3;
        }
        .container .d-flex.justify-content-between.mt-3 a,
        .container p a {
            color: #f97316 !important;
        }
        .container .d-flex.justify-content-between.mt-3 a:hover,
        .container p a:hover {
            color: #d8570c !important;
        }

        /* Footer Styling (Copied from artikel.php) */
        .footer {
            background-color: #FF4500; /* Consistent with navbar */
            color: white;
            padding: 2rem 0;
            margin-top: auto; /* Push footer to the bottom */
            /* ADDED: Ensure footer itself spans full width */
            width: 100%;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* ADDED: Ensure container-fluid within footer has proper padding */
        /* MENYESUAIKAN PADDING UNTUK KONTEN FOOTER */
        .footer .container-fluid {
            padding-left: 15rem; /* Mengatur padding kiri */
            padding-right: 23rem; /* Mengatur padding kanan */
        }

        .footer p, .footer small {
            color: rgba(255, 255, 255, 0.7);
        }

        .footer h5 {
            color: white;
        }

        .sosmed-icons a {
            color: white;
            font-size: 1.5rem;
            margin: 0 10px;
            transition: transform 0.2s ease-in-out;
        }

        .sosmed-icons a:hover {
            transform: translateY(-3px);
        }

        /* Specific social media icon colors */
        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

        .footer .col-md-4:nth-child(1) {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .footer .col-md-4:nth-child(2) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .footer .col-md-4:nth-child(2) p {
            text-align: center;
        }
        .form-control:focus {
            border-color: #f97316; /* Warna oranye sesuai permintaan */
            box-shadow: 0 0 0 0.2rem rgba(249, 115, 22, 0.25); /* Bayangan oranye sesuai permintaan */
        }
        .form-group {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .form-group i {
            position: absolute;
            top: 72%; /* This was causing the icon to be too low, corrected in login-container styles */
            left: 12px;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #888;
            z-index: 2;
            pointer-events: none;
            line-height: 1;
        }
        .form-control {
            padding-left: 40px !important;
            height: 45px;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .btn-primary {
            background-color: #f97316; /* Warna oranye yang diminta */
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #d8570c; /* Oranye sedikit lebih gelap untuk hover */
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
        }
    </style>
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">BUMDes</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door-fill"></i></a></li>
                <li class="nav-item"><a class="nav-link" href="produk_pembeli.php">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="artikel.php">Artikel</a></li>
                <li class="nav-item"><a class="nav-link active" href="login.php">Login</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="login-wrapper">
    <div class="container login-container">
        <h2 class="text-center mb-4">Login Pengguna BUMDES</h2>

        <?php if ($error_message): ?>
            <div class="error-message mb-3"><?= $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Username</label>
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="password-container"> <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
                    <span id="togglePassword" class="toggle-password fa fa-eye" onclick="togglePasswordVisibility()"></span>
                </div>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>

        <div class="d-flex justify-content-between mt-3">
            
        </div>
        <p class="text-center mt-3">Belum punya akun? <a href="register.php">Daftar di sini</a>.</p>
    </div>
</div>

<footer class="footer py-4 text-white">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                <img src="img/logo.png" alt="Logo Desa" width="80" class="mb-2">
                <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                <p class="text-center">Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
                <div class="sosmed-icons mt-3">
                    <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                    <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                    <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                    <a href="https://www.instagram.com/pekonsinarpetir_?igsh=bWpmbHY4bzV0dW1n"><i class="bi bi-instagram instagram-icon"></i></a>
                    <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                    <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-3 text-center">
                <h5 class="fw-bold text-white"><i class="bi bi-chat-dots"></i> HUBUNGI KAMI</h5>
                <p>Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi Lampung Kode Pos 35377.</p>
                <p><i class="bi bi-telephone-fill"></i> Telepon: 081272020355</p>
                <p><i class="bi bi-envelope-fill"></i> Email: snrpetir@gmail.com</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5 class="fw-bold text-orange"><i class="bi bi-map"></i> PETA LOKASI</h5>
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.100908151834!2d104.5936737!3d-5.2673523!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40e691232c4e23%3A0x6b40e32f5f1c5c1!2sDesa%20Sinar%20Petir!5e0!3m2!1sid!2sid!4v1716347395015!5m2!1sid!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
        <hr class="border-top border-light mt-4">
        <div class="text-center mt-3">
            <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir. Dikelola oleh Tim IT Desa.</small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();
</script>
</body>
</html>