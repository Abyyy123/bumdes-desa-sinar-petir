<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini jika berbeda

// --- 1. Keamanan: Cek apakah pengguna sudah login sebagai penjual ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// --- 2. Validasi Parameter URL (nomor_resi) ---
if (!isset($_GET['resi']) || empty($_GET['resi'])) {
    die("Nomor resi tidak valid. Kembali ke halaman sebelumnya.");
}
$nomor_resi = $_GET['resi'];

// Ambil data penjual untuk verifikasi dan tampilan
$query_penjual = "SELECT pengguna_id FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);

if (!$penjual) {
    die("Anda tidak memiliki akses ke halaman ini.");
}

// --- 3. Ambil data pengiriman dan pesanan dari database ---
$query_data_resi = "
    SELECT
        p.id AS pesanan_id,
        p.kode_unik,
        p.tanggal_pesanan,
        p.alamat_pengiriman,
        pe.nama AS nama_pembeli,
        pe.nomor_telepon AS nomor_telepon_pembeli,
        t.nama_toko AS nama_penjual,
        t.alamat AS alamat_penjual,
        t.nomor_telepon AS nomor_telepon_penjual,
        dp.quantity,
        pr.nama
    FROM
        pengiriman k
    JOIN
        pesanan p ON k.pesanan_id = p.id
    JOIN
        pelanggan pe ON p.pelanggan_id = pe.pengguna_id
    JOIN
        detail_pesanan dp ON p.id = dp.pesanan_id
    JOIN
        produk pr ON dp.produk_id = pr.id
    JOIN
        penjual t ON pr.penjual_id = t.pengguna_id
    WHERE
        k.nomor_resi = ? AND t.pengguna_id = ?
    GROUP BY
        p.id, p.kode_unik, k.nomor_resi, pe.nama, t.nama_toko, dp.quantity, pr.nama
    LIMIT 1;
";

$stmt_data_resi = mysqli_prepare($conn, $query_data_resi);
mysqli_stmt_bind_param($stmt_data_resi, 'si', $nomor_resi, $penjual['pengguna_id']);
mysqli_stmt_execute($stmt_data_resi);
$result_data_resi = mysqli_stmt_get_result($stmt_data_resi);

if (!$result_data_resi || mysqli_num_rows($result_data_resi) == 0) {
    die("Data pesanan tidak ditemukan atau bukan milik Anda.");
}

$data_resi = mysqli_fetch_assoc($result_data_resi);
mysqli_stmt_close($stmt_data_resi);

// Ambil daftar produk untuk tampilan
$query_produk = "SELECT pr.nama, dp.quantity, dp.harga_satuan FROM detail_pesanan dp
                 JOIN produk pr ON dp.produk_id = pr.id
                 WHERE dp.pesanan_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
mysqli_stmt_bind_param($stmt_produk, 'i', $data_resi['pesanan_id']);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$daftar_produk = mysqli_fetch_all($result_produk, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_produk);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Resi: <?= htmlspecialchars($nomor_resi) ?></title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .shipping-label {
            border: 2px dashed #333;
            padding: 20px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .header, .content, .footer {
            margin-bottom: 20px;
        }
        .header h1, .header h2 {
            margin: 0 0 10px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .info-col {
            width: 48%;
        }
        .info-col h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .product-list li {
            border-bottom: 1px dashed #eee;
            padding: 5px 0;
        }
        .print-button {
            text-align: center;
            margin-top: 20px;
        }
        .print-button button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        @media print {
            body { background-color: #fff; }
            .container { box-shadow: none; padding: 0; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="shipping-label">
        <div class="header">
            <h1>Bukti Pengiriman</h1>
            <h2>Nomor Resi: <span style="font-weight: bold;"><?= htmlspecialchars($nomor_resi) ?></span></h2>
        </div>

        <div class="info-row">
            <div class="info-col">
                <h3>Dari:</h3>
                <p>
                    <strong><?= htmlspecialchars($data_resi['nama_penjual']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($data_resi['alamat_penjual'])) ?><br>
                    <strong>Telp:</strong> <?= htmlspecialchars($data_resi['nomor_telepon_penjual']) ?>
                </p>
            </div>
            <div class="info-col">
                <h3>Kepada:</h3>
                <p>
                    <strong><?= htmlspecialchars($data_resi['nama_pembeli']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($data_resi['alamat_pengiriman'])) ?><br>
                    <strong>Telp:</strong> <?= htmlspecialchars($data_resi['nomor_telepon_pembeli']) ?>
                </p>
            </div>
        </div>

        <div class="content">
            <h3>Detail Pesanan:</h3>
            <p>Tanggal Pesanan: <?= date('d M Y', strtotime($data_resi['tanggal_pesanan'])) ?></p>
            <p>Kode Unik Pesanan: #<?= htmlspecialchars($data_resi['kode_unik']) ?></p>

            <h3>Daftar Produk:</h3>
            <ul class="product-list">
                <?php foreach ($daftar_produk as $produk) : ?>
                    <li>
                        <?= htmlspecialchars($produk['quantity']) ?>x <?= htmlspecialchars($produk['nama']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <div class="print-button">
        <button onclick="window.print()">Cetak Label Pengiriman</button>
    </div>
</div>

</body>
</html>