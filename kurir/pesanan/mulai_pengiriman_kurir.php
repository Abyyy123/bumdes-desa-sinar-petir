<?php
session_start();
include('../../koneksi/koneksi.php'); // Pastikan path ini benar

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php'); // Redirect ke halaman login jika belum login
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID kurir yang login

// Ambil data profil kurir dari tabel 'kurir'
$query_kurir = "SELECT * FROM kurir WHERE id = ?";
$stmt_kurir = mysqli_prepare($conn, $query_kurir);
// Cek jika prepared statement berhasil
if (!$stmt_kurir) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_kurir, 'i', $user_id);
mysqli_stmt_execute($stmt_kurir);
$result_kurir = mysqli_stmt_get_result($stmt_kurir);
$user = mysqli_fetch_assoc($result_kurir);

// Jika kurir tidak ditemukan atau statusnya tidak 'aktif', redirect ke login
if (!$user || $user['status'] !== 'aktif') {
    session_destroy(); // Hancurkan sesi
    header('Location: ../../login.php?error=invalid_kurir_status'); // Redirect dengan pesan error
    exit;
}

// --- LOGIKA UPDATE PROFIL KURIR (SAMA SEPERTI SEBELUMNYA) ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $password_baru = $_POST['password'];
    $password_hash = $user['password']; // Default ke password lama yang sudah di-hash

    // Hanya update password jika ada input password baru
    if (!empty($password_baru)) {
        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
    }

    $foto_lama = $user['foto'];
    $foto_baru = $foto_lama; // Inisialisasi dengan foto lama

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../img/foto/';
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Hapus foto lama jika ada, berbeda dengan yang baru, dan bukan 'default.png'
            if ($foto_lama && file_exists($upload_dir . $foto_lama) && $foto_lama != $foto_name && $foto_lama != 'default.png') {
                unlink($upload_dir . $foto_lama);
            }
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                $foto_baru = $foto_name; // Update dengan nama file baru
            } else {
                echo "<script>alert('Gagal mengunggah foto baru.');</script>";
            }
        } else {
            echo "<script>alert('Jenis file foto tidak diizinkan (hanya jpg, jpeg, png, gif).');</script>";
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi error saat mengunggah foto: " . $_FILES['foto']['error'] . "');</script>";
    }

    // Update data di tabel 'kurir'
    $update = "UPDATE kurir SET nama=?, username=?, email=?, nomor_telepon=?, password=?, foto=? WHERE id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    if (!$stmt_update) {
        die("Error preparing update statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_update, 'ssssssi', $nama, $username, $email, $nomor_telepon, $password_hash, $foto_baru, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        // Refresh data pengguna setelah update
        $query_kurir_refresh = "SELECT * FROM kurir WHERE id = ?";
        $stmt_kurir_refresh = mysqli_prepare($conn, $query_kurir_refresh);
        mysqli_stmt_bind_param($stmt_kurir_refresh, 'i', $user_id);
        mysqli_stmt_execute($stmt_kurir_refresh);
        $result_kurir_refresh = mysqli_stmt_get_result($stmt_kurir_refresh);
        $user = mysqli_fetch_assoc($result_kurir_refresh);
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='pesanan_kurir.php';</script>"; // Redirect ke halaman ini
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- LOGIKA AKSI PENGIRIMAN ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pesanan_id = $_GET['id'];
    $action = $_GET['action'];

    // Validasi apakah pesanan memang ditugaskan ke kurir ini
    $query_cek_pesanan = "SELECT kurir_id, status_pesanan FROM pesanan WHERE id = ?";
    $stmt_cek = mysqli_prepare($conn, $query_cek_pesanan);
    mysqli_stmt_bind_param($stmt_cek, 'i', $pesanan_id);
    mysqli_stmt_execute($stmt_cek);
    $result_cek = mysqli_stmt_get_result($stmt_cek);
    $pesanan_data = mysqli_fetch_assoc($result_cek);
    mysqli_stmt_close($stmt_cek);

    if (!$pesanan_data || $pesanan_data['kurir_id'] != $user_id) {
        echo "<script>alert('Akses ditolak: Pesanan tidak valid atau bukan tugas Anda.'); window.location.href='pesanan_kurir.php';</script>";
        exit;
    }

    if ($action == 'start_delivery') {
        // Cek status pesanan, hanya bisa memulai jika 'diproses'
        if ($pesanan_data['status_pesanan'] == 'diproses') {
            mysqli_autocommit($conn, FALSE); // Mulai transaksi

            $update_pesanan_status = "UPDATE pesanan SET status_pesanan = 'dikirim' WHERE id = ?";
            $stmt_update_pesanan = mysqli_prepare($conn, $update_pesanan_status);
            mysqli_stmt_bind_param($stmt_update_pesanan, 'i', $pesanan_id);

            // Tambahkan entri ke tabel pengiriman
            $insert_pengiriman = "INSERT INTO pengiriman (pesanan_id, kurir_id, tanggal_pengiriman, status_pengiriman) VALUES (?, ?, CURDATE(), 'dikirim')";
            $stmt_insert_pengiriman = mysqli_prepare($conn, $insert_pengiriman);
            mysqli_stmt_bind_param($stmt_insert_pengiriman, 'ii', $pesanan_id, $user_id);

            if (mysqli_stmt_execute($stmt_update_pesanan) && mysqli_stmt_execute($stmt_insert_pengiriman)) {
                mysqli_commit($conn); // Commit transaksi
                echo "<script>alert('Pesanan ID " . $pesanan_id . " berhasil dimulai pengirimannya.'); window.location.href='pesanan_kurir.php';</script>";
            } else {
                mysqli_rollback($conn); // Rollback jika ada yang gagal
                echo "<script>alert('Gagal memulai pengiriman untuk Pesanan ID " . $pesanan_id . ": " . mysqli_error($conn) . "'); window.location.href='pesanan_kurir.php';</script>";
            }
            mysqli_stmt_close($stmt_update_pesanan);
            mysqli_stmt_close($stmt_insert_pengiriman);
        } else {
            echo "<script>alert('Pesanan ID " . $pesanan_id . " tidak dapat dimulai pengirimannya karena statusnya bukan \\'diproses\\'.'); window.location.href='pesanan_kurir.php';</script>";
        }

    } elseif ($action == 'finish_delivery') {
        // Cek status pesanan, hanya bisa menyelesaikan jika 'dikirim'
        if ($pesanan_data['status_pesanan'] == 'dikirim') {
            mysqli_autocommit($conn, FALSE); // Mulai transaksi

            $update_pesanan_status = "UPDATE pesanan SET status_pesanan = 'selesai' WHERE id = ?";
            $stmt_update_pesanan = mysqli_prepare($conn, $update_pesanan_status);
            mysqli_stmt_bind_param($stmt_update_pesanan, 'i', $pesanan_id);

            // Update status di tabel pengiriman
            $update_pengiriman_status = "UPDATE pengiriman SET status_pengiriman = 'tiba', updated_at = CURRENT_TIMESTAMP WHERE pesanan_id = ? AND kurir_id = ? AND status_pengiriman = 'dikirim'";
            $stmt_update_pengiriman = mysqli_prepare($conn, $update_pengiriman_status);
            mysqli_stmt_bind_param($stmt_update_pengiriman, 'ii', $pesanan_id, $user_id);

            if (mysqli_stmt_execute($stmt_update_pesanan) && mysqli_stmt_execute($stmt_update_pengiriman)) {
                mysqli_commit($conn); // Commit transaksi
                echo "<script>alert('Pesanan ID " . $pesanan_id . " berhasil diselesaikan.'); window.location.href='pesanan_kurir.php';</script>";
            } else {
                mysqli_rollback($conn); // Rollback jika ada yang gagal
                echo "<script>alert('Gagal menyelesaikan pengiriman untuk Pesanan ID " . $pesanan_id . ": " . mysqli_error($conn) . "'); window.location.href='pesanan_kurir.php';</script>";
            }
            mysqli_stmt_close($stmt_update_pesanan);
            mysqli_stmt_close($stmt_update_pengiriman);
        } else {
            echo "<script>alert('Pesanan ID " . $pesanan_id . " tidak dapat diselesaikan karena statusnya bukan \\'dikirim\\'.'); window.location.href='pesanan_kurir.php';</script>";
        }
    } elseif ($action == 'report_issue') {
        // Implementasi untuk melaporkan masalah.
        // Ini bisa berupa redirect ke halaman form pelaporan,
        // atau menampilkan modal untuk input masalah.
        // Untuk contoh ini, kita akan menampilkan alert sederhana.
        echo "<script>
                var issue = prompt('Laporkan masalah untuk Pesanan ID " . $pesanan_id . ":');
                if (issue !== null && issue.trim() !== '') {
                    // Di sini Anda bisa mengirimkan data masalah ke database atau ke admin
                    alert('Masalah telah dilaporkan: ' + issue + '. Tim akan segera menindaklanjuti.');
                    // Anda mungkin ingin mengarahkan ke halaman lain atau mengupdate status pesanan menjadi 'bermasalah'
                    window.location.href='pesanan_kurir.php'; // Kembali ke halaman ini
                } else {
                    alert('Pelaporan masalah dibatalkan.');
                    window.location.href='pesanan_kurir.php';
                }
              </script>";
        exit;
    }
}


// --- LOGIKA UTAMA UNTUK HALAMAN PESANAN KURIR ---
// Ambil daftar pesanan yang ditugaskan kepada kurir ini dan statusnya 'diproses' atau 'dikirim'
$query_tugas_pengiriman = "SELECT
                                p.id AS pesanan_id,
                                p.tanggal_pesanan,
                                p.status_pesanan,
                                p.metode_pengiriman,
                                p.alamat_pengiriman,
                                p.ongkos_kirim,
                                p.total_harga,
                                p.catatan_pelanggan,
                                pl.nama AS nama_pelanggan,
                                pl.nomor_telepon AS telepon_pelanggan
                            FROM pesanan p
                            JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
                            WHERE p.kurir_id = ? AND p.status_pesanan IN ('diproses', 'dikirim')
                            ORDER BY p.tanggal_pesanan ASC";

$stmt_tugas_pengiriman = mysqli_prepare($conn, $query_tugas_pengiriman);
if (!$stmt_tugas_pengiriman) {
    die("Error preparing tugas pengiriman statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_tugas_pengiriman, 'i', $user_id);
mysqli_stmt_execute($stmt_tugas_pengiriman);
$result_tugas_pengiriman = mysqli_stmt_get_result($stmt_tugas_pengiriman);

// Inisialisasi array untuk menyimpan pesanan dengan detail produk
$pesanan_kurir_dengan_detail = [];
while ($pesanan = mysqli_fetch_assoc($result_tugas_pengiriman)) {
    $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']] = $pesanan;
    $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']]['produk'] = [];

    // Ambil detail produk untuk setiap pesanan
    $query_detail_produk = "SELECT
                                dp.quantity,
                                dp.harga_satuan,
                                dp.nama_produk_saat_beli,
                                dp.variasi_detail_saat_beli,
                                pr.gambar AS gambar_produk
                            FROM detail_pesanan dp
                            JOIN produk pr ON dp.produk_id = pr.id
                            WHERE dp.pesanan_id = ?";
    $stmt_detail_produk = mysqli_prepare($conn, $query_detail_produk);
    if (!$stmt_detail_produk) {
        die("Error preparing detail produk statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_detail_produk, 'i', $pesanan['pesanan_id']);
    mysqli_stmt_execute($stmt_detail_produk);
    $result_detail_produk = mysqli_stmt_get_result($stmt_detail_produk);

    while ($detail_produk = mysqli_fetch_assoc($result_detail_produk)) {
        $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']]['produk'][] = $detail_produk;
    }
    mysqli_stmt_close($stmt_detail_produk);
}
mysqli_stmt_close($stmt_tugas_pengiriman);

// --- AKHIR LOGIKA UTAMA UNTUK HALAMAN PESANAN KURIR ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tugas Pengiriman | BUMDes Sinar Petir</title>
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
            position: sticky;
            top: 0;
            left: 0;
            overflow-y: auto;
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
        .content.ml-collapsed {
            margin-left: 80px;
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

        /* Styles for order list table */
        .order-list-table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .order-detail-card {
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .order-detail-card .card-header {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-detail-card .card-body {
            padding: 20px;
        }
        .order-item-list {
            list-style: none;
            padding: 0;
        }
        .order-item-list li {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px dashed #eee;
        }
        .order-item-list li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .order-item-list img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 5px;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-details strong {
            display: block;
            font-size: 1.1em;
            margin-bottom: 3px;
        }
        .order-item-details small {
            color: #666;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
            .content.ml-collapsed {
                margin-left: 0;
            }
            .toggle-btn {
                margin-left: 10px;
            }
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
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../img/foto/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto Profil" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../img/foto/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                    <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                    <a class="btn btn-link text-danger p-0 d-block" href="../../logout.php">Logout</a>
                </div>
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
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="nomor_telepon" class="form-control" value="<?= htmlspecialchars($user['nomor_telepon'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($user['foto'] && $user['foto'] != 'default.png') : ?>
                            <img src="../../img/foto/<?= htmlspecialchars($user['foto']) ?>" width="80" class="mb-2 rounded"><br>
                        <?php else: ?>
                            <img src="../../img/foto/default.png" width="80" class="mb-2 rounded"><br>
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
                <a class="nav-link" href="../dashboard_kurir.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="pesanan_kurir.php">
                    <i class="fas fa-box-open"></i>
                    <span class="ml-2">Tugas Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../riwayat/riwayat_pengiriman.php">
                    <i class="fas fa-history"></i>
                    <span class="ml-2">Riwayat Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../invoice/invoice_kurir.php"> <i class="fas fa-file-invoice"></i> <span class="ml-2">Invoice</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../laporan/laporan_kinerja.php">
                    <i class="fas fa-chart-bar"></i>
                    <span class="ml-2">Laporan Kinerja</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#editProfilModal">
                    <i class="fas fa-user-circle"></i>
                    <span class="ml-2">Pengaturan Profil</span>
                </a>
            </li>
        </ul>
        <a href="../../logout.php" class="text-danger mt-3 d-block pl-3"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <h2>Tugas Pengiriman Anda</h2>
        <p>Berikut adalah daftar pesanan yang sedang dalam proses pengiriman atau siap untuk dikirim oleh Anda.</p>

        <?php if (!empty($pesanan_kurir_dengan_detail)) : ?>
            <?php foreach ($pesanan_kurir_dengan_detail as $pesanan_id => $pesanan) : ?>
                <div class="card shadow order-detail-card">
                    <div class="card-header">
                        <div>
                            <strong>Pesanan ID: <?= htmlspecialchars($pesanan['pesanan_id']) ?></strong><br>
                            <small>Tanggal Pesanan: <?= htmlspecialchars(date('d F Y H:i', strtotime($pesanan['tanggal_pesanan']))) ?></small>
                        </div>
                        <div>
                            <span class="badge
                                <?php
                                    if($pesanan['status_pesanan'] == 'diproses') echo 'bg-warning text-dark';
                                    else if($pesanan['status_pesanan'] == 'dikirim') echo 'bg-info text-white';
                                    else echo 'bg-secondary text-white';
                                ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['status_pesanan']))) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Informasi Pelanggan:</h6>
                                <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($pesanan['nama_pelanggan']) ?></p>
                                <p class="mb-1"><strong>Telepon:</strong> <?= htmlspecialchars($pesanan['telepon_pelanggan']) ?></p>
                                <p class="mb-1"><strong>Alamat Pengiriman:</strong> <?= htmlspecialchars($pesanan['alamat_pengiriman']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Detail Pesanan:</h6>
                                <p class="mb-1"><strong>Metode Pengiriman:</strong> <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['metode_pengiriman']))) ?></p>
                                <p class="mb-1"><strong>Ongkos Kirim:</strong> Rp. <?= number_format($pesanan['ongkos_kirim'], 0, ',', '.') ?></p>
                                <p class="mb-1"><strong>Total Harga:</strong> Rp. <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></p>
                                <?php if (!empty($pesanan['catatan_pelanggan'])) : ?>
                                    <p class="mb-1"><strong>Catatan Pelanggan:</strong> <?= htmlspecialchars($pesanan['catatan_pelanggan']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                        <h6>Produk dalam Pesanan:</h6>
                        <ul class="order-item-list">
                            <?php foreach ($pesanan['produk'] as $produk_item) : ?>
                                <li>
                                    <img src="../../img/barang/<?= htmlspecialchars($produk_item['gambar_produk'] ?: 'default_product.png') ?>" alt="Gambar Produk">
                                    <div class="order-item-details">
                                        <strong><?= htmlspecialchars($produk_item['nama_produk_saat_beli']) ?></strong>
                                        <small>Jumlah: <?= htmlspecialchars($produk_item['quantity']) ?></small><br>
                                        <small>Harga Satuan: Rp. <?= number_format($produk_item['harga_satuan'], 0, ',', '.') ?></small>
                                        <?php if (!empty($produk_item['variasi_detail_saat_beli'])) : ?>
                                            <small>Variasi: <?= htmlspecialchars($produk_item['variasi_detail_saat_beli']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="text-right mt-3">
                            <?php if ($pesanan['status_pesanan'] == 'diproses') : ?>
                                <a href="mulai_pengiriman_kurir.php?action=start_delivery&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-info me-2">Mulai Pengiriman</a>
                            <?php elseif ($pesanan['status_pesanan'] == 'dikirim') : ?>
                                <a href="mulai_pengiriman_kurir.php?action=finish_delivery&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-success me-2">Selesaikan Pengiriman</a>
                                <a href="mulai_pengiriman_kurir.php?action=report_issue&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-warning">Laporkan Masalah</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="alert alert-info text-center" role="alert">
                Tidak ada tugas pengiriman aktif saat ini.
            </div>
        <?php endif; ?>

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
        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });
    });
</script>
</body>
</html>