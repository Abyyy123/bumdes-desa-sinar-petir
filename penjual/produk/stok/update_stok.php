<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk_id = $_POST['produk_id'];
    $stok_baru = $_POST['stok'];
    $user_id = $_SESSION['pengguna_id'];

    // Validasi input
    if (!filter_var($produk_id, FILTER_VALIDATE_INT) || $produk_id <= 0) {
        echo "<script>alert('ID Produk tidak valid.'); window.location.href='stok.php';</script>";
        exit;
    }
    if (!filter_var($stok_baru, FILTER_VALIDATE_INT) || $stok_baru < 0) {
        echo "<script>alert('Stok tidak valid. Masukkan angka positif.'); window.location.href='stok.php';</script>";
        exit;
    }

    // Pastikan produk dimiliki oleh penjual yang sedang login
    $query_check_owner = "SELECT COUNT(*) AS count FROM produk WHERE id = ? AND penjual_id = ?";
    $stmt_check_owner = mysqli_prepare($conn, $query_check_owner);
    mysqli_stmt_bind_param($stmt_check_owner, 'ii', $produk_id, $user_id);
    mysqli_stmt_execute($stmt_check_owner);
    $result_check_owner = mysqli_stmt_get_result($stmt_check_owner);
    $row_check_owner = mysqli_fetch_assoc($result_check_owner);

    if ($row_check_owner['count'] == 0) {
        echo "<script>alert('Anda tidak memiliki izin untuk mengedit stok produk ini.'); window.location.href='stok.php';</script>";
        exit;
    }

    // Update stok produk di database
    $query_update_stok = "UPDATE produk SET stok = ?, updated_at = NOW() WHERE id = ?";
    $stmt_update_stok = mysqli_prepare($conn, $query_update_stok);
    mysqli_stmt_bind_param($stmt_update_stok, 'ii', $stok_baru, $produk_id);

    if (mysqli_stmt_execute($stmt_update_stok)) {
        echo "<script>alert('Stok berhasil diperbarui!'); window.location.href='stok.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui stok: " . mysqli_error($conn) . "'); window.location.href='stok.php';</script>";
    }

    mysqli_stmt_close($stmt_update_stok);
    mysqli_stmt_close($stmt_check_owner);
    mysqli_close($conn);
} else {
    header('Location: stok.php');
    exit;
}
?>