<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat keranjang belanja Anda.";
    header('Location: ../../login.php'); // Sesuaikan path ke halaman login Anda
    exit();
}

$customer_id = $_SESSION['pengguna_id'];

// Ambil informasi pengguna untuk navbar
$nama_pelanggan = null;
$foto_pelanggan = null;
$query_user_info = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?"; // Menggunakan tabel 'pelanggan'
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

// Ambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0;
if (isset($customer_id)) {
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

// --- Logika Pencarian ---
$search_term = ''; // Ini adalah nilai yang akan ditampilkan di input pencarian HTML
$search_query_param = null; // Ini adalah nilai dengan wildcard untuk query database

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = $_GET['search']; // Ambil nilai mentah untuk ditampilkan
    $search_query_param = '%' . $search_term . '%'; // Tambahkan wildcard hanya untuk query database
}
// --- Akhir Logika Pencarian ---

// Query untuk mengambil semua item di keranjang pengguna
$query_keranjang = "
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
        u.nama_ukuran,
        pen.nama_toko AS nama_toko_penjual
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
    JOIN
        penjual pen ON p.penjual_id = pen.pengguna_id
    WHERE
        kc.customer_id = ?
";

// Tambahan kondisi pencarian jika ada search_query_param
if ($search_query_param !== null) {
    $query_keranjang .= "
        AND (
            p.nama LIKE ? OR
            r.nama_rasa LIKE ? OR
            w.nama_warna LIKE ? OR
            u.nama_ukuran LIKE ?
        )
    ";
}

$query_keranjang .= " ORDER BY pen.nama_toko, kc.tanggal_ditambahkan DESC";


$stmt_keranjang = $conn->prepare($query_keranjang);

// Penyesuaian bind_param berdasarkan adanya search_query_param
if ($search_query_param !== null) {
    $stmt_keranjang->bind_param("issss", $customer_id, $search_query_param, $search_query_param, $search_query_param, $search_query_param);
} else {
    $stmt_keranjang->bind_param("i", $customer_id);
}

$stmt_keranjang->execute();
$result_keranjang = $stmt_keranjang->get_result();

$cart_items = [];
$grouped_cart_items = [];
$total_harga_semua_produk = 0;
$total_produk_di_keranjang = 0; // Untuk badge navbar

if ($result_keranjang->num_rows > 0) {
    while ($row = $result_keranjang->fetch_assoc()) {
        $harga_satuan = $row['variasi_id'] ? $row['harga_variasi'] : $row['harga_produk'];
        $stok_tersedia = $row['variasi_id'] ? $row['stok_variasi'] : $row['stok_produk'];

        // Fallback jika variasi harga/stok null (misalnya variasi dihapus atau data tidak konsisten)
        if ($row['variasi_id'] && ($harga_satuan === null || $stok_tersedia === null)) {
            $harga_satuan = $row['harga_produk']; // Gunakan harga produk utama jika variasi tidak ditemukan
            $stok_tersedia = $row['stok_produk']; // Gunakan stok produk utama jika variasi tidak ditemukan
        }

        $subtotal = $harga_satuan * $row['quantity'];
        $total_harga_semua_produk += $subtotal;
        $total_produk_di_keranjang += $row['quantity']; // Menghitung total kuantitas produk

        $variasi_detail = [];
        if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
        if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
        if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }

        $item_data = [
            'keranjang_id' => $row['keranjang_id'],
            'produk_id' => $row['produk_id'],
            'variasi_id' => $row['variasi_id'],
            'nama_produk' => htmlspecialchars($row['nama_produk']),
            'gambar_produk' => htmlspecialchars($row['gambar_produk']),
            'harga_satuan' => $harga_satuan,
            'quantity' => $row['quantity'],
            'subtotal' => $subtotal,
            'stok_tersedia' => $stok_tersedia,
            'variasi_string' => implode(', ', $variasi_detail),
            'nama_toko_penjual' => htmlspecialchars($row['nama_toko_penjual'])
        ];

        // Kelompokkan item berdasarkan toko penjual
        if (!isset($grouped_cart_items[$row['nama_toko_penjual']])) {
            $grouped_cart_items[$row['nama_toko_penjual']] = [];
        }
        $grouped_cart_items[$row['nama_toko_penjual']][] = $item_data;
        $cart_items[] = $item_data; // Juga simpan di array datar jika diperlukan
    }
}
$stmt_keranjang->close();

// Ambil ID produk yang ada di wishlist saat ini untuk inisialisasi ikon
$wishlist_produk_ids = [];
if ($customer_id) {
    $query_wishlist_init = "SELECT produk_id FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_init = $conn->prepare($query_wishlist_init);
    if ($stmt_wishlist_init) {
        $stmt_wishlist_init->bind_param("i", $customer_id);
        $stmt_wishlist_init->execute();
        $result_wishlist_init = $stmt_wishlist_init->get_result();
        while ($row = $result_wishlist_init->fetch_assoc()) {
            $wishlist_produk_ids[] = $row['produk_id'];
        }
        $stmt_wishlist_init->close();
    }
}

$conn->close();

// Definisikan $page_title sebelum menyertakan header.php
$page_title = "Keranjang Belanja";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Keranjang Belanja - Produk</title>
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
        color: rgba(255, 255, 255, 0.8) !important;
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
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    }

    .navbar-nav .dropdown-item {
        color: rgba(255, 255, 255, 0.8);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .navbar-nav .dropdown-item:hover {
        background-color: #ffe0b2;
        color: white;
        /* background-color: #ffe0b2; <-- Ini duplikasi, hapus salah satu */
    }

    .navbar-nav .dropdown-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
    }

    /* Dropdown for cart items in navbar (if used) */
    #dropdown-keranjang {
        min-width: 280px;
        max-height: 400px;
        overflow-y: auto;
        right: 0;
        left: auto;
        transform: translateX(0);
        border: 1px solid #eee;
    }

    #dropdown-keranjang .list-unstyled li img {
        border-radius: 5px;
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
        flex-basis: 150px; /* Lebar untuk checkbox dan label Produk */
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

    /* Combined .product-card and .product-item */
    .product-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px dashed #f0f0f0;
        /* --- START PERUBAHAN PENTING DI SINI --- */
        /* Mengizinkan wrap pada item jika tidak cukup ruang, ini krusial untuk zoom */
        flex-wrap: wrap; 
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
        /* Tambahkan min-width agar tidak terlalu kecil */
        min-width: 150px; 
        /* Pastikan elemen ini bisa mengecil jika diperlukan */
        flex-shrink: 1; 
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

    /* Modifikasi untuk `product-price`, `quantity-control`, `subtotal-item-col`, `action-col` */
    /* Untuk desktop (layar besar), kita ingin mereka sejajar */
    @media (min-width: 768px) { /* Ini adalah breakpoint md dari Bootstrap */
        .product-item > div {
            /* Atur flex-grow, flex-shrink, dan flex-basis di sini untuk kontrol yang lebih baik */
            flex-grow: 0; /* Jangan membesar secara otomatis */
            flex-shrink: 0; /* Jangan menyusut secara otomatis */
            padding-left: 5px; /* Tambahkan padding agar tidak terlalu rapat */
            padding-right: 5px;
        }

        .product-item .col-1 { /* Checkbox */
            flex-basis: 5%; /* Bisa disesuaikan */
            max-width: 5%;
        }
        .product-item .col-md-5 { /* Image and Product Info */
            flex-basis: 35%; /* Bisa disesuaikan */
            max-width: 35%;
        }
        .product-item .col-md-2:nth-of-type(1) { /* Harga Satuan (Rp ...) */
            flex-basis: 15%; /* Bisa disesuaikan */
            max-width: 15%;
            text-align: center; /* Tetap center atau start sesuai desain Anda */
        }
        .product-item .col-md-2.quantity-control { /* Quantity Control */
            flex-basis: 15%; /* Sesuaikan agar cukup */
            max-width: 15%;
            justify-content: center;
        }
        .product-item .col-md-2:nth-of-type(2) { /* Item Subtotal + Hapus + Wishlist */
            flex-basis: 20%; /* Beri ruang lebih banyak untuk ketiganya */
            max-width: 20%;
            text-align: right;
            display: flex; /* Jadikan flex container */
            flex-direction: column; /* Tata dalam kolom */
            align-items: flex-end; /* Ratakan ke kanan */
            justify-content: center;
        }
        
        /* Hapus atau setel ulang properti yang sebelumnya konflik */
        .product-price { /* Ini target div nya, bukan col-md-2 */
            width: auto; /* Biarkan flex menentukan */
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            font-size: 1.05em;
            flex-grow: 1; /* Biarkan tumbuh jika ada ruang */
            flex-shrink: 1; /* Izinkan menyusut */
        }

        .quantity-control {
            width: auto; /* Biarkan flex menentukan */
            margin: 0 5px; /* Sesuaikan margin */
            flex-grow: 1; /* Biarkan tumbuh jika ada ruang */
            flex-shrink: 1; /* Izinkan menyusut */
        }

        .subtotal-item-col { /* Ini target span nya, bukan col-md-2 */
            width: auto; /* Biarkan flex menentukan */
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            flex-grow: 1; /* Biarkan tumbuh jika ada ruang */
            flex-shrink: 1; /* Izinkan menyusut */
        }

        .action-col { /* Ini target div nya, bukan col-md-2 */
            width: auto; /* Biarkan flex menentukan */
            text-align: center;
            flex-grow: 1; /* Biarkan tumbuh jika ada ruang */
            flex-shrink: 1; /* Izinkan menyusut */
            /* Pastikan elemen di dalamnya tidak terpotong */
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    }
    /* --- END PERUBAHAN PENTING DI SINI --- */

    .product-price {
        /* Ini adalah harga per satuan, ini tidak perlu lebar tetap */
        /* width: 120px; <--- Hapus atau komentar ini */
        text-align: right;
        font-weight: bold;
        color: #ff4500;
        font-size: 1.05em;
        /* flex-shrink: 0; <--- Hapus atau komentar ini, biarkan fleksibel */
        /* Tambahkan agar bisa responsif */
        min-width: 80px; /* Minimal lebar */
    }

    .quantity-control {
        display: flex;
        align-items: center;
        /* width: 150px; <--- Hapus atau komentar ini, biarkan fleksibel */
        margin: 0 15px;
        /* flex-shrink: 0; <--- Hapus atau komentar ini, biarkan fleksibel */
        /* Tambahkan agar bisa responsif */
        min-width: 120px; /* Minimal lebar agar tombol dan input tidak berdesakan */
        justify-content: center; /* Pusatkan kontrol kuantitas */
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
        /* width: 120px; <--- Hapus atau komentar ini, biarkan fleksibel */
        text-align: right;
        font-weight: bold;
        color: #ff4500;
        /* flex-shrink: 0; <--- Hapus atau komentar ini, biarkan fleksibel */
        /* Tambahkan agar bisa responsif */
        min-width: 80px; /* Minimal lebar */
    }

    .action-col {
        /* width: 80px; <--- Hapus atau komentar ini, biarkan fleksibel */
        text-align: center;
        /* flex-shrink: 0; <--- Hapus atau komentar ini, biarkan fleksibel */
        /* Tambahkan agar bisa responsif */
        min-width: 80px; /* Minimal lebar */
        /* Untuk menata Hapus dan Wishlist */
        display: flex;
        flex-direction: column; /* Tumpuk ke bawah */
        align-items: center; /* Pusatkan horizontal */
        justify-content: center;
    }

    .btn-remove-item {
        background: none;
        border: none;
        color: #dc3545;
        font-size: 0.9em;
        cursor: pointer;
        padding: 0;
        margin-bottom: 5px; /* Beri sedikit jarak dari wishlist icon */
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

    /* Specific social media icon colors (kept from the second snippet for vibrancy) */
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
        .cart-footer .left-section,
        .cart-footer .right-section {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }
        .cart-footer .total-display {
            width: 100%;
            text-align: right;
            margin-top: 10px;
        }
        .btn-checkout-bottom {
            width: 100%;
            margin-top: 10px;
        }
        .cart-footer .form-check,
        .cart-footer .btn-link {
            margin-bottom: 10px;
            margin-right: 0 !important;
        }
    }

    /* --- PENAMBAHAN UNTUK WARNA PUTIH --- */

    /* Membuat ikon hati menjadi putih solid */
    .bi-heart-fill {
        color: white !important;
    }
    .wishlist-icon {
        cursor: pointer;
        font-size: 1.2rem;
        color: #ccc; /* Default color for not in wishlist */
    }
    .wishlist-icon.active {
        color: palevioletred !important; /* Color when in wishlist */
    }

    /* Membuat tulisan nama_pelanggan menjadi putih */
    .navbar-nav .dropdown-toggle span.ms-1 {
        color: white !important;
    }

    /* Pastikan semua nav-link di navbar berwarna putih, termasuk dropdown-toggle */
    .navbar-nav .nav-link {
        color: white !important;
    }
    .product-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 5px;
    }
    .quantity-control button {
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
    }
    .quantity-control input {
        width: 50px;
        text-align: center;
        height: 35px;
        border: 1px solid #dee2e6;
        border-radius: 0;
    }
    .quantity-control input:focus {
        outline: none;
        box-shadow: none;
    }
    .form-check-input {
        cursor: pointer;
    }
    /* Style for disabled checkout button */
    #checkoutBtn:disabled {
        background-color: #cccccc !important;
        border-color: #cccccc !important;
        cursor: not-allowed;
    }
    /* Existing style for checkout button */
    #checkoutBtn {
        background-color: #ff4500 !important;
        border-color: #ff4500 !important;
    }

    /* Styles to change blue to #ff4500 */
    .text-primary {
        color: #ff4500 !important; /* Changes the color of text with class 'text-primary' */
    }
    .form-check-input:checked {
        background-color: #ff4500; /* Changes background of checked checkbox */
        border-color: #ff4500;      /* Changes border of checked checkbox */
    }
    .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(255, 69, 0, 0.25); /* Changes focus shadow to a lighter orange */
    }

    /* NEW STYLE FOR THE CART BADGE (adjusted for your new navbar structure) */
    /* Pastikan badge di navbar Anda menggunakan id `jumlah-keranjang` */
    .navbar-nav .nav-link .badge {
        background-color: white !important; /* Set background to white */
        color: #ff4500 !important;          /* Set text color to #ff4500 (OrangeRed) */
        border: 1px solid #ff4500;          /* Add a subtle border matching the text color */
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
            <form class="d-flex me-2 mb-2" role="search" action="keranjang.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari produk di keranjang..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../wishlist.php">
                        <i class="bi bi-heart-fill"></i>
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-badge">
                            <?php echo $total_item_wishlist ?? '0'; ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="position-relative">
                        <a class="nav-link active" href="keranjang.php" id="link-keranjang">
                            <i class="bi bi-cart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                <?php echo $total_produk_di_keranjang ?? '0'; ?>
                            </span>
                        </a>
                        </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                                <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan ?? 'Tamu'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item" href="pesanan_saya.php">Pesanan Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container my-4">
        <h2 class="mb-4 text-center">Keranjang Belanja</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <?php if (!empty($search_term)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Tidak ada produk di keranjang yang cocok dengan "<?php echo htmlspecialchars($search_term); ?>".
                    <br>
                    <a href="keranjang.php" class="btn mt-2 text-white" style="background-color: #ff4500; border-color: #ff4500;">Lihat Semua Keranjang</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    <i class="bi bi-cart-x-fill mb-3" style="font-size: 80px; color: #FF4500;"></i>
                    <p class="lead">Keranjang belanja Anda kosong.</p>
                    <p>Mulai belanja sekarang dan tambahkan produk ke keranjang Anda!</p>
                    <a href="../produk.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Produk</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                            <label class="form-check-label" for="selectAllCheckbox">
                                Pilih Semua <span class="total-selected-items"></span>
                            </label>
                        </div>
                        <button class="btn btn-sm btn-danger" id="removeSelectedBtn" disabled>
                            Hapus Produk Terpilih (<span id="selectedProductCount">0</span>)
                        </button>
                    </div>
                </div>
            </div>

            <?php foreach ($grouped_cart_items as $nama_toko => $items_per_toko): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-shop me-2"></i><?php echo $nama_toko; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items_per_toko as $item): ?>
                            <div class="row align-items-center mb-3 py-2 border-bottom product-item" data-keranjang-id="<?php echo $item['keranjang_id']; ?>">
                                <div class="col-1 text-center">
                                    <input class="form-check-input product-checkbox" type="checkbox"
                                            data-keranjang-id="<?php echo $item['keranjang_id']; ?>"
                                            data-subtotal="<?php echo $item['subtotal']; ?>"
                                            id="checkbox-<?php echo $item['keranjang_id']; ?>">
                                </div>
                                <div class="col-md-5 d-flex align-items-center">
                                    <img src="../../img/barang/<?php echo $item['gambar_produk']; ?>" class="product-image me-3"
                                            onerror="this.onerror=null; this.src='../../img/placeholder-no-image.png';"
                                            alt="<?php echo $item['nama_produk']; ?>">
                                    <div>
                                        <h6 class="mb-0"><?php echo $item['nama_produk']; ?></h6>
                                        <?php if (!empty($item['variasi_string'])): ?>
                                            <small class="text-muted"><?php echo $item['variasi_string']; ?></small><br>
                                        <?php endif; ?>
                                        <small class="text-muted">Stok: <?php echo $item['stok_tersedia']; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-2 text-center text-md-start" data-harga-satuan="<?php echo $item['harga_satuan']; ?>">
                                    Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?>
                                </div>
                                <div class="col-md-2 text-center quantity-control d-flex justify-content-center align-items-center">
                                    <button class="btn btn-outline-secondary btn-sm minus-btn"
                                            data-keranjang-id="<?php echo $item['keranjang_id']; ?>"
                                            data-produk-id="<?php echo $item['produk_id']; ?>"
                                            data-variasi-id="<?php echo $item['variasi_id'] ?? ''; ?>">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm text-center quantity-input mx-1"
                                            value="<?php echo $item['quantity']; ?>"
                                            data-keranjang-id="<?php echo $item['keranjang_id']; ?>"
                                            data-produk-id="<?php echo $item['produk_id']; ?>"
                                            data-variasi-id="<?php echo $item['variasi_id'] ?? ''; ?>"
                                            data-stok-tersedia="<?php echo $item['stok_tersedia']; ?>"
                                            readonly>
                                    <button class="btn btn-outline-secondary btn-sm plus-btn"
                                            data-keranjang-id="<?php echo $item['keranjang_id']; ?>"
                                            data-produk-id="<?php echo $item['produk_id']; ?>"
                                            data-variasi-id="<?php echo $item['variasi_id'] ?? ''; ?>">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <div class="col-md-2 text-center text-md-end">
                                    <span class="item-subtotal">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                                    <button class="btn btn-link text-danger remove-btn"
                                            data-keranjang-id="<?php echo $item['keranjang_id']; ?>">
                                        Hapus
                                    </button>
                                    <i class="bi wishlist-icon
                                        <?php echo in_array($item['produk_id'], $wishlist_produk_ids) ? 'bi-heart-fill active' : 'bi-heart'; ?>"
                                        data-product-id="<?php echo $item['produk_id']; ?>"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="fixed-bottom bg-white shadow-lg p-3">
                <div class="container d-flex justify-content-end align-items-center">
                    <div class="me-4 text-end">
                        <small>Total Harga (<span id="totalCheckedItemsCount">0</span> produk terpilih):</small><br>
                        <h4 class="text-primary mt-1" id="grandTotalDisplay">Rp 0</h4>
                    </div>
                    <button class="btn btn-lg text-white" style="background-color: #ff4500; border-color: #ff4500;" id="checkoutBtn" disabled>
                        Checkout
                    </button>
                </div>
            </div>
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

        // Script typing effect dari header.php (jika ingin tetap ada)
        const textToType = "BUMDes Desa Sinar Petir";
        const typingTextElement = document.getElementById('typingText');
        let i = 0;

        function typeWriter() {
            if (i < textToType.length) {
                typingTextElement.innerHTML += textToType.charAt(i);
                i++;
                setTimeout(typeWriter, 100);
            }
        }
    </script>
    <script>
        // Fungsi untuk menampilkan pesan alert (sudah dimodifikasi untuk auto-dismiss)
function showMessage(type, message, targetElementId = 'alert-placeholder', autoDismiss = true, dismissTime = 3000) {
    const alertContainer = $('#' + targetElementId);
    alertContainer.empty(); // Hapus alert sebelumnya

    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    alertContainer.append(alertHtml);

    // Tambahkan fungsionalitas auto-dismiss
    if (autoDismiss) {
        // Cari elemen alert yang baru ditambahkan di dalam container
        const newAlert = alertContainer.find('.alert');
        setTimeout(function() {
            newAlert.alert('close'); // Tutup alert menggunakan fungsi Bootstrap
        }, dismissTime);
    }
}
    $(document).ready(function() {
        // Data wishlist_produk_ids dari PHP untuk inisialisasi ikon
        const wishlistProdukIds = <?php echo json_encode($wishlist_produk_ids); ?>;

        // Inisialisasi ikon wishlist saat DOM siap
        $('.wishlist-icon').each(function() {
            const productId = $(this).data('product-id');
            if (wishlistProdukIds.includes(productId)) {
                $(this).removeClass('bi-heart').addClass('bi-heart-fill active');
            } else {
                $(this).removeClass('bi-heart-fill active').addClass('bi-heart');
            }
        });

        // Fungsi untuk menampilkan pesan alert
        function showMessage(type, message, targetElementId = 'alert-placeholder') {
            const alertElement = $('#' + targetElementId);
            alertElement.empty(); // Hapus alert sebelumnya
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            alertElement.append(alertHtml);
        }

        // Fungsi untuk memformat angka menjadi format Rupiah
        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(angka).replace('Rp', '').trim();
        }

        // Fungsi untuk memperbarui jumlah item di keranjang (badge navbar)
        function updateCartItemCount() {
            $.ajax({
                url: 'get_cart_count.php', // File PHP yang mengambil jumlah item di keranjang
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#jumlah-keranjang').text(response.count); // Menggunakan ID yang sesuai
                    } else {
                        console.error('Gagal mengambil jumlah keranjang: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error (get_cart_count):', status, error, xhr.responseText);
                }
            });
        }

        // --- Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX ---
        function updateWishlistItemCount() {
            $.ajax({
                url: '../wishlist/get_wishlist_count.php', // Sesuaikan path ke file PHP Anda
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#jumlah-wishlist-badge').text(response.count);
                    } else {
                        console.error('Gagal memuat jumlah wishlist di navbar:', response.message);
                        $('#jumlah-wishlist-badge').text('0');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error (get_wishlist_count):', status, error);
                    $('#jumlah-wishlist-badge').text('0');
                }
            });
        }

        // --- Fungsi untuk toggle wishlist (dengan AJAX ke DB) ---
        $(document).on('click', '.wishlist-icon', function() {
            const icon = $(this);
            const productId = icon.data('product-id');
            let action; // 'add' or 'remove'

            if (icon.hasClass('bi-heart')) {
                action = 'add';
            } else {
                action = 'remove';
            }

            $.ajax({
                url: '../toggle_wishlist.php', // PATH INI SANGAT PENTING. Disesuaikan agar sama dengan produk.php
                method: 'POST',
                data: {
                    product_id: productId,
                    action: action
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (action === 'add') {
                            icon.removeClass('bi-heart').addClass('bi-heart-fill active');
                            showMessage('success', 'Produk berhasil ditambahkan ke wishlist!', 'alert-placeholder');
                        } else {
                            icon.removeClass('bi-heart-fill active').addClass('bi-heart');
                            showMessage('success', 'Produk berhasil dihapus dari wishlist!', 'alert-placeholder');
                        }
                        updateWishlistItemCount(); // Perbarui badge wishlist di navbar
                    } else {
                        console.error('Gagal memperbarui wishlist:', response.message);
                        showMessage('danger', 'Gagal memperbarui wishlist: ' + response.message, 'alert-placeholder');
                        // Kembalikan ikon ke kondisi semula jika terjadi kesalahan dari server
                        if (action === 'add') {
                            icon.removeClass('bi-heart-fill active').addClass('bi-heart');
                        } else {
                            icon.removeClass('bi-heart').addClass('bi-heart-fill active');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error (toggleWishlist):', status, error, xhr.responseText);
                    showMessage('danger', 'Terjadi kesalahan koneksi saat memperbarui wishlist.', 'alert-placeholder');
                    // Kembalikan ikon ke kondisi semula jika terjadi kesalahan koneksi
                    if (action === 'add') {
                        icon.removeClass('bi-heart-fill active').addClass('bi-heart');
                    } else {
                        icon.removeClass('bi-heart').addClass('bi-heart-fill active');
                    }
                }
            });
        });
        // --- Akhir Fungsi toggle_wishlist ---
        
        // Fungsi untuk memperbarui total harga yang dicentang dan status tombol
        function updateSummaryAndButtons() {
            let totalHargaChecked = 0;
            let checkedItemsCount = 0;
            let totalProductCheckboxes = $('.product-checkbox').length;

            $('.product-checkbox:checked').each(function() {
                const subtotal = parseFloat($(this).data('subtotal'));
                if (!isNaN(subtotal)) {
                    totalHargaChecked += subtotal;
                }
                checkedItemsCount++;
            });

            $('#grandTotalDisplay').text('Rp ' + formatRupiah(totalHargaChecked));
            $('#totalCheckedItemsCount').text(checkedItemsCount);
            $('#selectedProductCount').text(checkedItemsCount);

            // Aktifkan/nonaktifkan tombol checkout
            if (checkedItemsCount > 0) {
                $('#checkoutBtn').prop('disabled', false);
                $('#removeSelectedBtn').prop('disabled', false);
            } else {
                $('#checkoutBtn').prop('disabled', true);
                $('#removeSelectedBtn').prop('disabled', true);
            }

            // Atur status checkbox "Pilih Semua"
            if (totalProductCheckboxes > 0 && checkedItemsCount === totalProductCheckboxes) {
                $('#selectAllCheckbox').prop('checked', true);
            } else {
                $('#selectAllCheckbox').prop('checked', false);
            }
            $('#selectAllCheckbox').next('label').text(`Pilih Semua (${checkedItemsCount}/${totalProductCheckboxes})`);
        }

        // Panggil fungsi update saat halaman dimuat
        updateSummaryAndButtons();
        updateCartItemCount();
        updateWishlistItemCount(); // Panggil juga untuk wishlist saat halaman dimuat

        // --- Event Listener untuk Quantity Control (Plus/Minus) ---
        $(document).on('click', '.minus-btn, .plus-btn', function() {
            const button = $(this);
            const keranjangId = button.data('keranjang-id');
            const produkId = button.data('produk-id');
            const variasiId = button.data('variasi-id') || null;
            const quantityInput = $(`input.quantity-input[data-keranjang-id="${keranjangId}"]`);
            let currentQuantity = parseInt(quantityInput.val());
            const stokTersedia = parseInt(quantityInput.data('stok-tersedia'));

            if (button.hasClass('minus-btn')) {
                if (currentQuantity > 1) {
                    currentQuantity--;
                } else {
                    showMessage('info', 'Kuantitas tidak bisa kurang dari 1.', 'alert-placeholder');
                    return;
                }
            } else if (button.hasClass('plus-btn')) {
                if (currentQuantity < stokTersedia) {
                    currentQuantity++;
                } else {
                    showMessage('warning', `Stok produk hanya tersedia ${stokTersedia}.`, 'alert-placeholder');
                    return;
                }
            }

            // Update input display
            quantityInput.val(currentQuantity);

            // Kirim request AJAX untuk memperbarui kuantitas di database
            $.ajax({
                url: 'update_keranjang.php', // URL ke script PHP update_keranjang.php
                method: 'POST',
                dataType: 'json',
                data: {
                    keranjang_id: keranjangId,
                    produk_id: produkId,
                    variasi_id: variasiId,
                    quantity: currentQuantity
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showMessage('success', response.message, 'alert-placeholder');
                        const hargaSatuan = parseFloat(button.closest('.product-item').find('.col-md-2.text-center.text-md-start').data('harga-satuan'));
                        const newSubtotal = hargaSatuan * currentQuantity;
                        button.closest('.product-item').find('.item-subtotal').text('Rp ' + formatRupiah(newSubtotal));
                        $(`#checkbox-${keranjangId}`).data('subtotal', newSubtotal);
                        updateSummaryAndButtons();
                        updateCartItemCount();
                    } else {
                        showMessage('danger', response.message, 'alert-placeholder');
                        quantityInput.val(parseInt(quantityInput.val()) - (button.hasClass('plus-btn') ? 1 : -1));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error (update_keranjang):', status, error, xhr.responseText);
                    showMessage('danger', 'Terjadi kesalahan saat memperbarui kuantitas.', 'alert-placeholder');
                    quantityInput.val(parseInt(quantityInput.val()) - (button.hasClass('plus-btn') ? 1 : -1));
                }
            });
        });

        // --- Event Listener untuk tombol Hapus per Item ---
        $(document).on('click', '.remove-btn', function() {
            const keranjangId = $(this).data('keranjang-id');
            const itemElement = $(this).closest('.product-item');

            if (confirm('Anda yakin ingin menghapus produk ini dari keranjang?')) {
                $.ajax({
                    url: 'remove_keranjang_item.php', // URL ke script PHP remove_keranjang_item.php
                    method: 'POST',
                    dataType: 'json',
                    data: { keranjang_id: keranjangId },
                    success: function(response) {
                        if (response.status === 'success') {
                            showMessage('success', response.message, 'alert-placeholder');
                            itemElement.fadeOut(300, function() {
                                $(this).remove();
                                updateSummaryAndButtons();
                                updateCartItemCount();
                                if ($('.product-item').length === 0) {
                                    $('.container.my-4').append(`
                                        <div class="alert alert-info text-center" role="alert">
                                            Keranjang belanja Anda kosong.
                                        </div>
                                    `);
                                    $('.fixed-bottom').hide();
                                    $('.card.shadow-sm.mb-4').hide();
                                }
                            });
                        } else {
                            showMessage('danger', response.message, 'alert-placeholder');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error (remove_keranjang_item):', status, error, xhr.responseText);
                        showMessage('danger', 'Terjadi kesalahan saat menghapus produk.', 'alert-placeholder');
                    }
                });
            }
        });

        // --- Event Listener untuk checkbox "Pilih Semua" ---
        $('#selectAllCheckbox').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.product-checkbox').prop('checked', isChecked);
            updateSummaryAndButtons();
        });

        // Event Listener untuk setiap checkbox produk
        $(document).on('change', '.product-checkbox', function() {
            updateSummaryAndButtons();
        });

        // --- Event Listener untuk tombol Hapus Produk Terpilih ---
        $('#removeSelectedBtn').on('click', function() {
            const selectedIds = [];
            $('.product-checkbox:checked').each(function() {
                selectedIds.push($(this).data('keranjang-id'));
            });

            if (selectedIds.length === 0) {
                showMessage('warning', 'Tidak ada produk terpilih untuk dihapus.', 'alert-placeholder');
                return;
            }

            if (confirm(`Anda yakin ingin menghapus ${selectedIds.length} produk terpilih dari keranjang?`)) {
                $.ajax({
                    url: 'remove_multiple_keranjang_items.php', // URL ke script PHP remove_multiple_keranjang_items.php
                    method: 'POST',
                    dataType: 'json',
                    data: { keranjang_ids: selectedIds },
                    success: function(response) {
                        if (response.status === 'success') {
                            showMessage('success', response.message, 'alert-placeholder');
                            selectedIds.forEach(id => {
                                $(`input.product-checkbox[data-keranjang-id="${id}"]`).closest('.product-item').fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            setTimeout(() => {
                                updateSummaryAndButtons();
                                updateCartItemCount();
                                if ($('.product-item').length === 0) {
                                    $('.container.my-4').append(`
                                        <div class="alert alert-info text-center" role="alert">
                                            Keranjang belanja Anda kosong.
                                        </div>
                                    `);
                                    $('.fixed-bottom').hide();
                                    $('.card.shadow-sm.mb-4').hide();
                                }
                            }, 350); // Small delay to allow fadeOut
                        } else {
                            showMessage('danger', response.message, 'alert-placeholder');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error (remove_multiple_keranjang_items):', status, error, xhr.responseText);
                        showMessage('danger', 'Terjadi kesalahan saat menghapus produk.', 'alert-placeholder');
                    }
                });
            }
        });

        // --- Event Listener untuk tombol Checkout ---
        $('#checkoutBtn').on('click', function() {
            const selectedCartIds = [];
            $('.product-checkbox:checked').each(function() {
                selectedCartIds.push($(this).data('keranjang-id'));
            });

            if (selectedCartIds.length === 0) {
                showMessage('warning', 'Pilih setidaknya satu produk untuk checkout.', 'alert-placeholder');
                return;
            }

            // Redirect ke halaman checkout dengan ID keranjang yang dipilih
            const checkoutUrl = `checkout.php?cart_ids=${selectedCartIds.join(',')}`;
            window.location.href = checkoutUrl;
        });
        

    });
    </script>
</body>
</html>