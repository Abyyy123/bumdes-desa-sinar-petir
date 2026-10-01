<?php
session_start();

// Aktifkan laporan kesalahan untuk membantu debugging (hapus pada produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Periksa apakah permintaan adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan data yang dibutuhkan diterima
    if (isset($_POST['id_produk']) && isset($_POST['kuantitas'])) {
        $id_produk = filter_var($_POST['id_produk'], FILTER_SANITIZE_NUMBER_INT);
        $kuantitas = filter_var($_POST['kuantitas'], FILTER_SANITIZE_NUMBER_INT);

        // Validasi data
        if ($id_produk > 0 && $kuantitas > 0) {
            // Asumsikan Anda memiliki koneksi database di file lain
            require_once 'koneksi.php'; // Gantilah dengan path ke file koneksi Anda

            // Asumsikan Anda memiliki fungsi untuk mendapatkan informasi produk dari database
            function get_produk($conn, $id_produk) {
                $stmt = $conn->prepare("SELECT id_produk, nama_produk, harga, stok FROM produk WHERE id_produk = ?");
                $stmt->bind_param("i", $id_produk);
                $stmt->execute();
                $result = $stmt->get_result();
                return $result->fetch_assoc();
            }

            // Dapatkan informasi produk dari database
            $produk = get_produk($conn, $id_produk);

            if ($produk) {
                // Periksa stok produk
                if ($produk['stok'] >= $kuantitas) {
                    // Asumsikan pengguna sudah login dan ID pengguna tersedia di session
                    if (isset($_SESSION['id_pelanggan'])) {
                        $id_pelanggan = $_SESSION['id_pelanggan'];

                        // Periksa apakah produk sudah ada di keranjang pengguna
                        $stmt_cek = $conn->prepare("SELECT id_keranjang, kuantitas FROM keranjang WHERE id_pelanggan = ? AND id_produk = ?");
                        $stmt_cek->bind_param("ii", $id_pelanggan, $id_produk);
                        $stmt_cek->execute();
                        $result_cek = $stmt_cek->get_result();
                        $keranjang_item = $result_cek->fetch_assoc();

                        if ($keranjang_item) {
                            // Jika produk sudah ada, update kuantitas
                            $total_kuantitas = $keranjang_item['kuantitas'] + $kuantitas;
                            if ($produk['stok'] >= $total_kuantitas) {
                                $stmt_update = $conn->prepare("UPDATE keranjang SET kuantitas = ? WHERE id_keranjang = ?");
                                $stmt_update->bind_param("ii", $total_kuantitas, $keranjang_item['id_keranjang']);
                                if ($stmt_update->execute()) {
                                    $response = ['success' => true, 'message' => 'Kuantitas produk berhasil diperbarui di keranjang!', 'jumlah_item' => get_jumlah_item_keranjang($conn, $id_pelanggan)];
                                } else {
                                    $response = ['success' => false, 'message' => 'Gagal memperbarui kuantitas produk di keranjang.'];
                                }
                                $stmt_update->close();
                            } else {
                                $response = ['success' => false, 'message' => 'Stok produk tidak mencukupi untuk penambahan ini.'];
                            }
                        } else {
                            // Jika produk belum ada, tambahkan ke keranjang
                            $stmt_insert = $conn->prepare("INSERT INTO keranjang (id_pelanggan, id_produk, kuantitas, harga_satuan, tanggal_ditambahkan) VALUES (?, ?, ?, ?, NOW())");
                            $stmt_insert->bind_param("iiid", $id_pelanggan, $id_produk, $kuantitas, $produk['harga']);
                            if ($stmt_insert->execute()) {
                                $response = ['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang!', 'jumlah_item' => get_jumlah_item_keranjang($conn, $id_pelanggan)];
                            } else {
                                $response = ['success' => false, 'message' => 'Gagal menambahkan produk ke keranjang.'];
                            }
                            $stmt_insert->close();
                        }
                        $stmt_cek->close();
                    } else {
                        $response = ['success' => false, 'message' => 'Pengguna belum login.'];
                        // Anda mungkin ingin menyimpan item ke session atau cara lain untuk pengguna yang belum login
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Stok produk tidak mencukupi.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Produk tidak ditemukan.'];
            }

            $stmt->close();
            $conn->close();
        } else {
            $response = ['success' => false, 'message' => 'ID produk atau kuantitas tidak valid.'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Parameter id_produk dan kuantitas tidak lengkap.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode permintaan tidak valid. Gunakan POST.'];
}

// Mengembalikan respons dalam format JSON
header('Content-Type: application/json');
echo json_encode($response);

// Fungsi contoh untuk mendapatkan jumlah item di keranjang (sesuaikan dengan logika Anda)
function get_jumlah_item_keranjang($conn, $id_pelanggan) {
    $stmt = $conn->prepare("SELECT SUM(kuantitas) AS total_item FROM keranjang WHERE id_pelanggan = ?");
    $stmt->bind_param("i", $id_pelanggan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total_item'] ?? 0;
}
?>