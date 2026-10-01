<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path koneksi
include '../../header.php'; // Atau file header yang relevan untuk pelanggan

if (!isset($_SESSION['pengguna_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$pesanan = [];

// Query untuk mengambil semua pesanan untuk pengguna yang sedang login
$query = "SELECT
            id,
            kode_pesanan,
            tanggal_pesanan,
            total_pembayaran,
            status_pesanan
          FROM
            pesanan
          WHERE
            customer_id = ?
          ORDER BY
            tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing statement: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $pengguna_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $pesanan[] = $row;
}
$stmt->close();
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
    <title>Riwayat Pesanan - Desa Sinar Petir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 960px; }
        .card-header { background-color: #ff4500; color: white; font-weight: bold; }
        .order-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .order-card .card-body {
            padding: 20px;
        }
        .status-badge-pending { background-color: #ffc107 !important; color: #333 !important; }
        .status-badge-diproses { background-color: #0d6efd !important; }
        .status-badge-dikirim { background-color: #17a2b8 !important; }
        .status-badge-selesai { background-color: #28a745 !important; }
        .status-badge-dibatalkan { background-color: #dc3545 !important; }
        .footer { background-color: #212529; }
        .sosmed-icons a { color: white; margin: 0 8px; font-size: 1.5rem; transition: color 0.3s ease; }
        .sosmed-icons a:hover { color: #ff4500; }
        .text-orange { color: #ff4500 !important; }
    </style>
</head>
<body>
    <?php include '../../navbar.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4 text-center">Riwayat Pesanan Saya</h2>

        <?php if (empty($pesanan)): ?>
            <div class="alert alert-info text-center" role="alert">
                Anda belum memiliki riwayat pesanan. Mulai belanja sekarang!
                <a href="../../index.php" class="alert-link">Kembali ke Beranda</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pesanan as $order): ?>
                    <div class="col-12" data-aos="fade-up">
                        <div class="order-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Pesanan #<?php echo htmlspecialchars($order['kode_pesanan']); ?></span>
                                <span class="badge
                                    <?php
                                        switch ($order['status_pesanan']) {
                                            case 'pending': echo 'bg-warning text-dark'; break;
                                            case 'diproses': echo 'bg-primary'; break;
                                            case 'dikirim': echo 'bg-info'; break;
                                            case 'selesai': echo 'bg-success'; break;
                                            case 'dibatalkan': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary'; break;
                                        }
                                    ?>
                                    "><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['status_pesanan']))); ?></span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Tanggal: <?php echo date('d M Y, H:i', strtotime($order['tanggal_pesanan'])); ?></p>
                                <p class="card-text">Total Pembayaran: <strong class="text-orange"><?php echo formatRupiah($order['total_pembayaran']); ?></strong></p>
                                <a href="detail_pesanan.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="btn btn-outline-primary btn-sm">Lihat Detail</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>