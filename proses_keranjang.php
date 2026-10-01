<?php
session_start();
include 'koneksi/koneksi.php'; // Pastikan path ini benar relative terhadap proses_keranjang.php

// Aktifkan error reporting untuk debugging (HAPUS ATAU KOMEN SAAT DEPLOY KE PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Header JSON untuk respons AJAX
header('Content-Type: application/json');

// Pastikan pengguna sudah login
if (!isset($_SESSION['id_pelanggan'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda harus login terlebih dahulu.', 'redirect' => 'login.php']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $produk_id = $_POST['produk_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    $variasi_id_raw = $_POST['variasi_id'] ?? null; // Ambil dulu dalam bentuk string/null

    // Konversi variasi_id ke int jika bukan null dan valid, jika tidak, set ke null
    $variasi_id = null;
    if ($variasi_id_raw !== null && is_numeric($variasi_id_raw) && $variasi_id_raw > 0) {
        $variasi_id = (int) $variasi_id_raw;
    }

    $id_pelanggan = $_SESSION['id_pelanggan'];

    // Validasi input
    if ($produk_id <= 0 || $quantity <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data produk atau kuantitas tidak valid.']);
        exit();
    }

    $stok_tersedia = 0;
    $harga_satuan = 0;

    if ($variasi_id !== null) {
        // Jika ada variasi, ambil stok dan harga dari produk_variasi
        $sql_variasi = "SELECT stok, harga FROM produk_variasi WHERE id = ? AND produk_id = ?";
        $stmt_variasi = mysqli_prepare($conn, $sql_variasi);
        if ($stmt_variasi === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error prepare statement variasi: ' . mysqli_error($conn)]);
            exit();
        }
        mysqli_stmt_bind_param($stmt_variasi, "ii", $variasi_id, $produk_id);
        mysqli_stmt_execute($stmt_variasi);
        $result_variasi = mysqli_stmt_get_result($stmt_variasi);
        $data_variasi = mysqli_fetch_assoc($result_variasi);
        mysqli_stmt_close($stmt_variasi);

        if (!$data_variasi) {
            echo json_encode(['status' => 'error', 'message' => 'Variasi produk tidak ditemukan.']);
            exit();
        }
        $stok_tersedia = $data_variasi['stok'];
        $harga_satuan = $data_variasi['harga'];
    } else {
        // Jika tidak ada variasi, ambil stok dan harga dari produk utama
        $sql_produk = "SELECT stok, harga FROM produk WHERE id = ?";
        $stmt_produk = mysqli_prepare($conn, $sql_produk);
        if ($stmt_produk === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error prepare statement produk: ' . mysqli_error($conn)]);
            exit();
        }
        mysqli_stmt_bind_param($stmt_produk, "i", $produk_id);
        mysqli_stmt_execute($stmt_produk);
        $result_produk = mysqli_stmt_get_result($stmt_produk);
        $data_produk = mysqli_fetch_assoc($result_produk);
        mysqli_stmt_close($stmt_produk);

        if (!$data_produk) {
            echo json_encode(['status' => 'error', 'message' => 'Produk tidak ditemukan.']);
            exit();
        }
        $stok_tersedia = $data_produk['stok'];
        $harga_satuan = $data_produk['harga'];
    }

    if ($quantity > $stok_tersedia) {
        echo json_encode(['status' => 'error', 'message' => "Stok tidak cukup. Hanya tersedia " . $stok_tersedia . " unit."]);
        exit();
    }

    // Cek apakah produk (dan variasi) sudah ada di keranjang pelanggan
    $sql_cek_keranjang = "SELECT id, quantity FROM keranjang WHERE id_pelanggan = ? AND produk_id = ?";
    $bind_types = "ii";
    $bind_params = [$id_pelanggan, $produk_id];

    if ($variasi_id !== null) {
        $sql_cek_keranjang .= " AND variasi_id = ?";
        $bind_types .= "i";
        $bind_params[] = $variasi_id;
    } else {
        $sql_cek_keranjang .= " AND variasi_id IS NULL"; // Penting untuk produk tanpa variasi
    }

    $stmt_cek_keranjang = mysqli_prepare($conn, $sql_cek_keranjang);
    if ($stmt_cek_keranjang === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement cek keranjang: ' . mysqli_error($conn)]);
        exit();
    }
    // Menggunakan call_user_func_array untuk bind_param dengan array dinamis
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_cek_keranjang, $bind_types], $bind_params));

    mysqli_stmt_execute($stmt_cek_keranjang);
    $result_cek_keranjang = mysqli_stmt_get_result($stmt_cek_keranjang);
    $keranjang_item = mysqli_fetch_assoc($result_cek_keranjang);
    mysqli_stmt_close($stmt_cek_keranjang);

    if ($keranjang_item) {
        // Jika sudah ada, update kuantitas
        $new_quantity = $keranjang_item['quantity'] + $quantity;
        if ($new_quantity > $stok_tersedia) {
            echo json_encode(['status' => 'error', 'message' => "Menambahkan produk ini akan melebihi stok yang tersedia. Stok saat ini: " . $stok_tersedia . ", di keranjang: " . $keranjang_item['quantity'] . "."]);
            exit();
        }
        $sql_update_keranjang = "UPDATE keranjang SET quantity = ?, harga_satuan = ?, total_harga = ? WHERE id = ?";
        $total_harga_baru = $new_quantity * $harga_satuan;
        $stmt_update_keranjang = mysqli_prepare($conn, $sql_update_keranjang);
        if ($stmt_update_keranjang === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error prepare statement update keranjang: ' . mysqli_error($conn)]);
            exit();
        }
        // Pastikan tipe bind_param sesuai dengan tipe data di DB (d untuk double/float)
        mysqli_stmt_bind_param($stmt_update_keranjang, "idii", $new_quantity, $harga_satuan, $total_harga_baru, $keranjang_item['id']);
        if (mysqli_stmt_execute($stmt_update_keranjang)) {
            echo json_encode(['status' => 'success', 'message' => "Kuantitas produk di keranjang berhasil diperbarui!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Gagal memperbarui keranjang: " . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt_update_keranjang);
    } else {
        // Jika belum ada, masukkan sebagai item baru
        $total_harga = $quantity * $harga_satuan;

        $sql_insert_keranjang_base = "INSERT INTO keranjang (id_pelanggan, produk_id, ";
        $sql_insert_keranjang_values_base = "VALUES (?, ?, ";
        $bind_types_insert = "ii";
        $bind_params_insert = [$id_pelanggan, $produk_id];

        if ($variasi_id !== null) {
            $sql_insert_keranjang_base .= "variasi_id, ";
            $sql_insert_keranjang_values_base .= "?, ";
            $bind_types_insert .= "i";
            $bind_params_insert[] = $variasi_id;
        }

        $sql_insert_keranjang = $sql_insert_keranjang_base . "quantity, harga_satuan, total_harga, created_at) " .
                                $sql_insert_keranjang_values_base . "?, ?, ?, NOW())";

        $bind_types_insert .= "idd"; // Untuk quantity, harga_satuan, total_harga
        $bind_params_insert[] = $quantity;
        $bind_params_insert[] = $harga_satuan;
        $bind_params_insert[] = $total_harga;

        $stmt_insert_keranjang = mysqli_prepare($conn, $sql_insert_keranjang);
        if ($stmt_insert_keranjang === false) {
            echo json_encode(['status' => 'error', 'message' => 'Error prepare statement insert keranjang: ' . mysqli_error($conn)]);
            exit();
        }

        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_insert_keranjang, $bind_types_insert], $bind_params_insert));

        if (mysqli_stmt_execute($stmt_insert_keranjang)) {
            echo json_encode(['status' => 'success', 'message' => "Produk berhasil ditambahkan ke keranjang!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Gagal menambahkan produk ke keranjang: " . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt_insert_keranjang);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => "Metode request tidak valid."]);
}

mysqli_close($conn); // Tutup koneksi di akhir skrip
?>