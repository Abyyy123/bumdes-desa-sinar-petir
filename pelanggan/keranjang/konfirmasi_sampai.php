<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk mengkonfirmasi penerimaan pesanan.";
    header('Location: ../../login.php');
    exit();
}

// Cek apakah order_id diberikan melalui parameter GET
if (!isset($_GET['order_id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak ditemukan.";
    header('Location: pesanan_saya.php');
    exit();
}

$order_id = $_GET['order_id'];
$pengguna_id = $_SESSION['pengguna_id']; // ID pelanggan yang login

// 1. Periksa status pesanan dan pemiliknya
// Pastikan pesanan adalah milik pengguna yang login dan statusnya 'dikirim'
$query_check_order = "SELECT status_pesanan FROM pesanan WHERE id = ? AND pelanggan_id = ?";
$stmt_check_order = $conn->prepare($query_check_order);
if ($stmt_check_order === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memeriksa pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing check order query in konfirmasi_sampai.php: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}
$stmt_check_order->bind_param("ii", $order_id, $pengguna_id);
$stmt_check_order->execute();
$result_check_order = $stmt_check_order->get_result();
$order_data = $result_check_order->fetch_assoc();
$stmt_check_order->close();

if (!$order_data) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: pesanan_saya.php');
    exit();
}

// Hanya izinkan konfirmasi jika statusnya 'dikirim'
if ($order_data['status_pesanan'] !== 'dikirim') {
    $_SESSION['error_message'] = "Pesanan #{$order_id} tidak dalam status 'dikirim'. Tidak dapat mengkonfirmasi penerimaan.";
    header('Location: pesanan_saya.php');
    exit();
}

// 2. Jika semua validasi lolos, update status pesanan menjadi 'selesai'
$new_status = 'selesai';
$query_update_status = "UPDATE pesanan SET status_pesanan = ? WHERE id = ?";
$stmt_update_status = $conn->prepare($query_update_status);
if ($stmt_update_status === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat mengupdate status pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing update status query in konfirmasi_sampai.php: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}

$stmt_update_status->bind_param("si", $new_status, $order_id);

if ($stmt_update_status->execute()) {
    $_SESSION['success_message'] = "Pesanan #{$order_id} berhasil dikonfirmasi telah diterima. Terima kasih!";
    // Anda bisa menambahkan logika lain di sini,
    // misalnya:
    // - Notifikasi ke penjual bahwa pesanan sudah selesai
    // - Otomatis rilis dana ke penjual jika menggunakan sistem escrow
    // - Memberikan kesempatan untuk memberikan ulasan produk
} else {
    $_SESSION['error_message'] = "Gagal mengkonfirmasi penerimaan pesanan #{$order_id}: " . htmlspecialchars($stmt_update_status->error);
    error_log("Error updating status in konfirmasi_sampai.php for order {$order_id}: " . $stmt_update_status->error);
}

$stmt_update_status->close();
$conn->close();

header('Location: pesanan_saya.php');
exit();
?>