<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda. Diasumsikan koneksi.php ada di root/koneksi/koneksi.php

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman pembayaran.";
    header('Location: ../../login.php'); // Redirect ke halaman login di root
    exit();
}

// Cek apakah order_id diberikan dan valid
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak valid untuk halaman pembayaran.";
    header('Location: pesanan_saya.php'); // Redirect kembali ke daftar pesanan
    exit();
}

$order_id = $_GET['order_id'];
$pengguna_id = $_SESSION['pengguna_id'];

$order_data = null;
$grouped_items = [];
$seller_payment_details = null; // Variabel untuk detail pembayaran penjual (bisa bank atau e-wallet)

// Ambil detail pesanan dari database
// **PERUBAHAN UTAMA DI SINI:** JOIN dengan `metode_pembayaran` untuk mendapatkan nama dan tipe metode pembayaran
$query_pesanan = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.metode_pembelian AS metode_pembayaran_id_pesanan, -- Ambil ID metode pembayaran yang tersimpan di pesanan
        mp.nama_metode AS nama_metode_pembayaran, -- Ambil nama dari tabel metode_pembayaran
        mp.tipe_pembayaran AS tipe_metode_pembayaran -- Ambil tipe dari tabel metode_pembayaran
    FROM
        pesanan p
    JOIN
        metode_pembayaran mp ON p.metode_pembelian = mp.id -- JOIN baru di sini
    WHERE
        p.id = ? AND p.pelanggan_id = ? AND p.status_pesanan = 'menunggu_pembayaran';
";

$stmt_pesanan = $conn->prepare($query_pesanan);
if ($stmt_pesanan === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat detail pesanan: " . htmlspecialchars($conn->error);
    error_log("Error preparing payment gateway order query: " . $conn->error);
    header('Location: customer/pesanan_saya.php');
    exit();
}
$stmt_pesanan->bind_param("ii", $order_id, $pengguna_id);
$stmt_pesanan->execute();
$result_pesanan = $stmt_pesanan->get_result();

if ($result_pesanan->num_rows > 0) {
    $order_data = $result_pesanan->fetch_assoc();

    // Ambil detail produk untuk pesanan ini dan juga penjual_id
    $query_detail_pesanan = "
        SELECT
            dp.produk_id,
            dp.variasi_id,
            dp.quantity,
            dp.harga_satuan,
            prod.nama AS nama_produk,
            prod.gambar AS gambar_produk,
            pen.nama_toko,
            pen.pengguna_id AS penjual_id, -- Ambil ID Penjual dari tabel 'penjual'
            r.nama_rasa,
            w.nama_warna,
            u.nama_ukuran
        FROM
            detail_pesanan dp
        JOIN
            produk prod ON dp.produk_id = prod.id
        JOIN
            penjual pen ON prod.penjual_id = pen.pengguna_id
        LEFT JOIN
            produk_variasi pv ON dp.variasi_id = pv.id
        LEFT JOIN
            rasa r ON pv.rasa_id = r.id
        LEFT JOIN
            warna w ON pv.warna_id = w.id
        LEFT JOIN
            ukuran u ON pv.ukuran_id = u.id
        WHERE
            dp.pesanan_id = ?
        ORDER BY pen.nama_toko, prod.nama;
    ";
    $stmt_detail = $conn->prepare($query_detail_pesanan);
    if ($stmt_detail === false) {
        $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat detail produk pesanan: " . htmlspecialchars($conn->error);
        error_log("Error preparing payment gateway detail query: " . $conn->error);
        header('Location: customer/pesanan_saya.php');
        exit();
    }
    $stmt_detail->bind_param("i", $order_id);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();

    if ($result_detail->num_rows > 0) {
        $first_seller_id = null; // Untuk menyimpan ID penjual pertama yang ditemukan

        while ($item_row = $result_detail->fetch_assoc()) {
            if ($first_seller_id === null) {
                $first_seller_id = $item_row['penjual_id']; // Ambil ID penjual pertama
            }

            $variasi_detail = [];
            if (!empty($item_row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($item_row['nama_rasa']); }
            if (!empty($item_row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($item_row['nama_warna']); }
            if (!empty($item_row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($item_row['nama_ukuran']); }

            $item_data = [
                'nama_produk' => htmlspecialchars($item_row['nama_produk']),
                'gambar_produk' => htmlspecialchars($item_row['gambar_produk']),
                'quantity' => $item_row['quantity'],
                'harga_satuan' => $item_row['harga_satuan'],
                'variasi_string' => implode(', ', $variasi_detail)
            ];

            if (!isset($grouped_items[$item_row['nama_toko']])) {
                $grouped_items[$item_row['nama_toko']] = [];
            }
            $grouped_items[$item_row['nama_toko']][] = $item_data;
        }

        // --- Perubahan untuk Mengambil Detail Pembayaran Penjual ---
        // Kita sudah punya metode_pembayaran_id_pesanan dari $order_data
        $selected_method_id = $order_data['metode_pembayaran_id_pesanan'];
        $selected_method_tipe = $order_data['tipe_metode_pembayaran']; // Tipe (bank_transfer, e_wallet, cash, qris)

        // Hanya ambil detail pembayaran penjual jika bukan COD
        if ($first_seller_id !== null && $selected_method_id !== null && $selected_method_tipe !== 'cash') {
            $query_payment_details = "
                SELECT
                    ppp.detail_akun,
                    ppp.nama_pemilik_akun,
                    mp.nama_metode,
                    mp.tipe_pembayaran
                FROM
                    pengaturan_pembayaran_penjual ppp
                JOIN
                    metode_pembayaran mp ON ppp.metode_pembayaran_id = mp.id
                WHERE
                    ppp.pengguna_id = ? AND ppp.metode_pembayaran_id = ? LIMIT 1;
            ";
            $stmt_payment = $conn->prepare($query_payment_details);
            if ($stmt_payment === false) {
                // Log error tapi jangan hentikan eksekusi
                error_log("Error preparing payment details query filtered by method: " . $conn->error);
                // Set error message jika diperlukan, tapi ini halaman pembayaran, jadi mungkin tidak fatal
            } else {
                $stmt_payment->bind_param("ii", $first_seller_id, $selected_method_id);
                $stmt_payment->execute();
                $result_payment = $stmt_payment->get_result();
                if ($result_payment->num_rows > 0) {
                    $seller_payment_details = $result_payment->fetch_assoc();
                } else {
                    // Log jika detail pembayaran penjual tidak ditemukan untuk metode yang dipilih
                    error_log("No seller payment details found for seller ID {$first_seller_id} and method ID {$selected_method_id}");
                }
                $stmt_payment->close();
            }
        }
        // --- Akhir Perubahan Pengambilan Detail Pembayaran Penjual ---

    }
    $stmt_detail->close();
} else {
    // Jika pesanan tidak ditemukan, bukan milik pengguna, atau statusnya tidak 'menunggu_pembayaran'
    $_SESSION['error_message'] = "Pesanan tidak ditemukan, bukan milik Anda, atau tidak lagi dalam status 'menunggu pembayaran'.";
    header('Location: customer/pesanan_saya.php');
    exit();
}

$stmt_pesanan->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Pesanan #<?php echo htmlspecialchars($order_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .payment-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            background-color: #fff;
        }
        .payment-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            font-weight: bold;
        }
        .payment-body {
            padding: 20px;
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        .product-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .product-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .payment-summary-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            font-weight: bold;
        }
        .store-section {
            border: 1px solid #f0f0f0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fff;
        }
        .store-section .store-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #343a40;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="mb-4">Halaman Pembayaran</h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($order_data): ?>
        <div class="payment-card">
            <div class="payment-header">
                <h5 class="mb-0">Pesanan #<?php echo htmlspecialchars($order_data['pesanan_id']); ?></h5>
                <small class="text-muted">Tanggal Pesanan: <?php echo date('d M Y, H:i', strtotime($order_data['tanggal_pesanan'])); ?></small><br>
                <small class="text-muted">Metode Pembayaran: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_data['nama_metode_pembayaran']))); ?></strong></small>
            </div>
            <div class="payment-body">
                <p class="fw-bold">Detail Produk:</p>
                <?php if (empty($grouped_items)): ?>
                    <p class="text-muted text-center">Tidak ada detail produk untuk pesanan ini.</p>
                <?php else: ?>
                    <?php foreach ($grouped_items as $nama_toko => $items_per_toko): ?>
                        <div class="store-section">
                            <div class="store-header">
                                <i class="fas fa-store me-2"></i>Toko: <?php echo htmlspecialchars($nama_toko); ?>
                            </div>
                            <?php foreach ($items_per_toko as $item): ?>
                                <div class="product-item">
                                    <img src="uploads/<?php echo htmlspecialchars($item['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['nama_produk']); ?></h6>
                                        <?php if (!empty($item['variasi_string'])): ?>
                                            <small class="text-muted"><?php echo $item['variasi_string']; ?></small>
                                        <?php endif; ?>
                                        <p class="mb-0 text-muted">
                                            Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?> x <?php echo $item['quantity']; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h4 class="mt-4 text-end">Total yang harus dibayar: <span class="text-success">Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></span></h4>

                <hr>

                <p class="fw-bold">Instruksi Pembayaran:</p>
                <div class="alert alert-info">
                    <?php
                    $tipe_pembayaran_display = $order_data['tipe_metode_pembayaran']; // Ambil dari hasil JOIN

                    if ($tipe_pembayaran_display === 'cash'): // Untuk COD
                    ?>
                        <p>Anda telah memilih pembayaran **COD (Bayar di Tempat)**.</p>
                        <p>Silakan siapkan uang tunai sebesar **Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?>**.</p>
                        <p>Pembayaran akan dilakukan kepada kurir saat pesanan Anda tiba.</p>
                        <p>Anda tidak perlu melakukan konfirmasi pembayaran di halaman ini.</p>
                    <?php elseif ($seller_payment_details): // Untuk transfer bank, e-wallet, QRIS yang ada detailnya ?>
                        <p>Silakan lakukan pembayaran ke detail berikut:</p>
                        <p>
                            Metode: <?php echo htmlspecialchars($seller_payment_details['nama_metode']); ?><br>
                            <?php if ($tipe_pembayaran_display === 'bank_transfer'): ?>
                                Nomor Rekening: **<?php echo htmlspecialchars($seller_payment_details['detail_akun']); ?>**<br>
                            <?php elseif ($tipe_pembayaran_display === 'e_wallet'): ?>
                                ID/Nomor Telepon: **<?php echo htmlspecialchars($seller_payment_details['detail_akun']); ?>**<br>
                            <?php elseif ($tipe_pembayaran_display === 'qris'): ?>
                                Detail QRIS: **<?php echo htmlspecialchars($seller_payment_details['detail_akun']); ?>**<br>
                                <?php else: // Fallback untuk tipe lain yang mungkin tidak terdefinisi ?>
                                Detail Akun: **<?php echo htmlspecialchars($seller_payment_details['detail_akun']); ?>**<br>
                            <?php endif; ?>
                            Atas Nama: **<?php echo htmlspecialchars($seller_payment_details['nama_pemilik_akun']); ?>**
                        </p>
                    <?php else: // Jika metode bukan COD, tapi detail penjual tidak ditemukan ?>
                        <p>
                            Detail pembayaran untuk metode **<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_data['nama_metode_pembayaran']))); ?>** tidak ditemukan untuk penjual ini.
                            Mohon hubungi layanan pelanggan atau penjual untuk informasi lebih lanjut.
                        </p>
                    <?php endif; ?>
                    <p>Jumlah yang harus dibayar: **Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?>**</p>
                    <?php if ($tipe_pembayaran_display !== 'cash'): // Hanya tampilkan ini jika bukan COD ?>
                        <p>Pastikan Anda memasukkan nomor pesanan Anda (Pesanan #<?php echo htmlspecialchars($order_data['pesanan_id']); ?>) di bagian keterangan/berita transfer/pesan.</p>
                        <p>Setelah melakukan pembayaran, konfirmasi pembayaran Anda melalui tautan di bawah.</p>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2">
                    <?php if ($tipe_pembayaran_display !== 'cash'): // Tombol konfirmasi hanya untuk metode non-COD ?>
                        <a href="konfirmasi_pembayaran.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-primary btn-lg">Konfirmasi Pembayaran</a>
                    <?php endif; ?>
                    <a href="pesanan_saya.php" class="btn btn-secondary btn-lg">Kembali ke Pesanan Saya</a>
                </div>
            </div>
            <div class="payment-summary-footer text-center">
                <p class="mb-0">Terima kasih telah berbelanja!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center" role="alert">
            Tidak ada pesanan yang valid untuk diproses pembayaran saat ini.
            <a href="pesanan_saya.php" class="alert-link">Kembali ke Pesanan Saya</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>