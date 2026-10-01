<?php
// Termasuk file koneksi.php untuk menghubungkan ke database
include('../koneksi/koneksi.php');

// Mulai sesi
session_start();

// Periksa apakah pengguna sudah login
$pengguna_id = $_SESSION['pengguna_id'] ?? null; // Null jika tidak ada sesi

// Inisialisasi variabel profil pelanggan
$nama_pelanggan = "Akun"; // Default
$foto_pelanggan = "";    // Default

// Hanya jalankan query profil jika pengguna_id ada
if ($pengguna_id) {
    // Query untuk mengambil informasi profil pengguna (nama dan foto) dari tabel pelanggan
    $sql_profil = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
    $stmt_profil = $conn->prepare($sql_profil);
    if ($stmt_profil === false) {
        error_log("Terjadi kesalahan sistem saat memuat profil: " . $conn->error);
    } else {
        $stmt_profil->bind_param("i", $pengguna_id);
        $stmt_profil->execute();
        $result_profil = $stmt_profil->get_result();

        if ($result_profil->num_rows === 1) {
            $data_profil = $result_profil->fetch_assoc();
            $nama_pelanggan = htmlspecialchars($data_profil['nama']);
            $foto_pelanggan = htmlspecialchars($data_profil['foto']);
        }
        $stmt_profil->close();
    }
}

// Ambil jumlah item di keranjang untuk badge navbar
$total_item_keranjang_badge = 0;
if ($pengguna_id) {
    $query_total_cart = "SELECT COUNT(*) AS total_count FROM keranjang_customer WHERE customer_id = ?";
    $stmt_total_cart = $conn->prepare($query_total_cart);
    if ($stmt_total_cart) {
        $stmt_total_cart->bind_param("i", $pengguna_id);
        $stmt_total_cart->execute();
        $result_total_cart = $stmt_total_cart->get_result();
        $row_total_cart = $result_total_cart->fetch_assoc();
        $total_item_keranjang_badge = $row_total_cart['total_count'];
        $stmt_total_cart->close();
    } else {
        error_log("Gagal mempersiapkan kueri total keranjang: " . $conn->error);
    }
}

// Ambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0;
if ($pengguna_id) {
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $pengguna_id);
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
}


// Query untuk mengambil data produk beserta informasi terkait (Tetap sama seperti aslinya)
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

// Tutup koneksi database
$conn->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BUMDes Desa Sinar Petir</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    header {
      background: url('../img/desa.jpg') no-repeat center center;
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
              max-height: 710px;
              object-fit: cover;
              width: 100%;
          }
              .carousel-inner {
              max-width: 50%;
              margin: 0 auto;
          }
                  .carousel-caption {
              background-color: rgba(0, 0, 0, 0.0);
              color: white;
          }
                  .carousel-caption h5:hover,
      .carousel-caption p:hover {
        color: #E6E6FA;
        cursor: pointer;
      }
                  .carousel-control-prev, .carousel-control-next {
              margin-left: 350px;
              margin-right: 350px;
          }

        /* Navbar Styling from promo.php */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            margin-right: 8px;
            max-width: 30px;
        }

        .nav-link {
            color: white !important; /* Disetel putih solid */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important; /* Warna hover sedikit lebih terang */
            font-weight: bold;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .form-control-sm {
            border-radius: 0.25rem 0 0 0.25rem;
        }

        .btn-outline-light {
            border-radius: 0 0.25rem 0.25rem 0;
            border-color: #fff;
            color: #fff;
        }

        .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #FF4500;
        }

        .navbar-nav .badge {
            font-size: 0.75em;
            transform: translateY(-50%);
            top: 40%;
            right: -18px;
            padding: 0.4em 0.7em;
            vertical-align: super;
        }

        .navbar-nav .dropdown-menu {
            background-color: #FF4500; /* Consistent with navbar color */
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        .navbar-nav .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar-nav .dropdown-item:hover {
            background-color: #ffe0b2; /* Light orange on hover */
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* Navbar badge styles */
        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        /* Memastikan ikon hati di NAVBAR tetap putih */
        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important; /* Pastikan selalu putih */
        }

        /* Membuat tulisan nama_pelanggan menjadi putih solid */
        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }

        /* Footer Styling from promo.php */
        .footer {
            background-color: #FF4500; /* Consistent with navbar */
            color: white;
            padding: 2rem 0;
            margin-top: auto;
        }

        .footer p, .footer small {
            color: rgba(255, 255, 255, 0.7);
        }

        .footer h5 {
            color: white;
        }

        .sosmed-icons a {
            color: white;
            font-size: 1.5rem;
            margin: 0 10px;
            transition: transform 0.2s ease-in-out;
        }

        .sosmed-icons a:hover {
            transform: translateY(-3px);
        }

        /* Specific social media icon colors (adjust as needed for current footer) */
        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

        /* Adjust footer layout for consistency */
        .footer .col-md-4:nth-child(1) {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .footer .col-md-4:nth-child(2) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .footer .col-md-4:nth-child(2) p {
            text-align: center;
        }
        /* Dropdown for cart items in navbar */
        #dropdown-keranjang {
            width: 430px;
            z-index: 1000;
            display: none; /* Awalnya tersembunyi, akan diatur oleh JS */
            right: 0;
            left: auto; /* Memastikan dropdown berada di kanan */
            min-width: 280px;
            background-color: white;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
        }

        #dropdown-keranjang h5 {
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 3px;
            display: inline-block;
        }

        #daftar-produk-keranjang li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            flex-direction: row;
            gap: 10px;
        }
        #daftar-produk-keranjang li:last-child {
            border-bottom: none;
        }
        #daftar-produk-keranjang li img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        #daftar-produk-keranjang li .item-details {
            flex-grow: 1;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #daftar-produk-keranjang li .item-details .product-name {
            font-weight: bold;
            font-size: 0.9rem;
        }
        #daftar-produk-keranjang li .item-details .product-variation {
            font-size: 0.75rem;
        }
        #daftar-produk-keranjang li .item-details .item-price {
            color: #FF4500;
            font-size: 0.85rem;
            text-align: right;
            margin-left: auto;
            flex-shrink: 0;
        }
        #pesan-keranjang-kosong {
            text-align: center;
            margin-top: 10px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
        }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
  <div class="container">
    <a class="navbar-brand" href="index.php">
        <img src="../img/logo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
        BUMDes
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli" aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarPembeli">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" href="index.php">Beranda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="produk.php">Produk</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="kategori/kategori.php">Kategori</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="promo/promo.php">Promo</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="artikel/artikel.php">Artikel</a>
        </li>
      </ul>
      <form class="d-flex me-2 mb-2" role="search" action="produk.php" method="GET">
          <div class="input-group">
              <input class="form-control form-control-sm" type="search" placeholder="Cari produk..." aria-label="Search" name="search" value="">
              <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-search"></i></button>
          </div>
      </form>
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="wishlist.php">
            <i class="bi bi-heart-fill"></i>
            <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
              <?php echo $total_item_wishlist; ?>
            </span>
          </a>
        </li>
        <li class="nav-item">
            <div class="position-relative">
                <a class="nav-link" href="keranjang/keranjang.php" id="link-keranjang">
                    <i class="bi bi-cart-fill"></i>
                    <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                        <?php echo $total_item_keranjang_badge; ?>
                    </span>
                </a>
                <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2" style="display: none; width: 430px; z-index: 1000; right: 0; left: auto; min-width: 280px; background-color: white; border-radius: 5px; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; border: 1px solid #eee;">
                    <h5>Baru Ditambahkan</h5>
                    <ul class="list-unstyled" id="daftar-produk-keranjang">
                        <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja kosong.</li>
                    </ul>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                        <a href="keranjang/keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                    </div>
                </div>
            </div>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if ($pengguna_id && $foto_pelanggan): ?>
              <img src="../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
            <?php else: ?>
              <i class="bi bi-person-circle"></i>
            <?php endif; ?>
            <span class="ms-1"><?php echo htmlspecialchars($pengguna_id ? $nama_pelanggan : 'Tamu'); ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <?php if ($pengguna_id): ?>
              <li><a class="dropdown-item" href="profil/profil.php">Profil</a></li>
              <li><a class="dropdown-item" href="keranjang/pesanan_saya.php">Pesanan Saya</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            <?php else: ?>
              <li><a class="dropdown-item" href="login.php">Login</a></li>
            <?php endif; ?>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<header class="text-center">
  <div class="container">
    <img src="../img/logo.png" alt="Logo BUMDes Desa Sinar Petir" class="logo-img" data-aos="zoom-in">
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

<footer class="footer py-4 text-white">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
        <img src="../img/logo.png" alt="Logo Desa" width="80" class="mb-2">
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

  // Fungsi formatRupiah (sama seperti di promo.php)
  function formatRupiah(angka) {
      let number = parseFloat(angka);
      if (isNaN(number)) {
          console.error("Input ke formatRupiah bukan angka yang valid:", angka);
          return "0";
      }
      return new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 0,
          maximumFractionDigits: 0
      }).format(number);
  }

  // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX (sama seperti di promo.php)
  function muatJumlahKeranjangNav() {
      $.ajax({
          url: 'keranjang/get_cart_count.php', // Path disesuaikan
          method: 'GET',
          dataType: 'json',
          success: function(response) {
              if (response.status === 'success') {
                  $('#jumlah-keranjang').text(response.count);
              } else {
                  console.error('Gagal memuat jumlah keranjang di navbar:', response.message);
                  $('#jumlah-keranjang').text('0');
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error (get_cart_count):', status, error);
              $('#jumlah-keranjang').text('0');
          }
      });
  }

  /**
   * Fungsi untuk memuat detail item di dropdown keranjang.
   * Mengambil data keranjang dari backend menggunakan Fetch API dan menampilkannya di UI.
   * (Sama persis seperti di promo.php)
   */
  function muatIsiKeranjangDropdown() {
      const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
      const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
      const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

      jumlahProdukLainnyaSpan.style.display = 'none';
      jumlahProdukLainnyaSpan.textContent = '';

      fetch('keranjang/ambil_keranjang_sementara.php') // Path disesuaikan
          .then(response => {
              if (!response.ok) {
                  throw new Error(`HTTP error! status: ${response.status}`);
              }
              return response.json();
          })
          .then(data => {
              daftarProdukKeranjang.innerHTML = '';

              const displayLimit = 3;
              let totalQuantityOtherProducts = 0;

              if (data.length > 0) {
                  if (pesanKeranjangKosong) {
                      pesanKeranjangKosong.style.display = 'none';
                  }

                  data.forEach((item, index) => {
                      if (index < displayLimit) {
                          const listItem = document.createElement('li');
                          listItem.classList.add('d-flex', 'align-items-center', 'mb-2');
                          listItem.innerHTML = `
                              <img src="../img/barang/${item.gambar_produk}" alt="${item.nama_produk}" class="img-fluid rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                              <div class="flex-grow-1">
                                  <span class="d-block text-truncate" style="max-width: 150px; font-size: 0.85rem;">${item.nama_produk}</span>
                                  ${item.variasi_string ? `<small class="d-block text-muted" style="font-size: 0.75rem;">(${item.variasi_string})</small>` : ''}
                              </div>
                              <div class="ms-auto text-end">
                                  <span class="d-block text-danger fw-bold" style="font-size: 0.9rem;">Rp${formatRupiah(item.harga_satuan)}</span>
                                  <small class="text-muted" style="font-size: 0.8rem;">x${item.quantity}</small>
                              </div>
                          `;
                          daftarProdukKeranjang.appendChild(listItem);
                      } else {
                          totalQuantityOtherProducts += item.quantity;
                      }
                  });

                  if (totalQuantityOtherProducts > 0) {
                      jumlahProdukLainnyaSpan.textContent = `${totalQuantityOtherProducts} Produk Lainnya`;
                      jumlahProdukLainnyaSpan.style.display = 'inline-block';
                  } else {
                      jumlahProdukLainnyaSpan.style.display = 'none';
                  }

              } else {
                  if (pesanKeranjangKosong) {
                      pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                      pesanKeranjangKosong.style.display = 'block';
                  }
                  jumlahProdukLainnyaSpan.style.display = 'none';
              }
          })
          .catch(error => {
              console.error('Error fetching cart items for dropdown:', error);
              if (pesanKeranjangKosong) {
                  pesanKeranjangKosong.textContent = "Gagal memuat detail keranjang. Silakan coba lagi.";
                  pesanKeranjangKosong.style.display = 'block';
              }
              jumlahProdukLainnyaSpan.style.display = 'none';
          });
  }


  // Event listener untuk menampilkan/menyembunyikan dropdown keranjang (sama seperti di promo.php)
  document.addEventListener('DOMContentLoaded', () => {
      const linkKeranjang = document.getElementById('link-keranjang');
      const dropdownKeranjang = document.getElementById('dropdown-keranjang');

      muatJumlahKeranjangNav(); // Panggil saat DOM dimuat
      // muatIsiKeranjangDropdown(); // Tidak perlu panggil di sini, akan dipanggil saat mouseenter

      if (linkKeranjang && dropdownKeranjang) {
          linkKeranjang.addEventListener('mouseenter', () => {
              muatIsiKeranjangDropdown(); // Muat ulang isi dropdown setiap kali mouse masuk
              dropdownKeranjang.style.display = 'block';
          });

          dropdownKeranjang.addEventListener('mouseleave', () => {
              dropdownKeranjang.style.display = 'none';
          });

          document.addEventListener('click', (event) => {
              if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                  dropdownKeranjang.style.display = 'none';
              }
          });
      }
  });

  // Fungsi untuk memperbarui jumlah item di wishlist pada navbar via AJAX
  function updateWishlistItemCount() {
      $.ajax({
          url: 'toggle_wishlist.php?action=get_count', // Path disesuaikan
          method: 'GET',
          dataType: 'json',
          success: function(response) {
              if (response.status === 'success') {
                  $('#wishlist-count').text(response.wishlist_count);
              } else {
                  console.error('Gagal memuat jumlah wishlist di navbar:', response.message);
                  $('#wishlist-count').text('0');
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error (get_wishlist_count):', status, error);
              $('#wishlist-count').text('0');
          }
      });
  }



    function updateWishlistItemCount() {
            // Sama seperti updateCartItemCount, ini adalah 'placeholder'.
            // Badge wishlist di navbar akan menampilkan nilai yang sudah dihitung oleh PHP saat page load.
            // Jika Anda ingin badge wishlist update secara dinamis tanpa refresh halaman,
            // Anda harus menambahkan AJAX request ke endpoint yang menghitung wishlist.
            // Saya tidak menambahkan AJAX baru sesuai instruksi Anda.
    }
    
  $(document).ready(function() {
      // Panggil fungsi update saat halaman dimuat
      muatJumlahKeranjangNav(); // Pastikan badge keranjang terupdate
      updateWishlistItemCount(); // Pastikan badge wishlist terupdate
  });
</script>

</body>
</html>