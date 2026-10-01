<?php
session_start();
// Path disesuaikan agar konsisten dengan produk.php
include '../../koneksi/koneksi.php'; 

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['pengguna_id'])) {
    // Jika belum login, arahkan ke halaman login
    $_SESSION['error_message'] = "Anda harus login untuk melihat halaman ini.";
    header('Location: ../../login.php'); // Kembali ke login.php di root
    exit();
}

// Ambil ID pengguna dari sesi
$pengguna_id = $_SESSION['pengguna_id'];

// Inisialisasi variabel profil pelanggan
$nama_pelanggan = "Akun"; // Default
$foto_pelanggan = "";    // Default

// Query untuk mengambil informasi profil pengguna (nama dan foto) dari tabel pelanggan
// Menggunakan tabel 'pelanggan' agar konsisten dengan produk.php
$sql_profil = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
$stmt_profil = $conn->prepare($sql_profil);
if ($stmt_profil === false) {
    // Tangani error jika query profil gagal dipersiapkan
    error_log("Terjadi kesalahan sistem saat memuat profil Anda di promo.php: " . $conn->error);
    // Tidak perlu exit atau redirect ke login, hanya log error dan gunakan default
} else {
    $stmt_profil->bind_param("i", $pengguna_id);
    $stmt_profil->execute();
    $result_profil = $stmt_profil->get_result();

    if ($result_profil->num_rows === 1) {
        $data_profil = $result_profil->fetch_assoc();
        $nama_pelanggan = htmlspecialchars($data_profil['nama']);
        $foto_pelanggan = htmlspecialchars($data_profil['foto']);
    }
    $stmt_profil->close();
}


// Ambil jumlah item di keranjang untuk badge navbar
$total_item_keranjang_badge = 0;
// Menggunakan $pengguna_id yang sudah diambil dari sesi
$query_total_cart = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?"; // Menggunakan SUM(quantity) untuk total produk
$stmt_total_cart = $conn->prepare($query_total_cart);
if ($stmt_total_cart) {
    $stmt_total_cart->bind_param("i", $pengguna_id);
    $stmt_total_cart->execute();
    $result_total_cart = $stmt_total_cart->get_result();
    if ($row_total_cart = $result_total_cart->fetch_assoc()) {
        $total_item_keranjang_badge = $row_total_cart['total_qty'] ?? 0;
    }
    $stmt_total_cart->close();
} else {
    error_log("Gagal mempersiapkan kueri total keranjang di promo.php: " . $conn->error);
}

// Ambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0; 
if ($pengguna_id) { 
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $pengguna_id); 
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
}

// Logika untuk pencarian promo
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$where_clause = " WHERE status = 'aktif' AND tanggal_mulai <= NOW() AND tanggal_berakhir >= NOW()";
$bind_types = "";
$bind_params = [];

if (!empty($search_query)) {
    $where_clause .= " AND (nama_diskon LIKE ? OR kode_diskon LIKE ?)";
    $bind_types .= "ss";
    // *** PERBAIKAN DI SINI ***
    // Membuat variabel terpisah yang akan diikat sebagai referensi
    $param_nama_diskon = '%' . $search_query . '%';
    $param_kode_diskon = '%' . $search_query . '%';
    $bind_params[] = &$param_nama_diskon; // Pass by reference
    $bind_params[] = &$param_kode_diskon; // Pass by reference
}

// --- LOGIKA PAGINATION DIMULAI ---
$items_per_page = 9; // Jumlah promo per halaman
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Query untuk menghitung total promo (tanpa LIMIT/OFFSET)
$query_count = "SELECT COUNT(id) AS total_promos FROM diskon " . $where_clause;
$stmt_count = $conn->prepare($query_count);
if ($stmt_count) {
    if (!empty($bind_params)) {
        // *** PERBAIKAN DI SINI (BARIS 112) ***
        // Buat array untuk call_user_func_array, diawali dengan tipe, diikuti oleh referensi.
        $count_stmt_params = [];
        $count_stmt_params[] = $bind_types; // Tipe data

        // Tambahkan parameter pencarian sebagai referensi.
        // Karena $bind_params sudah berisi referensi dari atas, kita bisa langsung menggunakannya.
        foreach ($bind_params as &$param) { // Penting: Iterasi dengan referensi
            $count_stmt_params[] = &$param; // Masukkan referensi ke array baru
        }
        call_user_func_array(array($stmt_count, 'bind_param'), $count_stmt_params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_promos = $result_count->fetch_assoc()['total_promos'];
    $stmt_count->close();
} else {
    error_log("Error preparing count query: " . $conn->error);
    $total_promos = 0; // Default jika query gagal
}

$total_pages = ceil($total_promos / $items_per_page);

// Pastikan current_page tidak kurang dari 1 dan tidak lebih dari total_pages
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
// --- LOGIKA PAGINATION BERAKHIR ---


// Logika untuk mengambil data promo dari tabel 'diskon' dengan pencarian dan pagination
$promos = [];
$query_promos = "
    SELECT
        id,
        nama_diskon AS nama_promo,
        kode_diskon AS kode_promo,
        jenis_diskon,
        nilai_diskon,
        tanggal_mulai,
        tanggal_berakhir
    FROM
        diskon
    " . $where_clause . "
    ORDER BY tanggal_berakhir ASC
    LIMIT ? OFFSET ?
";

$stmt_promos = $conn->prepare($query_promos);
if ($stmt_promos) {
    // Siapkan parameter untuk query LIMIT dan OFFSET
    // Variabel ini perlu dibuat sebagai referensi juga.
    $limit_param = $items_per_page; // Gunakan variabel terpisah
    $offset_param = $offset;// Gunakan variabel terpisah

    // Gabungkan tipe dan parameter binding untuk search_query dan limit/offset
    $all_bind_types = $bind_types . "ii"; // 'i' untuk integer (limit_param dan offset_param)
    
    // *** PERBAIKAN DI SINI (BARIS 190) ***
    // Buat array baru yang berisi semua parameter sebagai referensi
    $stmt_all_params = [];
    $stmt_all_params[] = $all_bind_types; // Tipe data di awal

    // Tambahkan parameter pencarian (jika ada) sebagai referensi
    foreach ($bind_params as &$param) { // Penting: Iterasi dengan referensi
        $stmt_all_params[] = &$param;
    }

    // Tambahkan parameter limit dan offset sebagai referensi
    $stmt_all_params[] = &$limit_param;
    $stmt_all_params[] = &$offset_param;
    
    // Lakukan binding
    call_user_func_array(array($stmt_promos, 'bind_param'), $stmt_all_params);

    $stmt_promos->execute();
    $result_promos = $stmt_promos->get_result();
    while ($row = $result_promos->fetch_assoc()) {
        $promos[] = $row;
    }
    $stmt_promos->close();
} else {
    error_log("Error preparing promos query: " . $conn->error);
}

$conn->close();

$page_title = "Promo Spesial";
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

        /* Navbar Styling */
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
        }

        .nav-link {
            color: white !important; /* Disetel putih solid */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important; /* Warna hover sedikit lebih terang */
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
            background-color: #ffe0b2; /* Light orange on hover */
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

        /* Memastikan ikon hati di NAVBAR tetap putih */
        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important; /* Pastikan selalu putih */
        }

        /* Membuat tulisan nama_pelanggan menjadi putih solid */
        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }


        /* Promo Page Specific Styles */
        .promo-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
            background-color: #fff;
            height: 100%; /* Ensure all cards in a row have same height */
            display: flex;
            flex-direction: column;
        }

        .promo-card:hover {
            transform: translateY(-5px);
        }

        .promo-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1; /* Allow content to grow to fill space */
        }

        .promo-card-title {
            font-weight: bold;
            color: #FF4500;
            margin-bottom: 10px;
            font-size: 1.5em; /* Make title slightly larger */
        }

        .promo-card-text {
            font-size: 0.95em;
            color: #555;
            margin-bottom: 15px;
            flex-grow: 1; /* Allow text to grow */
            overflow: hidden; /* Hide overflow if text is too long */
            text-overflow: ellipsis; /* Add ellipsis for long text */
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Limit text to 3 lines */
            -webkit-box-orient: vertical;
        }

        .promo-code-section {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap; /* Allow wrapping on small screens */
            gap: 10px; /* Space between items */
        }

        .promo-code-label {
            font-weight: bold;
            color: #333;
        }

        .promo-code {
            background-color: #f0f0f0;
            border: 1px dashed #ccc;
            padding: 8px 12px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            user-select: all; /* Allow easy selection */
            cursor: pointer; /* Indicate it's clickable */
            flex-grow: 1; /* Allow code to grow */
            text-align: center;
        }
        .promo-code.copied {
            background-color: #d4edda; /* Light green on copy */
            border-color: #28a745;
            color: #28a745;
        }

        .promo-diskon-info {
            font-weight: bold;
            color: #28a745; /* Green for discount info */
            margin-bottom: 10px;
            font-size: 1.2em; /* Make discount info slightly larger */
        }

        .promo-dates {
            font-size: 0.85em;
            color: #888;
            margin-top: auto; /* Push to the bottom */
            padding-top: 10px; /* Add some space from elements above */
            border-top: 1px solid #eee; /* Separator line */
        }

        .btn-copy-code {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
            white-space: nowrap; /* Prevent text wrapping inside button */
        }
        .btn-copy-code:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        .empty-promo {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }
        .empty-promo img {
            filter: grayscale(80%);
            opacity: 0.6;
        }
        .empty-promo .btn-primary {
            background-color: #FF4500;
            border-color: #FF4500;
        }
        .empty-promo .btn-primary:hover {
            background-color: #E63C00;
            border-color: #E63C00;
        }
        /* Footer Styling */
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
        /* Custom Pagination Styles for #FF4500 */
        .pagination .page-item .page-link {
            color: #FF4500; /* Warna teks untuk link halaman biasa */
            border-color: #dee2e6; /* Warna border default */
        }
        .pagination .page-item .page-link:hover {
            color: white; /* Warna teks saat hover */
            background-color: #FF4500; /* Warna latar belakang saat hover */
            border-color: #FF4500; /* Warna border saat hover */
        }
        .pagination .page-item.active .page-link {
            background-color: #FF4500; /* Warna latar belakang untuk halaman aktif */
            border-color: #FF4500; /* Warna border untuk halaman aktif */
            color: white; /* Warna teks untuk halaman aktif */
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d; /* Warna teks untuk link yang dinonaktifkan (prev/next di ujung) */
            background-color: #e9ecef; /* Latar belakang link dinonaktifkan */
            border-color: #dee2e6; /* Border link dinonaktifkan */
        }
        /* Pastikan tidak ada aturan seperti ini yang menimpa alert-info */
        .text-center.py-5 {
            /* background-color: #fff; */ /* Pastikan baris ini dihilangkan/dikomentari */
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }
        /* Dropdown for cart items in navbar */
        #dropdown-keranjang {
            width: 430px;
            z-index: 1000;
            display: none; /* Awalnya tersembunyi, akan diatur oleh JS */
            right: 0;
            left: auto; /* Memastikan dropdown berada di kanan */
            min-width: 280px;
            background-color: white;
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
        }
        #daftar-produk-keranjang li .item-details .product-variation {
            font-size: 0.75rem;
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
                            <a class="nav-link active" href="promo.php">Promo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../artikel/artikel.php">Artikel</a>
                        </li>
                    </ul>
                    <form class="d-flex me-2 mb-2" role="search" action="promo.php" method="GET">
                        <div class="input-group">
                            <input class="form-control form-control-sm" type="search" placeholder="Cari promo..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <ul class="navbar-nav mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="../wishlist.php">
                                <i class="bi bi-heart-fill"></i>
                                <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                                    <?php echo $total_item_wishlist; ?> </span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <div class="position-relative">
                                <a class="nav-link" href="../keranjang/keranjang.php" id="link-keranjang">
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
                                            <a href="../keranjang/keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                                        </div>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                                        <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                                <?php else: ?>
                                        <i class="bi bi-person-circle"></i>
                                <?php endif; ?>
                                <span class="ms-1 text-white"><?php echo htmlspecialchars($nama_pelanggan ?? 'Tamu'); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                                <li><a class="dropdown-item" href="../keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <div class="container my-4">
        <h2 class="mb-4 text-center">Promo Spesial untuk Anda!</h2>

        <?php if (empty($promos) && empty($search_query) && $current_page == 1): ?>
            <div class="alert alert-info text-center" role="alert" id="empty-promo-message">
                <i class="bi bi-tags-fill mb-3" style="font-size: 80px; color: #FF4500;"></i>
                <p class="lead">Saat ini belum ada promo yang tersedia.</p>
                <p>Nantikan promo menarik lainnya di kemudian hari!</p>
                <a href="../produk.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Jelajahi Produk</a>
            </div>
        <?php elseif (empty($promos) && !empty($search_query)): ?>
            <div class="alert alert-info text-center" role="alert">
                Tidak ada promo yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>".
                <br>
                <a href="promo.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Semua Promo</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($promos as $promo): ?>
                    <div class="col" data-aos="fade-up">
                        <div class="card promo-card">
                            <div class="card-body promo-card-body">
                                <h5 class="card-title promo-card-title"><?php echo htmlspecialchars($promo['nama_promo']); ?></h5>
                                <p class="promo-diskon-info">
                                    Diskon
                                    <?php
                                    if ($promo['jenis_diskon'] == 'persen') {
                                        echo htmlspecialchars($promo['nilai_diskon']) . '%';
                                    } elseif ($promo['jenis_diskon'] == 'nominal') {
                                        echo 'Rp ' . number_format($promo['nilai_diskon'], 0, ',', '.');
                                    } else {
                                        echo 'Jumlah Diskon Tidak Diketahui';
                                    }
                                    ?>
                                </p>
                                <?php if (!empty($promo['kode_promo'])): ?>
                                    <div class="promo-code-section">
                                        <span class="promo-code-label">Kode Promo:</span>
                                        <span class="promo-code" id="promo-code-<?php echo $promo['id']; ?>"><?php echo htmlspecialchars($promo['kode_promo']); ?></span>
                                        <button class="btn btn-sm btn-copy-code"
                                                data-clipboard-target="#promo-code-<?php echo $promo['id']; ?>"
                                                data-promo-id="<?php echo $promo['id']; ?>">
                                            <i class="bi bi-files me-1"></i> Salin
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Tidak ada kode promo yang diperlukan.</p>
                                <?php endif; ?>
                                <p class="promo-dates">
                                    Berlaku hingga: <?php echo date('d M Y', strtotime($promo['tanggal_berakhir'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation for promos" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>" aria-label="Next">
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
                    <img src="../../img/logo.png" alt="Logo Desa" width="80" class="mb-2">
                    <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                    <p class="text-center">Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // Fungsi formatRupiah (sama seperti di produk.php)
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
        // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX (sama seperti di produk.php)
        function muatJumlahKeranjangNav() {
            $.ajax({
                url: '../keranjang/get_cart_count.php', // Path disesuaikan
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
         * (Sama persis seperti di produk.php)
         */
        function muatIsiKeranjangDropdown() {
            const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
            const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
            const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

            jumlahProdukLainnyaSpan.style.display = 'none';
            jumlahProdukLainnyaSpan.textContent = '';

            fetch('../keranjang/ambil_keranjang_sementara.php') // Path disesuaikan
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


        // Event listener untuk menampilkan/menyembunyikan dropdown keranjang (sama seperti di produk.php)
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

        // Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX
        function updateWishlistItemCount() {
            $.ajax({
                url: '../toggle_wishlist.php?action=get_count', // Kita bisa modifikasi toggle_wishlist.php untuk mengembalikan count saja
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

        // Fungsi untuk memperbarui jumlah item di wishlist (badge navbar)
        // Fungsi ini TIDAK MEMANGGIL FILE PHP terpisah, melainkan mengandalkan data yang sudah dimuat di awal halaman
        function updateWishlistItemCount() {
            // Sama seperti updateCartItemCount, ini adalah 'placeholder'.
            // Badge wishlist di navbar akan menampilkan nilai yang sudah dihitung oleh PHP saat page load.
            // Jika Anda ingin badge wishlist update secara dinamis tanpa refresh halaman,
            // Anda harus menambahkan AJAX request ke endpoint yang menghitung wishlist.
            // Saya tidak menambahkan AJAX baru sesuai instruksi Anda.
        }

        $(document).ready(function() {
            // Panggil fungsi update saat halaman dimuat
            muatJumlahKeranjangNav(); // Pastikan badge keranjang terupdate
            updateWishlistItemCount(); // Pastikan badge wishlist terupdate

            // Script untuk menyalin kode promo
            $(document).on('click', '.btn-copy-code', function() {
                const button = $(this);
                const targetId = button.data('clipboard-target');
                const promoCodeElement = $(targetId);
                const promoCode = promoCodeElement.text();

                // Menggunakan Clipboard API modern
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(promoCode).then(function() {
                        // Animasi feedback
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
                    // Fallback untuk browser lama atau HTTP
                    const textArea = document.createElement("textarea");
                    textArea.value = promoCode;
                    textArea.style.position = "fixed"; // hindari scroll
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
        });
    </script>
</body>
</html>