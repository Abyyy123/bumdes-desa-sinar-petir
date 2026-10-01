<?php
session_start();
include '../koneksi/koneksi.php'; // Sesuaikan path koneksi Anda

// Pastikan request method adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $variasi_id = isset($_POST['variasi_id']) ? (int)$_POST['variasi_id'] : null; // Bisa null jika tidak ada variasi

    if ($produk_id > 0 && $quantity > 0) {
        // Ambil informasi stok dan harga dari database
        $stok_tersedia = 0;
        $harga_satuan = 0;
        $nama_produk_item = '';
        $gambar_produk_item = '';
        $catatan_variasi = '';

        if ($variasi_id) {
            // Ambil detail variasi
            $sql_variasi = "SELECT pv.stok, pv.harga, pv.gambar, p.nama AS nama_produk_utama,
                                   r.nama_rasa, w.nama_warna, u.nama_ukuran
                            FROM produk_variasi pv
                            JOIN produk p ON pv.produk_id = p.id
                            LEFT JOIN rasa r ON pv.rasa_id = r.id
                            LEFT JOIN warna w ON pv.warna_id = w.id
                            LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                            WHERE pv.id = ? AND pv.produk_id = ?";
            $stmt_variasi = mysqli_prepare($conn, $sql_variasi);
            if ($stmt_variasi) {
                mysqli_stmt_bind_param($stmt_variasi, "ii", $variasi_id, $produk_id);
                mysqli_stmt_execute($stmt_variasi);
                $result_variasi = mysqli_stmt_get_result($stmt_variasi);
                $data_variasi = mysqli_fetch_assoc($result_variasi);
                mysqli_stmt_close($stmt_variasi);

                if ($data_variasi) {
                    $stok_tersedia = (int)$data_variasi['stok'];
                    $harga_satuan = (float)$data_variasi['harga'];
                    $nama_produk_item = $data_variasi['nama_produk_utama'];
                    $gambar_produk_item = $data_variasi['gambar'];

                    if ($data_variasi['nama_rasa']) $catatan_variasi .= "Rasa: " . $data_variasi['nama_rasa'] . "; ";
                    if ($data_variasi['nama_warna']) $catatan_variasi .= "Warna: " . $data_variasi['nama_warna'] . "; ";
                    if ($data_variasi['nama_ukuran']) $catatan_variasi .= "Ukuran: " . $data_variasi['nama_ukuran'] . "; ";
                    $catatan_variasi = rtrim($catatan_variasi, '; '); // Hapus semicolon terakhir
                }
            }
        } else {
            // Ambil detail produk utama
            $sql_produk = "SELECT stok, harga, gambar, nama FROM produk WHERE id = ?";
            $stmt_produk = mysqli_prepare($conn, $sql_produk);
            if ($stmt_produk) {
                mysqli_stmt_bind_param($stmt_produk, "i", $produk_id);
                mysqli_stmt_execute($stmt_produk);
                $result_produk = mysqli_stmt_get_result($stmt_produk);
                $data_produk = mysqli_fetch_assoc($result_produk);
                mysqli_stmt_close($stmt_produk);

                if ($data_produk) {
                    $stok_tersedia = (int)$data_produk['stok'];
                    $harga_satuan = (float)$data_produk['harga'];
                    $nama_produk_item = $data_produk['nama'];
                    $gambar_produk_item = $data_produk['gambar'];
                }
            }
        }

        if ($stok_tersedia > 0 && $quantity <= $stok_tersedia) {
            // Simpan informasi produk untuk pembelian langsung ke sesi
            $_SESSION['beli_sekarang'] = [
                'produk_id' => $produk_id,
                'variasi_id' => $variasi_id,
                'quantity' => $quantity,
                'harga_satuan' => $harga_satuan,
                'nama_produk' => $nama_produk_item,
                'gambar_produk' => $gambar_produk_item,
                'catatan_variasi' => $catatan_variasi,
                'jenis_pembelian' => 'langsung' // Penanda untuk checkout
            ];

            // Redirect ke halaman checkout
            header('Location: checkout.php'); // Sesuaikan path checkout Anda
            exit();
        } else {
            echo "Stok tidak mencukupi atau produk tidak valid.";
        }
    } else {
        echo "Data produk tidak lengkap atau tidak valid.";
    }
} else {
    echo "Metode request tidak diizinkan.";
}
mysqli_close($conn);
?>