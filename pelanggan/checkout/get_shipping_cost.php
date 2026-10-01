<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path koneksi database Anda

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.', 'shipping_cost' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan pengguna sudah login jika diperlukan untuk perhitungan ongkir
    if (!isset($_SESSION['pengguna_id'])) {
        $response['message'] = "Anda harus login untuk mendapatkan biaya pengiriman.";
        echo json_encode($response);
        exit();
    }

    $kurir_id = isset($_POST['kurir_id']) ? (int)$_POST['kurir_id'] : 0;
    $total_berat = isset($_POST['total_berat']) ? (float)$_POST['total_berat'] : 0;

    // Validasi input
    if ($kurir_id <= 0 || $total_berat <= 0) {
        $response['message'] = "ID Kurir atau Total Berat tidak valid.";
        echo json_encode($response);
        exit();
    }

    // --- Contoh Logika Perhitungan Ongkir ---
    // Logika ini sangat sederhana. Anda perlu menggantinya dengan:
    // 1. Mengambil tarif dari database berdasarkan kurir_id.
    // 2. Menggunakan API layanan pengiriman (misal: RajaOngkir) untuk perhitungan yang akurat
    //    berdasarkan lokasi asal (penjual) dan tujuan (pembeli), serta berat.

    $shipping_cost = 0;

    // Contoh: Ambil tarif dasar dari tabel 'kurir'
    // Asumsi tabel `kurir` memiliki kolom `tarif_dasar_per_kg` atau sejenisnya
    $query_tarif = "SELECT tarif_dasar_per_kg FROM kurir WHERE id = ?";
    $stmt_tarif = $conn->prepare($query_tarif);

    if ($stmt_tarif) {
        $stmt_tarif->bind_param("i", $kurir_id);
        $stmt_tarif->execute();
        $result_tarif = $stmt_tarif->get_result();

        if ($row_tarif = $result_tarif->fetch_assoc()) {
            $tarif_per_kg = (float)$row_tarif['tarif_dasar_per_kg'];
            // Contoh sederhana: tarif per kg dikalikan total berat
            // Anda mungkin ingin membulatkan berat ke atas untuk perhitungan tarif (misal: 1.1kg jadi 2kg)
            $berat_untuk_tarif = ceil($total_berat); // Bulatkan ke atas
            $shipping_cost = $berat_untuk_tarif * $tarif_per_kg;

            $response['status'] = 'success';
            $response['message'] = 'Biaya pengiriman berhasil dihitung.';
            $response['shipping_cost'] = $shipping_cost;
        } else {
            $response['message'] = "Kurir tidak ditemukan atau tarif belum diatur.";
        }
        $stmt_tarif->close();
    } else {
        $response['message'] = "Gagal menyiapkan statement: " . htmlspecialchars($conn->error);
    }

} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

echo json_encode($response);
$conn->close();
?>