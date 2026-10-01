<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat pesanan Anda.";
    header('Location: ../../login.php'); // Redirect ke halaman login
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$pesanan_customer = [];

// --- Bagian untuk Navbar ---
$user_id = $_SESSION['pengguna_id'];
$nama_pelanggan_navbar = 'Akun';
$foto_pelanggan_navbar = '';

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

// --- Logika Pencarian ---
$search_term = '';
$search_query_param = null;

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = trim($_GET['search']);
    $search_query_param = '%' . $search_term . '%';
}
// --- Akhir Logika Pencarian ---

// --- Logika Paginasi ---
$limit = 7; // Jumlah pesanan per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total pesanan (untuk paginasi) DENGAN PENCARIAN
$query_total_pesanan = "
    SELECT COUNT(DISTINCT p.id) AS total_rows
    FROM pesanan p
    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    LEFT JOIN produk prod ON dp.produk_id = prod.id
    LEFT JOIN penjual pen ON prod.penjual_id = pen.pengguna_id
    WHERE p.pelanggan_id = ?
";

$params_total = [$pengguna_id];
$types_total = "i";

if ($search_query_param !== null) {
    $query_total_pesanan .= "
        AND (
            prod.nama LIKE ? OR
            pen.nama_toko LIKE ? OR
            p.id LIKE ?
        )
    ";
    $params_total[] = $search_query_param;
    $params_total[] = $search_query_param;
    $params_total[] = $search_query_param;
    $types_total .= "sss";
}

$stmt_total_pesanan = $conn->prepare($query_total_pesanan);
if ($stmt_total_pesanan) {
    $stmt_total_pesanan->bind_param($types_total, ...$params_total);
    $stmt_total_pesanan->execute();
    $result_total_pesanan = $stmt_total_pesanan->get_result();
    $total_rows = $result_total_pesanan->fetch_assoc()['total_rows'];
    $stmt_total_pesanan->close();
} else {
    $total_rows = 0;
    error_log("Error preparing total pesanan query: " . $conn->error);
}

$total_pages = ceil($total_rows / $limit);

// Query untuk mengambil pesanan customer dengan paginasi, pencarian, dan status pengembalian
$query_pesanan = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.metode_pembelian AS metode_pembayaran_nama,
        p.bukti_pengiriman,
        pr.status_pengembalian,
        pr.id AS pengembalian_id,
        k.nama AS nama_kurir,
        k.nomor_telepon AS telepon_kurir,
        peng.nomor_resi AS nomor_resi
    FROM
        pesanan p
    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    LEFT JOIN produk prod ON dp.produk_id = prod.id
    LEFT JOIN penjual pen ON prod.penjual_id = pen.pengguna_id
    LEFT JOIN pengembalian_barang pr ON p.id = pr.pesanan_id
    LEFT JOIN kurir k ON p.kurir_id = k.id
    LEFT JOIN pengiriman peng ON p.id = peng.pesanan_id
    WHERE
        p.pelanggan_id = ?
";

$params_pesanan = [$pengguna_id];
$types_pesanan = "i";

if ($search_query_param !== null) {
    $query_pesanan .= "
        AND (
            prod.nama LIKE ? OR
            pen.nama_toko LIKE ? OR
            p.id LIKE ?
        )
    ";
    $params_pesanan[] = $search_query_param;
    $params_pesanan[] = $search_query_param;
    $params_pesanan[] = $search_query_param;
    $types_pesanan .= "sss";
}

$query_pesanan .= "
    GROUP BY p.id
    ORDER BY p.id DESC, p.tanggal_pesanan DESC
    LIMIT ? OFFSET ?;
";

$params_pesanan[] = $limit;
$params_pesanan[] = $offset;
$types_pesanan .= "ii";

$stmt_pesanan = $conn->prepare($query_pesanan);
if ($stmt_pesanan === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat daftar pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing pesanan query: " . $conn->error);
    header('Location: ../index.php');
    exit();
}
$stmt_pesanan->bind_param($types_pesanan, ...$params_pesanan);
$stmt_pesanan->execute();
$result_pesanan = $stmt_pesanan->get_result();

if ($result_pesanan->num_rows > 0) {
    while ($pesanan_row = $result_pesanan->fetch_assoc()) {
        $pesanan_id = $pesanan_row['pesanan_id'];

        // Ambil detail produk untuk setiap pesanan
        $query_detail_pesanan = "
            SELECT
                dp.produk_id,
                dp.variasi_id,
                dp.quantity,
                dp.harga_satuan,
                prod.nama AS nama_produk,
                prod.gambar AS gambar_produk,
                pen.nama_toko,
                r.nama_rasa,
                w.nama_warna,
                u.nama_ukuran
            FROM
                detail_pesanan dp
            JOIN
                produk prod ON dp.produk_id = prod.id
            JOIN
                penjual pen ON prod.penjual_id = pen.pengguna_id
            LEFT JOIN
                produk_variasi pv ON dp.variasi_id = pv.id
            LEFT JOIN
                rasa r ON pv.rasa_id = r.id
            LEFT JOIN
                warna w ON pv.warna_id = w.id
            LEFT JOIN
                ukuran u ON pv.ukuran_id = u.id
            WHERE
                dp.pesanan_id = ?
            ORDER BY pen.nama_toko, prod.nama;
        ";
        $stmt_detail = $conn->prepare($query_detail_pesanan);
        if ($stmt_detail === false) {
            error_log("Error preparing detail pesanan query: " . $conn->error);
            continue;
        }
        $stmt_detail->bind_param("i", $pesanan_id);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $grouped_items_per_toko = [];

        if ($result_detail->num_rows > 0) {
            while ($item_row = $result_detail->fetch_assoc()) {
                $variasi_detail = [];
                if (!empty($item_row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($item_row['nama_rasa']); }
                if (!empty($item_row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($item_row['nama_warna']); }
                if (!empty($item_row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($item_row['nama_ukuran']); }

                $item_data = [
                    'produk_id' => $item_row['produk_id'],
                    'variasi_id' => $item_row['variasi_id'],
                    'nama_produk' => htmlspecialchars($item_row['nama_produk']),
                    'gambar_produk' => htmlspecialchars($item_row['gambar_produk']),
                    'quantity' => $item_row['quantity'],
                    'harga_satuan' => $item_row['harga_satuan'],
                    'variasi_string' => implode(', ', $variasi_detail)
                ];

                if (!isset($grouped_items_per_toko[$item_row['nama_toko']])) {
                    $grouped_items_per_toko[$item_row['nama_toko']] = [];
                }
                $grouped_items_per_toko[$item_row['nama_toko']][] = $item_data;
            }
        }
        $stmt_detail->close();

        $pesanan_row['grouped_items'] = $grouped_items_per_toko;
        $pesanan_customer[] = $pesanan_row;
    }
}
$stmt_pesanan->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - BUMDes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            height: auto;
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
        .status-sudah-dibayar { background-color: #28a745; color: #fff; } /* Green for paid */
        .status-menunggu-verifikasi { background-color: #fd7e14; color: #fff; } /* Orange */
        .status-dikemas { background-color: #6f42c1; color: #fff; } /* Purple for packed */
        .status-diproses { background-color: #0dcaf0; color: #fff; } /* Cyan */
        .status-dikirim { background-color: #0d6efd; color: #fff; } /* Blue */
        .status-selesai { background-color: #198754; color: #fff; } /* Green */
        .status-dibatalkan { background-color: #dc3545; color: #fff; } /* Red */
        .status-dikembalikan { background-color: #6c757d; color: #fff; } /* Gray */

        /* NEW: Status Pengembalian */
        .status-pengembalian-diajukan { background-color: #0d6efd; color: #fff; } /* Blue */
        .status-pengembalian-disetujui { background-color: #198754; color: #fff; } /* Green */
        .status-pengembalian-ditolak { background-color: #dc3545; color: #fff; } /* Red */
        .status-pengembalian-diproses { background-color: #ffc107; color: #343a40; } /* Yellow */
        .status-pengembalian-selesai_pengembalian { background-color: #6c757d; color: #fff; } /* Gray */

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
        /* Dalam file CSS Anda atau di dalam tag <style> di <head> */
        /* Warna untuk link paginasi normal */
        .pagination .page-link {
            color: #FF4500; /* Warna teks link */
        }
        /* Warna border untuk link paginasi normal */
        .pagination .page-item .page-link {
            border-color: #dee2e6; /* Border default Bootstrap */
        }

        /* Warna saat hover pada link paginasi */
        .pagination .page-item .page-link:hover {
            color: #e63e00; /* Sedikit lebih gelap dari #FF4500 saat hover */
            background-color: #ffe8e0; /* Background terang saat hover */
            border-color: #e63e00; /* Border saat hover */
        }

        /* Warna untuk item paginasi yang aktif (halaman saat ini) */
        .pagination .page-item.active .page-link {
            background-color: #FF4500; /* Warna latar belakang untuk halaman aktif */
            border-color: #FF4500; /* Warna border untuk halaman aktif */
            color: white; /* Warna teks untuk halaman aktif */
        }

        /* Warna untuk item paginasi yang disable (misal tombol Previous di halaman 1) */
        .pagination .page-item.disabled .page-link {
            color: #6c757d; /* Warna teks abu-abu */
            pointer-events: none; /* Tidak bisa diklik */
            background-color: #fff; /* Latar belakang putih */
            border-color: #dee2e6; /* Border default Bootstrap */
        }

        /* Mengatasi fokus pada link paginasi */
        .pagination .page-item .page-link:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 69, 0, 0.25); /* Shadow dengan warna #FF4500 */
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
            <form class="d-flex me-2 mb-2" role="search" action="pesanan_saya.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari pesanan(produk/toko/ID)..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
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
    <div class="container my-4">
        <h2 class="mb-5 text-center" style="color: black;">Pesanan Saya</h2>
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

    <?php if (empty($pesanan_customer) && $total_rows == 0): ?>
        <div class="alert alert-info text-center" role="alert">
            Anda belum memiliki pesanan. Mulai jelajahi produk kami!
            <a href="../produk.php" class="alert-link">Belanja Sekarang</a>
        </div>
    <?php elseif (empty($pesanan_customer) && $total_rows > 0): ?>
        <div class="alert alert-info text-center" role="alert">
            Tidak ada pesanan yang cocok dengan "<?php echo htmlspecialchars($search_term); ?>".
            <br>
            <a href="pesanan_saya.php" class="btn btn-primary mt-2">Lihat Semua Pesanan</a>
        </div>
    <?php else: ?>
        <?php foreach ($pesanan_customer as $order): ?>
            <div class="order-card mb-4">
                <div class="order-header">
                    <div>
                        <h5 class="mb-0">Pesanan #<?php echo htmlspecialchars($order['pesanan_id']); ?></h5>
                        <small class="text-muted">Tanggal: <?php echo date('d M Y, H:i', strtotime($order['tanggal_pesanan'])); ?></small>
                        
                        <?php if ($order['status_pesanan'] === 'dikirim'): ?>
                            <?php if (!empty($order['nama_kurir']) || !empty($order['telepon_kurir'])): ?>
                                <div class="mt-2">
                                    <small class="d-block">
                                        <i class="fas fa-truck me-1"></i> Kurir: 
                                        <strong><?php echo htmlspecialchars($order['nama_kurir'] ?? 'N/A'); ?></strong>
                                    </small>
                                    <small class="d-block">
                                        <i class="fas fa-phone me-1"></i> Telepon: 
                                        <?php if (!empty($order['telepon_kurir'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($order['telepon_kurir']); ?>">
                                                <?php echo htmlspecialchars($order['telepon_kurir']); ?>
                                            </a>
                                            <a href="https://wa.me/<?php echo preg_replace('/^08/', '628', htmlspecialchars($order['telepon_kurir'])); ?>?text=Halo%20kurir,%20saya%20ingin%20menanyakan%20status%20pesanan%20ID:%20<?php echo $order['pesanan_id']; ?>.%20Nomor%20resi%20saya%20adalah:%20<?php echo urlencode($order['nomor_resi']); ?>"
                                               target="_blank" class="btn btn-sm btn-success ms-2 py-0 px-2" style="font-size: 0.75rem;">
                                                <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status_pesanan'])); ?>">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pesanan']))); ?>
                        </span>
                        
                        <?php if (!empty($order['status_pengembalian'])): ?>
                            <br>
                            <span class="status-badge mt-1 status-pengembalian-<?php echo strtolower($order['status_pengembalian']); ?>">
                                Pengembalian: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pengembalian']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="order-body">
                    <?php if (empty($order['grouped_items'])): ?>
                        <p class="text-muted text-center">Tidak ada detail produk untuk pesanan ini.</p>
                    <?php else: ?>
                        <?php foreach ($order['grouped_items'] as $nama_toko => $items_per_toko): ?>
                            <div class="store-section">
                                <div class="store-header">
                                    <i class="fas fa-store me-2"></i>Toko: <?php echo htmlspecialchars($nama_toko); ?>
                                </div>
                                <?php foreach ($items_per_toko as $item): ?>
                                    <div class="product-item">
                                        <?php if (!empty($item['gambar_produk'])): ?>
                                            <img src="../../img/barang/<?php echo htmlspecialchars($item['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 5px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['nama_produk']); ?></h6>
                                            <?php if (!empty($item['variasi_string'])): ?>
                                                <small class="text-muted"><?php echo $item['variasi_string']; ?></small>
                                            <?php endif; ?>
                                            <p class="mb-0 text-muted">
                                                Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?> x <?php echo $item['quantity']; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="order-summary-footer">
                    <span>Metode Pembayaran: <?php echo htmlspecialchars($order['metode_pembayaran_nama']); ?></span>
                    <span>Total Pesanan: Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></span>
                </div>
                <div class="order-actions">
                    <?php if ($order['status_pesanan'] === 'menunggu_pembayaran'): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="window.location.href='konfirmasi_pembayaran.php?order_id=<?php echo $order['pesanan_id']; ?>'">Konfirmasi Pembayaran</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) { window.location.href='batalkan_pesanan.php?order_id=<?php echo $order['pesanan_id']; ?>'; }">Batalkan Pesanan</button>
                    <?php elseif ($order['status_pesanan'] === 'dikirim'): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="if(confirm('Konfirmasi bahwa pesanan ini sudah sampai?')) { window.location.href='konfirmasi_sampai.php?order_id=<?php echo $order['pesanan_id']; ?>'; }">Konfirmasi Pesanan Sampai</button>

                        <?php if (!empty($order['nomor_resi'])): ?>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <button class="btn btn-sm btn-info text-white lacak-pesanan-btn"
                                        data-nomor-resi="<?php echo htmlspecialchars($order['nomor_resi']); ?>"
                                        data-nama-kurir="<?php echo htmlspecialchars($order['nama_kurir']); ?>">
                                    <i class="fas fa-truck me-1"></i> Lacak Pesanan
                                </button>
                                <div class="tracking-status-container" id="tracking-status-<?php echo htmlspecialchars($order['pesanan_id']); ?>">
                                    </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($order['status_pesanan'] === 'selesai'): ?>
                        <button type="button" class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#buktiPengirimanModal"
                                data-bukti-pengiriman-src="../../img/bukti_pengiriman/<?php echo htmlspecialchars($order['bukti_pengiriman']); ?>">
                                Lihat Bukti Pengiriman
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='beli_lagi.php?order_id=<?php echo $order['pesanan_id']; ?>'">Beli Lagi</button>

                        <?php if (empty($order['status_pengembalian'])): ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="window.location.href='ajukan_pengembalian.php?order_id=<?php echo $order['pesanan_id']; ?>'">Ajukan Pengembalian</button>
                        <?php elseif ($order['status_pengembalian'] === 'diajukan' || $order['status_pengembalian'] === 'disetujui' || $order['status_pengembalian'] === 'diproses'): ?>
                             <button type="button" class="btn btn-info btn-sm" onclick="window.location.href='detail_pengajuan_pengembalian.php?pengembalian_id=<?php echo $order['pengembalian_id']; ?>'">Lihat Status Pengembalian</button>
                        <?php elseif ($order['status_pengembalian'] === 'ditolak'): ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.location.href='detail_pengajuan_pengembalian.php?pengembalian_id=<?php echo $order['pengembalian_id']; ?>'">Pengembalian Ditolak</button>
                            <?php elseif ($order['status_pengembalian'] === 'selesai_pengembalian'): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='detail_pengajuan_pengembalian.php?pengembalian_id=<?php echo $order['pengembalian_id']; ?>'">Pengembalian Selesai</button>
                        <?php endif; ?>

                        <button type="button" class="btn btn-info btn-sm" onclick="window.location.href='ulas_produk.php?order_id=<?php echo $order['pesanan_id']; ?>'">Ulas Produk</button>
                    <?php endif; ?>

                    <?php
                        $show_invoice_button = false;
                        $metode_pembayaran_lower = strtolower($order['metode_pembayaran_nama']);

                        if ($metode_pembayaran_lower !== 'cod') {
                            if ($order['status_pesanan'] === 'menunggu_pembayaran' || $order['status_pesanan'] === 'diproses') {
                                $show_invoice_button = true;
                            }
                        }
                    ?>
                    <?php if ($show_invoice_button): ?>
                        <a href="invoice.php?order_id=<?php echo $order['pesanan_id']; ?>" target="_blank" class="btn btn-warning btn-sm">
                            <i class="bi bi-file-earmark-text-fill"></i> Lihat Invoice
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation example" class="d-flex justify-content-center mt-4">
                <ul class="pagination">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>" aria-label="Next">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
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
    <div class="modal fade" id="buktiPengirimanModal" tabindex="-1" aria-labelledby="buktiPengirimanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="buktiPengirimanModalLabel">Bukti Pengiriman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="buktiPengirimanImage" src="" alt="Bukti Pengiriman" class="img-fluid" style="max-height: 80vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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

    // Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX (Disalin dari promo.php/artikel.php)
    function updateWishlistItemCount() {
        $.ajax({
            url: '../toggle_wishlist.php?action=get_count', // Path disesuaikan
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#wishlist-count').text(response.wishlist_count);
                } else {
                    console.error('Gagal memuat jumlah wishlist di navbar:', response.message);
                    $('#wishlist-count').text('0');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error (get_wishlist_count):', status, error);
                $('#wishlist-count').text('0');
            }
        });
    }

    $(document).ready(function() {
        // Panggil fungsi update saat halaman dimuat
        muatJumlahKeranjangNav();
        updateWishlistItemCount();
        
        // Event handler untuk tombol "Lacak Pesanan"
        $('.lacak-pesanan-btn').on('click', function() {
            const button = $(this);
            const nomorResi = button.data('nomor-resi');
            const namaKurir = button.data('nama-kurir');
            const trackingContainer = button.closest('.order-actions').find('.tracking-status-container');
            
            // Tampilkan loading saat proses pelacakan
            trackingContainer.html('<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Sedang melacak pesanan...</div>');
            button.prop('disabled', true); // Nonaktifkan tombol saat loading

            // Panggil endpoint API pelacakan yang baru dibuat
            $.ajax({
                url: 'get_tracking_status.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    nomor_resi: nomorResi,
                    nama_kurir: namaKurir
                },
                success: function(response) {
                    if (response.status === 'success') {
                        const trackingData = response.data;
                        const statusText = trackingData.deskripsi_status;
                        const updateTime = trackingData.waktu_update;

                        // Tampilkan status terbaru
                        trackingContainer.html(`
                            <small class="d-block mt-1 text-muted">
                                Status Pengiriman: <strong>${statusText}</strong>
                                <br>
                                (Update: ${updateTime})
                            </small>
                        `);
                    } else {
                        trackingContainer.html('<div class="text-danger">Gagal melacak pesanan. Silakan coba lagi.</div>');
                        console.error('Error from API:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    trackingContainer.html('<div class="text-danger">Gagal menghubungi server pelacakan.</div>');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    button.prop('disabled', false); // Aktifkan kembali tombol setelah selesai
                }
            });
        });
    });

    // JavaScript untuk menampilkan gambar bukti pengiriman di modal
    const buktiPengirimanModal = document.getElementById('buktiPengirimanModal');
    if (buktiPengirimanModal) {
        buktiPengirimanModal.addEventListener('show.bs.modal', event => {
            // Button yang memicu modal
            const button = event.relatedTarget;
            // Ambil informasi dari atribut data-bs-*
            const imageUrl = button.getAttribute('data-bukti-pengiriman-src');

            // Perbarui src gambar di dalam modal
            const modalImage = buktiPengirimanModal.querySelector('#buktiPengirimanImage');
            modalImage.src = imageUrl;
        });
    }
    function updateWishlistItemCount() {
            // Sama seperti updateCartItemCount, ini adalah 'placeholder'.
            // Badge wishlist di navbar akan menampilkan nilai yang sudah dihitung oleh PHP saat page load.
            // Jika Anda ingin badge wishlist update secara dinamis tanpa refresh halaman,
            // Anda harus menambahkan AJAX request ke endpoint yang menghitung wishlist.
            // Saya tidak menambahkan AJAX baru sesuai instruksi Anda.
    }
</script>

</body>
</html>