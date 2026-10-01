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
    $keranjang_ids = isset($_POST['keranjang_ids']) ? $_POST['keranjang_ids'] : [];

    if (empty($keranjang_ids) || !is_array($keranjang_ids)) {
        $response['message'] = 'Tidak ada ID keranjang yang valid untuk dihapus.';
        echo json_encode($response);
        exit();
    }

    // Filter dan sanitasi ID
    $clean_ids = array_filter(array_map('intval', $keranjang_ids), function($id) {
        return $id > 0;
    });

    if (empty($clean_ids)) {
        $response['message'] = 'ID keranjang tidak valid atau kosong setelah sanitasi.';
        echo json_encode($response);
        exit();
    }

    // Buat placeholder untuk query IN clause
    $placeholders = implode(',', array_fill(0, count($clean_ids), '?'));
    $types = str_repeat('i', count($clean_ids)); // 'i' for integer

    // Tambahkan customer_id ke parameter
    $params = array_merge($clean_ids, [$customer_id]);
    $types .= 'i'; // Tambahkan 'i' untuk customer_id

    $stmt_delete = $conn->prepare("DELETE FROM keranjang_customer WHERE id IN ($placeholders) AND customer_id = ?");

    if (!$stmt_delete) {
        $response['message'] = 'Gagal menyiapkan query: ' . $conn->error;
        echo json_encode($response);
        exit();
    }

    // Bind parameter secara dinamis
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = &$params[$i]; // Variabel referensi
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt_delete, 'bind_param'], $bind_names);

    if ($stmt_delete->execute()) {
        $affected_rows = $stmt_delete->affected_rows;
        if ($affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = "Berhasil menghapus {$affected_rows} produk dari keranjang.";
        } else {
            $response['status'] = 'info';
            $response['message'] = 'Tidak ada produk yang cocok ditemukan atau sudah terhapus.';
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