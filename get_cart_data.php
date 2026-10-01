<?php
// get_cart_data.php
session_start();
include 'koneksi/koneksi.php'; // Path ke koneksi.php dari root

header('Content-Type: application/json');

$response = [
    'success' => false,
    'cart_items' => [],
    'message' => ''
];

if (!isset($_SESSION['pengguna_id'])) {
    $response['message'] = 'Anda belum login.';
    echo json_encode($response);
    exit();
}

$keranjang_items = $_SESSION['keranjang'] ?? [];
$final_cart_items = [];

if (!empty($keranjang_items)) {
    $produk_ids = [];
    $variasi_ids = [];

    foreach ($keranjang_items as $item) {
        $produk_ids[] = $item['produk_id'];
        if (isset($item['variasi_id']) && $item['variasi_id'] !== null) {
            $variasi_ids[] = $item['variasi_id'];
        }
    }

    // Ambil detail produk utama
    $produk_details = [];
    if (!empty($produk_ids)) {
        $unique_produk_ids = array_unique($produk_ids);
        $placeholders = implode(',', array_fill(0, count($unique_produk_ids), '?'));
        $sql_produk = "SELECT id, nama, harga, gambar FROM produk WHERE id IN ($placeholders)";
        $stmt_produk = $conn->prepare($sql_produk);
        if ($stmt_produk) {
            $types = str_repeat('i', count($unique_produk_ids));
            $stmt_produk->bind_param($types, ...$unique_produk_ids);
            $stmt_produk->execute();
            $result_produk = $stmt_produk->get_result();
            while ($row = $result_produk->fetch_assoc()) {
                $produk_details[$row['id']] = $row;
            }
            $stmt_produk->close();
        }
    }

    // Ambil detail variasi
    $variasi_details = [];
    if (!empty($variasi_ids)) {
        $unique_variasi_ids = array_unique($variasi_ids);
        $placeholders = implode(',', array_fill(0, count($unique_variasi_ids), '?'));
        $sql_variasi = "SELECT pv.id, pv.harga, pv.gambar, r.nama_rasa, w.nama_warna, u.nama_ukuran
                        FROM produk_variasi pv
                        LEFT JOIN rasa r ON pv.rasa_id = r.id
                        LEFT JOIN warna w ON pv.warna_id = w.id
                        LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                        WHERE pv.id IN ($placeholders)";
        $stmt_variasi = $conn->prepare($sql_variasi);
        if ($stmt_variasi) {
            $types = str_repeat('i', count($unique_variasi_ids));
            $stmt_variasi->bind_param($types, ...$unique_variasi_ids);
            $stmt_variasi->execute();
            $result_variasi = $stmt_variasi->get_result();
            while ($row = $result_variasi->fetch_assoc()) {
                $variasi_details[$row['id']] = $row;
            }
            $stmt_variasi->close();
        }
    }

    // Gabungkan data
    foreach ($keranjang_items as $item) {
        $item_data = [
            'produk_id' => $item['produk_id'],
            'quantity' => $item['quantity'],
            'variasi_id' => $item['variasi_id'] ?? null,
            'nama_produk' => '',
            'harga_satuan' => 0,
            'gambar_produk' => '',
            'nama_variasi' => ''
        ];

        // Ambil info produk
        if (isset($produk_details[$item['produk_id']])) {
            $item_data['nama_produk'] = htmlspecialchars($produk_details[$item['produk_id']]['nama']);
            $item_data['harga_satuan'] = $produk_details[$item['produk_id']]['harga'];
            $item_data['gambar_produk'] = htmlspecialchars($produk_details[$item['produk_id']]['gambar']);
        }

        // Jika ada variasi, override dengan info variasi
        if ($item['variasi_id'] !== null && isset($variasi_details[$item['variasi_id']])) {
            $variasi = $variasi_details[$item['variasi_id']];
            $item_data['harga_satuan'] = $variasi['harga'];
            if (!empty($variasi['gambar'])) {
                $item_data['gambar_produk'] = htmlspecialchars($variasi['gambar']);
            }

            $variasi_parts = [];
            if (!empty($variasi['nama_rasa'])) $variasi_parts[] = htmlspecialchars($variasi['nama_rasa']);
            if (!empty($variasi['nama_warna'])) $variasi_parts[] = htmlspecialchars($variasi['nama_warna']);
            if (!empty($variasi['nama_ukuran'])) $variasi_parts[] = htmlspecialchars($variasi['nama_ukuran']);
            $item_data['nama_variasi'] = implode(', ', $variasi_parts);
        }
        $final_cart_items[] = $item_data;
    }
}

$response['success'] = true;
$response['cart_items'] = $final_cart_items;
echo json_encode($response);

$conn->close();
?>