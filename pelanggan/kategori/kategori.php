<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// --- Bagian PHP untuk Data Navbar (Disamakan dengan wishlist.php untuk profil) ---
$customer_id = $_SESSION['pengguna_id'] ?? null;
$nama_pelanggan = null;
$foto_pelanggan = null;

if ($customer_id) {
    // Sesuaikan query ini agar mengambil dari tabel 'pelanggan' menggunakan 'pengguna_id'
    // Sama seperti di wishlist.php
    $query_user_info = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
    $stmt_user_info = $conn->prepare($query_user_info);
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $customer_id);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($user_info = $result_user_info->fetch_assoc()) {
            $nama_pelanggan = $user_info['nama'];
            $foto_pelanggan = $user_info['foto'];
        }
        $stmt_user_info->close();
    }
}

$total_produk_di_keranjang = 0;
if ($customer_id) {
    $query_cart_count = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?";
    $stmt_cart_count = $conn->prepare($query_cart_count);
    if ($stmt_cart_count) {
        $stmt_cart_count->bind_param("i", $customer_id);
        $stmt_cart_count->execute();
        $result_cart_count = $stmt_cart_count->get_result();
        if ($row_cart_count = $result_cart_count->fetch_assoc()) {
            $total_produk_di_keranjang = $row_cart_count['total_qty'] ?? 0;
        }
        $stmt_cart_count->close();
    }
}

$total_item_wishlist = 0;
if ($customer_id) {
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $customer_id);
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
}
// --- Akhir Bagian PHP untuk Data Navbar ---

// --- Bagian PHP untuk Paginasi dan Pencarian Kategori ---
$limit = 6; // Jumlah kategori per halaman. Anda bisa sesuaikan ini.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $limit;

// Ambil istilah pencarian jika ada
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// Bangun bagian WHERE untuk query berdasarkan pencarian
$where_clause = "";
$bind_types = "";
$bind_params = [];

if (!empty($search_query)) {
    $where_clause = " WHERE nama_kategori LIKE ?";
    $bind_types .= "s";
    $bind_params[] = '%' . $search_query . '%';
}

// Query untuk menghitung total kategori (dengan atau tanpa filter pencarian)
$total_categories_query = "SELECT COUNT(id) AS total FROM kategori_produk" . $where_clause;
$stmt_total = $conn->prepare($total_categories_query);

if ($stmt_total) {
    if (!empty($bind_params)) {
        $stmt_total->bind_param($bind_types, ...$bind_params);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_categories = $total_row['total'];
    $stmt_total->close();
} else {
    error_log("Error preparing total categories query: " . $conn->error);
    $total_categories = 0; // Fallback in case of error
}

$total_pages = ceil($total_categories / $limit);

// Logika untuk mengambil data kategori dari tabel 'kategori_produk' dengan paginasi dan pencarian
$categories = [];
$query_categories = "
    SELECT
        kp.id,
        kp.nama_kategori,
        kp.deskripsi,
        kp.gambar
    FROM
        kategori_produk kp
    " . $where_clause . "
    ORDER BY kp.nama_kategori ASC
    LIMIT ?, ?
";

$stmt_categories = $conn->prepare($query_categories);
if ($stmt_categories) {
    // Gabungkan parameter pencarian dengan parameter LIMIT
    $all_bind_params = array_merge($bind_params, [$start_from, $limit]);
    $all_bind_types = $bind_types . "ii";

    $stmt_categories->bind_param($all_bind_types, ...$all_bind_params);
    $stmt_categories->execute();
    $result_categories = $stmt_categories->get_result();
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt_categories->close();
} else {
    error_log("Error preparing categories query: " . $conn->error);
}

// --- Tambahan: Mengecek apakah ada produk untuk kategori yang ditampilkan ---
// Ini penting untuk memastikan "Lihat Produk" mengarah ke kategori yang punya produk
// Jika kategori tidak memiliki produk, kita bisa tampilkan pesan atau sembunyikan tombol
$categories_with_product_count = [];
if (!empty($categories)) {
    $category_ids = array_column($categories, 'id');
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $types = str_repeat('i', count($category_ids));

    $query_product_count = "
        SELECT kategori_id, COUNT(id) AS product_count
        FROM produk
        WHERE kategori_id IN ($placeholders)
        GROUP BY kategori_id
    ";
    $stmt_product_count = $conn->prepare($query_product_count);
    if ($stmt_product_count) {
        $stmt_product_count->bind_param($types, ...$category_ids);
        $stmt_product_count->execute();
        $result_product_count = $stmt_product_count->get_result();
        while ($row = $result_product_count->fetch_assoc()) {
            $categories_with_product_count[$row['kategori_id']] = $row['product_count'];
        }
        $stmt_product_count->close();
    }
}

$conn->close();

$page_title = "Daftar Kategori"; // Judul halaman
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $page_title; ?> - BUMDes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
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

        /* Navbar Styling (Sama dengan promo.php) */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            margin-right: 8px;
            max-width: 30px;
            height: auto;
        }

        .nav-link {
            color: white !important; /* Ubah dari rgba menjadi putih solid */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: white !important;
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
        }

        .navbar-nav .dropdown-menu {
            background-color: #FF4500; /* Consistent with navbar color */
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem #FF4500;
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
            border-top: 1px solid #ff4500;
        }

        /* Navbar badge styles */
        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        /* Kategori Page Specific Styles */
        .category-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
            background-color: #fff;
            height: 100%;
            display: flex;
            flex-direction: column;
            text-align: center;
            /* Make the entire card clickable */
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-card img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            padding: 15px;
            filter: grayscale(10%) brightness(1.05);
        }

        .category-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: center;
        }

        .category-card-title {
            font-weight: bold;
            color: #FF4500;
            margin-bottom: 10px;
            font-size: 1.35em;
        }

        .category-card-text {
            font-size: 0.9em;
            color: #777;
            margin-bottom: 15px;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .empty-categories {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }
        .empty-categories img {
            filter: grayscale(80%);
            opacity: 0.6;
        }
        .empty-categories .btn-primary {
            background-color: #FF4500;
            border-color: #FF4500;
        }
        .empty-categories .btn-primary:hover {
            background-color: #E63C00;
            border-color: #E63C00;
        }

        /* Footer Styling (Sama dengan promo.php) */
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
        /* Dropdown for cart items in navbar */
        #dropdown-keranjang {
            width: 430px;
            z-index: 1000;
            display: none; /* Awalnya tersembunyi, akan diatur oleh JS */
            right: 0;
            left: auto; /* Memastikan dropdown berada di kanan */
            min-width: 280px;
            background-color: white; /* Diubah menjadi putih untuk kontras yang lebih baik */
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
        }

        #dropdown-keranjang h5 {
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 3px;
            display: inline-block;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }

        #daftar-produk-keranjang li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            flex-direction: row;
            gap: 10px;
        }
        #daftar-produk-keranjang li:last-child {
            border-bottom: none;
        }
        #daftar-produk-keranjang li img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        #daftar-produk-keranjang li .item-details {
            flex-grow: 1;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #daftar-produk-keranjang li .item-details .product-name {
            font-weight: bold;
            font-size: 0.9rem;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .product-variation {
            font-size: 0.75rem;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .item-price {
            color: #FF4500;
            font-size: 0.85rem;
            text-align: right;
            margin-left: auto;
            flex-shrink: 0;
        }
        #pesan-keranjang-kosong {
            text-align: center;
            margin-top: 10px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }

        .d-flex.justify-content-between.align-items-center.mt-2 {
            margin-top: 15px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
        }

        #jumlah-produk-lainnya {
            color: #6c757d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
            background-color: #FF4500;
            color: white;
            text-decoration: none;
        }
        /* Dropdown for cart items in navbar */
        #dropdown-keranjang {
            width: 430px;
            z-index: 1000;
            display: none; /* Awalnya tersembunyi, akan diatur oleh JS */
            right: 0;
            left: auto; /* Memastikan dropdown berada di kanan */
            min-width: 280px;
            background-color: white; /* Diubah menjadi putih untuk kontras yang lebih baik */
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
        }

        #dropdown-keranjang h5 {
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 3px;
            display: inline-block;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }

        #daftar-produk-keranjang li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            flex-direction: row;
            gap: 10px;
        }
        #daftar-produk-keranjang li:last-child {
            border-bottom: none;
        }
        #daftar-produk-keranjang li img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        #daftar-produk-keranjang li .item-details {
            flex-grow: 1;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #daftar-produk-keranjang li .item-details .product-name {
            font-weight: bold;
            font-size: 0.9rem;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .product-variation {
            font-size: 0.75rem;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .item-price {
            color: #FF4500;
            font-size: 0.85rem;
            text-align: right;
            margin-left: auto;
            flex-shrink: 0;
        }
        #pesan-keranjang-kosong {
            text-align: center;
            margin-top: 10px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }

        .d-flex.justify-content-between.align-items-center.mt-2 {
            margin-top: 15px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
        }

        #jumlah-produk-lainnya {
            color: #6c757d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
            background-color: #FF4500;
            color: white;
            text-decoration: none;
        }
                /* Pagination Styling */
        .pagination .page-item .page-link {
            color: #FF4500;
        }

        .pagination .page-item.active .page-link {
            background-color: #FF4500;
            border-color: #FF4500;
            color: white;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d; /* Warna abu-abu untuk item yang disabled */
        }
        
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <img src="../../img/logo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
            BUMDes
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli"
            aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
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
                    <a class="nav-link active" href="../kategori/kategori.php">Kategori</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../promo/promo.php">Promo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../artikel/artikel.php">Artikel</a>
                </li>
            </ul>
            <form class="d-flex me-2 mb-2" role="search" action="kategori.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari kategori..."
                        aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
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
                        <a class="nav-link" href="../keranjang/keranjang.php" id="link-keranjang">
                            <i class="bi bi-cart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                <?php echo $total_produk_di_keranjang; ?>
                            </span>
                        </a>
                        <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2">
                            <h5>Baru Ditambahkan</h5>
                            <ul class="list-unstyled" id="daftar-produk-keranjang">
                                <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja
                                    kosong.</li>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                                <a href="../keranjang/keranjang.php" class="btn btn-sm"
                                    style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                        <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil"
                            class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1 text-white"><?php echo htmlspecialchars($nama_pelanggan ?? 'Tamu'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <?php if ($customer_id): ?>
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item" href="../keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                        <?php else: ?>
                        <li><a class="dropdown-item" href="../../login.php">Login</a></li>
                        <li><a class="dropdown-item" href="../../registrasi.php">Daftar</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="mb-4 text-center">Jelajahi Berbagai Kategori Produk!</h2>

    <?php if (empty($categories) && empty($search_query)): ?>
    <div class="alert alert-info text-center py-5" role="alert">
        <img src="../../img/kategori.png" alt="Tidak Ada Kategori" class="mb-3" style="max-width: 150px;">
        <p class="lead">Saat ini belum ada kategori produk yang tersedia.</p>
        <p>Nantikan kategori menarik lainnya di kemudian hari!</p>
        <a href="../produk.php" class="btn mt-3" style="background-color: #FF4500; color: white;">Jelajahi Produk</a>
    </div>
    <?php elseif (empty($categories) && !empty($search_query)): ?>
    <div class="alert alert-info text-center py-4" role="alert">
        <p class="lead mb-3">Tidak ada kategori yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>".
        </p>
        <a href="kategori.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Semua
            Kategori</a>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($categories as $category): ?>
        <div class="col" data-aos="fade-up">
            <?php 
            // Cek apakah kategori ini memiliki produk
            $has_products = isset($categories_with_product_count[$category['id']]) && $categories_with_product_count[$category['id']] > 0;
            // Buat URL untuk klik kategori
            $category_link = $has_products ? "../produk.php?kategori_id=" . $category['id'] : '#';
            ?>
            <a href="<?php echo $category_link; ?>" 
               class="card category-card text-decoration-none" 
               data-bs-toggle="<?php echo $has_products ? '' : 'modal'; ?>" 
               data-bs-target="<?php echo $has_products ? '' : '#noProductsModal'; ?>"
               data-category-name="<?php echo htmlspecialchars($category['nama_kategori']); ?>"
               <?php echo $has_products ? '' : 'onclick="return false;"'; ?>>
                <?php if (!empty($category['gambar'])): ?>
                <img src="../../img/kategori/<?php echo htmlspecialchars($category['gambar']); ?>" class="card-img-top"
                    alt="Gambar Kategori <?php echo htmlspecialchars($category['nama_kategori']); ?>">
                <?php else: ?>
                <img src="../../img/default-category.png" class="card-img-top" alt="Gambar Default Kategori">
                <?php endif; ?>
                <div class="card-body category-card-body">
                    <h5 class="card-title category-card-title"><?php echo htmlspecialchars($category['nama_kategori']); ?>
                    </h5>
                    <?php if (!empty($category['deskripsi'])): ?>
                    <p class="card-text category-card-text"><?php echo htmlspecialchars($category['deskripsi']); ?></p>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): // Tampilkan pagination hanya jika ada lebih dari 1 halaman ?>
    <div class="d-flex justify-content-center mt-5">
        <nav aria-label="Kategori Pagination">
            <ul class="pagination">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                        aria-label="Previous">
                        <span aria-hidden="true">«</span>
                    </a>
                </li>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                        <?php if ($i == $page) echo 'aria-current="page"'; ?>>
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                        aria-label="Next">
                        <span aria-hidden="true">»</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<footer class="footer py-4 text-white">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                <img src="../../img/logo.png" alt="Logo Desa" width="80" class="mb-2">
                <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                <p class="text-center">Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten
                    Tanggamus</p>
                <div class="sosmed-icons mt-3">
                    <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                    <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                    <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                    <a href="#"><i class="bi bi-instagram instagram-icon"></i></a>
                    <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                    <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-3 text-center">
                <h5 class="fw-bold text-white"><i class="bi bi-chat-dots"></i> HUBUNGI KAMI</h5>
                <p>Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi
                    Lampung Kode Pos 35377.</p>
                <p><i class="bi bi-telephone-fill"></i> Telepon: 081272020355</p>
                <p><i class="bi bi-envelope-fill"></i> Email: snrpetir@gmail.com</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5 class="fw-bold text-orange"><i class="bi bi-map"></i> PETA LOKASI</h5>
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.100908151834!2d104.5936737!3d-5.2673523!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40e691232c4e23%3A0x6b40e32f5f1c5c1!2sDesa%20Sinar%20Petir!5e0!3m2!1sid!2sid!4v1716347395015!5m2!1sid!2sid" width="100%" height="200"
                    style="border:0;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
        <hr class="border-top border-light mt-4">
        <div class="text-center mt-3">
            <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir. Dikelola oleh Tim IT Desa.</small>
        </div>
    </div>
</footer>

<div class="modal fade" id="noProductsModal" tabindex="-1" aria-labelledby="noProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="noProductsModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Informasi Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="fs-5">Kategori "<span id="modalCategoryName" class="fw-bold text-danger"></span>" belum memiliki produk.</p>
                <p class="text-muted">Silakan jelajahi kategori lain atau kembali ke daftar produk.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="../produk.php" class="btn btn-primary" style="background-color: #FF4500; border-color: #FF4500;">Lihat Semua Produk</a>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // Fungsi untuk memperbarui jumlah item di keranjang (badge navbar)
        function updateCartItemCount() {
            // Placeholder, badge sudah diisi oleh PHP saat page load
        }

        // Fungsi untuk memperbarui jumlah item di wishlist (badge navbar)
        function updateWishlistItemCount() {
            // Placeholder, badge sudah diisi oleh PHP saat page load
        }

        $(document).ready(function() {
            updateCartItemCount();
            updateWishlistItemCount();

            // Skrip salin kode promo dari promo.php, dipertahankan sesuai permintaan "semua function yang ada"
            $(document).on('click', '.btn-copy-code', function() {
                const button = $(this);
                const targetId = button.data('clipboard-target');
                const promoCodeElement = $(targetId);
                const promoCode = promoCodeElement.text();

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(promoCode).then(function() {
                        promoCodeElement.addClass('copied');
                        button.text('Tersalin!');
                        setTimeout(() => {
                            promoCodeElement.removeClass('copied');
                            button.html('<i class="bi bi-files me-1"></i> Salin');
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Gagal menyalin kode promo: ', err);
                        alert('Gagal menyalin kode promo. Silakan salin manual: ' + promoCode);
                    });
                } else {
                    const textArea = document.createElement("textarea");
                    textArea.value = promoCode;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-999999px";
                    textArea.style.top = "-999999px";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        promoCodeElement.addClass('copied');
                        button.text('Tersalin!');
                        setTimeout(() => {
                            promoCodeElement.removeClass('copied');
                            button.html('<i class="bi bi-files me-1"></i> Salin');
                        }, 2000);
                    } catch (err) {
                        console.error('Gagal menyalin kode promo (fallback): ', err);
                        alert('Gagal menyalin kode promo. Silakan salin manual: ' + promoCode);
                    }
                    document.body.removeChild(textArea);
                }
            });

            // JavaScript untuk mengisi nama kategori ke modal
            var noProductsModal = document.getElementById('noProductsModal');
            if (noProductsModal) {
                noProductsModal.addEventListener('show.bs.modal', function (event) {
                    // Button that triggered the modal
                    var button = event.relatedTarget; 
                    // Extract info from data-category-name attributes
                    var categoryName = button.getAttribute('data-category-name');
                    // Update the modal's content.
                    var modalCategoryName = noProductsModal.querySelector('#modalCategoryName');
                    modalCategoryName.textContent = categoryName;
                });
            }
        });
    // Fungsi formatRupiah yang diperlukan oleh muatIsiKeranjangDropdown
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

    // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX
    function muatJumlahKeranjangNav() {
        // Menggunakan jQuery.ajax karena ini adalah bagian dari kode asli Anda yang menggunakan $
        $.ajax({
            url: '../keranjang/get_cart_count.php', // Pastikan file ini ada dan mengembalikan {status: 'success', count: N}
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
     */
    function muatIsiKeranjangDropdown() {
        // Ambil elemen-elemen DOM yang dibutuhkan
        const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
        const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
        const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

        // Sembunyikan elemen "Produk Lainnya" dan kosongkan teksnya sebagai inisialisasi
        if (jumlahProdukLainnyaSpan) {
            jumlahProdukLainnyaSpan.style.display = 'none';
            jumlahProdukLainnyaSpan.textContent = '';
        }

        // Lakukan permintaan Fetch ke endpoint yang mengambil data keranjang
        fetch('../keranjang/ambil_keranjang_sementara.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (daftarProdukKeranjang) {
                    daftarProdukKeranjang.innerHTML = ''; // Kosongkan daftar produk yang mungkin sudah ada
                }

                const displayLimit = 3; // Batasi jumlah item produk unik yang ditampilkan di dropdown
                let totalQuantityOtherProducts = 0; // Inisialisasi total kuantitas produk yang tidak ditampilkan

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
                            if (daftarProdukKeranjang) {
                                daftarProdukKeranjang.appendChild(listItem);
                            }
                        } else {
                            totalQuantityOtherProducts += item.quantity;
                        }
                    });

                    if (totalQuantityOtherProducts > 0 && jumlahProdukLainnyaSpan) {
                        jumlahProdukLainnyaSpan.textContent = `${totalQuantityOtherProducts} Produk Lainnya`;
                        jumlahProdukLainnyaSpan.style.display = 'inline-block';
                    } else if (jumlahProdukLainnyaSpan) {
                        jumlahProdukLainnyaSpan.style.display = 'none';
                    }

                } else {
                    if (pesanKeranjangKosong) {
                        pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                        pesanKeranjangKosong.style.display = 'block';
                    }
                    if (jumlahProdukLainnyaSpan) {
                        jumlahProdukLainnyaSpan.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching cart items for dropdown:', error);
                if (pesanKeranjangKosong) {
                    pesanKeranjangKosong.textContent = "Gagal memuat detail keranjang. Silakan coba lagi.";
                    pesanKeranjangKosong.style.display = 'block';
                }
                if (jumlahProdukLainnyaSpan) {
                    jumlahProdukLainnyaSpan.style.display = 'none';
                }
            });
    }

    // Event listener untuk tampilan/menyembunyikan dropdown keranjang
    document.addEventListener('DOMContentLoaded', () => {
        const linkKeranjang = document.getElementById('link-keranjang');
        const dropdownKeranjang = document.getElementById('dropdown-keranjang');

        // Panggil fungsi ini saat halaman dimuat untuk inisialisasi badge keranjang
        muatJumlahKeranjangNav();

        if (linkKeranjang && dropdownKeranjang) {
            linkKeranjang.addEventListener('mouseenter', () => {
                muatIsiKeranjangDropdown(); // Muat ulang isi dropdown setiap kali mouse masuk
                dropdownKeranjang.style.display = 'block';
            });

            // Menyembunyikan dropdown saat mouse keluar dari dropdown itu sendiri
            dropdownKeranjang.addEventListener('mouseleave', () => {
                dropdownKeranjang.style.display = 'none';
            });

            // Menyembunyikan dropdown saat klik di luar area keranjang dan dropdown
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