<?php
session_start();
include 'koneksi/koneksi.php'; // Pastikan path ini benar, diasumsikan proses_beli.php di folder root

// Memastikan user sudah login
if (!isset($_SESSION['pengguna_id'])) {
    // Jika belum login, simpan produk yang ingin dibeli langsung ke sesi sementara
    // Ini agar setelah login, mereka bisa langsung diarahkan kembali ke checkout dengan produk ini
    $_SESSION['redirect_after_login'] = [
        'page' => 'checkout.php',
        'params' => $_GET // Simpan semua parameter GET
    ];
    header('Location: login.php'); // Arahkan ke halaman login
    exit();
}

$user_id = $_SESSION['pengguna_id'];
$produk_id = isset($_GET['produk_id']) ? intval($_GET['produk_id']) : 0;
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 0;
$variasi_id = isset($_GET['variasi_id']) ? intval($_GET['variasi_id']) : null; // variasi_id bisa null

// Validasi dasar input
if ($produk_id <= 0 || $quantity <= 0) {
    $_SESSION['error_message'] = "Detail pembelian tidak valid.";
    header('Location: produk.php?id=' . $produk_id); // Kembali ke detail produk
    exit();
}

// --- Pengecekan Stok di Database (di sisi server untuk keamanan) ---
$stok_tersedia = 0;
if ($variasi_id) {
    $stmt_stok = $conn->prepare("SELECT variasi_stok FROM produk_variasi WHERE id = ? AND produk_id = ?");
    $stmt_stok->bind_param("ii", $variasi_id, $produk_id);
    $stmt_stok->execute();
    $result_stok = $stmt_stok->get_result();
    if ($variasi_data = $result_stok->fetch_assoc()) {
        $stok_tersedia = $variasi_data['variasi_stok'];
    }
    $stmt_stok->close();
} else {
    $stmt_stok = $conn->prepare("SELECT stok FROM produk WHERE id = ?");
    $stmt_stok->bind_param("i", $produk_id);
    $stmt_stok->execute();
    $result_stok = $stmt_stok->get_result();
    if ($produk_data = $result_stok->fetch_assoc()) {
        $stok_tersedia = $produk_data['stok'];
    }
    $stmt_stok->close();
}

if ($quantity > $stok_tersedia) {
    $_SESSION['error_message'] = "Kuantitas yang diminta (" . number_format($quantity, 0, ',', '.') . ") melebihi stok yang tersedia (" . number_format($stok_tersedia, 0, ',', '.') . ").";
    header('Location: detail_produk.php?id=' . $produk_id); // Kembali ke detail produk
    exit();
}

// --- Simpan Detail Pembelian Langsung ke Sesi ---
// Ini akan digunakan oleh halaman checkout untuk menampilkan produk yang akan dibeli
$_SESSION['buy_now_item'] = [
    'produk_id' => $produk_id,
    'variasi_id' => $variasi_id,
    'quantity' => $quantity
];

// --- Redirect ke Halaman Checkout ---
header('Location: checkout.php');
exit();

$conn->close();
?>