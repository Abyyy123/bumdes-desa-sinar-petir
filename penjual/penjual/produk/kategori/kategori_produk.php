<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}
$user_id = $_SESSION['pengguna_id'];

// Inisialisasi pesan
$pesan = "";

// PAGINATION KATEGORI
$batas = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$previous = $halaman - 1;
$next = $halaman + 1;

// Ambil kata kunci pencarian dari parameter GET
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$keyword_param = "%" . $keyword . "%";

// Query untuk menghitung total data kategori produk (dengan filter keyword)
$query_total = "SELECT COUNT(id) AS total FROM kategori_produk WHERE nama_kategori LIKE ?";
$stmt_total = mysqli_prepare($conn, $query_total);
mysqli_stmt_bind_param($stmt_total, 's', $keyword_param);
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$data_total = mysqli_fetch_assoc($result_total);
$total_data = $data_total['total'];
$total_halaman = ceil($total_data / $batas);
mysqli_stmt_close($stmt_total);

// Query untuk mengambil data kategori produk dengan pagination (dengan filter keyword)
$sql = "SELECT id, nama_kategori, deskripsi, slug, gambar, parent_id, created_at, updated_at
        FROM kategori_produk
        WHERE nama_kategori LIKE ?
        ORDER BY nama_kategori ASC
        LIMIT ?, ?";
$stmt_kategori = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt_kategori, 'sii', $keyword_param, $halaman_awal, $batas);
mysqli_stmt_execute($stmt_kategori);
$result_kategori = mysqli_stmt_get_result($stmt_kategori);
$daftar_kategori = mysqli_fetch_all($result_kategori, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_kategori);

// Proses Tambah Kategori Produk
if (isset($_POST['tambah_kategori'])) {
    $nama_kategori = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    $slug = strtolower(str_replace(' ', '-', $nama_kategori));
    $parent_id = $_POST['parent_id'] == 0 ? NULL : $_POST['parent_id'];
    $parent_kategori_baru = trim($_POST['parent_kategori_baru']);
    $gambar = "";

    // Upload gambar kategori jika ada
    $upload_dir = '../../../img/kategori/';
    if (!empty($_FILES['gambar_kategori']['name'])) {
        $gambar_name = uniqid() . '_' . basename($_FILES['gambar_kategori']['name']);
        $target = $upload_dir . $gambar_name;
        $ext = strtolower(pathinfo($gambar_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'avif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['gambar_kategori']['tmp_name'], $target)) {
                $gambar = $gambar_name;
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengupload gambar kategori.</div>";
            }
        } else {
            $pesan = "<div class='alert alert-warning'>Ekstensi file gambar kategori tidak diizinkan.</div>";
        }
    }

    if (!empty($parent_kategori_baru)) {
        // Cek apakah parent kategori baru sudah ada
        $query_cek_parent = "SELECT id FROM kategori_produk WHERE nama_kategori = ?";
        $stmt_cek_parent = mysqli_prepare($conn, $query_cek_parent);
        mysqli_stmt_bind_param($stmt_cek_parent, 's', $parent_kategori_baru);
        mysqli_stmt_execute($stmt_cek_parent);
        $result_cek_parent = mysqli_stmt_get_result($stmt_cek_parent);
        $data_cek_parent = mysqli_fetch_assoc($result_cek_parent);
        mysqli_stmt_close($stmt_cek_parent);

        if ($data_cek_parent) {
            $parent_id = $data_cek_parent['id'];
        } else {
            // Tambahkan parent kategori baru
            $slug_parent = strtolower(str_replace(' ', '-', $parent_kategori_baru));
            $query_tambah_parent = "INSERT INTO kategori_produk (nama_kategori, slug) VALUES (?, ?)";
            $stmt_tambah_parent = mysqli_prepare($conn, $query_tambah_parent);
            mysqli_stmt_bind_param($stmt_tambah_parent, 'ss', $parent_kategori_baru, $slug_parent);
            if (mysqli_stmt_execute($stmt_tambah_parent)) {
                $parent_id = mysqli_insert_id($conn);
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan parent kategori baru.</div>";
            }
            mysqli_stmt_close($stmt_tambah_parent);
        }
    }

    if (!empty($nama_kategori)) {
        $query_cek = "SELECT id FROM kategori_produk WHERE nama_kategori = ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 's', $nama_kategori);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Nama kategori sudah ada.</div>";
        } else {
            $query_tambah = "INSERT INTO kategori_produk (nama_kategori, deskripsi, slug, gambar, parent_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_tambah = mysqli_prepare($conn, $query_tambah);
            mysqli_stmt_bind_param($stmt_tambah, 'ssssi', $nama_kategori, $deskripsi, $slug, $gambar, $parent_id);
            if (mysqli_stmt_execute($stmt_tambah)) {
                $pesan = "<div class='alert alert-success'>Kategori produk berhasil ditambahkan.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan kategori produk.</div>";
            }
            mysqli_stmt_close($stmt_tambah);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama kategori tidak boleh kosong.</div>";
    }
}

// Proses Edit Kategori Produk
if (isset($_POST['edit_kategori'])) {
    $id_kategori_edit = $_POST['id_kategori_edit'];
    $nama_kategori_edit = trim($_POST['nama_kategori_edit']);
    $deskripsi_edit = trim($_POST['deskripsi_edit']);
    $slug_edit = strtolower(str_replace(' ', '-', $nama_kategori_edit));
    $parent_id_edit = $_POST['parent_id_edit'] == 0 ? NULL : $_POST['parent_id_edit'];
    $parent_kategori_baru_edit = trim($_POST['parent_kategori_baru_edit']);
    $gambar_lama_edit = isset($_POST['gambar_lama_edit']) ? $_POST['gambar_lama_edit'] : ''; // Tambahkan pemeriksaan isset
    $gambar_baru_edit = $gambar_lama_edit;
    $upload_error = false;

    $upload_dir = '../../../img/kategori/';
    if (!empty($_FILES['gambar_edit']['name'])) {
        $gambar_name = uniqid() . '_' . basename($_FILES['gambar_edit']['name']);
        $target = $upload_dir . $gambar_name;
        $ext = strtolower(pathinfo($gambar_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'avif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['gambar_edit']['tmp_name'], $target)) {
                $gambar_baru_edit = $gambar_name;
                if (!empty($gambar_lama_edit) && $gambar_lama_edit != 'default.png' && file_exists($upload_dir . $gambar_lama_edit)) {
                    unlink($upload_dir . $gambar_lama_edit);
                }
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengupload gambar kategori.</div>";
                $upload_error = true;
            }
        } else {
            $pesan = "<div class='alert alert-warning'>Ekstensi file gambar kategori tidak diizinkan.</div>";
            $upload_error = true;
        }
    }

    if (!empty($parent_kategori_baru_edit)) {
        // Cek apakah parent kategori baru sudah ada
        $query_cek_parent_edit = "SELECT id FROM kategori_produk WHERE nama_kategori = ?";
        $stmt_cek_parent_edit = mysqli_prepare($conn, $query_cek_parent_edit);
        mysqli_stmt_bind_param($stmt_cek_parent_edit, 's', $parent_kategori_baru_edit);
        mysqli_stmt_execute($stmt_cek_parent_edit);
        $result_cek_parent_edit = mysqli_stmt_get_result($stmt_cek_parent_edit);
        $data_cek_parent_edit = mysqli_fetch_assoc($result_cek_parent_edit);
        mysqli_stmt_close($stmt_cek_parent_edit);

        if ($data_cek_parent_edit) {
            $parent_id_edit = $data_cek_parent_edit['id'];
        } else {
            // Tambahkan parent kategori baru
            $slug_parent_edit = strtolower(str_replace(' ', '-', $parent_kategori_baru_edit));
            $query_tambah_parent_edit = "INSERT INTO kategori_produk (nama_kategori, slug) VALUES (?, ?)";
            $stmt_tambah_parent_edit = mysqli_prepare($conn, $query_tambah_parent_edit);
            mysqli_stmt_bind_param($stmt_tambah_parent_edit, 'ss', $parent_kategori_baru_edit, $slug_parent_edit);
            if (mysqli_stmt_execute($stmt_tambah_parent_edit)) {
                $parent_id_edit = mysqli_insert_id($conn);
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan parent kategori baru.</div>";
            }
            mysqli_stmt_close($stmt_tambah_parent_edit);
        }
    }

    if (!empty($nama_kategori_edit) && !$upload_error) {
        $query_cek = "SELECT id FROM kategori_produk WHERE nama_kategori = ? AND id != ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 'si', $nama_kategori_edit, $id_kategori_edit);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Nama kategori sudah ada.</div>";
        } else {
            $query_update = "UPDATE kategori_produk SET nama_kategori = ?, deskripsi = ?, slug = ?, gambar = ?, parent_id = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $query_update);
            mysqli_stmt_bind_param($stmt_update, 'ssssii', $nama_kategori_edit, $deskripsi_edit, $slug_edit, $gambar_baru_edit, $parent_id_edit, $id_kategori_edit);
            if (mysqli_stmt_execute($stmt_update)) {
                $pesan = "<div class='alert alert-success'>Kategori produk berhasil diubah.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengubah kategori produk.</div>";
            }
            mysqli_stmt_close($stmt_update);
        }
        mysqli_stmt_close($stmt_cek);
    } else if (empty($nama_kategori_edit)) {
        $pesan = "<div class='alert alert-warning'>Nama kategori tidak boleh kosong.</div>";
    }
}

// Proses Hapus Kategori Produk
if (isset($_GET['hapus_kategori'])) {
    $id_kategori_hapus = $_GET['hapus_kategori'];
    // Cek apakah kategori memiliki produk terkait
    $query_cek_produk = "SELECT id FROM produk WHERE kategori_id = ?";
    $stmt_cek_produk = mysqli_prepare($conn, $query_cek_produk);
    mysqli_stmt_bind_param($stmt_cek_produk, 'i', $id_kategori_hapus);
    mysqli_stmt_execute($stmt_cek_produk);
    mysqli_stmt_store_result($stmt_cek_produk);
    if (mysqli_stmt_num_rows($stmt_cek_produk) > 0) {
        $pesan = "<div class='alert alert-danger'>Kategori tidak dapat dihapus karena masih memiliki produk terkait.</div>";
    } else {
        // Cek apakah kategori memiliki sub-kategori
        $query_cek_subkategori = "SELECT id FROM kategori_produk WHERE parent_id = ?";
        $stmt_cek_subkategori = mysqli_prepare($conn, $query_cek_subkategori);
        mysqli_stmt_bind_param($stmt_cek_subkategori, 'i', $id_kategori_hapus);
        mysqli_stmt_execute($stmt_cek_subkategori);
        mysqli_stmt_store_result($stmt_cek_subkategori);
        if (mysqli_stmt_num_rows($stmt_cek_subkategori) > 0) {
            $pesan = "<div class='alert alert-danger'>Kategori tidak dapat dihapus karena masih memiliki sub-kategori.</div>";
        } else {
            $query_hapus = "DELETE FROM kategori_produk WHERE id = ?";
            $stmt_hapus = mysqli_prepare($conn, $query_hapus);
            mysqli_stmt_bind_param($stmt_hapus, 'i', $id_kategori_hapus);
            if (mysqli_stmt_execute($stmt_hapus)) {
                $pesan = "<div class='alert alert-success'>Kategori produk berhasil dihapus.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menghapus kategori produk.</div>";
            }
            mysqli_stmt_close($stmt_hapus);
        }
        mysqli_stmt_close($stmt_cek_subkategori);
    }
    mysqli_stmt_close($stmt_cek_produk);
}

// Ambil semua data kategori untuk dropdown parent
$query_parent = "SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori ASC";
$result_parent = mysqli_query($conn, $query_parent);
$daftar_parent_kategori = mysqli_fetch_all($result_parent, MYSQLI_ASSOC);

// Ambil data pengguna (untuk mendapatkan nama lengkap) - Bagian yang dipertahankan
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);
mysqli_stmt_close($stmt_pengguna);

if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}

// Bagian kode penjual dan update profil (dipertahankan tanpa perubahan signifikan)
$query_penjual = "SELECT nama_toko, nama_pemilik, email, username, foto, alamat, nomor_telepon, tanggal_lahir, jenis_kelamin FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);
if (!$penjual) {
    echo "<script>alert('Data penjual tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}
// Proses update profil - Bagian yang dipertahankan
if (isset($_POST['simpan'])) {
    $nama_lengkap = $_POST['nama_lengkap'];
    $nama_toko = $_POST['nama_toko'];
    $nama_pemilik = $_POST['nama_pemilik'];
    $email_penjual = $_POST['email_penjual'];
    $username_penjual = $_POST['username_penjual'];
    $alamat = $_POST['alamat'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $foto_lama = $penjual['foto'];
    $foto_baru = $foto_lama;
    $upload_dir = '../../../img/foto/';
    if (!empty($_FILES['foto']['name'])) {
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
    $update_penjual = "UPDATE penjual SET nama_toko=?, nama_pemilik=?, email=?, username=?, foto=?, alamat=?, nomor_telepon=?, tanggal_lahir=?, jenis_kelamin=?, updated_at=NOW() WHERE pengguna_id=?";
    $stmt_update_penjual = mysqli_prepare($conn, $update_penjual);
    mysqli_stmt_bind_param($stmt_update_penjual, 'sssssssssi', $nama_toko, $nama_pemilik, $email_penjual, $username_penjual, $foto_baru, $alamat, $nomor_telepon, $tanggal_lahir, $jenis_kelamin, $user_id);
    mysqli_stmt_execute($stmt_update_penjual);
    $update_pengguna = "UPDATE pengguna SET nama=? WHERE id=?";
    $stmt_update_pengguna = mysqli_prepare($conn, $update_pengguna);
    mysqli_stmt_bind_param($stmt_update_pengguna, 'si', $nama_lengkap, $user_id);
    mysqli_stmt_execute($stmt_update_pengguna);
    if (mysqli_stmt_affected_rows($stmt_update_penjual) > 0 || mysqli_stmt_affected_rows($stmt_update_pengguna) > 0) {
        echo "<script>alert('Profil berhasil diperbarui.'); window.location.href='../../dashboard_penjual.php';</script>";
    } else {
        echo "<script>alert('Tidak ada perubahan pada profil.'); window.location.href='../../dashboard_penjual.php';</script>";
    }
    mysqli_stmt_close($stmt_update_penjual);
    mysqli_stmt_close($stmt_update_pengguna);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f2f6fc;
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
            background-color: #1f2937;
            min-height: 100vh;
            padding: 20px 0;
            color: white;
            transition: width 0.3s ease;
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
            color: #dcdcdc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #495057;
            color: #ffffff;
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #cfcfcf;
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

        .card-dashboard {
            border-radius: 10px;
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
        height: 80px;        /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;    /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;      /* jarak luar card */
    }
    #salesChart {
        width: 100% !important;
        max-width: 600px !important;
        height: 300px !important;
    }
    .aksi-kontainer {
    display: flex;
    align-items: center; /* Agar ikon dan teks sejajar vertikal */
    gap: 5px; /* Jarak antar tombol */
}

.btn-aksi {
    display: flex;
    align-items: center; /* Agar ikon dan teks di dalam tombol sejajar vertikal */
    gap: 5px; /* Jarak antara ikon dan teks di dalam tombol */
    padding: 5px 10px; /* Sesuaikan padding sesuai keinginan */
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px; /* Sesuaikan ukuran font */
}

.btn-warning {
    background-color: #ffc107; /* Warna kuning untuk Edit */
    color: #212529; /* Warna teks untuk Edit */
}

.btn-danger {
    background-color: #dc3545; /* Warna merah untuk Hapus */
    color: #fff; /* Warna teks untuk Hapus */
}

.btn-warning:hover, .btn-danger:hover {
    opacity: 0.9; /* Efek hover (opsional) */
}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Foto Profil Penjual" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Profil Penjual" width="80" height="80" style="object-fit: cover;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($penjual['nama_pemilik']); ?></h5>
                <p class="mb-0 small">Username Toko: <?= htmlspecialchars($penjual['username']); ?></p>
                <p class="mb-0 small">Email Toko: <?= htmlspecialchars($penjual['email']); ?></p>
                <p class="mb-0 small">Nama Toko: <?= htmlspecialchars($penjual['nama_toko']); ?></p>
                <p class="mb-0 small">Nomor Telepon: <?= htmlspecialchars($penjual['nomor_telepon']); ?></p>
                <div class="dropdown-divider my-2"></div>
    <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
    <a class="btn btn-link text-danger p-0 d-block" href="../../../logout.php">Logout</a>
    </div>
    </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
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
                            <img src="../../../img/foto/<?= $penjual['foto'] ?>" width="80" class="mb-2 rounded"><br>
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
                <a class="nav-link" href="../../dashboard_penjual.php">
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
                <div class="collapse show" id="produkMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../daftar/daftar_produk.php">Daftar Produk</a>
                        </li>
                        <li class="nav-item">
                            <!-- <a class="nav-link submenu" href="tambah_produk.php">Tambah Produk</a> -->
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../stok/stok.php">Stok / Inventaris</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../varian/varian_produk.php">Varian Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="kategori_produk.php">Kategori Produk</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pesananMenu" role="button" aria-expanded="false" aria-controls="pesananMenu">
                    <i class="fas fa-list-check"></i>
                    <span class="ml-2">Pesanan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pesananMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/daftar/pesanan.php">Daftar Pesanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengiriman/pengiriman.php">Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengembalian/pengembalian_barang.php">Pengembalian Barang</a>
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
                            <a class="nav-link submenu" href="../../pembayaran/riwayat/pembayaran.php">Riwayat Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/penarikan/penarikan.php">Penarikan Dana</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/catatan/transaksi_lain.php">Catatan Transaksi Lain</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/metode/metode_pembayaran.php">Metode Pembayaran</a>
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
                            <a class="nav-link submenu" href="../../laporan/penjualan/laporan_penjualan.php">Laporan Penjualan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/stok/laporan_stok.php">Laporan Stok</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/keuangan/laporan_keuangan.php">Laporan Keuangan</a>
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
                            <a class="nav-link submenu" href="../../promosi/diskon.php">Diskon</a>
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
                            <a class="nav-link submenu" href="../../toko/profil/profil_toko.php">Profil Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengaturan/pengaturan_toko.php">Pengaturan Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengiriman/pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pembayaran/pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/unit/unit_usaha_saya.php">Unit Usaha Saya</a>
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
                            <a class="nav-link submenu" href="../../komunikasi/pesan/pesan_masuk.php">Pesan Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../komunikasi/ulasan/ulasan.php">Ulasan</a>
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
                <div class="collapse" id="artikelMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../artikel/artikel_saya.php">Artikel Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#akunMenu" role="button" aria-expanded="false" aria-controls="akunMenu">
                    <i class="fas fa-user"></i>
                    <span class="ml-2">Akun</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="akunMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../akun/profil.php">Profil</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <a class="nav-link text-danger" href="../../../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="ml-2">Logout</span>
        </a>
    </div>
<div class="content">
    <h2>Kategori Produk</h2>
    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahKategoriModal">
            <i class="fas fa-plus"></i> Tambah Kategori
        </button>
    </div>

    <form class="form-inline mb-3" method="get">
        <input class="form-control mr-sm-2" type="search" placeholder="Cari Kategori" aria-label="Search" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>">
        <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Cari</button>
    </form>

    <?php if (!empty($pesan)) : ?>
        <div class="alert alert-success"><?= $pesan; ?></div>
    <?php endif; ?>

    <?php
    if (!empty($daftar_kategori)) :
    ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th>Slug</th>
                    <th>Gambar</th>
                    <th>Parent Kategori</th>
                    <th>Dibuat</th>
                    <th>Diperbarui</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_kategori as $kategori) : ?>
                    <tr>
                        <td><?= htmlspecialchars($kategori['id']); ?></td>
                        <td><?= htmlspecialchars($kategori['nama_kategori']); ?></td>
                        <td><?= htmlspecialchars($kategori['deskripsi']); ?></td>
                        <td><?= htmlspecialchars($kategori['slug']); ?></td>
                        <td>
                            <?php if (!empty($kategori['gambar'])) : ?>
                                <img src="../../../img/kategori/<?= htmlspecialchars($kategori['gambar']); ?>" alt="Gambar Kategori" width="50">
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($kategori['parent_id'] != null) {
                                $query_parent_nama = "SELECT nama_kategori FROM kategori_produk WHERE id = ?";
                                $stmt_parent_nama = mysqli_prepare($conn, $query_parent_nama);
                                mysqli_stmt_bind_param($stmt_parent_nama, 'i', $kategori['parent_id']);
                                mysqli_stmt_execute($stmt_parent_nama);
                                $result_parent_nama = mysqli_stmt_get_result($stmt_parent_nama);
                                $parent_data = mysqli_fetch_assoc($result_parent_nama);
                                mysqli_stmt_close($stmt_parent_nama);
                                echo htmlspecialchars($parent_data['nama_kategori']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($kategori['created_at']); ?></td>
                        <td><?= htmlspecialchars($kategori['updated_at']); ?></td>
                        <td>
                            <div class="aksi-kontainer">
                                <button type="button" class="btn-aksi btn-warning" data-toggle="modal" data-target="#editKategoriModal<?= htmlspecialchars($kategori['id']); ?>">
                                    <i class="fas fa-edit text-secondary"></i> Edit
                                </button>
                                <a href="kategori_produk.php?hapus_kategori=<?= htmlspecialchars($kategori['id']); ?>" class="btn-aksi btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </div>
                        </td>
                    </tr>
                    <div class="modal fade" id="editKategoriModal<?= htmlspecialchars($kategori['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="editKategoriModalLabel<?= htmlspecialchars($kategori['id']); ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editKategoriModalLabel<?= htmlspecialchars($kategori['id']); ?>">Edit Kategori Produk</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="nama_kategori_edit">Nama Kategori:</label>
                                            <input type="text" class="form-control" id="nama_kategori_edit" name="nama_kategori_edit" value="<?= htmlspecialchars($kategori['nama_kategori']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="deskripsi_edit">Deskripsi:</label>
                                            <textarea class="form-control" id="deskripsi_edit" name="deskripsi_edit"><?= htmlspecialchars($kategori['deskripsi']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_id_edit">Parent Kategori:</label>
                                            <select class="form-control" id="parent_id_edit" name="parent_id_edit">
                                                <option value="0">-- Tidak Ada Parent --</option>
                                                <?php foreach ($daftar_parent_kategori as $parent) : ?>
                                                    <?php if ($parent['id'] != $kategori['id']) : ?>
                                                        <option value="<?= htmlspecialchars($parent['id']); ?>" <?= ($kategori['parent_id'] == $parent['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($parent['nama_kategori']); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Pilih jika ingin mengubah parent kategori.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="parent_kategori_baru_edit">Atau buat parent kategori baru:</label>
                                            <input type="text" class="form-control" id="parent_kategori_baru_edit" name="parent_kategori_baru_edit" placeholder="Nama parent kategori baru">
                                            <small class="form-text text-muted">Isi jika parent kategori yang diinginkan belum tersedia.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="gambar_edit">Gambar Kategori:</label><br>
                                            <?php if (!empty($kategori['gambar'])) : ?>
                                                <img src="../../../img/kategori/<?= htmlspecialchars($kategori['gambar']); ?>" alt="Gambar Kategori Lama" width="100" class="mb-2">
                                                <input type="hidden" name="gambar_lama_edit" value="<?= htmlspecialchars($kategori['gambar']); ?>">
                                            <?php endif; ?>
                                            <input type="file" class="form-control-file" id="gambar_edit" name="gambar_edit">
                                            <small class="form-text text-muted">Format yang diizinkan: jpg, jpeg, png, gif, avif.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="id_kategori_edit" value="<?= htmlspecialchars($kategori['id']); ?>">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary" name="edit_kategori">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>

        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($halaman > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?halaman=<?= $previous; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php else : ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_halaman; $i++) : ?>
                    <li class="page-item <?= ($halaman == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?halaman=<?= $i; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($halaman < $total_halaman) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?halaman=<?= $next; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php else : ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php else : ?>
        <div class="alert alert-warning">
            <?php if (!empty($keyword)) : ?>
                Tidak ada kategori produk yang ditemukan dengan kata kunci "<?= htmlspecialchars($keyword); ?>".
            <?php else : ?>
                Belum ada data kategori produk.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<div class="modal fade" id="tambahKategoriModal" tabindex="-1" role="dialog" aria-labelledby="tambahKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahKategoriModalLabel">Tambah Kategori Produk Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_kategori">Nama Kategori:</label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi:</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="parent_id">Parent Kategori:</label>
                        <select class="form-control" id="parent_id" name="parent_id">
                            <option value="0">-- Tidak Ada Parent --</option>
                            <?php foreach ($daftar_parent_kategori as $parent) : ?>
                                <option value="<?= htmlspecialchars($parent['id']); ?>"><?= htmlspecialchars($parent['nama_kategori']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Pilih jika ini adalah sub-kategori.</small>
                    </div>
                    <div class="form-group">
                        <label for="parent_kategori_baru">Atau buat parent kategori baru:</label>
                        <input type="text" class="form-control" id="parent_kategori_baru" name="parent_kategori_baru" placeholder="Nama parent kategori baru">
                        <small class="form-text text-muted">Isi jika parent kategori belum tersedia di daftar.</small>
                    </div>
                    <div class="form-group">
                        <label for="gambar_kategori">Gambar Kategori:</label>
                        <input type="file" class="form-control-file" id="gambar_kategori" name="gambar_kategori">
                        <small class="form-text text-muted">Format yang diizinkan: jpg, jpeg, png, gif, avif.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="tambah_kategori">Simpan Kategori</button>
                </div>
            </form>
        </div>
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

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    $(document).ready(function() {
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('active');
            $('#content').toggleClass('active');
        });

        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });
    });
</script>

</body>
</html>