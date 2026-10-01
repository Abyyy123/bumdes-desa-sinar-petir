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

// Ambil data penjual
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

$pesan_sukses = '';
$pesan_error = '';

// --- LOGIC UNTUK UPDATE RESI DAN STATUS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_resi') {
    $pesanan_id_to_update = $_POST['pesanan_id_to_ship'];
    $resi_input = isset($_POST['resi_pengiriman']) ? trim($_POST['resi_pengiriman']) : '';

    if (empty($pesanan_id_to_update)) {
        $pesan_error = "ID Pesanan tidak boleh kosong.";
    } else {
        // --- KEAMANAN: Pastikan pesanan ini memang milik penjual ini dan statusnya 'diproses' atau 'dikirim' ---
        $query_verify_ownership = "
            SELECT p.status_pesanan, COUNT(DISTINCT p.id) AS count_orders
            FROM pesanan p
            JOIN detail_pesanan dp ON p.id = dp.pesanan_id
            JOIN produk pr ON dp.produk_id = pr.id
            WHERE p.id = ? AND pr.penjual_id = ?
            GROUP BY p.id;
        ";
        $stmt_verify = mysqli_prepare($conn, $query_verify_ownership);
        mysqli_stmt_bind_param($stmt_verify, 'ii', $pesanan_id_to_update, $penjual_id);
        mysqli_stmt_execute($stmt_verify);
        $result_verify = mysqli_stmt_get_result($stmt_verify);
        $verified_order = mysqli_fetch_assoc($result_verify);
        mysqli_stmt_close($stmt_verify);

        if (!$verified_order || $verified_order['count_orders'] == 0) {
            $pesan_error = "Pesanan tidak ditemukan atau bukan milik Anda.";
        } elseif ($verified_order['status_pesanan'] == 'selesai' || $verified_order['status_pesanan'] == 'dibatalkan') {
            $pesan_error = "Status pesanan tidak bisa diubah karena sudah selesai atau dibatalkan.";
        } else {
            // Logika untuk membuat atau menggunakan resi
            $prefix = "LMP-";
            $final_resi = '';

            if (empty($resi_input)) {
                // Jika penjual tidak memasukkan resi, buat resi unik otomatis
                $final_resi = $prefix . date('Ymd-His') . '-' . $pesanan_id_to_update;
            } else {
                // Jika penjual memasukkan resi, pastikan memiliki prefix
                $final_resi = (strpos(strtoupper($resi_input), $prefix) === 0) ? $resi_input : $prefix . $resi_input;
            }

            // Langkah 1: Cek apakah data pengiriman sudah ada untuk pesanan ini
            $query_check_pengiriman = "SELECT id FROM pengiriman WHERE pesanan_id = ?";
            $stmt_check = mysqli_prepare($conn, $query_check_pengiriman);
            mysqli_stmt_bind_param($stmt_check, 'i', $pesanan_id_to_update);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $pengiriman_exists = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);

            $action_success = false;
            
            if ($pengiriman_exists) {
                // Jika sudah ada, lakukan UPDATE
                $query_action = "
                    UPDATE pengiriman SET nomor_resi = ?, tanggal_pengiriman = NOW(), status_pengiriman = 'dikirim' WHERE pesanan_id = ?
                ";
                $stmt_action = mysqli_prepare($conn, $query_action);
                mysqli_stmt_bind_param($stmt_action, 'si', $final_resi, $pesanan_id_to_update);
            } else {
                // Jika belum ada, lakukan INSERT
                $query_action = "
                    INSERT INTO pengiriman (nomor_resi, pesanan_id, tanggal_pengiriman, status_pengiriman)
                    VALUES (?, ?, NOW(), 'dikirim')
                ";
                $stmt_action = mysqli_prepare($conn, $query_action);
                mysqli_stmt_bind_param($stmt_action, 'si', $final_resi, $pesanan_id_to_update);
            }

            $action_success = mysqli_stmt_execute($stmt_action);
            mysqli_stmt_close($stmt_action);

            // Langkah 2: Update status di tabel 'pesanan' jika berhasil
            if ($action_success) {
                $query_update_pesanan = "
                    UPDATE pesanan SET status_pesanan = 'dikirim' WHERE id = ?
                ";
                $stmt_update = mysqli_prepare($conn, $query_update_pesanan);
                mysqli_stmt_bind_param($stmt_update, 'i', $pesanan_id_to_update);
                $update_success = mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                if ($update_success) {
                    $pesan_sukses = "Pesanan #{$pesanan_id_to_update} berhasil diupdate menjadi 'Dikirim' dengan nomor resi: " . htmlspecialchars($final_resi);
                } else {
                    $pesan_error = "Gagal memperbarui status pesanan. " . mysqli_error($conn);
                }
            } else {
                $pesan_error = "Gagal memproses pengiriman: " . mysqli_error($conn);
            }
        }
    }
}
// --- AKHIR LOGIC UNTUK UPDATE RESI DAN STATUS ---

// --- PENGATURAN FILTER UNTUK STATUS ---
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'semua'; // Default 'semua' jika tidak ada filter

$where_clause = "pr.penjual_id = ? AND (p.status_pesanan = 'diproses' OR p.status_pesanan = 'dikirim')"; // Kondisi default

// Jika ada filter spesifik, ubah kondisi WHERE
if ($filter_status === 'diproses') {
    $where_clause = "pr.penjual_id = ? AND p.status_pesanan = 'diproses'";
} elseif ($filter_status === 'dikirim') {
    $where_clause = "pr.penjual_id = ? AND p.status_pesanan = 'dikirim'";
}

// --- LOGIKA UNTUK PAGINATION DENGAN FILTER ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$query_count_siap_kirim = "
    SELECT COUNT(DISTINCT p.id) AS total_orders
    FROM pesanan p
    JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    JOIN produk pr ON dp.produk_id = pr.id
    WHERE " . $where_clause . ";
";
$stmt_count = mysqli_prepare($conn, $query_count_siap_kirim);
mysqli_stmt_bind_param($stmt_count, 'i', $penjual_id); // Gunakan $penjual_id sebagai parameter
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_records = $row_count['total_orders'];
mysqli_stmt_close($stmt_count);
$total_pages = ceil($total_records / $records_per_page);

// --- AMBIL DATA PESANAN DENGAN FILTER DAN PAGINATION ---
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
        " . $where_clause . "
    GROUP BY
        p.id, p.tanggal_pesanan, pb.nama, p.alamat_pengiriman, p.status_pesanan, p.kode_unik
    ORDER BY
        p.tanggal_pesanan ASC
    LIMIT ? OFFSET ?;
";

$stmt_pesanan_siap_kirim = mysqli_prepare($conn, $query_pesanan_siap_kirim);
mysqli_stmt_bind_param($stmt_pesanan_siap_kirim, 'iii', $penjual_id, $records_per_page, $offset);
mysqli_stmt_execute($stmt_pesanan_siap_kirim);
$result_pesanan_siap_kirim = mysqli_stmt_get_result($stmt_pesanan_siap_kirim);

if ($result_pesanan_siap_kirim) {
    while ($row = mysqli_fetch_assoc($result_pesanan_siap_kirim)) {
        $pesanan_siap_kirim[] = $row;
    }
} else {
    $pesan_error .= "Gagal mengambil data pesanan siap kirim: " . mysqli_error($conn);
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
// Menggunakan $penjual['pengguna_id'] karena 'id' mungkin tidak ada di array $penjual dari query awal
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
    <title>Pengiriman Pesanan - Dashboard Penjual</title>
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
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.7em;
            border-radius: 0.25rem;
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
                            <a class="nav-link submenu active" href="../pengiriman/pengiriman.php">Pengiriman</a>
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
                <div class="collapse" id="artikelMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../artikel/artikel_saya.php">Artikel Saya</a>
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

        <?php if (!empty($pesan_sukses)): ?>
            <div class="alert alert-success" role="alert">
                <?= $pesan_sukses ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $pesan_error ?>
            </div>
        <?php endif; ?>


        <div class="mb-4">
            <h5 class="mb-2">Filter Status:</h5>
            <select id="statusFilter" class="form-control" style="max-width: 250px;">
                <option value="semua" <?= ($filter_status === 'semua') ? 'selected' : '' ?>>Semua Pesanan</option>
                <option value="diproses" <?= ($filter_status === 'diproses') ? 'selected' : '' ?>>Diproses</option>
                <option value="dikirim" <?= ($filter_status === 'dikirim') ? 'selected' : '' ?>>Dikirim</option>
            </select>
        </div>
        <div class="card p-4">
            <p>Berikut adalah daftar pesanan yang berstatus 'Diproses' dan siap untuk dikirim. Masukkan nomor resi dan ubah status pesanan menjadi 'Dikirim'.</p>

            <?php if (empty($pesanan_siap_kirim)): ?>
                <div class="alert alert-info">Tidak ada pesanan yang siap dikirim saat ini.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
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
                                <tr>
                                    <td>#<?= htmlspecialchars($pesanan['pesanan_id']) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($pesanan['tanggal_pesanan'])) ?></td>
                                    <td><?= htmlspecialchars($pesanan['nama_pembeli']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($pesanan['alamat_pengiriman'])) ?></td>
                                    <td>Rp<?= number_format($pesanan['total_harga_pesanan_penjual'], 0, ',', '.') ?></td>
                                    <td><?= getStatusPesananText($pesanan['status_pesanan']) ?></td>
                                    <td>
                                        <?php 
                                        $resi_data = null; // Inisialisasi variabel
                                        // Query untuk mengambil nomor resi dari tabel 'pengiriman'
                                        $query_resi = "SELECT nomor_resi FROM pengiriman WHERE pesanan_id = ?";
                                        $stmt_resi = mysqli_prepare($conn, $query_resi);
                                        mysqli_stmt_bind_param($stmt_resi, 'i', $pesanan['pesanan_id']);
                                        mysqli_stmt_execute($stmt_resi);
                                        $result_resi = mysqli_stmt_get_result($stmt_resi);
                                        if ($result_resi) {
                                            $resi_data = mysqli_fetch_assoc($result_resi);
                                        }
                                        mysqli_stmt_close($stmt_resi);
                                        if ($resi_data && !empty($resi_data['nomor_resi'])) {
                                            echo htmlspecialchars($resi_data['nomor_resi']);
                                        } else {
                                            echo 'Menunggu Resi';
                                        }
                                        ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($pesanan['status_pesanan'] == 'diproses') : ?>
                                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#kirimModal" data-pesanan-id="<?= $pesanan['pesanan_id'] ?>">
                                                Kirim
                                            </button>
                                        <?php elseif ($pesanan['status_pesanan'] == 'dikirim' && $resi_data) : ?>
                                            <a href="cetak_resi.php?resi=<?= urlencode($resi_data['nomor_resi']) ?>" target="_blank" class="btn btn-sm btn-success">
                                                <i class="fas fa-print"></i> Cetak Resi
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page + 1 ?>" aria-label="Next">
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

<div class="modal fade" id="kirimModal" tabindex="-1" aria-labelledby="kirimModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="kirimModalLabel">Kirim Pesanan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_resi">
                    <input type="hidden" name="pesanan_id_to_ship" id="pesanan_id_to_ship">
                    <p>Apakah Anda yakin ingin mengirim pesanan ini? Sistem akan secara otomatis membuat nomor resi dan mengubah status pesanan menjadi **'Dikirim'**.</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Konfirmasi & Kirim</button>
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

        // Skrip untuk mengisi ID pesanan ke modal saat tombol "Input Resi / Kirim" diklik
        $('#kirimModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Tombol yang memicu modal
            var pesananId = button.data('pesanan-id'); // Ambil data-pesanan-id dari tombol

            var modal = $(this);
            modal.find('#pesanan_id_to_ship').val(pesananId); // Set nilai hidden input di modal
        });
    });

    document.getElementById('statusFilter').addEventListener('change', function() {
        var selectedStatus = this.value;
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('status', selectedStatus);
        currentUrl.searchParams.set('page', 1); // Reset halaman ke 1 saat filter berubah
        window.location.href = currentUrl.toString();
    });
</script>

</body>
</html>