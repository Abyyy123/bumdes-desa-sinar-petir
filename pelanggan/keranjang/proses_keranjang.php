<?php
session_start();
include '../../koneksi/koneksi.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

if (!isset($_SESSION['pengguna_id'])) {
    $response['message'] = 'Anda harus login untuk menambahkan produk ke keranjang.';
    echo json_encode($response);
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    // Penting: Pastikan variasi_id dikirim sebagai string 'null' atau integer
    $variasi_id = isset($_POST['variasi_id']) && $_POST['variasi_id'] !== '' ? (int)$_POST['variasi_id'] : null;

    if ($produk_id <= 0 || $quantity <= 0) {
        $response['message'] = 'Data produk atau kuantitas tidak valid.';
        echo json_encode($response);
        exit();
    }

    // 1. Validasi Stok
    $current_stock = 0;
    if ($variasi_id !== null) { // Menggunakan !== null untuk memeriksa variasi_id
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

    if ($quantity > $current_stock) {
        $response['message'] = "Stok produk tidak mencukupi. Tersedia: " . $current_stock;
        echo json_encode($response);
        exit();
    }

    // 2. Cek apakah produk sudah ada di keranjang untuk pengguna ini
    $sql_check = "SELECT id, quantity FROM keranjang_customer WHERE customer_id = ? AND produk_id = ?";
    if ($variasi_id !== null) {
        $sql_check .= " AND variasi_id = ?";
    } else {
        $sql_check .= " AND variasi_id IS NULL";
    }
    $stmt_check = $conn->prepare($sql_check);

    if ($variasi_id !== null) {
        $stmt_check->bind_param("iii", $pengguna_id, $produk_id, $variasi_id);
    } else {
        $stmt_check->bind_param("ii", $pengguna_id, $produk_id);
    }
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($row_cart = $result_check->fetch_assoc()) {
        // Produk sudah ada, update kuantitas
        $keranjang_item_id = $row_cart['id'];
        $old_quantity = $row_cart['quantity'];
        $new_total_quantity = $old_quantity + $quantity;

        // Validasi stok lagi untuk total kuantitas baru
        if ($new_total_quantity > $current_stock) {
            $response['message'] = "Penambahan gagal. Total kuantitas (" . $new_total_quantity . ") melebihi stok yang tersedia (" . $current_stock . ").";
            echo json_encode($response);
            $stmt_check->close();
            $conn->close();
            exit();
        }

        $sql_update = "UPDATE keranjang_customer SET quantity = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $new_total_quantity, $keranjang_item_id);

        if ($stmt_update->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Kuantitas produk di keranjang berhasil diperbarui.';
            $response['cart_count'] = 0; // Akan dihitung ulang
        } else {
            $response['message'] = 'Gagal memperbarui kuantitas di keranjang: ' . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        // Produk belum ada, tambahkan sebagai item baru
        $sql_insert = "INSERT INTO keranjang_customer (customer_id, produk_id, variasi_id, quantity) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        // Perbaikan di sini: Gunakan "iisi" (integer, integer, string, integer)
        // atau "iiii" jika variasi_id dijamin selalu integer (tidak null dari POST)
        // karena $variasi_id bisa null, "iisi" lebih aman
        $stmt_insert->bind_param("iisi", $pengguna_id, $produk_id, $variasi_id, $quantity);

        if ($stmt_insert->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Produk berhasil ditambahkan ke keranjang.';
            $response['cart_count'] = 0; // Akan dihitung ulang
        } else {
            $response['message'] = 'Gagal menambahkan produk ke keranjang: ' . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
    $stmt_check->close();

    // Hitung ulang jumlah item di keranjang setelah operasi
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS count FROM keranjang_customer WHERE customer_id = ?");
    $stmt_count->bind_param("i", $pengguna_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $response['cart_count'] = $row_count['count'];
    $stmt_count->close();

} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

$conn->close();
echo json_encode($response);
?>