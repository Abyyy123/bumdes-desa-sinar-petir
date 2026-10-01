<?php
session_start();
include('../../../koneksi/koneksi.php');
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}
$user_id = $_SESSION['pengguna_id'];
// Ambil data pengguna (untuk mendapatkan nama lengkap)
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);
if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}
// Ambil data penjual termasuk foto
$query_penjual = "SELECT nama_toko, nama_pemilik, email, username, foto, alamat, nomor_telepon, tanggal_lahir, jenis_kelamin FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
if (!$penjual) {
    echo "<script>alert('Data penjual tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}
// Proses update profil
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
    $upload_dir = '../../../img/foto/';
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
        echo "<script>alert('Profil berhasil diperbarui.'); window.location.href='../../dashboard_penjual.php';</script>";
    } else {
        echo "<script>alert('Tidak ada perubahan pada profil.'); window.location.href='../../dashboard_penjual.php';</script>";
    }
    mysqli_stmt_close($stmt_update_penjual);
    mysqli_stmt_close($stmt_update_pengguna);
}
// Ambil ID produk dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID produk tidak valid.'); window.location.href='daftar_produk.php';</script>";
    exit;
}
$produk_id = $_GET['id'];
// Ambil data produk berdasarkan ID dan user ID (untuk keamanan)
$query_produk = "SELECT
                    id,
                    unit_usaha_id,
                    nama,
                    deskripsi,
                    harga,
                    stok,
                    gambar,
                    asal_desa,
                    kategori_id,
                    berat,
                    status_produk,
                    tanggal_publikasi
                FROM produk
                WHERE id = ? AND penjual_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
mysqli_stmt_bind_param($stmt_produk, 'ii', $produk_id, $user_id);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$data_produk = mysqli_fetch_assoc($result_produk);
mysqli_stmt_close($stmt_produk);
if (!$data_produk) {
    echo "<script>alert('Produk tidak ditemukan atau bukan milik Anda.'); window.location.href='daftar_produk.php';</script>";
    exit;
}
// Ambil data kategori produk
$query_kategori = "SELECT id, nama_kategori FROM kategori_produk";
$result_kategori = mysqli_query($conn, $query_kategori);
$daftar_kategori = mysqli_fetch_all($result_kategori, MYSQLI_ASSOC);
mysqli_free_result($result_kategori);
// Ambil data unit usaha (sesuai dengan pengguna yang login)
$query_unit_usaha = "SELECT id, nama FROM unit_usaha WHERE bumdes_id = (SELECT bumdes_id FROM pengguna WHERE id = ?)";
$stmt_unit_usaha = mysqli_prepare($conn, $query_unit_usaha);
mysqli_stmt_bind_param($stmt_unit_usaha, 'i', $user_id);
mysqli_stmt_execute($stmt_unit_usaha);
$result_unit_usaha = mysqli_stmt_get_result($stmt_unit_usaha);
$daftar_unit_usaha = mysqli_fetch_all($result_unit_usaha, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_unit_usaha);
// Ambil gambar dan video tambahan produk dari produk_media
$query_media_tambahan = "SELECT id, jenis_media, nama_file FROM produk_media WHERE produk_id = ?";
$stmt_media_tambahan = mysqli_prepare($conn, $query_media_tambahan);
mysqli_stmt_bind_param($stmt_media_tambahan, 'i', $produk_id);
mysqli_stmt_execute($stmt_media_tambahan);
$result_media_tambahan = mysqli_stmt_get_result($stmt_media_tambahan);
$media_tambahan = mysqli_fetch_all($result_media_tambahan, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_media_tambahan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk - Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        .img-thumbnail-small {
            width: 80px;
            height: auto;
            margin-right: 5px;
            margin-bottom: 5px;
            border: 1px solid #ddd;
            padding: 5px;
            display: inline-block;
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
    <?php include('sidebar.php'); ?>
    <div class="content">
        <div class="pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Edit Produk</h1>
        </div>
        <form action="proses_edit_produk.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= htmlspecialchars($data_produk['id']) ?>">
            <div class="form-group">
                <label for="nama_produk">Nama Produk</label>
                <input type="text" class="form-control" id="nama_produk" name="nama_produk" value="<?= htmlspecialchars($data_produk['nama']) ?>" required>
            </div>
            <div class="form-group">
                <label for="deskripsi">Deskripsi</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($data_produk['deskripsi']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="harga">Harga</label>
                <input type="number" class="form-control" id="harga" name="harga" value="<?= htmlspecialchars($data_produk['harga']) ?>" required step="0.01" min="0">
            </div>
            <div class="form-group">
                <label for="stok">Stok</label>
                <input type="number" class="form-control" id="stok" name="stok" value="<?= htmlspecialchars($data_produk['stok']) ?>" required min="0">
            </div>
            <div class="form-group">
                <label for="kategori_id">Kategori</label>
                <select class="form-control" id="kategori_id" name="kategori_id" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($daftar_kategori as $kategori): ?>
                        <option value="<?php echo htmlspecialchars($kategori['id']); ?>" <?php if ($data_produk['kategori_id'] == $kategori['id']) echo 'selected'; ?>><?php echo htmlspecialchars($kategori['nama_kategori']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="unit_usaha_id">Unit Usaha</label>
                <select class="form-control" id="unit_usaha_id" name="unit_usaha_id" required>
                    <option value="">Pilih Unit Usaha</option>
                    <?php foreach ($daftar_unit_usaha as $unit_usaha): ?>
                        <option value="<?php echo htmlspecialchars($unit_usaha['id']); ?>" <?php if ($data_produk['unit_usaha_id'] == $unit_usaha['id']) echo 'selected'; ?>><?php echo htmlspecialchars($unit_usaha['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="asal_desa">Asal Desa</label>
                <input type="text" class="form-control" id="asal_desa" name="asal_desa" value="<?php echo htmlspecialchars($data_produk['asal_desa']); ?>">
            </div>
            <div class="form-group">
                <label for="berat">Berat (kg)</label>
                <input type="number" class="form-control" id="berat" name="berat" value="<?php echo htmlspecialchars($data_produk['berat']); ?>" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label for="status_produk">Status Produk</label>
                <select class="form-control" id="status_produk" name="status_produk">
                    <option value="aktif" <?php if ($data_produk['status_produk'] == 'aktif') echo 'selected'; ?>>Aktif</option>
                    <option value="tidak_aktif" <?php if ($data_produk['status_produk'] == 'tidak_aktif') echo 'selected'; ?>>Tidak Aktif</option>
                    <option value="arsip" <?php if ($data_produk['status_produk'] == 'arsip') echo 'selected'; ?>>Arsip</option>
                </select>
            </div>
            <div class="form-group">
                <label for="tanggal_publikasi">Tanggal Publikasi</label>
                <input type="datetime-local" class="form-control" id="tanggal_publikasi" name="tanggal_publikasi" value="<?php echo date('Y-m-d\TH:i', strtotime($data_produk['tanggal_publikasi'])); ?>">
            </div>
            <div class="form-group">
                <label for="gambar_utama">Gambar Utama Produk</label><br>
                <?php if ($data_produk['gambar']): ?>
                    <img src="../../../img/barang/<?php echo htmlspecialchars($data_produk['gambar']); ?>" alt="Gambar Utama" width="100"><br>
                    <small class="text-muted">Kosongkan jika tidak ingin mengubah gambar utama.</small>
                <?php else: ?>
                    <small class="text-muted">Belum ada gambar utama.</small>
                <?php endif; ?>
                <input type="file" class="form-control-file" id="gambar_utama" name="gambar_utama">
            </div>
            <div class="form-group">
                <label for="gambar_tambahan">Gambar dan Video Tambahan Produk</label><br>
                <?php if (!empty($media_tambahan)): ?>
                    <h3>Media Tambahan yang Sudah Ada:</h3>
                    <div class="d-flex flex-wrap">
                        <?php foreach ($media_tambahan as $media): ?>
                            <div class="mr-2 mb-2">
                                <?php if ($media['jenis_media'] == 'gambar'): ?>
                                    <img src="../../../img/barang/<?php echo htmlspecialchars($media['nama_file']); ?>" alt="Gambar Tambahan" width="80">
                                <?php elseif ($media['jenis_media'] == 'video'): ?>
                                    <video src="../../../img/barang/<?php echo htmlspecialchars($media['nama_file']); ?>" width="80" controls></video>
                                <?php endif; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="hapus_media[]" value="<?php echo htmlspecialchars($media['id']); ?>">
                                    <label class="form-check-label">Hapus</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <small class="text-muted">Belum ada gambar dan video tambahan.</small>
                <?php endif; ?>
                <input type="file" class="form-control-file" id="gambar_tambahan" name="gambar_tambahan[]" multiple>
                <small class="text-muted">Anda dapat memilih beberapa gambar dan video.</small>
            </div>
            <button type="submit" class="btn btn-primary" name="simpan_produk">Simpan Perubahan</button>
            <a href="daftar_produk.php" class="btn btn-secondary ml-2">Batal</a>
        </form>
    </div>
</div>
<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y') ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
            <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../js/script.js"></script>
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
    function hapusGambarTambahan(id_gambar) {
        if (confirm('Apakah Anda yakin ingin menghapus gambar ini?')) {
            window.location.href = 'proses_hapus_gambar.php?id=' + id_gambar + '&produk_id=<?= htmlspecialchars($produk_id) ?>';
        }
    }
</script>
</body>
</html>