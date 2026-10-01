<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Metode request tidak valid.";
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login.";
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
$new_quantity = filter_input(INPUT_POST, 'new_quantity', FILTER_VALIDATE_INT);

if ($cart_id === false || $cart_id <= 0) {
    echo "ID item keranjang tidak valid.";
    exit();
}
if ($new_quantity === false || $new_quantity <= 0) { // Kuantitas minimal 1
    echo "Kuantitas baru tidak valid. Minimal 1.";
    exit();
}

// 1. Ambil detail produk/variasi dan stoknya dari database
$produk_id = null;
$variasi_id = null;
$current_stock = 0;

$sql_get_item_details = "SELECT 
                            k.produk_id, 
                            k.variasi_id, 
                            p.stok AS produk_stok, 
                            pv.stok AS variasi_stok
                         FROM 
                            keranjang k
                         JOIN 
                            produk p ON k.produk_id = p.id
                         LEFT JOIN 
                            produk_variasi pv ON k.variasi_id = pv.id
                         WHERE 
                            k.id = ? AND k.user_id = ?";
$stmt_get_details = mysqli_prepare($conn, $sql_get_item_details);
if ($stmt_get_details === false) {
    echo "Error prepare get details statement: " . mysqli_error($conn);
    exit();
}
mysqli_stmt_bind_param($stmt_get_details, "ii", $cart_id, $user_id);
mysqli_stmt_execute($stmt_get_details);
$result_details = mysqli_stmt_get_result($stmt_get_details);
$item_details = mysqli_fetch_assoc($result_details);
mysqli_stmt_close($stmt_get_details);

if (!$item_details) {
    echo "Item keranjang tidak ditemukan atau bukan milik Anda.";
    exit();
}

$produk_id = $item_details['produk_id'];
$variasi_id = $item_details['variasi_id'];

if ($variasi_id !== null) {
    $current_stock = $item_details['variasi_stok'] ?? 0;
} else {
    $current_stock = $item_details['produk_stok'] ?? 0;
}

if ($new_quantity > $current_stock) {
    echo "Kuantitas melebihi stok yang tersedia. Maksimal: " . number_format($current_stock, 0, ',', '.') . ".";
    exit();
}

// 2. Update kuantitas di tabel keranjang
$sql_update = "UPDATE keranjang SET kuantitas = ? WHERE id = ? AND user_id = ?";
$stmt_update = mysqli_prepare($conn, $sql_update);
if ($stmt_update === false) {
    echo "Error prepare update statement: " . mysqli_error($conn);
    exit();
}
mysqli_stmt_bind_param($stmt_update, "iii", $new_quantity, $cart_id, $user_id);

if (mysqli_stmt_execute($stmt_update)) {
    if (mysqli_stmt_affected_rows($stmt_update) > 0) {
        echo "Kuantitas item keranjang berhasil diperbarui.";
    } else {
        echo "Kuantitas tidak berubah atau item tidak ditemukan.";
    }
} else {
    echo "Gagal memperbarui kuantitas item keranjang: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt_update);
mysqli_close($conn);
?>