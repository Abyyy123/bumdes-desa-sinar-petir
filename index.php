<?php
// Termasuk file koneksi.php untuk menghubungkan ke database
include('koneksi/koneksi.php');

// Query untuk mengambil data produk beserta informasi terkait
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
  <title>BUMDes Desa Sinar Petir</title>
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
      color: #2c6e49;
    }
    .card:hover {
      transform: scale(1.02);
      transition: transform 0.3s;
    }
    .counter {
      font-size: 2.5rem;
      font-weight: bold;
      color: #198754;
    }
    footer {
      background-color: #2c6e49;
      color: white;
      padding: 1rem;
      text-align: center;
      margin-top: 40px;
    }
    blockquote {
      font-style: italic;
      color: #555;
      border-left: 5px solid #198754;
      padding-left: 15px;
      margin-top: 1rem;
    }
    .carousel-item img {
              max-height: 710px;   /* Menetapkan tinggi maksimum gambar */
              object-fit: cover;   /* Agar gambar tidak terdistorsi dan tetap proporsional */
              width: 100%;       /* Gambar mengisi lebar 100% kontainer */
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
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
  <div class="container">
    <a class="navbar-brand" href="#">BUMDes</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-house-door-fill"></i></a></li>
        <li class="nav-item"><a class="nav-link" href="produk_pembeli.php">Produk</a></li>
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

<div class="container">
  <section data-aos="fade-up">
    <h2 class="section-title"><i class="bi bi-info-circle-fill"></i> Tentang BUMDes</h2>
    <p>BUMDes Desa Sinar Petir hadir sebagai motor penggerak ekonomi desa dengan mengelola unit usaha produktif. Website ini menyediakan informasi lengkap mulai dari profil, produk, hingga laporan keuangan.</p>
  </section>

  <section data-aos="fade-up">
    <h2 class="section-title"><i class="bi bi-person-lines-fill"></i> Profil BUMDes</h2>
    <p>Badan Usaha Milik Desa Maju berkomitmen untuk mendorong pertumbuhan ekonomi lokal melalui pengelolaan usaha produktif milik desa. Di sini Anda bisa mengetahui sejarah, visi, misi BUMDes.</p>
  </section>

  <section data-aos="fade-up">
    <h2 class="section-title"><i class="bi bi-lightbulb-fill"></i> Visi & Misi</h2>
    <h5>Visi:</h5>
    <p>Menjadi lembaga ekonomi desa yang mandiri, profesional, dan berdaya saing.</p>
    <h5>Misi:</h5>
    <ul>
      <li>Meningkatkan kesejahteraan masyarakat desa melalui usaha produktif.</li>
      <li>Mengelola potensi desa secara transparan dan akuntabel.</li>
      <li>Mendorong partisipasi masyarakat dalam pembangunan desa.</li>
    </ul>
  </section>
</div>

<footer class="footer mt-auto py-4 text-white">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-3">
        <img src="img/logo.png" alt="Logo Desa" width="60">
        <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
        <p>Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
        <div class="sosmed-icons mt-3">
          <a href="#"><i class="bi bi-facebook"></i></a>
          <a href="#"><i class="bi bi-twitter"></i></a>
          <a href="#"><i class="bi bi-youtube"></i></a>
          <a href="#"><i class="bi bi-instagram"></i></a>
          <a href="#"><i class="bi bi-whatsapp"></i></a>
          <a href="#"><i class="bi bi-telegram"></i></a>
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
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init();</script>

</body>
</html>