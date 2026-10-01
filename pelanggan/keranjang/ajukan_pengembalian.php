<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk mengkonfirmasi pembayaran.";
    header('Location: ../../login.php');
    exit();
}

$order_id = $_GET['order_id'] ?? null; // Gunakan null coalescing operator untuk handle jika order_id tidak ada
$pengguna_id = $_SESSION['pengguna_id']; // ID pelanggan yang login

// --- Bagian untuk Navbar (PASTIKAN INI ADA UNTUK MENGHINDARI NOTICE UNDEFINED VARIABLE) ---
// Inisialisasi variabel untuk navbar
$user_id = $_SESSION['pengguna_id'];
$nama_pelanggan_navbar = 'Akun';
$foto_pelanggan_navbar = '';
$search_term = ''; 

// Ambil data pelanggan untuk navbar
$query_pelanggan_navbar = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
$stmt_pelanggan_navbar = $conn->prepare($query_pelanggan_navbar);
if ($stmt_pelanggan_navbar) {
    $stmt_pelanggan_navbar->bind_param("i", $user_id);
    $stmt_pelanggan_navbar->execute();
    $result_pelanggan_navbar = $stmt_pelanggan_navbar->get_result();
    if ($pelanggan_data_navbar = $result_pelanggan_navbar->fetch_assoc()) {
        $nama_pelanggan_navbar = $pelanggan_data_navbar['nama'];
        $foto_pelanggan_navbar = $pelanggan_data_navbar['foto'];
    }
    $stmt_pelanggan_navbar->close();
}

// Ambil jumlah item di keranjang untuk badge navbar
$total_item_keranjang_badge = 0;
$query_cart_count = "SELECT COUNT(*) AS total_count FROM keranjang_customer WHERE customer_id = ?";
$stmt_cart_count = $conn->prepare($query_cart_count);
if ($stmt_cart_count) {
    $stmt_cart_count->bind_param("i", $user_id);
    $stmt_cart_count->execute();
    $result_cart_count = $stmt_cart_count->get_result();
    if ($row_cart_count = $result_cart_count->fetch_assoc()) {
        $total_item_keranjang_badge = $row_cart_count['total_count'];
    }
    $stmt_cart_count->close();
}

// Ambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0;
$query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
$stmt_wishlist_count = $conn->prepare($query_wishlist_count);
if ($stmt_wishlist_count) {
    $stmt_wishlist_count->bind_param("i", $user_id);
    $stmt_wishlist_count->execute();
    $result_wishlist_count = $stmt_wishlist_count->get_result();
    if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
        $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
    }
    $stmt_wishlist_count->close();
}
// --- Akhir Bagian Navbar ---


// Cek apakah order_id diberikan melalui parameter GET
if ($order_id === null) { // Ganti isset($_GET['order_id']) dengan $order_id === null
    $_SESSION['error_message'] = "ID Pesanan tidak ditemukan.";
    header('Location: pesanan_saya.php');
    exit();
}

// Cek apakah order_id diberikan melalui parameter GET
if (!isset($_GET['order_id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak ditemukan.";
    header('Location: pesanan_saya.php');
    exit();
}

//sampe sini jangan di copy
$order_id = $_GET['order_id'];
$pelanggan_id = $_SESSION['pengguna_id'];

// 1. Verifikasi kepemilikan pesanan dan status
// Hanya izinkan pengajuan pengembalian untuk pesanan yang sudah 'selesai'
$query_check_order = "SELECT status_pesanan FROM pesanan WHERE id = ? AND pelanggan_id = ?";
$stmt_check_order = $conn->prepare($query_check_order);
if ($stmt_check_order === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memeriksa pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing check order query in ajukan_pengembalian.php: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}
$stmt_check_order->bind_param("ii", $order_id, $pelanggan_id);
$stmt_check_order->execute();
$result_check_order = $stmt_check_order->get_result();
$order_data = $result_check_order->fetch_assoc();
$stmt_check_order->close();

if (!$order_data) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: pesanan_saya.php');
    exit();
}

// Cek status pesanan. Hanya yang 'selesai' yang bisa diajukan pengembalian.
if ($order_data['status_pesanan'] !== 'selesai') {
    $_SESSION['error_message'] = "Pengajuan pengembalian hanya bisa dilakukan untuk pesanan yang berstatus 'selesai'. Status saat ini: " . htmlspecialchars($order_data['status_pesanan']);
    header('Location: pesanan_saya.php');
    exit();
}

// Inisialisasi variabel untuk nilai form (jika ada error, nilai ini akan diisi kembali)
$alasan_pengembalian_val = '';
$errors = []; // Inisialisasi array error

// Proses ketika form dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alasan_pengembalian = htmlspecialchars(trim($_POST['alasan_pengembalian']));

    // Simpan nilai input untuk ditampilkan kembali jika ada error
    $alasan_pengembalian_val = $alasan_pengembalian;

    // Validasi input
    if (empty($alasan_pengembalian)) {
        $errors[] = "Alasan pengembalian tidak boleh kosong.";
    }

    $bukti_foto_path = null; // Untuk bukti_foto_cacat
    $bukti_video_path = null; // Untuk bukti_video_cacat
    $upload_dir = '../../img/bukti_pengembalian/'; // Pastikan direktori ini ada dan writable

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
    }

    $allowed_image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $allowed_video_extensions = ['mp4', 'mov', 'avi', 'webm']; // Tambahkan ekstensi video yang diizinkan
    $max_image_size = 5 * 1024 * 1024; // 5 MB
    $max_video_size = 20 * 1024 * 1024; // 20 MB (sesuaikan jika perlu)

    // Fungsi helper untuk upload file
    function upload_file($file_input_name, $allowed_extensions, $max_size, $upload_dir, &$errors, $prefix = 'bukti_') {
        global $order_id; // Akses $order_id dari scope luar
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                $errors[] = "Format file untuk '{$file_input_name}' tidak didukung. Hanya " . implode(', ', $allowed_extensions) . " yang diperbolehkan.";
                return null;
            }
            if ($_FILES[$file_input_name]['size'] > $max_size) {
                $errors[] = "Ukuran file untuk '{$file_input_name}' terlalu besar. Maksimal " . ($max_size / (1024 * 1024)) . " MB.";
                return null;
            }

            $new_file_name = uniqid($prefix) . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $target_file)) {
                return 'bukti_pengembalian/' . $new_file_name; // Path relatif untuk database
            } else {
                $errors[] = "Gagal mengupload file '{$file_input_name}'. Kode Error: " . $_FILES[$file_input_name]['error'];
                error_log("File upload failed for order {$order_id} ({$file_input_name}). Error: " . $_FILES[$file_input_name]['error']);
                return null;
            }
        }
        return null; // Tidak ada file atau ada error upload lainnya
    }

    // Upload bukti foto (akan masuk ke bukti_foto_cacat)
    $bukti_foto_path = upload_file('bukti_foto', $allowed_image_extensions, $max_image_size, $upload_dir, $errors, 'foto_');
    if (!$bukti_foto_path && $_FILES['bukti_foto']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Bukti foto harus diunggah.";
    }

    // Upload bukti video (akan masuk ke bukti_video_cacat)
    $bukti_video_path = upload_file('bukti_video', $allowed_video_extensions, $max_video_size, $upload_dir, $errors, 'video_');


    // Jika tidak ada error validasi, simpan ke database
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insert data ke tabel pengembalian_barang
            $query_insert_return = "
                INSERT INTO pengembalian_barang (
                    pesanan_id, pelanggan_id, alasan_pengembalian,
                    bukti_foto_cacat, bukti_video_cacat, status_pengembalian
                ) VALUES (?, ?, ?, ?, ?, 'diajukan')
            ";
            $stmt_insert_return = $conn->prepare($query_insert_return);
            if ($stmt_insert_return === false) {
                throw new Exception("Gagal menyiapkan statement insert pengajuan pengembalian: " . $conn->error);
            }
            $stmt_insert_return->bind_param("iisss",
                $order_id,
                $pelanggan_id,
                $alasan_pengembalian,
                $bukti_foto_path,  // Masuk ke bukti_foto_cacat
                $bukti_video_path  // Masuk ke bukti_video_cacat
            );

            if (!$stmt_insert_return->execute()) {
                throw new Exception("Gagal menyimpan pengajuan pengembalian: " . $stmt_insert_return->error);
            }
            $stmt_insert_return->close();

            // Opsional: Update status pesanan di tabel 'pesanan' menjadi 'diajukan_retur'
            // Anda perlu menambahkan status ini di ENUM kolom status_pesanan tabel pesanan
            // ALTER TABLE pesanan MODIFY COLUMN status_pesanan ENUM('menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'diajukan_retur', 'ditolak_retur', 'retur_diproses', 'retur_selesai') NOT NULL;
            $query_update_order_status = "UPDATE pesanan SET status_pesanan = 'diajukan_retur' WHERE id = ?";
            $stmt_update_order_status = $conn->prepare($query_update_order_status);
            if ($stmt_update_order_status === false) {
                throw new Exception("Gagal menyiapkan statement update status pesanan: " . $conn->error);
            }
            $stmt_update_order_status->bind_param("i", $order_id);
            if (!$stmt_update_order_status->execute()) {
                throw new Exception("Gagal mengupdate status pesanan menjadi diajukan_retur: " . $stmt_update_order_status->error);
            }
            $stmt_update_order_status->close();


            $conn->commit(); // Commit transaksi jika semua berhasil
            $_SESSION['success_message'] = "Pengajuan pengembalian untuk pesanan #{$order_id} berhasil diajukan. Mohon tunggu proses peninjauan oleh admin/penjual.";
            header('Location: pesanan_saya.php'); // Redirect kembali ke daftar pesanan
            exit();

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaksi jika ada error
            $_SESSION['error_message'] = "Terjadi kesalahan saat mengajukan pengembalian: " . $e->getMessage();
            error_log("Error ajukan_pengembalian: " . $e->getMessage());
            // Hapus file bukti yang sudah diupload jika terjadi error database
            if ($bukti_foto_path && file_exists('../../img/' . $bukti_foto_path)) unlink('../../img/' . $bukti_foto_path);
            if ($bukti_video_path && file_exists('../../img/' . $bukti_video_path)) unlink('../../img/' . $bukti_video_path);
        } finally {
            if ($conn && $conn->ping()) {
                $conn->close();
            }
        }
    } else {
        // Jika ada error validasi, simpan pesan error ke session
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
} else {
    // Jika ini adalah GET request awal, bersihkan pesan error/sukses sebelumnya
    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);
}

// Tutup koneksi jika belum ditutup
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Pengembalian Pesanan #<?php echo htmlspecialchars($order_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
            /* General Body Styling */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Navbar Styling (Sama dengan promo.php & artikel.php) */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
            color: white !important; /* Pastikan brand juga putih */
        }

        .navbar-brand img {
            margin-right: 8px;
            max-width: 30px;
        }

        .nav-link {
            color: white !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important;
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
            vertical-align: super;
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        .navbar-nav .dropdown-menu {
            background-color: #FF4500;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        .navbar-nav .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar-nav .dropdown-item:hover {
            background-color: #ffe0b2;
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important;
        }

        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }

        /* Order Page Specific Styles (dari kode Anda) */
        .order-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .order-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-body {
            padding: 20px;
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        .product-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .product-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .order-summary-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            text-transform: capitalize;
        }
        /* Update status colors as needed, pastikan 'menunggu_verifikasi' ditambahkan */
        .status-menunggu-pembayaran { background-color: #ffc107; color: #343a40; } /* Yellow */
        .status-menunggu-verifikasi { background-color: #fd7e14; color: #fff; } /* Orange */
        .status-diproses { background-color: #0dcaf0; color: #fff; } /* Cyan */
        .status-dikirim { background-color: #0d6efd; color: #fff; } /* Blue */
        .status-selesai { background-color: #198754; color: #fff; } /* Green */
        .status-dibatalkan { background-color: #dc3545; color: #fff; } /* Red */
        .status-dikembalikan { background-color: #6c757d; color: #fff; } /* Gray */

        .store-section {
            border: 1px solid #f0f0f0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fff;
        }
        .store-section .store-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #343a40;
        }
        .order-actions {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* Footer Styling (Sama dengan promo.php & artikel.php) */
        .footer {
            background-color: #FF4500; /* Consistent with navbar */
            color: white;
            padding: 2rem 0;
            margin-top: auto;
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
        .form-label {
            font-weight: bold;
        }
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            font-weight: bold;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <img src="../../img/logoo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
            BUMDes
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli" aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarPembeli">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../produk.php">Produk</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../kategori/kategori.php">Kategori</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../promo/promo.php">Promo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../artikel/artikel.php">Artikel</a>
                </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../wishlist.php">
                        <i class="bi bi-heart-fill"></i>
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                            <?php echo $total_item_wishlist; ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="position-relative">
                        <a class="nav-link" href="keranjang.php" id="link-keranjang">
                            <i class="bi bi-cart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                <?php echo $total_item_keranjang_badge; ?>
                            </span>
                        </a>
                        <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2" style="display: none; width: 430px; z-index: 1000; right: 0; left: auto; min-width: 280px; background-color: white; border-radius: 5px; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; border: 1px solid #eee;">
                            <h5>Baru Ditambahkan</h5>
                            <ul class="list-unstyled" id="daftar-produk-keranjang">
                                <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja kosong.</li>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                                <a href="keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan_navbar) && $foto_pelanggan_navbar && $foto_pelanggan_navbar != 'default.png'): ?>
                                <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan_navbar); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan_navbar); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item active" href="pesanan_saya.php">Pesanan Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5 mb-5">
    <h2 class="mb-4">Ajukan Pengembalian Pesanan #<?php echo htmlspecialchars($order_id); ?></h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Anda mengajukan pengembalian untuk pesanan ini. Mohon lengkapi formulir di bawah.
    </div>

    <div class="card p-4">
        <form action="ajukan_pengembalian.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="alasan_pengembalian" class="form-label">Alasan Pengembalian</label>
                <textarea class="form-control" id="alasan_pengembalian" name="alasan_pengembalian" rows="5" required><?php echo htmlspecialchars($alasan_pengembalian_val); ?></textarea>
                <small class="form-text text-muted">Jelaskan mengapa Anda ingin mengembalikan pesanan ini (misal: produk rusak, tidak sesuai, dll.).</small>
            </div>
            <div class="mb-3">
                <label for="bukti_foto" class="form-label">Bukti Foto <span class="text-danger">*</span></label>
                <input class="form-control" type="file" id="bukti_foto" name="bukti_foto" accept="image/*" required>
                <small class="form-text text-muted">Unggah foto jelas yang menunjukkan kondisi produk (maks. 5MB, JPG, PNG, GIF).</small>
            </div>
            <div class="mb-3">
                <label for="bukti_video" class="form-label">Bukti Video (Opsional)</label>
                <input class="form-control" type="file" id="bukti_video" name="bukti_video" accept="video/*">
                <small class="form-text text-muted">Unggah video jika relevan (maks. 20MB, MP4, MOV, AVI, WEBM).</small>
            </div>
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-paper-plane me-1"></i> Ajukan Pengembalian</button>
            <a href="pesanan_saya.php" class="btn btn-secondary"><i class="fas fa-times-circle me-1"></i> Batal</a>
        </form>
    </div>
</div>
<footer class="footer py-4 text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                    <img src="../../img/logoo.png" alt="Logo Desa" width="80" class="mb-2">
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
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
        AOS.init();

    // Fungsi formatRupiah (sama dengan promo.php & artikel.php)
    function formatRupiah(angka) {
        let number = parseFloat(angka);
        if (isNaN(number)) {
            console.error("Input ke formatRupiah bukan angka yang valid:", angka);
            return "0";
        }
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    }

    // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX (Disalin dari promo.php/artikel.php)
    function muatJumlahKeranjangNav() {
        $.ajax({
            url: 'get_cart_count.php', // Path relatif terhadap keranjang/
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#jumlah-keranjang').text(response.count);
                } else {
                    console.error('Gagal memuat jumlah keranjang di navbar:', response.message);
                    $('#jumlah-keranjang').text('0');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error (get_cart_count):', status, error);
                $('#jumlah-keranjang').text('0');
            }
        });
    }

    /**
     * Fungsi untuk memuat detail item di dropdown keranjang.
     * Mengambil data keranjang dari backend menggunakan Fetch API dan menampilkannya di UI.
     * (Disalin dari promo.php/artikel.php)
     */
    function muatIsiKeranjangDropdown() {
        const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
        const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
        const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

        jumlahProdukLainnyaSpan.style.display = 'none';
        jumlahProdukLainnyaSpan.textContent = '';

        fetch('ambil_keranjang_sementara.php') // Path relatif terhadap keranjang/
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                daftarProdukKeranjang.innerHTML = '';

                const displayLimit = 3;
                let totalQuantityOtherProducts = 0;

                if (data.length > 0) {
                    if (pesanKeranjangKosong) {
                        pesanKeranjangKosong.style.display = 'none';
                    }

                    data.forEach((item, index) => {
                        if (index < displayLimit) {
                            const listItem = document.createElement('li');
                            listItem.classList.add('d-flex', 'align-items-center', 'mb-2');
                            listItem.innerHTML = `
                                <img src="../../img/barang/${item.gambar_produk}" alt="${item.nama_produk}" class="img-fluid rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <span class="d-block text-truncate" style="max-width: 150px; font-size: 0.85rem;">${item.nama_produk}</span>
                                    ${item.variasi_string ? `<small class="d-block text-muted" style="font-size: 0.75rem;">(${item.variasi_string})</small>` : ''}
                                </div>
                                <div class="ms-auto text-end">
                                    <span class="d-block text-danger fw-bold" style="font-size: 0.9rem;">Rp${formatRupiah(item.harga_satuan)}</span>
                                    <small class="text-muted" style="font-size: 0.8rem;">x${item.quantity}</small>
                                </div>
                            `;
                            daftarProdukKeranjang.appendChild(listItem);
                        } else {
                            totalQuantityOtherProducts += item.quantity;
                        }
                    });

                    if (totalQuantityOtherProducts > 0) {
                        jumlahProdukLainnyaSpan.textContent = `${totalQuantityOtherProducts} Produk Lainnya`;
                        jumlahProdukLainnyaSpan.style.display = 'inline-block';
                    } else {
                        jumlahProdukLainnyaSpan.style.display = 'none';
                    }

                } else {
                    if (pesanKeranjangKosong) {
                        pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                        pesanKeranjangKosong.style.display = 'block';
                    }
                    jumlahProdukLainnyaSpan.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching cart items for dropdown:', error);
                if (pesanKeranjangKosong) {
                    pesanKeranjangKosong.textContent = "Gagal memuat detail keranjang. Silakan coba lagi.";
                    pesanKeranjangKosong.style.display = 'block';
                }
                jumlahProdukLainnyaSpan.style.display = 'none';
            });
    }

    // Event listener untuk menampilkan/menyembunyikan dropdown keranjang (Disalin dari promo.php/artikel.php)
    document.addEventListener('DOMContentLoaded', () => {
        const linkKeranjang = document.getElementById('link-keranjang');
        const dropdownKeranjang = document.getElementById('dropdown-keranjang');

        muatJumlahKeranjangNav(); // Panggil saat DOM dimuat
        // muatIsiKeranjangDropdown(); // Tidak perlu panggil di sini, akan dipanggil saat mouseenter

        if (linkKeranjang && dropdownKeranjang) {
            linkKeranjang.addEventListener('mouseenter', () => {
                muatIsiKeranjangDropdown(); // Muat ulang isi dropdown setiap kali mouse masuk
                dropdownKeranjang.style.display = 'block';
            });

            dropdownKeranjang.addEventListener('mouseleave', () => {
                dropdownKeranjang.style.display = 'none';
            });

            document.addEventListener('click', (event) => {
                if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                    dropdownKeranjang.style.display = 'none';
                }
            });
        }
    });

</script>

</body>
</html>