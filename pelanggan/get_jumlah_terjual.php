<?php
// get_jumlah_terjual.php
// Jika get_jumlah_terjual.php ada di folder yang sama dengan koneksi.php
// ATAU jika koneksi.php ada di folder induk, sesuaikan path ini.
// Contoh: Jika keduanya di root atau di 'pages/' folder yang sama:
include '../koneksi/koneksi.php';// Path ini harus sesuai lokasi koneksi.php relatif ke get_jumlah_terjual.php

header('Content-Type: application/json'); // Beri tahu browser bahwa responsnya adalah JSON

$produk_id = 0;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $produk_id = $_GET['id'];

    $sql = "SELECT COALESCE(jumlah_terjual, 0) AS jumlah_terjual
            FROM produk
            WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $produk_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'jumlah_terjual' => (int) $data['jumlah_terjual']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
}

mysqli_close($conn);
?>