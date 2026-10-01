<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini jika berbeda

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
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

// Ambil data penjual (untuk mendapatkan penjual_id dan data profil toko)
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

$penjual_id = $penjual['pengguna_id']; // ID penjual (untuk kueri pendapatan berdasarkan produk)

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
        // Alihkan kembali ke halaman pengiriman.php setelah update
        echo "<script>alert('Profil berhasil diperbarui.'); window.location.href='pengiriman.php';</script>";
    } else {
        echo "<script>alert('Tidak ada perubahan pada profil.'); window.location.href='pengiriman.php';</script>";
    }
    mysqli_stmt_close($stmt_update_penjual);
    mysqli_stmt_close($stmt_update_pengguna);
}
// --- AKHIR PROSES UPDATE PROFIL ---

// --- AMBIL SEMUA METODE PEMBAYARAN DARI TABEL metode_pembayaran ---
$metode_pembayaran_options = [];
// Pastikan aktif_platform sesuai dengan data di database Anda untuk 'bank_transfer' dan 'e_wallet'
$query_metode_pembayaran = "SELECT id, nama_metode FROM metode_pembayaran WHERE tipe_pembayaran = TRUE";
$result_metode_pembayaran = mysqli_query($conn, $query_metode_pembayaran);
if ($result_metode_pembayaran) {
    while ($row = mysqli_fetch_assoc($result_metode_pembayaran)) {
        $metode_pembayaran_options[] = $row;
    }
} else {
    // Handle error if query fails
    error_log("Error fetching payment methods: " . mysqli_error($conn));
}
// AKHIR BARIS BARU DITAMBAHKAN


// --- AMBIL DATA BANK DEFAULT DARI tabel pengaturan_pembayaran_penjual ---
$default_metode_penarikan_id = ''; // Menambahkan variabel untuk ID metode pembayaran
$default_nama_bank = ''; // Akan diisi dari nama_metode yang dipilih
$default_nomor_rekening = '';
$default_nama_pemilik_rekening = '';

$query_default_bank = "SELECT metode_pembayaran_id, detail_akun, nama_pemilik_akun
                       FROM pengaturan_pembayaran_penjual
                       WHERE pengguna_id = ?";
$stmt_default_bank = mysqli_prepare($conn, $query_default_bank);
mysqli_stmt_bind_param($stmt_default_bank, 'i', $penjual_id);
mysqli_stmt_execute($stmt_default_bank);
$result_default_bank = mysqli_stmt_get_result($stmt_default_bank);
$default_bank_data = mysqli_fetch_assoc($result_default_bank);
mysqli_stmt_close($stmt_default_bank);

if ($default_bank_data) {
    $default_metode_penarikan_id = $default_bank_data['metode_pembayaran_id'];
    $default_nomor_rekening = $default_bank_data['detail_akun'];
    $default_nama_pemilik_rekening = $default_bank_data['nama_pemilik_akun'];

    // Ambil nama bank dari tabel metode_pembayaran berdasarkan ID
    $query_nama_bank_default = "SELECT nama_metode FROM metode_pembayaran WHERE id = ?";
    $stmt_nama_bank_default = mysqli_prepare($conn, $query_nama_bank_default);
    mysqli_stmt_bind_param($stmt_nama_bank_default, 'i', $default_metode_penarikan_id);
    mysqli_stmt_execute($stmt_nama_bank_default);
    $result_nama_bank_default = mysqli_stmt_get_result($stmt_nama_bank_default);
    $row_nama_bank_default = mysqli_fetch_assoc($result_nama_bank_default);
    if ($row_nama_bank_default) {
        $default_nama_bank = $row_nama_bank_default['nama_metode'];
    }
    mysqli_stmt_close($stmt_nama_bank_default);
}


$pesan_sukses = '';
$pesan_error = '';

// --- HITUNG SALDO TERSEDIA ---
// Total Pendapatan dari pesanan Selesai untuk penjual ini
$query_pendapatan_bersih = "SELECT SUM(dp.harga_satuan * dp.quantity) AS total_pendapatan
                            FROM pesanan p
                            JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                            JOIN produk pr ON dp.produk_id = pr.id
                            WHERE p.status_pesanan = 'selesai' AND pr.penjual_id = ?";
$stmt_pendapatan = mysqli_prepare($conn, $query_pendapatan_bersih);
mysqli_stmt_bind_param($stmt_pendapatan, 'i', $penjual_id);
mysqli_stmt_execute($stmt_pendapatan);
$result_pendapatan = mysqli_stmt_get_result($stmt_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total_pendapatan'] ?: 0;
mysqli_stmt_close($stmt_pendapatan);

// Total Penarikan yang Sudah Selesai/Disetujui
$query_total_penarikan = "SELECT SUM(jumlah_penarikan) AS total_ditarik
                          FROM penarikan_dana
                          WHERE pengguna_id = ? AND status_penarikan = 'selesai'";
$stmt_penarikan = mysqli_prepare($conn, $query_total_penarikan);
mysqli_stmt_bind_param($stmt_penarikan, 'i', $user_id);
mysqli_stmt_execute($stmt_penarikan);
$result_penarikan = mysqli_stmt_get_result($stmt_penarikan);
$row_penarikan = mysqli_fetch_assoc($result_penarikan);
$total_ditarik = $row_penarikan['total_ditarik'] ?: 0;
mysqli_stmt_close($stmt_penarikan);

$saldo_tersedia = $total_pendapatan - $total_ditarik;


// --- PROSES PENGAJUAN PENARIKAN (Jika ada POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_penarikan'])) {
    $jumlah_penarikan = filter_input(INPUT_POST, 'jumlah_penarikan', FILTER_VALIDATE_FLOAT);
    // PERUBAHAN KRUSIAL DI SINI: Sesuaikan 'name' dengan 'metode_pembayaran' dari form
    $metode_penarikan_id_selected = filter_input(INPUT_POST, 'metode_pembayaran', FILTER_VALIDATE_INT);
    $nomor_rekening_input = filter_input(INPUT_POST, 'nomor_rekening', FILTER_SANITIZE_STRING);
    $nama_pemilik_rekening_input = filter_input(INPUT_POST, 'nama_pemilik_rekening', FILTER_SANITIZE_STRING);

    // Ambil nama metode pembayaran berdasarkan ID yang dipilih
    $selected_metode_name = '';
    foreach ($metode_pembayaran_options as $option) {
        if ($option['id'] == $metode_penarikan_id_selected) {
            $selected_metode_name = $option['nama_metode'];
            break;
        }
    }

    // Validasi input
    if ($jumlah_penarikan <= 0 || $jumlah_penarikan === false) {
        $pesan_error = "Jumlah penarikan tidak valid.";
    } elseif ($jumlah_penarikan > $saldo_tersedia) {
        $pesan_error = "Jumlah penarikan melebihi saldo yang tersedia.";
    } elseif (empty($metode_penarikan_id_selected) || empty($nomor_rekening_input) || empty($nama_pemilik_rekening_input)) {
        // Pesan error ini akan muncul jika salah satu dari 3 field ini kosong
        $pesan_error = "Semua kolom metode penarikan (Metode, Nomor Rekening, Nama Pemilik) harus diisi.";
    } else {
        // Cek apakah ada permintaan penarikan 'pending' yang sedang berjalan
        $query_cek_pending = "SELECT COUNT(id) AS total_pending FROM penarikan_dana WHERE pengguna_id = ? AND status_penarikan = 'pending'";
        $stmt_cek_pending = mysqli_prepare($conn, $query_cek_pending);
        mysqli_stmt_bind_param($stmt_cek_pending, 'i', $user_id);
        mysqli_stmt_execute($stmt_cek_pending);
        $result_cek_pending = mysqli_stmt_get_result($stmt_cek_pending);
        $row_cek_pending = mysqli_fetch_assoc($result_cek_pending);
        mysqli_stmt_close($stmt_cek_pending);

        if ($row_cek_pending['total_pending'] > 0) {
            $pesan_error = "Anda memiliki permintaan penarikan yang sedang diproses. Mohon tunggu hingga selesai.";
        } else {
            // Masukkan permintaan penarikan ke database
            // MODIFIKASI BARIS INI: Gunakan $selected_metode_name sebagai nama_bank
            $query_insert_penarikan = "INSERT INTO penarikan_dana (pengguna_id, jumlah_penarikan, tanggal_permintaan, status_penarikan, metode_penarikan, nama_bank, nomor_rekening, nama_pemilik_rekening) VALUES (?, ?, NOW(), 'pending', ?, ?, ?, ?)";
            $stmt_insert_penarikan = mysqli_prepare($conn, $query_insert_penarikan);
            if ($stmt_insert_penarikan) {
                // MODIFIKASI BARIS INI: Bind $selected_metode_name untuk 'nama_bank'
                mysqli_stmt_bind_param($stmt_insert_penarikan, 'idssss', $user_id, $jumlah_penarikan, $selected_metode_name, $selected_metode_name, $nomor_rekening_input, $nama_pemilik_rekening_input);
                if (mysqli_stmt_execute($stmt_insert_penarikan)) {
                    $pesan_sukses = "Permintaan penarikan dana berhasil diajukan. Mohon tunggu proses verifikasi.";
                    header('Location: penarikan.php?success=' . urlencode($pesan_sukses));
                    exit;
                } else {
                    $pesan_error = "Gagal mengajukan permintaan penarikan: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_insert_penarikan);
            } else {
                $pesan_error = "MySQLi prepare error: " . mysqli_error($conn);
            }
        }
    }
}

// Tangani pesan sukses dari redirect
if (isset($_GET['success'])) {
    $pesan_sukses = htmlspecialchars($_GET['success']);
}


// --- BAGIAN UNTUK PAGINATION ---
$limit = 10; // Jumlah item per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Ambil total jumlah riwayat penarikan untuk pagination
$query_total_riwayat = "SELECT COUNT(id) AS total FROM penarikan_dana WHERE pengguna_id = ?";
$stmt_total_riwayat = mysqli_prepare($conn, $query_total_riwayat);
mysqli_stmt_bind_param($stmt_total_riwayat, 'i', $user_id);
mysqli_stmt_execute($stmt_total_riwayat);
$result_total_riwayat = mysqli_stmt_get_result($stmt_total_riwayat);
$row_total_riwayat = mysqli_fetch_assoc($result_total_riwayat);
$total_riwayat = $row_total_riwayat['total'];
mysqli_stmt_close($stmt_total_riwayat);

$total_pages = ceil($total_riwayat / $limit);

// --- AMBIL RIWAYAT PENARIKAN DENGAN LIMIT UNTUK PAGINATION ---
$riwayat_penarikan = [];
$query_riwayat_penarikan = "SELECT id, jumlah_penarikan, tanggal_permintaan, status_penarikan, metode_penarikan, nama_bank, nomor_rekening, nama_pemilik_rekening, catatan_admin
                            FROM penarikan_dana
                            WHERE pengguna_id = ?
                            ORDER BY tanggal_permintaan DESC
                            LIMIT ?, ?";
$stmt_riwayat = mysqli_prepare($conn, $query_riwayat_penarikan);
mysqli_stmt_bind_param($stmt_riwayat, 'iii', $user_id, $start, $limit);
mysqli_stmt_execute($stmt_riwayat);
$result_riwayat = mysqli_stmt_get_result($stmt_riwayat);
while ($row = mysqli_fetch_assoc($result_riwayat)) {
    $riwayat_penarikan[] = $row;
}
mysqli_stmt_close($stmt_riwayat);
// --- AKHIR BAGIAN UNTUK PAGINATION ---


// =====================================================================
// DATA STATISTIK UNTUK SIDEBAR/FOOTER (Opsional, dari file lain)
// =====================================================================
// Ini adalah bagian dari kode yang sama di laporan_keuangan.php, saya sertakan untuk konsistensi.
// Anda bisa menghapusnya jika tidak ingin menampilkannya di halaman ini.

$query_menunggu = "SELECT COUNT(DISTINCT p.id) AS total FROM pesanan p
                   JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                   JOIN produk pr ON dp.produk_id = pr.id
                   WHERE pr.penjual_id = ? AND p.status_pesanan = 'menunggu_pembayaran'";
$stmt_menunggu = mysqli_prepare($conn, $query_menunggu);
mysqli_stmt_bind_param($stmt_menunggu, 'i', $penjual['pengguna_id']);
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
mysqli_stmt_bind_param($stmt_diproses, 'i', $penjual['pengguna_id']);
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
mysqli_stmt_bind_param($stmt_dikirim, 'i', $penjual['pengguna_id']);
mysqli_stmt_execute($stmt_dikirim);
$result_dikirim = mysqli_stmt_get_result($stmt_dikirim);
$row_dikirim = mysqli_fetch_assoc($result_dikirim);
$total_dikirim = $row_dikirim['total'] ?? 0;
mysqli_stmt_close($stmt_dikirim);

$query_produk = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
mysqli_stmt_bind_param($stmt_produk, 'i', $penjual['pengguna_id']);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'] ?? 0;
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
mysqli_stmt_bind_param($stmt_stok_rendah, 'i', $penjual['pengguna_id']);
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
mysqli_stmt_bind_param($stmt_pendapatan_total_dashboard, 'i', $penjual['pengguna_id']);
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
    <title>Penarikan Dana - Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* CSS umum (dari file-file Anda sebelumnya) */
        /* Palet Warna Hijau Hutan & Emas:
        - #FBFBFB (Krem Pucat - Latar Belakang Umum)
        - #0A422F (Hijau Hutan Gelap - Navbar, Kartu Total Produk)
        - #1C6B4D (Hijau Forest Lebih Terang - Sidebar)
        - #C7A77C (Krem Keemasan - Sidebar Hover/Active)
        - #EFEAD8 (Krem Pudar - Submenu Sidebar)
        - #D4C29E (Krem Gelap - Kartu Menunggu Pembayaran)
        - #8B9B7A (Hijau Zaitun Pudar - Kartu Sedang Diproses)
        - #4F7942 (Hijau Gelap Medium - Kartu Sedang Dikirim)
        - #8B0000 (Merah Gelap/Marun - Kartu Stok Rendah)
        - #B8860B (Kuning Emas Tua - Kartu Total Pendapatan)
        */

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
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #0A422F;">
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
            <form action="" method="POST" enctype="multipart/form-data"> <div class="modal-header">
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
                <a class="nav-link collapsed" data-toggle="collapse" href="#pembayaranMenu" role="button" aria-expanded="true" aria-controls="pembayaranMenu">
                    <i class="fas fa-wallet"></i>
                    <span class="ml-2">Pembayaran</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="pembayaranMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../riwayat/pembayaran.php">Riwayat Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="penarikan.php">Penarikan Dana</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../catatan/transaksi_lain.php">Catatan Transaksi Lain</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../metode/metode_pembayaran.php">Metode Pembayaran</a>
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
        <h1>Penarikan Dana</h1>
        <hr>

        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $pesan_sukses ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $pesan_error ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card p-4 mb-4">
            <h4>Saldo Tersedia untuk Ditarik</h4>
            <h2 class="text-success">Rp<?= number_format($saldo_tersedia, 0, ',', '.') ?></h2>
            <p class="text-muted">Total pendapatan dari pesanan selesai dikurangi total penarikan yang sudah selesai.</p>

            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tarikDanaModal">
                Ajukan Penarikan Dana
            </button>
            <?php if (empty($default_bank_data)): ?>
                <div class="alert alert-warning mt-3" role="alert">
                    Anda belum mengatur rekening bank/e-wallet default untuk penarikan. Silakan atur di halaman <a href="../metode/metode_pembayaran.php" class="alert-link">Metode Pembayaran</a>.
                </div>
            <?php endif; ?>
        </div>

        <div class="card p-4">
            <h4>Riwayat Permintaan Penarikan</h4>
            <?php if (empty($riwayat_penarikan) && $page == 1) : ?>
                <div class="alert alert-info">Tidak ada riwayat permintaan penarikan dana.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Jumlah</th>
                                <th>Tanggal Permintaan</th>
                                <th>Status</th>
                                <th>Metode</th>
                                <th>No. Rekening</th>
                                <th>A.N. Rekening</th>
                                <th>Catatan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $start + 1; // Sesuaikan nomor awal untuk pagination ?>
                            <?php foreach ($riwayat_penarikan as $permintaan) : ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>Rp<?= number_format($permintaan['jumlah_penarikan'], 0, ',', '.') ?></td>
                                    <td><?= date('d M Y H:i', strtotime($permintaan['tanggal_permintaan'])) ?></td>
                                    <td>
                                        <?php
                                            $status_class = '';
                                            switch ($permintaan['status_penarikan']) {
                                                case 'pending':
                                                    $status_class = 'badge-info';
                                                    break;
                                                case 'diproses':
                                                    $status_class = 'badge-primary';
                                                    break;
                                                case 'selesai':
                                                    $status_class = 'badge-success';
                                                    break;
                                                case 'ditolak':
                                                    $status_class = 'badge-danger';
                                                    break;
                                                default:
                                                    $status_class = 'badge-secondary';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?= htmlspecialchars(ucwords($permintaan['status_penarikan'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($permintaan['metode_penarikan']) ?></td>
                                    <td><?= htmlspecialchars($permintaan['nomor_rekening']) ?></td>
                                    <td><?= htmlspecialchars($permintaan['nama_pemilik_rekening']) ?></td>
                                    <td><?= htmlspecialchars($permintaan['catatan_admin'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
        </div>

    </div>
</div>

<div class="modal fade" id="tarikDanaModal" tabindex="-1" aria-labelledby="tarikDanaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="tarikDanaModalLabel">Ajukan Penarikan Dana</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="saldoTersediaModal">Saldo Tersedia</label>
                        <input type="text" class="form-control" id="saldoTersediaModal" value="Rp<?= number_format($saldo_tersedia, 0, ',', '.') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="jumlahPenarikan">Jumlah Penarikan (Minimum Rp10.000)</label>
                        <input type="number" step="1000" min="10000" class="form-control" id="jumlahPenarikan" name="jumlah_penarikan" required>
                    </div>
                    <div class="form-group">
                        <label for="metodePenarikan">Metode Penarikan</label>
                        <select class="form-control" id="metodePenarikan" name="metode_pembayaran" required>
                            <?php
                            // Pastikan array tidak kosong sebelum perulangan
                            if (!empty($metode_pembayaran_options)):
                                foreach ($metode_pembayaran_options as $option): ?>
                                <option value="<?= htmlspecialchars($option['id']) ?>"
                                    <?= ($option['id'] == $default_metode_penarikan_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($option['nama_metode']) ?>
                                </option>
                            <?php endforeach;
                            else: ?>
                                <option value="">Tidak ada metode penarikan tersedia</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="namaBank">Nama Bank / E-Wallet</label>
                        <input type="text" class="form-control" id="namaBank" name="nama_bank" placeholder="Akan terisi otomatis berdasarkan Metode Penarikan" value="" readonly>
                        </div>
                    <div class="form-group">
                        <label for="nomorRekening">Nomor Rekening / ID E-Wallet</label>
                        <input type="text" class="form-control" id="nomorRekening" name="nomor_rekening" placeholder="Contoh: 1234567890 (Nomor Rekening) atau 0812xxxx (Nomor Telepon E-Wallet)" value="<?= htmlspecialchars($default_nomor_rekening) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="namaPemilikRekening">Nama Pemilik Rekening / Akun E-Wallet</label>
                        <input type="text" class="form-control" id="namaPemilikRekening" name="nama_pemilik_rekening" placeholder="Sesuai nama di rekening bank atau akun e-wallet" value="<?= htmlspecialchars($default_nama_pemilik_rekening) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="ajukan_penarikan" class="btn btn-primary">Ajukan Penarikan</button>
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
        // Skrip untuk dropdown profil
        $('.dropdown-toggle').dropdown();

        // Skrip untuk memastikan modal tetap terbuka jika ada error setelah submit
        <?php if (!empty($pesan_error) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_penarikan'])): ?>
            $('#tarikDanaModal').modal('show');
        <?php endif; ?>

        // Menambahkan event listener untuk dropdown metode penarikan
        $('#metodePenarikan').change(function() {
            var selectedMetodeId = $(this).val();
            // Data metode pembayaran yang sudah di-encode dari PHP
            var metodeOptions = <?= json_encode($metode_pembayaran_options); ?>;
            var selectedMetodeName = '';

            for (var i = 0; i < metodeOptions.length; i++) {
                if (metodeOptions[i].id == selectedMetodeId) {
                    selectedMetodeName = metodeOptions[i].nama_metode;
                    break;
                }
            }
            $('#namaBank').val(selectedMetodeName);
        });

        // Panggil change event saat modal dimuat untuk mengisi namaBank jika ada default
        // Ini akan memastikan `namaBank` terisi dengan nama metode yang terpilih pertama kali
        $('#tarikDanaModal').on('show.bs.modal', function (e) {
            $('#metodePenarikan').trigger('change');
        });

        // Memastikan namaBank terisi sesuai metode yang terpilih saat halaman dimuat
        // (jika ada default_metode_penarikan_id yang diset)
        var initialSelectedMetodeId = $('#metodePenarikan').val();
        if (initialSelectedMetodeId) {
            var metodeOptions = <?= json_encode($metode_pembayaran_options); ?>;
            var initialSelectedMetodeName = '';
            for (var i = 0; i < metodeOptions.length; i++) {
                if (metodeOptions[i].id == initialSelectedMetodeId) {
                    initialSelectedMetodeName = metodeOptions[i].nama_metode;
                    break;
                }
            }
            $('#namaBank').val(initialSelectedMetodeName);
        }
    });
</script>

</body>
</html>