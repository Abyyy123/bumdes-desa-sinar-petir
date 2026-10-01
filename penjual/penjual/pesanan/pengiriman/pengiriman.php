<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini jika berbeda

// Pastikan pengguna sudah login
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// Ambil data penjual dan pengguna
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

$query_penjual = "SELECT pengguna_id, nama_toko, nama_pemilik, email, username, foto, nomor_telepon, alamat, kebijakan_pengiriman, tanggal_lahir, jenis_kelamin
                  FROM penjual WHERE pengguna_id = ?";
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

// --- LOGIC UNTUK UPDATE RESI DAN STATUS (khusus untuk AJAX) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_resi_ajax') {
    $pesanan_id_to_update = $_POST['pesanan_id_to_ship'];
    $resi_pengiriman = trim($_POST['resi_pengiriman']);

    if (empty($pesanan_id_to_update) || empty($resi_pengiriman)) {
        echo json_encode(['success' => false, 'message' => "ID Pesanan dan Nomor Resi tidak boleh kosong."]);
        exit;
    }

    // --- KEAMANAN: Pastikan pesanan ini memang milik penjual ini dan statusnya 'diproses' ---
    $query_verify_ownership = "
        SELECT COUNT(DISTINCT p.id) AS count_orders
        FROM pesanan p
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        JOIN produk pr ON dp.produk_id = pr.id
        WHERE p.id = ? AND pr.penjual_id = ? AND p.status_pesanan = 'diproses';
    ";
    $stmt_verify = mysqli_prepare($conn, $query_verify_ownership);
    mysqli_stmt_bind_param($stmt_verify, 'ii', $pesanan_id_to_update, $penjual_id);
    mysqli_stmt_execute($stmt_verify);
    $result_verify = mysqli_stmt_get_result($stmt_verify);
    $verified_order = mysqli_fetch_assoc($result_verify);
    mysqli_stmt_close($stmt_verify);

    if ($verified_order['count_orders'] > 0) {
        // Jika pesanan valid dan milik penjual ini, lakukan update
        $query_update_resi = "UPDATE pesanan SET status_pesanan = 'dikirim', resi_pengiriman = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $query_update_resi);
        mysqli_stmt_bind_param($stmt_update, 'si', $resi_pengiriman, $pesanan_id_to_update);

        if (mysqli_stmt_execute($stmt_update)) {
            // Jika berhasil, kirim respons sukses dalam JSON
            echo json_encode(['success' => true, 'message' => "Pesanan #{$pesanan_id_to_update} berhasil diupdate menjadi 'Dikirim' dengan nomor resi: " . htmlspecialchars($resi_pengiriman)]);
        } else {
            // Jika gagal, kirim respons gagal dalam JSON
            echo json_encode(['success' => false, 'message' => "Gagal mengupdate pesanan: " . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt_update);
    } else {
        echo json_encode(['success' => false, 'message' => "Pesanan tidak ditemukan, bukan milik Anda, atau tidak dalam status 'diproses'."]);
    }
    // Hentikan eksekusi skrip setelah mengirim respons JSON
    exit;
}

// --- AMBIL DATA PESANAN YANG SIAP DIKIRIM (STATUS 'diproses') ---
// Logika ini tetap ada untuk memuat data saat halaman pertama kali dibuka
$pesanan_siap_kirim = [];
$query_pesanan_siap_kirim = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesanan,
        pb.nama AS nama_pembeli,
        p.alamat_pengiriman,
        SUM(dp.harga_satuan * dp.quantity) AS total_harga_pesanan_penjual,
        p.status_pesanan,
        p.kode_unik
    FROM
        pesanan p
    JOIN
        detail_pesanan dp ON p.id = dp.pesanan_id
    JOIN
        produk pr ON dp.produk_id = pr.id
    JOIN
        pelanggan pb ON p.pelanggan_id = pb.pengguna_id
    WHERE
        pr.penjual_id = ?
        AND p.status_pesanan = 'diproses'
    GROUP BY
        p.id, p.tanggal_pesanan, pb.nama, p.alamat_pengiriman, p.status_pesanan, p.kode_unik
    ORDER BY
        p.tanggal_pesanan ASC;
";
$stmt_pesanan_siap_kirim = mysqli_prepare($conn, $query_pesanan_siap_kirim);
mysqli_stmt_bind_param($stmt_pesanan_siap_kiram, 'i', $penjual_id);
mysqli_stmt_execute($stmt_pesanan_siap_kirim);
$result_pesanan_siap_kirim = mysqli_stmt_get_result($stmt_pesanan_siap_kirim);

if ($result_pesanan_siap_kirim) {
    while ($row = mysqli_fetch_assoc($result_pesanan_siap_kirim)) {
        $pesanan_siap_kirim[] = $row;
    }
} else {
    // Pesan error ini tidak akan ditampilkan pada AJAX
    // Tapi akan ditampilkan jika halaman diakses normal
    $pesan_error = "Gagal mengambil data pesanan siap kirim: " . mysqli_error($conn);
}
mysqli_stmt_close($stmt_pesanan_siap_kirim);

// Fungsi untuk mengubah status pesanan menjadi teks yang mudah dibaca
function getStatusPesananText($status) {
    switch ($status) {
        case 'menunggu_pembayaran':
            return '<span class="badge badge-warning">Menunggu Pembayaran</span>';
        case 'diproses':
            return '<span class="badge badge-info">Diproses</span>';
        case 'dikirim':
            return '<span class="badge badge-primary">Dikirim</span>';
        case 'selesai':
            return '<span class="badge badge-success">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge badge-danger">Dibatalkan</span>';
        case 'pengembalian':
            return '<span class="badge badge-secondary">Pengembalian</span>';
        default:
            return '<span class="badge badge-light">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span>';
    }
}

// ... Bagian kode untuk statistik (seperti yang Anda berikan) ...
$query_menunggu = "SELECT COUNT(DISTINCT p.id) AS total FROM pesanan p
                   JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                   JOIN produk pr ON dp.produk_id = pr.id
                   WHERE pr.penjual_id = ? AND p.status_pesanan = 'menunggu_pembayaran'";
$stmt_menunggu = mysqli_prepare($conn, $query_menunggu);
mysqli_stmt_bind_param($stmt_menunggu, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_menunggu);
$result_menunggu = mysqli_stmt_get_result($stmt_menunggu);
$row_menunggu = mysqli_fetch_assoc($result_menunggu);
$total_menunggu = $row_menunggu['total'] ?? 0;
mysqli_stmt_close($stmt_menunggu);

$query_diproses = "SELECT COUNT(DISTINCT p.id) AS total FROM pesanan p
                   JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                   JOIN produk pr ON dp.produk_id = pr.id
                   WHERE pr.penjual_id = ? AND p.status_pesanan = 'diproses'";
$stmt_diproses = mysqli_prepare($conn, $query_diproses);
mysqli_stmt_bind_param($stmt_diproses, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_diproses);
$result_diproses = mysqli_stmt_get_result($stmt_diproses);
$row_diproses = mysqli_fetch_assoc($result_diproses);
$total_diproses = $row_diproses['total'] ?? 0;
mysqli_stmt_close($stmt_diproses);

$query_dikirim = "SELECT COUNT(DISTINCT p.id) AS total FROM pesanan p
                  JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                  JOIN produk pr ON dp.produk_id = pr.id
                  WHERE pr.penjual_id = ? AND p.status_pesanan = 'dikirim'";
$stmt_dikirim = mysqli_prepare($conn, $query_dikirim);
mysqli_stmt_bind_param($stmt_dikirim, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_dikirim);
$result_dikirim = mysqli_stmt_get_result($stmt_dikirim);
$row_dikirim = mysqli_fetch_assoc($result_dikirim);
$total_dikirim = $row_dikirim['total'] ?? 0;
mysqli_stmt_close($stmt_dikirim);

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

$query_pendapatan_total_dashboard = "SELECT SUM(dp.harga_satuan * dp.quantity) AS total
                     FROM pesanan p
                     JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                     JOIN produk pr ON dp.produk_id = pr.id
                     WHERE p.status_pesanan = 'selesai' AND pr.penjual_id = ?";
$stmt_pendapatan_total_dashboard = mysqli_prepare($conn, $query_pendapatan_total_dashboard);
mysqli_stmt_bind_param($stmt_pendapatan_total_dashboard, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_pendapatan_total_dashboard);
$result_pendapatan_total_dashboard = mysqli_stmt_get_result($stmt_pendapatan_total_dashboard);
$row_pendapatan_total_dashboard = mysqli_fetch_assoc($result_pendapatan_total_dashboard);
$total_pendapatan_dashboard = $row_pendapatan_total_dashboard['total'] ?: 0;
mysqli_stmt_close($stmt_pendapatan_total_dashboard);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengiriman Pesanan - Dashboard Penjual</title>
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
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.7em;
            border-radius: 0.25rem;
        }
        #alertContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            width: 350px;
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
                <div class="collapse" id="produkMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/daftar/daftar_produk.php">Daftar Produk</a>
                        </li>
                        <li class="nav-item">
                            </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/stok/stok.php">Stok / Inventaris</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/varian/varian_produk.php">Varian Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/kategori/kategori_produk.php">Kategori Produk</a>
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
                <div class="collapse show" id="pesananMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../daftar/pesanan.php">Daftar Pesanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="pengiriman.php">Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pengembalian/pengembalian_barang.php">Pengembalian Barang</a>
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
        <h1>Pengiriman Pesanan</h1>
        <hr>

        <div id="alertContainer">
            </div>

        <div class="card p-4">
            <p>Berikut adalah daftar pesanan yang berstatus 'Diproses' dan siap untuk dikirim. Masukkan nomor resi dan ubah status pesanan menjadi 'Dikirim'.</p>

            <?php if (empty($pesanan_siap_kirim)): ?>
                <div id="no-orders-message" class="alert alert-info">Tidak ada pesanan yang siap dikirim saat ini.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="ordersTable">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Tanggal</th>
                                <th>Pembeli</th>
                                <th>Alamat Pengiriman</th>
                                <th>Total (Produk Anda)</th>
                                <th>Status</th>
                                <th>Resi Pengiriman</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesanan_siap_kirim as $pesanan) : ?>
                                <tr id="row-<?= $pesanan['pesanan_id'] ?>">
                                    <td>#<?= htmlspecialchars($pesanan['pesanan_id']) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($pesanan['tanggal_pesanan'])) ?></td>
                                    <td><?= htmlspecialchars($pesanan['nama_pembeli']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($pesanan['alamat_pengiriman'])) ?></td>
                                    <td>Rp<?= number_format($pesanan['total_harga_pesanan_penjual'], 0, ',', '.') ?></td>
                                    <td id="status-<?= $pesanan['pesanan_id'] ?>"><?= getStatusPesananText($pesanan['status_pesanan']) ?></td>
                                    <td id="resi-<?= $pesanan['pesanan_id'] ?>"><?= htmlspecialchars($pesanan['resi_pengiriman'] ?: '-') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary btn-kirim" data-toggle="modal" data-target="#kirimModal" data-pesanan-id="<?= $pesanan['pesanan_id'] ?>">
                                            Input Resi / Kirim
                                        </button>
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

<div class="modal fade" id="kirimModal" tabindex="-1" aria-labelledby="kirimModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formKirim" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="kirimModalLabel">Input Resi dan Kirim Pesanan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_resi_ajax">
                    <input type="hidden" name="pesanan_id_to_ship" id="pesanan_id_to_ship">
                    <div class="form-group">
                        <label for="resi_pengiriman">Nomor Resi Pengiriman</label>
                        <input type="text" class="form-control" id="resi_pengiriman" name="resi_pengiriman" placeholder="Masukkan nomor resi" required>
                    </div>
                    <p class="mt-3">Setelah mengisi nomor resi, status pesanan akan berubah menjadi **'Dikirim'**.</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Simpan Resi & Kirim</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    // Fungsi untuk menampilkan alert di sudut kanan atas
    function showAlert(message, type) {
        var alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>`;
        $('#alertContainer').append(alertHtml);
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000); // Alert akan hilang setelah 5 detik
    }

    $(document).ready(function() {
        // Skrip untuk dropdown profil
        $('.dropdown-toggle').dropdown();

        // Skrip untuk mengisi ID pesanan ke modal saat tombol "Input Resi / Kirim" diklik
        $('#kirimModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Tombol yang memicu modal
            var pesananId = button.data('pesanan-id'); // Ambil data-pesanan-id dari tombol

            var modal = $(this);
            modal.find('#pesanan_id_to_ship').val(pesananId); // Set nilai hidden input di modal
        });

        // --- SKRIP AJAX UNTUK PROSES PENGIRIMAN ---
        $('#formKirim').on('submit', function(e) {
            e.preventDefault(); // Mencegah form dikirim secara default

            var form = $(this);
            var url = form.attr('action') || window.location.href; // Ambil URL aksi form, atau URL saat ini

            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(), // Mengambil data dari form
                dataType: 'json',
                success: function(response) {
                    $('#kirimModal').modal('hide'); // Sembunyikan modal

                    if (response.success) {
                        showAlert(response.message, 'success');

                        // Dapatkan ID pesanan yang berhasil di-update
                        var pesananId = $('#pesanan_id_to_ship').val();

                        // Hapus baris pesanan dari tabel
                        $('#row-' + pesananId).remove();

                        // Periksa apakah tabel kosong setelah penghapusan
                        if ($('#ordersTable tbody tr').length === 0) {
                            var noOrdersHtml = '<div id="no-orders-message" class="alert alert-info">Tidak ada pesanan yang siap dikirim saat ini.</div>';
                            $('#ordersTable').closest('.table-responsive').replaceWith(noOrdersHtml);
                        }

                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    $('#kirimModal').modal('hide');
                    showAlert('Terjadi kesalahan saat memproses permintaan. Silakan coba lagi.', 'danger');
                }
            });
        });
    });
</script>

</body>
</html>