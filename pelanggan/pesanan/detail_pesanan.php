<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path koneksi
include '../../header.php'; // Atau file header yang relevan untuk pelanggan

if (!isset($_SESSION['pengguna_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo "<div class='container my-5'><div class='alert alert-danger text-center'>ID Pesanan tidak valid.</div></div>";
    include '../../footer.php'; // Sertakan footer
    exit();
}

$order_data = null;
$order_items = [];

// Query untuk mengambil detail pesanan utama
$query_order = "SELECT * FROM pesanan WHERE id = ? AND customer_id = ?";
$stmt_order = $conn->prepare($query_order);
if ($stmt_order === false) {
    die("Error preparing order query: " . htmlspecialchars($conn->error));
}
$stmt_order->bind_param("ii", $order_id, $pengguna_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows > 0) {
    $order_data = $result_order->fetch_assoc();
}
$stmt_order->close();

// Jika pesanan ditemukan, ambil detail itemnya
if ($order_data) {
    $query_items = "SELECT
                        dp.quantity,
                        dp.harga_satuan,
                        dp.subtotal,
                        p.nama_produk,
                        p.gambar_produk,
                        pv.nama_variasi,
                        t.nama_toko
                    FROM
                        detail_pesanan dp
                    JOIN
                        produk p ON dp.produk_id = p.id
                    LEFT JOIN
                        produk_variasi pv ON dp.variasi_id = pv.id
                    JOIN
                        toko t ON dp.toko_id = t.id
                    WHERE
                        dp.pesanan_id = ?";
    $stmt_items = $conn->prepare($query_items);
    if ($stmt_items === false) {
        die("Error preparing order items query: " . htmlspecialchars($conn->error));
    }
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    while ($row = $result_items->fetch_assoc()) {
        $order_items[] = $row;
    }
    $stmt_items->close();
}
$conn->close();

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo htmlspecialchars($order_data['kode_pesanan'] ?? 'N/A'); ?> - Desa Sinar Petir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 960px; }
        .card-header { background-color: #ff4500; color: white; font-weight: bold; }
        .product-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        .detail-section { background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; }
        .footer { background-color: #212529; }
        .sosmed-icons a { color: white; margin: 0 8px; font-size: 1.5rem; transition: color 0.3s ease; }
        .sosmed-icons a:hover { color: #ff4500; }
        .text-orange { color: #ff4500 !important; }
    </style>
</head>
<body>
    <?php include '../../navbar.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4 text-center">Detail Pesanan Anda</h2>

        <?php if (!$order_data): ?>
            <div class="alert alert-danger text-center" role="alert">
                Pesanan tidak ditemukan atau bukan milik Anda.
            </div>
        <?php else: ?>
            <div class="detail-section" data-aos="fade-up">
                <h4 class="mb-3">Informasi Pesanan</h4>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Kode Pesanan:</strong> <?php echo htmlspecialchars($order_data['kode_pesanan']); ?></p>
                        <p><strong>Tanggal Pesanan:</strong> <?php echo date('d M Y, H:i', strtotime($order_data['tanggal_pesanan'])); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_data['status_pesanan']))); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_data['metode_pembayaran']))); ?></p>
                        <p><strong>Metode Pengiriman:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_data['metode_pengiriman']))); ?></p>
                    </div>
                </div>
            </div>

            <div class="detail-section" data-aos="fade-up" data-aos-delay="100">
                <h4 class="mb-3">Alamat Pengiriman</h4>
                <p><strong>Penerima:</strong> <?php echo htmlspecialchars($order_data['nama_penerima']); ?></p>
                <p><strong>Telepon:</strong> <?php echo htmlspecialchars($order_data['telepon_penerima']); ?></p>
                <p><strong>Alamat:</strong> <?php echo nl2br(htmlspecialchars($order_data['alamat_lengkap_pengiriman'])); ?></p>
            </div>

            <div class="detail-section" data-aos="fade-up" data-aos-delay="200">
                <h4 class="mb-3">Item Pesanan</h4>
                <?php foreach ($order_items as $item): ?>
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <img src="../../img/produk/<?php echo htmlspecialchars($item['gambar_produk']); ?>" class="me-3" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo htmlspecialchars($item['nama_produk']); ?></h6>
                            <?php if ($item['nama_variasi']): ?>
                                <small class="text-muted">Variasi: <?php echo htmlspecialchars($item['nama_variasi']); ?></small><br>
                            <?php endif; ?>
                            <small class="text-muted">Dari Toko: <?php echo htmlspecialchars($item['nama_toko']); ?></small><br>
                            <small class="text-muted"><?php echo formatRupiah($item['harga_satuan']); ?> x <?php echo htmlspecialchars($item['quantity']); ?></small>
                        </div>
                        <div class="text-end fw-bold">
                            <?php echo formatRupiah($item['subtotal']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="detail-section" data-aos="fade-up" data-aos-delay="300">
                <h4 class="mb-3">Ringkasan Pembayaran</h4>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Harga Produk:</span>
                    <span class="fw-bold"><?php echo formatRupiah($order_data['total_harga_produk']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Biaya Pengiriman:</span>
                    <span class="fw-bold"><?php echo formatRupiah($order_data['biaya_pengiriman']); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fs-5 fw-bold">Total Pembayaran:</span>
                    <span class="fs-5 fw-bold text-orange"><?php echo formatRupiah($order_data['total_pembayaran']); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="../pesanan/riwayat_pesanan.php" class="btn btn-secondary">Kembali ke Riwayat Pesanan</a>
        </div>
    </div>

    <footer class="footer py-4 text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                    <img src="../../img/logoo.png" alt="Logo Desa" width="80" class="mb-2">
                    <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                    <p class="text-center">Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
                    <div class="sosmed-icons mt-3">
                        <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                        <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                        <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                        <a href="#"><i class="bi bi-instagram instagram-icon"></i></a>
                        <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                        <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-3 text-center">
                    <h5 class="fw-bold text-white"><i class="bi bi-chat-dots"></i> HUBUNGI KAMI</h5>
                    <p>Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi Lampung Kode Pos 35377.</p>
                    <p><i class="bi bi-telephone-fill"></i> Telepon: 081272020355</p>
                    <p><i class="bi bi-envelope-fill"></i> Email: snrpetir@gmail.com</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold text-orange"><i class="bi bi-map"></i> PETA LOKASI</h5>
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.100908151834!2d104.5936737!3d-5.2673523!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40e691232c4e23%3A0x6b40e32f5f1c5c1!2sDesa%20Sinar%20Petir!5e0!3m2!1sid!2sid!4v1716347395015!5m2!1sid!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            <hr class="border-top border-light mt-4">
            <div class="text-center mt-3">
                <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir. Dikelola oleh Tim IT Desa.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>