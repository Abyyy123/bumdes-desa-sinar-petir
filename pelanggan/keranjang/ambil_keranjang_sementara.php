<?php
// keranjang/ambil_keranjang_sementara.php
include('../../koneksi/koneksi.php'); // Sesuaikan path jika perlu
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['pengguna_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda belum login.']);
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$cart_items_data = [];

$query_cart_dropdown = "
    SELECT
        kc.id AS keranjang_id,
        kc.produk_id,
        kc.variasi_id,
        kc.quantity,
        p.nama AS nama_produk,
        p.gambar AS gambar_produk,
        p.harga AS harga_produk,
        pv.harga AS harga_variasi,
        pv.stok AS stok_variasi,
        p.stok AS stok_produk,
        r.nama_rasa,
        w.nama_warna,
        u.nama_ukuran
    FROM
        keranjang_customer kc
    JOIN
        produk p ON kc.produk_id = p.id
    LEFT JOIN
        produk_variasi pv ON kc.variasi_id = pv.id
    LEFT JOIN
        rasa r ON pv.rasa_id = r.id
    LEFT JOIN
        warna w ON pv.warna_id = w.id
    LEFT JOIN
        ukuran u ON pv.ukuran_id = u.id
    WHERE
        kc.customer_id = ?
    ORDER BY
        kc.tanggal_ditambahkan DESC
"; // Tidak perlu LIMIT di sini karena JavaScript akan membatasi tampilan

$stmt = $conn->prepare($query_cart_dropdown);
if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan kueri: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $pengguna_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $harga_satuan = $row['variasi_id'] ? ($row['harga_variasi'] ?? $row['harga_produk']) : $row['harga_produk'];
        $stok_tersedia = $row['variasi_id'] ? ($row['stok_variasi'] ?? $row['stok_produk']) : $row['stok_produk'];

        $variasi_detail_parts = [];
        if (!empty($row['nama_rasa'])) { $variasi_detail_parts[] = htmlspecialchars($row['nama_rasa']); }
        if (!empty($row['nama_warna'])) { $variasi_detail_parts[] = htmlspecialchars($row['nama_warna']); }
        if (!empty($row['nama_ukuran'])) { $variasi_detail_parts[] = htmlspecialchars($row['nama_ukuran']); }

        $cart_items_data[] = [
            'keranjang_id' => $row['keranjang_id'],
            'produk_id' => $row['produk_id'],
            'variasi_id' => $row['variasi_id'],
            'nama_produk' => htmlspecialchars($row['nama_produk']),
            'gambar_produk' => htmlspecialchars($row['gambar_produk']),
            'harga_satuan' => $harga_satuan,
            'quantity' => $row['quantity'],
            'variasi_string' => implode(', ', $variasi_detail_parts) // String variasi singkat
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode($cart_items_data);
?>