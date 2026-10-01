<?php
// Tentukan path yang benar ke file koneksi Anda
include '../../koneksi/koneksi.php';

// Atur header untuk mengembalikan respons JSON
header('Content-Type: application/json');

// --- Bagian untuk Simulasi API Kurir ---
// Fungsi simulasi untuk mendapatkan data pelacakan
// Dalam implementasi nyata, ganti dengan panggilan API kurir yang sesungguhnya.
function cekStatusPengirimanDariAPI($nomor_resi, $nama_kurir) {
    // Logika simulasi
    $statuses = [
        'dikemas' => [
            'deskripsi' => 'Paket sedang disiapkan oleh penjual.',
            'estimasi' => date('Y-m-d', strtotime('+5 days'))
        ],
        'dikirim' => [
            'deskripsi' => 'Paket dalam perjalanan menuju lokasi Anda.',
            'estimasi' => date('Y-m-d', strtotime('+2 days'))
        ],
        'selesai' => [
            'deskripsi' => 'Paket telah diterima.',
            'estimasi' => date('Y-m-d')
        ]
    ];
    
    // Logika sederhana untuk mendapatkan status dari nomor resi
    $current_status = 'dikemas'; // Default
    if (substr($nomor_resi, -1) % 3 == 0) {
        $current_status = 'selesai';
    } elseif (substr($nomor_resi, -1) % 2 == 0) {
        $current_status = 'dikirim';
    }

    return [
        'status' => $current_status,
        'deskripsi' => $statuses[$current_status]['deskripsi'],
        'estimasi_sampai' => $statuses[$current_status]['estimasi'],
        'waktu_cek' => date('Y-m-d H:i:s')
    ];
}
// --- Akhir Bagian Simulasi API Kurir ---

// Periksa apakah permintaan berasal dari metode POST dan memiliki nomor_resi serta nama_kurir
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nomor_resi']) || !isset($_POST['nama_kurir'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

$nomor_resi = $_POST['nomor_resi'];
$nama_kurir = $_POST['nama_kurir'];

// Panggil fungsi API simulasi untuk mendapatkan data pelacakan
$data_tracking = cekStatusPengirimanDariAPI($nomor_resi, $nama_kurir);

// Jika data berhasil didapatkan
if ($data_tracking) {
    // Siapkan data untuk dikembalikan dalam format JSON
    $response_data = [
        'status' => 'success',
        'data' => [
            'deskripsi_status' => $data_tracking['deskripsi'],
            'waktu_update' => $data_tracking['waktu_cek']
        ]
    ];
    echo json_encode($response_data);
} else {
    // Jika ada masalah saat memanggil API
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch tracking data.']);
}

// Tutup koneksi
if ($conn) {
    $conn->close();
}