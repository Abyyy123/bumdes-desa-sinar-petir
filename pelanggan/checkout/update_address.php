<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan pengguna sudah login
    if (!isset($_SESSION['pengguna_id'])) {
        $response['message'] = "Anda harus login untuk memperbarui alamat.";
        echo json_encode($response);
        exit();
    }
    $pengguna_id = $_SESSION['pengguna_id'];

    // Ambil data dari POST request
    $nama = isset($_POST['nama']) ? htmlspecialchars(trim($_POST['nama'])) : '';
    $alamat = isset($_POST['alamat']) ? htmlspecialchars(trim($_POST['alamat'])) : '';
    $nomor_telepon = isset($_POST['nomor_telepon']) ? htmlspecialchars(trim($_POST['nomor_telepon'])) : '';

    // Validasi input
    if (empty($nama) || empty($alamat) || empty($nomor_telepon)) {
        $response['message'] = "Nama, alamat, dan nomor telepon tidak boleh kosong.";
        echo json_encode($response);
        exit();
    }

    // Perbarui alamat di tabel 'pelanggan'
    $query_update = "UPDATE pelanggan SET nama = ?, alamat = ?, nomor_telepon = ? WHERE pengguna_id = ?";
    $stmt_update = $conn->prepare($query_update);

    if ($stmt_update) {
        $stmt_update->bind_param("sssi", $nama, $alamat, $nomor_telepon, $pengguna_id);
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Alamat berhasil diperbarui.';
                $response['data'] = [
                    'nama' => $nama,
                    'alamat' => $alamat,
                    'nomor_telepon' => $nomor_telepon
                ];
            } else {
                $response['message'] = 'Alamat tidak berubah atau tidak ditemukan.';
            }
        } else {
            $response['message'] = "Gagal memperbarui alamat: " . htmlspecialchars($stmt_update->error);
        }
        $stmt_update->close();
    } else {
        $response['message'] = "Gagal menyiapkan statement: " . htmlspecialchars($conn->error);
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

echo json_encode($response);
$conn->close();
?>