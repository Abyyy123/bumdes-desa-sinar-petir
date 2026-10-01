<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['produk_id']) && is_numeric($_GET['produk_id'])) {
    $id_media = $_GET['id'];
    $produk_id = $_GET['produk_id'];
    $user_id = $_SESSION['pengguna_id'];
    $gambar_path = '../../../img/barang/';

    // Ambil informasi media yang akan dihapus untuk menghapus file fisiknya
    $query_ambil_media = "SELECT pm.nama_file
                           FROM produk_media pm
                           JOIN produk p ON pm.produk_id = p.id
                           WHERE pm.id = ? AND pm.produk_id = ? AND p.penjual_id = ?";
    $stmt_ambil_media = mysqli_prepare($conn, $query_ambil_media);
    mysqli_stmt_bind_param($stmt_ambil_media, 'iii', $id_media, $produk_id, $user_id);
    mysqli_stmt_execute($stmt_ambil_media);
    $result_ambil_media = mysqli_stmt_get_result($stmt_ambil_media);
    $data_media = mysqli_fetch_assoc($result_ambil_media);
    mysqli_stmt_close($stmt_ambil_media);

    if ($data_media) {
        $nama_file = $data_media['nama_file'];

        // Hapus data media dari database
        $query_hapus = "DELETE FROM produk_media
                        WHERE id = ? AND produk_id = ? AND
                              produk_id = (SELECT id FROM produk WHERE id = ? AND penjual_id = ?)";
        $stmt_hapus = mysqli_prepare($conn, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, 'iiii', $id_media, $produk_id, $produk_id, $user_id);
        if (mysqli_stmt_execute($stmt_hapus)) {
            // Hapus file media dari folder
            if ($nama_file && file_exists($gambar_path . $nama_file)) {
                unlink($gambar_path . $nama_file);
            }
            echo "<script>alert('Media berhasil dihapus.'); window.location.href='edit_produk.php?id=" . $produk_id . "';</script>";
        } else {
            echo "<script>alert('Gagal menghapus media dari database.'); window.location.href='edit_produk.php?id=" . $produk_id . "';</script>";
        }
        mysqli_stmt_close($stmt_hapus);
    } else {
        echo "<script>alert('Media tidak ditemukan atau bukan milik produk Anda.'); window.location.href='edit_produk.php?id=" . $produk_id . "';</script>";
    }
} else {
    echo "<script>alert('ID media atau ID produk tidak valid.'); window.location.href='./daftar_produk.php';</script>";
}

mysqli_close($conn);
?>