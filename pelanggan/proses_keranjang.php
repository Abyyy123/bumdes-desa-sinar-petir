<?php
session_start();
// Ubah path koneksi.php jika proses_keranjang.php ada di dalam subfolder (misal: pelanggan/)
include '../koneksi/koneksi.php'; // Path ini naik satu level ke root, lalu masuk ke folder koneksi/

header('Content-Type: text/plain');

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Metode request tidak diizinkan.";
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login terlebih dahulu untuk menambahkan produk ke keranjang.";
    exit();
}

$user_id = $_SESSION['user_id'];
$produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$variasi_id = isset($_POST['variasi_id']) ? (int)$_POST['variasi_id'] : null;

// Validasi input
if ($produk_id <= 0 || $quantity <= 0) {
    echo "Data produk atau kuantitas tidak valid.";
    exit();
}

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Ambil detail produk atau variasi untuk validasi stok dan harga
    $nama_produk = '';
    $harga_satuan = 0;
    $stok_tersedia = 0;

    if ($variasi_id) {
        // Jika ada variasi yang dipilih
        $sql_get_variasi = "SELECT pv.stok, pv.harga, p.nama FROM produk_variasi pv JOIN produk p ON pv.produk_id = p.id WHERE pv.id = ? AND pv.produk_id = ?";
        $stmt_get_variasi = mysqli_prepare($conn, $sql_get_variasi);
        mysqli_stmt_bind_param($stmt_get_variasi, "ii", $variasi_id, $produk_id);
        mysqli_stmt_execute($stmt_get_variasi);
        $result_get_variasi = mysqli_stmt_get_result($stmt_get_variasi);

        if ($data_variasi = mysqli_fetch_assoc($result_get_variasi)) {
            $stok_tersedia = $data_variasi['stok'];
            $harga_satuan = $data_variasi['harga'];
            $nama_produk = $data_variasi['nama'];
        } else {
            echo "Variasi produk tidak ditemukan.";
            mysqli_rollback($conn);
            exit();
        }
        mysqli_stmt_close($stmt_get_variasi);
    } else {
        // Jika tidak ada variasi (produk tunggal)
        $sql_get_produk = "SELECT stok, harga, nama FROM produk WHERE id = ?";
        $stmt_get_produk = mysqli_prepare($conn, $sql_get_produk);
        mysqli_stmt_bind_param($stmt_get_produk, "i", $produk_id);
        mysqli_stmt_execute($stmt_get_produk);
        $result_get_produk = mysqli_stmt_get_result($stmt_get_produk);

        if ($data_produk = mysqli_fetch_assoc($result_get_produk)) {
            $stok_tersedia = $data_produk['stok'];
            $harga_satuan = $data_produk['harga'];
            $nama_produk = $data_produk['nama'];
        } else {
            echo "Produk tidak ditemukan.";
            mysqli_rollback($conn);
            exit();
        }
        mysqli_stmt_close($stmt_get_produk);
    }

    // Validasi stok
    if ($quantity > $stok_tersedia) {
        echo "Stok untuk " . htmlspecialchars($nama_produk) . " tidak mencukupi. Stok tersedia: " . number_format($stok_tersedia, 0, ',', '.') . ".";
        mysqli_rollback($conn);
        exit();
    }

    // Cek apakah produk (atau variasi) sudah ada di keranjang pengguna
    $keranjang_id = null;
    $current_quantity_in_cart = 0;

    if ($variasi_id) {
        $sql_check_cart = "SELECT id, kuantitas FROM keranjang WHERE user_id = ? AND produk_id = ? AND variasi_id = ?";
        $stmt_check_cart = mysqli_prepare($conn, $sql_check_cart);
        mysqli_stmt_bind_param($stmt_check_cart, "iii", $user_id, $produk_id, $variasi_id);
    } else {
        $sql_check_cart = "SELECT id, kuantitas FROM keranjang WHERE user_id = ? AND produk_id = ? AND variasi_id IS NULL";
        $stmt_check_cart = mysqli_prepare($conn, $sql_check_cart);
        mysqli_stmt_bind_param($stmt_check_cart, "ii", $user_id, $produk_id);
    }
    mysqli_stmt_execute($stmt_check_cart);
    $result_check_cart = mysqli_stmt_get_result($stmt_check_cart);

    if ($row_cart = mysqli_fetch_assoc($result_check_cart)) {
        $keranjang_id = $row_cart['id'];
        $current_quantity_in_cart = $row_cart['kuantitas'];
    }
    mysqli_stmt_close($stmt_check_cart);

    $new_quantity = $current_quantity_in_cart + $quantity;

    // Validasi stok lagi setelah mempertimbangkan kuantitas yang sudah ada di keranjang
    if ($new_quantity > $stok_tersedia) {
        echo "Jumlah total produk " . htmlspecialchars($nama_produk) . " (termasuk yang sudah di keranjang) melebihi stok yang tersedia. Stok tersedia: " . number_format($stok_tersedia, 0, ',', '.') . ".";
        mysqli_rollback($conn);
        exit();
    }

    if ($keranjang_id) {
        // Jika produk sudah ada di keranjang, update kuantitasnya
        $sql_update_cart = "UPDATE keranjang SET kuantitas = ?, harga_saat_ini = ? WHERE id = ?";
        $stmt_update_cart = mysqli_prepare($conn, $sql_update_cart);
        mysqli_stmt_bind_param($stmt_update_cart, "idi", $new_quantity, $harga_satuan, $keranjang_id);
        mysqli_stmt_execute($stmt_update_cart);
        mysqli_stmt_close($stmt_update_cart);
        echo "Kuantitas " . htmlspecialchars($nama_produk) . " di keranjang berhasil diperbarui.";
    } else {
        // Jika produk belum ada di keranjang, tambahkan baru
        $sql_insert_cart = "INSERT INTO keranjang (user_id, produk_id, variasi_id, kuantitas, harga_saat_ini) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_cart = mysqli_prepare($conn, $sql_insert_cart);
        if ($variasi_id) {
            mysqli_stmt_bind_param($stmt_insert_cart, "iiiid", $user_id, $produk_id, $variasi_id, $quantity, $harga_satuan);
        } else {
            // Jika variasi_id adalah null, gunakan "i" untuk produk_id, "i" untuk kuantitas, "d" untuk harga_saat_ini dan set variasi_id ke NULL
            mysqli_stmt_bind_param($stmt_insert_cart, "iiid", $user_id, $produk_id, $quantity, $harga_satuan);
            // Untuk variasi_id IS NULL, Anda perlu mengubah query atau memastikan bahwa parameter yang dikirim benar-benar NULL
            $sql_insert_cart = "INSERT INTO keranjang (user_id, produk_id, variasi_id, kuantitas, harga_saat_ini) VALUES (?, ?, NULL, ?, ?)";
            $stmt_insert_cart = mysqli_prepare($conn, $sql_insert_cart);
            mysqli_stmt_bind_param($stmt_insert_cart, "iiid", $user_id, $produk_id, $quantity, $harga_satuan);
        }
        
        mysqli_stmt_execute($stmt_insert_cart);
        mysqli_stmt_close($stmt_insert_cart);
        echo htmlspecialchars($nama_produk) . " berhasil ditambahkan ke keranjang.";
    }

    mysqli_commit($conn); // Commit transaksi jika semua berhasil

} catch (Exception $e) {
    mysqli_rollback($conn); // Rollback transaksi jika terjadi error
    echo "Terjadi kesalahan: " . $e->getMessage();
}

mysqli_close($conn);
?>