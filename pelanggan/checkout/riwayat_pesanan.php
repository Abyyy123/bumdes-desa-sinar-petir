<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda
$fetch_error = false;

// Fungsi untuk membangun URL paginasi dengan mempertahankan parameter yang ada
function buildPaginationUrl($pageNum, $current_params) {
    $params = $current_params;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat pesanan Anda.";
    header('Location: ../../login.php'); // Redirect ke halaman login
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$pesanan_customer = [];

// --- Bagian untuk Navbar (Sama dengan promo.php/artikel.php/profil.php) ---
$user_id = $_SESSION['pengguna_id']; // Menggunakan kembali $pengguna_id
$nama_pelanggan_navbar = 'Akun'; // Default
$foto_pelanggan_navbar = ''; // Default

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

// --- Pagination Configuration ---
$limit = 5; // Jumlah pesanan per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Search Logic ---
$search_query = isset($_GET['search']) ? $_GET['search'] : ''; // Ambil nilai dari input search
$search_param = '%' . $search_query . '%'; // Tambahkan wildcard untuk LIKE

// Ambil semua parameter GET saat ini untuk dipertahankan di paginasi
// Penting: Hapus 'page' dari $current_query_params agar tidak terduplikasi saat buildPaginationUrl menambahkan ulang
$current_query_params = $_GET;
unset($current_query_params['page']);


try {
    // 1. Hitung total jumlah pesanan (dengan mempertimbangkan search query)
    $query_count = "SELECT COUNT(DISTINCT p.id) AS total FROM pesanan p
                    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                    WHERE p.pelanggan_id = ?";
    
    // Tambahkan kondisi pencarian jika ada search_query
    if (!empty($search_query)) {
        $query_count .= " AND (p.kode_unik LIKE ? OR dp.nama_produk_saat_beli LIKE ? OR p.status_pesanan LIKE ?)";
    }

    $stmt_count = $conn->prepare($query_count);
    if ($stmt_count === false) {
        throw new Exception("Error preparing count query: " . $conn->error);
    }
    
    // Bind parameter untuk query count
    if (!empty($search_query)) {
        $stmt_count->bind_param('isss', $pengguna_id, $search_param, $search_param, $search_param);
    } else {
        $stmt_count->bind_param('i', $pengguna_id);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    
    $total_orders = $result_count->fetch_assoc()['total'];
    
    $stmt_count->close();

    $total_pages = ceil($total_orders / $limit);

    // 2. Query untuk mengambil pesanan (dengan mempertimbangkan search query dan pagination)
    $query_orders = "
        SELECT
            p.id AS pesanan_id,
            p.kode_unik AS kode_pesanan,
            p.tanggal_pesanan,
            p.total_harga AS total_harga_produk,
            p.total_harga AS total_pembayaran,
            p.status_pesanan,
            p.metode_pembelian AS nama_metode_pembayaran,
            pl.nama AS nama_penerima,
            pl.nomor_telepon AS telepon_penerima,
            p.alamat_pengiriman,
            p.catatan_pelanggan AS catatan_pelanggan_umum,
            p.metode_pengiriman
        FROM
            pesanan p
        JOIN
            pelanggan pl ON p.pelanggan_id = pl.pengguna_id
        LEFT JOIN
            detail_pesanan dp ON p.id = dp.pesanan_id -- Gunakan LEFT JOIN agar pesanan tanpa detail juga bisa muncul atau saat filtering
        WHERE
            p.pelanggan_id = ?
    ";
    
    // Tambahkan kondisi pencarian jika ada search_query
    if (!empty($search_query)) {
        $query_orders .= " AND (p.kode_unik LIKE ? OR dp.nama_produk_saat_beli LIKE ? OR p.status_pesanan LIKE ?)";
    }
    
    // Penting: tambahkan GROUP BY agar tidak ada duplikasi pesanan jika ada banyak item yang cocok
    $query_orders .= " GROUP BY p.id"; 

    $query_orders .= "
        ORDER BY
            p.id DESC -- Mengurutkan berdasarkan pesanan_id dari terbesar (terbaru) ke terkecil (terlama)
        LIMIT ? OFFSET ?
    ";

    $stmt_orders = $conn->prepare($query_orders);
    if ($stmt_orders === false) {
        throw new Exception("Error preparing orders query: " . $conn->error);
    }
    
    // Bind parameter untuk query utama
    if (!empty($search_query)) {
        $stmt_orders->bind_param('isssii', $pengguna_id, $search_param, $search_param, $search_param, $limit, $offset);
    } else {
        $stmt_orders->bind_param('iii', $pengguna_id, $limit, $offset);
    }
    
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();

    $orders = []; // Inisialisasi array orders
    if ($result_orders->num_rows > 0) {
        while ($row = $result_orders->fetch_assoc()) {
            $order_id = $row['pesanan_id'];
            $orders[$order_id] = [
                'kode_pesanan' => htmlspecialchars($row['kode_pesanan']),
                'tanggal_pesanan' => date('d F Y H:i', strtotime($row['tanggal_pesanan'])),
                'total_harga_produk' => $row['total_harga_produk'],
                'total_ongkos_kirim' => 0, // Set to 0 since it's being removed
                'total_pembayaran' => $row['total_pembayaran'], // Total pembayaran sekarang hanya total harga produk
                'status_pesanan' => htmlspecialchars($row['status_pesanan']),
                'nama_metode_pembayaran' => htmlspecialchars($row['nama_metode_pembayaran']),
                'nama_penerima' => htmlspecialchars($row['nama_penerima']),
                'alamat_pengiriman' => htmlspecialchars($row['alamat_pengiriman']),
                'telepon_penerima' => htmlspecialchars($row['telepon_penerima']),
                'catatan_pelanggan_umum' => htmlspecialchars($row['catatan_pelanggan_umum']),
                'metode_pengiriman' => htmlspecialchars($row['metode_pengiriman']),
                'shipments' => []
            ];
        }
    } else {
        $orders = []; // Ensure $orders is an empty array if no results
    }
    $stmt_orders->close();

    // Now fetch shipment details for each order
    foreach ($orders as $order_id => &$order_data) {
        $query_shipments = "
            SELECT
                pp.id AS pengiriman_id,
                pp.kurir_id,
                k.nama AS nama_kurir,
                pp.nomor_resi,
                pp.status_pengiriman,
                pp.tanggal_pengiriman,
                pp.keterangan AS catatan_penjual
            FROM
                pengiriman pp
            JOIN
                kurir k ON pp.kurir_id = k.id
            WHERE
                pp.pesanan_id = ?
            ORDER BY
                pp.id ASC
        ";
        $stmt_shipments = $conn->prepare($query_shipments);
        if ($stmt_shipments === false) {
            throw new Exception("Error preparing shipments query: " . $conn->error);
        }
        $stmt_shipments->bind_param('i', $order_id);
        $stmt_shipments->execute();
        $result_shipments = $stmt_shipments->get_result();

        while ($shipment_row = $result_shipments->fetch_assoc()) {
            $pengiriman_id = $shipment_row['pengiriman_id'];
            $order_data['shipments'][$pengiriman_id] = [
                'kurir_id' => $shipment_row['kurir_id'],
                'nama_kurir' => htmlspecialchars($shipment_row['nama_kurir']),
                'ongkir_per_penjual' => 0, // Set this to 0 as ongkir is removed
                'nomor_resi' => htmlspecialchars($shipment_row['nomor_resi']),
                'status_pengiriman' => htmlspecialchars($shipment_row['status_pengiriman']),
                'tanggal_pengiriman' => $shipment_row['tanggal_pengiriman'] ? date('d F Y', strtotime($shipment_row['tanggal_pengiriman'])) : 'Belum Dikirim',
                'catatan_penjual' => htmlspecialchars($shipment_row['keterangan'] ?? ''),
                'items' => []
            ];
        }
        $stmt_shipments->close();

        // Now fetch all product details for this specific order_id
        $query_detail_products_for_order = "
            SELECT
                dp.produk_id,
                dp.variasi_id,
                dp.quantity,
                dp.harga_satuan,
                (dp.quantity * dp.harga_satuan) AS subtotal_harga,
                prod.gambar AS gambar_produk,
                dp.nama_produk_saat_beli AS nama_produk,
                dp.variasi_detail_saat_beli AS variasi_detail,
                prod.penjual_id
            FROM
                detail_pesanan dp
            JOIN
                produk prod ON dp.produk_id = prod.id
            WHERE
                dp.pesanan_id = ?
        ";
        $stmt_detail_products = $conn->prepare($query_detail_products_for_order);
        if ($stmt_detail_products === false) {
            throw new Exception("Error preparing product details query: " . $conn->error);
        }
        $stmt_detail_products->bind_param('i', $order_id);
        $stmt_detail_products->execute();
        $result_detail_products = $stmt_detail_products->get_result();

        while ($product_row = $result_detail_products->fetch_assoc()) {
            $added_to_shipment = false;
            foreach ($order_data['shipments'] as $pengiriman_id => &$shipment_data) {
                // Asumsi: Setiap produk di detail_pesanan diasosiasikan dengan satu pengiriman jika ada
                // Logika ini mungkin perlu disesuaikan jika satu pesanan bisa punya produk yang dikirim oleh pengiriman berbeda
                // Untuk saat ini, kita hanya menambahkan semua produk ke pengiriman pertama yang ditemukan,
                // atau ke 'items_without_shipment' jika tidak ada pengiriman.
                $shipment_data['items'][] = [
                    'produk_id' => $product_row['produk_id'],
                    'variasi_id' => $product_row['variasi_id'],
                    'nama_produk' => htmlspecialchars($product_row['nama_produk']),
                    'gambar_produk' => htmlspecialchars($product_row['gambar_produk']),
                    'quantity' => $product_row['quantity'],
                    'harga_satuan' => $product_row['harga_satuan'],
                    'subtotal_harga' => $product_row['subtotal_harga'],
                    'variasi_detail' => htmlspecialchars($product_row['variasi_detail'])
                ];
                $added_to_shipment = true;
                break; // Hanya tambahkan ke pengiriman pertama yang ditemukan
            }
            if (!$added_to_shipment) {
                if (!isset($order_data['items_without_shipment'])) {
                    $order_data['items_without_shipment'] = [];
                }
                $order_data['items_without_shipment'][] = [
                    'produk_id' => $product_row['produk_id'],
                    'variasi_id' => $product_row['variasi_id'],
                    'nama_produk' => htmlspecialchars($product_row['nama_produk']),
                    'gambar_produk' => htmlspecialchars($product_row['gambar_produk']),
                    'quantity' => $product_row['quantity'],
                    'harga_satuan' => $product_row['harga_satuan'],
                    'subtotal_harga' => $product_row['subtotal_harga'],
                    'variasi_detail' => htmlspecialchars($product_row['variasi_detail'])
                ];
            }
        }
        $stmt_detail_products->close();
    }
    unset($order_data); // Unset reference

} catch (Exception $e) {
    $fetch_error = "Gagal memuat riwayat pesanan: " . $e->getMessage();
    error_log("Riwayat Pesanan Fetch Error: " . $e->getMessage());
} finally {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getStatusBadge($status) {
    $badge_class = '';
    $status_text = '';
    $icon = '';
    switch ($status) {
        case 'menunggu_pembayaran':
            $badge_class = 'bg-warning text-dark';
            $status_text = 'Menunggu Pembayaran';
            $icon = '<i class="bi bi-hourglass-split me-1"></i>';
            break;
        case 'diproses':
            $badge_class = 'bg-info text-dark';
            $status_text = 'Diproses Penjual';
            $icon = '<i class="bi bi-gear-fill me-1"></i>';
            break;
        case 'menunggu_konfirmasi_penjual':
            $badge_class = 'bg-secondary';
            $status_text = 'Menunggu Konfirmasi Penjual';
            $icon = '<i class="bi bi-person-check-fill me-1"></i>';
            break;
        case 'dikirim':
            $badge_class = 'bg-primary';
            $status_text = 'Dikirim';
            $icon = '<i class="bi bi-truck me-1"></i>';
            break;
        case 'diterima':
            $badge_class = 'bg-success';
            $status_text = 'Diterima';
            $icon = '<i class="bi bi-box-seam-fill me-1"></i>';
            break;
        case 'dibatalkan':
            $badge_class = 'bg-danger';
            $status_text = 'Dibatalkan';
            $icon = '<i class="bi bi-x-circle-fill me-1"></i>';
            break;
        case 'selesai':
            $badge_class = 'bg-success';
            $status_text = 'Selesai';
            $icon = '<i class="bi bi-check-circle-fill me-1"></i>';
            break;
        default:
            $badge_class = 'bg-light text-dark';
            $status_text = ucfirst(str_replace('_', ' ', $status));
            $icon = '<i class="bi bi-question-circle-fill me-1"></i>';
            break;
    }
    return "<span class=\"badge {$badge_class}\">{$icon}{$status_text}</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Toko Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* General Body Styling */
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        font-family: 'Arial', sans-serif;
        color: #333;
        /* Background-color diambil dari input terbaru */
        background-color: #f0f2f5; /* Updated background color */
        /* align-items: center; Hapus atau komen baris ini jika ada konflik pemusatan */
    }

    /* Navbar Styling */
    .navbar {
        background-color: #FF4500 !important; /* Primary color */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        width: 100%;
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
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30' fill='%23fff'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
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
        background-color: #ffe0b2; /* Perubahan warna hover agar lebih kontras */
        color: #FF4500; /* Warna teks yang lebih gelap saat hover */
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

    /* Container Styling (Updated from second input) */
    .container.my-5.flex-grow-1 {
        max-width: 850px; /* Slightly wider content area */
        margin-top: 4rem !important; /* Adjust top margin for larger navbar */
        margin-bottom: 4rem !important; /* Adjust bottom margin for larger footer */
        padding-left: 1.5rem; /* Increased padding */
        padding-right: 1.5rem; /* Increased padding */
    }

    /* Order Page Specific Styles */
    .order-card {
        background-color: #ffffff; /* Menggunakan background putih */
        border-radius: 12px; /* Radius lebih besar */
        margin-bottom: 25px; /* Margin bawah lebih besar */
        box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* Shadow yang lebih menonjol */
        overflow: hidden; /* Penting untuk border-radius */
        /* Properti dari input pertama yang tumpang tindih dihapus/diganti */
        /* border: 1px solid #e0e0e0; */
        /* margin-bottom: 20px; */
        /* box-shadow: 0 2px 4px rgba(0,0,0,0.05); */
    }

    .order-header {
        padding: 20px; /* Padding lebih besar */
        background-color: #e9ecef; /* Warna header lebih terang */
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        /* Properti dari input pertama yang tumpang tindih dihapus/diganti */
        /* background-color: #f8f9fa; */
        /* padding: 15px 20px; */
        /* border-top-left-radius: 8px; */
        /* border-top-right-radius: 8px; */
    }

    .order-body {
        padding: 20px; /* Padding sama */
    }

    /* New: Specific order summary item styling */
    .order-summary-item {
        margin-bottom: 10px;
    }
    .order-total {
        font-size: 1.5rem;
        font-weight: bold;
        color: #FF4500; /* Menggunakan warna #FF4500 untuk total */
    }

    /* Renamed from .product-item to .product-list-item for clarity based on input */
    .product-list-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px; /* Margin lebih besar */
        padding-bottom: 15px; /* Padding bawah lebih besar */
        border-bottom: 1px dashed #e9ecef; /* Border dashed */
        gap: 15px; /* Tetap pertahankan gap */
        /* Properti dari input pertama yang tumpang tindih dihapus/diganti */
        /* padding: 10px 0; */
        /* border-bottom: 1px dashed #eee; */
    }
    .product-list-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0; /* Pastikan padding bawah juga nol */
    }
    .product-img-thumb { /* Renamed from .product-item img */
        width: 70px; /* Ukuran lebih besar */
        height: 70px; /* Ukuran lebih besar */
        object-fit: cover;
        border-radius: 8px; /* Radius lebih besar */
        margin-right: 15px; /* Margin kanan lebih besar */
        border: 1px solid #e0e0e0;
        /* Properti dari input pertama yang tumpang tindih dihapus/diganti */
        /* width: 60px; */
        /* height: 60px; */
        /* border-radius: 5px; */
        /* margin-right: 15px; (sudah ada) */
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
    /* Status colors */
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

    /* New: Shipment Detail Card */
    .shipment-detail-card {
        background-color: #f8f9fa;
        border: 1px solid #e2e6ea;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }

    /* New: Accordion Styling */
    .accordion-button:not(.collapsed) {
        color: #fff;
        background-color: #FF4500; /* Warna accordion header saat aktif */
        box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
    }
    .accordion-button:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }
    .accordion-button {
        border-radius: 8px !important;
    }
    .accordion-item {
        border: none;
        background-color: transparent;
    }
    .accordion-collapse {
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 8px 8px;
    }
    .accordion-body {
        padding: 20px;
    }
    .status-header {
        font-size: 1.1rem;
        font-weight: 600;
    }
    .btn-track {
        background-color: #17a2b8;
        color: white;
    }
    .btn-track:hover {
        background-color: #138496;
        color: white;
    }

    /* Footer Styling */
    .footer {
        background-color: #FF4500; /* Consistent with navbar */
        color: white;
        padding: 2rem 0;
        margin-top: auto;
        width: 100%; /* Tetap pertahankan ini */
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

    /* Pagination Styling */
    .pagination .page-link {
        color: #FF4500; /* Warna teks link */
    }
    .pagination .page-item .page-link {
        border-color: #dee2e6; /* Border default Bootstrap */
    }

    .pagination .page-item .page-link:hover {
        color: #e63e00; /* Sedikit lebih gelap dari #FF4500 saat hover */
        background-color: #ffe8e0; /* Background terang saat hover */
        border-color: #e63e00; /* Border saat hover */
    }

    .pagination .page-item.active .page-link {
        background-color: #FF4500; /* Warna latar belakang untuk halaman aktif */
        border-color: #FF4500; /* Warna border untuk halaman aktif */
        color: white; /* Warna teks untuk halaman aktif */
    }

    .pagination .page-item.disabled .page-link {
        color: #6c757d; /* Warna teks abu-abu */
        pointer-events: none; /* Tidak bisa diklik */
        background-color: #fff; /* Latar belakang putih */
        border-color: #dee2e6; /* Border default Bootstrap */
    }

    .pagination .page-item .page-link:focus {
        box-shadow: 0 0 0 0.25rem rgba(255, 69, 0, 0.25); /* Shadow dengan warna #FF4500 */
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
                    <a class="nav-link" href="../artikel/artikel.php">Artikel</a>
                </li>
            </ul>
            <form class="d-flex me-2 mb-2" role="search" action="riwayat_pesanan.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" placeholder="Cari pesanan..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
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
                        <?php if (isset($foto_pelanggan_navbar) && $foto_pelanggan_navbar && $foto_pelanggan_navbar != 'default.png'): ?>
                                <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan_navbar); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan_navbar); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item" href="../keranjang/pesanan_saya.php">Pesanan Saya</a></li> <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
        <main class="container my-4">
            <h2 class="mb-5 text-center" style="color: black;">Riwayat Pesanan Anda</h2>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($fetch_error): ?>
                <div class="alert alert-danger text-center py-4" role="alert">
                    <p class="mb-3 fs-5">Terjadi kesalahan: <?php echo htmlspecialchars($fetch_error); ?></p>
                    <p>Silakan coba lagi nanti atau hubungi dukungan.</p>
                </div>
            <?php elseif (empty($orders)): // Jika $orders kosong (baik karena tidak ada pesanan atau tidak ada hasil pencarian) ?>
                <?php if (!empty($search_query)): // Jika ada istilah pencarian tapi tidak ada hasil ?>
                    <div class="alert alert-info text-center py-4" role="alert">
                        <i class="bi bi-info-circle-fill mb-3" style="font-size: 80px; color: #FF4500;"></i>
                        <p class="lead">Tidak ada pesanan yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>".</p>
                        <p>Coba cari dengan kata kunci lain atau lihat semua pesanan Anda.</p>
                        <a href="riwayat_pesanan.php" class="btn mt-2 text-white" style="background-color: #ff4500; border-color: #ff4500;">Lihat Semua Pesanan</a>
                    </div>
                <?php else: // Jika tidak ada istilah pencarian dan pesanan kosong (default state) ?>
                    <div class="alert alert-info text-center py-4" role="alert">
                        <i class="bi bi-box-seam-fill mb-3" style="font-size: 80px; color: #FF4500;"></i>
                        <p class="lead">Anda belum memiliki riwayat pesanan.</p>
                        <p>Mulai belanja sekarang dan buat pesanan pertama Anda!</p>
                        <a href="../produk.php" class="btn mt-2" style="background-color: #FF4500; color: white;">Lihat Produk</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($orders as $order_id => $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <h5 class="mb-1"><strong>Kode Pesanan:</strong> <span class="text-primary"><?php echo $order['kode_pesanan']; ?></span></h5>
                                <small class="text-muted">Tanggal Pesanan: <?php echo $order['tanggal_pesanan']; ?></small>
                            </div>
                            <div class="status-header">
                                <?php echo getStatusBadge($order['status_pesanan']); ?>
                            </div>
                        </div>
                        <div class="order-body">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-6 order-summary-item">
                                    <p class="mb-1"><strong>Total Harga Produk:</strong> <span class="fw-semibold"><?php echo formatRupiah($order['total_harga_produk']); ?></span></p>
                                    <p class="mb-0"><strong>Metode Pembayaran:</strong> <span class="fw-semibold"><?php echo $order['nama_metode_pembayaran']; ?></span></p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <p class="mb-0 order-total">Total Pembayaran: <?php echo formatRupiah($order['total_pembayaran']); ?></p>
                                    <?php
                                    // Logika untuk menampilkan pesan pembayaran (selama status BUKAN 'selesai')
                                    if ($order['status_pesanan'] !== 'selesai') {
                                        // Logika untuk Tunai dan COD (tipe 'lainnya' atau ditentukan langsung dari nama_metode_pembayaran)
                                        // Pesan ini akan muncul di semua status kecuali 'selesai'
                                        if ($order['nama_metode_pembayaran'] === 'Tunai') {
                                            echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-check-circle-fill me-1"></i> Pembayaran tunai akan dilakukan di lokasi pengambilan pesanan.</p>';
                                        } elseif ($order['nama_metode_pembayaran'] === 'COD') {
                                            echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-box-seam me-1"></i> Pembayaran dilakukan saat pesanan tiba.</p>';
                                        }
                                        // Logika untuk metode pembayaran yang memerlukan tombol 'Bayar Sekarang'
                                        // Hanya jika statusnya 'menunggu_pembayaran' dan bukan Tunai/COD
                                        elseif ($order['status_pesanan'] === 'menunggu_pembayaran') {
                                            // Asumsi $order['tipe_pembayaran'] tersedia
                                            if (isset($order['tipe_pembayaran'])) {
                                                if ($order['tipe_pembayaran'] === 'ban_transfer') {
                                                    echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-bank me-1"></i> Mohon selesaikan pembayaran melalui transfer bank.</p>';
                                                } elseif ($order['tipe_pembayaran'] === 'e-wallet') {
                                                    echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-credit-card me-1"></i> Mohon selesaikan pembayaran melalui e-wallet.</p>';
                                                }
                                                // Fallback untuk tipe lain yang menunggu konfirmasi (selain Tunai/COD)
                                                else {
                                                    echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-exclamation-circle-fill me-1"></i> Mohon selesaikan pembayaran Anda.</p>';
                                                }
                                            } else {
                                                // Fallback jika tipe_pembayaran tidak tersedia
                                                echo '<p class="mt-3" style="color: #FF4500;"><i class="bi bi-exclamation-circle-fill me-1"></i> Mohon selesaikan pembayaran Anda.</p>';
                                            }
                                            // Tombol 'Bayar Sekarang' akan selalu muncul jika statusnya 'menunggu_pembayaran'
                                            // dan metode pembayaran bukan Tunai/COD (karena Tunai/COD tidak perlu tombol ini)
                                            echo '<a href="../keranjang/Konfirmasi_pembayaran.php?order_id=' . $order_id . '" class="btn btn-success mt-3">';
                                            echo '    <i class="bi bi-wallet-fill me-2"></i>Bayar Sekarang';
                                            echo '</a>';
                                        }
                                    }
                                    // Jika status pesanan sudah 'selesai', tidak ada output dari blok di atas.
                                    // Anda bisa menambahkan pesan "Pesanan sudah selesai" di sini jika diinginkan
                                    // else {
                                    //     echo '<p class="mt-3 text-muted">Pesanan Anda telah selesai.</p>';
                                    // }
                                    ?>
                                </div>
                            </div>
                            <div class="accordion" id="accordionOrder_<?php echo $order_id; ?>">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading_<?php echo $order_id; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?php echo $order_id; ?>" aria-expanded="false" aria-controls="collapse_<?php echo $order_id; ?>">
                                            <i class="bi bi-info-circle me-2"></i> Lihat Detail Pesanan Lengkap
                                        </button>
                                    </h2>
                                    <div id="collapse_<?php echo $order_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?php echo $order_id; ?>" data-bs-parent="#accordionOrder_<?php echo $order_id; ?>">
                                        <div class="accordion-body">
                                            <h6 class="mb-3 text-primary">Informasi Pengiriman Utama:</h6>
                                            <ul class="list-unstyled mb-4">
                                                <li><strong>Penerima:</strong> <?php echo $order['nama_penerima']; ?></li>
                                                <li><strong>Alamat:</strong> <?php echo $order['alamat_pengiriman']; ?></li>
                                                <li><strong>Telepon:</strong> <?php echo $order['telepon_penerima']; ?></li>
                                                <li><strong>Metode Pengiriman:</strong> <?php echo $order['metode_pengiriman']; ?></li>
                                                <?php if (!empty($order['catatan_pelanggan_umum'])): ?>
                                                    <li><strong>Catatan Umum:</strong> <?php echo $order['catatan_pelanggan_umum']; ?></li>
                                                <?php endif; ?>
                                            </ul>

                                            <?php if (empty($order['shipments'])): ?>
                                                <div class="alert alert-warning text-center" role="alert">Tidak ada informasi pengiriman untuk pesanan ini.</div>
                                                <?php if (!empty($order['items_without_shipment'])): ?>
                                                    <h6 class="mb-3 text-secondary"><i class="bi bi-box me-2"></i>Produk dalam Pesanan ini:</h6>
                                                    <?php foreach ($order['items_without_shipment'] as $item): ?>
                                                        <div class="product-list-item">
                                                            <img src="../../img/barang/<?php echo $item['gambar_produk']; ?>" alt="<?php echo $item['nama_produk']; ?>" class="product-img-thumb">
                                                            <div class="flex-grow-1">
                                                                <p class="mb-0 fw-bold"><?php echo $item['nama_produk']; ?></p>
                                                                <?php if (!empty($item['variasi_detail'])): ?>
                                                                    <small class="text-muted d-block"><?php echo $item['variasi_detail']; ?></small>
                                                                <?php endif; ?>
                                                                <small class="text-muted"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['harga_satuan']); ?></small>
                                                            </div>
                                                            <span class="fw-bold text-end"><?php echo formatRupiah($item['subtotal_harga']); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php foreach ($order['shipments'] as $pengiriman_id => $shipment): ?>
                                                    <div class="shipment-detail-card mb-4">
                                                        <h6 class="mb-3 d-flex align-items-center">
                                                            <i class="bi bi-truck me-2"></i> Pengiriman dengan: <span class="badge bg-dark ms-2"><?php echo $shipment['nama_kurir']; ?></span>
                                                        </h6>
                                                        <ul class="list-unstyled mb-3">
                                                            <li><strong>Kurir:</strong> <?php echo $shipment['nama_kurir']; ?></li>
                                                            <li><strong>Status Pengiriman:</strong> <?php echo getStatusBadge($shipment['status_pengiriman']); ?></li>
                                                            <?php if (!empty($shipment['nomor_resi'])): ?>
                                                                <li>
                                                                    <strong>Nomor Resi:</strong> <?php echo $shipment['nomor_resi']; ?>
                                                                    <a href="https://cekresi.com/?noresi=<?php echo urlencode($shipment['nomor_resi']); ?>" target="_blank" class="btn btn-sm btn-track ms-2">
                                                                        <i class="bi bi-search me-1"></i> Lacak
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($shipment['status_pengiriman'] === 'dikirim' || $shipment['status_pengiriman'] === 'diterima' || $shipment['status_pengiriman'] === 'selesai'): ?>
                                                                <li><strong>Tanggal Pengiriman:</strong> <?php echo $shipment['tanggal_pengiriman']; ?></li>
                                                            <?php endif; ?>
                                                            <?php if (!empty($shipment['catatan_penjual'])): ?>
                                                                <li><strong>Catatan Pengiriman:</strong> <?php echo $shipment['catatan_penjual']; ?></li>
                                                            <?php endif; ?>
                                                            <?php if ($shipment['status_pengiriman'] === 'dikirim'): ?>
                                                                <li class="mt-3">
                                                                    <button class="btn btn-success" onclick="confirmOrderReceived(<?php echo $order_id; ?>, <?php echo $pengiriman_id; ?>)">
                                                                        <i class="bi bi-box-seam me-1"></i> Konfirmasi Diterima
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                        <h6 class="mb-3 text-secondary"><i class="bi bi-box me-2"></i>Produk dalam Pengiriman ini:</h6>
                                                        <?php if (empty($shipment['items'])): ?>
                                                            <p class="text-muted">Tidak ada produk dalam pengiriman ini.</p>
                                                        <?php else: ?>
                                                            <?php foreach ($shipment['items'] as $item): ?>
                                                                <div class="product-list-item">
                                                                    <img src="../../img/barang/<?php echo $item['gambar_produk']; ?>" alt="<?php echo $item['nama_produk']; ?>" class="product-img-thumb">
                                                                    <div class="flex-grow-1">
                                                                        <p class="mb-0 fw-bold"><?php echo $item['nama_produk']; ?></p>
                                                                        <?php if (!empty($item['variasi_detail'])): ?>
                                                                            <small class="text-muted d-block"><?php echo $item['variasi_detail']; ?></small>
                                                                        <?php endif; ?>
                                                                        <small class="text-muted"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['harga_satuan']); ?></small>
                                                                    </div>
                                                                    <span class="fw-bold text-end"><?php echo formatRupiah($item['subtotal_harga']); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation example" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $current_query_params); ?>" aria-label="Previous">
                    <span aria-hidden="true">«</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo buildPaginationUrl($i, $current_query_params); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $current_query_params); ?>" aria-label="Next">
                    <span aria-hidden="true">»</span>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

            <?php endif; ?>
        </main>
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
            url: '../keranjang/get_cart_count.php', // Path relatif terhadap direktori customer
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

        fetch('../keranjang/ambil_keranjang_sementara.php') // Path relatif terhadap direktori customer
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

            // Menggunakan event 'click' pada document untuk menyembunyikan dropdown
            // jika klik dilakukan di luar linkKeranjang dan dropdownKeranjang
            document.addEventListener('click', (event) => {
                if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                    dropdownKeranjang.style.display = 'none';
                }
            });
        }
    });



        // asli riwayat
        function confirmOrderReceived(orderId, shipmentId) {
            if (confirm("Apakah Anda yakin telah menerima pesanan ini? Aksi ini akan menyelesaikan pesanan Anda.")) {
                $.ajax({
                    url: '../pesanan/confirm_received.php', // Ensure this path is correct
                    method: 'POST',
                    data: {
                        order_id: orderId,
                        shipment_id: shipmentId
                    },
                    success: function(response) {
                        try {
                            const res = JSON.parse(response);
                            if (res.success) {
                                alert(res.message);
                                location.reload(); // Reload the page to update status
                            } else {
                                alert("Gagal mengkonfirmasi penerimaan: " + res.message);
                            }
                        } catch (e) {
                            console.error("JSON Parse Error:", e);
                            alert("Terjadi kesalahan tak terduga dari server. Respon: " + response);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("Terjadi kesalahan AJAX: " + error);
                        console.error("AJAX Error:", xhr.responseText);
                    }
                });
            }
        }
    </script>
</body>
</html>