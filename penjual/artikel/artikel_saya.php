<?php
session_start();
include('../../koneksi/koneksi.php'); // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php'); // Sesuaikan path ini ke halaman login Anda
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID pengguna yang sedang login

// Ambil data pengguna (untuk kebutuhan tampilan di navbar, dll)
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);
mysqli_stmt_close($stmt_pengguna);

if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan. Silakan login kembali.'); window.location.href='../../../logout.php';</script>";
    exit;
}

// Ambil data penjual (untuk tampilan profil toko, dll, dan untuk mendapatkan penjual_id)
$query_penjual = "SELECT pengguna_id, nama_toko, nama_pemilik, email, username, tanggal_lahir, foto, nomor_telepon, alamat, kebijakan_pengiriman FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);

if (!$penjual) {
    echo "<script>alert('Anda belum memiliki profil penjual. Silakan lengkapi profil Anda.'); window.location.href='../../../logout.php';</script>";
    exit;
}

$penjual_id_artikel = $penjual['pengguna_id']; // ID penjual dari tabel 'penjual', akan digunakan untuk kolom 'penulis_id' di tabel 'artikel'

// --- PROSES UPDATE PROFIL (disalin dari daftar_produk.php dan pesanan.php) ---
if (isset($_POST['simpan'])) {
    $nama_lengkap = $_POST['nama_lengkap']; // Ambil nilai nama lengkap dari form
    $nama_toko = $_POST['nama_toko'];
    $nama_pemilik = $_POST['nama_pemilik'];
    $email_penjual = $_POST['email_penjual'];
    $username_penjual = $_POST['username_penjual'];
    $alamat = $_POST['alamat'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $foto_lama = $penjual['foto'];
    $foto_baru = $foto_lama; // Default jika tidak ada upload baru
    $upload_dir = '../../img/foto/';

    // Proses upload foto
    if ($_FILES['foto']['name']) {
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                if ($foto_lama && $foto_lama != 'default.png' && file_exists($upload_dir . $foto_lama)) {
                    unlink($upload_dir . $foto_lama);
                }
                $foto_baru = $foto_name;
            } else {
                echo "<script>alert('Gagal mengupload foto profil.');</script>";
            }
        } else {
            echo "<script>alert('Ekstensi file foto profil tidak diizinkan.');</script>";
        }
    }
    // Update data di tabel penjual
    $update_penjual = "UPDATE penjual SET nama_toko=?, nama_pemilik=?, email=?, username=?, foto=?, alamat=?, nomor_telepon=?, tanggal_lahir=?, jenis_kelamin=?, updated_at=NOW() WHERE pengguna_id=?";
    $stmt_update_penjual = mysqli_prepare($conn, $update_penjual);
    mysqli_stmt_bind_param($stmt_update_penjual, 'sssssssssi', $nama_toko, $nama_pemilik, $email_penjual, $username_penjual, $foto_baru, $alamat, $nomor_telepon, $tanggal_lahir, $jenis_kelamin, $user_id);
    mysqli_stmt_execute($stmt_update_penjual);

    // Update data nama di tabel pengguna
    $update_pengguna = "UPDATE pengguna SET nama=? WHERE id=?";
    $stmt_update_pengguna = mysqli_prepare($conn, $update_pengguna);
    mysqli_stmt_bind_param($stmt_update_pengguna, 'si', $nama_lengkap, $user_id);
    mysqli_stmt_execute($stmt_update_pengguna);

    if (mysqli_stmt_affected_rows($stmt_update_penjual) > 0 || mysqli_stmt_affected_rows($stmt_update_pengguna) > 0) {
        // Alihkan kembali ke halaman pengiriman.php setelah update
        echo "<script>alert('Profil berhasil diperbarui.'); window.location.href='artikel.php';</script>";
    } else {
        echo "<script>alert('Tidak ada perubahan pada profil.'); window.location.href='artikel.php';</script>";
    }
    mysqli_stmt_close($stmt_update_penjual);
    mysqli_stmt_close($stmt_update_pengguna);
}
// --- AKHIR PROSES UPDATE PROFIL ---

// Fungsi untuk membuat slug dari judul artikel
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9 -]/', '', $string);
    $string = str_replace(' ', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// =====================================================================
// BAGIAN INTI MANAJEMEN ARTIKEL
// =====================================================================

$pesan_sukses = '';
$pesan_error = '';

// Path untuk menyimpan gambar artikel
$target_dir = "../../img/artikel/"; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

// Pastikan direktori target ada
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Proses Tambah Artikel
if (isset($_POST['tambah_artikel'])) {
    $judul = trim($_POST['judul']);
    $konten = trim($_POST['konten']);
    $url_berita = trim($_POST['url_berita']); // Ambil link berita dari form
    $status = $_POST['status'];
    $slug = generateSlug($judul);
    $gambar_nama_file = null;

    if (empty($judul)) {
        $pesan_error = "Judul artikel tidak boleh kosong.";
    } else {
        // Handle upload gambar
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == UPLOAD_ERR_OK) {
            $gambar_tmp_name = $_FILES['gambar']['tmp_name'];
            $gambar_nama_asli = basename($_FILES['gambar']['name']);
            $gambar_ext = strtolower(pathinfo($gambar_nama_asli, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($gambar_ext, $allowed_ext)) {
                $gambar_nama_file = uniqid('artikel_', true) . '.' . $gambar_ext;
                $target_file = $target_dir . $gambar_nama_file;

                if (!move_uploaded_file($gambar_tmp_name, $target_file)) {
                    $pesan_error = "Gagal mengunggah gambar.";
                    $gambar_nama_file = null; // Reset jika gagal upload
                }
            } else {
                $pesan_error = "Jenis file gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
            }
        }

        // Cek duplikasi slug
        // Menggunakan penulis_id
        $query_check_slug = "SELECT COUNT(id) FROM artikel WHERE slug = ? AND penulis_id = ?";
        $stmt_check_slug = mysqli_prepare($conn, $query_check_slug);
        mysqli_stmt_bind_param($stmt_check_slug, 'si', $slug, $penjual_id_artikel);
        mysqli_stmt_execute($stmt_check_slug);
        mysqli_stmt_bind_result($stmt_check_slug, $count_slug);
        mysqli_stmt_fetch($stmt_check_slug);
        mysqli_stmt_close($stmt_check_slug);

        if ($count_slug > 0) {
            // Jika slug sudah ada, tambahkan suffix unik
            $original_slug = $slug;
            $counter = 1;
            do {
                $slug = $original_slug . '-' . $counter++;
                $stmt_check_slug = mysqli_prepare($conn, $query_check_slug);
                mysqli_stmt_bind_param($stmt_check_slug, 'si', $slug, $penjual_id_artikel);
                mysqli_stmt_execute($stmt_check_slug);
                mysqli_stmt_bind_result($stmt_check_slug, $count_slug);
                mysqli_stmt_fetch($stmt_check_slug);
                mysqli_stmt_close($stmt_check_slug);
            } while ($count_slug > 0);
        }

        if (empty($pesan_error)) { // Lanjutkan hanya jika tidak ada error upload gambar
            // Menggunakan penulis_id, gambar_utama, url_berita dan tanggal_publikasi
            $insert_query = "INSERT INTO artikel (penulis_id, judul, slug, konten, gambar_utama, url_berita, status, tanggal_publikasi, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt_insert = mysqli_prepare($conn, $insert_query);
            // 'issssssss' -> int, string, string, string, string (gambar), string (url_berita), string (status)
            mysqli_stmt_bind_param($stmt_insert, 'issssss', $penjual_id_artikel, $judul, $slug, $konten, $gambar_nama_file, $url_berita, $status);

            if (mysqli_stmt_execute($stmt_insert)) {
                $pesan_sukses = "Artikel berhasil ditambahkan.";
                header('Location: artikel_saya.php?status=success_add');
                exit;
            } else {
                $pesan_error = "Gagal menambahkan artikel: " . mysqli_error($conn);
                error_log("Error adding artikel: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_insert); 
        }
    }
}

// Proses Edit Artikel
if (isset($_POST['edit_artikel'])) {
    $artikel_id = $_POST['artikel_id'];
    $judul = trim($_POST['judul_edit']);
    $konten = trim($_POST['konten_edit']);
    $url_berita = trim($_POST['url_berita_edit']); // Ambil link berita dari form edit
    $status = $_POST['status_edit'];
    $slug = generateSlug($judul);
    $gambar_nama_file_lama = $_POST['gambar_lama']; // Nama gambar lama
    $gambar_nama_file_baru = $gambar_nama_file_lama; // Default ke gambar lama

    if (empty($judul)) {
        $pesan_error = "Judul artikel tidak boleh kosong.";
    } else {
        // Handle upload gambar baru
        if (isset($_FILES['gambar_edit']) && $_FILES['gambar_edit']['error'] == UPLOAD_ERR_OK) {
            $gambar_tmp_name = $_FILES['gambar_edit']['tmp_name'];
            $gambar_nama_asli = basename($_FILES['gambar_edit']['name']);
            $gambar_ext = strtolower(pathinfo($gambar_nama_asli, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($gambar_ext, $allowed_ext)) {
                $gambar_nama_file_baru = uniqid('artikel_', true) . '.' . $gambar_ext;
                $target_file = $target_dir . $gambar_nama_file_baru;

                if (move_uploaded_file($gambar_tmp_name, $target_file)) {
                    // Hapus gambar lama jika ada dan berbeda dengan yang baru
                    if ($gambar_nama_file_lama && file_exists($target_dir . $gambar_nama_file_lama) && $gambar_nama_file_lama != $gambar_nama_file_baru) {
                        unlink($target_dir . $gambar_nama_file_lama);
                    }
                } else {
                    $pesan_error = "Gagal mengunggah gambar baru.";
                    $gambar_nama_file_baru = $gambar_nama_file_lama; // Kembali ke gambar lama jika gagal upload
                }
            } else {
                $pesan_error = "Jenis file gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
                $gambar_nama_file_baru = $gambar_nama_file_lama; // Kembali ke gambar lama jika jenis file salah
            }
        } elseif (isset($_POST['hapus_gambar_edit']) && $_POST['hapus_gambar_edit'] == '1') {
            // Jika checkbox hapus gambar dicentang
            if ($gambar_nama_file_lama && file_exists($target_dir . $gambar_nama_file_lama)) {
                unlink($target_dir . $gambar_dir . $gambar_nama_file_lama); // Pastikan path benar
            }
            $gambar_nama_file_baru = null;
        }


        // Cek duplikasi slug, kecuali untuk slug artikel yang sedang diedit
        // Menggunakan penulis_id
        $query_check_slug = "SELECT COUNT(id) FROM artikel WHERE slug = ? AND penulis_id = ? AND id != ?";
        $stmt_check_slug = mysqli_prepare($conn, $query_check_slug);
        mysqli_stmt_bind_param($stmt_check_slug, 'sii', $slug, $penjual_id_artikel, $artikel_id);
        mysqli_stmt_execute($stmt_check_slug);
        mysqli_stmt_bind_result($stmt_check_slug, $count_slug);
        mysqli_stmt_fetch($stmt_check_slug);
        mysqli_stmt_close($stmt_check_slug);

        if ($count_slug > 0) {
            $original_slug = $slug;
            $counter = 1;
            do {
                $slug = $original_slug . '-' . $counter++;
                $stmt_check_slug = mysqli_prepare($conn, $query_check_slug);
                mysqli_stmt_bind_param($stmt_check_slug, 'sii', $slug, $penjual_id_artikel, $artikel_id);
                mysqli_stmt_execute($stmt_check_slug);
                mysqli_stmt_bind_result($stmt_check_slug, $count_slug);
                mysqli_stmt_fetch($stmt_check_slug);
                mysqli_stmt_close($stmt_check_slug);
            } while ($count_slug > 0);
        }

        if (empty($pesan_error)) { // Lanjutkan hanya jika tidak ada error upload gambar
            // Menggunakan gambar_utama, url_berita dan penulis_id
            $update_query = "UPDATE artikel SET
                                judul = ?, slug = ?, konten = ?, gambar_utama = ?, url_berita = ?, status = ?, updated_at = NOW()
                             WHERE id = ? AND penulis_id = ?"; // Penting: Pastikan hanya penjual yang berhak yang bisa mengedit
            $stmt_update = mysqli_prepare($conn, $update_query);
            // 'ssssssii' -> string, string, string, string, string (url_berita), string (status), int, int
            mysqli_stmt_bind_param($stmt_update, 'ssssssii', $judul, $slug, $konten, $gambar_nama_file_baru, $url_berita, $status, $artikel_id, $penjual_id_artikel);

            if (mysqli_stmt_execute($stmt_update)) {
                $pesan_sukses = "Artikel berhasil diperbarui.";
                header('Location: artikel_saya.php?status=success_edit');
                exit;
            } else {
                $pesan_error = "Gagal memperbarui artikel: " . mysqli_error($conn);
                error_log("Error updating artikel: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

// Proses Hapus Artikel
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $artikel_id_to_delete = $_GET['id'];

    // Ambil nama gambar artikel sebelum dihapus
    // Menggunakan gambar_utama dan penulis_id
    $query_get_gambar = "SELECT gambar_utama FROM artikel WHERE id = ? AND penulis_id = ?";
    $stmt_get_gambar = mysqli_prepare($conn, $query_get_gambar);
    mysqli_stmt_bind_param($stmt_get_gambar, 'ii', $artikel_id_to_delete, $penjual_id_artikel);
    mysqli_stmt_execute($stmt_get_gambar);
    mysqli_stmt_bind_result($stmt_get_gambar, $gambar_file_to_delete);
    mysqli_stmt_fetch($stmt_get_gambar);
    mysqli_stmt_close($stmt_get_gambar);

    // Menggunakan penulis_id
    $delete_query = "DELETE FROM artikel WHERE id = ? AND penulis_id = ?"; // Penting: hanya hapus milik penjual ini
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, 'ii', $artikel_id_to_delete, $penjual_id_artikel);

    if (mysqli_stmt_execute($stmt_delete)) {
        if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
            // Hapus file gambar fisik jika ada
            if ($gambar_file_to_delete && file_exists($target_dir . $gambar_file_to_delete)) {
                unlink($target_dir . $gambar_file_to_delete);
            }
            $pesan_sukses = "Artikel berhasil dihapus.";
            header('Location: artikel_saya.php?status=success_delete');
            exit;
        } else {
            $pesan_error = "Artikel tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.";
        }
    } else {
        $pesan_error = "Gagal menghapus artikel: " . mysqli_error($conn);
        error_log("Error deleting artikel: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_delete);
}

// Tampilkan pesan status dari redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $pesan_sukses = "Artikel berhasil ditambahkan.";
    } elseif ($_GET['status'] == 'success_edit') {
        $pesan_sukses = "Artikel berhasil diperbarui.";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan_sukses = "Artikel berhasil dihapus.";
    }
}

// =====================================================================
// LOGIKA PAGINASI BARU
// =====================================================================

$limit = 5; // Jumlah artikel per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Hitung total artikel untuk pagination
$query_total_artikel = "SELECT COUNT(id) AS total FROM artikel WHERE penulis_id = ?";
$stmt_total_artikel = mysqli_prepare($conn, $query_total_artikel);
mysqli_stmt_bind_param($stmt_total_artikel, 'i', $penjual_id_artikel);
mysqli_stmt_execute($stmt_total_artikel);
$result_total_artikel = mysqli_stmt_get_result($stmt_total_artikel);
$row_total_artikel = mysqli_fetch_assoc($result_total_artikel);
$total_articles = $row_total_artikel['total'];
mysqli_stmt_close($stmt_total_artikel);

$total_pages = ceil($total_articles / $limit);

// Ambil artikel untuk penjual yang sedang login dengan LIMIT dan OFFSET
$artikel_list = [];
$query_artikel = "SELECT id, judul, slug, konten, gambar_utama AS gambar, url_berita, status, tanggal_publikasi AS created_at, updated_at
                     FROM artikel
                     WHERE penulis_id = ? ORDER BY tanggal_publikasi DESC LIMIT ? OFFSET ?";
$stmt_artikel = mysqli_prepare($conn, $query_artikel);
mysqli_stmt_bind_param($stmt_artikel, 'iii', $penjual_id_artikel, $limit, $offset);
mysqli_stmt_execute($stmt_artikel);
$result_artikel = mysqli_stmt_get_result($stmt_artikel);
while ($row = mysqli_fetch_assoc($result_artikel)) {
    $artikel_list[] = $row;
}
mysqli_stmt_close($stmt_artikel);


// =====================================================================
// DATA STATISTIK UNTUK SIDEBAR/FOOTER (OPSIONAL, BISA DIHAPUS JIKA TIDAK PERLU)
// =====================================================================

$query_menunggu = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'menunggu_pembayaran'";
$result_menunggu = mysqli_query($conn, $query_menunggu);
$row_menunggu = mysqli_fetch_assoc($result_menunggu);
$total_menunggu = $row_menunggu['total'] ?? 0;

$query_diproses = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'diproses'";
$result_diproses = mysqli_query($conn, $query_diproses);
$row_diproses = mysqli_fetch_assoc($result_diproses);
$total_diproses = $row_diproses['total'] ?? 0;

$query_dikirim = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'dikirim'";
$result_dikirim = mysqli_query($conn, $query_dikirim);
$row_dikirim = mysqli_fetch_assoc($result_dikirim);
$total_dikirim = $row_dikirim['total'] ?? 0;

$query_produk = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
// Menggunakan $penjual['id'] karena tabel produk mungkin masih menggunakan penjual_id
mysqli_stmt_bind_param($stmt_produk, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'] ?? 0;
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
// Menggunakan $penjual['id']
mysqli_stmt_bind_param($stmt_stok_rendah, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_stok_rendah);
$result_stok_rendah = mysqli_stmt_get_result($stmt_stok_rendah);
$row_stok_rendah = mysqli_fetch_assoc($result_stok_rendah);
$total_stok_rendah = $row_stok_rendah['total'] ?? 0;
mysqli_stmt_close($stmt_stok_rendah);

$query_pendapatan = "SELECT SUM(p.total_harga) AS total
                     FROM pesanan p
                     WHERE p.status_pesanan = 'selesai'";
$result_pendapatan = mysqli_query($conn, $query_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total'] ?: 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Artikel Saya - Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* CSS umum (dari file-file Anda sebelumnya) */
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #FBFBFB; /* Latar belakang Krem Pucat */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #1C6B4D; /* Sidebar Hijau Forest Lebih Terang */
            min-height: 100vh;
            padding: 20px 0;
            color: white;
            transition: width 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 30px;
        }
        .sidebar a {
            color: #ffffff; /* Teks di sidebar (putih) */
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #C7A77C; /* Krem Keemasan saat hover */
            color: #0A422F; /* Teks hijau hutan gelap saat hover */
            text-decoration: none;
        }
        .sidebar a.active,
        .sidebar .nav-link.active {
            background-color: #C7A77C; /* Warna aktif, Krem Keemasan */
            color: #0A422F; /* Teks hijau hutan gelap saat aktif */
            font-weight: bold;
        }

        /* --- Tambahan untuk menghilangkan warna biru pada focus/outline dan link default --- */
        .sidebar a:focus,
        .sidebar .nav-link:focus {
            outline: 2px solid #C7A77C; /* Outline Krem Keemasan saat focus */
            outline-offset: -2px; /* Untuk membuat outline tidak terlalu lebar */
        }
        .sidebar a,
        .sidebar .nav-link,
        .sidebar .submenu {
            color: #ffffff; /* Memastikan semua teks link di sidebar putih secara default */
        }
        .sidebar .submenu:hover {
            color: #0A422F; /* Teks submenu saat hover menjadi hijau gelap */
            background-color: #C7A77C; /* Background submenu saat hover */
        }
        .sidebar .submenu.active { /* Jika ada submenu yang aktif, beri warna khusus */
            color: #0A422F;
            background-color: #C7A77C;
        }

        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #EFEAD8; /* Krem Pudar untuk submenu */
        }
        .sidebar .submenu:hover {
            color: #ffffff;
        }
        .sidebar .menu-text {
            margin-left: 10px;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .sidebar.collapsed .submenu {
            display: none;
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            margin-left: 20px;
            font-size: 20px;
        }

        /* Styling for the table and form elements */
        .table {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .table thead {
            background-color: #0A422F; /* Match navbar color from dashboard */
            color: white;
        }
        .table th, .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        .btn-primary {
            background-color: #0A422F; /* Green from dashboard navbar */
            border-color: #0A422F;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1C6B4D; /* Lighter green from dashboard sidebar */
            border-color: #1C6B4D;
            color: white;
        }
        .btn-outline-success {
            color: #1C6B4D; /* Green from dashboard sidebar */
            border-color: #1C6B4D;
        }
        .btn-outline-success:hover {
            background-color: #1C6B4D;
            color: white;
        }
        .form-control, .form-control-file, .custom-select {
            border-radius: 5px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0A422F; /* Match navbar color */
            border-color: #0A422F;
        }
        .pagination .page-link {
            color: #0A422F; /* Match navbar color */
        }
        .pagination .page-link:hover {
            color: #1C6B4D; /* Lighter green from sidebar */
        }
        .btn-warning {
            background-color: #D4C29E; /* Krem Gelap - similar to waiting payment card */
            border-color: #D4C29E;
            color: #333333; /* Dark text for readability */
        }
        .btn-warning:hover {
            background-color: #C7A77C; /* Krem Keemasan - sidebar hover color */
            border-color: #C7A77C;
            color: #333333;
        }
        .btn-danger {
            background-color: #8B0000; /* Merah Gelap/Marun - Stok Rendah card */
            border-color: #8B0000;
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #A52A2A; /* slightly lighter red */
            border-color: #A52A2A;
            color: #ffffff;
        }
        .btn-info {
            background-color: #8B9B7A; /* Hijau Zaitun Pudar - Sedang Diproses card */
            border-color: #8B9B7A;
            color: #333333; /* Dark text for readability */
        }
        .btn-info:hover {
            background-color: #6B7C5E; /* slightly darker olive green */
            border-color: #6B7C5E;
            color: #333333;
        }
        .table img {
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
            .content {
                padding: 15px;
            }
        }
        .profile-section {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .profile-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }
        .profile-menu {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .profile-menu h5 {
            margin-top: 0;
        }
        .profile-menu p {
            margin: 0;
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .custom-card {
        height: 80px;
        width: 59%;
        padding: 10px 5px;
        margin: 3px 5px;
    }
    #salesChart {
        width: 100% !important;
        max-width: 600px !important;
        height: 300px !important;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark" style="background-color: #0A422F;">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Foto Profil Penjual" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Profil Penjual" width="80" height="80" style="object-fit: cover;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($penjual['nama_pemilik']); ?></h5>
                <p class="mb-0 small">Username Toko: <?= htmlspecialchars($penjual['username']); ?></p>
                <p class="mb-0 small">Email Toko: <?= htmlspecialchars($penjual['email']); ?></p>
                <p class="mb-0 small">Nama Toko: <?= htmlspecialchars($penjual['nama_toko']); ?></p>
                <p class="mb-0 small">Nomor Telepon: <?= htmlspecialchars($penjual['nomor_telepon']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                <a class="btn btn-link text-danger p-0 d-block" href="../../logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data"><div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($pengguna_data['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($penjual['nama_toko']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" class="form-control" value="<?= htmlspecialchars($penjual['nama_pemilik']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Toko</label>
                        <input type="email" name="email_penjual" class="form-control" value="<?= htmlspecialchars($penjual['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username Toko</label>
                        <input type="text" name="username_penjual" class="form-control" value="<?= htmlspecialchars($penjual['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Toko</label>
                        <textarea name="alamat" class="form-control"><?= htmlspecialchars($penjual['alamat']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="nomor_telepon" class="form-control" value="<?= htmlspecialchars($penjual['nomor_telepon']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= htmlspecialchars($penjual['tanggal_lahir']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="pria" <?= ($penjual['jenis_kelamin'] == 'pria') ? 'selected' : '' ?>>Pria</option>
                            <option value="wanita" <?= ($penjual['jenis_kelamin'] == 'wanita') ? 'selected' : '' ?>>Wanita</option>
                            <option value="lainnya" <?= ($penjual['jenis_kelamin'] == 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($penjual['foto']) : ?>
                            <img src="../../img/foto/<?= $penjual['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <h4>&nbsp;</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../dashboard_penjual.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#produkMenu" role="button" aria-expanded="false" aria-controls="produkMenu">
                    <i class="fas fa-box"></i>
                    <span class="ml-2">Produk</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="produkMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../produk/daftar/daftar_produk.php">Daftar Produk</a>
                        </li>
                        <li class="nav-item">
                            </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../produk/stok/stok.php">Stok / Inventaris</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../produk/varian/varian_produk.php">Varian Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../produk/kategori/kategori_produk.php">Kategori Produk</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pesananMenu" role="button" aria-expanded="true" aria-controls="pesananMenu">
                    <i class="fas fa-list-check"></i>
                    <span class="ml-2">Pesanan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pesananMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pesanan/daftar/pesanan.php">Daftar Pesanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pesanan/pengiriman/pengiriman.php">Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pesanan/pengembalian/pengembalian_barang.php">Pengembalian Barang</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pembayaranMenu" role="button" aria-expanded="false" aria-controls="pembayaranMenu">
                    <i class="fas fa-wallet"></i>
                    <span class="ml-2">Pembayaran</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pembayaranMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pembayaran/riwayat/pembayaran.php">Riwayat Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pembayaran/penarikan/penarikan.php">Penarikan Dana</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pembayaran/catatan/transaksi_lain.php">Catatan Transaksi Lain</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pembayaran/metode/metode_pembayaran.php">Metode Pembayaran</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#laporanMenu" role="button" aria-expanded="false" aria-controls="laporanMenu">
                    <i class="fas fa-chart-line"></i>
                    <span class="ml-2">Laporan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="laporanMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../laporan/penjualan/laporan_penjualan.php">Laporan Penjualan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../laporan/stok/laporan_stok.php">Laporan Stok</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../laporan/keuangan/laporan_keuangan.php">Laporan Keuangan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#promosiMenu" role="button" aria-expanded="false" aria-controls="promosiMenu">
                    <i class="fas fa-bullhorn"></i>
                    <span class="ml-2">Promosi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="promosiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../promosi/diskon.php">Diskon</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#tokoMenu" role="button" aria-expanded="false" aria-controls="tokoMenu">
                    <i class="fas fa-store"></i>
                    <span class="ml-2">Toko Saya</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="tokoMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../toko/profil/profil_toko.php">Profil Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../toko/pengaturan/pengaturan_toko.php">Pengaturan Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../toko/pengiriman/pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../toko/pembayaran/pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../toko/unit/unit_usaha_saya.php">Unit Usaha Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#komunikasiMenu" role="button" aria-expanded="false" aria-controls="komunikasiMenu">
                    <i class="fas fa-envelope"></i>
                    <span class="ml-2">Komunikasi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="komunikasiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../penjual/komunikasi/ulasan/ulasan.php">Ulasan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#artikelMenu" role="button" aria-expanded="false" aria-controls="artikelMenu">
                    <i class="fas fa-newspaper"></i>
                    <span class="ml-2">Artikel</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="artikelMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="../../artikel/artikel_saya.php">Artikel Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="ml-2">Logout</span>
        </a>
    </div>

    <div class="content">
        <h1>Artikel Saya</h1>
        <hr>

        <?php if ($pesan_sukses) : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($pesan_sukses) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($pesan_error) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card p-4">
            <h4 class="mb-4">Daftar Artikel</h4>
            <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahArtikelModal">Tambah Artikel Baru</button>

            <?php if (empty($artikel_list)) : ?>
                <div class="alert alert-info">Anda belum memiliki artikel yang terdaftar.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul</th>
                                <th>Gambar</th>
                                <th>Link Berita</th>
                                <th>Status</th>
                                <th>Tanggal Publikasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = ($page - 1) * $limit + 1; // Sesuaikan nomor urut dengan halaman ?>
                            <?php foreach ($artikel_list as $artikel) : ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($artikel['judul']) ?></td>
                                    <td>
                                        <?php if ($artikel['gambar']) : // 'gambar' karena sudah di-alias dari 'gambar_utama' ?>
                                            <img src="../../img/artikel/<?= htmlspecialchars($artikel['gambar']) ?>" alt="Gambar Artikel" width="100">
                                        <?php else : ?>
                                            Tidak Ada
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($artikel['url_berita']) : ?>
                                            <a href="<?= htmlspecialchars($artikel['url_berita']) ?>" target="_blank">Lihat Berita</a>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($artikel['status'] == 'publikasi') : // 'publikasi' sesuai enum di tabel ?>
                                            <span class="badge badge-success">Diterbitkan</span>
                                        <?php else : ?>
                                            <span class="badge badge-secondary">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y H:i', strtotime($artikel['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning mb-1"
                                            data-toggle="modal"
                                            data-target="#editArtikelModal"
                                            data-id="<?= $artikel['id'] ?>"
                                            data-judul="<?= htmlspecialchars($artikel['judul']) ?>"
                                            data-konten="<?= htmlspecialchars($artikel['konten']) ?>"
                                            data-gambar="<?= htmlspecialchars($artikel['gambar']) ?>"
                                            data-url-berita="<?= htmlspecialchars($artikel['url_berita']) ?>"
                                            data-status="<?= htmlspecialchars($artikel['status']) ?>">
                                            Edit
                                        </button>
                                        <a href="artikel_saya.php?action=hapus&id=<?= $artikel['id'] ?>"
                                           class="btn btn-sm btn-danger mb-1"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus artikel ini? Aksi ini tidak dapat dibatalkan.');">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahArtikelModal" tabindex="-1" aria-labelledby="tambahArtikelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahArtikelModalLabel">Tambah Artikel Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="judul">Judul Artikel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" required>
                    </div>
                    <div class="form-group">
                        <label for="konten">Konten Artikel</label>
                        <textarea class="form-control" id="konten" name="konten" rows="8"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="gambar">Gambar Artikel (opsional)</label>
                        <input type="file" class="form-control-file" id="gambar" name="gambar" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label for="url_berita">Link Berita (URL)</label>
                        <input type="url" class="form-control" id="url_berita" name="url_berita" placeholder="Contoh: https://example.com/berita-terbaru">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="draft">Draft</option>
                            <option value="publikasi">Diterbitkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_artikel" class="btn btn-primary">Simpan Artikel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editArtikelModal" tabindex="-1" aria-labelledby="editArtikelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editArtikelModalLabel">Edit Artikel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="artikel_id" id="artikel_id_edit">
                    <input type="hidden" name="gambar_lama" id="gambar_lama_edit">
                    <div class="form-group">
                        <label for="judul_edit">Judul Artikel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul_edit" name="judul_edit" required>
                    </div>
                    <div class="form-group">
                        <label for="konten_edit">Konten Artikel</label>
                        <textarea class="form-control" id="konten_edit" name="konten_edit" rows="8"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Gambar Saat Ini:</label><br>
                        <div id="gambar_preview_edit">
                            </div>
                        <input type="file" class="form-control-file mt-2" id="gambar_edit" name="gambar_edit" accept="image/*">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" value="1" id="hapus_gambar_edit" name="hapus_gambar_edit">
                            <label class="form-check-label" for="hapus_gambar_edit">Hapus Gambar Saat Ini</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="url_berita_edit">Link Berita (URL)</label>
                        <input type="url" class="form-control" id="url_berita_edit" name="url_berita_edit" placeholder="Contoh: https://example.com/berita-terbaru">
                    </div>
                    <div class="form-group">
                        <label for="status_edit">Status</label>
                        <select class="form-control" id="status_edit" name="status_edit">
                            <option value="draft">Draft</option>
                            <option value="publikasi">Diterbitkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_artikel" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    $(document).ready(function() {
        // Ketika modal edit muncul, isi form dengan data yang sudah ada
        $('#editArtikelModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button yang memicu modal
            var id = button.data('id');
            var judul = button.data('judul');
            var konten = button.data('konten');
            var gambar = button.data('gambar'); // Sudah di-alias dari gambar_utama
            var urlBerita = button.data('url-berita'); // Ambil data URL berita
            var status = button.data('status');

            var modal = $(this);
            modal.find('#artikel_id_edit').val(id);
            modal.find('#judul_edit').val(judul);
            modal.find('#konten_edit').val(konten);
            modal.find('#gambar_lama_edit').val(gambar);
            modal.find('#url_berita_edit').val(urlBerita); // Isi input URL berita
            modal.find('#status_edit').val(status);

            // Tampilkan gambar saat ini di modal edit
            var gambarPreviewDiv = modal.find('#gambar_preview_edit');
            gambarPreviewDiv.empty(); // Kosongkan preview sebelumnya
            if (gambar) {
                gambarPreviewDiv.append('<img src="../../img/artikel/' + gambar + '" alt="Gambar Artikel" width="150" class="img-thumbnail mb-2">');
            } else {
                gambarPreviewDiv.append('<p class="text-muted">Tidak ada gambar saat ini.</p>');
            }

            // Reset checkbox hapus gambar
            modal.find('#hapus_gambar_edit').prop('checked', false);
        });

        // Event listener untuk checkbox "Hapus Gambar Saat Ini"
        $('#hapus_gambar_edit').change(function() {
            if ($(this).is(':checked')) {
                // Sembunyikan input file jika ingin menghapus gambar
                $('#gambar_edit').hide();
            } else {
                // Tampilkan kembali input file
                $('#gambar_edit').show();
            }
        });
    });
</script>

</body>
</html>