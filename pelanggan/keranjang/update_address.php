<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path ke file koneksi database Anda

header('Content-Type: application/json'); // Beri tahu browser bahwa responsnya adalah JSON

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan tidak dikenal.'
];

// Pastikan request adalah POST dan pengguna sudah login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['pengguna_id'])) {
        $response['message'] = 'Anda harus login untuk memperbarui alamat.';
        echo json_encode($response);
        exit();
    }

    $pengguna_id = $_SESSION['pengguna_id'];

    // Ambil data dari POST request
    $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $nomor_telepon = isset($_POST['nomor_telepon']) ? trim($_POST['nomor_telepon']) : '';

    // Validasi dasar di sisi server
    if (empty($nama)) {
        $response['message'] = 'Nama penerima tidak boleh kosong.';
        echo json_encode($response);
        exit();
    }
    if (empty($alamat)) {
        $response['message'] = 'Alamat lengkap tidak boleh kosong.';
        echo json_encode($response);
        exit();
    }
    if (empty($nomor_telepon)) {
        $response['message'] = 'Nomor telepon tidak boleh kosong.';
        echo json_encode($response);
        exit();
    }

    // Mulai transaksi database
    // Ini memastikan semua atau tidak sama sekali. Jika ada kueri yang gagal, semua perubahan akan di-rollback.
    $conn->begin_transaction();

    try {
        // --- 1. Perbarui tabel 'pelanggan' (nama, alamat, nomor_telepon) ---
        $stmt_pelanggan = $conn->prepare("UPDATE pelanggan SET nama = ?, alamat = ?, nomor_telepon = ? WHERE pengguna_id = ?");
        if ($stmt_pelanggan) {
            $stmt_pelanggan->bind_param('sssi', $nama, $alamat, $nomor_telepon, $pengguna_id);
            if (!$stmt_pelanggan->execute()) {
                throw new Exception('Gagal memperbarui data pelanggan: ' . $stmt_pelanggan->error);
            }
            $stmt_pelanggan->close();
        } else {
            throw new Exception('Gagal menyiapkan statement pelanggan: ' . $conn->error);
        }

        // --- 2. Perbarui tabel 'pengguna' (nama) ---
        // Biasanya tabel pengguna tidak menyimpan alamat dan nomor telepon, hanya nama.
        $stmt_pengguna = $conn->prepare("UPDATE pengguna SET nama = ? WHERE id = ?");
        if ($stmt_pengguna) {
            $stmt_pengguna->bind_param('si', $nama, $pengguna_id);
            if (!$stmt_pengguna->execute()) {
                throw new Exception('Gagal memperbarui data pengguna: ' . $stmt_pengguna->error);
            }
            $stmt_pengguna->close();
        } else {
            throw new Exception('Gagal menyiapkan statement pengguna: ' . $conn->error);
        }

        // --- 3. Perbarui tabel 'anggota' (nama DAN alamat) jika 'pelanggan' memiliki 'nomor_anggota' ---
        // Asumsi: 'nomor_anggota' di tabel 'pelanggan' adalah penghubung logis ke 'anggota.nomor_anggota'
        
        // Pertama, ambil 'nomor_anggota' yang terkait dengan 'pengguna_id' ini dari tabel 'pelanggan'
        $nomor_anggota_untuk_anggota = null;
        $stmt_get_anggota_link = $conn->prepare("SELECT nomor_anggota FROM pelanggan WHERE pengguna_id = ?");
        if ($stmt_get_anggota_link) {
            $stmt_get_anggota_link->bind_param('i', $pengguna_id);
            $stmt_get_anggota_link->execute();
            $result_anggota_link = $stmt_get_anggota_link->get_result();
            $pelanggan_data_link = $result_anggota_link->fetch_assoc();
            if ($pelanggan_data_link && !empty($pelanggan_data_link['nomor_anggota'])) {
                $nomor_anggota_untuk_anggota = $pelanggan_data_link['nomor_anggota'];
            }
            $stmt_get_anggota_link->close();
        } else {
            throw new Exception('Gagal menyiapkan statement untuk mengambil nomor_anggota dari pelanggan: ' . $conn->error);
        }

        // Jika 'nomor_anggota' ditemukan, lanjutkan untuk memperbarui tabel 'anggota'
        if ($nomor_anggota_untuk_anggota) {
            // *** PERUBAHAN DI SINI: Tambahkan 'alamat = ?' ke query UPDATE dan tambahkan $alamat ke bind_param ***
            $stmt_anggota = $conn->prepare("UPDATE anggota SET nama = ?, alamat = ? WHERE nomor_anggota = ?");
            if ($stmt_anggota) {
                // Tipe bind_param: 'sss' (nama, alamat, nomor_anggota - semua string)
                $stmt_anggota->bind_param('sss', $nama, $alamat, $nomor_anggota_untuk_anggota);
                if (!$stmt_anggota->execute()) {
                    throw new Exception('Gagal memperbarui data anggota: ' . $stmt_anggota->error);
                }
                $stmt_anggota->close();
            } else {
                throw new Exception('Gagal menyiapkan statement anggota: ' . $conn->error);
            }
        }

        // Jika semua pembaruan selesai tanpa menghasilkan exception, commit transaksi
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Alamat dan nama profil berhasil diperbarui di seluruh sistem.';

    } catch (Exception $e) {
        // Jika terjadi kesalahan, rollback semua perubahan yang dilakukan dalam transaksi ini
        $conn->rollback();
        $response['message'] = 'Gagal memperbarui alamat: ' . $e->getMessage();
        error_log("Transaksi gagal untuk ID pengguna {$pengguna_id}: " . $e->getMessage()); // Catat kesalahan untuk debugging
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

// Tutup koneksi database
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($response);
?>