<?php
session_start();
include('../../../koneksi/koneksi.php');

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

if (!isset($_SESSION['pengguna_id'])) {
    $response['message'] = 'Anda tidak memiliki akses.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// Ambil data penjual
$query_penjual = "SELECT pengguna_id FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);

if (!$penjual) {
    $response['message'] = 'Profil penjual tidak ditemukan.';
    echo json_encode($response);
    exit;
}

$penjual_id = $penjual['pengguna_id'];

function getStatusPesananBadge($status) {
    switch ($status) {
        case 'menunggu_pembayaran':
            return '<span class="badge badge-warning">Menunggu Pembayaran</span>';
        case 'diproses':
            return '<span class="badge badge-info">Diproses</span>';
        case 'dikirim':
            return '<span class="badge badge-primary">Dikirim</span>';
        case 'selesai':
            return '<span class="badge badge-success">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge badge-danger">Dibatalkan</span>';
        case 'pengembalian':
            return '<span class="badge badge-secondary">Pengembalian</span>';
        default:
            return '<span class="badge badge-light">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span>';
    }
}

$pesanan_siap_kirim = [];

$query_pesanan_siap_kirim = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesanan,
        pb.nama AS nama_pembeli,
        p.alamat_pengiriman,
        SUM(dp.harga_satuan * dp.quantity) AS total_harga_pesanan_penjual,
        p.status_pesanan,
        p.resi_pengiriman
    FROM
        pesanan p
    JOIN
        detail_pesanan dp ON p.id = dp.pesanan_id
    JOIN
        produk pr ON dp.produk_id = pr.id
    JOIN
        pelanggan pb ON p.pelanggan_id = pb.pengguna_id
    WHERE
        pr.penjual_id = ?
        AND p.status_pesanan = 'diproses'
    GROUP BY
        p.id
    ORDER BY
        p.tanggal_pesanan ASC;
";

$stmt_pesanan_siap_kirim = mysqli_prepare($conn, $query_pesanan_siap_kirim);
mysqli_stmt_bind_param($stmt_pesanan_siap_kirim, 'i', $penjual_id);
mysqli_stmt_execute($stmt_pesanan_siap_kirim);
$result_pesanan_siap_kirim = mysqli_stmt_get_result($stmt_pesanan_siap_kirim);

if ($result_pesanan_siap_kirim) {
    while ($row = mysqli_fetch_assoc($result_pesanan_siap_kirim)) {
        $row['tanggal_pesanan'] = date('d M Y H:i', strtotime($row['tanggal_pesanan']));
        $row['status_pesanan_badge'] = getStatusPesananBadge($row['status_pesanan']);
        $row['resi_pengiriman'] = $row['resi_pengiriman'] ? htmlspecialchars($row['resi_pengiriman']) : '-';
        $row['nama_pembeli'] = htmlspecialchars($row['nama_pembeli']);
        $row['alamat_pengiriman'] = nl2br(htmlspecialchars($row['alamat_pengiriman']));
        $pesanan_siap_kirim[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $pesanan_siap_kirim;
} else {
    $response['message'] = "Gagal mengambil data pesanan: " . mysqli_error($conn);
}
mysqli_stmt_close($stmt_pesanan_siap_kirim);

echo json_encode($response);
exit;