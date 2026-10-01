<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_produk = $_GET['id'];
    $user_id = $_SESSION['pengguna_id'];
    $gambar_path = '../../../img/barang/';

    // Amankan penghapusan: hanya penjual yang memiliki produk yang bisa menghapus
    $query_cek_kepemilikan = "SELECT gambar FROM produk WHERE id = ? AND penjual_id = ?";
    $stmt_cek_kepemilikan = mysqli_prepare($conn, $query_cek_kepemilikan);
    mysqli_stmt_bind_param($stmt_cek_kepemilikan, 'ii', $id_produk, $user_id);
    mysqli_stmt_execute($stmt_cek_kepemilikan);
    $result_cek_kepemilikan = mysqli_stmt_get_result($stmt_cek_kepemilikan);
    $data_produk = mysqli_fetch_assoc($result_cek_kepemilikan);
    mysqli_stmt_close($stmt_cek_kepemilikan);

    if ($data_produk) {
        $gambar_utama = $data_produk['gambar'];

        // Hapus data produk dari database
        $query_hapus_produk = "DELETE FROM produk WHERE id = ? AND penjual_id = ?";
        $stmt_hapus_produk = mysqli_prepare($conn, $query_hapus_produk);
        mysqli_stmt_bind_param($stmt_hapus_produk, 'ii', $id_produk, $user_id);

        if (mysqli_stmt_execute($stmt_hapus_produk)) {
            // Hapus gambar utama jika ada dan bukan gambar default
            if ($gambar_utama && file_exists($gambar_path . $gambar_utama)) {
                unlink($gambar_path . $gambar_utama);
            }

            // Hapus semua media terkait produk dari produk_media
            $query_hapus_media = "SELECT nama_file FROM produk_media WHERE produk_id = ?";
            $stmt_hapus_media = mysqli_prepare($conn, $query_hapus_media);
            mysqli_stmt_bind_param($stmt_hapus_media, 'i', $id_produk);
            mysqli_stmt_execute($stmt_hapus_media);
            $result_hapus_media = mysqli_stmt_get_result($stmt_hapus_media);
            while ($data_media = mysqli_fetch_assoc($result_hapus_media)) {
                $nama_file_media = $data_media['nama_file'];
                if ($nama_file_media && file_exists($gambar_path . $nama_file_media)) {
                    unlink($gambar_path . $nama_file_media);
                }
            }
            mysqli_stmt_close($stmt_hapus_media);

            // Hapus semua entri media terkait produk dari produk_media
            $query_delete_media_entries = "DELETE FROM produk_media WHERE produk_id = ?";
            $stmt_delete_media_entries = mysqli_prepare($conn, $query_delete_media_entries);
            mysqli_stmt_bind_param($stmt_delete_media_entries, 'i', $id_produk);
            mysqli_stmt_execute($stmt_delete_media_entries);
            mysqli_stmt_close($stmt_delete_media_entries);

            echo "<script>alert('Produk berhasil dihapus beserta semua medianya.'); window.location.href='daftar/daftar_produk.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus produk.'); window.location.href='daftar/daftar_produk.php';</script>";
        }
        mysqli_stmt_close($stmt_hapus_produk);
    } else {
        echo "<script>alert('Produk tidak ditemukan atau bukan milik Anda.'); window.location.href='daftar/daftar_produk.php';</script>";
    }
} else {
    echo "<script>alert('ID produk tidak valid.'); window.location.href='daftar/daftar_produk.php';</script>";
}

mysqli_close($conn);
?>