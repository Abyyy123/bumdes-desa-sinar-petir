<?php
session_start();
include('../../../koneksi/koneksi.php');

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan tidak dikenal.'
];

if (!isset($_SESSION['pengguna_id'])) {
    $response['message'] = 'Anda tidak memiliki akses. Silakan login kembali.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['pengguna_id'];

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_resi') {
    $pesanan_id_to_update = $_POST['pesanan_id_to_ship'];
    $resi_pengiriman = trim($_POST['resi_pengiriman']);

    if (empty($pesanan_id_to_update) || empty($resi_pengiriman)) {
        $response['message'] = "ID Pesanan dan Nomor Resi tidak boleh kosong.";
    } else {
        // KEAMANAN: Pastikan pesanan ini memang milik penjual ini dan statusnya 'diproses'
        $query_verify_ownership = "
            SELECT COUNT(DISTINCT p.id) AS count_orders
            FROM pesanan p
            JOIN detail_pesanan dp ON p.id = dp.pesanan_id
            JOIN produk pr ON dp.produk_id = pr.id
            WHERE p.id = ? AND pr.penjual_id = ? AND p.status_pesanan = 'diproses';
        ";
        $stmt_verify = mysqli_prepare($conn, $query_verify_ownership);
        mysqli_stmt_bind_param($stmt_verify, 'ii', $pesanan_id_to_update, $penjual_id);
        mysqli_stmt_execute($stmt_verify);
        $result_verify = mysqli_stmt_get_result($stmt_verify);
        $verified_order = mysqli_fetch_assoc($result_verify);
        mysqli_stmt_close($stmt_verify);

        if ($verified_order['count_orders'] > 0) {
            // Jika pesanan valid dan milik penjual ini, lanjutkan proses
            mysqli_begin_transaction($conn); // Mulai transaksi

            try {
                // 1. Update tabel `pesanan`
                $query_update_pesanan = "UPDATE pesanan SET status_pesanan = 'dikirim', resi_pengiriman = ? WHERE id = ?";
                $stmt_update_pesanan = mysqli_prepare($conn, $query_update_pesanan);
                mysqli_stmt_bind_param($stmt_update_pesanan, 'si', $resi_pengiriman, $pesanan_id_to_update);
                mysqli_stmt_execute($stmt_update_pesanan);
                mysqli_stmt_close($stmt_update_pesanan);

                // 2. Lakukan INSERT/UPDATE ke tabel `pengiriman`
                $kurir_id = NULL; // Sesuaikan dengan logika Anda jika ada kurir yang terdaftar
                $status_pengiriman = 'dikirim'; // atau status lain yang sesuai
                $tanggal_pengiriman = date('Y-m-d H:i:s');

                // Cek apakah sudah ada entri pengiriman untuk pesanan ini
                $query_check_pengiriman = "SELECT id FROM pengiriman WHERE pesanan_id = ?";
                $stmt_check_pengiriman = mysqli_prepare($conn, $query_check_pengiriman);
                mysqli_stmt_bind_param($stmt_check_pengiriman, 'i', $pesanan_id_to_update);
                mysqli_stmt_execute($stmt_check_pengiriman);
                $result_check_pengiriman = mysqli_stmt_get_result($stmt_check_pengiriman);
                $pengiriman_exists = mysqli_fetch_assoc($result_check_pengiriman);
                mysqli_stmt_close($stmt_check_pengiriman);

                if ($pengiriman_exists) {
                    // Jika sudah ada, lakukan UPDATE
                    $query_pengiriman = "
                        UPDATE pengiriman
                        SET kurir_id = ?, tanggal_pengiriman = ?, status_pengiriman = ?, nomor_resi = ?, updated_at = NOW()
                        WHERE pesanan_id = ?
                    ";
                    $stmt_pengiriman = mysqli_prepare($conn, $query_pengiriman);
                    mysqli_stmt_bind_param($stmt_pengiriman, 'isssi', $kurir_id, $tanggal_pengiriman, $status_pengiriman, $resi_pengiriman, $pesanan_id_to_update);
                } else {
                    // Jika belum ada, lakukan INSERT
                    $query_pengiriman = "
                        INSERT INTO pengiriman (pesanan_id, kurir_id, tanggal_pengiriman, status_pengiriman, nomor_resi, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ";
                    $stmt_pengiriman = mysqli_prepare($conn, $query_pengiriman);
                    mysqli_stmt_bind_param($stmt_pengiriman, 'iisss', $pesanan_id_to_update, $kurir_id, $tanggal_pengiriman, $status_pengiriman, $resi_pengiriman);
                }

                mysqli_stmt_execute($stmt_pengiriman);
                mysqli_stmt_close($stmt_pengiriman);

                mysqli_commit($conn); // Komit transaksi jika semua berhasil

                $response['success'] = true;
                $response['message'] = "Pesanan #{$pesanan_id_to_update} berhasil diupdate menjadi 'Dikirim' dengan nomor resi: " . htmlspecialchars($resi_pengiriman);

            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($conn); // Rollback transaksi jika ada kesalahan
                $response['message'] = "Gagal mengupdate pesanan dan pengiriman: " . $e->getMessage();
            }

        } else {
            $response['message'] = "Pesanan tidak ditemukan, bukan milik Anda, atau tidak dalam status 'diproses'.";
        }
    }
} else {
    $response['message'] = 'Permintaan tidak valid.';
}

echo json_encode($response);
exit;