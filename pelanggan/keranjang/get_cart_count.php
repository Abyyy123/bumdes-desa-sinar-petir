<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

if (!isset($_SESSION['pengguna_id'])) {
    $response['message'] = 'Anda harus login.';
    echo json_encode($response);
    exit();
}

$customer_id = $_SESSION['pengguna_id'];
$cart_total_quantity = 0; // Ubah nama variabel untuk lebih jelas

// Perubahan pada query: Gunakan SUM(quantity)
$stmt_count = $conn->prepare("SELECT SUM(quantity) AS total_quantity FROM keranjang_customer WHERE customer_id = ?");
$stmt_count->bind_param("i", $customer_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();

if ($row_count = $result_count->fetch_assoc()) {
    // Pastikan nilai tidak null jika keranjang kosong
    $cart_total_quantity = (int)$row_count['total_quantity'];
    $response['status'] = 'success';
    $response['count'] = $cart_total_quantity; // Kirimkan total kuantitas sebagai 'count'
} else {
    $response['message'] = 'Gagal mengambil jumlah keranjang.';
}
$stmt_count->close();

$conn->close();
echo json_encode($response);
?>