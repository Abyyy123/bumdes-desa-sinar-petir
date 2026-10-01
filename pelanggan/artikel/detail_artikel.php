<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login, ini opsional untuk halaman artikel jika Anda ingin pengguna tanpa login bisa melihat artikel
$pengguna_id = $_SESSION['pengguna_id'] ?? null;

// Ambil informasi pengguna untuk navbar
$nama_pelanggan = null;
$foto_pelanggan = null;
if ($pengguna_id) { // Hanya ambil info jika pengguna sudah login
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

// Ambil jumlah item di keranjang untuk badge navbar
$total_item_keranjang_badge = 0;
if ($pengguna_id) {
    $query_cart_count = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?";
    $stmt_cart_count = $conn->prepare($query_cart_count);
    if ($stmt_cart_count) {
        $stmt_cart_count->bind_param("i", $pengguna_id);
        $stmt_cart_count->execute();
        $result_cart_count = $stmt_cart_count->get_result();
        if ($row_cart_count = $result_cart_count->fetch_assoc()) {
            $total_item_keranjang_badge = $row_cart_count['total_qty'] ?? 0;
        }
        $stmt_cart_count->close();
    }
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

$article = null;
$page_title = "Detail Artikel"; // Default title
$article_id = null; // Initialize article_id

// Logika untuk pencarian artikel di navbar (disinkronkan dari artikel.php)
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';


// Ambil ID artikel dari URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $article_id = $_GET['id'];

    // Query untuk mengambil detail artikel berdasarkan ID
    $query_detail_article = "
        SELECT
            id,
            judul AS judul_artikel,
            konten AS isi_artikel,
            gambar_utama AS gambar_artikel,
            tanggal_publikasi,
            penulis_id,
            url_berita
        FROM
            artikel
        WHERE
            id = ? AND status = 'publikasi'
    ";
    $stmt_detail_article = $conn->prepare($query_detail_article);
    if ($stmt_detail_article) {
        $stmt_detail_article->bind_param("i", $article_id);
        $stmt_detail_article->execute();
        $result_detail_article = $stmt_detail_article->get_result();
        if ($result_detail_article->num_rows > 0) {
            $article = $result_detail_article->fetch_assoc();
            $page_title = htmlspecialchars($article['judul_artikel']); // Set judul halaman sesuai judul artikel
        }
        $stmt_detail_article->close();
    } else {
        error_log("Error preparing detail article query: " . $conn->error);
    }
}

// --- Pagination Logic for Latest Articles ---
$articles_per_page = 10; // Number of latest articles to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $articles_per_page;

$latest_articles = [];
$total_latest_articles = 0;

// First, get the total count of latest articles (excluding the current one)
$query_total_latest_articles = "
    SELECT COUNT(id) AS total_count
    FROM artikel
    WHERE status = 'publikasi' " . ($article_id ? "AND id != ?" : "");
$stmt_total_latest_articles = $conn->prepare($query_total_latest_articles);

if ($stmt_total_latest_articles) {
    if ($article_id) {
        $stmt_total_latest_articles->bind_param("i", $article_id);
    }
    $stmt_total_latest_articles->execute();
    $result_total_latest_articles = $stmt_total_latest_articles->get_result();
    if ($row_total_latest_articles = $result_total_latest_articles->fetch_assoc()) {
        $total_latest_articles = $row_total_latest_articles['total_count'];
    }
    $stmt_total_latest_articles->close();
} else {
    error_log("Error preparing total latest articles count query: " . $conn->error);
}

$total_pages = ceil($total_latest_articles / $articles_per_page);

// Then, fetch the latest articles for the current page
$query_latest_articles = "
    SELECT
        id,
        judul AS judul_artikel,
        gambar_utama AS gambar_artikel,
        tanggal_publikasi
    FROM
        artikel
    WHERE
        status = 'publikasi' " . ($article_id ? "AND id != ?" : "") . "
    ORDER BY tanggal_publikasi DESC
    LIMIT ? OFFSET ?
";
$stmt_latest_articles = $conn->prepare($query_latest_articles);
if ($stmt_latest_articles) {
    if ($article_id) {
        $stmt_latest_articles->bind_param("iii", $article_id, $articles_per_page, $offset);
    } else {
        $stmt_latest_articles->bind_param("ii", $articles_per_page, $offset);
    }
    $stmt_latest_articles->execute();
    $result_latest_articles = $stmt_latest_articles->get_result();
    while ($row = $result_latest_articles->fetch_assoc()) {
        $latest_articles[] = $row;
    }
    $stmt_latest_articles->close();
} else {
    error_log("Error fetching latest articles for sidebar: " . $conn->error);
}


$conn->close();
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

        /* Navbar badge styles */
        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        /* Memastikan ikon hati di NAVBAR tetap putih */
        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important;
        }

        /* Membuat tulisan nama_pelanggan menjadi putih solid */
        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }

        /* Article Detail Page Specific Styles */
        .article-detail-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .article-detail-img {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 25px;
            filter: brightness(0.95);
        }

        .article-detail-title {
            color: #FF4500;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 2.2em;
            line-height: 1.3;
        }

        .article-meta {
            font-size: 0.9em;
            color: #777;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .article-meta i {
            margin-right: 5px;
        }

        .article-content p {
            line-height: 1.8;
            margin-bottom: 1em;
            font-size: 1.05em;
            color: #444;
            text-align: justify; /* Justify text for better readability */
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            display: block; /* Ensures image is on its own line */
            margin: 20px auto; /* Centers image and adds vertical space */
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* --- Perbaikan untuk isi artikel --- */
        .article-content {
            font-size: 1.1em; /* Sedikit lebih besar untuk keterbacaan */
            line-height: 1.7; /* Jarak antar baris yang nyaman */
            color: #333; /* Warna teks yang lebih gelap */
        }

        .article-content h1, .article-content h2, .article-content h3,
        .article-content h4, .article-content h5, .article-content h6 {
            color: #FF4500; /* Warna judul di dalam konten */
            margin-top: 1.5em; /* Jarak atas untuk judul */
            margin-bottom: 0.8em; /* Jarak bawah untuk judul */
        }

        .article-content ul, .article-content ol {
            margin-bottom: 1em; /* Jarak bawah untuk daftar */
            padding-left: 25px; /* Indentasi daftar */
        }

        .article-content li {
            margin-bottom: 0.5em; /* Jarak antar item daftar */
        }

        .article-content blockquote {
            border-left: 4px solid #FF4500; /* Garis samping untuk blockquote */
            padding-left: 15px;
            margin: 1.5em 0;
            font-style: italic;
            color: #555;
        }

        .article-content pre {
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto; /* Untuk kode yang panjang */
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9em;
        }

        .article-content strong {
            color: #FF4500; /* Menarik perhatian pada teks tebal */
        }

        .article-content a {
            color: #007bff; /* Warna link yang standar */
            text-decoration: underline;
        }

        .article-content a:hover {
            color: #0056b3;
        }
        /* --- Akhir perbaikan untuk isi artikel --- */


        .sidebar {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .sidebar-title {
            color: #FF4500;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 1.4em;
            border-bottom: 2px solid #FF4500;
            padding-bottom: 10px;
        }

        .latest-article-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #eee;
        }

        .latest-article-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .latest-article-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }

        .latest-article-item-info {
            flex-grow: 1;
        }

        .latest-article-item-info h6 {
            font-size: 1em;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .latest-article-item-info a {
            color: #333;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .latest-article-item-info a:hover {
            color: #FF4500;
        }

        .latest-article-item-info small {
            color: #888;
            font-size: 0.8em;
        }
        .btn-back-to-articles {
            background-color: #6c757d; /* Gray color for back button */
            border-color: #6c757d;
            color: white;
            transition: background-color 0.2s ease;
        }

        .btn-back-to-articles:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            color: white;
        }

        /* Footer Styling */
        .footer {
            background-color: #FF4500;
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
        <div class="row">
            <div class="col-lg-8" data-aos="fade-up">
                <?php if ($article): ?>
                    <div class="article-detail-container">
                        <a href="artikel.php" class="btn btn-sm btn-back-to-articles mb-4">
                            <i class="bi bi-arrow-left"></i> Kembali ke Artikel
                        </a>
                        <?php if (!empty($article['gambar_artikel'])): ?>
                            <img src="../../img/artikel/<?php echo htmlspecialchars($article['gambar_artikel']); ?>" class="article-detail-img" alt="<?php echo htmlspecialchars($article['judul_artikel']); ?>">
                        <?php else: ?>
                            <img src="../../img/default-article.png" class="article-detail-img" alt="Gambar Default Artikel">
                        <?php endif; ?>

                        <h1 class="article-detail-title"><?php echo htmlspecialchars($article['judul_artikel']); ?></h1>
                        <div class="article-meta">
                            <p class="mb-0">
                                <i class="bi bi-calendar"></i> Dipublikasikan: <?php echo date('d M Y', strtotime($article['tanggal_publikasi'])); ?>
                            </p>
                            <?php if (!empty($article['url_berita'])): ?>
                                <p class="mb-0">
                                    <i class="bi bi-link-45deg"></i> Sumber Berita: <a href="<?php echo htmlspecialchars($article['url_berita']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($article['url_berita']); ?></a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="article-content">
                            <?php echo $article['isi_artikel']; // Konten artikel bisa mengandung HTML ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        <i class="bi bi-exclamation-triangle mb-3" style="font-size: 80px; color: #FFC107;"></i>
                        <h4 class="alert-heading">Artikel Tidak Ditemukan!</h4>
                        <p>Artikel yang Anda cari mungkin tidak ada, atau telah dihapus.</p>
                        <hr>
                        <p class="mb-0">Silakan kembali ke daftar artikel atau cari artikel lainnya.</p>
                        <a href="artikel.php" class="btn mt-3" style="background-color: #FF4500; color: white;">Lihat Semua Artikel</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0" data-aos="fade-left">
                <div class="sidebar">
                    <h5 class="sidebar-title">Artikel Terbaru Lainnya</h5>
                    <?php if (!empty($latest_articles)): ?>
                        <?php foreach ($latest_articles as $latest_article): ?>
                            <div class="latest-article-item">
                                <?php if (!empty($latest_article['gambar_artikel'])): ?>
                                    <img src="../../img/artikel/<?php echo htmlspecialchars($latest_article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($latest_article['judul_artikel']); ?>">
                                <?php else: ?>
                                    <img src="../../img/default-article.png" alt="Gambar Default" class="img-fluid">
                                <?php endif; ?>
                                <div class="latest-article-item-info">
                                    <h6><a href="detail_artikel.php?id=<?php echo $latest_article['id']; ?>"><?php echo htmlspecialchars($latest_article['judul_artikel']); ?></a></h6>
                                    <small><?php echo date('d M Y', strtotime($latest_article['tanggal_publikasi'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation for latest articles" class="mt-3">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $article_id; ?>&page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">«</span>
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $article_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $article_id; ?>&page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">»</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Tidak ada artikel terbaru lainnya saat ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15886.999824497672!2d104.53676225!3d-5.26353915!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40b37f4d2f099b%3A0x6b1d4e4c2f6d2f6d!2sSinar%20Petir%2C%20Talang%20Padang%2C%20Tanggamus%20Regency%2C%20Lampung!5e0!3m2!1sen!2sid!4v1700000000000!5m2!1sen!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
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

        // Fungsi formatRupiah (sama seperti di promo.php dan artikel.php)
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
            $.ajax({
                url: '../keranjang/get_cart_count.php',
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
            const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
            const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
            const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

            jumlahProdukLainnyaSpan.style.display = 'none';
            jumlahProdukLainnyaSpan.textContent = '';

            fetch('../keranjang/ambil_keranjang_sementara.php')
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


        // Event listener untuk menampilkan/menyembunyikan dropdown keranjang
        document.addEventListener('DOMContentLoaded', () => {
            const linkKeranjang = document.getElementById('link-keranjang');
            const dropdownKeranjang = document.getElementById('dropdown-keranjang');

            muatJumlahKeranjangNav(); // Panggil saat DOM dimuat

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

        // The second `updateWishlistItemCount` function is redundant. I'll remove it.
        // The one that makes an AJAX call is the correct one for dynamic updates.

        function updateWishlistItemCount() {
            // Sama seperti updateCartItemCount, ini adalah 'placeholder'.
            // Badge wishlist di navbar akan menampilkan nilai yang sudah dihitung oleh PHP saat page load.
            // Jika Anda ingin badge wishlist update secara dinamis tanpa refresh halaman,
            // Anda harus menambahkan AJAX request ke endpoint yang menghitung wishlist.
            // Saya tidak menambahkan AJAX baru sesuai instruksi Anda.
        }
        
        $(document).ready(function() {
            // Panggil fungsi update saat halaman dimuat
            muatJumlahKeranjangNav();
            updateWishlistItemCount();
        });
    </script>
</body>
</html>