<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login, ini opsional untuk halaman artikel jika Anda ingin pengguna tanpa login bisa melihat artikel
// Menggunakan 'pengguna_id' agar konsisten dengan promo.php
$pengguna_id = $_SESSION['pengguna_id'] ?? null;

// Ambil informasi pengguna untuk navbar
$nama_pelanggan = null;
$foto_pelanggan = null;
if ($pengguna_id) { // Hanya ambil info jika pengguna sudah login
    // Menggunakan tabel 'pelanggan' dan kolom 'pengguna_id' agar konsisten dengan promo.php
    $query_user_info = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
    $stmt_user_info = $conn->prepare($query_user_info);
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $pengguna_id);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($user_info = $result_user_info->fetch_assoc()) {
            $nama_pelanggan = $user_info['nama'];
            $foto_pelanggan = $user_info['foto'];
        }
        $stmt_user_info->close();
    }
}

// Ambil jumlah item di keranjang untuk badge navbar (Sama dengan promo.php)
$total_item_keranjang_badge = 0; // Mengganti $total_produk_di_keranjang agar konsisten
if ($pengguna_id) { // Menggunakan $pengguna_id
    $query_cart_count = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?"; // Menggunakan SUM(quantity)
    $stmt_cart_count = $conn->prepare($query_cart_count);
    if ($stmt_cart_count) {
        $stmt_cart_count->bind_param("i", $pengguna_id); // Gunakan $pengguna_id
        $stmt_cart_count->execute();
        $result_cart_count = $stmt_cart_count->get_result();
        if ($row_cart_count = $result_cart_count->fetch_assoc()) {
            $total_item_keranjang_badge = $row_cart_count['total_qty'] ?? 0;
        }
        $stmt_cart_count->close();
    }
}

// Ambil jumlah item di wishlist untuk badge navbar (Sama dengan promo.php)
$total_item_wishlist = 0; // Tetap gunakan nama ini untuk PHP
if ($pengguna_id) { // Menggunakan $pengguna_id
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $pengguna_id); // Gunakan $pengguna_id
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
}

// Logika untuk pencarian artikel
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$where_clause = " WHERE status = 'publikasi'";
$bind_types = "";
$bind_params = [];

if (!empty($search_query)) {
    $where_clause .= " AND (judul LIKE ? OR konten LIKE ?)";
    $bind_types .= "ss";
    // Membuat variabel terpisah yang akan diikat sebagai referensi
    $param_judul = '%' . $search_query . '%';
    $param_konten = '%' . $search_query . '%';
    $bind_params[] = &$param_judul; // Pass by reference
    $bind_params[] = &$param_konten; // Pass by reference
}

// --- Pagination Logic ---
$articles_per_page = 6; // Jumlah artikel per halaman
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $articles_per_page;

// Query untuk mendapatkan total artikel dengan filter pencarian
$total_articles = 0;
$query_total_articles = "SELECT COUNT(id) AS total FROM artikel" . $where_clause;
$stmt_total = $conn->prepare($query_total_articles);

if ($stmt_total) {
    if (!empty($bind_params)) {
        // --- FIX START ---
        // Create an array for call_user_func_array, including the type string as the first element
        $bind_params_total = array_merge([$bind_types], $bind_params);
        call_user_func_array([$stmt_total, 'bind_param'], $bind_params_total);
        // --- FIX END ---
    }
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    if ($row_total = $result_total->fetch_assoc()) {
        $total_articles = $row_total['total'];
    }
    $stmt_total->close();
} else {
    error_log("Error counting total articles: " . $conn->error);
}

$total_pages = ceil($total_articles / $articles_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages; // Adjust current page if it's beyond total pages
    $offset = ($current_page - 1) * $articles_per_page;
}

// Logika untuk mengambil data artikel dari tabel 'artikel' dengan pagination
$articles = [];
$query_articles = "
    SELECT
        id,
        judul AS judul_artikel,
        SUBSTRING(konten, 1, 150) AS isi_artikel_preview,
        gambar_utama AS gambar_artikel,
        tanggal_publikasi
    FROM
        artikel
    " . $where_clause . "
    ORDER BY tanggal_publikasi DESC
    LIMIT ? OFFSET ?
";

$stmt_articles = $conn->prepare($query_articles);
if ($stmt_articles) {
    // Combine existing bind parameters with new ones for LIMIT and OFFSET
    $bind_params_for_articles = $bind_params; // Copy the existing search params
    $bind_types_for_articles = $bind_types . "ii"; // Add 'ii' for integer types of LIMIT and OFFSET
    $bind_params_for_articles[] = &$articles_per_page;
    $bind_params_for_articles[] = &$offset;

    // Use call_user_func_array to bind parameters dynamically
    array_unshift($bind_params_for_articles, $bind_types_for_articles); // Prepend the types string
    call_user_func_array(array($stmt_articles, 'bind_param'), $bind_params_for_articles);

    $stmt_articles->execute();
    $result_articles = $stmt_articles->get_result();
    while ($row = $result_articles->fetch_assoc()) {
        $articles[] = $row;
    }
    $stmt_articles->close();
} else {
    // Handle error jika query gagal
    error_log("Error fetching articles from 'artikel' table: " . $conn->error);
}

$conn->close();

$page_title = "Artikel Terbaru"; // Judul halaman
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
        }

        .nav-link {
            color: white !important; /* Disesuaikan agar sama dengan promo.php */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important; /* Warna hover sedikit lebih terang, konsisten dengan promo.php */
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
            vertical-align: super; /* Menambahkan ini agar konsisten */
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
            background-color: #ffe0b2; /* Light orange on hover, konsisten dengan promo.php */
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
            color: white !important; /* Ditambahkan agar konsisten */
        }


        /* Artikel Page Specific Styles */
        .article-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
            background-color: #fff;
            height: 100%; /* Ensure cards have consistent height */
            display: flex;
            flex-direction: column;
        }

        .article-card:hover {
            transform: translateY(-5px);
        }

        .article-card img {
            width: 100%;
            height: 200px; /* Fixed height for article images */
            object-fit: cover;
            filter: brightness(0.9);
        }

        .article-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .article-card-title {
            font-weight: bold;
            color: #FF4500;
            margin-bottom: 10px;
            font-size: 1.25em;
        }

        .article-card-text {
            font-size: 0.95em;
            color: #555;
            margin-bottom: 15px;
            flex-grow: 1; /* Allow text to take available space */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Limit to 3 lines */
            -webkit-box-orient: vertical;
        }

        .article-date {
            font-size: 0.85em;
            color: #888;
            margin-top: 10px;
        }

        .btn-read-more {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
            align-self: flex-start; /* Align button to start */
        }
        .btn-read-more:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        .empty-articles {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }
        .empty-articles img {
            /*filter: grayscale(80%);*/
            /*opacity: 0.6;*/
        }
        .empty-articles .btn-primary {
            background-color: #FF4500;
            border-color: #FF4500;
        }
        .empty-articles .btn-primary:hover {
            background-color: #E63C00;
            border-color: #E63C00;
        }

        /* Pagination Styles */
        .pagination .page-item .page-link {
            color: #FF4500;
            border-color: #FF4500;
            transition: background-color 0.3s, color 0.3s;
        }

        .pagination .page-item .page-link:hover {
            background-color: #FF4500;
            color: white;
        }

        .pagination .page-item.active .page-link {
            background-color: #FF4500;
            border-color: #FF4500;
            color: white;
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
                    <a class="nav-link" href="../promo/promo.php">Promo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="../artikel/artikel.php">Artikel</a>
                </li>
            </ul>
            <form class="d-flex me-2 mb-2" role="search" action="artikel.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari artikel..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
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
                        <?php if ($pengguna_id): ?>
                                <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                                <li><a class="dropdown-item" href="../keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
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
        <h2 class="mb-4 text-center">Artikel Terbaru dari BUMDes!</h2>

        <?php if (empty($articles) && empty($search_query)): ?>
            <div class="alert alert-info text-center" role="alert" id="empty-articles-message">
                <i class="bi bi-file-earmark-text mb-3" style="font-size: 80px; color: #FF4500;"></i>
                <p class="lead">Saat ini belum ada artikel yang dipublikasikan.</p>
                <p>Nantikan tulisan dan informasi menarik lainnya di kemudian hari!</p>
                <a href="../index.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Kembali ke Beranda</a>
            </div>
        <?php elseif (empty($articles) && !empty($search_query)): ?>
            <div class="alert alert-info text-center" role="alert">
                Tidak ada artikel yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>".
                <br>
                <a href="artikel.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Semua Artikel</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($articles as $article): ?>
                    <div class="col" data-aos="fade-up">
                        <div class="card article-card">
                            <?php if (!empty($article['gambar_artikel'])): ?>
                                <img src="../../img/artikel/<?php echo htmlspecialchars($article['gambar_artikel']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($article['judul_artikel']); ?>">
                            <?php else: ?>
                                <img src="../../img/default-article.png" class="card-img-top" alt="Gambar Default Artikel">
                            <?php endif; ?>
                            <div class="card-body article-card-body">
                                <h5 class="card-title article-card-title"><?php echo htmlspecialchars($article['judul_artikel']); ?></h5>
                                <p class="card-text article-card-text"><?php echo htmlspecialchars(strip_tags($article['isi_artikel_preview'])); ?>...</p>
                                <p class="article-date mt-auto">
                                    Dipublikasikan: <?php echo date('d M Y', strtotime($article['tanggal_publikasi'])); ?>
                                </p>
                                <a href="detail_artikel.php?id=<?php echo $article['id']; ?>" class="btn btn-read-more">Baca Selengkapnya</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
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

        // Fungsi formatRupiah (sama seperti di promo.php)
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

        // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX (Disalin dari promo.php)
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
         * (Disalin dari promo.php)
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


        // Event listener untuk menampilkan/menyembunyikan dropdown keranjang (Disalin dari promo.php)
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

        // Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX (Disalin dari promo.php)
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

            // Menghapus script untuk menyalin kode promo karena tidak relevan di halaman artikel
            // Jika suatu saat ada kode promo di artikel, bisa ditambahkan kembali
        });
    </script>
</body>
</html>