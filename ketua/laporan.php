<?php
session_start();
include('../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id']) || $_SESSION['role'] !== 'ketua') {
    header('Location: ../login.php');
    exit;
}

// Fetch user data for the profile dropdown
$user_id = $_SESSION['pengguna_id'];
$query_user = "SELECT nama, username, email, foto FROM pengguna WHERE id = $user_id";
$result_user = mysqli_query($conn, $query_user);
$user = mysqli_fetch_assoc($result_user);

// Proses update profil (dari dashboard_ketua.php, ditambahkan ke sini juga jika ingin fitur edit profil di halaman laporan)
// Ini penting jika modal edit profil bisa diakses dari halaman laporan juga
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'] != '' ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password'];
    $foto_lama = $user['foto'];
    $foto_baru = $foto_lama; // Inisialisasi dengan foto lama

    if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../img/foto/';
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Hapus foto lama jika ada dan berbeda dengan yang baru
            if ($foto_lama && file_exists($upload_dir . $foto_lama) && $foto_lama != $foto_name) {
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
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi error saat mengunggah foto: " . $_FILES['foto']['error'] . "');</script>";
    }

    $update = "UPDATE pengguna SET nama=?, username=?, email=?, password=?, foto=? WHERE id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt_update, 'sssssi', $nama, $username, $email, $password, $foto_baru, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        // Perbarui data $user setelah update
        $user['nama'] = $nama;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['foto'] = $foto_baru;

        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='laporan.php?type=" . ($report_type ?? 'overview') . "';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

$report_type = $_GET['type'] ?? 'overview'; // Default ke overview jika tidak ada parameter

// Pagination setup
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// Pastikan $page tidak kurang dari 1
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan <?= ucfirst(str_replace('_', ' ', $report_type)); ?> - BUMDes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Warna Identik Lampung */
        :root {
            --lampung-gold: #FFD700; /* Kuning Emas */
            --lampung-red: #DC143C; /* Merah menyala, lebih dekat ke Crimson */
            --lampung-green: #228B22; /* Hijau (Forest Green) */
            --lampung-blue: #4682B4; /* Biru (Steel Blue) */
            --lampung-white: #FFFFFF; /* Putih */
            --lampung-dark-gray: #343a40; /* Untuk teks atau latar belakang gelap */
            --lampung-light-bg: #f8f9fa; /* Latar belakang terang */
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: var(--lampung-light-bg); /* Latar belakang terang */
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
            background-color: var(--lampung-dark-gray); /* Tetap gelap agar kontras dengan teks */
            min-height: 100vh;
            padding: 20px 0;
            color: var(--lampung-white);
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: var(--lampung-gold); /* Judul sidebar dengan kuning emas */
            margin-bottom: 30px;
        }
        .sidebar a {
            color: var(--lampung-white);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: var(--lampung-gold); /* Hover pada kuning emas */
            color: var(--lampung-dark-gray); /* Teks jadi gelap saat hover */
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: var(--lampung-white);
        }
        .sidebar .submenu:hover {
            color: var(--lampung-dark-gray); /* Teks submenu jadi gelap saat hover */
            background-color: var(--lampung-gold); /* Latar submenu jadi kuning emas saat hover */
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
            color: var(--lampung-white);
            margin-left: 20px;
            font-size: 20px;
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
            background: var(--lampung-white);
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .profile-menu h5 {
            margin-top: 0;
            color: var(--lampung-dark-gray);
        }
        .profile-menu p {
            margin: 0;
            color: var(--lampung-dark-gray);
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: var(--lampung-blue); /* Gunakan biru untuk link */
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: var(--lampung-white);
            padding: 20px;
            border-radius: 10px;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .btn-danger { /* Untuk tombol Download PDF */
            background-color: var(--lampung-red) !important;
            border-color: var(--lampung-red) !important;
        }
        .btn-danger:hover {
            background-color: #A52A2A !important; /* Slightly darker red on hover */
            border-color: #A52A2A !important;
        }

        /* Pagination Styling */
        .pagination .page-item .page-link {
            color: var(--lampung-dark-gray); /* Default text color for pagination links */
        }

        .pagination .page-item.active .page-link {
            background-color: var(--lampung-gold);
            border-color: var(--lampung-gold);
            color: var(--lampung-dark-gray); /* Text color for active page */
        }

        .pagination .page-item.active .page-link:hover {
            background-color: var(--lampung-gold); /* Keep same on hover for active */
            border-color: var(--lampung-gold);
            color: var(--lampung-dark-gray);
        }

        .pagination .page-item .page-link:hover {
            background-color: var(--lampung-gold); /* Hover background color */
            border-color: var(--lampung-gold); /* Hover border color */
            color: var(--lampung-dark-gray); /* Hover text color */
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d; /* Lighter color for disabled links */
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand ml-3" href="#" style="color: var(--lampung-gold);">BUMDes Sinar Petir</a>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                    <div class="profile-icon mb-2">
                        <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Profile">
                    </div>
                    <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                    <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                    <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
                    <div class="dropdown-divider my-2"></div>
                    <div class="text-left">
                    <a class="btn btn-link p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal" style="color: var(--lampung-blue);">Edit Profil</a>
                    <a class="btn btn-link p-0 d-block" href="../logout.php" style="color: var(--lampung-red);">Logout</a>
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
                            <label>Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                        <div class="form-group">
                            <label>Foto Profil</label><br>
                            <?php if ($user['foto']) : ?>
                                <img src="../img/foto/<?= $user['foto'] ?>" width="80" class="mb-2 rounded"><br>
                            <?php endif; ?>
                            <input type="file" name="foto" class="form-control-file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="simpan" class="btn btn-primary" style="background-color: var(--lampung-gold); border-color: var(--lampung-gold); color: var(--lampung-dark-gray);">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="wrapper">
        <div class="sidebar" id="sidebar">
            <h4>&nbsp;</h4>
            <li class="nav-item">
                <a class="nav-link" href="dashboard_ketua.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#laporanUmum" role="button" aria-expanded="false" aria-controls="laporanUmum">
                <i class="fas fa-file-alt"></i>
                <span class="ml-2">Laporan Umum</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="laporanUmum">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=bumdes">Laporan BUMDes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=unit_usaha">Laporan Unit Usaha</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=anggota">Laporan Anggota</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=pengguna">Laporan Pengguna</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#laporanEcom" role="button" aria-expanded="false" aria-controls="laporanEcom">
                <i class="fas fa-file-invoice-dollar"></i>
                <span class="ml-2">Laporan E-commerce</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="laporanEcom">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=pelanggan">Laporan Pelanggan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=penjual">Laporan Penjual</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=kurir">Laporan Kurir</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=produk">Laporan Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=pesanan">Laporan Pesanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=pembayaran">Laporan Pembayaran</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=ulasan">Laporan Ulasan Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="laporan.php?type=pengembalian">Laporan Pengembalian Barang</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="laporan.php?type=transaksi_keuangan">
            <i class="fas fa-chart-line"></i>
            <span class="ml-2">Laporan Keuangan</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="laporan.php?type=artikel">
            <i class="fas fa-newspaper"></i>
            <span class="ml-2">Laporan Artikel</span>
          </a>
        </li>
        </ul>
        <a href="../logout.php" class="text-danger" style="color: var(--lampung-red) !important;"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
        </div>

        <div class="content">
            <h2>Laporan Detail: <?= ucfirst(str_replace('_', ' ', $report_type)); ?></h2>
            <hr>

            <?php if ($report_type == 'bumdes') : ?>
                <h4>Data Informasi BUMDes</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=bumdes" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama BUMDes</th>
                                <th>Alamat</th>
                                <th>Tahun Berdiri</th>
                                <th>Visi</th>
                                <th>Misi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM bumdes";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_bumdes = mysqli_query($conn, "SELECT * FROM bumdes LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_bumdes) > 0) {
                                while ($row_bumdes = mysqli_fetch_assoc($result_bumdes)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['alamat']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['tahun_berdiri']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['visi']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_bumdes['misi']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data BUMDes.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'unit_usaha') : ?>
                <h4>Data Unit Usaha</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=unit_usaha" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Unit</th>
                                <th>Deskripsi</th>
                                <th>Tanggal Berdiri</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM unit_usaha";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_unit_usaha = mysqli_query($conn, "SELECT * FROM unit_usaha LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_unit_usaha) > 0) {
                                while ($row_unit_usaha = mysqli_fetch_assoc($result_unit_usaha)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_unit_usaha['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_unit_usaha['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_unit_usaha['deskripsi']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_unit_usaha['slug']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>Tidak ada data unit usaha.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'anggota') : ?>
                <h4>Data Anggota BUMDes</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=anggota" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Nomor anggota</th>
                                <th>Tanggal Bergabung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM anggota";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_anggota = mysqli_query($conn, "SELECT * FROM anggota LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_anggota) > 0) {
                                while ($row_anggota = mysqli_fetch_assoc($result_anggota)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_anggota['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_anggota['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_anggota['nomor_anggota']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_anggota['tanggal_bergabung']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>Tidak ada data anggota.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'pengguna') : ?>
                <h4>Data Pengguna Sistem</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=pengguna" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status Akun</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM pengguna";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_pengguna = mysqli_query($conn, "SELECT id, nama, username, email, role, status FROM pengguna LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_pengguna) > 0) {
                                while ($row_pengguna = mysqli_fetch_assoc($result_pengguna)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['role']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengguna['status']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data pengguna.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'pelanggan') : ?>
                <h4>Data Pelanggan E-commerce</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=pelanggan" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Alamat</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM pelanggan";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_pelanggan = mysqli_query($conn, "SELECT * FROM pelanggan LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_pelanggan) > 0) {
                                while ($row_pelanggan = mysqli_fetch_assoc($result_pelanggan)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['pengguna_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['nomor_telepon']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['alamat']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pelanggan['status']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data pelanggan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'penjual') : ?>
                <h4>Data Penjual E-commerce</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=penjual" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Toko</th>
                                <th>Nama Pemilik</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM penjual";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_penjual = mysqli_query($conn, "SELECT * FROM penjual LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_penjual) > 0) {
                                while ($row_penjual = mysqli_fetch_assoc($result_penjual)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_penjual['pengguna_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_penjual['nama_toko']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_penjual['nama_pemilik']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_penjual['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_penjual['nomor_telepon']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_penjual['status']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data penjual.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'kurir') : ?>
                <h4>Data Kurir</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=kurir" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kurir</th>
                                <th>Telepon</th>
                                <th>Area Layanan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM kurir";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_kurir = mysqli_query($conn, "SELECT * FROM kurir LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_kurir) > 0) {
                                while ($row_kurir = mysqli_fetch_assoc($result_kurir)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_kurir['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_kurir['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_kurir['nomor_telepon']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_kurir['area_pengiriman']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_kurir['status']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>Tidak ada data kurir.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'produk') : ?>
                <h4>Data Produk</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=produk" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Penjual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM produk";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_produk = mysqli_query($conn, "SELECT p.id, p.nama, kp.nama_kategori AS kategori, p.harga, p.stok, s.nama_toko AS penjual FROM produk p JOIN kategori_produk kp ON p.kategori_id = kp.id JOIN penjual s ON p.penjual_id = s.pengguna_id LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_produk) > 0) {
                                while ($row_produk = mysqli_fetch_assoc($result_produk)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_produk['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_produk['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_produk['kategori']) . "</td>";
                                    echo "<td>Rp. " . number_format($row_produk['harga'], 0, ',', '.') . "</td>";
                                    echo "<td>" . htmlspecialchars($row_produk['stok']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_produk['penjual']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data produk.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'pesanan') : ?>
                <h4>Data Pesanan</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=pesanan" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Tanggal Pesanan</th>
                                <th>Total Harga</th>
                                <th>Status Pesanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Hitung total data untuk paginasi
                            $count_query = "SELECT COUNT(*) AS total FROM pesanan";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_pesanan = mysqli_query($conn, "SELECT p.id, c.nama AS nama_pelanggan, p.tanggal_pesanan, p.total_harga, p.status_pesanan FROM pesanan p JOIN pelanggan c ON p.pelanggan_id = c.pengguna_id LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_pesanan) > 0) {
                                while ($row_pesanan = mysqli_fetch_assoc($result_pesanan)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_pesanan['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pesanan['nama_pelanggan']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pesanan['tanggal_pesanan']) . "</td>";
                                    echo "<td>Rp. " . number_format($row_pesanan['total_harga'], 0, ',', '.') . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pesanan['status_pesanan']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>Tidak ada data pesanan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'pembayaran') : ?>
                <h4>Data Pembayaran</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=pembayaran" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ID Pesanan</th>
                                <th>Metode Pembayaran</th>
                                <th>Tanggal Pembayaran</th>
                                <th>Jumlah</th>
                                <th>Status Pembayaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Hitung total data untuk paginasi
                            $count_query = "SELECT COUNT(*) AS total FROM pembayaran";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_pembayaran = mysqli_query($conn, "SELECT * FROM pembayaran LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_pembayaran) > 0) {
                                while ($row_pembayaran = mysqli_fetch_assoc($result_pembayaran)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_pembayaran['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pembayaran['pesanan_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pembayaran['metode_pembayaran']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pembayaran['tanggal_pembayaran']) . "</td>";
                                    echo "<td>Rp. " . number_format($row_pembayaran['jumlah_bayar'], 0, ',', '.') . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pembayaran['status_pembayaran']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>Tidak ada data pembayaran.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'ulasan') : ?>
                <h4>Data Ulasan Produk</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=ulasan" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produk</th>
                                <th>Pelanggan</th>
                                <th>Rating</th>
                                <th>Komentar</th>
                                <th>Tanggal Ulasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM ulasan_produk";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_ulasan = mysqli_query($conn, "SELECT up.id, p.nama AS nama_produk, c.nama AS nama_pelanggan, up.rating, up.komentar, up.tanggal_ulasan FROM ulasan_produk up JOIN produk p ON up.produk_id = p.id JOIN pelanggan c ON up.pelanggan_id = c.pengguna_id LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_ulasan) > 0) {
                                while ($row_ulasan = mysqli_fetch_assoc($result_ulasan)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['nama_produk']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['nama_pelanggan']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['rating']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['komentar']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_ulasan['tanggal_ulasan']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7'>Tidak ada data ulasan produk.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'pengembalian') : ?>
                <h4>Data Pengembalian Barang</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=pengembalian" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ID Pesanan</th>
                                <th>Produk</th>
                                <th>Pelanggan</th>
                                <th>Alasan</th>
                                <th>Status</th>
                                <th>Tanggal Pengajuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM pengembalian_barang";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            // Corrected JOIN: Assuming `pengembalian_barang` has `produk_id` and `pelanggan_id` directly or related via `pesanan_id`
                            // For simplicity, let's assume produk_id and pelanggan_id exist in pengembalian_barang
                            // If `produk_id` is not directly in `pengembalian_barang`, you'll need to join through `pesanan_detail` or similar.
                            // For this example, I'll update the query based on common database schema assumptions.
                            // If `produk_id` and `pelanggan_id` are in `pengembalian_barang`:
                            $result_pengembalian = mysqli_query($conn, "SELECT pb.id, pb.pesanan_id, pr.nama AS nama_produk, c.nama AS nama_pelanggan, pb.alasan_pengembalian, pb.status_pengembalian, pb.tanggal_pengajuan FROM pengembalian_barang pb JOIN produk pr ON pb.pesanan_id = pr.id JOIN pelanggan c ON pb.pelanggan_id = c.pengguna_id LIMIT $limit OFFSET $offset");

                            // If `produk_id` and `pelanggan_id` are *not* directly in `pengembalian_barang` but rather through `pesanan` and `detail_pesanan`:
                            // $result_pengembalian = mysqli_query($conn, "SELECT pb.id, pb.pesanan_id, pr.nama AS nama_produk, c.nama AS nama_pelanggan, pb.alasan_pengembalian, pb.status_pengembalian, pb.tanggal_pengajuan FROM pengembalian_barang pb JOIN pesanan ps ON pb.pesanan_id = ps.id JOIN detail_pesanan dp ON ps.id = dp.pesanan_id JOIN produk pr ON dp.produk_id = pr.id JOIN pelanggan c ON ps.pelanggan_id = c.pengguna_id LIMIT $limit OFFSET $offset");
                            // Please choose the correct query based on your actual database schema for pengembalian_barang.
                            // I'm using the first, simpler assumption for the provided code.

                            if (mysqli_num_rows($result_pengembalian) > 0) {
                                while ($row_pengembalian = mysqli_fetch_assoc($result_pengembalian)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['pesanan_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['nama_produk']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['nama_pelanggan']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['alasan_pengembalian']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['status_pengembalian']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_pengembalian['tanggal_pengajuan']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>Tidak ada data pengembalian barang.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($report_type == 'artikel') : ?>
                <h4>Data Artikel</h4>
                <div class="mb-3">
                    <a href="generate_pdf.php?type=artikel" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Penulis</th>
                                <th>Tanggal Publikasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Count total records for pagination
                            $count_query = "SELECT COUNT(*) AS total FROM artikel";
                            $count_result = mysqli_query($conn, $count_query);
                            $total_row = mysqli_fetch_assoc($count_result);
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $limit);

                            $result_artikel = mysqli_query($conn, "SELECT * FROM artikel ORDER BY tanggal_publikasi DESC LIMIT $limit OFFSET $offset");
                            if (mysqli_num_rows($result_artikel) > 0) {
                                while ($row_artikel = mysqli_fetch_assoc($result_artikel)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row_artikel['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_artikel['judul']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_artikel['penulis']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_artikel['tanggal_publikasi']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_artikel['status']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>Tidak ada data artikel.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page - 1; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?type=<?= $report_type; ?>&page=<?= $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else : ?>
                <p>Silakan pilih jenis laporan dari sidebar untuk melihat detailnya.</p>
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
            $('.nav-link.collapsed').click(function() {
                var targetCollapse = $(this).attr('href');
                $(targetCollapse).collapse('toggle');
            });
        });
    </script>
</body>
</html>