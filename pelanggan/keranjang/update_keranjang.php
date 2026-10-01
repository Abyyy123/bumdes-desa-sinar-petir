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
    $produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
    $variasi_id = isset($_POST['variasi_id']) && $_POST['variasi_id'] !== '' ? (int)$_POST['variasi_id'] : null;
    $new_quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($keranjang_id <= 0 || $new_quantity <= 0 || $produk_id <= 0) {
        $response['message'] = 'Data tidak valid.';
        echo json_encode($response);
        exit();
    }

    // 1. Validasi Stok (penting untuk keamanan)
    $current_stock = 0;
    if ($variasi_id !== null) {
        $stmt_stock = $conn->prepare("SELECT stok FROM produk_variasi WHERE id = ?");
        $stmt_stock->bind_param("i", $variasi_id);
    } else {
        $stmt_stock = $conn->prepare("SELECT stok FROM produk WHERE id = ?");
        $stmt_stock->bind_param("i", $produk_id);
    }
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    if ($row_stock = $result_stock->fetch_assoc()) {
        $current_stock = $row_stock['stok'];
    }
    $stmt_stock->close();

    if ($new_quantity > $current_stock) {
        $response['message'] = "Kuantitas yang diminta melebihi stok yang tersedia (" . $current_stock . ").";
        echo json_encode($response);
        exit();
    }

    // 2. Perbarui Kuantitas di Keranjang
    $stmt_update = $conn->prepare("UPDATE keranjang_customer SET quantity = ? WHERE id = ? AND customer_id = ?");
    $stmt_update->bind_param("iii", $new_quantity, $keranjang_id, $customer_id);

    if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Kuantitas berhasil diperbarui.';
        } else {
            // Bisa terjadi jika keranjang_id tidak ditemukan atau kuantitas tidak berubah
            $response['status'] = 'info';
            $response['message'] = 'Tidak ada perubahan kuantitas atau item tidak ditemukan.';
        }
    } else {
        $response['message'] = 'Gagal memperbarui kuantitas: ' . $stmt_update->error;
    }
    $stmt_update->close();

} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

$conn->close();
echo json_encode($response);
?>