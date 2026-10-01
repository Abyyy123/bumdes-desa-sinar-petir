<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Fungsi untuk mengonversi byte ke MB
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes, 1024) : 0));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Batasan ukuran maksimum file (dalam byte) - contoh 5MB
$max_file_size = 5 * 1024 * 1024; // 5MB

if (isset($_POST['simpan_produk'])) {
    $id = $_POST['id'];
    $nama_produk = $_POST['nama_produk'];
    $deskripsi = $_POST['deskripsi'];
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $kategori_id = $_POST['kategori_id'];
    $unit_usaha_id = $_POST['unit_usaha_id'];
    $asal_desa = $_POST['asal_desa'];
    $berat = $_POST['berat'];
    $status_produk = $_POST['status_produk'];
    $tanggal_publikasi = $_POST['tanggal_publikasi'];

    // Validasi data (tambahkan validasi sesuai kebutuhan Anda)
    if (empty($nama_produk) || empty($harga) || empty($stok) || empty($kategori_id) || empty($unit_usaha_id)) {
        echo "<script>alert('Pastikan semua field wajib diisi.'); window.location.href='edit_produk.php?id=" . $id . "';</script>";
        exit;
    }

    // Proses upload gambar utama
    $gambar_utama = $_FILES['gambar_utama']['name'];
    $gambar_utama_tmp = $_FILES['gambar_utama']['tmp_name'];
    $gambar_utama_error = $_FILES['gambar_utama']['error'];
    $gambar_utama_size = $_FILES['gambar_utama']['size'];
    $gambar_utama_path = '../../../img/barang/';
    $gambar_utama_baru = '';
    $update_gambar = false;

    if ($gambar_utama_error === 0) {
        if ($gambar_utama_size > $max_file_size) {
            echo "<script>alert('Ukuran gambar utama melebihi batas maksimum (" . formatBytes($max_file_size) . ").'); window.location.href='edit_produk.php?id=" . $id . "';</script>";
            exit;
        }
        $gambar_ext = strtolower(pathinfo($gambar_utama, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'avif', 'mp4'];

        if (in_array($gambar_ext, $allowed_ext)) {
            $gambar_utama_baru = uniqid('', true) . '.' . $gambar_ext;
            move_uploaded_file($gambar_utama_tmp, $gambar_utama_path . $gambar_utama_baru);
            $update_gambar = true;

            // Hapus gambar lama jika ada
            $query_lama = "SELECT gambar FROM produk WHERE id = ?";
            $stmt_lama = mysqli_prepare($conn, $query_lama);
            mysqli_stmt_bind_param($stmt_lama, 'i', $id);
            mysqli_stmt_execute($stmt_lama);
            $result_lama = mysqli_stmt_get_result($stmt_lama);
            $data_lama = mysqli_fetch_assoc($result_lama);
            mysqli_stmt_close($stmt_lama);

            if ($data_lama['gambar'] && file_exists($gambar_utama_path . $data_lama['gambar'])) {
                unlink($gambar_utama_path . $data_lama['gambar']);
            }
        } else {
            echo "<script>alert('Ekstensi file gambar utama tidak diizinkan (jpg, jpeg, png, gif, avif, mp4).'); window.location.href='edit_produk.php?id=" . $id . "';</script>";
            exit;
        }
    }
        // Update data produk ke tabel produk
        $query_update_produk = "UPDATE produk SET
        unit_usaha_id = ?,
        nama = ?,
        deskripsi = ?,
        harga = ?,
        stok = ?,
        " . ($update_gambar ? "gambar = ?, " : "") . "
        asal_desa = ?,
        kategori_id = ?,
        berat = ?,
        status_produk = ?,
        tanggal_publikasi = ?,
        updated_at = NOW()
        WHERE id = ?";
        $stmt_update_produk = mysqli_prepare($conn, $query_update_produk);
        $params = [$unit_usaha_id, $nama_produk, $deskripsi, $harga, $stok];
        $types = 'isssi';
        if ($update_gambar) {
        $params[] = $gambar_utama_baru;
        $types .= 's';
        }
        $params[] = $asal_desa;
        $types .= 's';
        $params[] = $kategori_id;
        $types .= 'i';
        $params[] = $berat;
        $types .= 'd';
        $params[] = $status_produk;
        $types .= 's';
        $params[] = $tanggal_publikasi;
        $types .= 's';
        $params[] = $id;
        $types .= 'i';
        mysqli_stmt_bind_param($stmt_update_produk, $types, ...$params);
        mysqli_stmt_execute($stmt_update_produk);

    if (mysqli_stmt_affected_rows($stmt_update_produk) > 0) {
        // Proses upload media tambahan ke produk_media
        if (isset($_FILES['gambar_tambahan']) && is_array($_FILES['gambar_tambahan']['name'])) {
            $upload_dir = '../../../img/barang/';
            $jumlah_file = count($_FILES['gambar_tambahan']['name']);
            for ($i = 0; $i < $jumlah_file; $i++) {
                if ($_FILES['gambar_tambahan']['error'][$i] === 0) {
                    $nama_file = basename($_FILES['gambar_tambahan']['name'][$i]);
                    $ukuran_file = $_FILES['gambar_tambahan']['size'][$i];
                    if ($ukuran_file > $max_file_size) {
                        echo "<script>alert('Ukuran salah satu file tambahan melebihi batas maksimum (" . formatBytes($max_file_size) . "). File " . htmlspecialchars($nama_file) . " tidak diupload.');</script>";
                        continue; // Lewati file yang ukurannya terlalu besar
                    }
                    $target_path = $upload_dir . $nama_file;
                    $ext = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'avif', 'mp4'];
                    $jenis_media = in_array($ext, ['mp4']) ? 'video' : 'gambar';

                    if (in_array($ext, $allowed)) {
                        if (move_uploaded_file($_FILES['gambar_tambahan']['tmp_name'][$i], $target_path)) {
                            $query_insert_media = "INSERT INTO produk_media (produk_id, jenis_media, nama_file) VALUES (?, ?, ?)";
                            $stmt_insert_media = mysqli_prepare($conn, $query_insert_media);
                            mysqli_stmt_bind_param($stmt_insert_media, 'iss', $id, $jenis_media, $nama_file);
                            mysqli_stmt_execute($stmt_insert_media);
                            mysqli_stmt_close($stmt_insert_media);
                        } else {
                            echo "<script>alert('Gagal mengupload salah satu file tambahan.');</script>";
                        }
                    } else {
                        echo "<script>alert('Ekstensi file tambahan tidak diizinkan (jpg, jpeg, png, gif, avif, mp4). File " . htmlspecialchars($nama_file) . " tidak diupload.');</script>";
                    }
                }
            }
        }

        // Proses penghapusan media tambahan dari produk_media
        if (isset($_POST['hapus_media']) && is_array($_POST['hapus_media'])) {
            foreach ($_POST['hapus_media'] as $media_id_hapus) {
                $id_hapus = mysqli_real_escape_string($conn, $media_id_hapus);

                // Ambil nama file media untuk dihapus dari folder
                $query_hapus_file = "SELECT nama_file FROM produk_media WHERE id = ?";
                $stmt_hapus_file = mysqli_prepare($conn, $query_hapus_file);
                mysqli_stmt_bind_param($stmt_hapus_file, 'i', $id_hapus);
                mysqli_stmt_execute($stmt_hapus_file);
                $result_hapus_file = mysqli_stmt_get_result($stmt_hapus_file);
                $data_hapus_file = mysqli_fetch_assoc($result_hapus_file);
                mysqli_stmt_close($stmt_hapus_file);

                if ($data_hapus_file['nama_file'] && file_exists('../../../img/barang/' . $data_hapus_file['nama_file'])) {
                    unlink('../../../img/barang/' . $data_hapus_file['nama_file']);
                }

                // Hapus data media dari database
                $query_delete_media = "DELETE FROM produk_media WHERE id = ?";
                $stmt_delete_media = mysqli_prepare($conn, $query_delete_media);
                mysqli_stmt_bind_param($stmt_delete_media, 'i', $id_hapus);
                mysqli_stmt_execute($stmt_delete_media);
                mysqli_stmt_close($stmt_delete_media);
            }
        }

        echo "<script>alert('Data produk berhasil diperbarui.'); window.location.href='daftar_produk.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data produk.'); window.location.href='edit_produk.php?id=" . $id . "';</script>";
    }

    mysqli_stmt_close($stmt_update_produk);
    mysqli_close($conn);

} else {
    header('Location: daftar_produk.php');
    exit;
}
?>