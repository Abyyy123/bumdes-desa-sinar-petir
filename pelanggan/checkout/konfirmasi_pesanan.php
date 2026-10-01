<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path koneksi database Anda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melihat riwayat pesanan.";
    header('Location: ../../login.php');
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$order_id = $_GET['order_id'] ?? null;
$order_code = $_GET['order_code'] ?? null;
$order_data = null;
$detail_items = [];
$payment_method_info = null;

if ($order_id && $order_code) {
    // Ambil detail pesanan utama
    $stmt_order = $conn->prepare("
        SELECT
            p.id, p.kode_pesanan, p.tanggal_pesanan, p.total_harga_produk, p.total_ongkir, p.total_pembayaran,
            p.alamat_pengiriman, p.nama_penerima, p.nomor_telepon_penerima, p.status_pesanan, p.catatan_pelanggan,
            mp.nama_metode, mp.kode_metode, mp.logo_metode, mp.tipe_pembayaran
        FROM
            pesanan p
        JOIN
            metode_pembayaran mp ON p.metode_pembayaran_id = mp.id
        WHERE
            p.id = ? AND p.kode_pesanan = ? AND p.customer_id = ?
    ");
    if ($stmt_order) {
        $stmt_order->bind_param("isi", $order_id, $order_code, $pengguna_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        if ($result_order->num_rows > 0) {
            $order_data = $result_order->fetch_assoc();
        }
        $stmt_order->close();
    }

    // Ambil detail item pesanan
    if ($order_data) {
        $query_detail_items = "
            SELECT
                dp.id AS detail_id,
                dp.produk_id,
                dp.variasi_id,
                dp.quantity,
                dp.harga_satuan,
                dp.subtotal,
                dp.berat_total,
                dp.ongkos_kirim_toko,
                dp.catatan_penjual,
                dp.status_item,
                prod.nama AS nama_produk,
                prod.gambar AS gambar_produk,
                kur.nama AS nama_kurir,
                pen.nama_toko AS nama_toko_penjual,
                r.nama_rasa,
                w.nama_warna,
                u.nama_ukuran
            FROM
                detail_pesanan dp
            JOIN
                produk prod ON dp.produk_id = prod.id
            LEFT JOIN
                produk_variasi pv ON dp.variasi_id = pv.id
            JOIN
                kurir kur ON dp.kurir_id = kur.id
            JOIN
                penjual pen ON dp.penjual_id = pen.pengguna_id
            LEFT JOIN
                rasa r ON pv.rasa_id = r.id
            LEFT JOIN
                warna w ON pv.warna_id = w.id
            LEFT JOIN
                ukuran u ON pv.ukuran_id = u.id
            WHERE
                dp.pesanan_id = ?
            ORDER BY pen.nama_toko, prod.nama ASC
        ";
        $stmt_detail = $conn->prepare($query_detail_items);
        if ($stmt_detail) {
            $stmt_detail->bind_param("i", $order_id);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            $grouped_detail_items = [];
            while ($row = $result_detail->fetch_assoc()) {
                $variasi_detail = [];
                if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
                if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
                if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }
                $row['variasi_string'] = implode(', ', $variasi_detail);

                if (!isset($grouped_detail_items[$row['penjual_id']])) {
                    $grouped_detail_items[$row['penjual_id']] = [
                        'nama_toko' => htmlspecialchars($row['nama_toko_penjual']),
                        'kurir' => htmlspecialchars($row['nama_kurir']),
                        'ongkos_kirim_toko' => $row['ongkos_kirim_toko'],
                        'items' => []
                    ];
                }
                $grouped_detail_items[$row['penjual_id']]['items'][] = $row;
            }
            $detail_items = $grouped_detail_items;
            $stmt_detail->close();
        }
    }
}

if (!$order_data) {
    $_SESSION['error_message'] = "Detail pesanan tidak ditemukan atau tidak valid.";
    header('Location: riwayat_pesanan.php'); // Atau halaman lain yang sesuai
    exit();
}

$conn->close();

// Dummy data untuk detail pembayaran jika diambil dari database (sesuaikan dengan metode_pembayaran_id)
// Ini adalah contoh, sebaiknya data ini diambil dari tabel `metode_pembayaran` atau `bank_pembayaran`
// atau `pengaturan_pembayaran_penjual` (jika pembayaran langsung ke penjual).
// Untuk transfer bank, Anda mungkin perlu menampilkan rekening penjual terkait.
$payment_info_details = [
    'BANK_TRANSFER_BCA' => [
        'bank_name' => 'Bank BCA',
        'account_number' => '1234567890',
        'account_name' => 'PT. Toko Kita'
    ],
    'BANK_TRANSFER_MANDIRI' => [
        'bank_name' => 'Bank Mandiri',
        'account_number' => '0987654321',
        'account_name' => 'PT. Toko Kita'
    ],
    'DANA' => [
        'ewallet_name' => 'DANA',
        'account_number' => '081234567890'
    ],
    'OVO' => [
        'ewallet_name' => 'OVO',
        'account_number' => '085678901234'
    ],
    'QRIS' => [
        'info' => 'Scan QR Code ini untuk pembayaran.',
        'qr_image' => '../../img/payment_logos/qris_placeholder.png' // Ganti dengan path QRIS Anda
    ],
    'COD' => [
        'info' => 'Pembayaran akan dilakukan saat barang diterima.'
    ]
];

$selected_method_code = $order_data['kode_metode'];
$payment_method_info = $payment_info_details[$selected_method_code] ?? null;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - <?php echo htmlspecialchars($order_data['kode_pesanan']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .summary-box {
            background-color: #f8f9fa;
            border-left: 5px solid #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="mb-4 text-center">Konfirmasi Pesanan</h2>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4>Pesanan Anda Berhasil Dibuat!</h4>
            </div>
            <div class="card-body">
                <p>Terima kasih telah berbelanja di toko kami. Pesanan Anda dengan kode **<?php echo htmlspecialchars($order_data['kode_pesanan']); ?>** telah berhasil dibuat.</p>
                <p>Status Pesanan: <span class="badge bg-warning"><?php echo htmlspecialchars(str_replace('_', ' ', strtoupper($order_data['status_pesanan']))); ?></span></p>
                <p>Tanggal Pesanan: <?php echo date('d M Y, H:i', strtotime($order_data['tanggal_pesanan'])); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Detail Pesanan</h5>
                    </div>
                    <div class="card-body">
                        <h6>Alamat Pengiriman:</h6>
                        <p><b><?php echo htmlspecialchars($order_data['nama_penerima']); ?></b></p>
                        <p><?php echo htmlspecialchars($order_data['alamat_pengiriman']); ?></p>
                        <p><?php echo htmlspecialchars($order_data['nomor_telepon_penerima']); ?></p>

                        <?php if (!empty($order_data['catatan_pelanggan'])): ?>
                            <div class="mt-3">
                                <h6>Catatan Umum:</h6>
                                <p class="text-muted fst-italic">"<?php echo htmlspecialchars($order_data['catatan_pelanggan']); ?>"</p>
                            </div>
                        <?php endif; ?>

                        <h6 class="mt-4">Produk yang Dipesan:</h6>
                        <?php foreach ($detail_items as $penjual_id => $store_data): ?>
                            <div class="mb-3 p-3 border rounded bg-light">
                                <h6 class="mb-2">Toko: <?php echo $store_data['nama_toko']; ?></h6>
                                <p class="mb-1"><small>Kurir: <?php echo $store_data['kurir']; ?> (Ongkir: Rp <?php echo number_format($store_data['ongkos_kirim_toko'], 0, ',', '.'); ?>)</small></p>
                                <?php foreach ($store_data['items'] as $item): ?>
                                    <div class="d-flex align-items-center mb-2 border-bottom pb-2">
                                        <img src="../../img/barang/<?php echo $item['gambar_produk']; ?>" alt="<?php echo $item['nama_produk']; ?>" class="product-img me-3">
                                        <div class="flex-grow-1">
                                            <p class="mb-0"><b><?php echo htmlspecialchars($item['nama_produk']); ?></b></p>
                                            <?php if (!empty($item['variasi_string'])): ?>
                                                <small class="text-muted"><?php echo $item['variasi_string']; ?></small><br>
                                            <?php endif; ?>
                                            <small class="text-muted"><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></small>
                                            <span class="fw-bold float-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!empty($store_data['items'][0]['catatan_penjual'])): ?>
                                    <p class="mt-2 mb-0"><small class="text-muted fst-italic">Catatan untuk toko: "<?php echo htmlspecialchars($store_data['items'][0]['catatan_penjual']); ?>"</small></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3 summary-box">
                    <div class="card-body">
                        <h5>Total Pembayaran</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                Total Harga Produk
                                <span>Rp <?php echo number_format($order_data['total_harga_produk'], 0, ',', '.'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                Total Ongkos Kirim
                                <span>Rp <?php echo number_format($order_data['total_ongkir'], 0, ',', '.'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between fw-bold text-primary fs-5">
                                Total Bayar
                                <span>Rp <?php echo number_format($order_data['total_pembayaran'], 0, ',', '.'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5>Metode Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($order_data['logo_metode'])): ?>
                                <img src="../../img/payment_logos/<?php echo htmlspecialchars($order_data['logo_metode']); ?>" alt="<?php echo htmlspecialchars($order_data['nama_metode']); ?>" style="max-height: 50px; margin-right: 15px;">
                            <?php endif; ?>
                            <h6><?php echo htmlspecialchars($order_data['nama_metode']); ?></h6>
                        </div>

                        <?php if ($payment_method_info): ?>
                            <?php if ($order_data['tipe_pembayaran'] === 'bank_transfer'): ?>
                                <p>Transfer ke rekening:</p>
                                <p class="fw-bold mb-0">Bank: <?php echo htmlspecialchars($payment_method_info['bank_name'] ?? 'N/A'); ?></p>
                                <p class="fw-bold mb-0">No. Rekening: <?php echo htmlspecialchars($payment_method_info['account_number'] ?? 'N/A'); ?> <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($payment_method_info['account_number'] ?? ''); ?>')"><i class="bi bi-clipboard"></i></button></p>
                                <p class="fw-bold">A.N.: <?php echo htmlspecialchars($payment_method_info['account_name'] ?? 'N/A'); ?></p>
                                <p class="text-info"><small>Mohon transfer sesuai dengan jumlah total pembayaran. Batas waktu pembayaran: 24 jam.</small></p>
                                <div class="alert alert-warning" role="alert">
                                    Setelah pembayaran, silakan <a href="konfirmasi_pembayaran.php?order_id=<?php echo $order_id; ?>" class="alert-link">konfirmasi pembayaran Anda di sini</a>.
                                </div>
                            <?php elseif ($order_data['tipe_pembayaran'] === 'e_wallet'): ?>
                                <p>Transfer ke E-Wallet:</p>
                                <p class="fw-bold mb-0"><?php echo htmlspecialchars($payment_method_info['ewallet_name'] ?? 'N/A'); ?>: <?php echo htmlspecialchars($payment_method_info['account_number'] ?? 'N/A'); ?> <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($payment_method_info['account_number'] ?? ''); ?>')"><i class="bi bi-clipboard"></i></button></p>
                                <p class="text-info"><small>Mohon transfer sesuai dengan jumlah total pembayaran. Batas waktu pembayaran: 24 jam.</small></p>
                                <div class="alert alert-warning" role="alert">
                                    Setelah pembayaran, silakan <a href="konfirmasi_pembayaran.php?order_id=<?php echo $order_id; ?>" class="alert-link">konfirmasi pembayaran Anda di sini</a>.
                                </div>
                            <?php elseif ($order_data['tipe_pembayaran'] === 'qris'): ?>
                                <p class="text-center">Scan QR Code ini untuk pembayaran:</p>
                                <div class="text-center mb-3">
                                    <img src="<?php echo htmlspecialchars($payment_method_info['qr_image'] ?? '../../img/payment_logos/qris_placeholder.png'); ?>" alt="QRIS Code" class="img-fluid" style="max-width: 250px;">
                                </div>
                                <p class="text-info text-center"><small>Pastikan jumlah yang dibayar sesuai total pembayaran.</small></p>
                                <div class="alert alert-warning" role="alert">
                                    Setelah pembayaran, silakan <a href="konfirmasi_pembayaran.php?order_id=<?php echo $order_id; ?>" class="alert-link">konfirmasi pembayaran Anda di sini</a>.
                                </div>
                            <?php elseif ($order_data['tipe_pembayaran'] === 'cod'): ?>
                                <p class="text-success">Pembayaran akan dilakukan saat barang diterima oleh Anda (Cash On Delivery).</p>
                                <p class="text-info"><small>Siapkan uang tunai sejumlah Rp <?php echo number_format($order_data['total_pembayaran'], 0, ',', '.'); ?> saat kurir tiba.</small></p>
                            <?php else: ?>
                                <p>Instruksi pembayaran untuk metode ini akan segera kami berikan.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Detail pembayaran tidak tersedia untuk metode ini. Silakan hubungi admin.</p>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="riwayat_pesanan.php" class="btn btn-outline-primary">Lihat Riwayat Pesanan Saya</a>
                    <a href="../produk/daftar_produk.php" class="btn btn-success">Lanjutkan Belanja</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Teks berhasil disalin: ' + text);
            }, function(err) {
                console.error('Gagal menyalin teks: ', err);
                alert('Gagal menyalin teks. Silakan salin manual.');
            });
        }
    </script>
</body>
</html>