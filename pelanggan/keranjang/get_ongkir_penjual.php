<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path koneksi database Anda

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak diketahui.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $penjual_id = isset($data['penjual_id']) ? (int)$data['penjual_id'] : 0;
    $total_berat = isset($data['total_berat']) ? (float)$data['total_berat'] : 0;
    $alamat_tujuan_customer = isset($data['alamat_tujuan']) ? htmlspecialchars($data['alamat_tujuan']) : '';
    // Anda mungkin memerlukan data lokasi yang lebih spesifik seperti kode pos atau kecamatan
    // $provinsi_tujuan = isset($data['provinsi_tujuan']) ? htmlspecialchars($data['provinsi_tujuan']) : '';
    // $kota_tujuan = isset($data['kota_tujuan']) ? htmlspecialchars($data['kota_tujuan']) : '';

    if ($penjual_id === 0 || $total_berat <= 0 || empty($alamat_tujuan_customer)) {
        $response['message'] = "Data tidak lengkap untuk menghitung ongkir.";
        echo json_encode($response);
        exit();
    }

    $kurir_options = [];

    // 1. Ambil pengaturan kurir untuk penjual ini
    $query_pengaturan = "SELECT
                            pk.kurir_id,
                            k.nama AS nama_kurir,
                            pk.biaya_default,
                            pk.tipe_biaya
                         FROM
                            pengaturan_kurir_penjual pk
                         JOIN
                            kurir k ON pk.kurir_id = k.id
                         WHERE
                            pk.pengguna_id = ? AND pk.aktif_penjual = TRUE"; // Pastikan kurir aktif
    $stmt_pengaturan = $conn->prepare($query_pengaturan);
    $stmt_pengaturan->bind_param("i", $penjual_id);
    $stmt_pengaturan->execute();
    $result_pengaturan = $stmt_pengaturan->get_result();

    while ($row_pengaturan = $result_pengaturan->fetch_assoc()) {
        $biaya_ongkir = 0;
        $tipe_biaya = $row_pengaturan['tipe_biaya'];

        if ($tipe_biaya === 'flat_rate') {
            $biaya_ongkir = $row_pengaturan['biaya_default'];
        } elseif ($tipe_biaya === 'per_berat') {
            // Asumsi biaya_default adalah tarif per kg/gram
            // Anda mungkin perlu menyesuaikan unit berat (kg vs gram)
            $biaya_ongkir = $row_pengaturan['biaya_default'] * ($total_berat / 1000); // Jika berat dalam gram, default per kg
        }
        // elseif ($tipe_biaya === 'dinamis_api') {
        //     // Di sini Anda akan memanggil API kurir eksternal
        //     // Ini akan sangat bergantung pada API yang Anda gunakan (contoh: RajaOngkir, JNE API, dll)
        //     // Membutuhkan logic yang lebih kompleks dan kredensial API
        //     // Untuk sementara, kita bisa anggap 0 atau default lain jika belum terintegrasi
        //     $biaya_ongkir = 0; // Placeholder
        // }

        $kurir_options[] = [
            'id' => $row_pengaturan['kurir_id'],
            'nama' => $row_pengaturan['nama_kurir'],
            'biaya_ongkir' => round($biaya_ongkir, 2), // Bulatkan biaya ongkir
            'tipe_biaya' => $tipe_biaya
        ];
    }
    $stmt_pengaturan->close();

    if (!empty($kurir_options)) {
        $response = ['status' => 'success', 'message' => 'Opsi kurir berhasil diambil.', 'kurir_options' => $kurir_options];
    } else {
        $response['message'] = "Tidak ada opsi kurir yang tersedia untuk penjual ini.";
    }

} else {
    $response['message'] = "Metode request tidak diizinkan.";
}

echo json_encode($response);
$conn->close();
?>