<?php
// Termasuk file koneksi.php untuk menghubungkan ke database
include('koneksi/koneksi.php');

// Query lengkap untuk mengambil data produk beserta informasi terkait
$sql_produk = "SELECT
    p.id,
    p.nama AS nama_produk,
    p.deskripsi,
    p.harga,
    p.gambar,
    u.nama AS nama_unit_usaha,
    penjual.nama_toko AS nama_penjual,
    kp.nama_kategori,
    p.asal_desa,
    p.jumlah_terjual,
    AVG(up.rating) AS rating_produk,
    COUNT(up.id) AS jumlah_ulasan,
    d.nama_diskon,
    d.jenis_diskon,
    d.nilai_diskon
FROM produk p
JOIN unit_usaha u ON p.unit_usaha_id = u.id
LEFT JOIN penjual ON p.penjual_id = penjual.pengguna_id
LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
LEFT JOIN ulasan_produk up ON p.id = up.produk_id
LEFT JOIN produk_diskon pd ON p.id = pd.produk_id
LEFT JOIN diskon d ON pd.diskon_id = d.id
WHERE p.status_produk = 'aktif'
GROUP BY p.id
ORDER BY p.tanggal_publikasi DESC";
$result_produk = $conn->query($sql_produk);
$daftar_produk = [];
if ($result_produk && $result_produk->num_rows > 0) {
    while ($row_produk = $result_produk->fetch_assoc()) {
        $daftar_produk[] = $row_produk;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>BUMDes Desa Sinar Petir - Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }
        header {
            background: url('img/desa.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 210px 0;
            text-shadow: 1px 1px 4px black;
        }
        .logo-img {
            max-width: 90px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .typing-effect {
            font-size: 2rem;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            border-right: 2px solid white;
            display: inline-block;
            min-height: 2.5rem;
            animation: blink 0.75s step-end infinite;
        }
        @keyframes blink {
            50% { border-color: transparent }
        }
        .section-title {
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: bold;
            color: #800000;
        }
        .card:hover {
            transform: scale(1.02);
            transition: transform 0.3s;
        }
        .counter {
            font-size: 2.5rem;
            font-weight: bold;
            color: #800000;
        }
        footer {
            background-color: #FF4500;
            color: white;
            padding: 1rem;
            text-align: center;
            margin-top: 40px;
        }
        blockquote {
            font-style: italic;
            color: #555;
            border-left: 5px solid #800000;
            padding-left: 15px;
            margin-top: 1rem;
        }
        .carousel-item img {
            max-height: 710px; /* Menetapkan tinggi maksimum gambar */
            object-fit: cover; /* Agar gambar tidak terdistorsi dan tetap proporsional */
            width: 100%;     /* Gambar mengisi lebar 100% kontainer */
        }
        /* Opsional: Mengatur ukuran carousel container */
        .carousel-inner {
            max-width: 50%; /* Mengatur lebar carousel agar responsif */
            margin: 0 auto; /* Memastikan carousel berada di tengah */
        }
        /* Gaya untuk carousel-caption agar teks lebih terbaca dengan latar belakang */
        .carousel-caption {
            background-color: rgba(0, 0, 0, 0.0); /* Latar belakang semi-transparan */
            color: white; /* Warna teks putih */
        }
        /* Efek hover pada <h5> dan <p> di dalam carousel-caption */
        .carousel-caption h5:hover,
        .carousel-caption p:hover {
            color: #E6E6FA; /* Warna ungu saat hover */
            cursor: pointer; /* Mengubah kursor menjadi tangan saat hover */
        }
        /* Mengatur jarak (spacing) tombol panah di carousel */
        .carousel-control-prev, .carousel-control-next {
            /* Mengatur jarak dari sisi kiri dan kanan carousel */
            margin-left: 350px;   /* Jarak dari kiri di dalam foto corousel*/
            margin-right: 350px; /* Jarak dari kanan */
        }
        .info-card {
            background-color: #FFF8F8;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #FF4500;
        }
        .info-card h5, .info-card p { color: #FF4500; }
        .sosmed-icons a i {
    color: white; /* Warna dasar ikon (jika ingin warna lain sebelum hover) */
    transition: color 0.3s ease; /* Efek transisi warna saat hover */
}

.facebook-icon {
    color: #1877F2; /* Warna biru Facebook */
}

.twitter-icon {
    color: #1DA1F2; /* Warna biru Twitter */
}

.youtube-icon {
    color: #FF0000; /* Warna merah YouTube */
}

.instagram-icon {
    /* Instagram memiliki gradasi, tapi kita gunakan warna dominan birunya */
    color: #C13584; /* Atau Anda bisa mencoba #E1306C */
}

.whatsapp-icon {
    color: #25D366; /* Warna hijau WhatsApp */
}

.telegram-icon {
    color: #229ED9; /* Warna biru Telegram */
}

/* Tambahkan efek hover jika diinginkan */
.sosmed-icons a:hover i {
    opacity: 0.8; /* Contoh: sedikit memudarkan warna saat hover */
}
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
    <div class="container">
        <a class="navbar-brand" href="index.php">BUMDes</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door-fill"></i></a></li>
                <li class="nav-item"><a class="nav-link active" href="produk_pembeli.php">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="berita.html">Berita</a></li>
                <li class="nav-item"><a class="nav-link" href="laporan.html">Laporan</a></li>
                <li class="nav-item"><a class="nav-link" href="galeri.html">Galeri</a></li>
                <li class="nav-item"><a class="nav-link" href="kontak.html">Kontak</a></li>
                <li class="nav-item"><a class="nav-link" href="transaksi.html">Transaksi</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
            </ul>
        </div>
    </div>
</nav>

<header class="text-center">
    <div class="container">
        <img src="img/logo.png" alt="Logo BUMDes Desa Sinar Petir" class="logo-img" data-aos="zoom-in">
        <h1 class="typing-effect" id="typingText"></h1>
        <p class="lead text-white" data-aos="fade-up">Kec. Talang Padang Kab. Tanggamus Prov. Lampung</p>
    </div>
</header>
<div class="container py-4">
    <div class="row text-center">
        <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-truck me-2"></i> Pengiriman Tercepat</h5><p>1-3 hari</p></div></div>
        <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-shield-lock me-2"></i> Kualitas Terjamin</h5><p>Produk terjamin</p></div></div>
        <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-credit-card me-2"></i> Pembayaran Mudah</h5><p>Cash On Delivery</p></div></div>
        <div class="col-md-3"><div class="info-card"><h5><i class="bi bi-person-lines-fill me-2"></i> Konsultasi Gratis</h5><p>Hubungi kami</p></div></div>
    </div>
</div>
<script>
    const text = "Selamat Datang di Website Resmi BUMDes Desa Sinar Petir";
    const typingElement = document.getElementById("typingText");
    let index = 0;
    let isDeleting = false;
    function typeEffect() {
        typingElement.textContent = text.substring(0, index);
        if (!isDeleting && index < text.length) {
            index++;
        } else if (isDeleting && index > 0) {
            index--;
        } else if (index === text.length) {
            isDeleting = true;
            setTimeout(typeEffect, 1500);
            return;
        } else if (index === 0) {
            isDeleting = false;
        }
        setTimeout(typeEffect, isDeleting ? 50 : 100);
    }
    document.addEventListener("DOMContentLoaded", () => {
        typeEffect();
    });
</script>

<div class="container mt-4">
    <h2 class="section-title text-center mb-4">Produk Unggulan Kami</h2>
    <div class="row">
        <?php if (!empty($daftar_produk)): ?>
            <?php
            $i = 0;
            foreach ($daftar_produk as $produk):
                if ($i % 6 == 0 && $i > 0): ?>
                    </div>
                    <div class="row">
                <?php endif; ?>
                <div class="col-md-2 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($i * 100); ?>">
    <div class="card h-100 shadow-sm">
        <a href="detail_produk.php?id=<?php echo $produk['id']; ?>" style="text-decoration: none; color: inherit;">
            <?php if ($produk['gambar']): ?>
                <img src="img/barang/<?php echo htmlspecialchars($produk['gambar']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" style="height: 150px; object-fit: cover;">
            <?php else: ?>
                <img src="img/default.jpg" class="card-img-top" alt="Gambar Default" style="height: 150px; object-fit: cover;">
            <?php endif; ?>
        </a>
        <div class="card-body">
            <h6 class="card-title" style="font-size: 0.8rem;">
                <a href="detail_produk.php?id=<?php echo $produk['id']; ?>" style="text-decoration: none; color: inherit;">
                    <?php echo htmlspecialchars($produk['nama_produk']); ?>
                </a>
            </h6>
            <?php if ($produk['nama_diskon']): ?>
                <div class="mb-1">
                    <span class="badge bg-danger"><?php echo htmlspecialchars($produk['nama_diskon']); ?></span>
                    <?php if ($produk['jenis_diskon'] == 'persen'): ?>
                        <small><?php echo htmlspecialchars($produk['nilai_diskon']); ?>% off</small>
                    <?php elseif ($produk['jenis_diskon'] == 'fixed'): ?>
                        <small>Rp <?php echo number_format($produk['nilai_diskon'], 0, ',', '.'); ?> off</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <p class="card-text" style="font-size: 0.9rem; font-weight: bold;">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></p>
            <div class="d-flex align-items-center mb-2">
                <div class="me-2">
                    <?php if ($produk['rating_produk'] > 0): ?>
                        <i class="bi bi-star-fill text-warning"></i> <small class="text-muted"><?php echo number_format($produk['rating_produk'], 1); ?></small>
                    <?php else: ?>
                        <i class="bi bi-star text-secondary"></i> <small class="text-muted"></small>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?php echo $produk['jumlah_terjual']; ?>+ terjual</small>
            </div>
            <p class="card-text text-muted" style="font-size: 0.7rem;">
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($produk['asal_desa'] ?? 'Lokasi tidak tersedia'); ?>
            </p>
        </div>
    </div>
</div>
<?php $i++; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center">
                <p>Belum ada produk yang tersedia saat ini.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<footer class="footer mt-auto py-4 text-white">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <img src="img/logo.png" alt="Logo Desa" width="60">
                <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                <p>Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
            <div class="sosmed-icons mt-3">
                <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                <a href="#"><i class="bi bi-instagram instagram-icon"></i></a>
                <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
            </div>
            </div>
            <div class="col-md-4 mb-3">
                <h5 class="fw-bold text-orange"><i class="bi bi-chat-dots"></i> HUBUNGI KAMI</h5>
                <p>Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi Lampung Kode Pos 35377.</p>
                <p><i class="bi bi-telephone-fill"></i> Telepon: 081272020355</p>
                <p><i class="bi bi-envelope-fill"></i> Email: snrpetir@gmail.com</p>
            </div>
            <hr class="border-top border-light">
            <div class="text-center">
                <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir </small>
            </div>
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