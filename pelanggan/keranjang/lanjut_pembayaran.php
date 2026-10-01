<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melanjutkan pembayaran.";
    header('Location: ../../login.php'); // Redirect ke halaman login di root
    exit();
}

// Cek apakah order_id diberikan dan valid
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak valid untuk pembayaran.";
    header('Location: pesanan_saya.php'); // Kembali ke daftar pesanan jika ID tidak valid
    exit();
}

$order_id = $_GET['order_id'];
$pengguna_id = $_SESSION['pengguna_id'];

// --- VERIFIKASI PESANAN SEBELUM DIARAHKAN KE PAYMENT GATEWAY ---
// Ini penting untuk memastikan pesanan tersebut:
// 1. Benar-benar milik pengguna yang sedang login.
// 2. Statusnya masih 'menunggu_pembayaran'.

$query_check_order = "SELECT status_pesanan FROM pesanan WHERE id = ? AND pelanggan_id = ?";
$stmt_check_order = $conn->prepare($query_check_order);

if ($stmt_check_order === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memverifikasi pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing check order query for payment: " . $conn->error); // Untuk debugging
    header('Location: pesanan_saya.php');
    exit();
}

$stmt_check_order->bind_param("ii", $order_id, $pengguna_id);
$stmt_check_order->execute();
$result_check_order = $stmt_check_order->get_result();

if ($result_check_order->num_rows === 0) {
    // Pesanan tidak ditemukan atau bukan milik pengguna ini
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau Anda tidak memiliki akses untuk pembayaran pesanan ini.";
    header('Location: pesanan_saya.php');
    exit();
}

$order_data = $result_check_order->fetch_assoc();
if ($order_data['status_pesanan'] !== 'menunggu_pembayaran') {
    // Pesanan sudah tidak dalam status menunggu pembayaran (mungkin sudah dibayar/dibatalkan)
    $_SESSION['error_message'] = "Pesanan #" . htmlspecialchars($order_id) . " tidak lagi dalam status 'menunggu pembayaran'. Status saat ini: " . htmlspecialchars($order_data['status_pesanan']);
    header('Location: pesanan_saya.php');
    exit();
}

$stmt_check_order->close();
$conn->close(); // Tutup koneksi setelah verifikasi

// --- REDIRECT KE HALAMAN PAYMENT GATEWAY (SIMULASI) ---
// Setelah semua verifikasi berhasil, arahkan ke halaman payment_gateway.php
$_SESSION['success_message'] = "Anda akan diarahkan ke halaman pembayaran untuk Pesanan #" . htmlspecialchars($order_id) . ".";
header('Location: payment_getaway.php?order_id=' . $order_id);
exit();
?>