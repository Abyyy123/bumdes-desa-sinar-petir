<?php
session_start();
include('../../koneksi/koneksi.php'); // Sesuaikan path jika perlu

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// Ambil data profil kurir dari tabel 'kurir'
$query_kurir = "SELECT * FROM kurir WHERE id = ?";
$stmt_kurir = mysqli_prepare($conn, $query_kurir);
if (!$stmt_kurir) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_kurir, 'i', $user_id);
mysqli_stmt_execute($stmt_kurir);
$result_kurir = mysqli_stmt_get_result($stmt_kurir);
$user = mysqli_fetch_assoc($result_kurir);

// Jika kurir tidak ditemukan atau statusnya tidak 'aktif', redirect ke login
if (!$user || $user['status'] !== 'aktif') {
    session_destroy();
    header('Location: ../../login.php?error=invalid_kurir_status');
    exit;
}

// Fungsi untuk format tanggal ke Bahasa Indonesia (Ini adalah deklarasi yang benar dan satu-satunya)
function format_tanggal_indonesia($tanggal) {
    $nama_bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecah_tanggal = explode('-', date('Y-m-d', strtotime($tanggal)));
    $tgl = $pecah_tanggal[2];
    $bln = $nama_bulan[(int)$pecah_tanggal[1]];
    $thn = $pecah_tanggal[0];
    $jam = date('H:i', strtotime($tanggal));
    return $tgl . ' ' . $bln . ' ' . $thn . ' ' . $jam;
}

// --- LOGIKA UNTUK MENGAMBIL DETAIL PESANAN ---
$pesanan_id = null;
$pesanan_detail = null;
$pelanggan_detail = null;

if (isset($_GET['id'])) {
    $pesanan_id = $_GET['id'];

    // Query untuk mendapatkan detail pesanan, termasuk info pelanggan, produk, dan bukti pembayaran/pengiriman
    $query_detail_pesanan = "
        SELECT
            p.id AS pesanan_id,
            p.tanggal_pesanan,
            p.status_pesanan,
            p.alamat_pengiriman,
            p.ongkos_kirim,
            p.metode_pembelian,
            p.bukti_pengiriman,
            p.diskon_kupon,
            p.total_harga,
            p.nomor_resi, -- MENAMBAHKAN NOMOR RESI DI SINI
            pm.bukti_transfer,
            pl.nama AS nama_pelanggan,
            pl.email AS email_pelanggan,
            pl.nomor_telepon AS telepon_pelanggan,
            k.nama AS nama_kurir,
            k.nomor_telepon AS telepon_kurir
        FROM pesanan p
        JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
        LEFT JOIN kurir k ON p.kurir_id = k.id
        LEFT JOIN pembayaran pm ON p.id = pm.pesanan_id
        WHERE p.id = ? AND p.kurir_id = ?";

    $stmt_detail_pesanan = mysqli_prepare($conn, $query_detail_pesanan);
    if (!$stmt_detail_pesanan) {
        die("Error preparing detail statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_detail_pesanan, 'ii', $pesanan_id, $user_id);
    mysqli_stmt_execute($stmt_detail_pesanan);
    $result_detail_pesanan = mysqli_stmt_get_result($stmt_detail_pesanan);
    $pesanan_detail = mysqli_fetch_assoc($result_detail_pesanan);

    // Ambil detail produk untuk pesanan ini
    $query_produk_pesanan = "SELECT nama_produk_saat_beli, quantity, harga_satuan FROM detail_pesanan WHERE pesanan_id = ?";
    $stmt_produk_pesanan = mysqli_prepare($conn, $query_produk_pesanan);
    if (!$stmt_produk_pesanan) {
        die("Error preparing product detail statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_produk_pesanan, 'i', $pesanan_id);
    mysqli_stmt_execute($stmt_produk_pesanan);
    $result_produk_pesanan = mysqli_stmt_get_result($stmt_produk_pesanan);
    $list_produk_pesanan = [];
    while ($row = mysqli_fetch_assoc($result_produk_pesanan)) {
        $list_produk_pesanan[] = $row;
    }

    // Handle status update and proof of delivery upload
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['new_status'];
        $order_id_to_update = $_POST['order_id'];
        $bukti_pengiriman_filename = $pesanan_detail['bukti_pengiriman']; // Keep existing if not updated

        $valid_statuses = ['diproses', 'dikirim', 'selesai', 'dibatalkan'];

        if (in_array($new_status, $valid_statuses) && $order_id_to_update == $pesanan_id) {
            // Handle bukti_pengiriman upload only if status is 'selesai' or relevant
            if ($new_status == 'selesai' && isset($_FILES['bukti_pengiriman']) && $_FILES['bukti_pengiriman']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "../../img/bukti_pengiriman/"; // Directory to save upload proofs
                $file_extension = pathinfo($_FILES['bukti_pengiriman']['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('delivery_') . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                // Validate file type
                $imageFileType = strtolower($file_extension);
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($imageFileType, $allowed_types)) {
                    echo "<script>alert('Maaf, hanya file JPG, JPEG, PNG & GIF yang diperbolehkan untuk bukti pengiriman.');</script>";
                    // Optionally, don't update status if upload fails
                } else if (move_uploaded_file($_FILES["bukti_pengiriman"]["tmp_name"], $target_file)) {
                    $bukti_pengiriman_filename = $new_filename;
                } else {
                    echo "<script>alert('Gagal mengunggah bukti pengiriman.');</script>";
                    // Optionally, don't update status if upload fails
                }
            }

            // Update query for status and bukti_pengiriman
            $update_query = "UPDATE pesanan SET status_pesanan = ?, bukti_pengiriman = ? WHERE id = ? AND kurir_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            if ($stmt_update) {
                mysqli_stmt_bind_param($stmt_update, 'ssii', $new_status, $bukti_pengiriman_filename, $order_id_to_update, $user_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    echo "<script>alert('Status pesanan berhasil diperbarui!'); window.location.href='detail_pesanan_kurir.php?id=" . $pesanan_id . "';</script>";
                    exit;
                } else {
                    echo "<script>alert('Gagal memperbarui status pesanan: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                echo "<script>alert('Gagal menyiapkan statement update: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('Status tidak valid atau ID pesanan tidak cocok.');</script>";
        }
    }

    // --- LOGIKA UNTUK MEMPERBARUI NOMOR RESI ---
    if (isset($_POST['update_resi'])) {
        $new_resi = trim($_POST['nomor_resi']);
        $order_id_to_update = $_POST['order_id'];

        if ($order_id_to_update == $pesanan_id) {
            // Update query khusus untuk nomor resi
            $update_query_resi = "UPDATE pesanan SET nomor_resi = ? WHERE id = ? AND kurir_id = ?";
            $stmt_update_resi = mysqli_prepare($conn, $update_query_resi);
            if ($stmt_update_resi) {
                mysqli_stmt_bind_param($stmt_update_resi, 'sii', $new_resi, $order_id_to_update, $user_id);
                if (mysqli_stmt_execute($stmt_update_resi)) {
                    echo "<script>alert('Nomor resi berhasil diperbarui!'); window.location.href='detail_pesanan_kurir.php?id=" . $pesanan_id . "';</script>";
                    exit;
                } else {
                    echo "<script>alert('Gagal memperbarui nomor resi: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                echo "<script>alert('Gagal menyiapkan statement update resi: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('ID pesanan tidak cocok.');</script>";
        }
    }

} else {
    // If no order ID is provided, redirect back to the order list
    header('Location: pesanan_kurir.php');
    exit;
}

// If after all checks, $pesanan_detail is still null, it means order was not found or not assigned to this courier
if (!$pesanan_detail) {
    echo "<script>alert('Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.'); window.location.href='pesanan_kurir.php';</script>";
    exit;
}

// --- LOGIKA UPDATE PROFIL KURIR ---
// Pastikan semua path dalam logika ini sudah disesuaikan dengan lokasi file detail_pesanan_kurir.php
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
        $upload_dir = '../../img/kurir/'; // PERHATIKAN PATH RELATIF INI: Dari pesanan/ ke img/kurir/
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
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='detail_pesanan_kurir.php?id=" . $pesanan_id . "';</script>"; // Redirect ke halaman ini
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- LOGIKA GENERASI INVOICE (PDF) DENGAN DOMPDF ---
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (isset($_GET['generate_invoice']) && $_GET['generate_invoice'] == 'true' && isset($_GET['id'])) {
    $invoice_pesanan_id = $_GET['id'];

    // Re-fetch order details for invoice generation to ensure data freshness
    $query_invoice_detail = "
        SELECT
            p.id AS pesanan_id,
            p.tanggal_pesanan,
            p.status_pesanan,
            p.alamat_pengiriman,
            p.ongkos_kirim,
            p.metode_pembelian,
            p.diskon_kupon,
            p.total_harga,
            p.nomor_resi, -- MENAMBAHKAN NOMOR RESI UNTUK INVOICE
            pm.bukti_transfer,
            pl.nama AS nama_pelanggan,
            pl.email AS email_pelanggan,
            pl.nomor_telepon AS telepon_pelanggan,
            k.nama AS nama_kurir,
            k.nomor_telepon AS telepon_kurir
        FROM pesanan p
        JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
        LEFT JOIN kurir k ON p.kurir_id = k.id
        LEFT JOIN pembayaran pm ON p.id = pm.pesanan_id
        WHERE p.id = ? AND p.kurir_id = ?";

    $stmt_invoice_detail = mysqli_prepare($conn, $query_invoice_detail);
    mysqli_stmt_bind_param($stmt_invoice_detail, 'ii', $invoice_pesanan_id, $user_id);
    mysqli_stmt_execute($stmt_invoice_detail);
    $result_invoice_detail = mysqli_stmt_get_result($stmt_invoice_detail);
    $invoice_data = mysqli_fetch_assoc($result_invoice_detail);

    // --- Ambil data BUMDes dari tabel 'bumdes' ---
    $bumdes_data = null;
    $query_bumdes = "SELECT nama, alamat, kontak FROM bumdes LIMIT 1"; // Tidak mengambil logo dari sini lagi
    $result_bumdes = mysqli_query($conn, $query_bumdes);
    if ($result_bumdes && mysqli_num_rows($result_bumdes) > 0) {
        $bumdes_data = mysqli_fetch_object($result_bumdes);
    }
    // --- Akhir Ambil data BUMDes ---

    // Fetch product details for the invoice
    $query_invoice_produk = "SELECT nama_produk_saat_beli, quantity, harga_satuan FROM detail_pesanan WHERE pesanan_id = ?";
    $stmt_invoice_produk = mysqli_prepare($conn, $query_invoice_produk);
    mysqli_stmt_bind_param($stmt_invoice_produk, 'i', $invoice_pesanan_id);
    mysqli_stmt_execute($stmt_invoice_produk);
    $result_invoice_produk = mysqli_stmt_get_result($stmt_invoice_produk);
    $invoice_products = [];
    while ($row = mysqli_fetch_assoc($result_invoice_produk)) {
        $invoice_products[] = $row;
    }

    if ($invoice_data) {
        // Hitung total produk
        $total_produk_harga = 0;
        foreach ($invoice_products as $product) {
            $subtotal_item = $product['quantity'] * $product['harga_satuan'];
            $total_produk_harga += $subtotal_item;
        }
        // Pastikan diskon adalah angka, default ke 0 jika null atau tidak ada
        $diskon = isset($invoice_data['diskon_kupon']) ? (float)$invoice_data['diskon_kupon'] : 0;

        // Gunakan total_harga dari database jika tersedia dan relevan,
        // atau hitung ulang jika diperlukan (misalnya untuk verifikasi)
        $grand_total_for_invoice = isset($invoice_data['total_harga']) ? (float)$invoice_data['total_harga'] : ($total_produk_harga + $invoice_data['ongkos_kirim'] - $diskon);

        // Pastikan grand total tidak negatif
        if ($grand_total_for_invoice < 0) {
            $grand_total_for_invoice = 0;
        }

        // Mulai buat HTML untuk invoice
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice Pesanan #' . htmlspecialchars($invoice_data['pesanan_id']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px;}
                .container { width: 100%; margin: auto; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { margin: 0; padding: 0; color: #4B3832; font-size: 24px; }
                .header p { margin: 2px 0; font-size: 10px; color: #666; }
                .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; color: #4B3832; }
                .details-table table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .details-table td { padding: 5px 0; vertical-align: top; }
                .details-table td:first-child { width: 150px; font-weight: bold; }
                .product-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .product-table th, .product-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .product-table th { background-color: #f2f2f2; color: #4B3832; font-size: 13px; }
                .product-table tfoot th { background-color: #4B3832; color: #fff; text-align: right; padding: 8px; font-size: 13px; }
                .product-table tfoot td { background-color: #4B3832; color: #fff; text-align: right; font-weight: bold; padding: 8px; font-size: 13px; }
                .text-right { text-align: right; }
                .status-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 5px;
                    color: #fff;
                    font-size: 10px;
                    font-weight: bold;
                }
                .status-diproses { background-color: #007bff; }
                .status-dikirim { background-color: #17a2b8; }
                .status-selesai { background-color: #28a745; }
                .status-dibatalkan { background-color: #dc3545; }
                .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #666; }
                /* Untuk logo yang mungkin tidak ditemukan di Dompdf, pastikan path relatifnya benar */
                .header img {
                    height: 50px;
                    margin-right: 15px;
                    /* Untuk memastikan Dompdf bisa menemukan gambar,
                       path ini harus relatif terhadap lokasi file PHP yang menjalankan Dompdf
                       atau menggunakan URL absolut jika server mendukungnya.
                       Jika file ini di `kurir/pesanan/detail_pesanan_kurir.php`,
                       maka `../../img/logo.jpg` sudah benar. */
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header" style="display: flex; align-items: center;">';
                // Menggunakan logo dari path '../../img/logoo.png' (sesuai yang Anda berikan)
                $html .= '<img src="../../img/logoo.png" alt="Logo BUMDes" style="height: 50px; margin-right: 15px;">';

                $html .= '
                    <div>
                        <h1>INVOICE PESANAN</h1>
                        <p>' . ($bumdes_data ? htmlspecialchars($bumdes_data->nama) : 'Nama BUMDes') . '</p>
                        <p>' . ($bumdes_data ? htmlspecialchars($bumdes_data->alamat) : 'Alamat BUMDes') . '</p>
                        <p>Telepon: ' . ($bumdes_data ? htmlspecialchars($bumdes_data->kontak) : 'Kontak BUMDes') . '</p>
                    </div>
                </div>

                <div class="section-title">Detail Invoice</div>
                <div class="details-table">
                    <table>
                        <tr>
                            <td>Nomor Invoice:</td>
                            <td>INV-' . str_pad($invoice_data['pesanan_id'], 5, '0', STR_PAD_LEFT) . '/' . date('Ymd', strtotime($invoice_data['tanggal_pesanan'])) . '</td>
                        </tr>
                        <tr>
                            <td>Tanggal Pesanan:</td>
                            <td>' . format_tanggal_indonesia($invoice_data['tanggal_pesanan']) . '</td>
                        </tr>
                        <tr>
                            <td>Status Pesanan:</td>
                            <td>
                                <span class="status-badge status-' . htmlspecialchars(strtolower($invoice_data['status_pesanan'])) . '">
                                    ' . htmlspecialchars(str_replace('_', ' ', strtoupper($invoice_data['status_pesanan']))) . '
                                </span>
                            </td>
                        </tr>';
                        // Menambahkan baris nomor resi ke invoice jika tersedia
                        if (!empty($invoice_data['nomor_resi'])) {
                            $html .= '
                            <tr>
                                <td>Nomor Resi:</td>
                                <td>' . htmlspecialchars($invoice_data['nomor_resi']) . '</td>
                            </tr>';
                        }
                        $html .= '
                        <tr>
                            <td>Metode Pembayaran:</td>
                            <td>' . htmlspecialchars($invoice_data['metode_pembelian']) . '</td>
                        </tr>
                    </table>
                </div>

                <div class="section-title">Detail Pelanggan</div>
                <div class="details-table">
                    <table>
                        <tr>
                            <td>Nama:</td>
                            <td>' . htmlspecialchars($invoice_data['nama_pelanggan']) . '</td>
                        </tr>
                        <tr>
                            <td>Email:</td>
                            <td>' . htmlspecialchars($invoice_data['email_pelanggan']) . '</td>
                        </tr>
                        <tr>
                            <td>Telepon:</td>
                            <td>' . htmlspecialchars($invoice_data['telepon_pelanggan']) . '</td>
                        </tr>
                        <tr>
                            <td>Alamat Pengiriman:</td>
                            <td>' . nl2br(htmlspecialchars($invoice_data['alamat_pengiriman'])) . '</td>
                        </tr>
                    </table>
                </div>

                <div class="section-title">Detail Produk</div>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
        $no = 1;
        foreach ($invoice_products as $product) {
            $subtotal_item = $product['quantity'] * $product['harga_satuan'];
            $html .= '
                        <tr>
                            <td>' . $no++ . '</td>
                            <td>' . htmlspecialchars($product['nama_produk_saat_beli']) . '</td>
                            <td>' . htmlspecialchars($product['quantity']) . '</td>
                            <td class="text-right">Rp. ' . number_format($product['harga_satuan'], 0, ',', '.') . '</td>
                            <td class="text-right">Rp. ' . number_format($subtotal_item, 0, ',', '.') . '</td>
                        </tr>';
        }
        $html .= '
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Total Harga Produk:</th>
                            <td class="text-right">Rp. ' . number_format($total_produk_harga, 0, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <th colspan="4">Ongkos Kirim:</th>
                            <td class="text-right">Rp. ' . number_format($invoice_data['ongkos_kirim'], 0, ',', '.') . '</td>
                        </tr>';
        // Menampilkan diskon jika ada
        if ($diskon > 0) {
            $html .= '
                        <tr>
                            <th colspan="4">Diskon:</th>
                            <td class="text-right">- Rp. ' . number_format($diskon, 0, ',', '.') . '</td>
                        </tr>';
        }
        $html .= '
                        <tr>
                            <th colspan="4">GRAND TOTAL:</th>
                            <td class="text-right">Rp. ' . number_format($grand_total_for_invoice, 0, ',', '.') . '</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="footer">
                    <p>Terima kasih atas pesanan Anda. Silakan hubungi kami jika ada pertanyaan.</p>
                    <p>Invoice ini dihasilkan secara otomatis dan valid tanpa tanda tangan.</p>
                </div>
            </div>
        </body>
        </html>';

        // Inisialisasi DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Penting jika Anda punya gambar dari URL atau gaya eksternal
        // Set base path untuk gambar agar Dompdf bisa menemukan gambar dengan path relatif
        $options->set('chroot', realpath('../../')); // Set root ke direktori di atas 'img' dan 'kurir'
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);

        // (Opsional) Atur ukuran kertas dan orientasi
        $dompdf->setPaper('A4', 'portrait');

        // Render HTML menjadi PDF
        $dompdf->render();

        // Kirim PDF ke browser
        $filename = 'Invoice_Pesanan_' . $invoice_data['pesanan_id'] . '.pdf';
        $dompdf->stream($filename, ["Attachment" => true]); // true = download, false = open in browser
        exit;
    } else {
        echo "<script>alert('Data invoice tidak ditemukan.'); window.location.href='detail_pesanan_kurir.php?id=" . $pesanan_id . "';</script>";
        exit;
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pesanan - Kurir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Warna Mocha Cream */
        :root {
            --mocha: #4B3832;
            --cream: #FFF8DC;
            --light-mocha: #6F4E37;
            --beige: #D2B48C;
            --light-beige: #EDE0C7;
            --text-dark: #333333;
            --text-light: #ffffff;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: var(--cream); /* Latar belakang utama */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark); /* Warna teks umum */
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 250px;
            background-color: var(--mocha); /* Sidebar mocha */
            min-height: 100vh;
            padding: 20px 0;
            color: var(--text-light);
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
            color: var(--cream);
            margin-bottom: 30px;
        }
        .sidebar a {
            color: var(--light-beige);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: var(--light-mocha);
            color: var(--text-light);
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: var(--beige);
        }
        .sidebar .submenu:hover {
            color: var(--text-light);
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
            background-color: var(--cream); /* Konten utama cream */
            margin-left: 0; /* Remove default margin for responsiveness */
        }
        .toggle-btn {
            background: none;
            border: none;
            color: var(--cream); /* Warna tombol toggle */
            margin-left: 20px;
            font-size: 20px;
        }
        .content.ml-collapsed {
             margin-left: 80px; /* Adjust content position when sidebar is collapsed */
        }

        .navbar {
            background-color: var(--mocha) !important; /* Navbar mocha */
            color: var(--text-light);
        }
        .navbar .navbar-brand,
        .navbar .nav-link {
            color: var(--cream) !important;
        }
        .navbar .nav-link.dropdown-toggle {
            color: var(--cream) !important;
        }
        .navbar .dropdown-menu {
            background-color: var(--light-beige);
            color: var(--text-dark);
        }
        .navbar .dropdown-item {
            color: var(--text-dark);
        }
        .navbar .dropdown-item:hover {
            background-color: var(--beige);
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
            background: var(--light-beige); /* Profil menu cream */
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            color: var(--text-dark);
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
            color: var(--mocha); /* Link di profil menu */
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil,
        .card.shadow {
            background: var(--light-beige); /* Form edit profil dan kartu */
            color: var(--text-dark);
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .card-detail {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-detail .card-header {
            background-color: var(--mocha); /* Header card detail */
            color: var(--text-light);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            font-weight: bold;
        }
        .card-detail .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items to start to accommodate longer content */
            background-color: var(--light-beige);
            border-color: var(--beige); /* Border list group item */
        }
        .card-detail .list-group-item:nth-of-type(even) {
            background-color: var(--beige);
        }
        .card-detail .list-group-item strong {
            min-width: 150px; /* Adjust as needed for alignment */
            margin-right: 15px; /* Add some space between strong and span */
        }
        .img-bukti-pembayaran, .img-bukti-pengiriman {
            max-width: 100px; /* Smaller thumbnail */
            height: auto;
            border-radius: 5px;
            margin-top: 10px;
            cursor: pointer;
            border: 1px solid var(--beige);
        }
        .img-bukti-pembayaran:hover, .img-bukti-pengiriman:hover {
            opacity: 0.8;
            transform: scale(1.05);
            transition: all 0.2s ease-in-out;
        }

        /* Table styles within card */
        .card-detail .table {
            background-color: var(--light-beige); /* Background tabel */
        }
        .card-detail .table thead th {
            background-color: var(--light-mocha); /* Header tabel */
            color: var(--text-light);
            border-bottom: 1px solid var(--mocha);
        }
        .card-detail .table tbody tr {
            background-color: var(--light-beige);
        }
        .card-detail .table tbody tr:nth-of-type(even) {
            background-color: var(--beige); /* Warna selang-seling */
        }
        .card-detail .table tbody tr:hover {
            background-color: var(--cream);
        }
        .card-detail .table td, .card-detail .table th {
            border-top: 1px solid var(--beige); /* Garis border tabel */
            padding: 12px;
        }
        .card-detail .table tfoot th {
            background-color: var(--mocha);
            color: var(--text-light);
            border-top: 2px solid var(--light-mocha);
        }

        /* Status badges */
        .badge.bg-primary { background-color: #007bff !important; } /* Biru */
        .badge.bg-info { background-color: #17a2b8 !important; } /* Cyan */
        .badge.bg-success { background-color: #28a745 !important; } /* Hijau */
        .badge.bg-danger { background-color: #dc3545 !important; } /* Merah */
        .badge.bg-secondary { background-color: #6c757d !important; } /* Abu-abu */

        /* Form elements */
        .form-control, .form-control-file, .btn {
            border-radius: 5px;
        }
        .btn-success {
            background-color: var(--mocha);
            border-color: var(--mocha);
            color: var(--text-light);
        }
        .btn-success:hover {
            background-color: var(--light-mocha);
            border-color: var(--light-mocha);
        }
        .btn-secondary {
            background-color: var(--beige);
            border-color: var(--beige);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
        }
        .modal-content {
            background-color: var(--light-beige);
            color: var(--text-dark);
        }
        .modal-header {
            background-color: var(--mocha);
            color: var(--text-light);
            border-bottom: 1px solid var(--light-mocha);
        }
        .modal-footer {
            border-top: 1px solid var(--beige);
        }
        .modal-title {
            color: var(--text-light);
        }

        /* Breadcrumb */
        .breadcrumb {
            background-color: var(--light-beige);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--beige);
        }
        .breadcrumb-item a {
            color: var(--mocha);
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: var(--text-dark);
        }

        /* Footer */
        footer {
            background-color: var(--mocha) !important;
            color: var(--cream) !important;
        }
        footer a {
            color: var(--light-beige) !important;
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
<nav class="navbar navbar-expand-lg navbar-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto Profil" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Profile">
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
                            <img src="../../img/kurir/<?= htmlspecialchars($user['foto']) ?>" width="80" class="mb-2 rounded"><br>
                        <?php else: ?>
                            <img src="../../img/kurir/default.png" width="80" class="mb-2 rounded"><br>
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
                <a class="nav-link" href="pesanan_kurir.php">
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard_kurir.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="pesanan_kurir.php">Tugas Pengiriman</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail Pesanan</li>
            </ol>
        </nav>
        <h1>Detail Pesanan #<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?></h1>
        <hr>

        <?php if ($pesanan_detail) : ?>
            <div class="row">
                <div class="col-md-7">
                    <div class="card card-detail shadow">
                        <div class="card-header">
                            Informasi Pesanan
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>ID Pesanan:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['pesanan_id']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Tanggal Pesanan:</strong>
                                <span><?= format_tanggal_indonesia($pesanan_detail['tanggal_pesanan']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Status:</strong>
                                <span>
                                    <span class="badge
                                        <?php
                                            if($pesanan_detail['status_pesanan'] == 'diproses') echo 'bg-primary text-white';
                                            else if($pesanan_detail['status_pesanan'] == 'dikirim') echo 'bg-info text-white';
                                            else if($pesanan_detail['status_pesanan'] == 'selesai') echo 'bg-success text-white';
                                            else if($pesanan_detail['status_pesanan'] == 'dibatalkan') echo 'bg-danger text-white';
                                            else echo 'bg-secondary text-white'; // Fallback
                                        ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan_detail['status_pesanan']))) ?>
                                    </span>
                                </span>
                            </li>
                            <li class="list-group-item">
                                <strong>Nomor Resi:</strong>
                                <span>
                                    <?= !empty($pesanan_detail['nomor_resi']) ? htmlspecialchars($pesanan_detail['nomor_resi']) : 'Belum Tersedia' ?>
                                </span>
                            </li>
                            <li class="list-group-item">
                                <strong>Alamat Pengiriman:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['alamat_pengiriman']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Ongkos Kirim:</strong>
                                <span>Rp. <?= number_format($pesanan_detail['ongkos_kirim'], 0, ',', '.') ?></span>
                            </li>
                            <?php if (isset($pesanan_detail['diskon_kupon']) && $pesanan_detail['diskon_kupon'] > 0) : ?>
                            <li class="list-group-item">
                                <strong>Diskon Kupon:</strong>
                                <span>- Rp. <?= number_format($pesanan_detail['diskon_kupon'], 0, ',', '.') ?></span>
                            </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <strong>Metode Pembayaran:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['metode_pembelian']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Grand Total (Final):</strong>
                                <span>Rp. <?= number_format($pesanan_detail['total_harga'], 0, ',', '.') ?></span>
                            </li>
                            <?php if (!empty($pesanan_detail['bukti_transfer'])): ?>
                                <li class="list-group-item">
                                    <strong>Bukti Pembayaran:</strong>
                                    <span>
                                        <?php
                                        // Assume $pesanan_detail['bukti_transfer'] might contain "bukti_pembayaran/filename.png"
                                        // We need to extract just "filename.png" if that's the case.
                                        $bukti_transfer_filename = basename($pesanan_detail['bukti_transfer']);
                                        ?>
                                        <a href="../../img/bukti_pembayaran/<?= htmlspecialchars($bukti_transfer_filename) ?>" target="_blank">
                                            Lihat Bukti
                                        </a>
                                        <br>
                                        <img src="../../img/bukti_pembayaran/<?= htmlspecialchars($bukti_transfer_filename) ?>"
                                             alt="Bukti Pembayaran" class="img-thumbnail img-bukti-pembayaran"
                                             data-toggle="modal" data-target="#buktiPembayaranModal">
                                    </span>
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($pesanan_detail['bukti_pengiriman'])): ?>
                                <li class="list-group-item">
                                    <strong>Bukti Pengiriman:</strong>
                                    <span>
                                        <a href="../../img/bukti_pengiriman/<?= htmlspecialchars($pesanan_detail['bukti_pengiriman']) ?>" target="_blank">
                                            Lihat Bukti
                                        </a>
                                        <br>
                                        <img src="../../img/bukti_pengiriman/<?= htmlspecialchars($pesanan_detail['bukti_pengiriman']) ?>"
                                             alt="Bukti Pengiriman" class="img-thumbnail img-bukti-pengiriman"
                                             data-toggle="modal" data-target="#buktiPengirimanModal">
                                    </span>
                                </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <strong>Invoice:</strong>
                                <span>
                                    <a href="detail_pesanan_kurir.php?id=<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?>&generate_invoice=true" class="btn btn-sm btn-info">
                                        <i class="fas fa-download"></i> Unduh Invoice
                                    </a>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="card card-detail shadow">
                        <div class="card-header">
                            Detail Produk
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Jumlah</th>
                                        <th>Harga Satuan</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $grand_total_produk = 0;
                                    if (!empty($list_produk_pesanan)) :
                                        foreach ($list_produk_pesanan as $item) :
                                            $subtotal = $item['quantity'] * $item['harga_satuan'];
                                            $grand_total_produk += $subtotal;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['nama_produk_saat_beli']) ?></td>
                                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                                            <td>Rp. <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                            <td>Rp. <?= number_format($subtotal, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php
                                        endforeach;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Tidak ada produk dalam pesanan ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">Total Harga Produk:</th>
                                        <th>Rp. <?= number_format($grand_total_produk, 0, ',', '.') ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-right">Ongkos Kirim:</th>
                                        <th>Rp. <?= number_format($pesanan_detail['ongkos_kirim'], 0, ',', '.') ?></th>
                                    </tr>
                                    <?php
                                    $diskon_display = isset($pesanan_detail['diskon_kupon']) ? (float)$pesanan_detail['diskon_kupon'] : 0;
                                    if ($diskon_display > 0) :
                                    ?>
                                    <tr>
                                        <th colspan="3" class="text-right">Diskon Kupon:</th>
                                        <th>- Rp. <?= number_format($diskon_display, 0, ',', '.') ?></th>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th colspan="3" class="text-right">Grand Total (Final):</th>
                                        <?php
                                            // Ambil langsung dari kolom total_harga yang sudah dihitung di database
                                            $final_grand_total = (float)$pesanan_detail['total_harga'];
                                        ?>
                                        <th>Rp. <?= number_format($final_grand_total, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="card card-detail shadow">
                        <div class="card-header">
                            Informasi Pelanggan
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Nama Pelanggan:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['nama_pelanggan']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Email Pelanggan:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['email_pelanggan']) ?></span>
                            </li>
                            <li class="list-group-item">
                                <strong>Telepon Pelanggan:</strong>
                                <span><?= htmlspecialchars($pesanan_detail['telepon_pelanggan']) ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="card card-detail shadow">
                        <div class="card-header">
                            Update Nomor Resi
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?>">
                                <div class="form-group mb-3">
                                    <label for="nomor_resi">Nomor Resi:</label>
                                    <input type="text" name="nomor_resi" id="nomor_resi" class="form-control" value="<?= htmlspecialchars($pesanan_detail['nomor_resi'] ?? '') ?>" placeholder="Masukkan nomor resi">
                                </div>
                                <button type="submit" name="update_resi" class="btn btn-info">Update Nomor Resi</button>
                            </form>
                        </div>
                    </div>

                    <div class="card card-detail shadow mt-4">
                        <div class="card-header">
                            Ubah Status Pesanan
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?>">
                                <div class="form-group mb-3">
                                    <label for="new_status">Pilih Status Baru:</label>
                                    <select name="new_status" id="new_status" class="form-control">
                                        <option value="diproses" <?= $pesanan_detail['status_pesanan'] == 'diproses' ? 'selected' : '' ?>>Diproses</option>
                                        <option value="dikirim" <?= $pesanan_detail['status_pesanan'] == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                                        <option value="selesai" <?= $pesanan_detail['status_pesanan'] == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="dibatalkan" <?= $pesanan_detail['status_pesanan'] == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                    </select>
                                </div>
                                <div class="form-group mb-3" id="buktiPengirimanField" style="<?= $pesanan_detail['status_pesanan'] == 'selesai' ? 'display: block;' : 'display: none;' ?>">
                                    <label for="bukti_pengiriman">Upload Bukti Pengiriman:</label>
                                    <input type="file" name="bukti_pengiriman" id="bukti_pengiriman" class="form-control-file">
                                    <?php if (!empty($pesanan_detail['bukti_pengiriman'])): ?>
                                        <small class="form-text text-muted mt-2">File saat ini: <a href="../../img/bukti_pengiriman/<?= htmlspecialchars($pesanan_detail['bukti_pengiriman']) ?>" target="_blank"><?= htmlspecialchars($pesanan_detail['bukti_pengiriman']) ?></a></small>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" name="update_status" class="btn btn-success">Update Status</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else : ?>
            <div class="alert alert-warning" role="alert">
                Pesanan tidak ditemukan atau Anda tidak memiliki izin untuk melihat pesanan ini.
            </div>
        <?php endif; ?>

        <a href="pesanan_kurir.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan</a>
    </div>
</div>

<?php if (!empty($pesanan_detail['bukti_transfer'])): ?>
<div class="modal fade" id="buktiPembayaranModal" tabindex="-1" aria-labelledby="buktiPembayaranModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="buktiPembayaranModalLabel">Bukti Pembayaran Pesanan #<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img src="../../img/bukti_pembayaran/<?= htmlspecialchars($bukti_transfer_filename) ?>" class="img-fluid" alt="Bukti Pembayaran">
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($pesanan_detail['bukti_pengiriman'])): ?>
<div class="modal fade" id="buktiPengirimanModal" tabindex="-1" aria-labelledby="buktiPengirimanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="buktiPengirimanModalLabel">Bukti Pengiriman Pesanan #<?= htmlspecialchars($pesanan_detail['pesanan_id']) ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img src="../../img/bukti_pengiriman/<?= htmlspecialchars($pesanan_detail['bukti_pengiriman']) ?>" class="img-fluid" alt="Bukti Pengiriman">
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


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
        // Handle dropdown menu for profile
        $('.nav-link.dropdown-toggle').click(function() {
            $(this).next('.dropdown-menu').toggle();
        });
        // Close dropdown when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').hide();
            }
        });

        // Show/hide bukti pengiriman field based on status selection
        $('#new_status').change(function() {
            if ($(this).val() === 'selesai') {
                $('#buktiPengirimanField').slideDown();
            } else {
                $('#buktiPengirimanField').slideUp();
            }
        });

        // Initialize visibility on page load
        if ($('#new_status').val() === 'selesai') {
            $('#buktiPengirimanField').show();
        }
    });
</script>
</body>
</html>