<?php
session_start();
include('../../koneksi/koneksi.php'); // Sesuaikan path ini

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];

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

// Ambil data penjual (untuk mendapatkan penjual_id)
$query_penjual = "SELECT pengguna_id, nama_toko, nama_pemilik, email, username, foto, nomor_telepon, alamat, kebijakan_pengiriman FROM penjual WHERE pengguna_id = ?";
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

$penjual_id = $penjual['pengguna_id']; 

$pesan_sukses = '';
$pesan_error = '';

if (isset($_POST['tambah_edit_diskon'])) {
    $diskon_id = $_POST['diskon_id'] ?? null;
    $kode_diskon = trim($_POST['kode_diskon']);
    $nama_diskon = trim($_POST['nama']); // Menggunakan 'nama' sesuai DB Anda
    $jenis_diskon = $_POST['jenis_diskon'];
    $nilai_diskon = $_POST['nilai_diskon'];

    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_berakhir = $_POST['tanggal_berakhir'];

    // Validasi input
    if (empty($kode_diskon) || empty($nama_diskon) || empty($jenis_diskon) || empty($nilai_diskon) || empty($tanggal_mulai) || empty($tanggal_berakhir)) {
        $pesan_error = "Semua kolom dengan tanda (*) harus diisi.";
    } elseif ($nilai_diskon <= 0) {
        $pesan_error = "Nilai diskon harus lebih dari 0.";
    } elseif (strtotime($tanggal_mulai) > strtotime($tanggal_berakhir)) { // Gunakan > karena tanggal_berakhir bisa sama dengan tanggal_mulai
        $pesan_error = "Tanggal berakhir harus setelah atau sama dengan tanggal mulai.";
    } else {
        if ($diskon_id) {
            // Edit Diskon
            // PENJUAL_ID DIKOMENTARI KARENA TIDAK ADA DI DB ANDA
            $query = "UPDATE diskon SET kode_diskon = ?, nama = ?, jenis_diskon = ?, nilai_diskon = ?, tanggal_mulai = ?, tanggal_berakhir = ? WHERE id = ?"; // Hapus penjual_id dari WHERE
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssdssi', $kode_diskon, $nama_diskon, $jenis_diskon, $nilai_diskon, $tanggal_mulai, $tanggal_berakhir, $diskon_id);
            $action_type = "edit";
        } else {
            $query = "INSERT INTO diskon (kode_diskon, nama, jenis_diskon, nilai_diskon, tanggal_mulai, tanggal_berakhir) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            // Jika penjual_id sudah ditambahkan, uncomment: mysqli_stmt_bind_param($stmt, 'isssdss', $penjual_id, $kode_diskon, $nama_diskon, $jenis_diskon, $nilai_diskon, $tanggal_mulai, $tanggal_berakhir);
            mysqli_stmt_bind_param($stmt, 'sssdss', $kode_diskon, $nama_diskon, $jenis_diskon, $nilai_diskon, $tanggal_mulai, $tanggal_berakhir);
            $action_type = "tambah";
        }

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $pesan_sukses = "Diskon berhasil di" . ($action_type == "tambah" ? "tambahkan" : "perbarui") . ".";
            } else {
                $pesan_error = "Tidak ada perubahan atau diskon tidak ditemukan.";
            }
        } else {
            if (mysqli_errno($conn) == 1062) { // Duplicate entry error for kode_diskon
                $pesan_error = "Kode diskon sudah ada. Mohon gunakan kode lain.";
            } else {
                $pesan_error = "Gagal " . ($action_type == "tambah" ? "menambahkan" : "memperbarui") . " diskon: " . mysqli_error($conn);
                error_log("Error " . $action_type . " diskon: " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($stmt);
        header('Location: diskon.php?status=success_' . $action_type);
        exit;
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $diskon_id = $_GET['id'];
    $action = $_GET['action'];

    if ($action == 'delete') {
        $query = "DELETE FROM diskon WHERE id = ?"; 
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $diskon_id); 
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $pesan_sukses = "Diskon berhasil dihapus.";
            } else {
                $pesan_error = "Diskon tidak ditemukan."; // Hapus atau Anda tidak memiliki izin...
            }
        } else {
            $pesan_error = "Gagal menghapus diskon: " . mysqli_error($conn);
            error_log("Error deleting diskon: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
        header('Location: diskon.php?status=success_delete');
        exit;
    } elseif ($action == 'status') {
        $new_status = $_GET['new_status'];
        $valid_statuses = ['aktif', 'nonaktif']; // Sesuai dengan ENUM Anda

        if (in_array($new_status, $valid_statuses)) {
            $query = "UPDATE diskon SET status = ? WHERE id = ?"; // Ganti status_diskon ke status, hapus AND penjual_id = ?
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $diskon_id); // Hapus $penjual_id
            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $pesan_sukses = "Status diskon berhasil diubah menjadi " . $new_status . ".";
                } else {
                    $pesan_error = "Diskon tidak ditemukan."; // Hapus atau Anda tidak memiliki izin...
                }
            } else {
                $pesan_error = "Gagal mengubah status diskon: " . mysqli_error($conn);
                error_log("Error changing diskon status: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
            header('Location: diskon.php?status=success_status_update');
            exit;
        } else {
            $pesan_error = "Status yang tidak valid.";
        }
    }
}

// Tampilkan pesan status dari redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_tambah') {
        $pesan_sukses = "Diskon berhasil ditambahkan.";
    } elseif ($_GET['status'] == 'success_edit') {
        $pesan_sukses = "Diskon berhasil diperbarui.";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan_sukses = "Diskon berhasil dihapus.";
    } elseif ($_GET['status'] == 'success_status_update') {
        $pesan_sukses = "Status diskon berhasil diperbarui.";
    }
}


$daftar_diskon = [];
// PENJUAL_ID DIKOMENTARI KARENA TIDAK ADA DI DB ANDA
$query_diskon = "SELECT * FROM diskon ORDER BY created_at DESC"; // Hapus WHERE penjual_id = ?
$stmt_diskon = mysqli_prepare($conn, $query_diskon);

mysqli_stmt_execute($stmt_diskon);
$result_diskon = mysqli_stmt_get_result($stmt_diskon);
while ($row = mysqli_fetch_assoc($result_diskon)) {
    // Logika untuk status 'berakhir' secara dinamis karena tidak ada di ENUM DB
    if (strtotime($row['tanggal_berakhir']) < time() && $row['status'] != 'berakhir') {
        $row['status_display'] = 'berakhir';
    } else {
        $row['status_display'] = $row['status']; // Gunakan status dari DB jika belum berakhir
    }
    $daftar_diskon[] = $row;
}
mysqli_stmt_close($stmt_diskon);

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
mysqli_stmt_bind_param($stmt_produk, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'] ?? 0;
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
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
    <title>Diskon - Dashboard Penjual</title>
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
        .form-group label span {
            color: red;
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
                <img src="../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Foto Profil Penjual" class="rounded-circle mr-2" width="40" height="40">
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
            <form action="../toko/profil/profil_toko.php" method="POST" enctype="multipart/form-data">
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
                <a class="nav-link collapsed" data-toggle="collapse" href="#pesananMenu" role="button" aria-expanded="false" aria-controls="pesananMenu">
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
                <a class="nav-link collapsed" data-toggle="collapse" href="#promosiMenu" role="button" aria-expanded="true" aria-controls="promosiMenu">
                    <i class="fas fa-bullhorn"></i>
                    <span class="ml-2">Promosi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="promosiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="diskon.php">Diskon</a>
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
                            <a class="nav-link submenu" href="../komunikasi/pesan/pesan_masuk.php">Pesan Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../komunikasi/ulasan/ulasan.php">Ulasan</a>
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
                            <a class="nav-link submenu" href="../artikel/artikel_saya.php">Artikel Saya</a>
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
                            <a class="nav-link submenu" href="../akun/profil.php">Profil</a>
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
        <h1>Manajemen Diskon</h1>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Daftar Diskon Anda</h4>
                <button class="btn btn-primary" data-toggle="modal" data-target="#diskonModal" id="btnTambahDiskon">
                    <i class="fas fa-plus"></i> Tambah Diskon Baru
                </button>
            </div>

            <?php if (empty($daftar_diskon)) : ?>
                <div class="alert alert-info">Anda belum memiliki diskon.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Diskon</th>
                                <th>Nama Diskon</th>
                                <th>Jenis</th>
                                <th>Nilai</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($daftar_diskon as $diskon) : ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($diskon['kode_diskon']) ?></strong></td>
                                    <td><?= htmlspecialchars($diskon['nama']) ?></td> <td>
                                        <?php
                                            // Menggunakan nilai ENUM sesuai DB Anda
                                            if ($diskon['jenis_diskon'] == 'persen') echo 'Persentase';
                                            elseif ($diskon['jenis_diskon'] == 'fixed') echo 'Jumlah Tetap';
                                            // 'gratis_ongkir' tidak didukung dengan ENUM Anda saat ini
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                            if ($diskon['jenis_diskon'] == 'persen') {
                                                echo htmlspecialchars($diskon['nilai_diskon']) . '%';
                                                // 'maksimal_diskon' tidak ada di DB Anda
                                            } elseif ($diskon['jenis_diskon'] == 'fixed') {
                                                echo 'Rp' . number_format($diskon['nilai_diskon'], 0, ',', '.');
                                            }
                                        ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($diskon['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($diskon['tanggal_berakhir'])) ?></td>
                                    <td>
                                        <?php
                                        // Menampilkan status berdasarkan tanggal dan kolom 'status' dari DB
                                        if ($diskon['status_display'] == 'aktif') {
                                            echo '<span class="badge badge-success">Aktif</span>';
                                        } elseif ($diskon['status_display'] == 'nonaktif') {
                                            echo '<span class="badge badge-warning">Nonaktif</span>';
                                        } elseif ($diskon['status_display'] == 'berakhir') {
                                            echo '<span class="badge badge-secondary">Berakhir</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn"
                                            data-toggle="modal"
                                            data-target="#diskonModal"
                                            data-id="<?= $diskon['id'] ?>"
                                            data-kode_diskon="<?= htmlspecialchars($diskon['kode_diskon']) ?>"
                                            data-nama="<?= htmlspecialchars($diskon['nama']) ?>"
                                            data-jenis_diskon="<?= htmlspecialchars($diskon['jenis_diskon']) ?>"
                                            data-nilai_diskon="<?= htmlspecialchars($diskon['nilai_diskon']) ?>"
                                            data-tanggal_mulai="<?= htmlspecialchars($diskon['tanggal_mulai']) ?>"
                                            data-tanggal_berakhir="<?= htmlspecialchars($diskon['tanggal_berakhir']) ?>"
                                            data-status="<?= htmlspecialchars($diskon['status']) ?>">
                                            Edit
                                        </button>
                                        <?php if ($diskon['status_display'] == 'aktif') : ?>
                                            <a href="diskon.php?action=status&id=<?= $diskon['id'] ?>&new_status=nonaktif"
                                               class="btn btn-sm btn-warning"
                                               onclick="return confirm('Apakah Anda yakin ingin menonaktifkan diskon ini?');">
                                                Nonaktifkan
                                            </a>
                                        <?php elseif ($diskon['status_display'] == 'nonaktif') : ?>
                                            <a href="diskon.php?action=status&id=<?= $diskon['id'] ?>&new_status=aktif"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Apakah Anda yakin ingin mengaktifkan diskon ini?');">
                                                Aktifkan
                                            </a>
                                        <?php endif; ?>
                                        <a href="diskon.php?action=delete&id=<?= $diskon['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus diskon ini?');">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="diskonModal" tabindex="-1" aria-labelledby="diskonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="diskonModalLabel">Tambah Diskon</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="diskon_id" id="diskon_id">
                    <div class="form-group">
                        <label for="kode_diskon">Kode Diskon <span class="text-danger">*</span></label>
                        <input type="text" name="kode_diskon" id="kode_diskon" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Diskon <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="nama" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="jenis_diskon">Jenis Diskon <span class="text-danger">*</span></label>
                        <select name="jenis_diskon" id="jenis_diskon" class="form-control" required>
                            <option value="">Pilih Jenis Diskon</option>
                            <option value="persen">Persentase</option>
                            <option value="fixed">Jumlah Tetap</option>
                            </select>
                    </div>
                    <div class="form-group">
                        <label for="nilai_diskon">Nilai Diskon <span class="text-danger">*</span></label>
                        <input type="number" name="nilai_diskon" id="nilai_diskon" class="form-control" step="0.01" required>
                        <small class="form-text text-muted">
                            Jika Persentase: Masukkan angka tanpa %, misal 10 untuk 10%.<br>
                            Jika Jumlah Tetap: Masukkan nilai rupiah tanpa titik atau koma, misal 50000 untuk Rp 50.000.
                        </small>
                    </div>
                    <?php /*
                    <div class="form-group">
                        <label for="minimal_pembelian">Minimal Pembelian (Opsional)</label>
                        <input type="number" name="minimal_pembelian" id="minimal_pembelian" class="form-control" step="0.01">
                        <small class="form-text text-muted">Diskon hanya berlaku jika total belanja mencapai nilai ini.</small>
                    </div>
                    <div class="form-group">
                        <label for="maksimal_diskon">Maksimal Diskon (Opsional - untuk Persentase)</label>
                        <input type="number" name="maksimal_diskon" id="maksimal_diskon" class="form-control" step="0.01">
                        <small class="form-text text-muted">Batas maksimal diskon yang diberikan jika jenis diskon persentase.</small>
                    </div>
                    <div class="form-group">
                        <label for="jumlah_tersedia">Jumlah Tersedia (Opsional)</label>
                        <input type="number" name="jumlah_tersedia" id="jumlah_tersedia" class="form-control">
                        <small class="form-text text-muted">Biarkan kosong untuk tidak terbatas.</small>
                    </div>
                    */ ?>
                    <div class="form-group">
                        <label for="tanggal_mulai">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_berakhir">Tanggal Berakhir <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_berakhir" id="tanggal_berakhir" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_edit_diskon" class="btn btn-primary">Simpan Diskon</button>
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
        // Ketika tombol Tambah Diskon Baru diklik
        $('#btnTambahDiskon').on('click', function() {
            $('#diskonModalLabel').text('Tambah Diskon');
            $('#diskon_id').val('');
            $('#kode_diskon').val('');
            $('#nama').val(''); // Gunakan #nama
            $('#jenis_diskon').val('');
            $('#nilai_diskon').val('');
            // $('#minimal_pembelian').val(''); // Dihapus
            // $('#maksimal_diskon').val('');   // Dihapus
            // $('#jumlah_tersedia').val('');   // Dihapus
            $('#tanggal_mulai').val('');
            $('#tanggal_berakhir').val('');
        });

        // Ketika tombol Edit Diskon diklik
        $('.edit-btn').on('click', function() {
            $('#diskonModalLabel').text('Edit Diskon');
            var id = $(this).data('id');
            var kode_diskon = $(this).data('kode_diskon');
            var nama = $(this).data('nama'); // Gunakan 'nama'
            var jenis_diskon = $(this).data('jenis_diskon');
            var nilai_diskon = $(this).data('nilai_diskon');
            var tanggal_mulai = $(this).data('tanggal_mulai');
            var tanggal_berakhir = $(this).data('tanggal_berakhir');
            // var minimal_pembelian = $(this).data('minimal_pembelian'); // Dihapus
            // var maksimal_diskon = $(this).data('maksimal_diskon');     // Dihapus
            // var jumlah_tersedia = $(this).data('jumlah_tersedia');     // Dihapus

            $('#diskon_id').val(id);
            $('#kode_diskon').val(kode_diskon);
            $('#nama').val(nama); // Set #nama
            $('#jenis_diskon').val(jenis_diskon);
            $('#nilai_diskon').val(nilai_diskon);
            // $('#minimal_pembelian').val(minimal_pembelian); // Dihapus
            // $('#maksimal_diskon').val(maksimal_diskon);     // Dihapus
            // $('#jumlah_tersedia').val(jumlah_tersedia);     // Dihapus
            $('#tanggal_mulai').val(tanggal_mulai);
            $('#tanggal_berakhir').val(tanggal_berakhir);
        });
    });
</script>

</body>
</html>