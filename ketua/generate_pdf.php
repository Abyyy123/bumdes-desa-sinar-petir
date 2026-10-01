<?php
session_start();
include('../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id']) || $_SESSION['role'] !== 'ketua') {
    header('Location: ../login.php');
    exit;
}

require_once '../vendor/autoload.php'; // Adjust path to your Dompdf autoload.php

use Dompdf\Dompdf;
use Dompdf\Options;

$report_type = $_GET['type'] ?? 'overview';

$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan ' . ucfirst(str_replace('_', ' ', $report_type)) . ' - BUMDes</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h2, h3, h4 { text-align: center; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin-bottom: 5px;">Laporan BUMDes Sinar Petir</h2>
        <h3>Laporan ' . ucfirst(str_replace('_', ' ', $report_type)) . '</h3>
        <hr>
    </div>
';

$table_content = '';
$report_title = '';

// Data fetching and HTML generation based on report_type
switch ($report_type) {
    case 'bumdes':
        $report_title = 'Data Informasi BUMDes';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM bumdes");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['alamat']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tahun_berdiri']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['visi']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['misi']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data BUMDes.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'unit_usaha':
        $report_title = 'Data Unit Usaha';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Unit</th>
                    <th>Deskripsi</th>
                    <th>Slug</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM unit_usaha");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['slug']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='4'>Tidak ada data unit usaha.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'anggota':
        $report_title = 'Data Anggota BUMDes';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Nomor Anggota</th>
                    <th>Tanggal Bergabung</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM anggota");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nomor_anggota']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_bergabung']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='4'>Tidak ada data anggota.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'pengguna':
        $report_title = 'Data Pengguna Sistem';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT id, nama, username, email, role, status FROM pengguna");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['username']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['email']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['role']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data pengguna.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'pelanggan':
        $report_title = 'Data Pelanggan E-commerce';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM pelanggan");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['pengguna_id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['email']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nomor_telepon']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['alamat']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data pelanggan.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'penjual':
        $report_title = 'Data Penjual E-commerce';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM penjual");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['pengguna_id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_toko']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_pemilik']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['email']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nomor_telepon']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data penjual.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'kurir':
        $report_title = 'Data Kurir';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kurir</th>
                    <th>Telepon</th>
                    <th>Area Layanan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM kurir");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nomor_telepon']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['area_pengiriman']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='5'>Tidak ada data kurir.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'produk':
        $report_title = 'Data Produk';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT p.id, p.nama, kp.nama_kategori AS kategori, p.harga, p.stok, s.nama_toko AS penjual FROM produk p JOIN kategori_produk kp ON p.kategori_id = kp.id JOIN penjual s ON p.penjual_id = s.pengguna_id");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['kategori']) . "</td>";
                $table_content .= "<td>Rp. " . number_format($row['harga'], 0, ',', '.') . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['stok']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['penjual']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data produk.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'pesanan':
        $report_title = 'Data Pesanan';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pelanggan</th>
                    <th>Tanggal Pesanan</th>
                    <th>Total Harga</th>
                    <th>Status Pesanan</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT p.id, c.nama AS nama_pelanggan, p.tanggal_pesanan, p.total_harga, p.status_pesanan FROM pesanan p JOIN pelanggan c ON p.pelanggan_id = c.pengguna_id");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_pelanggan']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_pesanan']) . "</td>";
                $table_content .= "<td>Rp. " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status_pesanan']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='5'>Tidak ada data pesanan.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'pembayaran':
        $report_title = 'Data Pembayaran';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM pembayaran");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['pesanan_id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_pembayaran']) . "</td>";
                $table_content .= "<td>Rp. " . number_format($row['jumlah_bayar'], 0, ',', '.') . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status_pembayaran']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data pembayaran.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'ulasan':
        $report_title = 'Data Ulasan Produk';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT up.id, p.nama AS nama_produk, c.nama AS nama_pelanggan, up.rating, up.komentar, up.tanggal_ulasan FROM ulasan_produk up JOIN produk p ON up.produk_id = p.id JOIN pelanggan c ON up.pelanggan_id = c.pengguna_id");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_produk']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_pelanggan']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['rating']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['komentar']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_ulasan']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='6'>Tidak ada data ulasan produk.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'pengembalian':
        $report_title = 'Data Pengembalian Barang';
        $table_content .= '
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
            <tbody>';
        $result = mysqli_query($conn, "SELECT pb.id, pb.pesanan_id, pr.nama AS nama_produk, c.nama AS nama_pelanggan, pb.alasan_pengembalian, pb.status_pengembalian, pb.tanggal_pengajuan FROM pengembalian_barang pb JOIN produk pr ON pb.produk_id = pr.id JOIN pelanggan c ON pb.pelanggan_id = c.pengguna_id");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['pesanan_id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_produk']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['nama_pelanggan']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['alasan_pengembalian']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status_pengembalian']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_pengajuan']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='7'>Tidak ada data pengembalian barang.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'transaksi_keuangan':
        $report_title = 'Laporan Transaksi Keuangan';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jenis Transaksi</th>
                    <th>Jumlah</th>
                    <th>Deskripsi</th>
                    <th>Tanggal Transaksi</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM transaksi_keuangan ORDER BY tanggal_transaksi DESC");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['jenis_transaksi']) . "</td>";
                $table_content .= "<td>Rp. " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_transaksi']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='5'>Tidak ada data transaksi keuangan.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    case 'artikel':
        $report_title = 'Data Artikel';
        $table_content .= '
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Judul</th>
                    <th>Penulis</th>
                    <th>Tanggal Publikasi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
        $result = mysqli_query($conn, "SELECT * FROM artikel ORDER BY tanggal_publikasi DESC");
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $table_content .= "<tr>";
                $table_content .= "<td>" . htmlspecialchars($row['id']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['judul']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['penulis']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['tanggal_publikasi']) . "</td>";
                $table_content .= "<td>" . htmlspecialchars($row['status']) . "</td>";
                $table_content .= "</tr>";
            }
        } else {
            $table_content .= "<tr><td colspan='5'>Tidak ada data artikel.</td></tr>";
        }
        $table_content .= '</tbody>';
        break;

    default:
        $report_title = 'Laporan Tidak Ditemukan';
        $table_content = '<tr><td colspan="7">Jenis laporan tidak valid.</td></tr>';
        break;
}

$html .= '
    <h4>' . $report_title . '</h4>
    <table>
        ' . $table_content . '
    </table>
    <div class="footer">
        Dicetak pada: ' . date('d-m-Y H:i:s') . '
    </div>
</body>
</html>';

// Instantiate Dompdf with options
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Load HTML to Dompdf
$dompdf->loadHtml($html);

// (Optional) Setup the paper size and orientation
$dompdf->setPaper('A4', 'landscape'); // or 'portrait'

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$dompdf->stream("Laporan_" . ucfirst(str_replace('_', ' ', $report_type)) . ".pdf", array("Attachment" => true));

exit;
?>