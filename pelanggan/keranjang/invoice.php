<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat invoice.";
    header('Location: ../../login.php'); // Redirect ke halaman login
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$pesanan_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

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

if ($pesanan_id === 0) {
    $_SESSION['error_message'] = "ID Pesanan tidak valid.";
    header('Location: pesanan_saya.php');
    exit();
}

$invoice_data = null;
$detail_pesanan_items = [];
$promo_data = null; // Variable to store promo data from 'diskon' table
$coupon_data = null; // New variable to store coupon data from 'pesanan' table

// Ambil data pesanan utama
$query_pesanan = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.metode_pembelian AS metode_pembayaran,
        pel.alamat AS alamat_pengiriman,
        p.ongkos_kirim,
        pel.nama AS nama_pelanggan,
        pel.email AS email_pelanggan,
        pel.nomor_telepon AS telepon_pelanggan,
        p.diskon_id,
        p.kode_kupon_terpakai,
        p.diskon_kupon,
        pr.jenis_diskon,
        pr.nilai_diskon
    FROM
        pesanan p
    JOIN
        pelanggan pel ON p.pelanggan_id = pel.pengguna_id
    LEFT JOIN
        diskon pr ON p.diskon_id = pr.id
    WHERE
        p.id = ? AND p.pelanggan_id = ?;
";
$stmt_pesanan = $conn->prepare($query_pesanan);
if ($stmt_pesanan) {
    $stmt_pesanan->bind_param("ii", $pesanan_id, $pengguna_id);
    $stmt_pesanan->execute();
    $result_pesanan = $stmt_pesanan->get_result();
    if ($result_pesanan->num_rows > 0) {
        $invoice_data = $result_pesanan->fetch_assoc();
        // Check if promo data exists
        if (!empty($invoice_data['diskon_id'])) {
            $promo_data = [
                'jenis_diskon' => $invoice_data['jenis_diskon'],
                'nilai_diskon' => $invoice_data['nilai_diskon']
            ];
        }
        // Check if coupon data exists
        if (!empty($invoice_data['kode_kupon_terpakai']) && $invoice_data['diskon_kupon'] > 0) {
            $coupon_data = [
                'kode' => $invoice_data['kode_kupon_terpakai'],
                'nilai' => $invoice_data['diskon_kupon']
            ];
        }
    } else {
        $_SESSION['error_message'] = "Invoice tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.";
        header('Location: pesanan_saya.php');
        exit();
    }
    $stmt_pesanan->close();
} else {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat invoice.";
    error_log("Error preparing invoice query: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}

// Ambil detail produk untuk invoice
$query_detail_invoice = "
    SELECT
        dp.quantity,
        dp.harga_satuan,
        prod.nama AS nama_produk,
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
$stmt_detail_invoice = $conn->prepare($query_detail_invoice);
if ($stmt_detail_invoice) {
    $stmt_detail_invoice->bind_param("i", $pesanan_id);
    $stmt_detail_invoice->execute();
    $result_detail_invoice = $stmt_detail_invoice->get_result();
    while ($item_row = $result_detail_invoice->fetch_assoc()) {
        $detail_pesanan_items[] = $item_row;
    }
    $stmt_detail_invoice->close();
} else {
    error_log("Error preparing invoice detail query: " . $conn->error);
}

// Hitung subtotal produk (sebelum diskon dan ongkir)
$subtotal_produk = 0;
foreach ($detail_pesanan_items as $item) {
    $subtotal_produk += ($item['quantity'] * $item['harga_satuan']);
}

// Hitung diskon jika ada promo
$nilai_diskon_promo = 0;
if ($promo_data) {
    if ($promo_data['jenis_diskon'] === 'persentase') {
        $nilai_diskon_promo = ($subtotal_produk * $promo_data['nilai_diskon']) / 100;
    } elseif ($promo_data['jenis_diskon'] === 'tetap') {
        $nilai_diskon_promo = $promo_data['nilai_diskon'];
    }
}

// Combine with coupon discount if applicable
$total_discount = $nilai_diskon_promo;
if ($coupon_data) {
    $total_discount += $coupon_data['nilai'];
}

// Calculate the total after discount but before shipping for clarity in display
$subtotal_after_discount = $subtotal_produk - $total_discount;
if ($subtotal_after_discount < 0) { // Ensure subtotal doesn't go negative
    $subtotal_after_discount = 0;
}

$conn->close();

function formatRupiah($angka) {
    return number_format($angka, 0, ',', '.');
}

// Fungsi untuk mendapatkan nama bulan dalam Bahasa Indonesia
function namaBulanIndonesia($bulan) {
    $nama_bulan = array(
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    return $nama_bulan[(int)$bulan];
}

// Format tanggal pesanan dengan nama bulan dalam Bahasa Indonesia
$tanggal_pesanan_timestamp = strtotime($invoice_data['tanggal_pesanan']);
$tanggal_format_indonesia = date('d', $tanggal_pesanan_timestamp) . ' ' .
                            namaBulanIndonesia(date('n', $tanggal_pesanan_timestamp)) . ' ' .
                            date('Y, H:i', $tanggal_pesanan_timestamp);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pesanan #<?php echo htmlspecialchars($invoice_data['pesanan_id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    body {
        font-family: 'Arial', sans-serif;
        background-color: #f8f9fa;
    }
    .invoice-container {
        max-width: 800px;
        margin: 40px auto;
        background-color: #ffffff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }
    .invoice-header, .invoice-footer {
        text-align: center;
        margin-bottom: 20px;
    }
    .invoice-header h2 {
        color: #FF4500;
        font-weight: bold;
    }

    /* START: CSS untuk Penataan Detail Rapi (Detail Pembeli & Detail Invoice) */
    .detail-item {
        display: flex; /* Menggunakan Flexbox */
        margin-bottom: 0.25rem; /* Sedikit jarak antar baris */
        line-height: 1.5; /* Sesuaikan jika perlu */
        align-items: baseline; /* Memastikan teks sejajar di baris dasar */
    }

    /* CSS untuk teks label (Nama, Email, Telepon, dll.) */
    .detail-label-text {
        font-weight: bold;
        text-align: left; /* Teks label rata kiri */
        flex-shrink: 0;
        /* Ini akan disetel per kolom di bawah */
    }

    /* CSS untuk titik dua */
    .detail-colon {
        font-weight: bold;
        text-align: center; /* Titik dua rata tengah dalam ruangnya */
        width: 10px; /* Lebar kecil untuk titik dua, sedikit dilebarkan */
        flex-shrink: 0;
        margin-left: 5px; /* Tambahkan jarak dari label */
        margin-right: 5px; /* Jarak antara titik dua dan nilai */
        white-space: nowrap; /* Mencegah titik dua wrap */
    }

    .detail-value {
        flex-grow: 1; /* Biarkan nilai mengisi sisa ruang */
    }

    /* Lebar khusus untuk label teks di kolom Detail Pembeli */
    .col-md-6:first-child .detail-label-text {
        width: 85px;
    }

    /* Lebar khusus untuk label teks di kolom Detail Invoice */
    .col-md-6:last-child .detail-label-text {
        width: 160px;
    }
    /* END: CSS untuk Penataan Detail Rapi (Detail Pembeli & Detail Invoice) */


    /* START: CSS Baru untuk Total Section */
    .total-section {
        margin-top: 30px;
        display: flex; /* Menggunakan flexbox untuk keseluruhan section */
        flex-direction: column; /* Mengatur item dalam kolom */
        align-items: flex-end; /* Menggeser seluruh blok ke kanan */
        page-break-inside: avoid; /* Pastikan section ini tidak terpotong di tengah halaman cetak */
    }
    .total-row {
        display: flex;
        justify-content: flex-end; /* Untuk memastikan label dan nilai di baris rata kanan */
        margin-bottom: 5px;
        width: 100%; /* Agar bisa flex penuh */
        flex-wrap: nowrap; /* Penting: Mencegah baris ini wrap ke bawah */
        align-items: baseline; /* Memastikan semua teks sejajar di baris dasar */
    }
    /* Kelas untuk label di total section (Subtotal Produk, Biaya Pengiriman, dll.) */
    .total-label-text {
        font-weight: bold;
        text-align: left; /* Teks label rata kiri dalam ruangnya */
        flex-shrink: 0;
        width: 150px; /* Pertahankan lebar tetap untuk label */
        white-space: nowrap; /* Mencegah teks label wrap */
    }
    /* Kelas untuk titik dua di total section */
    .total-colon {
        font-weight: bold;
        text-align: center; /* Titik dua rata tengah dalam ruangnya */
        width: 10px; /* Sedikit tingkatkan lebarnya untuk visibilitas */
        flex-shrink: 0;
        margin-left: 90px; /* Sesuaikan jarak */
        margin-right: 1px; /* Sesuaikan jarak */
        white-space: nowrap;
    }
    .total-value {
        text-align: right;
        flex-grow: 1;
        max-width: 150px; /* Batasi lebar maksimum nilai jika terlalu panjang */
        white-space: nowrap;
    }
    .total-row.grand-total .total-value {
        font-size: 1.25em;
        color: #FF4500;
        font-weight: bold;
    }
    /* END: CSS Baru untuk Total Section */

    .invoice-details table {
        width: 100%;
        margin-bottom: 20px;
    }
    .invoice-details th, .invoice-details td {
        padding: 8px;
        border-bottom: 1px solid #dee2e6;
        text-align: left;
    }
    .invoice-details th {
        background-color: #f2f2f2;
    }
    .table-products th, .table-products td {
        vertical-align: middle;
    }
    .table-products th:nth-child(2),
    .table-products td:nth-child(2) {
        width: 40%;
    }
    .table-products td:nth-child(4) {
        text-align: right;
    }
    .btn-print, .btn-back {
        margin-top: 20px;
    }

    /* START: Media Query untuk Cetak (Default: Portrait) */
    @media print {
        body {
            background-color: #fff;
            margin: 0;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            padding: 10mm; /* Margin global untuk kertas */
            font-size: 10pt; /* Ukuran font default untuk cetak */
        }
        .invoice-container {
            box-shadow: none;
            margin: 0 auto;
            padding: 0; /* Padding diatur di body atau @page */
            max-width: 100%; /* Biarkan kontainer mengambil lebar penuh yang tersedia */
            border-radius: 0;
            border: none;
            width: 100%; /* Pastikan kontainer mengambil 100% lebar body */
        }
        /* Hide navbar, footer, and action buttons for print */
        .navbar, .footer, .btn-print, .btn-back, .d-flex.justify-content-center.gap-2.mt-4 {
            display: none !important;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }
        .col-md-6 {
            flex: 0 0 auto;
            width: 50%;
            padding-left: 15px;
            padding-right: 15px;
            box-sizing: border-box;
        }

        .detail-item {
            margin-bottom: 0.15rem;
            line-height: 1.3;
        }

        /* Untuk Detail Pembeli & Detail Invoice */
        .col-md-6:first-child .detail-label-text {
            width: 85px;
        }
        .col-md-6:last-child .detail-label-text {
            width: 160px;
        }
        .detail-colon { /* Pastikan titik dua di detail pembeli/invoice juga terlihat */
            width: 8px; /* Lebih lebar untuk visibilitas */
            margin-left: 4px;
            margin-right: 4px;
            text-align: center;
            font-size: 10pt; /* Pastikan ukuran font konsisten */
        }

        .invoice-header {
            margin-bottom: 15px;
        }

        table {
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-footer-group;
        }

        /* Penyesuaian khusus untuk cetak pada total section */
        .total-section {
            margin-top: 20px;
            padding: 5px 0;
            border-top: 1px dashed #ccc;
            border-bottom: 1px dashed #ccc;
            page-break-inside: avoid; /* Pastikan seluruh section tetap dalam satu halaman saat dicetak */
        }
        .total-row {
            margin-bottom: 3px;
            flex-wrap: nowrap; /* Penting untuk cetak: tidak membungkus */
            align-items: baseline; /* Memastikan semua teks sejajar di baris dasar */
        }
        .total-label-text {
            width: 140px; /* Sesuaikan lebar label untuk cetak jika perlu */
            white-space: nowrap;
        }
        .total-colon {
            width: 8px; /* Sedikit lebih lebar dari 5px, tapi lebih kecil dari 15px */
            margin-left: 55px;
            margin-right: 4px;
            font-size: 10pt; /* Pastikan ukuran font titik dua konsisten */
        }
        .total-value {
            max-width: 140px; /* Sesuaikan lebar nilai untuk cetak jika perlu */
            white-space: nowrap;
        }
    }
    /* END: Media Query untuk Cetak (Default: Portrait) */

    /* START: Media Query untuk Cetak (Landscape) */
    @media print and (orientation: landscape) {
        body {
            padding: 5mm; /* Kurangi padding untuk memberi lebih banyak ruang horizontal */
            font-size: 9pt; /* Mengecilkan font sedikit lebih lanjut */
        }

        .invoice-container {
            max-width: none; /* Izinkan untuk mengambil lebar maksimal di landscape */
            width: 100%;
        }

        /* Untuk mengecilkan seluruh konten agar muat */
        /* Menggunakan zoom untuk menskalakan konten secara keseluruhan */
        html, body {
            zoom: 0.95; /* SESUAIKAN NILAI INI jika perlu (misal: 0.7, 0.8, 0.9, 0.95, 0.99) */
            /* Alternatif: transform: scale(0.75); transform-origin: top left; */
        }

        /* Penyesuaian margin/padding untuk menghemat ruang di landscape */
        .invoice-header {
            margin-bottom: 10px;
        }
        .row.invoice-details {
            margin-bottom: 10px;
        }
        .detail-item {
            margin-bottom: 0.1rem;
            line-height: 1.2;
        }

        /* Untuk Detail Pembeli & Detail Invoice di Landscape */
        .col-md-6:first-child .detail-label-text {
            width: 75px; /* Mungkin perlu sedikit lebih kecil di landscape */
        }
        .col-md-6:last-child .detail-label-text {
            width: 140px; /* Mungkin perlu sedikit lebih kecil di landscape */
        }
        .detail-colon {
            width: 7px; /* Lebih kecil di landscape */
            margin-left: 3px;
            margin-right: 3px;
            font-size: 9pt; /* Sesuaikan ukuran font */
        }


        .table-products th, .table-products td {
            padding: 6px;
        }
        .total-section {
            margin-top: 15px; /* Sedikit lebih kecil di landscape */
        }
        .total-row {
            margin-bottom: 2px; /* Kurangi margin antar baris */
        }
        .total-label-text {
            width: 120px;
        }
        .total-colon {
            width: 7px; /* Sesuaikan di landscape juga */
            margin-left: 55px;
            margin-right: 3px;
            font-size: 9pt; /* Sesuaikan ukuran font */
        }
        .total-value {
            max-width: 120px;
        }
        .invoice-footer {
            margin-top: 15px; /* Sedikit lebih kecil di landscape */
        }
    }
    /* END: Media Query untuk Cetak (Landscape) */


    /* Header normal (non-cetak) */
    .invoice-header {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        position: relative;
        padding-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .invoice-header .invoice-logo {
        max-width: 70px;
        height: auto;
        margin-right: 15px;
    }
    .invoice-header .header-content {
        text-align: center;
        flex-grow: 1;
    }
    .invoice-header h2 {
        color: #FF4500;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .invoice-header p {
        margin-bottom: 0;
    }
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
    <div class="invoice-container">
        <div class="invoice-header">
            <img src="../../img/logo.png" alt="Logo Perusahaan" class="invoice-logo">
            <div class="header-content"> <h2>INVOICE</h2>
                <p class="text-muted">Desa SinarPetir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
            </div>
        </div>
        <div class="row invoice-details mb-4">
            <div class="col-md-6">
                <h6>Detail Pembeli : </h6>
                <div class="detail-item">
                    <span class="detail-label-text">Nama</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($invoice_data['nama_pelanggan']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Email</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($invoice_data['email_pelanggan']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Telepon</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($invoice_data['telepon_pelanggan']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Alamat</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($invoice_data['alamat_pengiriman'])); ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Detail Invoice : </h6>
                <div class="detail-item">
                    <span class="detail-label-text">Nomor Invoice</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">INV-<?php echo htmlspecialchars($invoice_data['pesanan_id']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Tanggal Pesanan</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo $tanggal_format_indonesia; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Metode Pembayaran</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $invoice_data['metode_pembayaran']))); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label-text">Status Pesanan</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value"><span class="badge bg-success"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $invoice_data['status_pesanan']))); ?></span></span>
                </div>
            </div>
        </div>

        <h5 class="mt-4 mb-3">Detail Produk:</h5>
        <table class="table table-bordered table-products">
            <thead>
                <tr>
                    <th>Penjual</th>
                    <th>Produk</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Harga Satuan</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($detail_pesanan_items)): ?>
                    <?php foreach ($detail_pesanan_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nama_toko']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($item['nama_produk']); ?>
                                <?php
                                $variasi_detail = [];
                                if (!empty($item['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($item['nama_rasa']); }
                                if (!empty($item['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($item['nama_warna']); }
                                if (!empty($item['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($item['nama_ukuran']); }
                                if (!empty($variasi_detail)):
                                ?>
                                    <br><small class="text-muted">(<?php echo implode(', ', $variasi_detail); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td class="text-end">Rp <?php echo formatRupiah($item['harga_satuan']); ?></td>
                            <td class="text-end">Rp <?php echo formatRupiah($item['quantity'] * $item['harga_satuan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Tidak ada detail produk untuk invoice ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span class="total-label-text">Subtotal Produk</span>
                <span class="total-colon">:</span>
                <span class="total-value">Rp <?php echo formatRupiah($subtotal_produk); ?></span>
            </div>

            <?php if ($promo_data && $nilai_diskon_promo > 0): ?>
            <div class="total-row">
                <span class="total-label-text">Diskon Promo</span>
                <span class="total-colon">:</span>
                <span class="total-value text-danger">- Rp <?php echo formatRupiah($nilai_diskon_promo); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($coupon_data): // Menampilkan diskon kupon jika ada ?>
            <div class="total-row">
                <span class="total-label-text">Diskon Kupon (<?php echo htmlspecialchars($coupon_data['kode']); ?>)</span>
                <span class="total-colon">:</span>
                <span class="total-value text-danger">- Rp <?php echo formatRupiah($coupon_data['nilai']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($promo_data && $nilai_diskon_promo > 0 || $coupon_data): // Menampilkan baris ini hanya jika ada diskon yang diterapkan ?>
            <div class="total-row">
                <span class="total-label-text">Subtotal Setelah Diskon</span>
                <span class="total-colon">:</span>
                <span class="total-value">Rp <?php echo formatRupiah($subtotal_after_discount); ?></span>
            </div>
            <?php endif; ?>

            <div class="total-row">
                <span class="total-label-text">Biaya Pengiriman</span>
                <span class="total-colon">:</span>
                <span class="total-value">Rp <?php echo formatRupiah($invoice_data['ongkos_kirim']); ?></span>
            </div>
            <div class="total-row grand-total">
                <span class="total-label-text">TOTAL BAYAR</span>
                <span class="total-colon">:</span>
                <span class="total-value">Rp <?php echo formatRupiah($invoice_data['total_harga']); ?></span>
            </div>
        </div>

        <div class="invoice-footer mt-5">
            <p class="text-muted small">Terima kasih atas kepercayaan Anda berbelanja di BUMDes SinarPetir.</p>
            <p class="text-muted small">Invoice ini dibuat secara otomatis dan sah tanpa tanda tangan.</p>
        </div>

        <div class="d-flex justify-content-center gap-2 mt-4">
            <button class="btn btn-info btn-print" onclick="window.print()">
                <i class="bi bi-printer"></i> Cetak Invoice
            </button>
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

        // Pastikan fungsi ini dipanggil
        muatJumlahKeranjangNav();

        if (linkKeranjang && dropdownKeranjang) {
            linkKeranjang.addEventListener('mouseenter', () => {
                muatIsiKeranjangDropdown(); // Muat ulang isi dropdown setiap kali mouse masuk
                dropdownKeranjang.style.display = 'block';
            });

            dropdownKeranjang.addEventListener('mouseleave', () => {
                dropdownKeranjang.style.display = 'none';
            });

            // Menyembunyikan dropdown jika klik di luar area keranjang dan link
            document.addEventListener('click', (event) => {
                if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                    dropdownKeranjang.style.display = 'none';
                }
            });
        }
    });

    // Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX (Disalin dari promo.php/artikel.php)
    function updateWishlistItemCount() {
        // Karena Anda sudah mengambil $total_item_wishlist di awal script PHP,
        // dan badge ini hanya perlu diperbarui jika ada interaksi yang mengubah wishlist
        // (yang kemungkinan besar terjadi di halaman lain atau melalui AJAX terpisah),
        // tidak perlu memanggil AJAX di sini hanya untuk me-reload badge di halaman invoice.
        // Cukup pastikan nilai PHP ditampilkan dengan benar.
        // Jika Anda ingin update dinamis, perlu AJAX call ke '../toggle_wishlist.php?action=get_count'
        // seperti yang Anda komentari di bawah.
    }
    
    $(document).ready(function() {
        // Panggil fungsi update saat halaman dimuat
        muatJumlahKeranjangNav();
        updateWishlistItemCount(); // Meskipun tidak melakukan AJAX, tetap panggil untuk konsistensi
    });
    </script>
</body>
</html>