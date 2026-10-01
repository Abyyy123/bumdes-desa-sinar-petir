<?php
session_start();
include('koneksi/koneksi.php'); // Sesuaikan path jika get_cart_data.php tidak di root

header('Content-Type: application/json');

$cart_items_for_dropdown = [];
$total_items_count = 0;

$keranjang_items_session = $_SESSION['keranjang'] ?? [];

if (!empty($keranjang_items_session)) {
    $produk_ids_to_fetch = [];
    $variasi_ids_to_fetch = [];

    foreach ($keranjang_items_session as $item) {
        $produk_ids_to_fetch[] = $item['produk_id'];
        if (isset($item['variasi_id']) && $item['variasi_id'] !== null && $item['variasi_id'] !== '') {
            $variasi_ids_to_fetch[] = $item['variasi_id'];
        }
    }

    $produk_details = [];
    if (!empty($produk_ids_to_fetch)) {
        $unique_produk_ids = array_unique($produk_ids_to_fetch);
        $placeholders_produk = implode(',', array_fill(0, count($unique_produk_ids), '?'));
        $sql_produk = "SELECT id, nama, harga, gambar FROM produk WHERE id IN ($placeholders_produk)";
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

    $variasi_details = [];
    if (!empty($variasi_ids_to_fetch)) {
        $unique_variasi_ids = array_unique($variasi_ids_to_fetch);
        $placeholders_variasi = implode(',', array_fill(0, count($unique_variasi_ids), '?'));
        $sql_variasi = "SELECT pv.id, pv.harga, pv.gambar, r.nama_rasa, w.nama_warna, u.nama_ukuran
                        FROM produk_variasi pv
                        LEFT JOIN rasa r ON pv.rasa_id = r.id
                        LEFT JOIN warna w ON pv.warna_id = w.id
                        LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                        WHERE pv.id IN ($placeholders_variasi)";
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

    foreach ($keranjang_items_session as $item) {
        $nama_produk = '';
        $harga_satuan = 0;
        $gambar_produk = ''; // For main product image
        $gambar_variasi = ''; // For variation image
        $nama_variasi = '';

        if (isset($item['variasi_id']) && $item['variasi_id'] !== null && $item['variasi_id'] !== '' && isset($variasi_details[$item['variasi_id']])) {
            $current_variasi = $variasi_details[$item['variasi_id']];
            $harga_satuan = $current_variasi['harga'];
            if (!empty($current_variasi['gambar'])) {
                $gambar_variasi = $current_variasi['gambar']; // Use variation specific image
            }

            $variasi_parts = [];
            if (!empty($current_variasi['nama_rasa'])) $variasi_parts[] = $current_variasi['nama_rasa'];
            if (!empty($current_variasi['nama_warna'])) $variasi_parts[] = $current_variasi['nama_warna'];
            if (!empty($current_variasi['nama_ukuran'])) $variasi_parts[] = $current_variasi['nama_ukuran'];
            $nama_variasi = implode(', ', $variasi_parts);

            if (isset($produk_details[$item['produk_id']])) {
                $nama_produk = $produk_details[$item['produk_id']]['nama'];
                if (empty($gambar_variasi) && !empty($produk_details[$item['produk_id']]['gambar'])) {
                    $gambar_produk = $produk_details[$item['produk_id']]['gambar']; // Fallback to main product image
                }
            }
        } elseif (isset($produk_details[$item['produk_id']])) {
            $current_produk = $produk_details[$item['produk_id']];
            $nama_produk = $current_produk['nama'];
            $harga_satuan = $current_produk['harga'];
            if (!empty($current_produk['gambar'])) {
                $gambar_produk = $current_produk['gambar'];
            }
        }

        if (!empty($nama_produk)) {
            $cart_items_for_dropdown[] = [
                'produk_id' => $item['produk_id'],
                'variasi_id' => $item['variasi_id'] ?? null,
                'nama_produk' => $nama_produk,
                'harga_satuan' => $harga_satuan,
                'quantity' => $item['quantity'],
                'gambar_produk' => $gambar_produk, // For main product image
                'gambar_variasi' => $gambar_variasi, // For variation specific image
                'nama_variasi' => $nama_variasi
            ];
            $total_items_count += $item['quantity'];
        }
    }
}

echo json_encode([
    'success' => true,
    'cart_items' => $cart_items_for_dropdown,
    'total_count' => $total_items_count
]);
$conn->close();
?>