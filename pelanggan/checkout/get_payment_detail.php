<?php
// Pastikan path ke koneksi.php sudah benar
include '../koneksi/koneksi.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methodId = $_POST['method_id'] ?? null; // ID dari tabel metode_pembayaran
    // $methodCode = $_POST['method_code'] ?? null; // Kode metode (tidak terlalu dibutuhkan jika sudah ada methodId)

    if ($methodId) {
        // Ambil tipe pembayaran dan kode metode dari tabel `metode_pembayaran`
        // Ini penting untuk menentukan kolom mana yang akan diambil dari pengaturan_pembayaran_penjual
        $stmt_method_info = $conn->prepare("SELECT kode_metode, tipe_pembayaran FROM metode_pembayaran WHERE id = ?");
        $stmt_method_info->bind_param("i", $methodId);
        $stmt_method_info->execute();
        $result_method_info = $stmt_method_info->get_result();
        $method_info_row = $result_method_info->fetch_assoc();
        $methodType = $method_info_row['tipe_pembayaran'] ?? null;
        $methodCode = $method_info_row['kode_metode'] ?? null; // Dapatkan kode metode juga
        $stmt_method_info->close();

        if (!$methodType) {
            $response['message'] = 'Informasi metode pembayaran tidak ditemukan.';
            echo json_encode($response);
            $conn->close();
            exit();
        }

        // Untuk demo ini, kita akan asumsikan `pengguna_id` 1 adalah ID untuk pengaturan platform/default
        // Ganti ini dengan logika ID pengguna platform Anda jika berbeda.
        $platform_user_id = 1; // Contoh: ID pengguna yang mewakili platform/admin

        if ($methodType === 'bank_transfer' || $methodType === 'e_wallet') {
            // Ambil detail pembayaran dari tabel `pengaturan_pembayaran_penjual`
            // dengan asumsi `pengguna_id` adalah ID platform.
            $query = "SELECT nama_pemilik, detail_akun FROM pengaturan_pembayaran_penjual WHERE metode_pembayaran_id = ? AND pengguna_id = ? AND aktif = TRUE LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $methodId, $platform_user_id); 
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $response['success'] = true;
                if ($methodType === 'bank_transfer') {
                    $response['data'] = [
                        'nama_bank' => $methodCode, // Menggunakan kode metode sebagai nama bank (misal: BCA, MANDIRI)
                        'nomor_rekening' => $row['detail_akun'],
                        'atas_nama' => $row['nama_pemilik']
                    ];
                } elseif ($methodType === 'e_wallet') {
                    $response['data'] = [
                        'nama_ewallet' => $methodCode, // Menggunakan kode metode sebagai nama e-wallet (misal: DANA, OVO)
                        'nomor_ewallet' => $row['detail_akun']
                    ];
                }
            } else {
                $response['message'] = 'Detail pembayaran tidak ditemukan untuk metode ini.';
            }
            $stmt->close();
        } elseif ($methodType === 'qris') {
            // Untuk QRIS, gambar mungkin disimpan di tabel metode_pembayaran itu sendiri,
            // atau ada gambar QRIS umum. Jika gambar QRIS adalah `qris.png` statis,
            // tidak perlu query database di sini, cukup respons sukses.
            $response['success'] = true;
            $response['data'] = ['message' => 'QRIS code is handled on the frontend.'];
        } elseif ($methodType === 'cod') {
            $response['success'] = true;
            $response['data'] = ['message' => 'Cash on Delivery. Pay when order arrives.'];
        } else {
            $response['message'] = 'Tipe pembayaran tidak didukung untuk pengambilan detail.';
        }
    } else {
        $response['message'] = 'ID metode pembayaran tidak lengkap.';
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

echo json_encode($response);
$conn->close();
?>