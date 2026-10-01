<?php
// Termasuk file koneksi.php untuk menghubungkan ke database
// Path disesuaikan jika file ini berada di 'pelanggan/' dan koneksi di 'koneksi/'
include('../koneksi/koneksi.php');
session_start(); // Pastikan session_start() ada di bagian paling atas file

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
$sql_profil = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
$stmt_profil = $conn->prepare($sql_profil);
if ($stmt_profil === false) {
    // Tangani error jika query profil gagal dipersiapkan
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat profil Anda.";
    header('Location: ../../login.php'); // Atau redirect ke halaman error/beranda
    exit();
}
$stmt_profil->bind_param("i", $pengguna_id);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();

if ($result_profil->num_rows === 1) {
    $data_profil = $result_profil->fetch_assoc();
    $nama_pelanggan = htmlspecialchars($data_profil['nama']);
    $foto_pelanggan = htmlspecialchars($data_profil['foto']);
}
$stmt_profil->close();


// --- START: MODIFIKASI BAGIAN INI UNTUK SEARCH, KERANJANG, dan WISHLIST ---

// Inisialisasi variabel pencarian
$search_query = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
}

// MODIFIKASI: Inisialisasi variabel kategori_id
$kategori_id_filter = null;
if (isset($_GET['kategori_id']) && is_numeric($_GET['kategori_id'])) {
    $kategori_id_filter = (int)$_GET['kategori_id'];
}


// Query untuk mengambil TOTAL jumlah item di keranjang (untuk badge di ikon keranjang)
$total_item_keranjang_badge = 0;
$query_total_cart = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?"; // Menggunakan SUM(quantity)
$stmt_total_cart = $conn->prepare($query_total_cart);
if ($stmt_total_cart) {
    $stmt_total_cart->bind_param("i", $pengguna_id);
    $stmt_total_cart->execute();
    $result_total_cart = $stmt_total_cart->get_result();
    $row_total_cart = $result_total_cart->fetch_assoc();
    $total_item_keranjang_badge = $row_total_cart['total_qty'] ?? 0; // Menggunakan ?? 0 untuk penanganan null
    $stmt_total_cart->close();
} else {
    error_log("Gagal mempersiapkan kueri total keranjang: " . $conn->error);
}

$cart_items = [];
$total_harga_keranjang = 0; 

// Query untuk mengambil item di keranjang beserta detail produk dan variasinya
// Batasi hanya 5 item untuk ditampilkan di dropdown (kecuali nanti di ambil_keranjang_sementara.php)
$query_cart_dropdown = "
    SELECT
        kc.id AS keranjang_id,
        kc.produk_id,
        kc.variasi_id,
        kc.quantity,
        p.nama AS nama_produk,
        p.gambar AS gambar_produk,
        p.harga AS harga_produk,
        pv.harga AS harga_variasi,
        pv.stok AS stok_variasi,
        p.stok AS stok_produk,
        r.nama_rasa,
        w.nama_warna,
        u.nama_ukuran
    FROM
        keranjang_customer kc
    JOIN
        produk p ON kc.produk_id = p.id
    LEFT JOIN
        produk_variasi pv ON kc.variasi_id = pv.id
    LEFT JOIN
        rasa r ON pv.rasa_id = r.id
    LEFT JOIN
        warna w ON pv.warna_id = w.id
    LEFT JOIN
        ukuran u ON pv.ukuran_id = u.id
    WHERE
        kc.customer_id = ?
    ORDER BY
        kc.tanggal_ditambahkan DESC
    LIMIT 5"; // Batasi untuk pratinjau di dropdown


$stmt_cart_dropdown = $conn->prepare($query_cart_dropdown);
if ($stmt_cart_dropdown === false) {
    error_log("Terjadi kesalahan saat mempersiapkan query keranjang dropdown: " . $conn->error);
    // Tidak perlu exit, karena ini hanya untuk dropdown preview
} else {
    $stmt_cart_dropdown->bind_param("i", $pengguna_id);
    $stmt_cart_dropdown->execute();
    $result_cart_dropdown = $stmt_cart_dropdown->get_result();

    if ($result_cart_dropdown->num_rows > 0) {
        while ($row = $result_cart_dropdown->fetch_assoc()) {
            $harga_satuan = $row['variasi_id'] ? ($row['harga_variasi'] ?? $row['harga_produk']) : $row['harga_produk'];
            $stok_tersedia = $row['variasi_id'] ? ($row['stok_variasi'] ?? $row['stok_produk']) : $row['stok_produk'];

            $variasi_detail = [];
            if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
            if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
            if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }

            $cart_items[] = [
                'keranjang_id' => $row['keranjang_id'],
                'produk_id' => $row['produk_id'],
                'variasi_id' => $row['variasi_id'],
                'nama_produk' => htmlspecialchars($row['nama_produk']),
                'gambar_produk' => htmlspecialchars($row['gambar_produk']),
                'harga_satuan' => $harga_satuan,
                'quantity' => $row['quantity'],
                'subtotal' => $harga_satuan * $row['quantity'],
                'stok_tersedia' => $stok_tersedia,
                'variasi_string' => implode(', ', $variasi_detail)
            ];
            $total_harga_keranjang += ($harga_satuan * $row['quantity']);
        }
    }
    $stmt_cart_dropdown->close();
}

// MODIFIKASI: Query untuk mengambil TOTAL jumlah item di wishlist (untuk badge di ikon wishlist)
$total_item_wishlist_badge = 0;
if (isset($pengguna_id)) { // Hanya jalankan jika pengguna sudah login
    $query_total_wishlist = "SELECT COUNT(*) AS total_count FROM wishlist WHERE pelanggan_id = ?";
    $stmt_total_wishlist = $conn->prepare($query_total_wishlist);
    if ($stmt_total_wishlist) {
        $stmt_total_wishlist->bind_param("i", $pengguna_id);
        $stmt_total_wishlist->execute();
        $result_total_wishlist = $stmt_total_wishlist->get_result();
        $row_total_wishlist = $result_total_wishlist->fetch_assoc();
        $total_item_wishlist_badge = $row_total_wishlist['total_count'];
        $stmt_total_wishlist->close();
    } else {
        error_log("Gagal mempersiapkan kueri total wishlist: " . $conn->error);
    }
}

// MODIFIKASI: Untuk menentukan apakah suatu produk ada di wishlist pelanggan (untuk status ikon hati)
$wishlist_produk_ids = [];
if (isset($pengguna_id)) {
    $sql_wishlist_produk_ids = "SELECT produk_id FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_produk_ids = $conn->prepare($sql_wishlist_produk_ids);
    if ($stmt_wishlist_produk_ids) {
        $stmt_wishlist_produk_ids->bind_param("i", $pengguna_id);
        $stmt_wishlist_produk_ids->execute();
        $result_wishlist_produk_ids = $stmt_wishlist_produk_ids->get_result();
        while ($row = $result_wishlist_produk_ids->fetch_assoc()) {
            $wishlist_produk_ids[] = $row['produk_id'];
        }
        $stmt_wishlist_produk_ids->close();
    }
}

// --- PAGINATION LOGIC ---
$limit = 12; // Number of products per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

// MODIFIED QUERY: Query lengkap untuk mengambil data produk beserta informasi terkait,
// ditambahkan logika pencarian dan perhitungan jumlah_terjual dari tabel pesanan,
// dan FILTER KATEGORI.
$sql_base = "
    SELECT
        p.id,
        p.nama AS nama_produk,
        p.deskripsi,
        p.harga,
        p.gambar,
        u.nama AS nama_unit_usaha,
        penjual.nama_toko AS nama_penjual,
        kp.nama_kategori,
        p.asal_desa,
        COALESCE(SUM(dp.quantity), 0) AS jumlah_terjual,
        AVG(up.rating) AS rating_produk,
        COUNT(up.id) AS jumlah_ulasan,
        d.nama_diskon,
        d.jenis_diskon,
        d.nilai_diskon
    FROM produk p
    JOIN unit_usaha u ON p.unit_usaha_id = u.id
    LEFT JOIN penjual ON p.penjual_id = penjual.pengguna_id
    LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
    LEFT JOIN ulasan_produk up ON p.id = up.produk_id
    LEFT JOIN produk_diskon pd ON p.id = pd.produk_id
    LEFT JOIN diskon d ON pd.diskon_id = d.id
    LEFT JOIN detail_pesanan dp ON p.id = dp.produk_id
    LEFT JOIN pesanan s ON dp.pesanan_id = s.id AND s.status_pesanan IN ('selesai')
    WHERE p.status_produk = 'aktif'";

$conditions = [];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $conditions[] = "(p.nama LIKE ? OR p.deskripsi LIKE ? OR kp.nama_kategori LIKE ? OR penjual.nama_toko LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

// MODIFIKASI: Tambahkan kondisi kategori jika kategori_id_filter ada
if ($kategori_id_filter !== null) {
    $conditions[] = "p.kategori_id = ?";
    $params[] = $kategori_id_filter;
    $param_types .= "i";
}

if (!empty($conditions)) {
    $sql_base .= " AND " . implode(" AND ", $conditions);
}

$sql_group_by = " GROUP BY p.id ORDER BY p.tanggal_publikasi DESC";

// Query untuk menghitung total produk (untuk pagination)
$sql_count = "SELECT COUNT(DISTINCT p.id) AS total_produk FROM produk p
    JOIN unit_usaha u ON p.unit_usaha_id = u.id
    LEFT JOIN penjual ON p.penjual_id = penjual.pengguna_id
    LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
    WHERE p.status_produk = 'aktif'";

if (!empty($conditions)) {
    $sql_count .= " AND " . implode(" AND ", $conditions);
}

$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Error preparing count query: " . $conn->error);
}

if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_products = $result_count->fetch_assoc()['total_produk'];
$stmt_count->close();

$total_pages = ceil($total_products / $limit);

// Pastikan current_page tidak melebihi total_pages jika total_products = 0
if ($total_products == 0) {
    $current_page = 1;
    $total_pages = 1;
} elseif ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $limit; // Recalculate offset
}

$sql_produk = $sql_base . $sql_group_by . " LIMIT ?, ?";
$stmt_produk = $conn->prepare($sql_produk);

if ($stmt_produk === false) {
    die("Error preparing product query: " . $conn->error);
}

// Bind parameters for product query (search params + limit + offset)
$all_params = array_merge($params, [$offset, $limit]);
$all_param_types = $param_types . "ii";

// Use call_user_func_array to bind parameters dynamically
if (!empty($all_params)) {
    $stmt_produk->bind_param($all_param_types, ...$all_params);
}


$stmt_produk->execute();
$result_produk = $stmt_produk->get_result();

$daftar_produk = [];
if ($result_produk && $result_produk->num_rows > 0) {
    while ($row_produk = $result_produk->fetch_assoc()) {
        $daftar_produk[] = $row_produk;
    }
}
$stmt_produk->close(); // Tutup statement produk

$conn->close(); // Tutup koneksi database setelah semua query selesai
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Produk - BUMDes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <style>
        /* General Body Styling */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Header Styling (dari CSS kedua) */
        header {
            background: url('../img/desa.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 210px 0;
            text-shadow: 1px 1px 4px black;
        }

        .logo-img {
            max-width: 90px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .typing-effect {
            font-size: 2rem;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            border-right: 2px solid white;
            display: inline-block;
            min-height: 2.5rem;
            animation: blink 0.75s step-end infinite;
        }

        @keyframes blink {
            50% { border-color: transparent }
        }

        .section-title {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: bold;
            color: #800000;
        }

        .card:hover {
            transform: scale(1.02);
            transition: transform 0.3s;
        }

        .counter {
            font-size: 2.5rem;
            font-weight: bold;
            color: #800000;
        }

        blockquote {
            font-style: italic;
            color: #555;
            border-left: 5px solid #800000;
            padding-left: 15px;
            margin-top: 1rem;
        }

        .carousel-item img {
            max-height: 710px;
            object-fit: cover;
            width: 100%;
        }

        .carousel-inner {
            max-width: 50%;
            margin: 0 auto;
        }

        .carousel-caption {
            background-color: rgba(0, 0, 0, 0.0);
            color: white;
        }

        .carousel-caption h5:hover,
        .carousel-caption p:hover {
            color: #E6E6FA;
            cursor: pointer;
        }

        .carousel-control-prev, .carousel-control-next {
            margin-left: 350px;
            margin-right: 350px;
        }

        .info-card {
            background-color: #FFF8F8;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #FF4500;
        }

        .info-card h5, .info-card p { color: #FF4500; }


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
            height: auto;
        }

        .nav-link {
            color: white !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #f0f0f0 !important;
        }

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

        /* Cart Page Specific Styles */
        .cart-header {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .cart-header > div {
            flex: 1;
            padding: 0 5px;
        }
        .cart-header .form-check {
            flex-basis: 150px;
            flex-shrink: 0;
        }
        .cart-header .col-md-5 { flex-basis: 35%; }
        .cart-header .col-md-2 { flex-basis: 15%; text-align: center; }
        .cart-header .col-md-1 { flex-basis: 10%; text-align: center; }


        .store-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .store-header {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #fdfdfd;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            font-weight: bold;
            color: #333;
        }

        .store-header .form-check {
            margin-right: 15px;
        }

        .store-header .bi-shop {
            font-size: 1.2em;
            margin-right: 8px;
            color: #ff4500;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px dashed #f0f0f0;
        }
        .product-item:last-child {
            border-bottom: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .product-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
            border: 1px solid #eee;
            flex-shrink: 0;
        }

        .product-info-details {
            flex-grow: 1;
        }

        .product-info-details .name {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
            color: #333;
        }

        .product-info-details .variation {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .product-price {
            width: 120px;
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            font-size: 1.05em;
            flex-shrink: 0;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            width: 150px;
            margin: 0 15px;
            flex-shrink: 0;
        }

        .quantity-control .form-control {
            width: 60px;
            text-align: center;
            margin: 0 5px;
        }

        .quantity-control button {
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            font-size: 1.2em;
        }

        .subtotal-item-col {
            width: 120px;
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            flex-shrink: 0;
        }

        .action-col {
            width: 80px;
            text-align: center;
            flex-shrink: 0;
        }

        .btn-remove-item {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 0.9em;
            cursor: pointer;
            padding: 0;
        }
        .btn-remove-item:hover {
            text-decoration: underline;
        }

        .total-section {
            background-color: #f2f2f2;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .total-row.grand-total {
            font-size: 1.3em;
            font-weight: bold;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }

        /* Cart Footer (Bottom Summary) */
        .cart-footer {
            border-top: 1px solid #e0e0e0;
            background-color: #fff;
            padding: 15px 0;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.05);
        }

        .cart-footer .left-section,
        .cart-footer .right-section {
            display: flex;
            align-items: center;
        }

        .cart-footer .total-display {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }

        .cart-footer .total-display .value {
            color: #ff4500;
            font-size: 1.2em;
            margin-left: 5px;
        }

        .btn-checkout-bottom {
            background-color: #ff4500;
            border-color: #ff4500;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
        }
        .btn-checkout-bottom:hover:not(.disabled) {
            background-color: #e03a00;
            border-color: #e03a00;
            color: white;
        }
        .btn-checkout-bottom.disabled {
            background-color: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Empty Cart/Search Result Styling */
        .text-center.py-5 {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }

        .text-center.py-5 img {
            filter: grayscale(80%);
            opacity: 0.6;
        }

        .text-center.py-5 .btn-primary {
            background-color: #FF4500;
            border-color: #FF4500;
        }
        .text-center.py-5 .btn-primary:hover {
            background-color: #E63C00;
            border-color: #E63C00;
        }

        /* Footer Styling */
        footer {
            background-color: #FF4500;
            color: white;
            padding: 2rem 0;
            margin-top: auto;
            text-align: center;
        }

        footer p, footer small {
            color: rgba(255, 255, 255, 0.8);
        }

        footer h5 {
            color: white;
            margin-bottom: 1rem;
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

        /* Specific social media icon colors (for non-hover state) */
        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

        footer .col-md-4:nth-child(1) {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        footer .col-md-4:nth-child(2) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        footer .col-md-4:nth-child(2) p {
            text-align: center;
        }


        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .cart-header {
                display: none; /* Hide header on small screens */
            }
            .product-item {
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 10px;
            }
            .product-item img {
                width: 60px;
                height: 60px;
                margin-right: 10px;
            }
            .product-info-details {
                flex-basis: calc(100% - 70px);
                margin-bottom: 10px;
            }
            .product-price, .subtotal-item-col {
                width: auto;
                flex-basis: 48%;
                text-align: left;
                margin-top: 5px;
            }
            .quantity-control {
                width: auto;
                flex-basis: 48%;
                justify-content: flex-end;
                margin: 5px 0;
            }
            .action-col {
                flex-basis: 100%;
                text-align: right;
                margin-top: 10px;
            }
            .cart-footer .form-check,
            .cart-footer .btn-link {
                margin-bottom: 10px;
                margin-right: 0 !important;
            }
        }

        /* --- ATURAN UNTUK MEMASTIKAN WARNA PUTIH SOLID --- */

        /* Membuat ikon hati di produk card menjadi merah muda */
        .card .bi-heart-fill {
            color: palevioletred !important;
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

        /* Pastikan semua nav-link di navbar berwarna putih solid */
        .navbar-nav .nav-link {
            color: white !important;
        }
                /* Mengubah warna teks link pagination */
        .pagination .page-link {
            color: #FF4500;
        }

        /* Mengubah warna latar belakang saat aktif/hover (opsional, tapi disarankan) */
        .pagination .page-item.active .page-link,
        .pagination .page-link:hover {
            background-color: #FF4500; /* Warna latar belakang */
            border-color: #FF4500; /* Warna border */
            color: #fff; /* Warna teks saat aktif/hover agar terlihat jelas */
        }

        /* Mengubah warna teks untuk disabled state agar tetap terlihat */
        .pagination .page-item.disabled .page-link {
            color: #ccc; /* Atau warna lain yang cocok */
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../img/logoo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
                BUMDes
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli" aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarPembeli">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="produk.php">Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pelanggan/kategori/kategori.php">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pelanggan/promo/promo.php">Promo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pelanggan/artikel/artikel.php">Artikel</a>
                    </li>
                </ul>
            <form class="d-flex me-2" role="search" action="" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari produk..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                </div>
                <?php if ($kategori_id_filter !== null): ?>
                    <input type="hidden" name="kategori_id" value="<?php echo htmlspecialchars($kategori_id_filter); ?>">
                <?php endif; ?>
            </form>
            <div class="mx-2 d-none d-lg-block"></div> <ul class="navbar-nav mb-2 mb-lg-0">
                </ul>
                </form>
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="wishlist.php">
                            <i class="bi bi-heart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                                <?php echo $total_item_wishlist_badge; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="position-relative">
                            <a class="nav-link" href="keranjang/keranjang.php" id="link-keranjang">
                                <i class="bi bi-cart-fill"></i>
                                <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                    <?php echo $total_item_keranjang_badge ?? '0'; ?>
                                </span>
                            </a>
                            <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2">
                                <h5>Baru Ditambahkan</h5>
                                <ul class="list-unstyled" id="daftar-produk-keranjang">
                                    <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja kosong.</li>
                                </ul>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                                    <a href="keranjang/keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                                <img src="../img/foto/<?php echo $foto_pelanggan; ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <span class="ms-1"><?php echo $nama_pelanggan ?? 'Tamu'; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../pelanggan/profil/profil.php">Profil</a></li>
                            <li><a class="dropdown-item" href="keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="text-center">
        <div class="container">
            <img src="../img/logo.png" alt="Logo BUMDes Desa Sinar Petir" class="logo-img" data-aos="zoom-in">
            <h1 class="typing-effect" id="typingText"></h1>
            <p class="lead text-white" data-aos="fade-up">Kec. Talang Padang Kab. Tanggamus Prov. Lampung</p>
        </div>
    </header>

    <div class="container py-4">
        <div class="row text-center">
            <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-truck me-2"></i> Pengiriman Tercepat</h5><p>1-3 hari</p></div></div>
            <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-shield-lock me-2"></i> Kualitas Terjamin</h5><p>Produk terjamin</p></div></div>
            <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-credit-card me-2"></i> Pembayaran Mudah</h5><p>Cash On Delivery</p></div></div>
            <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-person-lines-fill me-2"></i> Konsultasi Gratis</h5><p>Hubungi kami</p></div></div>
        </div>
    </div>

<div class="container mt-4">
    <h2 class="section-title text-center mb-4">Produk Unggulan Kami</h2>
    <?php if (!empty($search_query ?? '') || $kategori_id_filter !== null): ?>
        <p class="text-center text-muted">
            Menampilkan hasil
            <?php if (!empty($search_query ?? '')) echo 'untuk: "<strong>' . htmlspecialchars($search_query) . '</strong>"'; ?>
            <?php if (!empty($search_query ?? '') && $kategori_id_filter !== null) echo ' dan '; ?>
            <?php if ($kategori_id_filter !== null) {
                // Fetch category name if needed for display
                // This requires an additional query to get the category name by ID
                // For simplicity, we'll just say "produk dalam kategori ini"
                echo 'dalam kategori terpilih.';
            } ?>
        </p>
    <?php endif; ?>
    <div class="row" id="product-list-container">
        <?php if (!empty($daftar_produk)): ?>
            <?php
            $i = 0; // Tetap gunakan $i untuk data-aos-delay
            foreach ($daftar_produk as $produk): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($i % 6 * 100); ?>">
                    <div class="card h-100 shadow-sm">
                        <a href="detail_produk.php?id=<?php echo $produk['id']; ?>" style="text-decoration: none; color: inherit;">
                            <?php if ($produk['gambar']): ?>
                                <img src="../img/barang/<?php echo htmlspecialchars($produk['gambar']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" style="height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <img src="../img/default.jpg" class="card-img-top" alt="Gambar Default" style="height: 150px; object-fit: cover;">
                            <?php endif; ?>
                        </a>
                        <div class="card-body">
                            <h6 class="card-title" style="font-size: 0.8rem;">
                                <a href="detail_produk.php?id=<?php echo $produk['id']; ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($produk['nama_produk']); ?>
                                </a>
                            </h6>
                            <?php if ($produk['nama_diskon']): ?>
                                <div class="mb-1">
                                    <span class="badge bg-danger"><?php echo htmlspecialchars($produk['nama_diskon']); ?></span>
                                    <?php if ($produk['jenis_diskon'] == 'persen'): ?>
                                        <small><?php echo htmlspecialchars($produk['nilai_diskon']); ?>% off</small>
                                    <?php elseif ($produk['jenis_diskon'] == 'fixed'): ?>
                                        <small>Rp <?php echo number_format($produk['nilai_diskon'], 0, ',', '.'); ?> off</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <p class="card-text" style="font-size: 0.9rem; font-weight: bold;">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></p>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2">
                                    <?php if ($produk['rating_produk'] > 0): ?>
                                        <i class="bi bi-star-fill text-warning"></i> <small class="text-muted"><?php echo number_format($produk['rating_produk'], 1); ?></small>
                                    <?php else: ?>
                                        <i class="bi bi-star text-secondary"></i> <small class="text-muted"></small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo $produk['jumlah_terjual']; ?>+ terjual</small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <p class="card-text text-muted" style="font-size: 0.7rem; margin-bottom: 0;">
                                    <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($produk['asal_desa'] ?? 'Lokasi tidak tersedia'); ?>
                                </p>
                                <i class="bi <?php echo (in_array($produk['id'], $wishlist_produk_ids) ? 'bi-heart-fill' : 'bi-heart'); ?>"
                                   style="cursor: pointer; font-size: 1.2rem; <?php echo (in_array($produk['id'], $wishlist_produk_ids) ? 'color: palevioletred;' : ''); ?>"
                                   onclick="toggleWishlist(this)"
                                   data-product-id="<?php echo $produk['id']; ?>">
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $i++; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 alert alert-info text-center py-4" role="alert">
                <?php if (!empty($search_query ?? '') || $kategori_id_filter !== null): ?>
                    <p class="lead mb-3">Mohon maaf, tidak ditemukan produk
                        <?php if (!empty($search_query ?? '')) echo 'dengan kata kunci "<strong>' . htmlspecialchars($search_query) . '</strong>"'; ?>
                        <?php if (!empty($search_query ?? '') && $kategori_id_filter !== null) echo ' dan '; ?>
                        <?php if ($kategori_id_filter !== null) echo 'dalam kategori terpilih.'; ?>
                    </p>
                <?php else: ?>
                    <p class="lead mb-3">Belum ada produk yang tersedia saat ini.</p>
                <?php endif; ?>
                <a href="produk.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Tampilkan Semua Produk</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): // Tampilkan pagination hanya jika ada lebih dari 1 halaman ?>
        <div class="d-flex justify-content-center mt-5">
            <nav aria-label="Produk Pagination">
                <ul class="pagination" id="myPagination">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>" id="prevPage">
                        <a class="page-link" href="<?php
                            $prev_url = "?page=" . ($current_page - 1);
                            if (!empty($search_query)) $prev_url .= "&search=" . urlencode($search_query);
                            if ($kategori_id_filter !== null) $prev_url .= "&kategori_id=" . urlencode($kategori_id_filter);
                            echo $prev_url;
                        ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>

                    <?php
                    $start_page = max(1, $current_page - floor($maxPagesToShow / 2));
                    $end_page = min($total_pages, $current_page + floor($maxPagesToShow / 2));

                    if ($end_page - $start_page + 1 < $maxPagesToShow) {
                        $start_page = max(1, $end_page - $maxPagesToShow + 1);
                    }
                    if ($end_page - $start_page + 1 < $maxPagesToShow) {
                        $end_page = min($total_pages, $start_page + $maxPagesToShow - 1);
                    }

                    if ($start_page > 1) {
                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++):
                        $page_url = "?page=" . $i;
                        if (!empty($search_query)) $page_url .= "&search=" . urlencode($search_query);
                        if ($kategori_id_filter !== null) $page_url .= "&kategori_id=" . urlencode($kategori_id_filter);
                    ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $page_url; ?>" <?php if ($i == $current_page) echo 'aria-current="page"'; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <li class="page-item disabled"><a class="page-link" href="#">...</a></li>
                    <?php endif; ?>

                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>" id="nextPage">
                        <a class="page-link" href="<?php
                            $next_url = "?page=" . ($current_page + 1);
                            if (!empty($search_query)) $next_url .= "&search=" . urlencode($search_query);
                            if ($kategori_id_filter !== null) $next_url .= "&kategori_id=" . urlencode($kategori_id_filter);
                            echo $next_url;
                        ?>" aria-label="Next">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
    <footer class="footer mt-auto py-4 text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <img src="../img/logo.png" alt="Logo Desa" width="60">
                    <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                    <p>Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
                    <div class="sosmed-icons mt-3">
                        <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                        <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                        <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                        <a href="#"><i class="bi bi-instagram instagram-icon"></i></a>
                        <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                        <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold text-orange"><i class="bi bi-chat-dots"></i> HUBUNGI KAMI</h5>
                    <p>Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi Lampung Kode Pos 35377.</p>
                    <p><i class="bi bi-telephone-fill"></i> Telepon: 081272020355</p>
                    <p><i class="bi bi-envelope-fill"></i> Email: snrpetir@gmail.com</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold text-orange"><i class="bi bi-map"></i> PETA LOKASI</h5>
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.100908151834!2d104.5936737!3d-5.2673523!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40e691232c4e23%3A0x6b40e32f5f1c5c1!2sDesa%20Sinar%20Petir!5e0!3m2!1sid!2sid!4v1716347395015!5m2!1sid!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            <hr class="border-top border-light">
            <div class="text-center">
                <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir </small>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
        AOS.init();
        // Fungsi untuk efek typing di header
        const text = "Selamat Datang di Website Resmi BUMDes Desa Sinar Petir";
        const typingElement = document.getElementById("typingText");
        let index = 0;
        let isDeleting = false;

        function typeEffect() {
            if (!typingElement) return; // Pastikan elemen ada

            typingElement.textContent = text.substring(0, index);
            if (!isDeleting && index < text.length) {
                index++;
            } else if (isDeleting && index > 0) {
                index--;
            } else if (index === text.length) {
                isDeleting = true;
                setTimeout(typeEffect, 1500);
                return;
            } else if (index === 0) {
                isDeleting = false;
            }
            setTimeout(typeEffect, isDeleting ? 50 : 100);
        }

        document.addEventListener("DOMContentLoaded", () => {
            typeEffect();
        });

        // MODIFIKASI: Fungsi untuk toggle wishlist (dengan AJAX)
        function toggleWishlist(icon) {
            const productId = icon.dataset.productId;
            let action;
            let newIconClass;
            let iconInCard = icon; // Referensi ikon di dalam card produk

            // Tentukan aksi dan kelas ikon baru
            if (iconInCard.classList.contains('bi-heart')) {
                // Add to wishlist
                action = 'add';
                newIconClass = 'bi-heart-fill';
                // Warna ikon di card akan berubah
                iconInCard.style.color = 'palevioletred';
            } else {
                // Remove from wishlist
                action = 'remove';
                newIconClass = 'bi-heart';
                // Warna ikon di card akan kembali ke default
                iconInCard.style.color = ''; // Mengatur kembali ke warna default CSS
            }

            // Send AJAX request
            fetch('toggle_wishlist.php', { // PASTIKAN PATH INI BENAR
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    action: action
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Update UI based on successful response
                    iconInCard.classList.remove('bi-heart', 'bi-heart-fill');
                    iconInCard.classList.add(newIconClass);

                    // --- BAGIAN PENTING: Memperbarui jumlah wishlist di navbar secara REAL-TIME ---
                    const wishlistCountBadge = document.getElementById('wishlist-count');
                    if (wishlistCountBadge) {
                        // Pastikan data.wishlist_count berisi jumlah yang benar dari server
                        wishlistCountBadge.textContent = data.wishlist_count;
                    }
                    // --- AKHIR BAGIAN PENTING ---

                    console.log(data.message);
                } else {
                    // Handle error, e.g., show an alert
                    alert('Gagal memperbarui wishlist: ' + data.message);
                    console.error('Wishlist update failed:', data.message);
                    // Jika gagal, kembalikan ikon di card ke kondisi semula
                    if (action === 'add') {
                        iconInCard.classList.remove('bi-heart-fill');
                        iconInCard.classList.add('bi-heart');
                        iconInCard.style.color = '';
                    } else {
                        iconInCard.classList.remove('bi-heart');
                        iconInCard.classList.add('bi-heart-fill');
                        iconInCard.style.color = 'palevioletred';
                    }
                }
            })
            .catch(error => {
                console.error('Error toggling wishlist:', error);
                alert('Terjadi kesalahan saat memperbarui wishlist. Silakan coba lagi.');
                // Jika error, kembalikan ikon di card ke kondisi semula
                if (action === 'add') {
                    iconInCard.classList.remove('bi-heart-fill');
                    iconInCard.classList.add('bi-heart');
                    iconInCard.style.color = '';
                } else {
                    iconInCard.classList.remove('bi-heart');
                    iconInCard.classList.add('bi-heart-fill');
                    iconInCard.style.color = 'palevioletred';
                }
            });
        }

        // Fungsi formatRupiah yang diperbaiki
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
                url: 'keranjang/get_cart_count.php', // Pastikan file ini ada dan mengembalikan {status: 'success', count: N}
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
    jumlahProdukLainnyaSpan.style.display = 'none';
    jumlahProdukLainnyaSpan.textContent = '';

    // Lakukan permintaan Fetch ke endpoint yang mengambil data keranjang
    fetch('keranjang/ambil_keranjang_sementara.php')
        .then(response => {
            // Periksa apakah respons HTTP berhasil (status 200 OK)
            if (!response.ok) {
                // Jika tidak berhasil, lemparkan error
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Parse respons sebagai JSON
            return response.json();
        })
        .then(data => {
            // Kosongkan daftar produk yang mungkin sudah ada sebelumnya di dropdown
            daftarProdukKeranjang.innerHTML = '';

            const displayLimit = 3; // Batasi jumlah item produk unik yang ditampilkan di dropdown
            let totalQuantityOtherProducts = 0; // Inisialisasi total kuantitas produk yang tidak ditampilkan

            // Periksa apakah ada data produk di keranjang
            if (data.length > 0) {
                // Jika ada produk, sembunyikan pesan keranjang kosong (jika ada)
                if (pesanKeranjangKosong) {
                    pesanKeranjangKosong.style.display = 'none';
                }

                // Iterasi melalui setiap item produk di data yang diterima
                data.forEach((item, index) => {
                    // Jika indeks item kurang dari batas tampilan, tampilkan item tersebut
                    if (index < displayLimit) {
                        const listItem = document.createElement('li');
                        listItem.classList.add('d-flex', 'align-items-center', 'mb-2');
                        listItem.innerHTML = `
                            <img src="../img/barang/${item.gambar_produk}" alt="${item.nama_produk}" class="img-fluid rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
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
                        // Jika item di luar batas tampilan, tambahkan kuantitasnya ke totalQuantityOtherProducts
                        totalQuantityOtherProducts += item.quantity;
                    }
                });

                // Tampilkan pesan "Produk Lainnya" jika ada produk yang tidak ditampilkan
                if (totalQuantityOtherProducts > 0) {
                    jumlahProdukLainnyaSpan.textContent = `${totalQuantityOtherProducts} Produk Lainnya`;
                    jumlahProdukLainnyaSpan.style.display = 'inline-block';
                } else {
                    jumlahProdukLainnyaSpan.style.display = 'none';
                }

            } else {
                // Jika tidak ada data produk (keranjang kosong)
                if (pesanKeranjangKosong) {
                    pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                    pesanKeranjangKosong.style.display = 'block';
                }
                jumlahProdukLainnyaSpan.style.display = 'none'; // Pastikan ini juga disembunyikan
            }
        })
        .catch(error => {
            // Tangani error jika permintaan Fetch gagal
            console.error('Error fetching cart items for dropdown:', error);
            if (pesanKeranjangKosong) {
                pesanKeranjangKosong.textContent = "Gagal memuat detail keranjang. Silakan coba lagi.";
                pesanKeranjangKosong.style.display = 'block';
            }
            jumlahProdukLainnyaSpan.style.display = 'none'; // Pastikan ini juga disembunyikan saat error
        });
}

        // Event listener untuk menampilkan/menyembunyikan dropdown keranjang
        document.addEventListener('DOMContentLoaded', () => {
            const linkKeranjang = document.getElementById('link-keranjang');
            const dropdownKeranjang = document.getElementById('dropdown-keranjang');

            // Panggil fungsi ini saat halaman dimuat untuk inisialisasi
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
        // JavaScript untuk Smart Pagination (disini diintegrasikan)
        document.addEventListener('DOMContentLoaded', function() {
            const paginationContainer = document.getElementById('myPagination');
            // Pastikan elemen pagination ada sebelum melanjutkan
            if (!paginationContainer) {
                console.warn("Elemen #myPagination tidak ditemukan. Pagination tidak akan berfungsi.");
                return;
            }

            const prevPageBtn = paginationContainer.querySelector('#prevPage');
            const nextPageBtn = paginationContainer.querySelector('#nextPage');
            const productListContainer = document.getElementById('product-list-container');
            const search_query = "<?php echo htmlspecialchars($search_query ?? ''); ?>"; // Ambil nilai search_query dari PHP
            const kategori_id_filter = <?php echo json_encode($kategori_id_filter); ?>; // Ambil nilai kategori_id_filter dari PHP

            const totalPages = <?php echo isset($total_pages) ? (int)$total_pages : 1; ?>;
            let currentPage = <?php echo isset($current_page) ? (int)$current_page : 1; ?>;
            const maxPagesToShow = 5;

            function renderPagination() {
                const existingPageItems = paginationContainer.querySelectorAll('.page-item:not(#prevPage):not(#nextPage)');
                existingPageItems.forEach(item => item.remove());

                let startPage, endPage;

                if (totalPages <= maxPagesToShow) {
                    startPage = 1;
                    endPage = totalPages;
                } else {
                    const pagesAroundCurrent = Math.floor(maxPagesToShow / 2);
                    if (currentPage <= pagesAroundCurrent) {
                        startPage = 1;
                        endPage = maxPagesToShow;
                    } else if (currentPage + pagesAroundCurrent >= totalPages) {
                        startPage = totalPages - maxPagesToShow + 1;
                        endPage = totalPages;
                    } else {
                        startPage = currentPage - pagesAroundCurrent;
                        endPage = currentPage + pagesAroundCurrent;
                    }
                }

                if (startPage > 1) {
                    const ellipsisItem = document.createElement('li');
                    ellipsisItem.className = 'page-item disabled';
                    ellipsisItem.innerHTML = '<a class="page-link" href="#">...</a>';
                    paginationContainer.insertBefore(ellipsisItem, nextPageBtn);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const pageItem = document.createElement('li');
                    pageItem.className = `page-item ${i === currentPage ? 'active' : ''}`;
                    // Buat URL dengan mempertahankan parameter search dan kategori_id
                    let pageUrl = `?page=${i}`;
                    if (search_query) {
                        pageUrl += `&search=${encodeURIComponent(search_query)}`;
                    }
                    if (kategori_id_filter !== null) {
                        pageUrl += `&kategori_id=${encodeURIComponent(kategori_id_filter)}`;
                    }
                    pageItem.innerHTML = `<a class="page-link" href="${pageUrl}" data-page="${i}">${i}</a>`;
                    paginationContainer.insertBefore(pageItem, nextPageBtn);
                }

                if (endPage < totalPages) {
                    const ellipsisItem = document.createElement('li');
                    ellipsisItem.className = 'page-item disabled';
                    ellipsisItem.innerHTML = '<a class="page-link" href="#">...</a>';
                    paginationContainer.insertBefore(ellipsisItem, nextPageBtn);
                }

                prevPageBtn.classList.toggle('disabled', currentPage === 1);
                nextPageBtn.classList.toggle('disabled', currentPage === totalPages);
                
                // Update href for prev/next buttons
                let prevUrl = `?page=${currentPage - 1}`;
                if (search_query) prevUrl += `&search=${encodeURIComponent(search_query)}`;
                if (kategori_id_filter !== null) prevUrl += `&kategori_id=${encodeURIComponent(kategori_id_filter)}`;
                prevPageBtn.querySelector('a').href = prevUrl;

                let nextUrl = `?page=${currentPage + 1}`;
                if (search_query) nextUrl += `&search=${encodeURIComponent(search_query)}`;
                if (kategori_id_filter !== null) nextUrl += `&kategori_id=${encodeURIComponent(kategori_id_filter)}`;
                nextPageBtn.querySelector('a').href = nextUrl;
            }

            renderPagination();
            // loadProducts(currentPage); // No need to call this if products are loaded by PHP initially
        });
    </script>
</body>
</html>