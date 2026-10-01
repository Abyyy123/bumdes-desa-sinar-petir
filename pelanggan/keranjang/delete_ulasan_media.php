<?php
// delete_ulasan_media.php

// Pastikan ini adalah permintaan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Sertakan file koneksi database Anda
// Sesuaikan path ini dengan struktur file Anda
require_once 'koneksi_database.php'; // Ganti dengan file koneksi DB Anda

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

if (isset($_POST['media_id'])) {
    $media_id = $_POST['media_id'];
    // Anda mungkin juga ingin memvalidasi ulasan_id atau user_id untuk keamanan tambahan
    // Misalnya, pastikan media ini memang milik ulasan yang dapat diedit oleh pengguna saat ini.
    // $ulasan_id = $_POST['ulasan_id'] ?? null; // Jika Anda mengirim ulasan_id dari frontend

    // Mulai transaksi jika perlu
    // $conn->begin_transaction();

    try {
        // 1. Dapatkan nama file dari database sebelum menghapus record
        $stmt = $conn->prepare("SELECT nama_file FROM ulasan_media WHERE id = ?");
        $stmt->bind_param("i", $media_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $media_data = $result->fetch_assoc();
        $stmt->close();

        if (!$media_data) {
            $response = ['success' => false, 'message' => 'Media tidak ditemukan.'];
            // $conn->rollback();
            echo json_encode($response);
            exit();
        }

        $file_to_delete = $media_data['nama_file'];
        $file_path = '../../img/ulasan_media/' . $file_to_delete; // Sesuaikan path

        // 2. Hapus record dari database
        $stmt = $conn->prepare("DELETE FROM ulasan_media WHERE id = ?");
        $stmt->bind_param("i", $media_id);

        if ($stmt->execute()) {
            // 3. Hapus file fisik dari server
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $response = ['success' => true, 'message' => 'Media berhasil dihapus.'];
                } else {
                    $response = ['success' => false, 'message' => 'Gagal menghapus file fisik.'];
                    // Anda mungkin ingin mempertimbangkan rollback di sini jika penghapusan file fisik sangat krusial
                }
            } else {
                $response = ['success' => true, 'message' => 'Record database berhasil dihapus, file fisik tidak ditemukan (mungkin sudah terhapus).'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus record dari database: ' . $stmt->error];
            // $conn->rollback();
        }
        $stmt->close();

        // Commit transaksi jika semua berhasil
        // $conn->commit();

    } catch (Exception $e) {
        // $conn->rollback();
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()];
    }

    $conn->close();
} else {
    $response = ['success' => false, 'message' => 'Parameter media_id tidak ditemukan.'];
}

echo json_encode($response);
exit();