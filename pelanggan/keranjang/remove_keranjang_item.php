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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keranjang_id = isset($_POST['keranjang_id']) ? (int)$_POST['keranjang_id'] : 0;

    if ($keranjang_id <= 0) {
        $response['message'] = 'ID keranjang tidak valid.';
        echo json_encode($response);
        exit();
    }

    $stmt_delete = $conn->prepare("DELETE FROM keranjang_customer WHERE id = ? AND customer_id = ?");
    $stmt_delete->bind_param("ii", $keranjang_id, $customer_id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Produk berhasil dihapus dari keranjang.';
        } else {
            $response['status'] = 'info';
            $response['message'] = 'Produk tidak ditemukan di keranjang Anda.';
        }
    } else {
        $response['message'] = 'Gagal menghapus produk: ' . $stmt_delete->error;
    }
    $stmt_delete->close();

} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

$conn->close();
echo json_encode($response);
?>