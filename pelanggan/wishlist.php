<?php
session_start();
include '../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// --- Bagian Penanganan AJAX untuk Hapus Item (TETAP SAMA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_wishlist_item') {
    header('Content-Type: application/json');

    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

    if (!isset($_SESSION['pengguna_id'])) {
        $response = ['status' => 'error', 'message' => 'Anda harus login untuk menghapus item dari wishlist.'];
        echo json_encode($response);
        exit();
    }

    $customer_id = $_SESSION['pengguna_id'];
    $wishlist_id = intval($_POST['wishlist_id'] ?? 0);

    if ($wishlist_id <= 0) {
        $response = ['status' => 'error', 'message' => 'ID wishlist tidak valid.'];
        echo json_encode($response);
        exit();
    }

    $query_delete = "DELETE FROM wishlist WHERE id = ? AND pelanggan_id = ?";
    $stmt_delete = $conn->prepare($query_delete);

    if ($stmt_delete) {
        $stmt_delete->bind_param("ii", $wishlist_id, $customer_id);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                // IMPORTANT: Recalculate total wishlist count *without* search filter here
                $new_wishlist_count = 0;
                $query_count_all = "SELECT COUNT(id) AS total_items FROM wishlist WHERE pelanggan_id = ?";
                $stmt_count_all = $conn->prepare($query_count_all);
                if ($stmt_count_all) {
                    $stmt_count_all->bind_param("i", $customer_id);
                    $stmt_count_all->execute();
                    $result_count_all = $stmt_count_all->get_result();
                    if ($row_count_all = $result_count_all->fetch_assoc()) {
                        $new_wishlist_count = $row_count_all['total_items'];
                    }
                    $stmt_count_all->close();
                }

                $response = [
                    'status' => 'success',
                    'message' => 'Produk berhasil dihapus dari wishlist.',
                    'wishlist_count' => $new_wishlist_count // Send this correct count
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Produk tidak ditemukan di wishlist Anda atau sudah dihapus.'];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Gagal menghapus produk dari database: ' . $stmt_delete->error];
        }
        $stmt_delete->close();
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal menyiapkan statement DELETE: ' . $conn->error];
    }

    $conn->close();
    echo json_encode($response);
    exit();
}
// --- Akhir Bagian Penanganan AJAX ---

// Cek login (tetap di sini untuk tampilan halaman utama)
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat wishlist Anda.";
    header('Location: ../../login.php'); // Sesuaikan path ke halaman login Anda
    exit();
}

$customer_id = $_SESSION['pengguna_id'];

// Inisialisasi variabel profil pelanggan
$nama_pelanggan = "Akun";
$foto_pelanggan = "";

// Ambil informasi pengguna untuk navbar
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

// Mengambil jumlah item di keranjang untuk badge navbar
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

// --- Logika Pencarian dan Pagination Baru ---
$search_query = $_GET['search'] ?? '';
$records_per_page = 5; // Jumlah item per halaman

// Dapatkan nomor halaman saat ini
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- PERBAIKAN: Hitung TOTAL item wishlist (untuk badge) TANPA filter pencarian ---
$total_item_wishlist = 0; // Initialize
$query_total_wishlist_unfiltered = "SELECT COUNT(id) AS total_count FROM wishlist WHERE pelanggan_id = ?";
$stmt_total_unfiltered = $conn->prepare($query_total_wishlist_unfiltered);
if ($stmt_total_unfiltered) {
    $stmt_total_unfiltered->bind_param("i", $customer_id);
    $stmt_total_unfiltered->execute();
    $result_total_unfiltered = $stmt_total_unfiltered->get_result();
    if ($row_total_unfiltered = $result_total_unfiltered->fetch_assoc()) {
        $total_item_wishlist = $row_total_unfiltered['total_count'];
    }
    $stmt_total_unfiltered->close();
} else {
    error_log("Failed to prepare unfiltered wishlist count query: " . $conn->error);
}
// --- AKHIR PERBAIKAN ---


// Query dasar untuk menghitung total item wishlist *YANG DITAMPILKAN PADA HALAMAN (dengan filter pencarian)*
$count_displayed_items_query = "
    SELECT COUNT(w.id) AS total_items_displayed
    FROM wishlist w
    JOIN produk p ON w.produk_id = p.id
    WHERE w.pelanggan_id = ?
";
$params_count_displayed = [$customer_id];
$types_count_displayed = "i";

if (!empty($search_query)) {
    $count_displayed_items_query .= " AND p.nama LIKE ?";
    $params_count_displayed[] = '%' . $search_query . '%';
    $types_count_displayed .= "s";
}

$stmt_total_displayed = $conn->prepare($count_displayed_items_query);
if ($stmt_total_displayed) {
    // Check if bind_param needs to be called
    if (!empty($types_count_displayed)) {
        $stmt_total_displayed->bind_param($types_count_displayed, ...$params_count_displayed);
    }
    $stmt_total_displayed->execute();
    $result_total_displayed = $stmt_total_displayed->get_result();
    $row_total_displayed = $result_total_displayed->fetch_assoc();
    $total_items_displayed_on_page = $row_total_displayed['total_items_displayed']; // Renamed for clarity
    $stmt_total_displayed->close();
} else {
    $total_items_displayed_on_page = 0;
    error_log("Failed to prepare displayed items count query: " . $conn->error);
}

$total_pages = ceil($total_items_displayed_on_page / $records_per_page);

// Query untuk mengambil produk-produk dari wishlist pengguna (dengan filter pencarian dan pagination)
$wishlist_items = [];
$base_query_wishlist = "
    SELECT
        w.id AS wishlist_id,
        p.id AS produk_id,
        p.nama AS nama_produk,
        p.gambar AS gambar_produk,
        p.harga AS harga_produk,
        p.deskripsi,
        p.stok,
        p.created_at,
        pen.nama_toko AS nama_toko_penjual,
        (SELECT COUNT(pv.id) FROM produk_variasi pv WHERE pv.produk_id = p.id) AS jumlah_variasi_produk
    FROM
        wishlist w
    JOIN
        produk p ON w.produk_id = p.id
    JOIN
        penjual pen ON p.penjual_id = pen.pengguna_id
    WHERE
        w.pelanggan_id = ?
";

$params_wishlist = [$customer_id];
$types_wishlist = "i";

if (!empty($search_query)) {
    $base_query_wishlist .= " AND p.nama LIKE ?";
    $params_wishlist[] = '%' . $search_query . '%';
    $types_wishlist .= "s";
}

$base_query_wishlist .= " ORDER BY w.tanggal_ditambahkan DESC LIMIT ?, ?";
$params_wishlist[] = $offset;
$params_wishlist[] = $records_per_page;
$types_wishlist .= "ii";

$stmt_wishlist = $conn->prepare($base_query_wishlist);

if ($stmt_wishlist) {
    // Correctly bind parameters for the main wishlist query
    $stmt_wishlist->bind_param($types_wishlist, ...$params_wishlist);
    $stmt_wishlist->execute();
    $result_wishlist = $stmt_wishlist->get_result();
    while ($row = $result_wishlist->fetch_assoc()) {
        $wishlist_items[] = $row;
    }
    $stmt_wishlist->close();
} else {
    error_log("Failed to prepare wishlist query: " . $conn->error);
}

$conn->close();

$page_title = "Wishlist Saya";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $page_title; ?> - Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    color: white !important; /* Set solid white color for nav-links */
    transition: color 0.3s ease;
}

.nav-link:hover,
.nav-link.active {
    color: #f0f0f0 !important; /* Slightly lighter on hover/active */
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
    background-color: #ffe0b2; /* Light orange on hover (lebih dominan dari dark orange) */
    color: white;
}

.navbar-nav .dropdown-divider {
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

/* Profile Dropdown Specific */
.navbar-nav .dropdown-toggle .ms-1 {
    color: white !important; /* Ensure profile name is white */
}

/* Wishlist Page Specific Styles */
.wishlist-item {
    display: flex;
    align-items: center;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 15px;
    background-color: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    padding: 15px;
    cursor: pointer;
    transition: transform 0.2s ease-in-out;
}

.wishlist-item:hover {
    transform: translateY(-3px);
}

.wishlist-item img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
    margin-right: 15px;
    border: 1px solid #eee;
    flex-shrink: 0;
}

.wishlist-item-details {
    flex-grow: 1;
    min-width: 0; /* PENTING: Izinkan menyusut lebih kecil dari kontennya jika perlu */
    margin-right: 15px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.wishlist-item-details h5 {
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.wishlist-item-details .price {
    font-weight: bold;
    color: #ff4500;
    font-size: 1.1em;
    margin-bottom: 5px;
}

.wishlist-item-details .store {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 5px;
}

/* KUNCI PERUBAHAN UNTUK TOMBOL ADA DI SINI */
.wishlist-item-actions {
    display: flex;
    flex-direction: row; /* Pastikan tombol sejajar secara HORIZONTAL */
    align-items: center; /* Sejajarkan tombol secara vertikal di tengah container */
    gap: 8px; /* Jarak antar tombol */
    flex-shrink: 0; /* Pastikan bagian aksi tidak menyusut */
    margin-left: auto; /* Dorong bagian aksi ke kanan paling ujung */
    min-width: fit-content; /* Biarkan lebarnya sesuai konten minimal */
}

.btn-view-product,
.btn-remove-wishlist {
    white-space: nowrap; /* Pastikan teks tombol tidak pecah ke baris baru */
    padding: 8px 12px; /* Sesuaikan padding agar tombol lebih ringkas */
    font-size: 0.85rem; /* Ukuran font yang sedikit lebih kecil untuk tombol */
    min-width: 90px; /* Beri lebar minimal agar tombol tidak terlalu sempit */
    text-align: center; /* Pastikan teks di tengah tombol */
    display: inline-flex; /* Gunakan inline-flex untuk sejajar ikon dan teks */
    align-items: center; /* Sejajarkan ikon dan teks vertikal */
    justify-content: center; /* Sejajarkan ikon dan teks horizontal */
}

/* Penyesuaian khusus untuk tombol hapus jika ada ikon sampah */
.btn-remove-wishlist .bi-trash {
    margin-right: 5px; /* Beri jarak antara ikon sampah dan teks "Hapus" */
}

/* Penyesuaian khusus untuk tombol lihat produk jika ada ikon mata */
.btn-view-product .bi-eye {
    margin-right: 5px; /* Beri jarak antara ikon mata dan teks "Lihat Produk" */
}


.btn-view-product {
    background-color: #FF4500;
    border-color: #FF4500;
    color: white;
}
.btn-view-product:hover {
    background-color: #E63C00;
    border-color: #E63C00;
    color: white;
}

.btn-remove-wishlist {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}
.btn-remove-wishlist:hover {
    background-color: #c82333;
    border-color: #c82333;
    color: white;
}

/* Empty Wishlist Styling */
.text-center.py-5 { /* Perhatian: Aturan ini hanya akan berlaku jika elemen memiliki kedua kelas ini */
    /* background-color: #fff; */ /* Pastikan baris ini dihilangkan/dikomentari */
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    padding: 40px !important;
}
/* Tambahkan ini untuk mengembalikan warna biru pada alert-info di dalam .text-center.py-5 */
.text-center.py-5.alert-info {
    background-color: var(--bs-alert-info-bg, #cfe2ff) !important; /* Warna default alert-info Bootstrap */
    color: var(--bs-alert-info-color, #055160) !important; /* Warna teks default */
    border-color: var(--bs-alert-info-border-color, #9ecafc) !important; /* Warna border default */
}

.text-center.py-5 img {
    /*filter: grayscale(80%);*/
    /*opacity: 0.6;*/
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

/* Responsive adjustments for Wishlist Items */
/* Media query untuk mengatasi perilaku pada zoom out (sekitar 900px ke bawah) */
@media (max-width: 991.98px) { /* Bootstrap 'lg' breakpoint - Ini mencakup banyak desktop kecil dan tablet lanskap */
    .wishlist-item {
        flex-wrap: wrap; /* Biarkan elemen item membungkus jika tidak muat */
        justify-content: space-between; /* Untuk distribusi ruang yang lebih baik */
        align-items: flex-start; /* Sejajarkan di bagian atas */
    }

    .wishlist-item-details {
        flex-basis: calc(100% - 130px); /* Beri lebar relatif, sisakan ruang untuk gambar */
        max-width: calc(100% - 130px);
        margin-right: 0; /* Hapus margin kanan */
    }

    .wishlist-item-actions {
        flex-direction: row; /* Tetap sebaris */
        flex-wrap: wrap; /* Biarkan tombol membungkus jika ruang tidak cukup */
        justify-content: flex-end; /* Rata kanan tombol */
        width: 100%; /* Ambil seluruh lebar yang tersedia di baris baru */
        margin-top: 10px; /* Beri sedikit jarak dari detail di atasnya jika mereka membungkus */
        margin-left: 0; /* Hapus margin auto, karena sudah diatur width 100% */
        flex-basis: 100%; /* Ambil seluruh lebar jika membungkus */
    }

    .btn-view-product,
    .btn-remove-wishlist {
        flex-grow: 1; /* Biarkan tombol tumbuh untuk mengisi ruang */
        flex-basis: calc(50% - 4px); /* Bagi ruang agar 2 tombol bisa sejajar dengan gap 8px */
        max-width: calc(50% - 4px); /* Pastikan ada sedikit celah */
        font-size: 0.8rem; /* Kurangi ukuran font lebih lanjut jika perlu */
        padding: 7px 10px; /* Kurangi padding */
    }
}

/* Media query untuk layar yang lebih kecil lagi (tablet potret ke bawah, atau zoom lebih ekstrim) */
@media (max-width: 767.98px) { /* Bootstrap 'md' breakpoint - Umumnya ponsel dan tablet potret */
    .wishlist-item {
        flex-direction: column; /* Tumpuk semua elemen pada layar kecil */
        align-items: center; /* Rata tengah semua elemen saat ditumpuk */
        text-align: center; /* Rata tengah teks */
        padding: 10px; /* Sesuaikan padding */
    }
    .wishlist-item img {
        margin-right: 0;
        margin-bottom: 15px; /* Beri jarak di bawah gambar */
    }
    .wishlist-item-details {
        width: 100%; /* Ambil lebar penuh */
        margin-bottom: 15px; /* Beri jarak di bawah detail */
        /* Reset nowrap dan ellipsis untuk tampilan vertikal agar teks bisa wrap */
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        flex-basis: auto; /* Reset flex-basis */
        max-width: none; /* Reset max-width */
    }
    .wishlist-item-details h5 {
        white-space: normal; /* Biarkan judul wrap */
        overflow: visible;
        text-overflow: clip;
    }
    .wishlist-item-actions {
        flex-direction: column; /* Tumpuk tombol pada layar ini */
        width: 100%; /* Ambil lebar penuh */
        justify-content: center; /* Rata tengah tombol saat ditumpuk */
        margin-left: 0; /* Hapus margin auto */
        flex-basis: auto; /* Reset flex-basis */
    }
    .btn-view-product,
    .btn-remove-wishlist {
        width: 100%; /* Tombol ambil lebar penuh */
        max-width: 100%; /* Pastikan tombol ambil lebar penuh */
        margin-left: 0;
        margin-right: 0;
        font-size: 1rem;
        padding: 10px 15px;
    }
}

/* Untuk layar yang sangat, sangat sempit, pastikan tombol menumpuk vertikal */
@media (max-width: 480px) {
    .wishlist-item-actions {
        flex-direction: column; /* Paksa tombol menumpuk vertikal */
    }
    .btn-view-product,
    .btn-remove-wishlist {
        width: 100%; /* Ambil lebar penuh saat menumpuk */
        max-width: 100%;
    }
}

/* Navbar badge styles */
.navbar-nav .nav-link .badge {
    background-color: white !important; /* Set background to white */
    color: #ff4500 !important; /* Set text color to #ff4500 (OrangeRed) */
    border: 1px solid #ff4500; /* Add a subtle border matching the text color */
}
/* Making the heart icon in the navbar solid white */
.navbar-nav .nav-item .nav-link .bi-heart-fill,
.navbar-nav .nav-item .nav-link .bi-heart {
    color: white !important; /* Ensure always white */
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
    color: #6c757d;
}

/* --- PENAMBAHAN/PENYESUAIAN UNTUK RESPONSIVITAS NAVBAR --- */

@media (max-width: 991.98px) { /* Ini adalah breakpoint Bootstrap untuk `lg` */
    .navbar-collapse {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }

    .navbar-nav {
        width: 100%;
        margin-top: 10px;
    }

    .navbar-nav .nav-item {
        width: 100%;
        text-align: left;
    }

    .navbar-nav .nav-link { /* Terapkan ini pada semua nav-link di mode collapsed */
        padding: 8px 15px;
        flex-direction: row; /* KUNCI: Kembalikan layout ke baris (horizontal) */
        justify-content: flex-start; /* Sejajarkan ke kiri */
        align-items: center;
    }

    /* Ini menargetkan khusus link wishlist dan keranjang untuk memastikan tata letak yang tepat */
    .navbar-nav .nav-item:nth-last-child(2) .nav-link, /* Untuk Wishlist */
    .navbar-nav .nav-item:nth-last-child(3) .nav-link { /* Untuk Keranjang */
         /* Pastikan mereka berdampingan */
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        padding: 8px 15px;
    }


    .navbar .d-flex.me-2 {
        width: 100%;
        margin-right: 0 !important;
        margin-bottom: 10px;
        margin-top: 10px;
    }

    .navbar .input-group {
        width: 100%;
    }

    .navbar .form-control-sm {
        flex-grow: 1;
        width: auto;
        min-width: 100px;
    }

    .navbar-nav .nav-link .badge {
        position: static; 
        transform: none; 
        margin-left: 5px; 
        top: auto; 
        right: auto; 
        vertical-align: middle; 
        padding: 0.4em 0.7em; /* Kembalikan padding badge default untuk tampilan statis */
        border-radius: 0.25rem; /* Kembalikan border-radius default untuk tampilan statis */
        
        /* Reset ukuran untuk tampilan statis di mobile, agar tidak terlalu bulat */
        min-width: auto; 
        height: auto; 
    }

    .navbar-nav .nav-link i {
        margin-top: 0; /* Hapus margin top yang ditambahkan untuk badge di atas ikon */
        font-size: 1rem; /* Kembalikan ukuran ikon normal untuk tampilan mobile */
    }

    .navbar-nav .dropdown {
        width: 100%;
    }

    .navbar-nav .dropdown-toggle {
        width: 100%;
        text-align: left;
        padding: 8px 15px;
    }

    .navbar-nav .dropdown-menu {
        width: 100%;
        left: 0 !important;
        right: auto !important;
    }

    .navbar-nav .dropdown-toggle img {
        width: 24px;
        height: 24px;
        margin-right: 5px;
    }
}

/* Optional: Jika Anda ingin navbar-toggler tetap di sisi kanan saat collapse terbuka */
@media (max-width: 991.98px) {
    .navbar-toggler {
        position: absolute;
        right: 15px;
        top: 10px;
        z-index: 1001;
    }
    .navbar-brand {
        margin-right: auto;
    }
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
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
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
                    <a class="nav-link" href="../pelanggan/produk.php">Produk</a>
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
            <form class="d-flex me-2 mb-2" role="search" action="wishlist.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari produk di wishlist..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="../pelanggan/wishlist.php">
                        <i class="bi bi-heart-fill"></i>
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                            <?php echo $total_item_wishlist; ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="position-relative">
                        <a class="nav-link" href="../pelanggan/keranjang/keranjang.php" id="link-keranjang">
                            <i class="bi bi-cart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                <?php echo $total_produk_di_keranjang; ?>
                            </span>
                        </a>
                        <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2">
                                <h5>Baru Ditambahkan</h5>
                                <ul class="list-unstyled" id="daftar-produk-keranjang">
                                    <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja kosong.</li>
                                </ul>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                                    <a href="../pelanggan/keranjang/keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                                </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                                <img src="../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan ?? 'Tamu'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../pelanggan/profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item" href="../pelanggan/keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container my-4">
        <h2 class="mb-4 text-center">Wishlist Saya</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="alert-placeholder">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="alert-placeholder">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($wishlist_items) && !empty($search_query)): ?>
            <div class="alert alert-info text-center py-4" role="alert">
                Tidak ada produk di wishlist yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>".
                <br>
                <a href="wishlist.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Semua Wishlist</a>
            </div>
        <?php elseif (empty($wishlist_items) && empty($search_query)): ?>
    <div class="alert alert-info text-center" role="alert" id="empty-wishlist-message">
        <i class="bi bi-clipboard-heart mb-3" style="font-size: 80px; color: #FF4500;"></i>
        <p class="lead">Wishlist Anda kosong.</p>
        <p>Belum ada produk yang Anda tandai sebagai favorit.</p>
        <a href="../pelanggan/produk.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Produk</a>
    </div>
        <?php else: ?>
            <div class="row" id="wishlist-items-container">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="col-12 mb-3" id="wishlist-item-<?php echo $item['wishlist_id']; ?>">
                        <div class="wishlist-item" data-product-id="<?php echo $item['produk_id']; ?>">
                            <a href="../pelanggan/detail_produk.php?id=<?php echo $item['produk_id']; ?>" class="d-flex align-items-center text-decoration-none text-dark flex-grow-1">
                                <img src="../img/barang/<?php echo htmlspecialchars($item['gambar_produk']); ?>"
                                    onerror="this.onerror=null; this.src='../img/placeholder-no-image.png';"
                                    alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                                <div class="wishlist-item-details">
                                    <h5><?php echo htmlspecialchars($item['nama_produk']); ?></h5>
                                    <div class="price">Rp <?php echo number_format($item['harga_produk'], 0, ',', '.'); ?></div>
                                    <div class="store"><i class="bi bi-shop me-1"></i><?php echo htmlspecialchars($item['nama_toko_penjual']); ?></div>
                                    <small class="text-muted">Stok: <?php echo $item['stok']; ?></small>
                                </div>
                            </a>
                            <div class="wishlist-item-actions">
                                <a href="../pelanggan/detail_produk.php?id=<?php echo $item['produk_id']; ?>" class="btn btn-sm btn-view-product">
                                    <i class="bi bi-eye me-1"></i> Lihat Produk
                                </a>
                                <button class="btn btn-sm btn-remove-wishlist" data-wishlist-id="<?php echo $item['wishlist_id']; ?>">
                                    <i class="bi bi-trash me-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Wishlist Pagination">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
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
                    <img src="../img/logo.png" alt="Logo Desa" width="80" class="mb-2">
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
        $(document).ready(function() {
            // Fungsi untuk menampilkan pesan alert
            function showMessage(type, message) {
                const alertContainer = $('#alert-placeholder');
                alertContainer.empty();
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                alertContainer.append(alertHtml);
            }

            // Fungsi untuk memperbarui jumlah item di keranjang (badge navbar)
            function updateCartItemCount() {
                $.ajax({
                    url: '../keranjang/get_cart_count.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#jumlah-keranjang').text(response.count);
                        } else {
                            console.error('Gagal mengambil jumlah keranjang: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error (get_cart_count):', status, error, xhr.responseText);
                    }
                });
            }

            // Panggil fungsi update saat halaman dimuat
            updateCartItemCount();

            // --- Event Listener untuk tombol "Hapus dari Wishlist" ---
            $(document).on('click', '.btn-remove-wishlist', function(e) {
                e.stopPropagation();
                const button = $(this);
                const wishlistId = button.data('wishlist-id');
                const itemElement = button.closest('.col-12');

                if (confirm('Anda yakin ingin menghapus produk ini dari wishlist?')) {
                    $.ajax({
                        url: 'wishlist.php', // Mengirim request ke file yang sama
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'remove_wishlist_item', // Tambahkan action untuk membedakan
                            wishlist_id: wishlistId
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                showMessage('success', response.message);
                                itemElement.fadeOut(300, function() {
                                    $(this).remove(); // Hapus item dari DOM

                                    // Perbarui badge wishlist di navbar dengan jumlah terbaru dari respons PHP
                                    $('#wishlist-count').text(response.wishlist_count);

                                    // Refresh halaman untuk memperbarui daftar item dan pagination
                                    // karena item_per_page mungkin berubah setelah penghapusan
                                    window.location.reload();
                                });
                            } else {
                                showMessage('danger', response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error (remove_wishlist_item):', status, error, xhr.responseText);
                            showMessage('danger', 'Terjadi kesalahan saat menghapus produk dari wishlist.');
                        }
                    });
                }
            });

            // --- Event Listener untuk mengarahkan ke detail_produk.php saat item diklik ---
            $(document).on('click', '.wishlist-item', function(e) {
                if (!$(e.target).is('button') && !$(e.target).closest('button').length &&
                    !$(e.target).is('a') && !$(e.target).closest('a').length) {
                    const productId = $(this).data('product-id');
                    if (productId) {
                        window.location.href = `../pelanggan/detail_produk.php?id=${productId}`;
                    }
                }
            });

            // Prevent direct navigation when clicking on links within .wishlist-item
            $(document).on('click', '.wishlist-item a', function(e) {
                e.stopPropagation();
            });
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
    if (jumlahProdukLainnyaSpan) {
        jumlahProdukLainnyaSpan.style.display = 'none';
        jumlahProdukLainnyaSpan.textContent = '';
    }

    // Lakukan permintaan Fetch ke endpoint yang mengambil data keranjang
    // PERUBAHAN PERTAMA DI SINI: SESUAIKAN PATH
    fetch('keranjang/ambil_keranjang_sementara.php')
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

// Event listener untuk tampilan/menyembunyikan dropdown keranjang (Sama persis dengan promo.php)
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