<?php
session_start();
include('../../koneksi/koneksi.php'); // Sesuaikan path

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.', 'current_quantity' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ambil data keranjang dari session
    $keranjang_items_session = $_SESSION['keranjang'] ?? [];

    switch ($action) {
        case 'update':
            $index = $_POST['index'] ?? null;
            $newQuantity = (int)($_POST['quantity'] ?? 0);

            if ($index !== null && isset($keranjang_items_session[$index]) && $newQuantity > 0) {
                $produk_id = $keranjang_items_session[$index]['produk_id'];
                $variasi_id = $keranjang_items_session[$index]['variasi_id'];

                $stok_tersedia = 0;
                if ($variasi_id !== null && $variasi_id !== '') {
                    // Get stock for variation
                    $stmt_stok = $conn->prepare("SELECT stok FROM produk_variasi WHERE id = ?");
                    $stmt_stok->bind_param("i", $variasi_id);
                    $stmt_stok->execute();
                    $result_stok = $stmt_stok->get_result();
                    if ($row = $result_stok->fetch_assoc()) {
                        $stok_tersedia = $row['stok'];
                    }
                    $stmt_stok->close();
                } else {
                    // Get stock for main product
                    $stmt_stok = $conn->prepare("SELECT stok FROM produk WHERE id = ?");
                    $stmt_stok->bind_param("i", $produk_id);
                    $stmt_stok->execute();
                    $result_stok = $stmt_stok->get_result();
                    if ($row = $result_stok->fetch_assoc()) {
                        $stok_tersedia = $row['stok'];
                    }
                    $stmt_stok->close();
                }

                if ($newQuantity <= $stok_tersedia) {
                    $keranjang_items_session[$index]['quantity'] = $newQuantity;
                    $_SESSION['keranjang'] = $keranjang_items_session;
                    $response['success'] = true;
                    $response['message'] = 'Kuantitas berhasil diperbarui.';
                    $response['current_quantity'] = $newQuantity;
                } else {
                    $response['message'] = "Stok tidak mencukupi. Stok tersedia: " . $stok_tersedia;
                    $response['current_quantity'] = $keranjang_items_session[$index]['quantity']; // Send back original quantity
                }
            } else {
                $response['message'] = 'Item keranjang tidak ditemukan atau kuantitas tidak valid.';
            }
            break;

        case 'remove':
            $index = $_POST['index'] ?? null;

            if ($index !== null && isset($keranjang_items_session[$index])) {
                array_splice($keranjang_items_session, $index, 1); // Remove item by index
                $_SESSION['keranjang'] = array_values($keranjang_items_session); // Re-index array
                $response['success'] = true;
                $response['message'] = 'Item berhasil dihapus.';
            } else {
                $response['message'] = 'Item keranjang tidak ditemukan.';
            }
            break;

        case 'remove_multiple':
            $indices_json = $_POST['indices'] ?? '[]';
            $indices_to_remove = json_decode($indices_json, true);

            if (is_array($indices_to_remove)) {
                // Sort indices in descending order to avoid re-indexing issues during removal
                rsort($indices_to_remove);

                foreach ($indices_to_remove as $index_to_remove) {
                    if (isset($keranjang_items_session[$index_to_remove])) {
                        array_splice($keranjang_items_session, $index_to_remove, 1);
                    }
                }
                $_SESSION['keranjang'] = array_values($keranjang_items_session); // Re-index array
                $response['success'] = true;
                $response['message'] = 'Item yang dipilih berhasil dihapus.';
            } else {
                $response['message'] = 'Daftar item untuk dihapus tidak valid.';
            }
            break;

        default:
            $response['message'] = 'Aksi tidak dikenal.';
            break;
    }
}

echo json_encode($response);
exit();
?>