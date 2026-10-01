<?php
session_start(); // Pastikan session dimulai di awal
// detail_produk.php
include '../koneksi/koneksi.php'; // Pastikan path ke koneksi.php sudah benar

// --- Bagian untuk Navbar PHP Logic ---
// Inisialisasi variabel untuk navbar
$user_id = $_SESSION['pengguna_id'] ?? null; // Gunakan null coalescing operator
$nama_pelanggan_navbar = 'Akun';
$foto_pelanggan_navbar = '';
$total_item_keranjang_badge = 0;
$total_item_wishlist_badge = 0; // Pastikan ini juga diinisialisasi
$search_query = $_GET['search'] ?? ''; // Untuk form pencarian di navbar
$kategori_id_filter = $_GET['kategori_id'] ?? null; // Untuk filter kategori di pencarian

if ($user_id) { // Hanya jalankan query jika pengguna sudah login
    // Ambil data pelanggan untuk navbar
    $query_pelanggan_navbar = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
    $stmt_pelanggan_navbar = $conn->prepare($query_pelanggan_navbar);
    if ($stmt_pelanggan_navbar) {
        $stmt_pelanggan_navbar->bind_param("i", $user_id);
        $stmt_pelanggan_navbar->execute();
        $result_pelanggan_navbar = $stmt_pelanggan_navbar->get_result();
        if ($pelanggan_data_navbar = $result_pelanggan_navbar->fetch_assoc()) {
            $nama_pelanggan_navbar = $pelanggan_data_navbar['nama'];
            $foto_pelanggan_navbar = $pelanggan_data_navbar['foto'];
        }
        $stmt_pelanggan_navbar->close();
    }

    // Ambil jumlah item di keranjang untuk badge navbar
    $query_total_cart = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?"; ;
    $stmt_total_cart = $conn->prepare($query_total_cart);
    if ($stmt_total_cart) {
        $stmt_total_cart->bind_param("i", $user_id);
        $stmt_total_cart->execute();
        $result_total_cart = $stmt_total_cart->get_result();
        if ($row_total_cart = $result_total_cart->fetch_assoc()) {
            $total_item_keranjang_badge = $row_total_cart['total_qty'] ?? 0;
        }
        $stmt_total_cart->close();
    }

    // Ambil jumlah item di wishlist untuk badge navbar
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $user_id);
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist_badge = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
} else {
    // Jika belum login, pastikan variabel nama_pelanggan diset 'Tamu' atau 'Akun'
    $nama_pelanggan_navbar = 'Tamu';
}
// --- Akhir Bagian Navbar PHP Logic ---

$cart_items = [];
$total_harga_keranjang = 0; 

// Query untuk mengambil item di keranjang beserta detail produk dan variasinya
// Batasi hanya 5 item untuk ditampilkan di dropdown (kecuali nanti di ambil_keranjang_sementara.php)
$query_cart_dropdown = "
    SELECT
        kc.id AS keranjang_id,
        kc.produk_id,
        kc.variasi_id,
        kc.quantity,
        p.nama AS nama_produk,
        p.gambar AS gambar_produk,
        p.harga AS harga_produk,
        pv.harga AS harga_variasi,
        pv.stok AS stok_variasi,
        p.stok AS stok_produk,
        r.nama_rasa,
        w.nama_warna,
        u.nama_ukuran
    FROM
        keranjang_customer kc
    JOIN
        produk p ON kc.produk_id = p.id
    LEFT JOIN
        produk_variasi pv ON kc.variasi_id = pv.id
    LEFT JOIN
        rasa r ON pv.rasa_id = r.id
    LEFT JOIN
        warna w ON pv.warna_id = w.id
    LEFT JOIN
        ukuran u ON pv.ukuran_id = u.id
    WHERE
        kc.customer_id = ?
    ORDER BY
        kc.tanggal_ditambahkan DESC
    LIMIT 5"; // Batasi untuk pratinjau di dropdown


$stmt_cart_dropdown = $conn->prepare($query_cart_dropdown);
if ($stmt_cart_dropdown === false) {
    error_log("Terjadi kesalahan saat mempersiapkan query keranjang dropdown: " . $conn->error);
    // Tidak perlu exit, karena ini hanya untuk dropdown preview
} else {
    $stmt_cart_dropdown->bind_param("i", $pengguna_id);
    $stmt_cart_dropdown->execute();
    $result_cart_dropdown = $stmt_cart_dropdown->get_result();

    if ($result_cart_dropdown->num_rows > 0) {
        while ($row = $result_cart_dropdown->fetch_assoc()) {
            $harga_satuan = $row['variasi_id'] ? ($row['harga_variasi'] ?? $row['harga_produk']) : $row['harga_produk'];
            $stok_tersedia = $row['variasi_id'] ? ($row['stok_variasi'] ?? $row['stok_produk']) : $row['stok_produk'];

            $variasi_detail = [];
            if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
            if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
            if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }

            $cart_items[] = [
                'keranjang_id' => $row['keranjang_id'],
                'produk_id' => $row['produk_id'],
                'variasi_id' => $row['variasi_id'],
                'nama_produk' => htmlspecialchars($row['nama_produk']),
                'gambar_produk' => htmlspecialchars($row['gambar_produk']),
                'harga_satuan' => $harga_satuan,
                'quantity' => $row['quantity'],
                'subtotal' => $harga_satuan * $row['quantity'],
                'stok_tersedia' => $stok_tersedia,
                'variasi_string' => implode(', ', $variasi_detail)
            ];
            $total_harga_keranjang += ($harga_satuan * $row['quantity']);
        }
    }
    $stmt_cart_dropdown->close();
}

/**
 * Fungsi untuk mencari file media (gambar atau video) di beberapa direktori.
 *
 * @param string $nama_file Nama file media.
 * @param string $jenis_media 'gambar' atau 'video'.
 * @param array $direktoris Array direktori pencarian (opsional, akan ditentukan otomatis jika kosong).
 * @return string Path lengkap file media atau 'placeholder.jpg' jika tidak ditemukan.
 */
function cari_media($nama_file, $jenis_media = 'gambar', $direktoris = [])
{
    // Tentukan prioritas direktori berdasarkan jenis media
    if ($jenis_media == 'video') {
        $direktoris = ['../img/barang/', '../img/produk/']; // Prioritaskan img/barang untuk video
    } else {
        $direktoris = ['../img/produk/', '../img/barang/']; // Prioritaskan img/produk untuk gambar
    }

    foreach ($direktoris as $direktori) {
        $path = $direktori . $nama_file;
        if (file_exists($path)) {
            return $path;
        }
    }
    return 'placeholder.jpg'; // Kembalikan placeholder jika tidak ditemukan
}

// Inisialisasi variabel produk_id agar tidak terjadi error jika parameter GET tidak ada
$produk_id = 0;
$produk = [];
$media_lain = [];
$variasi_produk = [];
$gambar_utama = 'placeholder.jpg';
$penjual = null; // Tambahkan inisialisasi untuk informasi penjual

// Ambil ID produk dari parameter GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $produk_id = $_GET['id'];

    // Ambil data produk utama
    $sql_produk = "SELECT p.*,
                               (SELECT harga FROM produk_variasi WHERE produk_id = p.id ORDER BY harga ASC LIMIT 1) AS harga_variasi_min,
                               COALESCE(p.jumlah_terjual, 0) AS jumlah_terjual
                               FROM produk p
                               WHERE id = ?";
    $stmt_produk = mysqli_prepare($conn, $sql_produk);
    if ($stmt_produk === false) {
        die("Error prepare statement produk: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_produk, "i", $produk_id);
    mysqli_stmt_execute($stmt_produk);
    $result_produk = mysqli_stmt_get_result($stmt_produk);
    if (!$result_produk) {
        die("Error query produk: " . mysqli_error($conn));
    }
    $produk = mysqli_fetch_assoc($result_produk);
    mysqli_stmt_close($stmt_produk);

    // ************* TAMBAHAN PENTING DI SINI *************
    // Konversi harga produk utama ke float
    if ($produk) {
        $produk['harga'] = (float) $produk['harga'];
        // Pastikan harga_lama juga dikonversi jika ada dan digunakan
        $produk['harga_lama'] = (float) ($produk['harga_lama'] ?? $produk['harga']); // Default ke harga jika harga_lama tidak ada
        $produk['harga_variasi_min'] = (float) ($produk['harga_variasi_min'] ?? 0); // Konversi juga

        // Ambil informasi penjual
        if (isset($produk['penjual_id'])) { // Asumsi ada kolom 'penjual_id' di tabel 'produk'
            $sql_penjual = "SELECT pengguna_id, nama_toko, foto, nomor_telepon FROM penjual WHERE pengguna_id = ?"; // Asumsi tabel 'penjual'
            $stmt_penjual = mysqli_prepare($conn, $sql_penjual);
            if ($stmt_penjual === false) {
                die("Error prepare statement penjual: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_penjual, "i", $produk['penjual_id']);
            mysqli_stmt_execute($stmt_penjual);
            $result_penjual = mysqli_stmt_get_result($stmt_penjual);
            $penjual = mysqli_fetch_assoc($result_penjual);
            mysqli_stmt_close($stmt_penjual);

            // Jika ada foto profil penjual, sesuaikan path
            if ($penjual && !empty($penjual['foto'])) { // Menggunakan 'foto' sesuai schema default, bukan 'foto_profil'
                // Asumsi foto profil penjual ada di '../img/foto/'
                $path_foto_penjual = '../img/foto/' . $penjual['foto'];
                if (file_exists($path_foto_penjual)) {
                    $penjual['foto_profil_full_path'] = $path_foto_penjual;
                } else {
                    $penjual['foto_profil_full_path'] = 'placeholder_penjual.jpg'; // Placeholder jika tidak ada
                }
            } else {
                $penjual['foto_profil_full_path'] = 'placeholder_penjual.jpg'; // Placeholder jika tidak ada
            }
        }
    }
    // ****************************************************

    // Ambil media produk lainnya dari tabel produk_media
    $sql_media_lain = "SELECT nama_file, jenis_media FROM produk_media WHERE produk_id = ?";
    $stmt_media_lain = mysqli_prepare($conn, $sql_media_lain);
    if ($stmt_media_lain === false) {
        die("Error prepare statement media lain: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_media_lain, "i", $produk_id);
    mysqli_stmt_execute($stmt_media_lain);
    $result_media_lain = mysqli_stmt_get_result($stmt_media_lain);
    $media_lain = mysqli_fetch_all($result_media_lain, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_media_lain);

    // Ambil variasi produk beserta nama rasa, warna, dan ukuran
    $sql_variasi = "SELECT pv.*,
                                 r.nama_rasa AS rasa_nama,
                                 w.nama_warna AS warna_nama,
                                 u.nama_ukuran AS ukuran_nama,
                                 pv.gambar AS variasi_gambar,
                                 pv.stok AS variasi_stok,
                                 pv.harga AS variasi_harga
                               FROM produk_variasi pv
                               LEFT JOIN rasa r ON pv.rasa_id = r.id
                               LEFT JOIN warna w ON pv.warna_id = w.id
                               LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                               WHERE pv.produk_id = ?";
    $stmt_variasi = mysqli_prepare($conn, $sql_variasi);
    if ($stmt_variasi === false) {
        die("Error prepare statement variasi: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_variasi, "i", $produk_id);
    mysqli_stmt_execute($stmt_variasi);
    $result_variasi = mysqli_stmt_get_result($stmt_variasi);
    // ************* TAMBAHAN PENTING DI SINI *************
    while ($row = mysqli_fetch_assoc($result_variasi)) {
        $row['variasi_harga'] = (float) $row['variasi_harga']; // Konversi ke float
        // Jika Anda memiliki 'harga_lama' di variasi juga, konversi itu
        // $row['original_price'] = (float) ($row['original_price'] ?? $produk['harga_lama']);
        $variasi_produk[] = $row;
    }
    // ****************************************************
    mysqli_stmt_close($stmt_variasi);

    // Tentukan jenis variasi yang ada untuk produk ini
    $ada_rasa = false;
    $ada_warna = false;
    $ada_ukuran = false;
    if (!empty($variasi_produk)) {
        foreach ($variasi_produk as $variasi) {
            if (!empty($variasi['rasa_id'])) {
                $ada_rasa = true;
            }
            if (!empty($variasi['warna_id'])) {
                $ada_warna = true;
            }
            if (!empty($variasi['ukuran_id'])) {
                $ada_ukuran = true;
            }
        }
    }

    /**
     * Fungsi untuk menghitung rating rata-rata produk.
     *
     * @param int $produk_id ID produk.
     * @param mysqli $conn Objek koneksi database.
     * @return float Rating rata-rata.
     */
    function hitung_rating_rata_rata($produk_id, $conn)
    {
        $sql_rating = "SELECT AVG(rating) AS rata_rata FROM ulasan_produk WHERE produk_id = ?";
        $stmt_rating = mysqli_prepare($conn, $sql_rating);
        mysqli_stmt_bind_param($stmt_rating, "i", $produk_id);
        mysqli_stmt_execute($stmt_rating);
        $result_rating = mysqli_stmt_get_result($stmt_rating);
        $row_rating = mysqli_fetch_assoc($result_rating);
        mysqli_stmt_close($stmt_rating);
        return (float) ($row_rating['rata_rata'] ?? 0); // Konversi juga ke float
    }

    /**
     * Fungsi untuk menghitung jumlah rating produk.
     *
     * @param int $produk_id ID produk.
     * @param mysqli $conn Objek koneksi database.
     * @return int Jumlah rating.
     */
    function hitung_jumlah_rating($produk_id, $conn)
    {
        $sql_jumlah_rating = "SELECT COUNT(*) AS jumlah FROM ulasan_produk WHERE produk_id = ?";
        $stmt_jumlah_rating = mysqli_prepare($conn, $sql_jumlah_rating);
        mysqli_stmt_bind_param($stmt_jumlah_rating, "i", $produk_id);
        mysqli_stmt_execute($stmt_jumlah_rating);
        $result_jumlah_rating = mysqli_stmt_get_result($stmt_jumlah_rating);
        $row_jumlah_rating = mysqli_fetch_assoc($result_jumlah_rating);
        mysqli_stmt_close($stmt_jumlah_rating);
        return (int) ($row_jumlah_rating['jumlah'] ?? 0); // Konversi ke int
    }

    // Ambil gambar utama produk
    $gambar_utama_file = $produk['gambar'] ?? '';
    $gambar_utama = cari_media($gambar_utama_file);
} else {
    echo "ID produk tidak valid.";
    exit();
}

// --- BAGIAN BARU UNTUK MENGAMBIL DATA BUMDES / KONTAK ---
$bumdes_kontak = ''; // Inisialisasi variabel untuk menyimpan kontak bumdes
$bumdes_alamat = ''; // Inisialisasi variabel untuk menyimpan alamat bumdes
$bumdes_nama = ''; // Inisialisasi variabel untuk menyimpan nama bumdes (jika ada di tabel bumdes)

// Asumsi Anda memiliki tabel 'bumdes' yang hanya berisi satu baris data atau ID tertentu
// Jika hanya satu baris, Anda bisa langsung SELECT
$query_bumdes_info = "SELECT nama, alamat, kontak FROM bumdes LIMIT 1"; // Sesuaikan kolom jika berbeda
$result_bumdes_info = $conn->query($query_bumdes_info);

if ($result_bumdes_info && $result_bumdes_info->num_rows > 0) {
    $bumdes_data = $result_bumdes_info->fetch_assoc();
    $bumdes_kontak = htmlspecialchars($bumdes_data['kontak']);
    $bumdes_alamat = htmlspecialchars($bumdes_data['alamat']);
    $bumdes_nama = htmlspecialchars($bumdes_data['nama'] ?? ''); // Gunakan ?? '' jika kolom nama mungkin tidak ada
}
// --- AKHIR BAGIAN BARU ---
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $produk['nama'] ?? 'Detail Produk'; ?></title>
    <link rel="stylesheet" href="path/to/your/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
<style>
    /* Global Styles for Main Image and Thumbnails */
    .main-image-container {
        border: none solid #ddd; /* Perbaikan: seharusnya '1px solid #ddd;' jika ingin ada border */
        border-radius: 5px;
        overflow: hidden;
        height: 400px;
    }
    .main-image {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: contain; /* Memastikan gambar terlihat penuh tanpa terpotong */
    }

    /* Styles for Thumbnails Container and Individual Thumbnails */
    .thumbnails-container {
        padding: 0 30px; /* Padding untuk ruang tombol scroll */
        position: relative;
    }
    .thumbnails-inner-wrapper {
        display: flex;
        overflow-x: auto; /* Mengaktifkan scroll horizontal */
        -webkit-overflow-scrolling: touch; /* Untuk smooth scrolling di iOS */
        scroll-behavior: smooth;
        padding: 10px 0; /* Tambahkan padding di sini agar tidak tumpang tindih dengan tombol */
    }
    .thumbnails-inner-wrapper::-webkit-scrollbar {
        display: none; /* Sembunyikan scrollbar Webkit (Chrome, Safari) */
    }
    .thumbnails-inner-wrapper {
        -ms-overflow-style: none; /* Sembunyikan scrollbar IE and Edge */
        scrollbar-width: none; /* Sembunyikan scrollbar Firefox */
    }

    /* General Thumbnail Styling (Applies to both image and video thumbnails) */
    .thumbnails-container .thumbnail {
        cursor: pointer;
        width: 80px;
        height: 80px; /* Atur tinggi agar konsisten */
        flex-shrink: 0; /* Agar thumbnail tidak menyusut */
        border: 1px solid #eee;
        border-radius: 5px;
        margin-right: 5px; /* Jarak antar thumbnail */
        object-fit: cover; /* Memastikan gambar mengisi area thumbnail */
    }
    .thumbnails-container .thumbnail:hover {
        border-color: #007bff;
    }
    .thumbnail.active-thumbnail {
        border: 2px solid #007bff; /* Border yang lebih menonjol untuk thumbnail aktif */
    }

    /* Video Thumbnail Specific Styles */
    .thumbnail-video-container {
        position: relative;
        display: flex; /* Untuk memusatkan ikon play */
        justify-content: center;
        align-items: center;
        overflow: hidden;
        /* Properti ukuran, border, dll. sudah diatur di .thumbnails-container .thumbnail */
        /* Anda bisa menambahkan background-image jika ada gambar preview video yang spesifik */
    }

    /* Ikon Play Overlay untuk Video Thumbnail */
    .play-overlay {
        position: absolute;
        top: 50%; /* Memastikan center vertikal dari parent */
        left: 50%; /* Memastikan center horizontal dari parent */
        transform: translate(-50%, -50%); /* Menggeser elemen kembali sebesar setengah lebarnya sendiri */
        display: flex; /* Untuk memusatkan ikon Font Awesome */
        justify-content: center;
        align-items: center;
        background-color: rgba(0, 0, 0, 0.6); /* Latar belakang gelap transparan */
        border-radius: 50%; /* Membuat efek lingkaran pada background ikon */
        padding: 5px; /* Padding ini akan mengecilkan ukuran lingkaran hitam */
        cursor: pointer;
        color: white; /* Warna ikon Font Awesome */
        /* Menggunakan ukuran eksplisit untuk kontrol lebih baik */
        width: 40px; /* Lebar lingkaran hitam */
        height: 40px; /* Tinggi lingkaran hitam (harus sama dengan width untuk lingkaran sempurna) */
        transition: opacity 0.2s ease-in-out;
        opacity: 1; /* Default opacity */
    }
    .thumbnail-video-container:hover .play-overlay {
        opacity: 0.9; /* Sedikit transparan saat di-hover */
    }
    .play-overlay i {
        font-size: 20px; /* Mengatur ukuran ikon play (segitiga) di dalam lingkaran */
        text-shadow: 0px 0px 5px rgba(0,0,0,0.7); /* Efek bayangan untuk visibilitas */
    }


    /* Scroll Buttons for Thumbnails Container */
    .scroll-button {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background-color: rgba(255, 255, 255, 0.8);
        border: 1px solid #ccc;
        border-radius: 50%;
        padding: 5px 10px;
        cursor: pointer;
        z-index: 10;
        display: flex; /* Untuk memusatkan ikon di dalam tombol */
        align-items: center;
        justify-content: center;
        width: 30px; /* Lebar tombol */
        height: 30px; /* Tinggi tombol */
    }
    .scroll-button.left {
        left: 0px;
    }
    .scroll-button.right {
        right: 0px;
    }

    /* Product Details Section */
    .product-title {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    .rating-value {
        color: #ff9f00;
        font-weight: bold;
    }
    .separator {
        color: #ccc;
    }
    .reviews, .sold {
        color: #777;
        font-size: 0.9rem;
    }

    /* Price Section */
    .price-section {
        margin-bottom: 1rem;
    }
    .current-price {
        font-size: 1.8rem;
        color: #dc3545;
        font-weight: bold;
    }
    .original-price {
        color: #777;
        text-decoration: line-through;
        font-size: 1.1rem;
    }
    .discount {
        background-color: #f0ad4e;
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 3px;
        font-size: 0.9rem;
    }

    /* Variation Section */
    .variation-section h5, .quantity-section label {
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .variation-button {
        border-radius: 5px;
        padding: 0.5rem 0.7rem;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        border: 1px solid #ccc;
        background-color: #fff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
    }
    .variation-button.active {
        border-color: #007bff;
        background-color: rgba(0, 123, 255, 0.2);
        color: #007bff;
    }
    .variation-button img {
        width: 24px;
        height: 24px;
        margin-right: 0.3rem;
        object-fit: cover;
        border-radius: 3px;
    }
    .variation-scrollable {
        max-height: 100px;
        overflow-y: auto;
        padding-right: 5px;
    }
    .variation-scrollable::-webkit-scrollbar {
        width: 8px;
    }
    .variation-scrollable::-webkit-scrollbar-thumb {
        background-color: #ccc;
        border-radius: 4px;
        border: 1px solid transparent;
        background-clip: padding-box;
    }
    .variation-scrollable::-webkit-scrollbar-track {
        background-color: #f1f1f1;
        border-radius: 4px;
    }
    .variation-scrollable::-webkit-scrollbar-thumb:vertical:start {
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
    }
    .variation-scrollable::-webkit-scrollbar-thumb:vertical:end {
        border-bottom-left-radius: 4px;
        border-bottom-right-radius: 4px;
    }
    .variation-scrollable {
        scrollbar-width: thin;
        scrollbar-color: #ccc #f1f1f1;
    }

    /* Quantity Section */
    .quantity-section {
        margin-top: 1rem;
    }
    .quantity-controls {
        display: flex;
        align-items: center;
    }
    .quantity-input-group {
        display: inline-flex;
        align-items: center;
        border: 1px solid #ccc;
        border-radius: 25px;
        overflow: hidden;
    }
    .qty-button {
        background: none;
        border: none;
        padding: 0.6rem 1rem;
        font-size: 1rem;
        cursor: pointer;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
        transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
    }
    .qty-button:focus {
        outline: none;
    }
    .qty-button:active {
        background-color: rgba(0, 0, 0, 0.1);
    }
    .qty-button#qty-minus {
        color: #555;
    }
    .qty-button#qty-plus {
        color: #198754;
        font-weight: bold;
    }
    .qty-input {
        border: none;
        -moz-appearance: textfield;
        appearance: textfield;
        margin: 0;
        text-align: center;
        width: 40px;
        font-size: 1rem;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
    }
    .qty-input:focus {
        outline: none;
    }
    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    #stock-info {
        margin-left: 1rem;
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: bold;
    }
    .quantity-section.disabled-quantity .quantity-input-group .qty-button,
    .quantity-section.disabled-quantity .qty-input {
        background-color: #e9ecef;
        color: #adb5bd;
        border-color: #ced4da;
        cursor: not-allowed;
    }
    .qty-button.active-qty {
        color: #a7f3d0;
    }

    /* Action Buttons */
    .action-buttons button {
        padding: 0.7rem 1.5rem;
        border-radius: 5px;
        font-weight: bold;
    }

    /* Seller profile card within the fixed footer */
    .fixed-seller-actions-container {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: #fff;
        border-top: 1px solid #e0e0e0;
        padding: 10px 15px; /* Add padding for content */
        box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        z-index: 1050; /* Ensure it's above other content */
        display: flex; /* Use flexbox for alignment */
        align-items: center;
        justify-content: space-between; /* Distribute items with space in between */
    }

    .fixed-seller-profile-card {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .fixed-seller-profile-card img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #ccc;
    }

    .fixed-seller-info h5 {
        margin-bottom: 0;
        font-size: 1rem;
        font-weight: bold;
    }

    .fixed-seller-info p {
        margin-bottom: 0;
        font-size: 0.8rem;
        color: #666;
    }

    .fixed-seller-buttons .btn {
        padding: 8px 15px;
        font-size: 0.9rem;
        border-radius: 25px; /* Pill shape */
        margin-left: 10px; /* Space between buttons */
    }

    /* Adjust main container padding to prevent content being hidden by fixed footer */
    body {
        padding-bottom: 80px; /* Adjust based on the height of your fixed footer */
    }
    .ulasan-wrapper {
    /* Ini adalah container di sekitar ulasan jika Anda membuatnya. */
    /* Jika .ulasan-link langsung berada di elemen induk, Anda bisa menerapkan flexbox pada elemen induk tersebut */
    display: flex; /* Menggunakan flexbox */
    justify-content: flex-end; /* Memindahkan konten ke kanan */
    align-items: center; /* Memusatkan secara vertikal */
    width: 100%; /* Memastikan wrapper memenuhi lebar yang tersedia */
    overflow-x: auto; /* Memungkinkan geser horizontal jika konten terlalu lebar */
    -webkit-overflow-scrolling: touch; /* Untuk scrolling yang lebih halus di perangkat sentuh */
    /* Opsional: sembunyikan scrollbar jika tidak ingin terlihat kecuali saat digeser */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
    }

    .ulasan-wrapper::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }

    .ulasan-link {
        /* Tetap gunakan d-flex align-items-center dari Bootstrap */
        white-space: nowrap; /* Mencegah teks ulasan melipat ke baris baru */
        flex-shrink: 0; /* Mencegah elemen ulasan menyusut jika ruang terbatas */
        padding: 5px 0; /* Sedikit padding vertikal agar tidak terlalu mepet jika digeser */
        margin-left: auto; /* Ini akan mendorongnya ke kanan jika berada di dalam flex container */
        /* Pastikan warna teks cocok dengan desain Anda */
        color: #FF4500; /* Contoh warna link, sesuaikan dengan tema Anda */
        text-decoration: none; /* Hapus underline default jika ini link */
    }

    /* Media Queries untuk responsivitas lebih lanjut jika diperlukan */
    @media (max-width: 768px) {
        .ulasan-wrapper {
            /* Anda bisa menambahkan penyesuaian di sini untuk layar kecil jika perlu */
            /* Misalnya, margin atau padding yang berbeda */
        }
    }
    /* General Body Styling */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Header Styling (dari CSS kedua) */
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

        blockquote {
            font-style: italic;
            color: #555;
            border-left: 5px solid #800000;
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

        .info-card {
            background-color: #FFF8F8;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #FF4500;
        }

        .info-card h5, .info-card p { color: #FF4500; }


        /* Navbar Styling */
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
            height: auto;
        }

        .nav-link {
            color: white !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #f0f0f0 !important;
        }

        .nav-link.active {
            color: white !important;
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
            background-color: #FF4500;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        .navbar-nav .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar-nav .dropdown-item:hover {
            background-color: #ffe0b2;
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
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

        .d-flex.justify-content-between.align-items-center.mt-2 {
            margin-top: 15px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
        }

        #jumlah-produk-lainnya {
            color: #6c757d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
            background-color: #FF4500;
            color: white;
            text-decoration: none;
        }

        /* Cart Page Specific Styles */
        .cart-header {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .cart-header > div {
            flex: 1;
            padding: 0 5px;
        }
        .cart-header .form-check {
            flex-basis: 150px;
            flex-shrink: 0;
        }
        .cart-header .col-md-5 { flex-basis: 35%; }
        .cart-header .col-md-2 { flex-basis: 15%; text-align: center; }
        .cart-header .col-md-1 { flex-basis: 10%; text-align: center; }


        .store-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .store-header {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #fdfdfd;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            font-weight: bold;
            color: #333;
        }

        .store-header .form-check {
            margin-right: 15px;
        }

        .store-header .bi-shop {
            font-size: 1.2em;
            margin-right: 8px;
            color: #ff4500;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px dashed #f0f0f0;
        }
        .product-item:last-child {
            border-bottom: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .product-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
            border: 1px solid #eee;
            flex-shrink: 0;
        }

        .product-info-details {
            flex-grow: 1;
        }

        .product-info-details .name {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
            color: #333;
        }

        .product-info-details .variation {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .product-price {
            width: 120px;
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            font-size: 1.05em;
            flex-shrink: 0;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            width: 150px;
            margin: 0 15px;
            flex-shrink: 0;
        }

        .quantity-control .form-control {
            width: 60px;
            text-align: center;
            margin: 0 5px;
        }

        .quantity-control button {
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            font-size: 1.2em;
        }

        .subtotal-item-col {
            width: 120px;
            text-align: right;
            font-weight: bold;
            color: #ff4500;
            flex-shrink: 0;
        }

        .action-col {
            width: 80px;
            text-align: center;
            flex-shrink: 0;
        }

        .btn-remove-item {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 0.9em;
            cursor: pointer;
            padding: 0;
        }
        .btn-remove-item:hover {
            text-decoration: underline;
        }

        .total-section {
            background-color: #f2f2f2;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .total-row.grand-total {
            font-size: 1.3em;
            font-weight: bold;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }

        /* Cart Footer (Bottom Summary) */
        .cart-footer {
            border-top: 1px solid #e0e0e0;
            background-color: #fff;
            padding: 15px 0;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.05);
        }

        .cart-footer .left-section,
        .cart-footer .right-section {
            display: flex;
            align-items: center;
        }

        .cart-footer .total-display {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }

        .cart-footer .total-display .value {
            color: #ff4500;
            font-size: 1.2em;
            margin-left: 5px;
        }

        .btn-checkout-bottom {
            background-color: #ff4500;
            border-color: #ff4500;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
        }
        .btn-checkout-bottom:hover:not(.disabled) {
            background-color: #e03a00;
            border-color: #e03a00;
            color: white;
        }
        .btn-checkout-bottom.disabled {
            background-color: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Empty Cart/Search Result Styling */
        .text-center.py-5 {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 40px !important;
        }

        .text-center.py-5 img {
            filter: grayscale(80%);
            opacity: 0.6;
        }

        .text-center.py-5 .btn-primary {
            background-color: #FF4500;
            border-color: #FF4500;
        }
        .text-center.py-5 .btn-primary:hover {
            background-color: #E63C00;
            border-color: #E63C00;
        }

        /* Footer Styling */
        footer {
            background-color: #FF4500;
            color: white;
            padding: 2rem 0;
            margin-top: auto;
            text-align: center;
        }

        footer p, footer small {
            color: rgba(255, 255, 255, 0.8);
        }

        footer h5 {
            color: white;
            margin-bottom: 1rem;
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

        /* Specific social media icon colors (for non-hover state) */
        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

        footer .col-md-4:nth-child(1) {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        footer .col-md-4:nth-child(2) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        footer .col-md-4:nth-child(2) p {
            text-align: center;
        }


        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .cart-header {
                display: none; /* Hide header on small screens */
            }
            .product-item {
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 10px;
            }
            .product-item img {
                width: 60px;
                height: 60px;
                margin-right: 10px;
            }
            .product-info-details {
                flex-basis: calc(100% - 70px);
                margin-bottom: 10px;
            }
            .product-price, .subtotal-item-col {
                width: auto;
                flex-basis: 48%;
                text-align: left;
                margin-top: 5px;
            }
            .quantity-control {
                width: auto;
                flex-basis: 48%;
                justify-content: flex-end;
                margin: 5px 0;
            }
            .action-col {
                flex-basis: 100%;
                text-align: right;
                margin-top: 10px;
            }
            .cart-footer .form-check,
            .cart-footer .btn-link {
                margin-bottom: 10px;
                margin-right: 0 !important;
            }
        }

        /* --- ATURAN UNTUK MEMASTIKAN WARNA PUTIH SOLID --- */

        /* Membuat ikon hati di produk card menjadi merah muda */
        .card .bi-heart-fill {
            color: palevioletred !important;
        }

        /* Memastikan ikon hati di NAVBAR tetap putih */
        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important;
        }

        /* Membuat tulisan nama_pelanggan menjadi putih solid */
        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }

        /* Pastikan semua nav-link di navbar berwarna putih solid */
        .navbar-nav .nav-link {
            color: white !important;
        }
                /* Mengubah warna teks link pagination */
        .pagination .page-link {
            color: #FF4500;
        }

        /* Mengubah warna latar belakang saat aktif/hover (opsional, tapi disarankan) */
        .pagination .page-item.active .page-link,
        .pagination .page-link:hover {
            background-color: #FF4500; /* Warna latar belakang */
            border-color: #FF4500; /* Warna border */
            color: #fff; /* Warna teks saat aktif/hover agar terlihat jelas */
        }

        /* Mengubah warna teks untuk disabled state agar tetap terlihat */
        .pagination .page-item.disabled .page-link {
            color: #ccc; /* Atau warna lain yang cocok */
        }

        /* Chatbot Styling */
        .chatbot-container {
            position: fixed;
            bottom: 180px !important; /* Adjust based on your fixed seller actions container height */
            right: 20px;
            width: 320px;
            height: 400px;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: none; /* Hidden by default */
            flex-direction: column;
            z-index: 1000; /* Ensure it's above other content but below notification */
            overflow: hidden;
            font-family: Arial, sans-serif;
        }

        .chatbot-header {
            background-color: #FF4500; /* Primary color from your navbar */
            color: white;
            padding: 12px 15px;
            font-size: 1.1em;
            font-weight: bold;
            border-top-left-radius: 9px;
            border-top-right-radius: 9px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab; /* Indicates draggable */
        }

        .chatbot-header i {
            margin-right: 8px;
        }

        .chatbot-header .close-chatbot {
            cursor: pointer;
            font-size: 0.9em;
            padding: 5px;
            transition: background-color 0.2s ease;
            border-radius: 50%;
        }

        .chatbot-header .close-chatbot:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .chatbot-body {
            flex-grow: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: #f9f9f9;
        }

        .chatbot-footer {
            display: flex;
            padding: 10px 15px;
            border-top: 1px solid #eee;
            background-color: #fff;
        }

        .chatbot-footer input {
            flex-grow: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 15px;
            margin-right: 10px;
            font-size: 0.9em;
        }

        .chatbot-footer button {
            background-color: #FF4500;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .chatbot-footer button:hover {
            background-color: #e03a00;
        }

.message-bubble {
    padding: 10px 12px;
    border-radius: 15px;
    margin-bottom: 10px;
    max-width: 80%;
    word-wrap: break-word;
}

.bot-message {
    background-color: #e6e6e6;
    align-self: flex-start;
    border-bottom-left-radius: 2px;
}

.user-message {
    background-color: #dcf8c6;
    align-self: flex-end;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}

.quick-questions {
    margin-bottom: 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.quick-question-btn {
    background-color: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 15px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 0.85em;
    color: #555;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.quick-question-btn:hover {
    background-color: #e6e6e6;
    border-color: #ccc;
}

/* Chatbot Toggle Button */
.chatbot-toggle-btn {
    position: fixed;
    bottom: 80px; /* Aligned with fixed-seller-actions-container */
    right: 60px;
    background-color: #FF4500;
    color: white;
    border: none;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    font-size: 1.8em;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    z-index: 1001; /* Above the chatbot itself */
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.chatbot-toggle-btn:hover {
    background-color: #e03a00;
    transform: translateY(-3px);
}

</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="../img/logoo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
            BUMDes
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli" aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarPembeli">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="produk.php">Produk</a>
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
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">
                        <i class="bi bi-heart-fill"></i>
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                            <?php echo $total_item_wishlist_badge; ?>
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
                        <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2">
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
                        <?php if (!empty($foto_pelanggan_navbar)): ?>
                            <img src="../img/foto/<?php echo htmlspecialchars($foto_pelanggan_navbar); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan_navbar); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <?php if ($user_id): // Tampilkan menu ini hanya jika user sudah login ?>
                            <li><a class="dropdown-item" href="profil/profil.php">Profil</a></li>
                            <li><a class="dropdown-item" href="keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="../login.php">Login</a></li>
                            <li><a class="dropdown-item" href="../register.php">Daftar</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-5">
            <div class="main-image-container">
                <img id="main-image" src="<?php echo $gambar_utama; ?>" alt="<?php echo $produk['nama'] ?? ''; ?>"
                    class="img-fluid main-image"
                    style="<?php echo (strpos($gambar_utama, '.mp4') !== false || strpos($gambar_utama, '.mov') !== false) ? 'display: none;' : ''; ?>">
                <video id="main-video" class="img-fluid main-image" controls
                    style="<?php echo (strpos($gambar_utama, '.mp4') !== false || strpos($gambar_utama, '.mov') !== false) ? '' : 'display: none;'; ?>">
                    <source src="<?php echo $gambar_utama; ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="thumbnails-container mt-2">
                <button class="scroll-button left" onclick="scrollThumbnails(-100)"><i
                        class="fas fa-chevron-left"></i></button>
                <div class="thumbnails-inner-wrapper" id="thumbnail-scroll-container">
                    <?php if (!empty($produk['gambar'])): ?>
                        <?php $gambar_utama_thumbnail = cari_media($produk['gambar'], 'gambar'); ?>
                        <img src="<?php echo $gambar_utama_thumbnail; ?>" alt="Gambar Utama Produk"
                            class="thumbnail img-thumbnail mr-2 active-thumbnail" data-media-type="gambar"
                            data-media-src="<?php echo $gambar_utama_thumbnail; ?>" onclick="changeMainMedia(this)">
                    <?php endif; ?>
                    <?php if (isset($media_lain) && is_array($media_lain) && count($media_lain) > 0): ?>
                        <?php foreach ($media_lain as $media): ?>
                            <?php $path_media_lain = cari_media($media['nama_file'], $media['jenis_media']); ?>
                            <?php if ($media['jenis_media'] == 'gambar'): ?>
                                <img src="<?php echo $path_media_lain; ?>" alt="Gambar Produk Lain"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_media_lain; ?>" onclick="changeMainMedia(this)">
                            <?php elseif ($media['jenis_media'] == 'video'): ?>
                                <div class="thumbnail-video-container position-relative mr-2 thumbnail img-thumbnail"
                                    data-media-type="video" data-media-src="<?php echo $path_media_lain; ?>"
                                    onclick="changeMainMedia(this)">
                                    <div class="play-overlay"><i class="fas fa-play"></i></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php
                    // Tampilkan gambar variasi warna dan rasa yang unik sebagai thumbnail
                    $gambar_warna_unik = [];
                    $gambar_rasa_unik = [];
                    if (!empty($variasi_produk)):
                        foreach ($variasi_produk as $variasi):
                            if (!empty($variasi['warna_nama']) && !isset($gambar_warna_unik[$variasi['warna_nama']]) && !empty($variasi['variasi_gambar'])):
                                $path_gambar_variasi_warna = cari_media($variasi['variasi_gambar'], 'gambar');
                                ?>
                                <img src="<?php echo $path_gambar_variasi_warna; ?>"
                                    alt="Variasi Warna <?php echo $variasi['warna_nama'] ?? ''; ?>"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_gambar_variasi_warna; ?>"
                                    onclick="changeMainMedia(this)">
                                <?php
                                $gambar_warna_unik[$variasi['warna_nama']] = true;
                            endif;

                            if (!empty($variasi['rasa_nama']) && !isset($gambar_rasa_unik[$variasi['rasa_nama']]) && !empty($variasi['variasi_gambar'])):
                                $path_gambar_variasi_rasa = cari_media($variasi['variasi_gambar'], 'gambar');
                                ?>
                                <img src="<?php echo $path_gambar_variasi_rasa; ?>"
                                    alt="Variasi Rasa <?php echo $variasi['rasa_nama'] ?? ''; ?>"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_gambar_variasi_rasa; ?>"
                                    onclick="changeMainMedia(this)">
                                <?php
                                $gambar_rasa_unik[$variasi['rasa_nama']] = true;
                            endif;
                        endforeach;
                    endif;
                    ?>
                </div>
                <button class="scroll-button right" onclick="scrollThumbnails(100)"><i
                        class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <div class="col-md-7">
            <h1 class="product-title"><?php echo $produk['nama'] ?? ''; ?></h1>
            <div class="d-flex align-items-center mb-2">
                <span class="rating">
                    <?php
                    $rating_rata_rata = hitung_rating_rata_rata($produk_id, $conn);
                    for ($i = 0; $i < floor($rating_rata_rata); $i++) {
                        echo '<i class="fas fa-star text-warning"></i>';
                    }
                    if (fmod($rating_rata_rata, 1) >= 0.5) {
                        echo '<i class="fas fa-star-half-alt text-warning"></i>';
                    }
                    for ($i = ceil($rating_rata_rata); $i < 5; $i++) {
                        echo '<i class="far fa-star text-warning"></i>';
                    }
                    ?>
                </span>
                <span class="rating-value ml-1"><?php echo number_format($rating_rata_rata, 1); ?></span>
                <span class="separator ml-2">|</span>
                <span class="reviews ml-2"><?php echo hitung_jumlah_rating($produk_id, $conn); ?> Penilaian</span>
                <span class="separator ml-2">|</span>
                <span class="sold ml-2"><span id="live-jumlah-terjual"><?php echo $produk['jumlah_terjual'] ?? '0'; ?></span>+ Terjual</span>
            </div>

            <?php if (isset($produk['harga_lama']) && $produk['harga_lama'] > $produk['harga'] && empty($variasi_produk)): ?>
                <div class="mb-2">
                    <span class="original-price ">Rp <?php echo number_format($produk['harga_lama'], 0, ',', '.'); ?></span>
                </div>
            <?php endif; ?>

            <div class="price-section">
                <span class="current-price" id="display-price">
                    <?php if (!empty($variasi_produk) && isset($produk['harga_variasi_min']) && $produk['harga_variasi_min'] < $produk['harga']): ?>
                        Rp <?php echo number_format($produk['harga_variasi_min'], 0, ',', '.'); ?> - Rp
                        <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
                    <?php elseif (!empty($variasi_produk) && isset($produk['harga_variasi_min']) && $produk['harga_variasi_min'] == $produk['harga']): ?>
                        Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
                    <?php else: ?>
                        Rp <?php echo number_format($produk['harga'] ?? 0, 0, ',', '.'); ?>
                    <?php endif; ?>
                </span>
                <?php if (isset($produk['harga_lama']) && $produk['harga_lama'] > $produk['harga'] && empty($variasi_produk)): ?>
                    <?php
                    $discount = (($produk['harga_lama'] - $produk['harga']) / $produk['harga_lama']) * 100;
                    ?>
                    <span class="discount ml-2">-<?php echo round($discount); ?>%</span>
                <?php endif; ?>
            </div>
            <div class="variation-section mt-3" id="variation-container">
                <h5>Variasi</h5>
                <div class="d-flex flex-wrap">
                    <?php if (!empty($variasi_produk)): ?>
                        <?php if ($ada_rasa): ?>
                            <div class="mb-2 w-100">
                                <label class="font-weight-bold">Rasa:</label><br>
                                <div class="variation-scrollable">
                                    <?php
                                    $rasa_unik = [];
                                    foreach ($variasi_produk as $variasi):
                                        if (!empty($variasi['rasa_nama']) && !in_array($variasi['rasa_nama'], $rasa_unik)):
                                            $gambar_variasi_rasa = !empty($variasi['variasi_gambar']) ? cari_media($variasi['variasi_gambar']) : $gambar_utama;
                                            ?>
                                            <button class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
                                                data-rasa="<?php echo htmlspecialchars($variasi['rasa_nama']); ?>"
                                                data-gambar="<?php echo $gambar_variasi_rasa; ?>" data-jenis="rasa"
                                                <?php if ($variasi['variasi_stok'] <= 0)
                                                    echo 'disabled'; ?>>
                                                <?php if (!empty($variasi['variasi_gambar'])): ?>
                                                    <img src="<?php echo cari_media($variasi['variasi_gambar']); ?>"
                                                        alt="<?php echo htmlspecialchars($variasi['rasa_nama']); ?>">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($variasi['rasa_nama']); ?>
                                            </button>
                                            <?php
                                            $rasa_unik[] = $variasi['rasa_nama'];
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($ada_warna): ?>
                            <div class="mb-2 w-100">
                                <label class="font-weight-bold">Warna:</label><br>
                                <div class="variation-scrollable">
                                    <?php
                                    $warna_unik = [];
                                    foreach ($variasi_produk as $variasi):
                                        if (!empty($variasi['warna_nama']) && !in_array($variasi['warna_nama'], $warna_unik)):
                                            $gambar_variasi_warna = !empty($variasi['variasi_gambar']) ? cari_media($variasi['variasi_gambar']) : $gambar_utama;
                                            ?>
                                            <button class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
                                                data-warna="<?php echo htmlspecialchars($variasi['warna_nama']); ?>"
                                                data-gambar="<?php echo $gambar_variasi_warna; ?>" data-jenis="warna"
                                                <?php if ($variasi['variasi_stok'] <= 0)
                                                    echo 'disabled'; ?>>
                                                <?php if (!empty($variasi['variasi_gambar'])): ?>
                                                    <img src="<?php echo cari_media($variasi['variasi_gambar']); ?>"
                                                        alt="<?php echo htmlspecialchars($variasi['warna_nama']); ?>">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($variasi['warna_nama']); ?>
                                            </button>
                                            <?php
                                            $warna_unik[] = $variasi['warna_nama'];
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($ada_ukuran): ?>
                            <div class="mb-2 w-100">
                                <label class="font-weight-bold">Ukuran:</label><br>
                                <div class="variation-scrollable">
                                    <?php
                                    $ukuran_unik = [];
                                    foreach ($variasi_produk as $variasi) {
                                        if (!empty($variasi['ukuran_nama']) && !in_array($variasi['ukuran_nama'], $ukuran_unik)) {
                                            $ukuran_unik[] = $variasi['ukuran_nama'];
                                        }
                                    }
                                    foreach ($ukuran_unik as $ukuran): ?>
                                        <?php
                                        $variasi_ukuran_gambar = '';
                                        foreach ($variasi_produk as $v) {
                                            if (!empty($v['ukuran_nama']) && $v['ukuran_nama'] == $ukuran && !empty($v['variasi_gambar'])) {
                                                $variasi_ukuran_gambar = cari_media($v['variasi_gambar']);
                                                break;
                                            }
                                        }
                                        $gambar_ukuran = $variasi_ukuran_gambar ?: '';
                                        ?>
                                        <button class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
                                            data-ukuran="<?php echo htmlspecialchars($ukuran); ?>"
                                            data-gambar="<?php echo $gambar_ukuran; ?>" data-jenis="ukuran" <?php
                                            $is_disabled = true;
                                            foreach ($variasi_produk as $v) {
                                                if ($v['ukuran_nama'] == $ukuran && $v['variasi_stok'] > 0) {
                                                    $is_disabled = false;
                                                    break;
                                                }
                                            }
                                            if ($is_disabled)
                                                echo 'disabled';
                                            ?>>
                                            <?php echo htmlspecialchars($ukuran); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p id="no-variation-message">Tidak ada variasi untuk produk ini.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="quantity-section mt-3 <?php echo empty($variasi_produk) ? '' : 'disabled-quantity'; ?>">
                <label for="quantity">Kuantitas:</label>
                <div class="d-flex align-items-center quantity-controls">
                    <div class="quantity-input-group">
                        <button type="button" id="qty-minus" class="qty-button">-</button>
                        <input type="number" id="quantity" class="qty-input" value="1" min="1">
                        <button type="button" id="qty-plus" class="qty-button">+</button>
                    </div>
                    <span id="stock-info" class="ml-2">
                        <?php if (empty($variasi_produk) && isset($produk['stok'])): ?>
                            Stok: <span id="current-stock"><?php echo number_format($produk['stok'], 0, ',', '.'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div id="stock-error-message" class="text-danger mt-2 d-none"></div>
            </div>
            <div class="action-buttons mt-4 d-flex align-items-center">
                <button class="btn btn-warning mr-2" id="add-to-cart-btn" disabled>
                    <i class="bi bi-cart-plus fs-6 mr-1"></i> Masukkan Keranjang
                </button>
                <button class="btn btn-danger mr-2" id="buy-now-btn" disabled>
                    <i class="bi bi-bag-check-fill mr-1"></i> Beli Sekarang
                </button>
                <div class="ulasan-link d-flex align-items-center" style="cursor: pointer;" onclick="scrollToUlasan()">
                    <i class="bi bi-chat-dots mr-1"></i> Ulasan (<span
                        id="jumlah-ulasan"><?php echo hitung_jumlah_rating($produk_id ?? 0, $conn); ?></span>)
                </div>
            </div>
            </div>
    </div>
    <div class="row mt-4">
        <div class="col-12">
            <h3>Deskripsi Produk</h3>
            <p><?php echo nl2br(htmlspecialchars($produk['deskripsi'] ?? '')); ?></p>
        </div>
    </div>

    <?php include 'ulasan.php'; // Path disesuaikan ?>
</div>

<div class="chatbot-container" id="non-ai-chatbot">
    <div class="chatbot-header" id="chatbot-header">
        <i class="bi bi-chat-text-fill"></i> Chat Bantuan
        <i class="bi bi-x-lg close-chatbot" id="close-chatbot"></i>
    </div>
    <div class="chatbot-body">
            <div class="message-bubble bot-message">Halo, Bestie! Selamat datang di chatbot <?php echo $bumdes_nama; ?>! Aku di sini siap bantu kamu seputar produk dan layanan kami. Pilih pertanyaan cepat di bawah atau ketik aja yang mau kamu tanyain!</div>
            <div class="quick-questions">
                <button class="quick-question-btn" data-question="Bagaimana cara memesan produk?">Bagaimana cara memesan produk?</button>
                <button class="quick-question-btn" data-question="Bagaimana cara melacak pesanan saya?">Bagaimana cara melacak pesanan saya?</button>
                <button class="quick-question-btn" data-question="Apakah bisa COD?">Apakah bisa COD?</button>
                <button class="quick-question-btn" data-question="Bagaimana cara menghubungi penjual?">Bagaimana cara menghubungi penjual?</button>
                <button class="quick-question-btn" data-question="Metode pembayaran apa saja yang tersedia?">Metode pembayaran apa saja yang tersedia?</button>
            </div>
            <div id="chat-messages">
            </div>
        </div>
    <div class="chatbot-footer">
        <input type="text" id="user-input" placeholder="Ketik pesan Anda...">
        <button id="send-button"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<button class="chatbot-toggle-btn" id="chatbot-toggle-btn">
    <i class="bi bi-chat-text-fill"></i>
</button>

<?php if ($penjual): ?>
    <?php
    $sellerPhoneNumber = '';
    if (isset($penjual['nomor_telepon']) && !empty($penjual['nomor_telepon'])) {
        $sellerPhoneNumber = preg_replace('/[^0-9]/', '', $penjual['nomor_telepon']);
        if (substr($sellerPhoneNumber, 0, 1) === '0') {
            $sellerPhoneNumber = '62' . substr($sellerPhoneNumber, 1);
        } else if (substr($sellerPhoneNumber, 0, 2) !== '62') {
            $sellerPhoneNumber = '62' . $sellerPhoneNumber;
        }
    }
    ?>
    <div class="fixed-seller-actions-container">
        <div class="fixed-seller-profile-card">
            <img src="<?php echo !empty($penjual['foto_profil_full_path']) ? htmlspecialchars($penjual['foto_profil_full_path']) : '../img/foto/penjual.jpeg'; ?>" alt="Foto Profil Penjual">
            <div class="fixed-seller-info">
                <h5><?php echo htmlspecialchars($penjual['nama_toko'] ?? $penjual['username'] ?? 'Penjual'); ?></h5>
                <p>Penjual</p>
            </div>
        </div>
        <div class="fixed-seller-buttons">
            <a href="profil_penjual.php?id=<?php echo $penjual['pengguna_id']; ?>" class="btn btn-outline-primary">
                <i class="bi bi-shop mr-1"></i> Kunjungi Toko
            </a>
            <?php if (!empty($sellerPhoneNumber)): ?>
                <a href="#" id="chat-seller-whatsapp-btn" target="_blank" class="btn btn-success">
                    <i class="bi bi-whatsapp"></i> Chat Penjual
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div id="cart-success-notification" style="
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 20px 30px;
        border-radius: 8px;
        text-align: center;
        z-index: 1050;
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
        min-width: 250px;
    ">
        <div style="
            background-color: #28a745;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
        ">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="white" class="bi bi-check-lg" viewBox="0 0 16 16">
                <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.01L3.245 8.95c-.287-.29-.287-.756 0-1.047a.733.733 0 0 1 1.047 0L7.19 10.914l5.545-6.944z"/>
            </svg>
        </div>
        <p style="font-size: 1.2em; font-weight: bold; margin-bottom: 0; line-height: 1.2;">Produk telah ditambahkan ke keranjang belanja</p>
    </div>
    <input type="hidden" id="whatsapp-product-name" value="<?php echo htmlspecialchars($produk['nama'] ?? 'Produk Tidak Diketahui'); ?>">
    <input type="hidden" id="whatsapp-product-price" value="<?php echo htmlspecialchars(number_format($produk['harga'] ?? 0, 0, ',', '.')); ?>">
    <input type="hidden" id="whatsapp-selected-rasa" value="">
    <input type="hidden" id="whatsapp-selected-warna" value="">
    <input type="hidden" id="whatsapp-selected-ukuran" value="">
    <input type="hidden" id="whatsapp-seller-phone" value="<?php echo htmlspecialchars($sellerPhoneNumber); ?>">
<?php endif; ?>
<footer class="footer mt-auto py-4 text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <img src="../img/logo.png" alt="Logo Desa" width="60">
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
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold text-orange"><i class="bi bi-map"></i> PETA LOKASI</h5>
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.100908151834!2d104.5936737!3d-5.2673523!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e40e691232c4e23%3A0x6b40e32f5f1c5c1!2sDesa%20Sinar%20Petir!5e0!3m2!1sid!2sid!4v1716347395015!5m2!1sid!2sid" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            <hr class="border-top border-light">
            <div class="text-center">
                <small>Hak cipta © 2025 - Pemerintah Desa Sinar Petir </small>
            </div>
        </div>
    </footer>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
// Variabel-variabel untuk elemen DOM
const quantitySection = document.querySelector('.quantity-section');
const quantityInput = document.getElementById('quantity');
const qtyMinusButton = document.getElementById('qty-minus');
const qtyPlusButton = document.getElementById('qty-plus');
const stockInfoSpan = document.getElementById('stock-info');
const priceSpan = document.querySelector('.current-price');
const originalPriceDisplay = document.querySelector('.price-section .original-price');
const variationButtons = document.querySelectorAll('.variation-button');
const addToCartButton = document.getElementById('add-to-cart-btn');
const buyNowButton = document.getElementById('buy-now-btn');
const stockErrorMessage = document.getElementById('stock-error-message');
const noVariationMessage = document.getElementById('no-variation-message');

// Pastikan variabel PHP di-encode dengan benar untuk JavaScript
    // Variabel ini akan tersedia di seluruh JavaScript setelah dicetak
    const bumdesKontak = <?php echo json_encode($bumdes_kontak); ?>;
    const bumdesAlamat = <?php echo json_encode($bumdes_alamat); ?>;
    const bumdesNama = <?php echo json_encode($bumdes_nama); ?>;

// WhatsApp elements
const whatsappProductNameInput = document.getElementById('whatsapp-product-name');
const whatsappProductPriceInput = document.getElementById('whatsapp-product-price');
const whatsappSelectedRasaInput = document.getElementById('whatsapp-selected-rasa');
const whatsappSelectedWarnaInput = document.getElementById('whatsapp-selected-warna');
const whatsappSelectedUkuranInput = document.getElementById('whatsapp-selected-ukuran');
const whatsappSellerPhoneInput = document.getElementById('whatsapp-seller-phone');
const chatSellerWhatsappBtn = document.getElementById('chat-seller-whatsapp-btn');

// Live Jumlah Terjual element
const liveJumlahTerjualElement = document.getElementById('live-jumlah-terjual');

// Elemen-elemen untuk Badge Keranjang dan Wishlist di Navbar
const jumlahKeranjangBadge = document.getElementById('jumlah-keranjang');
const wishlistCountBadge = document.getElementById('wishlist-count'); // Pastikan ID ini ada di HTML navbar Anda
const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');
const linkKeranjang = document.getElementById('link-keranjang');
const dropdownKeranjang = document.getElementById('dropdown-keranjang');


let selectedVariation = {};
let productPrice = <?php echo $produk['harga'] ?? 0; ?>;
let productOriginalPrice = <?php echo $produk['harga_lama'] ?? 0; ?>;
let productStock = <?php echo $produk['stok'] ?? 0; ?>;
let currentProductStock = productStock; // Variabel untuk menyimpan stok produk atau variasi yang dipilih

// PASTIKAN VARIABEL PHP INI TERSEDIA DAN BERNILAI TRUE/FALSE DARI QUERY DATABASE ANDA
const adaRasa = <?php echo $ada_rasa ? 'true' : 'false'; ?>; // Harus diisi dari PHP berdasarkan apakah produk punya variasi rasa
const adaWarna = <?php echo $ada_warna ? 'true' : 'false'; ?>; // Sudah ada
const adaUkuran = <?php echo $ada_ukuran ? 'true' : 'false'; ?>; // Sudah ada

// PASTIKAN VARIABEL PHP INI TERSEDIA DAN MENGANDUNG DATA VARIASI DENGAN RASA_NAMA, WARNA_NAMA, UKURAN_NAMA
const allVariations = <?php echo json_encode($variasi_produk); ?>;

let gambarDipilih = '<?php echo htmlspecialchars($gambar_utama); ?>'; // Inisialisasi dengan gambar utama
const mainImageElement = document.getElementById('main-image'); // Dapatkan elemen gambar utama
const mainVideoElement = document.getElementById('main-video'); // Dapatkan elemen video utama
const mainImageContainerElement = document.querySelector('.main-image-container'); // Dapatkan kontainer utama

// Elemen-elemen untuk scroll thumbnail
const thumbnailScrollContainer = document.getElementById('thumbnail-scroll-container');
const prevThumbnailButton = document.getElementById('prev-thumbnail');
const nextThumbnailButton = document.getElementById('next-thumbnail');

const variationContainer = document.getElementById('variation-container');

// Inisialisasi status thumbnail aktif dan kondisi variasi saat DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    const initialMainMedia = mainImageContainerElement.querySelector('img') || mainImageContainerElement.querySelector('video');
    if (initialMainMedia) {
        const initialSrc = initialMainMedia.getAttribute('src');
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumb => {
            if (thumb.dataset.mediaSrc === initialSrc) {
                thumb.classList.add('active-thumbnail');
            }
        });
    }

    // Panggil updateButtonStates untuk inisialisasi awal
    updateButtonStates();

    // Sembunyikan atau tampilkan kontainer variasi dan pesan "tidak ada variasi"
    if (allVariations.length === 0) {
        if (variationContainer) {
            variationContainer.style.display = 'none';
        }
        if (noVariationMessage) {
            noVariationMessage.style.display = 'block'; // Tampilkan pesan "tidak ada variasi"
            noVariationMessage.textContent = 'Tidak ada variasi yang tersedia.';
        }
        // Aktifkan kontrol kuantitas dan tombol karena tidak ada variasi
        enableQuantityControls(productStock);
        addToCartButton.disabled = false;
        buyNowButton.disabled = false;
        quantitySection.classList.remove('disabled-quantity');
    } else {
        // Jika ada variasi, pastikan kontainer variasi terlihat
        if (variationContainer) {
            variationContainer.style.display = 'block';
        }
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none'; // Sembunyikan jika ada variasi
        }
        disableQuantityControls(); // Defaultnya disabled sampai variasi dipilih
    }

    // Initialize WhatsApp message
    updateWhatsAppMessage();

    // Inisialisasi dan panggil pembaruan jumlah terjual real-time
    const produkId = <?php echo $produk_id; ?>;
    // Panggil fungsi sekali saat halaman dimuat
    updateJumlahTerjual(produkId);
    // Atur agar fungsi dipanggil setiap 10 detik (10000 milidetik)
    setInterval(() => updateJumlahTerjual(produkId), 10000);

    // Panggil fungsi untuk memperbarui badge keranjang dan wishlist saat DOM dimuat
    muatJumlahKeranjangNav();
    updateWishlistItemCount();

    // Event listener untuk dropdown keranjang
    if (linkKeranjang && dropdownKeranjang) {
        linkKeranjang.addEventListener('mouseenter', () => {
            muatIsiKeranjangDropdown(); // Muat ulang isi dropdown setiap kali mouse masuk
            dropdownKeranjang.style.display = 'block';
        });

        // Menggunakan event 'mouseleave' pada linkKeranjang juga untuk menyembunyikan dropdown
        // Atau bisa juga pada dropdownKeranjang itu sendiri jika ingin lebih fleksibel
        dropdownKeranjang.addEventListener('mouseleave', () => {
            dropdownKeranjang.style.display = 'none';
        });

        // Menyembunyikan dropdown jika klik di luar area linkKeranjang dan dropdownKeranjang
        document.addEventListener('click', (event) => {
            if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                dropdownKeranjang.style.display = 'none';
            }
        });
    }
});

// --- Fungsi-fungsi Utama ---

function changeMainMedia(mediaSrc, mediaType) {
    const mainImageContainer = document.querySelector('.main-image-container');
    const currentMainVideo = mainImageContainer.querySelector('video'); // Dapatkan elemen video yang sedang aktif

    // Jika ada video yang sedang aktif, pause sebelum menggantinya
    if (currentMainVideo) {
        currentMainVideo.pause();
        currentMainVideo.currentTime = 0; // Opsional: reset ke awal
    }

    mainImageContainer.innerHTML = ''; // Kosongkan kontainer

    if (mediaType === 'video') {
        const video = document.createElement('video');
        video.id = 'main-video';
        video.classList.add('img-fluid', 'main-image');
        video.controls = true;
        video.autoplay = true;
        video.muted = true; // Opsional: muted agar autoplay tidak mengganggu
        video.loop = true;

        const source = document.createElement('source');
        source.src = mediaSrc;
        source.type = 'video/mp4';
        video.appendChild(source);

        mainImageContainer.appendChild(video);
    } else {
        const img = document.createElement('img');
        img.id = 'main-image';
        img.src = mediaSrc;
        img.alt = '<?php echo htmlspecialchars($produk['nama'] ?? ''); ?>';
        img.classList.add('img-fluid', 'main-image');
        mainImageContainer.appendChild(img);
    }
}

function enableQuantityControls(stock) {
    quantityInput.disabled = false;
    qtyMinusButton.disabled = false;
    qtyPlusButton.disabled = false;
    quantitySection.classList.remove('disabled-quantity');
    stockInfoSpan.textContent = 'Stok: ' + formatRupiah(stock);
    updateQuantityInputMax(); // Pastikan state tombol sesuai dengan stok yang baru
}

function disableQuantityControls() {
    quantityInput.disabled = true;
    qtyMinusButton.disabled = true;
    qtyPlusButton.disabled = true;
    quantityInput.value = 1; // Default ke 1 saat disabled
    stockInfoSpan.textContent = 'Stok: <?php if (isset($produk['stok'])) { echo number_format($produk['stok'], 0, ',', '.'); } ?>';
    priceSpan.textContent = 'Rp ' + formatRupiah(productPrice);
    if (originalPriceDisplay) {
        originalPriceDisplay.classList.remove('d-none');
        originalPriceDisplay.textContent = 'Rp ' + formatRupiah(productOriginalPrice);
    }
    addToCartButton.disabled = true;
    buyNowButton.disabled = true;
    quantitySection.classList.add('disabled-quantity');
    stockErrorMessage.classList.add('d-none');
}

function updatePriceAndStock(price, stock) {
    priceSpan.textContent = 'Rp ' + formatRupiah(price);
    whatsappProductPriceInput.value = formatRupiah(price); // Update hidden field for WhatsApp
    stockInfoSpan.textContent = 'Stok: ' + formatRupiah(stock);
    currentProductStock = stock;

    if (originalPriceDisplay) {
        let actualOriginalPrice = productOriginalPrice;
        let actualPrice = productPrice;

        const allVariationsSelected = (adaRasa ? selectedVariation.rasa !== undefined : true) &&
                                       (adaWarna ? selectedVariation.warna !== undefined : true) &&
                                       (adaUkuran ? selectedVariation.ukuran !== undefined : true);

        if (allVariations.length > 0 && allVariationsSelected) {
            const matchingVariation = allVariations.find(v => {
                let match = true;
                if (adaRasa && v.rasa_nama !== selectedVariation.rasa) match = false;
                if (adaWarna && v.warna_nama !== selectedVariation.warna) match = false;
                if (adaUkuran && v.ukuran_nama !== selectedVariation.ukuran) match = false;
                return match;
            });

            if (matchingVariation) {
                // Gunakan harga_lama dari variasi jika ada, jika tidak, gunakan harga_lama produk
                actualOriginalPrice = matchingVariation.original_price ? matchingVariation.original_price : productOriginalPrice;
                actualPrice = matchingVariation.variasi_harga;
            }
        }

        if (actualOriginalPrice && actualOriginalPrice > actualPrice) {
            originalPriceDisplay.classList.remove('d-none');
            originalPriceDisplay.textContent = 'Rp ' + formatRupiah(actualOriginalPrice);
        } else {
            originalPriceDisplay.classList.add('d-none');
        }
    }

    updateButtonStates();
    updateQuantityInputMax();
    updateWhatsAppMessage(); // Call to update WhatsApp message when price/stock changes
}

function updateButtonStates() {
    let allVariationsSelected = true;
    if (allVariations.length > 0) { // Hanya periksa jika ada variasi yang didefinisikan
        if (adaRasa && selectedVariation.rasa === undefined) allVariationsSelected = false;
        if (adaWarna && selectedVariation.warna === undefined) allVariationsSelected = false;
        if (adaUkuran && selectedVariation.ukuran === undefined) allVariationsSelected = false;
    }

    const isStockAvailable = currentProductStock > 0;

    if (allVariations.length > 0 && !allVariationsSelected) {
        addToCartButton.disabled = true;
        buyNowButton.disabled = true;
        disableQuantityControls();
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Silakan pilih variasi untuk melihat stok dan harga.';
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none';
        }
    } else if (!isStockAvailable) {
        addToCartButton.disabled = true;
        buyNowButton.disabled = true;
        disableQuantityControls();
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Stok habis untuk variasi ini.';
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none';
        }
    } else {
        addToCartButton.disabled = false;
        buyNowButton.disabled = false;
        enableQuantityControls(currentProductStock);
        stockErrorMessage.classList.add('d-none');
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none';
        }
        // Pastikan kuantitas tidak melebihi stok yang tersedia saat ini
        if (parseInt(quantityInput.value) > currentProductStock) {
            quantityInput.value = currentProductStock > 0 ? currentProductStock : 0;
        } else if (parseInt(quantityInput.value) === 0 && currentProductStock > 0) {
            // Jika kuantitas 0 tapi stok ada, reset ke 1
            quantityInput.value = 1;
        }
    }
    updateQuantityInputMax(); // Panggil ini di akhir untuk sinkronisasi tombol +/-
}

function formatRupiah(angka) {
    if (isNaN(angka)) return '0';
    let number_string = parseFloat(angka).toFixed(0).toString().replace(/[^,\d]/g, '').toString(), // Ensure it's a fixed decimal number, then string
        split = number_string.split(','),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah;
}

// Fungsi untuk mengambil dan memperbarui jumlah terjual secara real-time
function updateJumlahTerjual(id) {
    // Sesuaikan path ini sesuai lokasi file get_jumlah_terjual.php Anda
    // Jika detail_produk.php dan get_jumlah_terjual.php ada di direktori yang sama: 'get_jumlah_terjual.php'
    // Jika detail_produk.php ada di 'produk/' dan get_jumlah_terjual.php ada di 'koneksi/': '../koneksi/get_jumlah_terjual.php'
    const fetchUrl = 'get_jumlah_terjual.php?id=' + id;

    fetch(fetchUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (liveJumlahTerjualElement) {
                    liveJumlahTerjualElement.textContent = data.jumlah_terjual;
                }
            } else {
                console.error('Failed to fetch jumlah_terjual:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching jumlah_terjual:', error);
        });
}


function scrollToUlasan() {
    const ulasanSection = document.getElementById('ulasan-pembeli');
    if (ulasanSection) {
        ulasanSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.warn('Elemen ulasan tidak ditemukan. Pastikan ulasan.php memiliki elemen dengan ID "ulasan-pembeli".');
    }
}

// Fungsi untuk menggulir thumbnail
function scrollThumbnails(offset) {
    if (thumbnailScrollContainer) {
        thumbnailScrollContainer.scrollLeft += offset;
        // Panggil checkScrollButtons setelah scroll selesai untuk memperbarui status tombol panah
        checkScrollButtons();
    }
}

function checkScrollButtons() {
    if (prevThumbnailButton && nextThumbnailButton && thumbnailScrollContainer) {
        // Logika untuk menampilkan/menyembunyikan tombol scroll
        prevThumbnailButton.style.display = thumbnailScrollContainer.scrollLeft > 0 ? 'block' : 'none';
        // Memberikan toleransi 5px untuk menghindari masalah floating point
        nextThumbnailButton.style.display = thumbnailScrollContainer.scrollLeft < (thumbnailScrollContainer.scrollWidth - thumbnailScrollContainer.clientWidth - 5) ? 'block' : 'none';
    }
}

// --- WhatsApp Message Logic ---
function updateWhatsAppMessage() {
    const productName = whatsappProductNameInput.value;
    const productPriceFormatted = whatsappProductPriceInput.value;
    const sellerPhone = whatsappSellerPhoneInput.value;

    let message = `Halo, apakah produk ini masih tersedia?\n`;
    message += `Nama Produk: ${productName}\n`;

    // Conditionally add variation details
    if (adaRasa && selectedVariation.rasa) {
        message += `Variasi: Rasa: ${selectedVariation.rasa}\n`;
        whatsappSelectedRasaInput.value = selectedVariation.rasa; // Update hidden field
    } else {
        whatsappSelectedRasaInput.value = ''; // Clear hidden field if not selected
    }
    if (adaWarna && selectedVariation.warna) {
        message += `Variasi: Warna: ${selectedVariation.warna}\n`;
        whatsappSelectedWarnaInput.value = selectedVariation.warna; // Update hidden field
    } else {
        whatsappSelectedWarnaInput.value = ''; // Clear hidden field if not selected
    }
    if (adaUkuran && selectedVariation.ukuran) {
        message += `Variasi: Ukuran: ${selectedVariation.ukuran}\n`;
        whatsappSelectedUkuranInput.value = selectedVariation.ukuran; // Update hidden field
    } else {
        whatsappSelectedUkuranInput.value = ''; // Clear hidden field if not selected
    }

    message += `Harga: Rp ${productPriceFormatted}\n`;
    message += `Terima kasih.`;

    const encodedMessage = encodeURIComponent(message);
    if (chatSellerWhatsappBtn) {
        chatSellerWhatsappBtn.href = `https://wa.me/${sellerPhone}?text=${encodedMessage}`;
    }
}

// --- Fungsi untuk memperbarui badge keranjang (diadaptasi dari jQuery ke Fetch API) ---
function muatJumlahKeranjangNav() {
    fetch('keranjang/get_cart_count.php') // Path disesuaikan
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                if (jumlahKeranjangBadge) {
                    jumlahKeranjangBadge.textContent = data.count;
                }
            } else {
                console.error('Gagal memuat jumlah keranjang di navbar:', data.message);
                if (jumlahKeranjangBadge) {
                    jumlahKeranjangBadge.textContent = '0';
                }
            }
        })
        .catch(error => {
            console.error('Fetch Error (get_cart_count):', error);
            if (jumlahKeranjangBadge) {
                jumlahKeranjangBadge.textContent = '0';
            }
        });
}

// --- Fungsi untuk memuat detail item di dropdown keranjang (Sudah Fetch API) ---
function muatIsiKeranjangDropdown() {
    if (!daftarProdukKeranjang || !pesanKeranjangKosong || !jumlahProdukLainnyaSpan) {
        console.warn('Elemen keranjang dropdown tidak ditemukan. Pastikan ID HTML sudah benar.');
        return;
    }

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
                pesanKeranjangKosong.style.display = 'none';

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
                pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                pesanKeranjangKosong.style.display = 'block';
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


  // ini program yg sebenarnya yg jangan di copy dibawah ---
// --- Event Listeners ---

// Skrip untuk AJAX Add to Cart (menggunakan Fetch API)
addToCartButton.addEventListener('click', function() {
    const productId = <?php echo $produk_id ?? 0; ?>;
    const quantity = parseInt(quantityInput.value);
    let variationId = null;

    if (allVariations.length > 0) {
        const matchingVariation = allVariations.find(v => {
            let match = true;
            if (adaRasa && v.rasa_nama !== selectedVariation.rasa) match = false;
            if (adaWarna && v.warna_nama !== selectedVariation.warna) match = false;
            if (adaUkuran && v.ukuran_nama !== selectedVariation.ukuran) match = false;
            return match;
        });

        if (matchingVariation) {
            variationId = matchingVariation.id;
        } else {
            alert('Harap pilih semua variasi yang tersedia.');
            return;
        }
    }

    // Validasi kuantitas sebelum mengirim
    if (quantity <= 0 || isNaN(quantity) || quantity > currentProductStock) {
        alert(`Kuantitas tidak valid. Harap masukkan antara 1 dan ${formatRupiah(currentProductStock)}.`);
        return;
    }

    // Path disesuaikan: 'keranjang/proses_keranjang.php' - ini harus sesuai dengan lokasi Anda
    fetch('keranjang/proses_keranjang.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `produk_id=${productId}&quantity=${quantity}${variationId ? `&variasi_id=${variationId}` : ''}`,
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json(); // Mengubah respons menjadi objek JSON
        })
        .then(data => {
            if (data.status === 'success') {
                // Tampilkan notifikasi sukses yang sudah kita siapkan HTML-nya
                const notification = document.getElementById('cart-success-notification');
                notification.style.display = 'block'; // Tampilkan notifikasi

                // Sembunyikan notifikasi setelah beberapa detik
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000); // Tampil selama 2 detik (2000 milidetik)

                // Panggil fungsi untuk memperbarui jumlah item di keranjang pada navbar
                muatJumlahKeranjangNav();
                // Panggil fungsi untuk memperbarui isi dropdown keranjang
                muatIsiKeranjangDropdown();

            } else {
                // Tampilkan pesan error dari server
                alert(data.message || 'Gagal menambahkan produk ke keranjang.');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menambahkan produk ke keranjang. Silakan coba lagi.');
        });
});

buyNowButton.addEventListener('click', function() {
    const productId = <?php echo $produk_id ?? 0; ?>;
    const quantity = parseInt(quantityInput.value);
    let variationId = null;

    if (allVariations.length > 0) {
        const matchingVariation = allVariations.find(v => {
            let match = true;
            // Pastikan untuk memeriksa semua jenis variasi yang ada di produk ini
            if (adaRasa && v.rasa_nama !== selectedVariation.rasa) match = false;
            if (adaWarna && v.warna_nama !== selectedVariation.warna) match = false;
            if (adaUkuran && v.ukuran_nama !== selectedVariation.ukuran) match = false;
            return match;
        });

        if (!matchingVariation) {
            alert('Harap pilih semua variasi yang tersedia sebelum membeli.');
            return;
        }
        variationId = matchingVariation.id;
    }

    // Validasi kuantitas sebelum melanjutkan
    if (quantity <= 0 || isNaN(quantity) || quantity > currentProductStock) {
        alert(`Kuantitas tidak valid. Harap masukkan antara 1 dan ${formatRupiah(currentProductStock)}.`);
        return;
    }

    const params = new URLSearchParams();
    params.append('produk_id', productId);
    params.append('quantity', quantity);
    if (variationId) {
        params.append('variasi_id', variationId);
    }
    // LANGSUNG ARAHKAN KE CHECKOUT.PHP
    // Pastikan path ini sesuai dengan lokasi file checkout.php Anda!
    // Jika checkout.php ada di dalam folder 'keranjang' (e.g., /keranjang/checkout.php), gunakan 'keranjang/checkout.php?'
    // Jika checkout.php ada di root directory (e.g., /checkout.php), gunakan 'checkout.php?'
    window.location.href = 'keranjang/checkout.php?' + params.toString();
});
// >>>>>> AKHIR DARI BAGIAN YANG DIPERBARUI <<<<<<

variationButtons.forEach(button => {
    button.addEventListener('click', function() {
        const jenisVariasi = this.dataset.jenis;
        const nilaiVariasi = this.dataset[jenisVariasi]; // Akan menjadi 'rasa', 'warna', atau 'ukuran'
        const gambarVariasi = this.dataset.gambar; // Gambar dari variasi yang diklik

        console.log("--- Variation Button Clicked ---");
        console.log("Jenis Variasi:", jenisVariasi);
        console.log("Nilai Variasi:", nilaiVariasi);
        console.log("selectedVariation before update:", { ...selectedVariation }); // Salinan untuk log

        // Cek apakah tombol yang diklik sudah aktif
        const isActive = this.classList.contains('active');

        // Hapus kelas 'active' dari semua tombol variasi dengan jenis yang sama
        document.querySelectorAll(`.variation-button[data-jenis="${jenisVariasi}"]`).forEach(btn => {
            btn.classList.remove('active');
        });

        if (isActive) {
            // Jika tombol sudah aktif, batalkan pilihan
            delete selectedVariation[jenisVariasi];
            // Clear corresponding hidden input
            if (jenisVariasi === 'rasa') whatsappSelectedRasaInput.value = '';
            else if (jenisVariasi === 'warna') whatsappSelectedWarnaInput.value = '';
            else if (jenisVariasi === 'ukuran') whatsappSelectedUkuranInput.value = '';
        } else {
            // Jika tombol belum aktif, aktifkan dan simpan pilihan
            this.classList.add('active');
            selectedVariation[jenisVariasi] = nilaiVariasi;
            // Update corresponding hidden input
            if (jenisVariasi === 'rasa') whatsappSelectedRasaInput.value = nilaiVariasi;
            else if (jenisVariasi === 'warna') whatsappSelectedWarnaInput.value = nilaiVariasi;
            else if (jenisVariasi === 'ukuran') whatsappSelectedUkuranInput.value = nilaiVariasi;
        }

        console.log("selectedVariation after update:", { ...selectedVariation }); // Sangat penting!

        // --- Logika penentuan gambar yang dimodifikasi ---
        let imageToDisplay = '<?php echo htmlspecialchars($gambar_utama); ?>'; // Default ke gambar utama
        let hasImageVariationSelected = false; // Flag untuk melacak apakah ada variasi gambar yang aktif

        // Iterasi melalui semua variasi yang *mungkin* memiliki gambar (rasa, warna)
        // dan cek apakah variasi tersebut sedang aktif
        if (adaRasa && selectedVariation.rasa) {
            const rasaButton = document.querySelector(`.variation-button[data-jenis="rasa"][data-rasa="${selectedVariation.rasa}"]`);
            if (rasaButton && rasaButton.dataset.gambar) {
                imageToDisplay = rasaButton.dataset.gambar;
                hasImageVariationSelected = true;
            }
        }
        if (adaWarna && selectedVariation.warna) {
            const warnaButton = document.querySelector(`.variation-button[data-jenis="warna"][data-warna="${selectedVariation.warna}"]`);
            if (warnaButton && warnaButton.dataset.gambar) {
                imageToDisplay = warnaButton.dataset.gambar;
                hasImageVariationSelected = true;
            }
        }
        // Tambahkan logika serupa jika Anda memiliki jenis variasi lain yang memiliki gambar

        // Jika tidak ada variasi gambar yang aktif, pastikan kembali ke gambar utama
        if (!hasImageVariationSelected) {
            imageToDisplay = '<?php echo htmlspecialchars($gambar_utama); ?>';
        }

        // Ganti gambar utama
        changeMainMedia(imageToDisplay, 'gambar');

        // Update thumbnail aktif sesuai dengan gambar yang ditampilkan
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.classList.remove('active-thumbnail');
        });
        const activeThumbnail = document.querySelector(`.thumbnail[data-media-src="${imageToDisplay}"]`);
        if (activeThumbnail) {
            activeThumbnail.classList.add('active-thumbnail');
        }
        // --- Akhir logika penentuan gambar yang dimodifikasi ---

        // Cari variasi yang cocok berdasarkan pilihan saat ini
        let currentPriceToDisplay = productPrice;
        let currentStockToDisplay = productStock;
        let foundMatchingVariation = null;

        // Pastikan untuk selalu mencari variasi yang cocok jika ada variasi yang didefinisikan
        if (allVariations.length > 0) {
            foundMatchingVariation = allVariations.find(v => {
                let match = true;
                console.log("Checking variation from allVariations:", v); // Log setiap variasi yang sedang dicek
                // Periksa setiap jenis variasi yang mungkin ada
                if (adaRasa) {
                    // Jika ada variasi rasa dan rasa belum dipilih, atau tidak cocok
                    if (selectedVariation.rasa === undefined || v.rasa_nama !== selectedVariation.rasa) {
                        match = false;
                        console.log("Mismatch on rasa:", v.rasa_nama, selectedVariation.rasa);
                    }
                }
                if (adaWarna) {
                    // Jika ada variasi warna dan warna belum dipilih, atau tidak cocok
                    if (selectedVariation.warna === undefined || v.warna_nama !== selectedVariation.warna) {
                        match = false;
                        console.log("Mismatch on warna:", v.warna_nama, selectedVariation.warna);
                    }
                }
                if (adaUkuran) {
                    // Jika ada variasi ukuran dan ukuran belum dipilih, atau tidak cocok
                    if (selectedVariation.ukuran === undefined || v.ukuran_nama !== selectedVariation.ukuran) {
                        match = false;
                        console.log("Mismatch on ukuran:", v.ukuran_nama, selectedVariation.ukuran);
                    }
                }
                console.log("Match status for current variation:", match);
                return match;
            });
        }

        console.log("foundMatchingVariation result:", foundMatchingVariation); // Ini akan menunjukkan apakah variasi ditemukan
        if (foundMatchingVariation) {
            console.log("Harga variasi yang ditemukan:", foundMatchingVariation.variasi_harga);
            console.log("Stok variasi yang ditemukan:", foundMatchingVariation.variasi_stok);
        } else {
            console.log("Tidak ada variasi yang cocok ditemukan.");
        }


        // Logika penentuan harga dan stok
        if (allVariations.length === 0) {
            // Jika tidak ada variasi sama sekali, gunakan harga dan stok produk default
            currentPriceToDisplay = productPrice;
            currentStockToDisplay = productStock;
            enableQuantityControls(currentStockToDisplay);
            addToCartButton.disabled = false;
            buyNowButton.disabled = false;
            stockErrorMessage.classList.add('d-none');
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
        } else if (!foundMatchingVariation || // Tidak ada variasi yang cocok ditemukan
                   (adaRasa && selectedVariation.rasa === undefined) || // Ada rasa tapi belum dipilih
                   (adaWarna && selectedVariation.warna === undefined) || // Ada warna tapi belum dipilih
                   (adaUkuran && selectedVariation.ukuran === undefined) ) { // Ada ukuran tapi belum dipilih
            // Jika tidak ada variasi yang cocok ditemukan ATAU tidak semua variasi yang diperlukan dipilih
            currentPriceToDisplay = productPrice; // Kembali ke harga produk default
            currentStockToDisplay = 0; // Stok 0 jika tidak ada variasi dipilih atau tidak cocok
            stockErrorMessage.classList.remove('d-none');
            stockErrorMessage.textContent = 'Silakan pilih semua variasi untuk melihat stok dan harga.'; // Pesan lebih jelas
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
            disableQuantityControls();
            addToCartButton.disabled = true;
            buyNowButton.disabled = true;
        } else {
            // Jika variasi yang cocok ditemukan
            currentPriceToDisplay = foundMatchingVariation.variasi_harga;
            currentStockToDisplay = foundMatchingVariation.variasi_stok;
            stockErrorMessage.classList.add('d-none');
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
        }

        // Panggil updatePriceAndStock dengan nilai yang sudah ditentukan
        updatePriceAndStock(currentPriceToDisplay, currentStockToDisplay);
        // Pastikan juga untuk memanggil updateButtonStates setelah ini
        updateButtonStates();
        updateWhatsAppMessage(); // IMPORTANT: Update WhatsApp message after variation selection
    });
});

qtyPlusButton.addEventListener('click', () => {
    let currentQty = parseInt(quantityInput.value);
    if (isNaN(currentQty)) currentQty = 0; // Handle jika input kosong saat plus diklik

    if (currentQty < currentProductStock) {
        quantityInput.value = currentQty + 1;
    }
    updateQuantityInputMax();
});

qtyMinusButton.addEventListener('click', () => {
    let currentQty = parseInt(quantityInput.value);
    if (isNaN(currentQty)) currentQty = 1; // Handle jika input kosong saat minus diklik

    if (currentQty > 1) {
        quantityInput.value = currentQty - 1;
    }
    updateQuantityInputMax();
});

// Event listener untuk input kuantitas manual
quantityInput.addEventListener('input', () => {
    let val = quantityInput.value.trim(); // Ambil nilai dan hapus spasi di awal/akhir
    let parsedVal = parseInt(val);

    // Jika input benar-benar kosong setelah dihapus (misal: backspace semua angka)
    if (val === '') {
        // Biarkan kosong untuk sementara, user sedang mengetik
        stockErrorMessage.classList.add('d-none'); // Sembunyikan pesan error
        qtyMinusButton.disabled = true; // Nonaktifkan minus saat kosong
        qtyPlusButton.disabled = true; // Nonaktifkan plus saat kosong
        return; // Keluar dari fungsi, jangan lanjutkan validasi angka
    }

    // Jika input bukan angka valid atau kurang dari 1 (setelah parsed)
    if (isNaN(parsedVal) || parsedVal < 1) {
        quantityInput.value = currentProductStock > 0 ? 1 : 0; // Set ke 1 atau 0 jika stok 0
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Kuantitas tidak valid. Minimum adalah 1.';
    } else if (parsedVal > currentProductStock) {
        // Jika input melebihi stok, set ke stok maksimum dan tampilkan pesan
        quantityInput.value = currentProductStock;
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = `Kuantitas tidak boleh melebihi stok ${formatRupiah(currentProductStock)}.`;
    } else {
        // Jika valid, sembunyikan pesan error
        stockErrorMessage.classList.add('d-none');
    }
    updateQuantityInputMax(); // Pastikan tombol plus/minus diperbarui
});

// Ini fungsi yang belum didefinisikan di kode Anda sebelumnya. Tambahkan ini:
function updateQuantityInputMax() {
    quantityInput.max = currentProductStock;
    // Nonaktifkan tombol plus jika kuantitas mencapai stok
    qtyPlusButton.disabled = parseInt(quantityInput.value) >= currentProductStock || currentProductStock === 0 || quantityInput.disabled;
    // Nonaktifkan tombol minus jika kuantitas adalah 1 atau kurang
    qtyMinusButton.disabled = parseInt(quantityInput.value) <= 1 || quantityInput.disabled;
}


// Scroll thumbnail event listeners
if (prevThumbnailButton && nextThumbnailButton && thumbnailScrollContainer) {
    prevThumbnailButton.addEventListener('click', function() {
        thumbnailScrollContainer.scrollLeft -= 150;
        checkScrollButtons(); // Perbarui status tombol setelah scroll
    });

    nextThumbnailButton.addEventListener('click', function() {
        thumbnailScrollContainer.scrollLeft += 150;
        checkScrollButtons(); // Perbarui status tombol setelah scroll
    });

    // Panggil checkScrollButtons saat scroll atau setelah DOM dimuat (dengan sedikit penundaan)
    thumbnailScrollContainer.addEventListener('scroll', checkScrollButtons);
    setTimeout(checkScrollButtons, 100); // Panggil sekali setelah DOM dimuat untuk inisialisasi
}

const thumbnailsContainer = document.querySelector('.thumbnails-container');

thumbnailsContainer.addEventListener('click', function(event) {
    const clickedElement = event.target.closest('.thumbnail, .thumbnail-video-container');

    if (clickedElement) {
        const mediaType = clickedElement.dataset.mediaType;
        const mediaSrc = clickedElement.dataset.mediaSrc;

        // **Tambahkan bagian ini untuk menghentikan video utama jika sedang berjalan**
        const mainVideoElement = document.getElementById('main-video');
        if (mainVideoElement && !mainVideoElement.paused) {
            mainVideoElement.pause();
            mainVideoElement.currentTime = 0; // Opsional: reset ke awal
        }

        thumbnailsContainer.querySelectorAll('.thumbnail, .thumbnail-video-container').forEach(thumb => {
            thumb.classList.remove('active-thumbnail');
        });
        clickedElement.classList.add('active-thumbnail');

        changeMainMedia(mediaSrc, mediaType);
    }
});


// Chatbot elements
const chatbotContainer = document.getElementById('non-ai-chatbot');
const chatbotToggleBtn = document.getElementById('chatbot-toggle-btn');
const chatbotHeader = document.getElementById('chatbot-header');
const closeChatbotBtn = document.getElementById('close-chatbot');
const chatMessages = document.getElementById('chat-messages');
const userInput = document.getElementById('user-input');
const sendButton = document.getElementById('send-button');
const quickQuestionBtns = document.querySelectorAll('.quick-question-btn');


// Predefined answers for the non-AI chatbot (menggunakan regex)
const predefinedAnswers = [
    {
        patterns: [ /^(halo|hallo|halo assalamualaikum|hi|hai|hey|hei|hay|helo|hlo|woy|oy|assalamualaikum|aslm|assalam|slm|p|permisi|punten|tes|cuy|bro|sis|gan|min|mimin|kak|kakak|selamat|wassup|yo|holla|haloo|heloo|hy|uy|yoo|wasap|oyy|helooo|haii|hii|heyyo)$/i],
        responses: [
            "Halo! Ada yang bisa saya bantu, Bestie?",
            "Hai! Senang banget bisa bantu kamu.",
            "Selamat datang! Ada pertanyaan tentang produk atau pesanan kamu?",
            "Hey! Butuh bantuan apa nih, Kak?",
            "Salam! Ada yang bisa di-spill?",
            "Waalaikumsalam! Ada apa nih, Sayang?",
            "Wassup! Ada yang bisa kami bantu hari ini?",
            "Yo! Siap bantu nih, ada apa?",
            "Holla! Senang kamu mampir, ada yang bisa dibantu?",
            "Ada yang bisa kami bantu?" // Tambahan respons singkat dan umum
        ]
    },
    {
        patterns: [ /^(selamat pagi|pagi|slmt pgi|slmt pagi|slamt pgi|slamt pagi|slamt pg|slamat pgi|slamat pg|slamat pagi|slmat pgi|slmat pagi|slmat pg|pgi|morning|good morning|good mrning|pagi kak|pagi min|pagi gan|pagi bos|pagi bro|pagi sis|pgi min|morning all|good morning all|selamat pagi semua|selamat pagi teman|pagi gaes|pagi guys|pagi teman-teman|pagi rek|pg|pagi-pagi|selamat pagi kakak|selamat pagi admin|pagi bang|pagi mbak|pagi non|pagi ndan|pgi jg|pg kak|pg boss|morning gays|pg all|pagi gan sis|pagi om|pagi tante|pagi dok|pagi pak|pagi bu|selamat pagi pak|selamat pagi bu|pagi juga|pagii|paagi|morng|gud morning|pagi dong|pagi bosque|selamat pagi dunia|pagi salam|assalamualaikum pagi|salam pagi|pagi sahabat|pagi kawan|slmt pagi all|pagi gan, sis|pagi gan sist|pagi bunda|pagi papa|pagi ibu|pagi bapak|pagi suhu|pagi suhu-suhu|pagi kakak-kakak|pagi mas|pagi mba|pagi pakde|pagi bude|pagi lik|pagi om tante)$/i],
        responses: [
            "Selamat Pagi! Semoga harimu cerah dan lancar ya. Ada yang bisa kami bantu?"
        ]
    },
    {
        patterns: [ /^(selamat malam|malam|slmt mlm|slmt malam|slamt mlam|slamt malam|slamt mlm|slamat mlm|slamat mlm|slamat malam|slmat mlm|slmat malam|slmat malam|mlam|night|good night|malem|malem kak|malem min|malem gan|malem bos|malem bro|malem sis|mlm min|night all|good night all|selamat malam semua|selamat malam teman|mlm gaes|mlm guys|mlm teman-teman|mlm rek|mlm|malam-malam|selamat malam kakak|selamat malam admin|malam bang|malam mbak|malam non|malam ndan|mlm jg|mlm kak|mlm boss|night gays|mlm all|malam gan sis|malam om|malam tante|malam dok|malam pak|malam bu|selamat malam pak|selamat malam bu|malam juga|malamm|maalam|nite|gd night|malam dong|malam bosque|selamat malam dunia|malam salam|assalamualaikum malam|salam malam|malam sahabat|malam kawan|slmt mlm all|malam gan, sis|malam gan sist|malam bunda|malam papa|malam ibu|malam bapak|malam suhu|malam suhu-suhu|malam kakak-kakak|malam mas|malam mba|malam pakde|malam bude|malam lik|malam om tante|slm mlm|mlm syg|mlm bep|mlm cyg|nite nite|good nite|gud nite|malemm|mleem|mlm all|mlm my dear|mlm dear|mlm cintaku|mlm sayangku|mlm ayang|mlm ayangg|mlm cinta|met malam|met malem|selamat malam gaes|halo malam|hai malam|hey malam|malam nih|malam yak)$/i],
        responses: [
            "Selamat malam! Semoga istirahatmu tenang dan nyaman ya. Ada yang ingin ditanyakan?"
        ]        
    },
    {
        patterns: [ /^(selamat sore|sore|slmt sre|slmt sore|slamt sre|slamt sore|slamt sre|slamat sore|slamat sre|slamat sore|slmat sre|slmat sore|slmat sore|sre|evening|good evening|sore kak|sore min|sore gan|sore bos|sore bro|sore sis|sre min|evening all|good evening all|selamat sore semua|selamat sore teman|sore gaes|sore guys|sore teman-teman|sore rek|sre|sore-sore|selamat sore kakak|selamat sore admin|sore bang|sore mbak|sore non|sore ndan|sre jg|sre kak|sre boss|evening gays|sre all|sore gan sis|sore om|sore tante|sore dok|sore pak|sore bu|selamat sore pak|selamat sore bu|sore juga|soree|soore|evng|gud evening|sore dong|sore bosque|selamat sore dunia|sore salam|assalamualaikum sore|salam sore|sore sahabat|sore kawan|slmt sre all|sore gan, sis|sore gan sist|sore bunda|sore papa|sore ibu|sore bapak|sore suhu|sore suhu-suhu|sore kakak-kakak|sore mas|sore mba|sore pakde|sore bude|sore lik|sore om tante|slm sre|sre syg|sre bep|sre cyg|gud evning|sori|sorre|sore all|sore my dear|sore dear|sore cintaku|sore sayangku|sore ayang|sore ayangg|sore cinta|met sore|selamat sore gaes|halo sore|hai sore|hey sore|sore nih|sore yak)$/i],
        responses: [
            "Selamat sore! Semoga soremu cerah dan produktif. Ada masalah di website kami?"
        ]
    },
    {
        patterns: [ /^(selamat siang|siang|slmt siang|slamt siang|slamt siang|slamat siang|slmat siang|afternoon|good afternoon|siang kak|siang min|siang gan|siang bos|siang bro|siang sis|siang min|afternoon all|good afternoon all|selamat siang semua|selamat siang teman|siang gaes|siang guys|siang teman-teman|siang rek|siang|siang-siang|selamat siang kakak|selamat siang admin|siang bang|siang mbak|siang non|siang ndan|siang jg|siang kak|siang boss|afternoon gays|siang all|siang gan sis|siang om|siang tante|siang dok|siang pak|siang bu|selamat siang pak|selamat siang bu|siang juga|siangg|siaang|aftrnon|gud afternoon|siang dong|siang bosque|selamat siang dunia|siang salam|assalamualaikum siang|salam siang|siang sahabat|siang kawan|slmt siang all|siang gan, sis|siang gan sist|siang bunda|siang papa|siang ibu|siang bapak|siang suhu|siang suhu-suhu|siang kakak-kakak|siang mas|siang mba|siang pakde|siang bude|siang lik|siang om tante|siang syg|siang bep|siang cyg|met siang|siangg|sianggg|ciao siang|hey siang|hais siang|siang yak)$/i],
        responses: [
            "Selamat Siang! Semoga aktivitas siangmu lancar ya. Ada yang bisa kami bantu?"
        ]        
    },
    {
        patterns: [ /^(assalamualaikum|assalamualaikum wr wb|assalamualaikum wr. wb.|assalamualaikum wr. wb|aslmkm|asalamkum|assalmikum|Assalamu'alaikum|Assalamualaikum Warahmatullahi Wabarakatuh|Assalamu'alaikum Warahmatullahi Wabarakatuh|asalamualaikum|assalamualaikum wr.wb|assalamualaikum wr wb|asallamualaikum|assalamualaikum warohmatullahi wabarokatuh|assalamualaikum wrwb|assalamualaikum wr wb|assalamualaikum wr. wb|assalamu alaikum|assalamu'alaikum wr wb|assalamualaikum kak|assalamualaikum min|assalamualaikum dok|assalamualaikum pak|assalamualaikum bu|assalamualaikum gan|assalamualaikum bro|assalamualaikum sis|aslm|assalam|slm|askm|assalamu'alaikum wr. wb|assalamualaikum warahmatullahi wabarakatuh|salam assalamualaikum|salam alaikum|salam|asslm|asslmkm|ass|askum|slmat assalamualaikum|slmt assalamualaikum|mlkm salam|alaikum salam|walaikumsalam|waalaikumsalam wr wb|waalaikumsalam wr. wb|waalaikumsalam wr.wb|waalaikumsalam wrwb|wslm|wslmkm|wlkmslm|w'salam|waalaikumussalam|waalaikumsalam warahmatullahi wabarakatuh)$/i],
        responses: [
            "Waalaikumsalam, ada yang bisa saya bantu?."
        ]        
    },
    {
        patterns: [ /^(shalom|shlm|shalom aleichem|shalom alekhem|shalom kak|shalom min|shalom gan|shalom bro|shalom sis|salam shalom|hai shalom|halo shalom|shlm ya|shlm kak)$/i],
        responses: [
            "Shalom, semoga damai dan kasih menyertai Anda. Ada yang bisa saya bantu?"
        ]        
    },
    {
        patterns: [ /^(om swastiastu|om swstyastu|om swastyastu|swastiastu|swastyastu|om swastiastu kak|om swastiastu min|om swastiastu gan|om swastiastu bro|om swastiastu sis|salam om swastiastu|hai om swastiastu|halo om swastiastu|om sws|swastiastu kak|swastiastu min|swastiastu gan)$/i],
        responses: [
            "Om swastiastu, semoga Anda dalam keadaan baik. Ada yang bisa saya bantu?"
        ]        
    },
    {
        patterns: [ /^(namo buddhaya|namo budhaya|namobuddhaya|nmbdhy|namo buddhaya kak|namo buddhaya min|namo buddhaya gan|namo buddhaya bro|namo buddhaya sis|salam namo buddhaya|hai namo buddhaya|halo namo buddhaya|nmbdh|namo buday|nmdby|nbd|nb)$/i],
        responses: [
            "Namo Buddhaya, salam hormat dan damai untuk Anda. Ada yang bisa saya bantu?"
        ]        
    },
    {
        patterns: [ /^(salam kebajikan|salam kebajikan kak|salam kebajikan min|salam kebajikan gan|salam kebajikan bro|salam kebajikan sis|sk|skbjkn|salam kebajikan om|salam kebajikan tante|salam kebajikan bapak|salam kebajikan ibu|hai salam kebajikan|halo salam kebajikan|skbjkn dong|sk bjkn|salam kebaikan|salkeb)$/i],
        responses: [
            "Salam Kebajikan Kembali, semoga Anda diberi kebaikan hari ini. Ada yang bisa saya bantu?"
        ]        
    },
    {
        patterns: [/^(terima kasih|makasih|tks|oke makasih|terimakasih|thank you|thankyou so much| thank you so much| thanks|thank's|makasi|mksh|thank u|tq|matur nuwun|suwun|hatur nuhun|makasih banyak|makasih ya|thx|thks|thank's bgt|thanks banget|mksh bgt|makasih banget|terima kasih banyak|arigatou|nuhun|tengkyu|thx u|tx|thankies|makasih kak|makasih min|makasih gan|makasih bro|makasih sis|mksh ya kak|tq kak|tq min|mksh gan|thx bro|makasi banyak|terimakasih ya|terima kasih bgt|terimakasih bgt|thank's min|thanks boss|mksh bos|makasih bossque|mksh all|thx all|ty|tyvm|kamsia|trimakasih|terimakasih banyak-banyak|thx u all|thank's you|trims|trim|makasi ya|makasih bos|makasih bund|makasih pak|makasih bu|mksh kak|mksh min|mksh om|mksh tante|tq bang|tq mas|tq mba|terima kasih kak|terima kasih min|terima kasih gan|terima kasih bro|terima kasih sis|thq|tyvm|mksh bosku|makasih banget banget|terima kasih banget banget|makasih yah|terima kasih yah|arigato|thankies banget|tx kak|tq all|tq bosque|thank you very much|many thanks|cheers|terimakasih yaa|makasih yaa|mkshh|thanks gaes|tengkyuu|thanku|thankyouu|terimakasihh|makasii|mksh bgt ya|thx bgt ya|thanks for help|mksh buat bantuannya|terima kasih buat bantuannya|tq for help|suksma|maturnuwun|swn|thxx|makasi banget ya|terima kasih bgt ya|terima kasih bapak|terima kasih ibu|makasih abang|makasih mbak|mksh gan sis|mksh pakde|mksh bude|makasih pak haji|makasih bu haji|thank you so much kak|thank you so much min|thanks a lot|muchas gracias|domo arigato|gracias|thankx|terimakasi|makasiiih|mkshhh|thnks|thankss|thank u all|thank you all|thank you kak|thank you min|terima kasih pak bos|terima kasih bu bos|tq bang jago|makasih banyak ya|thanks so much)$/i],
        responses: [
            "Sama-sama! Senang banget bisa bantu kamu.",
            "Oke, sama-sama! Ada lagi yang bisa dibantu?",
            "Baik, terima kasih kembali ya!",
            "Siap, senang bisa bantu!",
            "Terima kasih kembali! Jangan sungkan kalau ada pertanyaan lagi.",
            "Sama-sama, Bestie! Ada lagi yang perlu di-spill?"
        ]        
    },
    {
        patterns: [/^(cara memesan|bagaimana cara beli|mau pesan|cara order|gimana cara beli|gimana kalo mau pesen|beli gimana|cara order gimana|pengen beli|mau checkout|pesen dong|gas beli|gercep order|minat beli|cara pesen|gimana order|beli produk gimana|order produk|cara pembelian|gimana cara ordernya|mau beli dong|pesen sekarang|checkout dong|beliin dong|mau beli ini|bagaimana pembelian|langsung beli|cara transaksi|mau transaksi|belinya gimana|pesan ini|gimana sih cara belinya|pengen order|mau beli produk ini|cara dapetinnya|gimana cara dapetnya|langkah-langkah order|proses pembelian|alur beli|alur order|mau beli kak|gimana cara pesannya|tolong bantu beli|bisa order sekarang|cara order gampang|gampang ordernya gimana|langsung order|langsung pesen|mau beli sekarang|pesen barang ini|beli barang ini|cara beli barang|cara pesen barang|order ini|mau beli itu|cara beli itu|cara beli dong|gimana cara order|gmna cara beli|gimana mau beli|cara pemesanan|mau beli nih|gimana nih beli|mau pesen nih|beli dong|order dong|gimana ordernya|cara ordernya|langkah beli|step beli|step order|gimana caranya beli|pengen beli produk|mau beli apa aja|bisa order apa aja|cara bayar gimana|pembayaran gimana|gimana cara bayarnya|checkout gimana|order via apa|beli via apa|minat order|niat beli|mau ambil barang|ambil barangnya gimana|langsung transaksi|gimana kalo mau beli|pengen order ini|bantu beli|bantu order|cara order product|cara beli product|gimana beli product|mau beli item ini|cara beli item|cara pesan item|beli via online|order via online|gimana beli online|cara beli online|mau beli online|beli web|beli website|order web|order website|gimana web|gimana website|mau belanja|cara belanja|belanja online|mau belanja online|gimana cara belanja|cara belanja gimana|beli sekarang|order sekarang|pesan sekarang|langsung checkout|mau beli sekarang juga|beli sekarang juga|beli langsung|order langsung|pesan langsung|cara dapetin barang|gimana dapetin barang|mau dapetin barang|pengen dapetin barang|beliin saya|orderin saya|tolong orderkan|tolong belikan|gimana cara pembayarannya|cara pembayaran|metode pembayaran|pembayaran metode|gimana bayar|bayar gimana|cara beli di sini|order di sini|pesan di sini|gimana beli di sini|beli lewat mana|order lewat mana|pesan lewat mana|gmn|gmna|gmnn|gimna|gimnaa|gimanaa|gimana nih|gimana ya|gimana caranya)$/i],
        responses: [
            "Untuk memesan produk, kamu tinggal tambahin produknya ke keranjang belanja, terus lanjutin ke halaman checkout buat selesain pembayaran. Gampang kok, tinggal klik-klik aja!",
            "Pesen barang? Bisa banget! Mau masuk keranjang dulu, atau langsung lanjut aja ke 'Beli Sekarang' juga bisa kok biar sat-set!"
        ]        
    },
    {
        patterns: [/^(lacak pesanan|status pesanan|pesanan saya|sudah sampai mana|paketku mana|pesenan udah nyampe mana|cek paket|tracking order|orderan saya|barang nyampe mana|udah dikirim belum|cek status|status order|lacak paket|paket saya|pesanan udah sampai|dimana pesanan|tracking paket|barangku di mana|cek kiriman|kiriman saya|paket belum sampai|orderan belum sampai|kapan sampai|estimasi sampai|cek nomor resi|resi saya|nomor resi|track my order|where is my order|order status|parcel status|kapan barang sampai|cek pesanan|cek orderan|paket udh dmn|brg udh dikirim|barang udh nyampe|mana paketku|mana orderanku|lacak pengiriman|status pengiriman|pengiriman saya|cek barang|brg udah dkirim blm|paketku|orderanku|udah sampe|kapan dikirim|estimasi kirim|track paket|pesanan status|posisi paket|posisi barang|kirimannya dmn|barang dmn|paketku udh dmn|orderku udh dmn|pesenan ku|paket nyangkut|paket lama|kok lama|lama bgt|delay paket|telat sampe|kapan paket sampe|nomor resi berapa|resi mana|lacak pake resi|cek pake resi|paket belum gerak|barang belum gerak|progress pengiriman|pengiriman progres|info pengiriman|update pengiriman|track my parcel|where is my parcel|status my order|my parcel status|brp lama|brp hari|berapa lama|berapa hari|udah berapa lama|kok blm|kok blm nyampe|blm sampe|uda blm|udah blm|paketku udah sampe mana|status orderan|cek order|lacak order|pesananku|paket ku|barangku|udah dikirim kah|kapan nyampe|kok belum dikirim|ada di mana paketnya|paketku nyasar|paket macet|resinya mana|trackid|kiriman sampai kapan|kapan barang nyampe|pengiriman kapan|sampai kapan kirimannya|update paket|cek status pengiriman|status pengiriman barang|paket saya nyampe kapan|orderan saya nyampe kapan|dimana barang saya|mana barangku|barang saya dmn|status kiriman|brg dmn skrg|uda dkirim blm|udah dikirim belum ya|pesanan kapan sampai|paket nyampe kpn|brg kpn smpe|status barang|cek brg|lacak brg|mana brg|nmr resi|no resi|nomor lacak|no lacak|nomor tracking|no tracking|nmr tracking|no id|id resi|no paket|nmr paket|brp lma|brpa lama|brp hri|brpa hri|berapa lma|berapa hri|lama brp|lama brp hri|lama brp lma|uda nyampe|udh nyampe|kpn smpe|paket dmn|brg dmn|lacak brgku|lacak paketku|status brg|status pket|udah dikirim|udah dikirim belum|udah nyampe|kapan kirim|kapan dikirim nih|kapan dikirim ya|estimasi kiriman|estimasi nyampe|paket on the way|otw paket|kiriman otw|barang otw|paket nyangkut dimana|lama banget nyampenya|cek pengiriman barang|tracking barang|tracking paket saya|resi tracking|no trackingnya|mana no resi|update status|cek update|progress paket|perkembangan paket|info paket|brg uda|pket uda|kirim dah|kirim blm|paketku brp lama|brp lma nyampe|kpn nyampe nih|udah jalan blm)$/i],
        responses: [
            "Kamu bisa lacak pesananmu di bagian 'Pesanan Saya' setelah login ke akunmu. Coba cek di sana ya, biar tahu udah sampai mana!",
            "Mau cek pesananmu? Gampang, tinggal login aja ke akunmu, terus lihat di bagian 'Pesanan Saya'."
        ]        
    },
    {
        patterns: [/bisa cod|terima cod|cash on delivery|bayar di tempat|bayar langsung|cod gak|bisa bayar di rumah|bayar cash|bayar pas sampe|apakah bisa cod|apakah cod|cod ya|cod aja|boleh cod|bisakah cod|bayar ditempat|bayar di rumah aja|bayar pas barang datang|bayar pas nyampe|pembayaran cod|sistem cod|codan|bisakah bayar di tempat|bayar langsung di rumah|bisa bayar saat diterima|terima pembayaran di tempat|cod in|cod dong|bisa ga cod|apakah melayani cod|pakai cod|cara cod|gimana cod|cod apa|cod gak ya|bayar di lokasi|bayar pas barang dateng|bayar di penerima|metode cod|cod bs|cod bisa ga|cod bisa gak|cod ga|cod ngak|cod apa nggak|bayar di tpt|byr di tmpt|byr lgsg|byr cash|byr pas nyampe|bs cod|bs bayar di tempat|trima cod|trima byr di tempat|cash on delivery bisa|cod aja deh|cod ajah|cod aja ya|cod aja kak|cod aja min|bisa cod kah|cod an|cod ga sih|cod ga ya|cod gitu|cod aja gimana|byr di rmh|bayar d rmh|byr dtempat|bayar dtempat|bayar lgsg|bayar d lokasi|bayar d penerima|bayar di penerima|byr smpe|bayar sampai|bayar sampe rumah|bayar pas brg dtg|byr pas paket dtg|byr waktu nyampe|bayar pas terima|bayar stlh trima|byr setelah terima|bayen|byar|bayarku|byar d tmpt|bayar d tmpt|cod kan|cod kan ya|cod kan kak|cod kan min|cod kan bos|cod kan sist|cod in dong|cod in kak|cod in min|cod in lah|cod in ya/i],
        responses: [
            "Saat ini, kami sudah menyediakan layanan 'Cash On Delivery (COD)' di beberapa area. Coba cek ketersediaan COD pas proses checkout ya, biar makin gampang!",
            "'COD' bisa kok di beberapa daerah! Nanti pas checkout, coba dicek aja ya, biar makin gampang transaksinya."
        ]
    },
    {
        patterns: [/hubungi penjual|kontak penjual|chat penjual|telpon penjual|nomor penjual|ngobrol sama penjual|adminnya siapa|kontak admin|telp admin|bicara sama penjual|chat sama penjual|telepon penjual|nomor telepon penjual|nanya penjual|ngobrol penjual|admin penjual|cs penjual|customer service penjual|kontak seller|chat seller|telpon seller|nomor seller|seller nya siapa|kontak owner|chat owner|owner nya siapa|hubungi cs|chat cs|nomor cs|adminya|adminnya|admin dong|chat dong|kontak dong|telpon dong|info kontak penjual|cara hubungi penjual|gimana hubungi penjual|pengen ngobrol sama penjual|langsung chat penjual|mau nanya penjual|ada nomor penjual|nomer penjual|penjualnya siapa|adminnya mana|cs nya mana|chat dengan penjual|bicara dengan penjual|tanya penjual|tanya admin|kontak bantuan|customer support|support center|telepon support|nomor support|tanya tanya penjual|tanya tanya admin|tanya tanya cs|cara chat penjual|gimana chat penjual|boleh chat penjual|bisa telpon penjual|nomor yang bisa dihubungi|hubungi langsung|kontak langsung|chat langsung|telpon langsung|penjual bisa dihubungi|admin bisa dihubungi|cs bisa dihubungi|mau tanya penjual|mau tanya admin|mau tanya cs|ada kontak|info kontak|kontak yang bisa dihubungi|nomor yang bisa dihubungi|dimana hubungi penjual|gimana cara kontak|cara kontak penjual|hubungi kami|kontak kami|chat kami|telpon kami|nomor kami|kami bisa dihubungi|bagaimana cara menghubungi|mau bicara sama|bisakah menghubungi|kontak person|contact person|CP|CP nya|nomor CP|adminnya ready|adminnya online|ada admin|bisa nanya|nanya nanya|mau nanya|boleh nanya|tanya tanya|tanya in|tanya dong|tanyain|tanyain dong|tanyain aja|tanya kak|tanya min|tanya bos|tanya sist|tanya gan|chat kak|chat min|chat bos|chat sist|chat gan|kontak kak|kontak min|kontak bos|kontak sist|kontak gan|telpon kak|telpon min|telpon bos|telpon sist|telpon gan|halo admin|hallo admin|hai admin|panggil admin|panggil CS|panggil seller|panggil penjual|minta nomor|minta kontak|minta telpon|minta chat|bisakah saya menghubungi|bisa kontak|bisa chat|bisa telpon|bisa telepon|kontak service|service center|pusat bantuan|pusat dukungan|layanan pelanggan|customer care|hotline|nomor hotline|call center|nomor call center|chat now|chat sekarang|kontak sekarang|hubungi sekarang|admin respon|bales chat|respon admin|balasan admin|chat cepat|respon cepat|bisa di chat|bisakah dihubungi|bisakah di chat|bisakah ditelepon|bisakah ditelpon|ada yang jaga|ada yang online|admin ada|cs ada|seller ada|penjual ada|kontak wa|nomor wa|chat wa|hubungi wa|wa nya berapa|ada wa|whatsapp|nomor whatsapp|chat whatsapp|hubungi whatsapp|cara nanya|nanya doang|mau nanya doang|mau nanya nanya|bisa dihubungi lewat apa|kontak lewat apa|chat lewat apa|telepon lewat apa|bisa di WA|bisa chat WA|admin cepat respon|cs cepat respon|seller cepat respon|penjual cepat respon|admin fast respon|cs fast respon|seller fast respon|penjual fast respon|fast respon|fast respond|respon cepat|respon kilat|balas cepat|balas kilat|admin ready|cs ready|seller ready|penjual ready|admin aktif|cs aktif|seller aktif|penjual aktif|bisa langsung chat|langsung hubungi|langsung kontak|langsung telepon|langsung telpon|minta bantuan admin|minta bantuan cs|minta bantuan seller|minta bantuan penjual|bagaimana menghubungi|cara menghubungi|bisakah admin dihubungi|bisakah cs dihubungi|bisakah seller dihubungi|bisakah penjual dihubungi/i],
        responses: [
            "Kamu bisa hubungi penjual lewat tombol 'Chat Penjual' yang ada di halaman detail produk atau di tombol 'Kunjungi Toko'. Atau bisa juga lewat kontak BUMDes ya, biar cepet direspons!",
            "Mau ngobrol sama penjualnya? Gampang kok! Tinggal klik aja tombol 'Chat Penjual' di halaman detail produk, atau bisa juga lewat tombol 'Kunjungi Toko'!",
            "Buat nanya-nanya ke penjual, kamu bisa langsung chat lewat tombol 'Chat Penjual' yang ada di halaman produk, atau mampir ke tokonya via tombol 'Kunjungi Toko'."
        ]        
    },
    {
        patterns: [/cara (memesan|beli|order|pesan)|gimana (cara|mau|kalo mo|mo|cara beli|cara order|cara pesen)|mau (pesan|beli|order|pesen|co|blanja)|pengen (beli|order|pesen)|mo (beli|order|pesen)|(beli|order|pesen|co|blanja) (gimana|gmn|gimane|gmna|cara|skrg|langsung|lgsg|yuk|deh|aja|ajh|aja ya)|(minat|niat) (beli|order|pesen)|dapetin (barang|produk|gimana|gmn)|ambil (dimana|gimana|brg|produk)|checkout (gimana|gmn|cara)|(proses|alur|langkah|prosedur|mekanisme|sistem|tutorial|guide) (beli|order|pesan|belanja|transaksi|co)|beli(in|in aja|in dong)|order(in|in aja|in dong)|pesen(in|in aja|in dong)|tolong (belikan|orderkan|pesankan|bantu beli|bantu order|bantu pesen)|via (apa|online|chat|telepon|telpon|pulsa|saldo|web|website|app|aplikasi)|lewat (apa|online|chat|telepon|telpon|pulsa|saldo|web|website|app|aplikasi)|(online|offline) (gimana|gmn|cara|langsung)|(pick up|ambil sendiri|jemput sendiri)|ada ongkir|berapa ongkir|ongkirnya berapa|beli (banyak|grosir|satuan|eceran|partai|jumlah besar|jumlah kecil|custom|customized|unik|langka|limited|pre order|po|ready stock|stok tersedia)|beli sekarang (juga|cepat|instan)|order sekarang (juga|cepat|instan)|pesan sekarang (juga|cepat|instan)|beli produk (skrg|ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|order produk (skrg|ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|pesan produk (skrg|ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|beli barang (ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|order barang (ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|pesan barang (ini|itu|tsb|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|beli item (ini|itu|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|order item (ini|itu|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|pesan item (ini|itu|tertentu|apa aja|semua|yang ini|yang itu|yang mana)|beli (jasa|layanan|servis)|order (jasa|layanan|servis)|pesan (jasa|layanan|servis)|saya (mau|ingin) (beli|order|pesan)|nanya cara (beli|order|pesan)|info cara (beli|order|pesan)|panduan (beli|order|pesan)|petunjuk (beli|order|pesan)|jelaskan cara (beli|order|pesan)|kasih tahu cara (beli|order|pesan)|tutorial lengkap (beli|order|pesan)|dari awal|tanpa ribet|simple|praktis|cepat|kilat|instan|buru-buru|langsung jadi|transaksi|(beli|order|pesan) online|beli di (toko|tempat|lokasi|konter|kios|gerai|store|outlet|cabang|marketplace|olshop|e-commerce|lapak|akun ini|website|web|app|aplikasi|rumah|luar kota|luar daerah)|beli (fisik|offline)|pengiriman untuk (beli|order|pesan)|ongkos kirim untuk (beli|order|pesan)/i],
        responses: [
        "Pesen barang? Bisa banget! Kamu tinggal 'masukkin ke keranjang' dulu, terus kalau udah yakin, langsung aja 'lanjut ke Beli Sekarang'. Dijamin sat-set dan langsung proses! Gampang banget, kan? Yuk, gas order sekarang!",
        "Mau 'belanja' ya? Gampang kok! Kamu bisa 'pilih barang' yang disuka, 'masukin ke keranjang', terus 'lanjut ke pembayaran'. Simpel banget biar cepat dapat barangnya!",
        "Waduh, pengen 'order' ya? Bisa banget! Langsung aja 'klik Beli Sekarang' atau 'masukin ke keranjang' dulu biar lebih santai. Prosesnya cepat, dijamin gak pake ribet!",
        "Nanya soal 'cara beli'? Sip! Pokoknya, 'pilih produknya', terus 'masukkin keranjang', abis itu 'checkout'. Gak nyampe 5 menit, udah beres deh transaksinya. Yuk, coba sekarang!",
        "Butuh 'bantuan untuk beli'? Tenang aja! Cukup 'cari produk' yang kamu mau, 'masukin ke keranjang', terus 'selesaikan pembayaran'. Kalau ada kendala, chat kita lagi aja ya! Happy shopping!"
        ]
    },
    {
        patterns: [/lacak pesanan|status pesanan|paket|pesanan saya|sudah sampai mana|udah nyampe mana|paketku mana|resi mana|paket belum datang|paket belum nyampe|kapan sampai|kapan nyampe|tracking pesanan|cek status|cek pesanan|paket gue|barang gue|barang saya mana|udah dikirim|udah jalan|nyampe kapan|estimasi sampai|estimasi pengiriman|posisi paket|posisi barang|lokasi paket|lokasi barang|paket nyangkut|paket lama|kok lama|lama banget|belum gerak|cek resi|update resi|tracking barang|cek barang|barang udah sampai|udah terima|kapan dikirim|pengiriman barang|status pengiriman|cek kiriman|barang nyampe jam berapa|bisa cek pengiriman|status pengiriman saya|paket saya dimana|barang saya dimana|tracking order|orderan gue|orderan saya|pesanan gue|barang otw|paket otw|paket jalan|sudah otw|udah otw|cek kiriman|udah dikirim belum|bagaimana melacak pesanan|dimana pesanan saya|cara cek status pesanan|bagaimana status pesanan|bisakah saya melacak pesanan saya|tolong lacak pesanan|bisakah lacak pesanan|bagaimana cara lacak|lacakin dong|tolong lacakin|bantu lacak|gimana lacak|cara lacak paket|cara lacak barang|gimana ngecek paket|cek paket saya|cek barang saya|gimana status barang saya|status barang saya|tolong cek pesanan saya|bisakah cek pesanan|apakah pesanan saya sudah diproses|sudah sampai tahap mana pesanan saya|dimana posisi pesanan saya|boleh saya melacak|saya mau melacak|saya ingin lacak|lacak dong|tracking dong|cek dong|info lacak|info status|minta info status|status pengiriman terkini|pesanan saya sudah keluar belum|apakah pesanan sudah dikirim|kapan pesanan akan tiba|perkiraan tiba|perkiraan sampai|estimasi tiba|barang belum nyampe|pesenan saya|barang saya kok belum nyampe|kapan barang sampai|kok lama banget|resi tracking|cek tracking|dimana posisi barang|update status pesanan|ada update pengiriman|status pengiriman terkini|kapan paket tiba|lacak order|cek order|lacak kiriman|dimana barang saya|barang belum diterima|apakah sudah dikirim|sudah di jalan|paketku udah di mana|barangku udah di mana|kapan perkiraan sampai|bisakah saya tahu status pengiriman|info pengiriman|gimana cara tracking|bantu tracking|paket saya di mana|barang saya di mana|cek status pengiriman|tolong cek pengiriman|cek pengiriman saya|kenapa belum sampai|masih dimana|belum dateng|udah dikirim kah|kapan dateng|tanggal berapa sampai|estimasi pengiriman berapa lama|bisa cek pengiriman saya|mohon cek status pesanan|tanya status pesanan|dimana status pesanan|liat status pesanan|status paket|status barang|cek paket|cek barang|tracking paket|barang saya kenapa|ada info paket|info paket|paket belum sampe|blm nyampe|blm sampe|blm datang|udah sampe blm|udah nyampe blm|lacakin paket|lacakin barang|posisi terakhir|terakhir dimana|status terkini|update terbaru|paketku udah sampai|barangku udah sampai|pengiriman sampai mana|lacak pengiriman|cek pengiriman|dimana posisi terkini|paketku lagi dimana|sampai mana pesanan|barang sampai mana|bagaimana cara mengecek|cara mengetahui status|info terbaru paket|paketku macet|kenapa belum gerak|pengiriman terhambat|barang belum sampai rumah|kapan saya bisa menerima|saya mau tahu status|boleh tahu status pengiriman|ada kendala pengiriman|paket tidak bergerak|berapa lama lagi sampai|sudah keluar dari gudang|sedang dikirim|sudah dalam perjalanan|info lokasi terkini|minta info pengiriman|tanya info pengiriman|bantu cek pengiriman|paket di kurir mana|kurir siapa|nomor resi saya|update barang|dimana paket saya sekarang|kapan tiba di tujuan|estimasi waktu pengiriman|tolong carikan pesanan saya|cek keberadaan paket|paket saya tertunda|kenapa belum ada update|belum ada perubahan status|barang saya ketahan|bisa bantu lacak|ada masalah pengiriman|pengiriman stuck|paket mandek|tolong periksa status|status kiriman|barang kapan tiba|paket kapan tiba|info perkembangan pengiriman|saya mau cek paket|cek info kiriman|minta update pengiriman|dimana paket saya|lacakkan pesanan saya|status pengiriman saya yang terbaru|boleh cek resi ini|lacak nomor resi|cek nomor resi|bagaimana perkembangan paket saya|info posisi paket|paket saya sudah sampai mana|lacak orderan saya|cek orderan saya|bagaimana status kiriman|info pengiriman terbaru|cek pengiriman barang|minta info barang|progress pengiriman|pengiriman udah dimana|paket udah sampe mana|tracking resi|resi tracking|cek resi saya|dimana resi saya|resi saya mana|saya mau cek resi|tracking pengiriman|info paket terkini|paket udah dikirim belum|barang udah dikirim belum|status barang terakhir|posisi barang terakhir|lacak kiriman saya|cek kiriman saya|kapan barang dikirim|kapan paket dikirim|tanggal pengiriman|jam berapa dikirim|sudah ambil kurir|kurir sudah ambil|sedang di perjalanan|paket saya dalam perjalanan|status barang ini|posisi barang ini|lacak barang ini|cek status barang ini|kapan sampai tujuan|estimasi sampai tujuan|barang masih di gudang|barang sudah keluar|sedang proses pengiriman|pengiriman sudah jalan|cek barang yang dipesan|lacak barang yang dipesan|status barang yang dipesan|dimana barang pesanan saya|tolong lacak barang pesanan|bisa cek barang pesanan|ada update barang pesanan|info barang pesanan|apakah barang sudah ada|barang sudah diterima belum|pengiriman belum sampai|estimasi kedatangan|kapan barang saya tiba|info pengiriman paket|cek status paket|paket saya sudah berangkat|barang saya sudah berangkat|lacak barang saya|cek barang saya|kapan sampai rumah|estimasi sampai rumah|bisa tolong lacak|minta tolong lacak|dimana posisi terakhir|update pengiriman terbaru|barang saya on the way|paket saya on the way|cek info paket|lacakkan paket saya|status paket terkini|info pengiriman barang|cek pengiriman terkini|pesanan sudah ada dimana|posisi pesanan saya|update pesanan saya|cek keberadaan barang|status keberadaan paket|lacak keberadaan barang|boleh cek status|mau tahu status|lacak pesanan instan|cek pesanan cepat|lacak status cepat|kapan sampainya|sudah proses kirim|info kirim|pengiriman gimana|statusnya gimana|barang gue nyampe kapan|paket gue nyampe kapan|cek status terbaru|lacak status terbaru|dimana posisi terakhir paket|terakhir paket dimana|ada update paket saya|info paket saya|barang udah jalan belum|paket udah jalan belum|barang sudah dikirim ke alamat|paket sudah dikirim ke alamat|tolong cek alamat pengiriman|pengiriman sesuai alamat|status pengiriman sesuai alamat|gmna status|gmn status|gmn paket|gmna paket|gmn brg|gmna brg|status skrg|paket skrg|brg skrg|lg dmn|lg dmn paket|lg dmn brg|ud sampe blm|udah smp blm|paket ku|brg ku|orderan ku|pesanan ku|cek skrg|lacak skrg|tanya status|tny status|status kirim|paket nyampe|brg nyampe|kpn smpe|kpn nyampe|estimasi smpe|estimasi nyampe|info sgr|sgr info|update sgr|skrg status|posisi skrg|lokasi skrg|udh krm|ud krm|udh sampe|ud sampe|udh trima|ud trima|kapan d krm|kpn d krm|kapan brg smpe|kapan pkt smpe|ada resi|ada no resi|kirim kemana|kmn kirim|brg dmn|pkt dmn|lacakin pkt|lacakin brg|status lngsng|lngsng cek|langsng cek|dimana ya|kmn ya|kok gk gerak|paket gk gerak|brg gk gerak|statusnya kok gini|kok gda update|gda update|apakah udh|udah apa|tolong cek ya|cek in dong|brg lg dmn|pkt lg dmn|udh nyampe mana ya|udah nyampe mana ya|cek no resi|status terbaru|info terbaru|update info|progress terbaru|terbaru status|terbaru info|paket gaul|brg gaul|lacak gaul|cek gaul|tracking gaul|info gaul/i],
        responses: [
        "Mau cek pesananmu? Gampang, tinggal login aja ke akunmu, terus lihat di bagian 'Pesanan Saya'. Semua info pengiriman ada di sana kok!",
        "Kepoin paketmu ya? Santai, kamu bisa langsung 'cek status' di menu 'Pesanan Saya' setelah login. Dijamin update terus!",
        "Barangmu udah sampe mana nih? Yuk, langsung aja 'tracking order' di akunmu. Dijamin gak pake lama!",
        "Pengen tahu posisi paketmu sekarang? Login, terus meluncur ke 'Pesanan Saya'. Semua info resi dan update ada di sana, tinggal klik!",
        "Lagi nungguin paket ya? Cuss, buka aja akunmu, terus intip di 'Pesanan Saya' buat lihat 'update resi' atau 'status pengiriman' terkini!"
        ]
    },
    {
        patterns: [/hubungi penjual|kontak penjual|chat penjual/i],
        responses: [
            "Buat nanya-nanya ke penjual, kamu bisa langsung chat lewat tombol 'Chat Penjual' yang ada di halaman detail produk, atau mampir ke tokonya via tombol 'Kunjungi Toko'."
        ]
    },
    {
        patterns: [/metode pembayaran|cara bayar|pembayaran apa saja|pembayaran apa|bayar pake apa|byr pake apa|byr pke apa|transfer apa aja|transfer bisa|tf bisa|tf|transfer|bisa bayar pake apa/i],
        responses: [
            "Kami menerima pembayaran via transfer bank (BCA, Mandiri, BRI, Lampung), OVO, GoPay, Dana, Tunai, dan COD. Lengkap banget kan pilihannya!",
            "Bayar di sini banyak pilihannya! Ada transfer bank (BCA, Mandiri, BRI, Lampung), e-wallet kayak OVO, GoPay, sama Dana. Kalau mau langsung di tempat, bisa tunai atau COD juga."
        ]
    },
    {
        patterns: [/^(cek stok|ketersediaan stok|stok (ini)?|berapa sisa stok|stoknya ada berapa|masih ada stok gak|sisa stok|stok produk ini|apakah barang ini ready stock|ready stock|ready|ada gak barangnya|restock kapan|ada ready)$/i], // Added "apakah barang ini ready stock"
        responses: [
            `Stok produk ini saat ini adalah ${typeof currentProductStock !== 'undefined' ? currentProductStock : 'tidak diketahui'} pcs. Yuk, amankan sebelum kehabisan!`,
            `Barangnya "ready stock" kok! Langsung checkout aja biar nggak kehabisan!` // Added for "ready stock"
        ]
    },
    {
        patterns: [/^(stoknya selalu update|update stok|stok real-time|stok update terus)$/i],
        responses: [
            "Yep, stok kami diperbarui secara real-time kok. Jadi apa yang kamu lihat itu data terbaru. Kalau kamu pilih variasi produk, stoknya juga bakal otomatis menyesuaikan."
        ]
    },
    {
        patterns: [/^(stoknya 0|kenapa stok 0|habis stok|kosong stok|udah gak ada)$/i],
        responses: [
            "Stoknya 0 berarti produk ini lagi nggak ada atau variasi yang kamu pilih udah habis. Coba deh pilih variasi lain atau cek lagi nanti ya, siapa tahu udah restock!"
        ]
    },
    {
        patterns: [/^(deskripsi produk|info produk|apa itu produk ini|jelasin produknya|detail produk|keterangan produk|ukuran produk|lebar produk|panjang produk|ukuran,lebar,panjang produk ini berapa|ukuran|dimensi|size|ukurannya berapa|detail ukuran)$/i],
        responses: [
            "Deskripsi lengkap produk ini bisa kamu temuin di bagian bawah halaman detail produk. Di sana ada info lengkap soal fitur, bahan, dan spesifikasinya. Untuk 'ukuran atau dimensi' juga ada di sana ya. Yuk, digeser ke bawah!"
        ]
    },
    {
        patterns: [/^(bahan produk|terbuat dari apa|ini dari bahan apa|materialnya apa)$/i],
        responses: [
            "Informasi bahan produk ini ada di bagian deskripsi produk ya. Scroll aja ke bawah buat lihat detailnya!"
        ]
    },
    {
        patterns: [/^(klaim garansi|cara klaim garansi|gimana klaim garansi)$/i],
        responses: [
            "Buat klaim garansi, kamu bisa hubungi penjual via fitur chat. Nanti penjualnya bakal kasih panduan dan persyaratan yang dibutuhin. Jangan sungkan ya, kami siap bantu!"
        ]
    },
    {
        patterns: [/^(ada garansi|garansi produk|berapa lama garansi|garansinya gimana|apa ada garansi)$/i], // Added "apa ada garansi"
        responses: [
            "Info garansi produk biasanya tertera di deskripsi produk. Kalau nggak ada, coba deh langsung hubungi penjual buat konfirmasi lebih lanjut ya. Tenang aja, kami pasti bantu!" // More friendly
        ]
    },
    {
        patterns: [/^(produk asli|original|asli gak|ini ori|barang asli|produk ini ori atau kw|ori apa kw)$/i], // Added ori/kw question
        responses: [
            "Kami jamin semua produk yang kami jual 'asli' dan berkualitas kok. Nggak ada KW-KW-an di sini! Kamu bisa lihat ulasan dari pembeli lain juga buat makin yakin!" // More assertive and gaul
        ]
    },
    {
        patterns: [/^(produk serupa|rekomendasi produk|ada yang mirip|ada yang lain gak|ada produk sejenis|rekomendasi dong|cari produk kayak gini)$/i],
        responses: [
            "Kamu bisa lihat produk serupa atau rekomendasi lainnya di bagian 'Produk Terkait' di bawah halaman ini, atau coba jelajahi kategori produk kami. Siapa tahu ada yang nyantol di hati!"
        ]
    },
    {
        patterns: [/^(barang rusak|rusak saat diterima|produk cacat|cacat produk|barang error|pecah|rusak pas nyampe|reject|kalau barang rusak atau defect, gimana proses retur|defect|barang reject|rusak pas dateng|cacat dikit)$/i], // Added return process question
        responses: [
            "Duh, maaf banget ya atas ketidaknyamanannya. Kalau barang yang kamu terima rusak atau cacat, langsung aja ajukan komplain di menu profil bagian pesanan saya via fitur 'ajukan pengembalian' di detail pesananmu. Jangan lupa sertakan bukti foto/video ya. Kami pasti bantu kok!"
        ]
    },
    {
        patterns: [/^(ganti alamat|ubah alamat|salah alamat|alamatnya mau diubah|alamat beda)$/i],
        responses: [
            "Untuk ganti alamat pengiriman, kamu bisa ubah di pengaturan akunmu di bagian 'Alamat Pengiriman' ya. Gampang kok!"
        ]
    },
    {
        patterns: [/^(lupa password|reset password|ganti password|lupa sandi|passwordnya apa)$/i],
        responses: [
            "Kalau kamu lupa kata sandi, tinggal klik 'Lupa Kata Sandi' di halaman login. Nanti instruksinya kami kirim ke emailmu kok!"
        ]
    },
    {
        patterns: [/lokasi toko (fisik)?|alamat toko|dimana toko|lokasi bumdes|alamat bumdes|kontak bumdes|kontak|kntak|nomor telepon bumdes|alamat lengkap|alamatnya di mana|telepon bumdes|nomor telpon|kontak kami|lokasi di mana|nomor bumdes/i],
        responses: [
            `Tentu, untuk info lokasi toko fisik ${bumdesNama}, kamu bisa kunjungi alamat: '${bumdesAlamat}'. Kalau mau hubungi kami, bisa lewat nomor telepon ini: '${bumdesKontak}'. Kami tunggu kedatanganmu ya!`
        ]
    },
    {
        patterns: [/^(jam buka|buka jam berapa|tutup jam berapa|jam operasional|jam kerja)$/i],
        responses: [
            "Kami buka setiap hari Senin - Jumat pukul 08.00 - 17.00 WIB. Hari Sabtu, Minggu, dan hari libur nasional kami libur ya. Ditunggu kedatangannya!"
        ]
    },
    {
        patterns: [/^(bisnis apa|jual apa aja|produk apa aja|dagang apa|usaha apa)$/i],
        responses: [
            `${bumdesNama} punya banyak banget produk, mulai dari kerajinan tangan, makanan olahan, hasil pertanian lokal, dan masih banyak lagi. Cek langsung di website kami ya, dijamin kalap!`
        ]
    },
    {
        patterns: [/^(ada diskon|promo|potongan harga|ada potongan|diskonan|ada voucher|ada diskon kalau beli banyak|harga nett atau bisa nego|harga nett|nego|harga pas|bisa kurang|ada promo apa)$/i], // Added discount/nego questions
        responses: [
            "Tentu dong! Kami sering banget ada promo menarik. Cek aja bagian 'Promo' di website kami atau follow Instagram kami biar nggak ketinggalan info diskon terbaru!",
            "Harga yang tertera itu udah 'harga nett' ya, Kak. Jadi belum bisa nego. Tapi siapa tahu nanti ada promo spesial kalau beli banyak, pantau terus ya!" // Added for harga nett/nego
        ]
    },
    {
        patterns: [/^(punya akun media sosial|medsosnya apa|instagramnya apa|facebooknya apa|tiktoknya apa|sosmed)$/i],
        responses: [
            "Tentu! Kami aktif di Instagram dengan akun: '@pekonsinarpetir_'. Jangan lupa follow ya buat update terbaru dan giveaway seru!"
        ]
    },
    {
        patterns: [/^(cara daftar|daftar akun|bikin akun|registrasi)$/i],
        responses: [
            "Untuk daftar akun, kamu tinggal klik tombol 'Daftar' atau 'Register' di pojok kanan atas halaman. Isi data yang dibutuhin, terus ikutin aja instruksinya. Gampang kok, sat-set!"
        ]
    },
    {
        patterns: [/^(hapus akun|tutup akun|delete akun)$/i],
        responses: [
            "Untuk hapus akun, kamu bisa langsung hubungi tim layanan pelanggan kami ya. Nanti mereka bakal bantu prosesnya."
        ]
    },
    {
        patterns: [/^(bisa jadi reseller|jadi agen|daftar reseller|join reseller)$/i],
        responses: [
            "Tentu, kami membuka kesempatan buat kamu yang mau jadi reseller atau agen kami. Langsung aja hubungi kontak BUMDes kami buat info lebih lanjut soal program reseller ya, cuan menanti!"
        ]
    },
    {
        patterns: [/^(kebijakan pengembalian|retur barang|plgin brg|pengembalian produk|refund|tukar barang|bisa tukar kalau ukurannya salah|tukar ukuran|salah ukuran|tuker barang|kekecilan|kegedean|gak pas)$/i], // Added size exchange question
        responses: [
            "Untuk kebijakan pengembalian produk, kamu bisa lihat detailnya di halaman 'Kebijakan Pengembalian & Penukaran' di website kami. Pastiin syarat dan ketentuannya terpenuhi ya, biar prosesnya lancar.",
            "Bisa kok! Kalau ukurannya salah atau nggak pas, kamu bisa ajukan penukaran produk. Cek detail syarat dan ketentuannya di halaman 'Kebijakan Pengembalian & Penukaran' ya. Jangan khawatir!" // Added for size exchange
        ]
    },
    {
        patterns: [/^(pengiriman|ongkir|biaya kirim|berapa ongkir|ongkirnya berapa ke \[lokasi\]|pakai kurir apa|bisa kirim pakai \[nama kurir\]|kapan barang saya dikirim|berapa lama pengiriman ke \[nama kota\]|bisa same day atau instant delivery|barang belum sampai, bisa bantu cekin|kirim|kurir|same day|instant delivery|lama pengiriman|barang dikirim|belum sampai|cek pengiriman|estimasi kirim|paket nyangkut|kirim kapan)$/i], // Added all delivery/shipping questions
        responses: [
            "Ongkir bakal dihitung otomatis pas kamu checkout, tergantung lokasi tujuan dan berat/volume produk. Ada pilihan jasa kirim juga kok, bisa disesuaikan sama kebutuhanmu!",
            "Kami pakai berbagai jasa kurir terpercaya kok. Nanti pas checkout, kamu bisa pilih kurir yang paling cocok ya!", // For "Pakai kurir apa?"
            "Bisa banget! Untuk pengiriman **Same Day atau Instant Delivery**, opsinya bakal muncul pas kamu checkout, kalau memang tersedia di area kamu.", // For "Same day/instant"
            "Estimasi pengiriman ke [nama kota] (sesuaikan dengan lokasimu) bisa dicek pas checkout ya. Biasanya tergantung jenis kurir dan lokasi pengiriman.", // For "Berapa lama pengiriman?"
            "Kalau barangmu belum sampai, coba deh lacak dulu di bagian 'Pesanan Saya' di akunmu. Kalau ada kendala, langsung kontak kami ya, biar bisa kita bantu cek ke kurirnya!" // For "Barang belum sampai"
        ]
    },
    {
        patterns: [/^(ada promo ongkir|gratis ongkir|free ongkir)$/i],
        responses: [
            "Kami kadang ada promo gratis ongkir atau diskon ongkir lho! Pantau terus pengumuman di website atau Instagram kami ya, biar nggak ketinggalan promo spesialnya."
        ]
    },
    {
        patterns: [/^(tentang kami|siapa bumdes|profil bumdes|sejarah bumdes|apa itu bumdes ini)$/i],
        responses: [
            `${bumdesNama} adalah badan usaha milik desa yang fokus banget sama pengembangan ekonomi lokal dan pemberdayaan masyarakat. Kami bergerak di bidang perdagangan produk-produk unggulan desa. Tujuan kami, ya biar masyarakat desa makin sejahtera lewat usaha ini.`
        ]
    },
    {
        patterns: [/^(cara komplain|pengaduan|komplain pesanan|ada masalah|sudah transfer, tapi belum diproses|ada kendala|gak beres|transfer belum proses|belum diproses|sudah bayar|kok belum diproses|bukti transfer)$/i], // Added transfer not processed
        responses: [
            "Kalau ada masalah atau komplain, langsung aja hubungi layanan pelanggan kami via chat ini atau nomor kontak BUMDes yang tertera. Kamu juga bisa kirim email ke 'snrpetir@gmail.com'. Kami siap bantu banget!",
            "Udah transfer tapi belum diproses? Aduh, maaf banget ya! Langsung kirim bukti transfer kamu ke kami via chat atau email 'snrpetir@gmail.com' ya. Nanti langsung kami cek dan proses secepatnya!" // Added for transfer not processed
        ]
    },
    {
        patterns: [/^(buka cabang|ada toko lain|cabang lain)$/i],
        responses: [
            "Saat ini, lokasi utama kami ada di ${bumdesAlamat}. Untuk info cabang lain (kalau ada) bakal kami umumkan di website atau Instagram kami ya. Stay tuned!"
        ]
    },
    {
        patterns: [/^(produk paling laku|best seller|favorit|rekomendasi produk)$/i],
        responses: [
            "Produk best seller kami saat ini adalah Batik Tulis Motif 'Kembang Desa' dan keripik singkong pedas 'Nampol'. Banyak banget yang suka loh, cobain deh!" // Contoh, sesuaikan dengan produk Anda
        ]
    },
    {
        patterns: [/^(ada lowongan|buka lowongan|lowongan kerja|kerja di bumdes)$/i],
        responses: [
            "Informasi lowongan kerja di ${bumdesNama} bakal kami umumkan di website resmi atau Instagram kami. Pantau terus ya, siapa tahu ada posisi yang cocok buat kamu!"
        ]
    },
    {
        patterns: [/^(saran|kritik|masukan)$/i],
        responses: [
            "Makasih banyak atas saran dan kritikmu! Kami sangat menghargai masukan buat perbaikan. Kamu bisa sampaikan via chat ini atau email kami di 'snrpetir@gmail.com'. Kami bakal tindak lanjuti kok!"
        ]
    },
    {
        patterns: [/^(salah pesan|salah order|mau ganti pesanan|batalin order|batalkan order|batalin barang|batalin brg|batalkan brg)$/i], // 'batalin order' ditambahkan di sini
        responses: [
            "Kalau kamu salah pesan, buruan deh hubungi layanan pelanggan kami secepatnya ya. Kami bakal cek apakah pesananmu masih bisa diubah atau dibatalin sebelum dikirim."
        ]
    },
    {
        patterns: [/^(harga produk|berapa harga ini|harganya berapa|list harga)$/i],
        responses: [
            "Harga produknya udah tertera jelas kok di halaman detail produk. Kalau ada variasi, harganya mungkin beda. Coba cek di sana ya!"
        ]
    },
    {
        patterns: [/^(produk baru|ada produk baru|new arrival)$/i],
        responses: [
            "Tentu! Kami selalu update produk baru secara berkala. Cek aja kategori 'Produk Terbaru' atau 'New Arrivals' di website kami ya, banyak yang kece-kece!"
        ]
    },
    {
        patterns: [/^(bisa pilih warna|bisa pilih model|mau pilih warna|mau pilih model|pilih warna atau model|warna apa|model apa|mau warna ini|bisa custom warna)$/i], // New pattern for color/model
        responses: [
            "Bisa dong! Kalau ada pilihan warna/model, nanti pas kamu klik produknya, opsi itu bakal muncul kok. Tinggal pilih aja sesuai selera!"
        ]
    },
    {
        patterns: [/^(foto asli|lihat foto asli|real pic|realpict|cek foto asli|ada foto aslinya gak)$/i], // New pattern for real photo
        responses: [
            "Foto produk yang kami tampilkan itu 'foto asli' (real pic) kok. Jadi apa yang kamu lihat itu yang bakal kamu dapetin!"
        ]
    },
    {
        patterns: [/^(ada bonus|free gift|bonusnya apa|dapet bonus gak|dapet free gift|hadiah|dapet apa aja|ada bonusnya gak)$/i], // New pattern for bonus/free gift
        responses: [
            "Kadang kami ada promo bonus atau free gift lho! Biasanya info promonya bakal muncul di halaman produk atau saat checkout. Jangan sampai kelewatan ya!"
        ]
    }
];

// Fungsi normalisasi teks
function normalizeText(text) {
    return text
        .toLowerCase()
        .replace(/[^\w\s]/gi, '')
        .replace(/\b(saya|ingin|mau|tolong|tlg|tlong|apakah|gimana|bagaimana|yah|ya|dong|nih|deh|pun|kan|kak|min|gan|bos|bestie|besti|bro|sis|om|tante|pak|bu|mas|mba|bang|mbak|ndeh|ndah|ndeh|lah|koq|kok|sih|dah|udah|yang|punya|ada|itu|ini|buat|bisa|kalau|kalo|juga|aja|aja ya|banget|bangett|bangettt|banget dah|banget deh|banget banget|banget dahh|banget dehh|sekali|bangettt|gitu|kan|kali|loh|lho|tau|tuh|coba|coba deh|coba dong|ya ampun|aduh|duh|loh kok|lho kok|gini|begitu|apaan|iya|iya nih|iya ya|oh|oh iya|oh ya|hmmm|hmm|oke|ok|okey|okay|siap|baik|sip|mantap|mantabb|gass|cus|ayok|yuk|yukk|yuk ah|ayo|coba dong|coba deh|deh|nya|nya itu|nya ini|di mana|dimana|ke mana|kemana|di sana|disana|ke sini|kesini|lagi|lagi nih|lagi dong|dong ya|ya dong|ya nih|ya kan|banget loh|banget lho|banget banget|gimana ya|gimana nih|pake|pakek|pakai|tolongin|bantu|bntu|bantuan|untuk|buat|krn|karena|sama|sama aja|biar|biar apa|apa ya|apa nih|apaan sih|itu apa|ini apa|yg|yg mana|yg ini|yg itu|apalah|lah ya|deh ya|nih ya|aja deh|aja sih|banget sih|banget deh|sekalian|mungkin|kira kira|kirakira|kira-kira)\b/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

// Keyword-based fallback matching
function keywordMatch(message) {
    const keywordMap = [
    {
        keywords: [
            'order', 'beli', 'pesan', 'memesan', 'mesan', 'pengen beli', 'mau checkout', 'pesen dong', 'gas beli',
            'gercep order', 'minat beli', 'cara memesan', 'bagaimana cara beli', 'cara order', 'gimana cara beli',
            'gimana kalo mau pesen', 'beli gimana', 'cara order gimana', 'cara pesen', 'gimana order',
            'beli produk gimana', 'order produk', 'cara pembelian', 'gimana cara ordernya', 'mau beli dong',
            'pesen sekarang', 'checkout dong', 'beliin dong', 'mau beli ini', 'bagaimana pembelian', 'langsung beli',
            'cara transaksi', 'mau transaksi', 'belinya gimana', 'pesan ini', 'gimana sih cara belinya', 'pengen order',
            'mau beli produk ini', 'cara dapetinnya', 'gimana cara dapetnya', 'langkah-langkah order',
            'proses pembelian', 'alur beli', 'alur order', 'mau beli kak', 'gimana cara pesannya',
            'tolong bantu beli', 'bisa order sekarang', 'cara order gampang', 'gampang ordernya gimana',
            'langsung order', 'langsung pesen', 'mau beli sekarang', 'pesen barang ini', 'beli barang ini',
            'cara beli barang', 'cara pesen barang', 'order ini', 'mau beli itu', 'cara beli itu',
            'cara beli dong', 'gimana cara order', 'gmna cara beli', 'gimana mau beli', 'cara pemesanan',
            'mau beli nih', 'gimana nih beli', 'mau pesen nih', 'beli dong', 'order dong', 'gimana ordernya',
            'cara ordernya', 'langkah beli', 'step beli', 'step order', 'gimana caranya beli', 'pengen beli produk',
            'mau beli apa aja', 'bisa order apa aja', 'cara bayar gimana', 'pembayaran gimana', 'gimana cara bayarnya',
            'checkout gimana', 'order via apa', 'beli via apa', 'minat order', 'niat beli', 'mau ambil barang',
            'ambil barangnya gimana', 'langsung transaksi', 'gimana kalo mau beli', 'pengen order ini',
            'bantu beli', 'bantu order', 'cara order product', 'cara beli product', 'gimana beli product',
            'mau beli item ini', 'cara beli item', 'cara pesan item', 'beli via online', 'order via online',
            'gimana beli online', 'cara beli online', 'mau beli online', 'beli web', 'beli website', 'order web',
            'order website', 'gimana web', 'gimana website', 'mau belanja', 'cara belanja', 'belanja online',
            'mau belanja online', 'gimana cara belanja', 'cara belanja gimana', 'beli sekarang', 'order sekarang',
            'pesan sekarang', 'langsung checkout', 'mau beli sekarang juga', 'beli sekarang juga', 'beli langsung',
            'order langsung', 'pesan langsung', 'cara dapetin barang', 'gimana dapetin barang', 'mau dapetin barang',
            'pengen dapetin barang', 'beliin saya', 'orderin saya', 'tolong orderkan', 'tolong belikan',
            'gimana cara pembayarannya', 'cara pembayaran', 'metode pembayaran', 'pembayaran metode', 'gimana bayar',
            'bayar gimana', 'cara beli di sini', 'order di sini', 'pesan di sini', 'gimana beli di sini',
            'beli lewat mana', 'order lewat mana', 'pesan lewat mana', 'gmn', 'gmna', 'gmnn', 'gimna', 'gimnaa',
            'gimanaa', 'gimana nih', 'gimana ya', 'gimana caranya', 'mau beli', 'beli', 'orderin', 'beliin',
            'cara beli', 'mau pesan', 'gimana cara', 'pengen', 'minat',
            // --- Penambahan kata baru "Cara Memesan" ---
            'mo beli', 'mo pesen', 'mo order', 'cara ordernya gmn', 'beli skrg', 'order skrg', 'pesen skrg',
            'mau transaksi sekarang', 'transaksi gmn', 'gmn byr', 'cara byr', 'byr gmn', 'mau beli brg', 'order brg',
            'belanja skrg', 'pengen beli skrg', 'bisa beli', 'bisa order', 'bisa pesen', 'cara dapetin', 'gimana dapet',
            'langkah order', 'proses beli', 'alur beli barang', 'beli dong kak', 'tolong order', 'mau beli ini itu',
            'checkout cepat', 'pesen cepat', 'beli online', 'order online', 'pesen online', 'gimana kalo beli',
            'pengen order apa aja', 'mau beli produk', 'cara pembayaran online', 'bayar online', 'metode byr',
            'order via apps', 'beli via apps', 'mau ambil', 'ambil di mana', 'transaksi langsung', 'bantu beliin',
            'orderin dong', 'belikan', 'gmna cara byr', 'byr nya gmn', 'beli disni', 'order disni', 'pesen disni',
            'beli lwt mana', 'order lwt mana', 'pesen lwt mana', 'gmn sih', 'gmn ya', 'bisa pesen', 'mau beli apa',
            'cara pesan produk', 'beli via', 'order via',
            // --- Penambahan variasi "langsung" dan singkatan lainnya ---
            'lgsg beli', 'lgsg order', 'lgsg pesen', 'lgsg checkout', 'lgsg transaksi', 'lgsg byr', 'lngsng', 'lngsng beli',
            'lngsng order', 'lngsng pesen', 'lngsng checkout', 'lngsng transaksi', 'lngsng byr', 'langsng', 'langsng beli',
            'langsng order', 'langsng pesen', 'langsng checkout', 'langsng transaksi', 'langsng byr', 'cus beli', 'gas order',
            'gercep beli', 'sikat beli', 'skrg beli', 'skrg order', 'skrg pesen'
        ],
        response: "Pesen barang? Bisa banget! Mau masuk keranjang dulu, atau langsung lanjut aja ke 'Beli Sekarang' juga bisa kok biar sat-set!"
    },
        {
        keywords: [
            // --- Inti Kata Kunci Pelacakan (Umum & Formal) ---
            'lacak', 'status', 'pesanan', 'paketku', 'cek paket', 'tracking', 'orderan', 'barang nyampe mana', 'udah dikirim belum',
            'cek status', 'status pesanan', 'paket udah nyampe', 'paketku gimana', 'orderanku', 'track order', 'barangku', 'kiriman',
            'sampai mana', 'cek resi', 'resi', 'nomor resi', 'status kirim', 'paketku udah sampe mana', 'status orderan',
            'cek order', 'lacak order', 'pesananku', 'paket ku', 'barang ku', 'udah dikirim kah', 'kapan nyampe',
            'kok belum dikirim', 'ada di mana paketnya', 'resinya mana', 'trackid', 'kiriman sampai kapan', 'kapan barang nyampe',
            'pengiriman kapan', 'sampai kapan kirimannya', 'update paket', 'cek status pengiriman', 'status pengiriman barang',
            'paket saya nyampe kapan', 'orderan saya nyampe kapan', 'dimana barang saya', 'mana barangku', 'barang saya dmn',
            'status kiriman', 'brg dmn skrg', 'uda dkirim blm', 'udah dikirim belum ya', 'pesanan kapan sampai', 'paket nyampe kpn',
            'brg kpn smpe', 'status barang', 'cek brg', 'lacak brg', 'mana brg', 'nmr resi', 'no resi', 'nomor lacak',
            'no lacak', 'nomor tracking', 'no tracking', 'nmr tracking', 'no id', 'id resi', 'no paket', 'nmr paket',
            'posisi brg', 'status kirim barang', 'barangku dmn', 'paketku dmn', 'cek lokasi paket', 'posisi paket',
            'barang saya di mana', 'paket ini di mana', 'estimasi sampai', 'kapan tiba', 'pengiriman saya', 'cek pengiriman',
            'info pengiriman', 'update pengiriman', 'lacak kiriman', 'resi pengiriman', 'nomor pelacakan', 'lacak pesanan online',
            'cek barang online', 'status pengiriman terbaru', 'barang belum tiba', 'paket belum diterima',
            'pesanan saya belum sampai', 'where is my order', 'track my order', 'my package', 'dikirim', 'dkrm',
            'd kirim', 'd krim', 'krm', 'kirim', 'terkirim', 'sdh kirim', 'sdh dikirim', 'nyampe', 'smpe', 'sampe', 'nmp',
            'sampai', 'tiba', 'udah tiba', 'sdh sampai', 'blm', 'blum', 'belum', 'blom',
            'udah', 'uda', 'udh', 'sdh', 'sudah', 'paket', 'pkt', 'pket', 'paketan', 'box', 'kiriman barang', 'barang',
            'brg', 'barng', 'produk', 'item', 'orderan barang', 'gimana', 'gmn', 'gimn', 'gmana', 'bgmn', 'bagaimana',
            'dimana', 'dmn', 'd mana', 'dmana', 'lokasi', 'posisi', 'kapan', 'kpn', 'waktu', 'jam berapa', 'tanggal berapa',
            'hari apa', 'nomor', 'no', 'nmr', 'nomer', 'angka', 'kode', 'resi', 'resy', 'resie', 'resi id', 'tracking id',
            'no resi', 'cek', 'ccek', 'check', 'lihat', 'pantau', 'periksa', 'liat', 'status', 'stts', 'sttus', 'kondisi',
            'progress', 'keadaan', 'order', 'ordr', 'orderan', 'odr', 'pesenan', 'pesanan online', 'track', 'treck', 'trak',
            'telusuri', 'lacakin', 'ngecek', 'kirim', 'kirmn', 'krmn', 'ngirim', 'pengiriman', 'kurir', 'ekspedisi',
            'estimasi', 'eta', 'perkiraan', 'kira-kira', 'sekitar',
            'belum ada', 'paket saya', 'pesanan saya', 'barang saya', 'my order', 'my package update', 'where is my stuff',
            'berapa lama', 'brp lama', 'brapa lama', 'lama', 'durasi', 'waktu pengiriman', 'berapa hari', 'brp hari',
            'brapa hari', 'hari', 'jumlah hari', 'kok belum', 'knp blm', 'kenapa belum', 'masih belum', 'belum juga',
            'update terbaru', 'info terbaru', 'last update', 'status terkini', 'info terkini', 'jasa kirim', 'antar', 'ngantar',
            'akun', 'masuk', 'login', 'situs', 'website', 'aplikasi', 'app', 'dashboard', 'riwayat', 'history', 'aktivitas',
            'daftar', 'log in', 'log', 'saya', 'punya saya', 'milik saya', 'barang saya', 'pesanan saya', 'order saya',
            'delivery saya', 'cekkan', 'tolong cek', 'bantu cek', 'cara cek', 'cara melihat', 'cara lacak', 'gimana cara',
            'detail', 'info', 'informasi', 'data', 'rincian', 'lengkap', 'lengkapnya', 'detailnya',
            // --- Variasi dan Sinonim Pelacakan (diperluas) ---
            'posisi barang', 'track barang', 'pengiriman barang', 'estimasi tiba', 'kapan sampai', 'info paket', 'cek kurir',
            'no order', 'lacak no resi', 'cek status order', 'status barang saya', 'resi barang', 'dimana paket saya',
            'kapan paket sampai', 'paket belum gerak', 'paket stuck', 'paket tertahan', 'kenapa paket lama',
            'cek riwayat pesanan', 'riwayat belanja', 'paket udah dikirim', 'barang dikirim kapan', 'kapan barang tiba',
            'info pengiriman terakhir', 'status terakhir paket', 'update status', 'cek posisi', 'paket di gudang mana',
            'paket otw', 'paket dalam perjalanan', 'sudah di kurir', 'resi hilang', 'cek pengirim', 'paket sudah dekat',
            'belum nyampe', 'gak ada kabar', 'gimana barangnya', 'cek barang kiriman', 'paket ekspedisi', 'pengiriman online',
            'cek lokasi', 'tracking orderan', 'cek orderan saya', 'status pengiriman saya', 'pengiriman brg',
            'estimasi pengiriman', 'paket ku blm nyampe', 'barangku blm sampe', 'lacak kiriman online', 'cek resi online',
            'status pengiriman resi', 'no resi saya', 'id pengiriman', 'no tracking saya', 'info resi', 'cari resi',
            'posisi terkini', 'barang saya dimana', 'paket saya telat', 'pengiriman telat', 'paket nyangkut', 'paket pending',
            'cek perjalanan paket', 'perjalanan barang', 'lokasi barang', 'dimana ya', 'kapan ya', 'kenapa lama',
            'sudah proses belum', 'cek detail', 'tracking detail', 'info detail paket', 'pengiriman saya gimana',
            'cek barang udah jalan', 'paket saya sudah sampai mana', 'cek jalur paket', 'paket di agen mana', 'nomor order',
            'order id', 'paket tiba kapan', 'sampai alamat mana', 'estimasi waktu tiba', 'tracking no order', 'cek status barang',
            'status pengiriman saya', 'cek paket keluar', 'paket sudah dikirim', 'sudah keluar gudang',
            'status pengiriman terbaru', 'cek barang sampai', 'paket sudah sampai', 'tracking terbaru', 'info terbaru paket',
            'posisi terakhir', 'paket dmn', 'brg dmn', 'lacak pesanan saya', 'info pengiriman barang', 'cek barang online',
            'status order saya', 'paket saya', 'cek paket saya', 'lacak paket saya', 'bagaimana status pesanan',
            'dimana posisi paket', 'kenapa paket belum sampai', 'berapa lama pengiriman', 'estimasi waktu pengiriman',
            'status pengiriman', 'lacak nomor resi', 'cek nomor tracking', 'info lacak', 'detail pengiriman',
            'paket tertunda', 'kiriman tertunda', 'lacak pengiriman', 'tracking paket', 'update kiriman', 'info paketku',
            'cek barang saya', 'posisi kiriman', 'barang sudah dikirim', 'sudah dikemas', 'belum diproses', 'cek lokasi',
            'info lokasi', 'posisi terakhir paket', 'barang saya dimana sekarang', 'progress pengiriman',
            'pengiriman barang saya', 'lacak barang online', 'cara cek status', 'cara lacak paket', 'cek riwayat order',
            'riwayat order', 'histori pesanan', 'daftar pesanan', 'pesanan saya', 'cek daftar pesanan',
            'pengiriman saya gimana', 'ada di mana', 'kapan sampai', 'barang saya dimana', 'pesanan saya dimana',
            'kok lama', 'udah diproses', 'belum dikirim', 'kapan kirim', 'status terbaru', 'update status terbaru',
            'posisi barang sekarang', 'sampai kapan', 'barang tiba', 'paket belum datang', 'udah sampai belum', 'cek pengiriman',
            'lacak pengiriman saya', 'gimana pengiriman saya', 'barangku kok belum', 'paket belum diterima', 'status kiriman saya',
            'cek data pesanan', 'info pesanan saya', 'cek order terbaru', 'lacak produk', 'produk saya sudah sampai mana',
            'cek pengiriman hari ini', 'paket dalam perjalanan', 'sedang dikirim', 'sedang di perjalanan', 'posisi kurir',
            'cek kurir sekarang', 'paket lagi di mana', 'estimasi tiba', 'perkiraan sampai', 'barang kapan sampai',
            'cek estimasi', 'sudah sampai', 'sampai tujuan', 'delivery status', 'shipment status', 'order status',
            'parcel tracking', 'item tracking', 'delivery tracking', 'shipping status', 'my order status', 'my delivery status',
            // --- Kata Kunci Gaul & Kekinian (Paling Banyak) ---
            'paket gue',, 'paket', 'dimana paket gue', 'cek barang gue', 'kapan nyampe sih', 'udah otw belom', 'stuck di mana nih',
            'paketku masi dmn', 'ini barang kapan sampe', 'ga gerak nih paket', 'resi gua mana', 'cek status orderan gue',
            'updatean paket', 'kapan nyampenya', 'udah sampe blm', 'ini paketku kok lama', 'ngecek paket', 'status pengiriman gw',
            'paket ngadat', 'barang nyangkut', 'cek posisi terkini', 'barang gue gimana', 'paket udah di tangan kurir',
            'kurirnya siapa', 'kok pending', 'lacak barang cepet', 'pengiriman lama banget', 'paket udah jalan',
            'barang lagi di jalan', 'dimana dia sekarang', 'ini kapan dikirim', 'status pengiriman gw', 'tracking gila',
            'orderan gila', 'paketku kok gitu', 'gaada update', 'mohon info paket', 'paket kok blm nyampe', 'barangku nyangkut',
            'udah sampai atau belum', 'cek paket dong', 'track my stuff', 'where\'s my stuff', 'my package update', 'shipment tracking',
            'paket eike', 'status eike', 'barang eike', 'lacak eike', 'mana nih paket', 'udah jalan belom', 'kapan sampai beb',
            'paketku ga dateng-dateng', 'cek lokasi real time', 'barangku on the way', 'paket nyangkut di mana', 'gimana sih statusnya',
            'tolong lacakin dong', 'udah di kurir mana', 'kepo sama paket', 'paket gue lagi apa', 'kapan dianter', 'gercep dong',
            'status orderan', 'info barang', 'kapan dateng', 'cek status sekarang', 'paket lagi di mana', 'resi mana',
            'brgku', 'pktku', 'cek brgku', 'lacak brgku', 'orderanku', 'cek orderanku', 'lacak orderanku',
            'paketku udah jalan', 'barangku udah jalan', 'cek kondisi paket', 'paketku kenapa', 'pengiriman apa',
            'via apa', 'pakai kurir apa', 'cek orderan online', 'lacak order online', 'barang saya sudah dikirim?',
            'kapan bisa diambil?', 'paketku belum sampai nih', 'udah lewat estimasi', 'kenapa lama banget',
            'tolong cek status', 'gimana cara liat', 'di mana liat resi', 'cek riwayatku', 'pesananku di mana ya',
            'resi saya yang mana', 'mohon bantuan lacak', 'urgent paket', 'perlu info paket', 'paket urgent',
            'barang mendesak', 'kapan nyampai paling cepat', 'status real time', 'lacak langsung', 'cek langsung',
            'info langsung', 'posisi terkini paket', 'barangku posisi', 'info terkini barang', 'update terbaru pengiriman',
            'lacak status pengiriman', 'cek status pengiriman saya', 'dimana sekarang', 'paketku berhenti', 'pengiriman macet',
            'kenapa belum bergerak', 'status kiriman', 'barang dikirim', 'cek barang dikirim', 'lacak barang dikirim',
            'info barang dikirim', 'sudah keluar', 'kapan bisa ambil', 'ambil paket', 'jemput paket', 'pickup paket',
            'di mana saya bisa ambil', 'sudah masuk belum', 'cek data kiriman', 'detail kiriman', 'perkiraan tiba',
            'cek estimasi tiba', 'kapan sampai rumah', 'kapan tiba di rumah', 'resi pengiriman', 'nomor pengiriman',
            'kode pengiriman', 'identitas pengiriman', 'cek id', 'lacak id', 'info id', 'gimana perkembangan',
            'perkembangan pengiriman', 'pengiriman progres', 'progres pengiriman', 'status progres', 'gimana kabarnya paket',
            'paketku sehat', 'aman ga', 'paketku aman', 'paket ga jelas', 'status ga jelas', 'infoin dong', 'update dong',
            'ini paket saya yang mana', 'paket apa ini', 'detail order', 'detail barang', 'detail pesanan', 'info pesanan lengkap',
            'update detail', 'cek info lengkap', 'lacak detail', 'barang udah sampai belum', 'paket udah sampai belum',
            'udah diterima belum', 'udah di tangan belum', 'barang saya sudah sampai', 'paket saya sudah sampai',
            'pengiriman sudah sampai', 'kiriman sudah sampai', 'paket ini sudah sampai', 'barang ini sudah sampai',
            'sudah sampai tujuan', 'sampai alamat', 'kapan diantarkan', 'kapan diantar', 'diantar kapan', 'jadwal antar',
            'jadwal pengiriman', 'jadwal sampai', 'cek jadwal', 'info jadwal', 'kurir nganter kapan', 'kapan mau diantar',
            'paket saya mau diantar kapan', 'barang saya mau diantar kapan', 'dikirim pakai apa', 'ekspedisi yang dipakai',
            'jasa kirimnya apa', 'nama ekspedisi', 'nama kurir', 'kurir yang bawa', 'siapa yang kirim', 'pengirimnya siapa',
            'paket tidak bergerak', 'status tidak berubah', 'stuck di mana', 'ada di kota mana', 'sedang di kota mana',
            'transaksi pengiriman', 'riwayat pengiriman', 'histori pengiriman', 'data pengiriman', 'daftar pengiriman',
            'cek data pengiriman', 'lacak data pengiriman', 'info data pengiriman', 'sudah dikirimkan', 'barang sudah dikirimkan',
            'paket sudah dikirimkan', 'sudah dalam proses pengiriman', 'dalam proses kirim', 'proses kirim', 'barang proses kirim',
            'paket proses kirim', 'status proses', 'lagi diproses', 'sedang diproses', 'sudah di sortir', 'lagi di sortir',
            'proses sortir', 'sortir paket', 'sortir barang', 'cek sortir', 'lacak sortir', 'info sortir', 'paket di gudang',
            'barang di gudang', 'di gudang mana', 'masih di gudang', 'belum keluar gudang', 'kapan keluar gudang',
            'info gudang', 'cek gudang', 'lacak gudang', 'barang saya di gudang', 'paket saya di gudang', 'paket terlama',
            'barang terlama', 'pengiriman terlama', 'kok lama banget', 'pengiriman lama', 'barang lama', 'paket lama',
            'pengecekan paket', 'pengecekan barang', 'pengecekan pesanan', 'cek cek', 'tracking tracking', 'lacak lacak',
            'status status', 'bagaimana status', 'statusnya gimana', 'ceknya gimana', 'lacaknya gimana', 'infonya gimana',
            'paketku kok gitu', 'barangku kok gitu', 'kenapa paket saya', 'kenapa barang saya', 'ada apa dengan paket',
            'ada apa dengan barang', 'masalah pengiriman', 'kendala pengiriman', 'paket ada kendala', 'barang ada kendala',
            'bisa bantu cek', 'bisakah cek', 'tolong cekin', 'lacakin dong', 'cek dong', 'infoin dong', 'updatein dong',
            'paketnya sampe', 'barangnya sampe', 'sampenya kapan', 'tiba nya kapan', 'estimasi sampainya', 'perkiraan sampainya',
            'sampainya kira-kira kapan', 'kira-kira sampai kapan', 'estimasi waktu', 'waktu perkiraan', 'durasi pengiriman',
            'berapa hari lagi', 'sampai berapa hari', 'butuh berapa hari', 'lama hari', 'hari pengiriman',
            'cek di mana ya', 'bisa cek di mana', 'portal lacak', 'halaman lacak', 'link lacak', 'situs lacak',
            'aplikasi lacak', 'fitur lacak', 'menu lacak', 'opsi lacak', 'opsi lacak pesanan', 'opsi lacak barang',
            'klik lacak', 'tap lacak', 'pilih lacak', 'bagian lacak', 'cari lacak', 'gunakan lacak', 'akses lacak',
            'masuk lacak', 'lihat lacak', 'display lacak', 'perlihatkan lacak', 'tampilkan lacak', 'data lacak',
            'hasil lacak', 'info lacak', 'log lacak', 'catatan lacak', 'history lacak', 'arsip lacak', 'data historis lacak',
            'paket sudah tiba', 'barang sudah tiba', 'pengiriman sudah tiba', 'sudah sampai di tujuan', 'status selesai',
            'pengiriman selesai', 'paket selesai', 'barang selesai', 'sudah diterima pelanggan', 'sudah di tangan pelanggan',
            'diterima oleh', 'siapa yang menerima', 'penerima paket', 'paket diterima siapa', 'bukti terima', 'foto terima',
            'tanda tangan terima', 'paket gagal kirim', 'pengiriman gagal', 'barang gagal kirim', 'kenapa gagal kirim',
            'alasan gagal kirim', 'paket retur', 'barang retur', 'dikembalikan', 'pengembalian barang', 'alamat tidak ditemukan',
            'penerima tidak dikenal', 'alamat tidak lengkap', 'nomor telepon tidak aktif', 'sudah dicoba kirim',
            'percobaan pengiriman', 'gagal coba kirim', 'kirim ulang', 'jadwal ulang', 'reschedule kirim', 'paket hilang',
            'barang hilang', 'klaim hilang', 'paket rusak', 'barang rusak', 'klaim rusak', 'paket pecah', 'barang pecah',
            'klaim pecah', 'paket basah', 'barang basah', 'klaim basah', 'paket cacat', 'barang cacat', 'klaim cacat',
            'komplain paket', 'komplain barang', 'keluhan paket', 'keluhan barang', 'masalah paket', 'masalah barang',
            'paketku bermasalah', 'barangku bermasalah', 'paket tidak sesuai', 'barang tidak sesuai', 'isi paket tidak sesuai',
            'jumlah tidak sesuai', 'paket kurang', 'barang kurang', 'klaim kurang', 'isi kurang', 'paket lebih', 'barang lebih',
            'klaim lebih', 'isi lebih', 'paket salah kirim', 'barang salah kirim', 'salah alamat', 'tukar paket', 'tukar barang',
            'paket tertukar', 'barang tertukar', 'perlu bantuan', 'butuh bantuan', 'bantuan lacak', 'bantuan pengiriman',
            'customer service', 'CS', 'bantuan CS', 'chat CS', 'hubungi CS', 'telepon CS', 'kontak CS', 'nomor CS',
            'email CS', 'live chat', 'agen CS', 'customer support', 'pusat bantuan', 'FAQ pengiriman', 'pertanyaan pengiriman',
            'bantuan umum', 'info umum', 'pertanyaan umum', 'solusi pengiriman', 'tips pengiriman', 'layanan pengiriman',
            'opsi pengiriman', 'jenis pengiriman', 'paket instan', 'paket sameday', 'paket reguler', 'paket kargo',
            'paket express', 'pengiriman instan', 'pengiriman sameday', 'pengiriman reguler', 'pengiriman kargo',
            'pengiriman express', 'cek instan', 'lacak instan', 'info instan', 'cek sameday', 'lacak sameday', 'info sameday',
            'cek reguler', 'lacak reguler', 'info reguler', 'cek kargo', 'lacak kargo', 'info kargo', 'cek express',
            'lacak express', 'info express', 'tarif pengiriman', 'ongkir', 'biaya kirim', 'ongkos kirim', 'hitung ongkir',
            'estimasi ongkir', 'promo ongkir', 'gratis ongkir', 'diskon ongkir', 'pengembalian dana', 'dana kembali',
            'refund', 'retur', 'dana retur', 'proses retur', 'status retur', 'cek retur', 'info retur', 'dana refund',
            'proses refund', 'status refund', 'cek refund', 'info refund', 'dana pengembalian', 'proses pengembalian',
            'status pengembalian', 'cek pengembalian', 'info pengembalian', 'garansi pengiriman', 'asuransi pengiriman',
            'klaim asuransi', 'premi asuransi', 'syarat klaim', 'ketentuan pengiriman', 'kebijakan pengiriman',
            'aturan pengiriman', 'aturan toko', 'syarat dan ketentuan', 'S&K pengiriman', 'peraturan', 'prosedur pengiriman',
            'cara kirim', 'metode kirim', 'pilihan kirim', 'kirim via', 'kirim pakai', 'kirim lewat', 'kurir favorit',
            'rekomendasi kurir', 'kurir terbaik', 'pengiriman cepat', 'pengiriman murah', 'pengiriman aman',
            'paket aman', 'barang aman', 'kirim aman', 'resi tidak ditemukan', 'nomor resi salah', 'resi invalid',
            'resi tidak valid', 'cek ulang resi', 'pastikan resi', 'resi udah bener', 'nomor resi benar', 'bukti pembayaran',
            'invoice', 'faktur', 'bukti transaksi', 'nota', 'screenshot pembayaran', 'resi pembayaran', 'bukti transfer',
            'detail pembayaran', 'bukti order', 'bukti pesanan', 'tanggal order', 'waktu order', 'jam order', 'tanggal kirim',
            'waktu kirim', 'jam kirim', 'estimasi tanggal', 'estimasi jam', 'perkiraan tanggal', 'perkiraan jam',
            'jam berapa sampai', 'tanggal berapa sampai', 'hari apa sampai', 'paket datang hari apa', 'barang datang hari apa',
            'libur pengiriman', 'tanggal merah', 'hari besar', 'cuti bersama', 'operasional kurir', 'jam operasional',
            'layanan 24 jam', 'buka tutup', 'jam kerja', 'alamat penerima', 'alamat lengkap', 'nama penerima',
            'nomor telepon penerima', 'kontak penerima', 'data penerima', 'info penerima', 'alamat pengirim', 'nama pengirim',
            'nomor telepon pengirim', 'kontak pengirim', 'data pengirim', 'info pengirim', 'ubah alamat', 'revisi alamat',
            'ganti alamat', 'edit alamat', 'koreksi alamat', 'perubahan alamat', 'pengubahan alamat', 'alamat baru',
            'alamat tujuan', 'alamat tujuan paket', 'alamat tujuan barang', 'alamat dikirim', 'tujuan pengiriman',
            'tujuan barang', 'tujuan paket', 'titik pengiriman', 'lokasi pengiriman', 'lokasi penerima', 'lokasi pengirim',
            'koordinat pengiriman', 'peta pengiriman', 'lokasi peta', 'map pengiriman', 'lokasi google maps',
            'peta google maps', 'lokasi real time', 'posisi real time', 'tracking real time', 'lacak real time',
            'barang live', 'paket live', 'info live', 'perkembangan live', 'status live', 'live tracking', 'live update',
            'pengiriman live', 'barang bergerak', 'paket bergerak', 'bergerak ke mana', 'arah paket', 'rute paket',
            'jalur paket', 'track jalur', 'cek jalur', 'info jalur', 'rute pengiriman', 'rute kurir', 'jalur kurir',
            'progress jalur', 'lokasi transit', 'transit barang', 'transit paket', 'di mana transit', 'transit di kota mana',
            'transit terakhir', 'paket di gudang sortir', 'barang di gudang sortir', 'gudang sortir', 'pusat sortir',
            'paket di hub', 'barang di hub', 'di hub mana', 'pusat distribusi', 'distribusi paket', 'distribusi barang',
            'stasiun pengiriman', 'kantor cabang', 'agen terdekat', 'ambil di agen', 'ambil di kantor', 'ambil di kurir',
            'bisa diambil', 'siap diambil', 'menunggu diambil', 'pickup point', 'drop point', 'titik pengambilan',
            'pengambilan barang', 'pengambilan paket', 'info pengambilan', 'lokasi pengambilan', 'jam pengambilan',
            'syarat pengambilan', 'cara pengambilan', 'pengambilan sendiri', 'ambil sendiri', 'jemput sendiri',
            'kapan bisa dijemput', 'kapan siap dijemput', 'jemput di mana', 'barang sudah sampai kurir', 'paket sudah sampai kurir',
            'kurir sudah menerima', 'sudah serah terima', 'kurir otw', 'kurir dalam perjalanan', 'kurir sedang mengantar',
            'kurir sedang mengirim', 'kurir sedang mendistribusi', 'kurir di area', 'kurir dekat', 'kurir sudah sampai',
            'kurir di lokasi', 'kurir di alamat', 'kurir belum sampai', 'kurir masih jauh', 'kurir macet', 'kurir kendala',
            'kurir ada masalah', 'telpon kurir', 'chat kurir', 'nomor kurir', 'kurir bisa dihubungi', 'kontak kurir',
            'resi tidak valid', 'resi salah', 'resi palsu', 'resi tidak terdaftar', 'resi tidak dikenali', 'cek keabsahan resi',
            'verifikasi resi', 'cek resi di sistem', 'resi tidak ditemukan di sistem', 'resi baru', 'resi lama', 'resi lama saya',
            'resi yang mana', 'punya resi', 'butuh resi', 'minta resi', 'cara dapat resi', 'bagaimana dapat resi',
            'resi dari mana', 'nomor resi saya', 'resi saya yang benar', 'resi order', 'resi pembelian', 'resi pengiriman',
            'resi pengembalian', 'resi penukaran', 'resi klaim', 'resi khusus', 'resi internasional', 'resi domestik',
            'tracking internasional', 'tracking domestik', 'paket luar negeri', 'barang luar negeri', 'kirim luar negeri',
            'bea cukai', 'masalah bea cukai', 'pajak impor', 'dokumen pengiriman', 'kelengkapan dokumen', 'izin impor',
            'paket ditahan', 'barang ditahan', 'kenapa ditahan', 'ditahan di mana', 'kapan rilis', 'rilis paket',
            'rilis barang', 'penahanan paket', 'penahanan barang', 'penahanan pengiriman', 'paket di karantina',
            'barang di karantina', 'karantina paket', 'karantina barang', 'aturan karantina', 'proses karantina'
        ],
        responses: [
            "Oke, mau lacak pesananmu? Gampang banget kok! Tinggal 'login' aja ke 'akunmu', terus cari 'Nomor resi' di halaman 'Riwayat Pesanan' atau 'Pesanan Saya'. Nanti keliatan deh semua detailnya, dijamin sat-set!",
            "Kepoin status paketmu? Santuy, kamu tinggal 'masuk ke akun' kamu, abis itu cus ke 'Riwayat Pesanan' atau 'Pesanan Saya'. Di situ ada semua info plus 'nomor resi' buat kamu pantau pengirimannya. Auto update!",
            "Penting nih gaes! Buat ngecek keberadaan paketmu, 'nomor resi' udah nangkring di halaman 'Riwayat Pesanan' atau 'Pesanan Saya' setelah kamu 'login'. Dari situ, kamu bisa mantau perjalanan paketmu deh. Dijamin nggak nyasar!",
            "Bingung paketmu di mana? Tenang, cukup 'login' ke 'akunmu' ya! Nanti kamu bakal nemu 'nomor resi' di bagian 'Pesanan Saya' atau 'Histori Pembelian'. Itu kuncinya buat ngecek status pengirimanmu. Selamat melacak!",
            "Lagi nungguin paket ya? Yuk, intip statusnya! Kamu bisa 'cek nomor resi' di 'Riwayat Pesanan' yang ada di 'akunmu' setelah 'login'. Dari situ, kamu bisa tahu update terbaru paketmu udah sampai mana. Gampang kan?",
            "Eh, mau tau barangmu udah nyampe mana? Cek aja langsung di 'akunmu' setelah 'login'. Cari bagian 'Pesanan Saya' atau 'Riwayat Pembelian', terus temuin 'nomor resi' orderanmu. Dijamin langsung keliatan!",
            "Paketmu masih galau di jalan? Udah dong, jangan pusing! Langsung aja 'login' ke 'akun' kamu. Terus, ke 'Pesanan Saya' atau 'Riwayat Transaksi' buat dapetin 'nomor resi' dan info terbaru status pengiriman. Biar nggak penasaran lagi!",
            "Yuk, buruan cek! Kalau mau tahu 'status orderan' kamu, caranya simpel: 'login' ke 'akunmu', terus klik 'Pesanan Saya'. Nanti kamu bisa lihat 'nomor resi' dan 'update pengiriman' terakhir. Gak pake ribet!",
            "Penasaran paketmu udah sampai mana? Tinggal 'login' aja ke 'akun' kamu. Abis itu, cari 'nomor resi' di halaman 'Riwayat Pesanan' atau 'Pesanan Saya'. Kamu bisa langsung tahu info lengkapnya dari situ. Gas!"
        ]
    },
    {
        keywords: [
            'cod', 'bayar di tempat', 'cash on delivery', 'bayar langsung', 'bisa bayar di rumah', 'bayar cash',
            'bayar pas sampe', 'bisa cod', 'bayar ketika sampai', 'bayar tunai', 'terima cod', 'cod bisa',
            'cash on delivery bisa', 'pembayaran di tempat',
            // --- Penambahan kata baru "COD" ---
            'apakah bisa cod', 'apakah cod', 'cod ya', 'cod aja', 'boleh cod', 'bisakah cod', 'bayar ditempat',
            'bayar di rumah aja', 'bayar pas barang datang', 'bayar pas nyampe', 'pembayaran cod', 'sistem cod',
            'codan', 'bisakah bayar di tempat', 'bayar langsung di rumah', 'bisa bayar saat diterima',
            'terima pembayaran di tempat', 'cod in', 'cod dong', 'bisa ga cod', 'apakah melayani cod', 'pakai cod',
            'cara cod', 'gimana cod', 'cod apa', 'cod gak ya', 'bayar di lokasi', 'bayar pas barang dateng',
            'bayar di penerima', 'metode cod', 'cod bs', 'cod bisa ga', 'cod bisa gak', 'cod ga', 'cod ngak',
            'cod apa nggak', 'bayar di tpt', 'byr di tmpt', 'byr lgsg', 'byr cash', 'byr pas nyampe', 'bs cod',
            'bs bayar di tempat', 'trima cod', 'trima byr di tempat', 'cash on delivery bisa', 'cod aja deh',
            'cod ajah', 'cod aja ya', 'cod aja kak', 'cod aja min', 'bisa cod kah', 'cod an', 'cod ga sih',
            'cod ga ya', 'cod gitu', 'cod aja gimana', 'byr di rmh', 'bayar d rmh', 'byr dtempat', 'bayar dtempat',
            'bayar lgsg', 'bayar d lokasi', 'bayar d penerima', 'bayar di penerima', 'byr smpe', 'bayar sampai',
            'bayar sampe rumah', 'bayar pas brg dtg', 'byr pas paket dtg', 'byr waktu nyampe', 'bayar pas terima',
            'bayar stlh trima', 'byr setelah terima', 'bayen', 'byar', 'bayarku', 'byar d tmpt', 'bayar d tmpt',
            'cod kan', 'cod kan ya', 'cod kan kak', 'cod kan min', 'cod kan bos', 'cod kan sist', 'cod in dong',
            'cod in kak', 'cod in min', 'cod in lah', 'cod in ya', 'cod aja dong', 'cod gak nih', 'bisa cod ya',
            'cod bisakah'
        ],
        response: "COD bisa kok di beberapa daerah! Nanti pas checkout, coba dicek aja ya biar makin gampang transaksinya."
    },
    {
        keywords: [
            'stok', 'ketersediaan', 'sisa stok', 'masih ada', 'ready stock', 'ready', 'ada gak barangnya',
            'restock kapan', 'ada ready', 'stok barang', 'barang ready', 'ready ga', 'stok habis', 'kapan restock',
            'ada stok',
            // --- Penambahan kata baru "Stok" ---
            'stok ada', 'stok barang ada', 'ready gak', 'masih ready', 'ada brg', 'brg ready', 'restok', 'ketersediaan stok',
            'brg ada ga', 'stoknya gimana', 'masih tersedia', 'brp stok', 'sisa brp'
        ],
        response: `Barangnya 'ready stock' kok! Langsung checkout aja biar nggak kehabisan!`
    },
    {
        keywords: [
            'ukuran', 'lebar', 'panjang', 'dimensi', 'size', 'ukurannya berapa', 'detail ukuran', 'size chart',
            'ukuran baju', 'ukuran sepatu', 'tabel ukuran', 'detail size', 'ukuran apa aja',
            // --- Penambahan kata baru "Ukuran" ---
            'size brp', 'detail size', 'ukuran nya', 'ukuran produk', 'tabel size', 'chart ukuran', 'size apa aja',
            'ukuran yg tersedia', 'size yg tersedia', 'ukurannya gimana'
        ],
        response: "Untuk ukuran/dimensi produk ada di deskripsi produk ya. Scroll aja ke bawah buat lihat detailnya!"
    },
    {
        keywords: [
            'garansi', 'klaim', 'garansinya gimana', 'klaim garansi', 'garansi produk', 'garansi berapa lama',
            'cara klaim garansi', 'ada garansi', 'garansi resmi', 'masa garansi',
            // --- Penambahan kata baru "Garansi" ---
            'garansi brp lama', 'cara klaim garansi gmn', 'ada garansi ga', 'garansi nya', 'garansi resminya',
            'garansi nya gimana', 'claim garansi', 'garansi brp'
        ],
        response: "Info garansi bisa dilihat di deskripsi produk. Hubungi penjual untuk klaim ya. Tenang aja, kami pasti bantu!"
    },
    {
        keywords: [
            'ori', 'original', 'asli', 'kw', 'asli gak', 'original gak', 'ori apa kw', 'produk ori', 'barang asli',
            'originalitas', 'asli bukan kw', 'asli atau palsu',
            // --- Penambahan kata baru "Originalitas" ---
            'ori ga', 'asli ga', 'bukan kw', 'original bkn', 'produk asli', 'ini ori', 'ini asli', 'jamin asli',
            'original kah'
        ],
        response: "Kami jamin semua produk yang kami jual **asli** dan berkualitas kok. Nggak ada KW-KW-an di sini!"
    },
    {
        keywords: [
            'kirim', 'pengiriman', 'kurir', 'same day', 'instant delivery', 'lama pengiriman', 'barang dikirim',
            'belum sampai', 'cek pengiriman', 'estimasi kirim', 'paket nyangkut', 'kirim kapan', 'pengiriman berapa lama',
            'kurir apa', 'expedisi', 'pengiriman cepat', 'kirimnya kapan', 'paket belum nyampe', 'barang belum nyampe',
            'jadwal kirim', 'dikirim kapan', 'kapan sampai',
            // --- Penambahan kata baru "Pengiriman" ---
            'estimasi sampai', 'kirimnya brp lama', 'lama kirim', 'kapan dikirim', 'paket lama', 'brg blm smpe',
            'kirim brp hari', 'paket ga gerak', 'expedisi apa', 'kirim pke apa', 'paketku blm nyampe'
        ],
        response: "Pengiriman diatur setelah pembayaranmu diverifikasi ya. Untuk estimasi waktu dan pilihan kurir, nanti ada di halaman checkout kok. Kalau barang belum sampai, coba cek di 'Pesanan Saya' atau kontak kami biar dibantu!"
    },
    {
        keywords: [
            'ongkir', 'biaya kirim', 'berapa ongkir', 'total ongkir', 'mahal gak ongkirnya', 'cek ongkir',
            'hitung ongkir', 'ongkos kirim', 'ongkirnya berapa', 'ongkir ke', 'ongkir murah', 'tarif ongkir',
            // --- Penambahan kata baru "Ongkir" ---
            'brp ongkir', 'ongkir brp', 'ongkirnya mahal', 'ongkir murah gak', 'cek biaya kirim', 'ongkir ke sini',
            'ongkir wilayah', 'ongkir total', 'ongkir item ini'
        ],
        response: "Ongkir bakal dihitung otomatis pas kamu checkout, tergantung lokasi tujuan dan berat/volume produk. Ada pilihan jasa kirim juga kok, bisa disesuaikan sama kebutuhanmu!"
    },
    {
        keywords: [
            'harga nett', 'nego', 'diskon', 'promo', 'potongan harga', 'voucher', 'beli banyak', 'ada diskon gak',
            'harga pas', 'bisa kurang', 'ada promo apa', 'harga fix', 'harga mati', 'diskonnya berapa',
            'ada voucher', 'promo terbaru', 'harga paling murah',
            // --- Penambahan kata baru "Harga & Promo" ---
            'nett price', 'bisa nego', 'ada diskon', 'ada promo', 'voucher apa', 'harga diskon', 'harga potongan',
            'promo apa', 'diskon brp', 'promo skrg', 'harga spesial'
        ],
        response: "Harga yang tertera itu udah 'harga nett' ya, Kak. Jadi belum bisa nego. Tapi siapa tahu nanti ada promo spesial kalau beli banyak, pantau terus ya!"
    },
    {
        keywords: [
            'bonus', 'free gift', 'hadiah', 'dapet apa aja', 'ada bonusnya gak', 'dapat bonus', 'bonus apa',
            'gratisan', 'freebies', 'ada hadiah',
            // --- Penambahan kata baru "Bonus/Hadiah" ---
            'dpt bonus', 'dpt hadiah', 'free gift apa', 'ada gratisan', 'dapet free', 'gratis apa', 'bonus nya'
        ],
        response: "Kadang kami ada promo bonus atau free gift lho! Biasanya info promonya bakal muncul di halaman produk atau saat checkout. Jangan sampai kelewatan ya!"
    },
    {
        keywords: [
            'tukar ukuran', 'tuker ukuran', 'salah ukuran', 'tuker barang', 'kekecilan', 'kegedean', 'gak pas',
            'retur ukuran', 'ganti ukuran', 'ukuran tidak sesuai', 'bisa tukar', 'balik barang',
            // --- Penambahan kata baru "Tukar Ukuran/Barang" ---
            'bisa tuker', 'salah size', 'mau tukar', 'ganti size', 'ukuran kekecilan', 'ukuran kegedean',
            'tuker size', 'retur size', 'bisa ganti'
        ],
        response: "Bisa kok! Kalau ukurannya salah atau nggak pas, kamu bisa ajukan penukaran produk. Cek detail syarat dan ketentuannya di halaman 'Kebijakan Pengembalian & Penukaran' ya. Jangan khawatir!"
    },
    {
        keywords: [
            'rusak', 'cacat', 'barang cacat', 'error', 'pecah', 'retur', 'defect', 'barang reject',
            'rusak pas dateng', 'cacat dikit', 'barang rusak', 'ada cacat', 'error produk', 'produk pecah',
            'pengembalian barang rusak',
            // --- Penambahan kata baru "Rusak/Cacat" ---
            'brg rusak', 'produk rusak', 'ada yg cacat', 'barang defek', 'reject item', 'pecah nih', 'rusak nih',
            'retur brg', 'claim rusak', 'brg error'
        ],
        response: "Kalau barang yang kamu terima rusak atau cacat, langsung aja ajukan pengembalian barang via 'Pesanan Saya' dengan sertakan bukti foto/video. Kami pasti bantu kok!"
    },
    {
        keywords: [
            'transfer belum proses', 'belum diproses', 'sudah bayar', 'kok belum diproses', 'bukti transfer',
            'pembayaran belum masuk', 'dana belum masuk', 'udah bayar kok', 'konfirmasi pembayaran', 'kirim bukti bayar',
            // --- Penambahan kata baru "Pembayaran Belum Diproses" ---
            'transfer blm masuk', 'pembayaran blm di proses', 'uda byr', 'dana blm masuk', 'byr blm di cek',
            'konfirm byr', 'kirim bukti', 'bukti transfer mana', 'transfer sukses', 'order blm di proses'
        ],
        response: "Udah transfer tapi belum diproses? Aduh, maaf banget ya! Langsung kirim bukti transfer kamu ke kami via chat atau email 'snrpetir@gmail.com' ya. Nanti langsung kami cek dan proses secepatnya!"
    },
    {
        keywords: [
            'pilih warna', 'pilih model', 'warna apa', 'model apa', 'mau warna ini', 'bisa custom warna',
            'variasi warna', 'pilihan warna', 'warna tersedia', 'model tersedia', 'ada warna lain',
            // --- Penambahan kata baru "Pilihan Warna/Model" ---
            'warna apa aja', 'model apa aja', 'warna yg ada', 'model yg ada', 'variasi nya', 'pilihan nya',
            'ada warna', 'ada model', 'warna custom'
        ],
        response: "Bisa dong! Kalau ada pilihan warna/model, nanti pas kamu klik produknya, opsi itu bakal muncul kok. Tinggal pilih aja sesuai selera!"
    },
    {
        keywords: [
            'foto asli', 'real pic', 'realpict', 'cek foto asli', 'ada foto aslinya gak', 'foto produk asli',
            'gambar asli', 'real foto', 'pic asli',
            // --- Penambahan kata baru "Foto Asli" ---
            'realpic', 'real pict', 'ada foto real', 'foto asli produk', 'gambar real', 'pic real'
        ],
        response: "Foto produk yang kami tampilkan itu **foto asli** (real pic) kok. Jadi apa yang kamu lihat itu yang bakal kamu dapetin!"
    },
    {
        keywords: [
            'lokasi', 'toko fisik', 'bumdes', 'kontak bumdes', 'nomor telepon', 'alamat lengkap', 'telepon bumdes',
            'nomor telpon', 'kontak kami', 'lokasi di mana', 'nomor bumdes', 'basecamp', 'markas',
            'alamat toko', 'peta lokasi', 'dimana lokasi', 'alamat kantor',
            // --- Penambahan kata baru "Lokasi/Kontak BUMDes" ---
            'alamat bumdes', 'lokasi toko', 'no telp', 'tlp bumdes', 'alamat dmn', 'kantor dmn', 'map lokasi',
            'lokasi asli', 'tempat bumdes', 'kontak bumdes'
        ],
        response: `Tentu, untuk info lokasi toko fisik "${bumdesNama}", kamu bisa kunjungi alamat: "${bumdesAlamat}". Kalau mau hubungi kami, bisa lewat nomor telepon ini: "${bumdesKontak}". Kami tunggu kedatanganmu ya!`
    },
    {
        keywords: [
            'hubungi penjual', 'kontak penjual', 'chat penjual', 'telpon penjual', 'nomor penjual', 'ngobrol sama penjual',
            'adminnya siapa', 'kontak admin', 'telp admin', 'bicara sama penjual', 'chat sama penjual', 'telepon penjual',
            'nomor telepon penjual', 'nanya penjual', 'ngobrol penjual', 'admin penjual', 'cs penjual', 'customer service penjual',
            'kontak seller', 'chat seller', 'telpon seller', 'nomor seller', 'seller nya siapa', 'kontak owner', 'chat owner',
            'owner nya siapa', 'hubungi cs', 'chat cs', 'nomor cs', 'adminya', 'adminnya', 'admin dong', 'chat dong',
            'kontak dong', 'telpon dong', 'info kontak penjual', 'cara hubungi penjual', 'gimana hubungi penjual',
            'pengen ngobrol sama penjual', 'langsung chat penjual', 'mau nanya penjual', 'ada nomor penjual', 'nomer penjual',
            'penjualnya siapa', 'adminnya mana', 'cs nya mana', 'chat dengan penjual', 'bicara dengan penjual', 'tanya penjual',
            'tanya admin', 'kontak bantuan', 'customer support', 'support center', 'telepon support', 'nomor support',
            'tanya tanya penjual', 'tanya tanya admin', 'tanya tanya cs', 'cara chat penjual', 'gimana chat penjual',
            'boleh chat penjual', 'bisa telpon penjual', 'nomor yang bisa dihubungi', 'hubungi langsung', 'kontak langsung',
            'chat langsung', 'telpon langsung', 'penjual bisa dihubungi', 'admin bisa dihubungi', 'cs bisa dihubungi',
            'mau tanya penjual', 'mau tanya admin', 'mau tanya cs', 'ada kontak', 'info kontak', 'kontak yang bisa dihubungi',
            'nomor yang bisa dihubungi', 'dimana hubungi penjual', 'gimana cara kontak', 'cara kontak penjual',
            'hubungi kami', 'kontak kami', 'chat kami', 'telpon kami', 'nomor kami', 'kami bisa dihubungi',
            'bagaimana cara menghubungi', 'mau bicara sama', 'bisakah menghubungi', 'kontak person', 'contact person', 'CP',
            'CP nya', 'nomor CP', 'adminnya ready', 'adminnya online', 'ada admin', 'bisa nanya', 'nanya nanya', 'mau nanya',
            'boleh nanya', 'tanya tanya', 'tanya in', 'tanya dong', 'tanyain', 'tanyain dong', 'tanyain aja', 'tanya kak',
            'tanya min', 'tanya bos', 'tanya sist', 'tanya gan', 'chat kak', 'chat min', 'chat bos', 'chat sist', 'chat gan',
            'kontak kak', 'kontak min', 'kontak bos', 'kontak sist', 'kontak gan', 'telpon kak', 'telpon min', 'telpon bos',
            'telpon sist', 'telpon gan', 'halo admin', 'hallo admin', 'hai admin', 'panggil admin', 'panggil CS', 'panggil seller',
            'panggil penjual', 'minta nomor', 'minta kontak', 'minta telpon', 'minta chat', 'bisakah saya menghubungi',
            'bisa kontak', 'bisa chat', 'bisa telpon', 'bisa telepon', 'kontak service', 'service center', 'pusat bantuan',
            'pusat dukungan', 'layanan pelanggan', 'customer care', 'hotline', 'nomor hotline', 'call center', 'nomor call center',
            'chat now', 'chat sekarang', 'kontak sekarang', 'hubungi sekarang', 'admin respon', 'bales chat', 'respon admin',
            'balasan admin', 'chat cepat', 'respon cepat', 'bisa di chat', 'bisakah dihubungi', 'bisakah di chat',
            'bisakah ditelepon', 'bisakah ditelpon', 'ada yang jaga', 'ada yang online', 'admin ada', 'cs ada', 'seller ada',
            'penjual ada', 'kontak wa', 'nomor wa', 'chat wa', 'hubungi wa', 'wa nya berapa', 'ada wa', 'whatsapp',
            'nomor whatsapp', 'chat whatsapp', 'hubungi whatsapp', 'cara nanya', 'nanya doang', 'mau nanya doang',
            'mau nanya nanya', 'bisa dihubungi lewat apa', 'kontak lewat apa', 'chat lewat apa', 'telepon lewat apa',
            'bisa di WA', 'bisa chat WA', 'admin cepat respon', 'cs cepat respon', 'seller cepat respon', 'penjual cepat respon',
            'admin fast respon', 'cs fast respon', 'seller fast respon', 'penjual fast respon', 'fast respon', 'fast respond',
            'respon cepat', 'respon kilat', 'balas cepat', 'balas kilat', 'admin ready', 'cs ready', 'seller ready',
            'penjual ready', 'admin aktif', 'cs aktif', 'seller aktif', 'penjual aktif', 'bisa langsung chat',
            'langsung hubungi', 'langsung kontak', 'langsung telepon', 'langsung telpon', 'minta bantuan admin',
            'minta bantuan cs', 'minta bantuan seller', 'minta bantuan penjual', 'bagaimana menghubungi', 'cara menghubungi',
            'bisakah admin dihubungi', 'bisakah cs dihubungi', 'bisakah seller dihubungi', 'bisakah penjual dihubungi',
            // --- Penambahan variasi "langsung" dan singkatan lainnya ---
            'lgsg hubungi', 'lgsg kontak', 'lgsg chat', 'lgsg telpon', 'lgsg telepon', 'lgsg wa', 'lngsng hubungi',
            'lngsng kontak', 'lngsng chat', 'lngsng telpon', 'lngsng telepon', 'lngsng wa', 'langsng hubungi',
            'langsng kontak', 'langsng chat', 'langsng telpon', 'langsng telepon', 'langsng wa', 'contact now',
            'call now', 'chat admin', 'chat cs', 'chat seller', 'chat penjual', 'telpon admin', 'telpon cs',
            'telpon seller', 'telpon penjual', 'nomor darurat', 'kontak darurat', 'hubungi darurat', 'help me'
        ],
        response: "Buat nanya-nanya ke penjual, kamu bisa langsung chat lewat tombol 'Chat Penjual' yang ada di halaman detail produk, atau mampir ke tokonya via tombol 'Kunjungi Toko'."
    },
    {
        keywords: [
            'alamat', 'ubah alamat', 'ganti alamat', 'salah alamat', 'alamatnya salah', 'edit alamat',
            'perbarui alamat', 'alamat pengiriman', 'ubah alamat kirim',
            // --- Penambahan kata baru "Alamat" ---
            'ganti alamat kirim', 'edit alamat pengiriman', 'salah alamat nih', 'perbarui alamat saya',
            'ubah alamat pengiriman', 'alamat baru'
        ],
        response: "Kamu bisa ubah alamat di pengaturan akun bagian 'Alamat Pengiriman'."
    },
    {
        keywords: [
            'password', 'reset', 'lupa', 'sandi', 'lupa sandi', 'ganti sandi', 'lupa password', 'reset password',
            'ubah password', 'sandi baru',
            // --- Penambahan kata baru "Password/Sandi" ---
            'lupa pw', 'ganti pw', 'reset pw', 'password lupa', 'sandi lupa', 'pw baru', 'ubah sandi', 'pw nya'
        ],
        response: "Klik 'Lupa Kata Sandi' di halaman login buat reset passwordmu. Nanti instruksinya kami kirim ke emailmu kok!"
    },
    {
        keywords: [
            'jam buka', 'buka jam', 'tutup jam', 'jam operasional', 'jam kerja', 'buka sampai jam berapa',
            'libur kapan', 'jam buka toko', 'jam tutup toko', 'operasional toko', 'hari libur',
            // --- Penambahan kata baru "Jam Operasional" ---
            'buka kpn', 'tutup kpn', 'jam bka', 'jam ttp', 'hari kerja', 'libur hari apa', 'buka tiap hari',
            'jam buka nya', 'jam tutup nya', 'operasionalnya'
        ],
        response: "Kami buka setiap hari Senin - Jumat pukul 08.00 - 17.00 WIB. Hari Sabtu, Minggu, dan hari libur nasional kami libur ya. Ditunggu kedatangannya!"
    },
    {
        keywords: [
            'bisnis apa', 'jual apa', 'produk apa', 'dagang apa', 'usaha apa', 'jualan apa aja', 'macam produk',
            'jenis produk', 'list produk', 'katalog produk',
            // --- Penambahan kata baru "Jenis Produk" ---
            'jual produk apa', 'bisnis nya apa', 'dagang nya apa', 'usaha nya apa', 'jual apa aja', 'macam2 produk',
            'jenis2 produk', 'daftar produk', 'katalog nya'
        ],
        response: `${bumdesNama} punya banyak banget produk, mulai dari kerajinan tangan, makanan olahan, hasil pertanian lokal, dan masih banyak lagi. Cek langsung di website kami ya, dijamin kalap!`
    },
    {
        keywords: [
            'diskon', 'promo', 'potongan harga', 'voucher', 'ada diskon gak', 'ada promo apa', 'diskon berapa',
            'promo apa aja', 'voucher diskon', 'kode promo', 'potongan harga', 'promosi',
            // --- Penambahan kata baru "Diskon/Promo" ---
            'disk', 'promosi apa', 'kode voucher', 'promo skrg', 'ada diskon skrg', 'promo diskon', 'potongan hrg',
            'voucher apa aja', 'promo terbaru apa'
        ],
        response: "Tentu dong! Kami sering ada promo menarik. Cek aja bagian 'Promo' di website kami atau follow Instagram kami biar nggak ketinggalan info diskon terbaru!"
    },
    {
        keywords: [
            'medsos', 'instagram', 'facebook', 'tiktok', 'sosmed', 'ig', 'fb', 'tiktok', 'akun sosmed',
            'media sosial', 'follow sosmed',
            // --- Penambahan kata baru "Media Sosial" ---
            'sosial media', 'akun ig', 'akun fb', 'tiktok nya', 'link sosmed', 'follow ig', 'link instagram'
        ],
        response: "Tentu! Kami aktif di Instagram dengan akun: **@pekonsinarpetir_**. Jangan lupa follow ya buat update terbaru dan giveaway seru!"
    },
    {
        keywords: [
            'daftar', 'register', 'bikin akun', 'mau daftar', 'cara daftar', 'registrasi akun', 'buat akun',
            'daftar anggota', 'join member',
            // --- Penambahan kata baru "Daftar Akun" ---
            'bikin akun baru', 'cara register', 'daftar member', 'sign up', 'cara buat akun', 'mau registrasi'
        ],
        response: "Untuk daftar akun, kamu tinggal klik tombol 'Daftar' atau 'Register' di pojok kanan atas halaman. Isi data yang dibutuhin, terus ikutin aja instruksinya. Gampang kok!"
    },
    {
        keywords: [
            'hapus akun', 'tutup akun', 'delete akun', 'nonaktifkan akun', 'menghapus akun', 'cara hapus akun',
            'permanent delete',
            // --- Penambahan kata baru "Hapus Akun" ---
            'delete akun permanent', 'cara nonaktifkan akun', 'tutup akun saya', 'hapus akun saya', 'akun dihapus'
        ],
        response: "Untuk hapus akun, kamu bisa langsung hubungi tim layanan pelanggan kami ya. Nanti mereka bakal bantu prosesnya."
    },
    {
        keywords: [
            'reseller', 'agen', 'join reseller', 'daftar jadi reseller', 'mau jadi agen', 'jadi reseller',
            'syarat reseller', 'program reseller', 'kemitraan',
            // --- Penambahan kata baru "Reseller/Agen" ---
            'jadi agen', 'daftar reseller', 'syarat agen', 'program agen', 'buka reseller', 'buka agen',
            'gabung reseller', 'join agen', 'mau reseller', 'mau agen'
        ],
        response: "Tentu, kami membuka kesempatan buat kamu yang mau jadi reseller atau agen kami. Langsung aja hubungi kontak BUMDes kami buat info lebih lanjut soal program reseller ya, cuan menanti!"
    },
    {
        keywords: [
            'retur', 'pengembalian', 'refund', 'tukar barang', 'kebijakan retur', 'cara retur', 'pengembalian dana',
            'kembalikan barang', 'tukar produk',
            // --- Penambahan kata baru "Retur/Refund" ---
            'bisa retur', 'bisa refund', 'cara pengembalian', 'balik dana', 'kembalikan uang', 'kebijakan refund',
            'ganti barang', 'tukar produk rusak',
            // --- Penambahan variasi "kembalikan" dan lainnya ---
            'kembalikan','ngembaliin', 'kembalian', 'balikin', 'balikin barang', 'balikkin uang', 'kembalikan dana',
            'pulangin', 'pulangin barang', 'pulangin uang', 'refund barang', 'refund duit', 'refund uang',
            'kembaliin', 'kembalikan produk', 'pengembalian produk', 'retur produk', 'retur uang', 'retur dana'
        ],
        response: "Untuk kebijakan pengembalian produk, kamu bisa lihat detailnya di halaman 'Kebijakan Pengembalian & Penukaran' di website kami. Pastiin syarat dan ketentuannya terpenuhi ya, biar prosesnya lancar."
    },
    {
        keywords: [
            'promo ongkir', 'gratis ongkir', 'free ongkir', 'ongkir gratis', 'diskon ongkir', 'bebas ongkir',
            'promo biaya kirim',
            // --- Penambahan kata baru "Promo Ongkir" ---
            'ongkir free', 'ongkir gratis ada', 'ada free ongkir', 'ongkir murah', 'bebas ongkir gak', 'promo ongkir apa'
        ],
        response: "Kami kadang ada promo gratis ongkir atau diskon ongkir lho! Pantau terus pengumuman di website atau Instagram kami ya, biar nggak ketinggalan promo spesialnya."
    },
    {
        keywords: [
            'tentang kami', 'profil bumdes', 'sejarah bumdes', 'bumdes ini apa', 'apa itu bumdes', 'latar belakang bumdes',
            'visi misi bumdes',
            // --- Penambahan kata baru "Tentang BUMDes" ---
            'info bumdes', 'profil kami', 'sejarah kami', 'bumdes itu apa', 'visi misi kami', 'mengenai bumdes'
        ],
        response: `${bumdesNama} adalah badan usaha milik desa yang fokus banget sama pengembangan ekonomi lokal dan pemberdayaan masyarakat. Kami bergerak di bidang perdagangan produk-produk unggulan desa. Tujuan kami, ya biar masyarakat desa makin sejahtera lewat usaha ini.`
    },
    {
        keywords: [
            'komplain', 'pengaduan', 'ada masalah', 'ada kendala', 'gak beres', 'keluhan', 'lapor masalah',
            'butuh bantuan', 'ada yang salah',
            // --- Penambahan kata baru "Komplain/Keluhan" ---
            'ada komplain', 'mau komplain', 'lapor keluhan', 'ada masalah nih', 'bantuan masalah', 'error nih',
            'ada kendala nih', 'gak beres nih'
        ],
        response: "Kalau ada masalah atau komplain, langsung aja hubungi layanan pelanggan kami via chat ini atau nomor kontak BUMDes yang tertera. Kamu juga bisa kirim email ke 'snrpetir@gmail.com'. Kami siap bantu banget!"
    },
    {
        keywords: [
            'cabang', 'toko lain', 'ada cabang gak', 'cabang di mana', 'alamat cabang', 'toko cabang',
            // --- Penambahan kata baru "Cabang Toko" ---
            'ada toko lain', 'lokasi cabang', 'cabang ada dimana', 'toko lainnya', 'ada cabang kah'
        ],
        response: "Saat ini, lokasi utama kami ada di ${bumdesAlamat}. Untuk info cabang lain (kalau ada) bakal kami umumkan di website atau Instagram kami ya. Stay tuned!"
    },
    {
        keywords: [
            'best seller', 'favorit', 'produk paling laku', 'rekomendasi', 'paling laris', 'produk hits',
            'laris manis', 'produk unggulan', 'rekomendasi produk',
            // --- Penambahan kata baru "Best Seller" ---
            'produk best seller', 'rekomendasi produk apa', 'produk favorit', 'paling laku', 'produk hits apa',
            'yg laris', 'produk terlaris'
        ],
        response: "Produk best seller kami saat ini adalah Batik Tulis Motif 'Kembang Desa' dan keripik singkong pedas 'Nampol'. Banyak banget yang suka loh, cobain deh!"
    },
    {
        keywords: [
            'lowongan', 'kerja', 'cari kerja', 'loker', 'lowongan pekerjaan', 'butuh karyawan', 'job vacancy',
            // --- Penambahan kata baru "Lowongan Kerja" ---
            'cari loker', 'loker terbaru', 'butuh pegawai', 'lowongan apa', 'ada lowongan', 'info loker'
        ],
        response: "Informasi lowongan kerja di ${bumdesNama} bakal kami umumkan di website resmi atau Instagram kami. Pantau terus ya, siapa tahu ada posisi yang cocok buat kamu!"
    },
    {
        keywords: [
            'saran', 'kritik', 'masukan', 'ide', 'feedback', 'komentar', 'usulan',
            // --- Penambahan kata baru "Saran/Kritik" ---
            'kasih saran', 'kasih kritik', 'beri masukan', 'ada ide', 'feedback nya', 'komentar saya', 'usul'
        ],
        response: "Makasih banyak atas saran dan kritikmu! Kami sangat menghargai masukan buat perbaikan. Kamu bisa sampaikan via chat ini atau email kami di 'snrpetir@gmail.com'. Kami bakal tindak lanjuti kok!"
    },
    {
        keywords: [
            'salah pesan', 'salah order', 'ganti pesanan', 'batalin order', 'batalkan pesanan', 'ubah pesanan',
            'cancel order', 'cancel pesanan',
            // --- Penambahan kata baru "Salah Pesan/Batal" ---
            'salah order nih', 'mau ganti pesanan', 'batalin aja', 'cancel aja', 'ubah order', 'pesanan salah',
            'order salah', 'bisakah dibatalkan'
        ],
        response: "Kalau kamu salah pesan, buruan deh hubungi layanan pelanggan kami secepatnya ya. Kami bakal cek apakah pesananmu masih bisa diubah atau dibatalin sebelum dikirim."
    },
    {
        keywords: [
            'harga', 'berapa harga', 'list harga', 'harga produk', 'harga barang', 'daftar harga', 'price list',
            'harganya berapa',
            // --- Penambahan kata baru "Harga Produk" ---
            'harga brg', 'brp hrg', 'harga item', 'list hrg', 'price nya', 'harga produk brp'
        ],
        response: "Harga produknya udah tertera jelas kok di halaman detail produk. Kalau ada variasi, harganya mungkin beda. Coba cek di sana ya!"
    },
    {
        keywords: [
            'produk baru', 'new arrival', 'produk baru apa', 'barang baru', 'update produk', 'rilis baru',
            // --- Penambahan kata baru "Produk Baru" ---
            'brg baru', 'produk baru ada', 'new produk', 'rilisan baru', 'produk terupdate', 'ada barang baru'
        ],
        response: "Tentu! Kami selalu update produk baru secara berkala. Cek aja kategori 'Produk Terbaru' atau 'New Arrivals' di website kami ya, banyak yang kece-kece!"
    }
]


    for (const item of keywordMap) {
        for (const keyword of item.keywords) {
            if (message.includes(keyword)) {
                return item.response;
            }
        }
    }

    return null;
}

// Tambahkan pesan ke chat
function addMessage(text, sender) {
    const messageBubble = document.createElement('div');
    messageBubble.classList.add('message-bubble', sender === 'user' ? 'user-message' : 'bot-message');
    messageBubble.textContent = text;
    chatMessages.appendChild(messageBubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Logika utama untuk respon bot
function getBotResponse(userMessage) {
    const normalizedMessage = normalizeText(userMessage);

    for (const qa of predefinedAnswers) {
        for (const pattern of qa.patterns) {
            if (pattern.test(normalizedMessage)) {
                // Tangani konteks stok jumlah saja
                if (
                    pattern.source.includes("cek stok") ||
                    pattern.source.includes("ketersediaan stok") ||
                    pattern.source.includes("stok (ini)?") ||
                    pattern.source.includes("berapa sisa stok") ||
                    pattern.source.includes("stoknya ada berapa") ||
                    pattern.source.includes("masih ada stok gak") ||
                    pattern.source.includes("sisa stok") ||
                    pattern.source.includes("stok produk ini")
                ) {
                    if (typeof currentProductStock !== 'undefined') {
                        return `Stok produk ini saat ini adalah ${currentProductStock} unit. Yuk, amankan sebelum kehabisan!`; // Diubah di sini
                    }
                }

                // Balasan normal dari regex match
                return qa.responses[Math.floor(Math.random() * qa.responses.length)];
            }
        }
    }

    // Fallback ke keyword
    const keywordResponse = keywordMatch(normalizedMessage);
    if (keywordResponse) return keywordResponse;

    // Fallback terakhir
    const fallbackResponses = [
        "Pertanyaanmu unik banget, bisa dijelaskan ulang biar aku lebih paham?",
        "Mohon dijelaskan lebih spesifik ya, Bestie, biar aku bisa bantu dengan tepat.",
        "Aku belum nyambung nih maksudmu, coba pakai kata lain ya.",
        "Maaf, aku masih bingung. Bisa diulang pertanyaannya dengan lebih jelas?",
        "Hmm, aku kurang yakin. Ada hal lain yang bisa aku bantu?"
    ];
    return fallbackResponses[Math.floor(Math.random() * fallbackResponses.length)];
}

// Toggle chatbot
chatbotToggleBtn.addEventListener('click', () => {
    chatbotContainer.style.display = chatbotContainer.style.display === 'flex' ? 'none' : 'flex';
    if (chatbotContainer.style.display === 'flex') {
        userInput.focus();
        chatbotToggleBtn.classList.add('hidden'); // Sembunyikan tombol toggle saat chatbot terbuka
    } else {
        chatbotToggleBtn.classList.remove('hidden'); // Tampilkan kembali tombol toggle
    }
});

closeChatbotBtn.addEventListener('click', () => {
    chatbotContainer.style.display = 'none';
    chatbotToggleBtn.classList.remove('hidden'); // Tampilkan kembali tombol toggle
});

// Kirim pesan
sendButton.addEventListener('click', () => {
    const message = userInput.value.trim();
    if (message) {
        addMessage(message, 'user');
        userInput.value = '';
        setTimeout(() => {
            addMessage(getBotResponse(message), 'bot');
        }, 500);
    }
});

// Enter untuk kirim
userInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        sendButton.click();
    }
});

// Pertanyaan cepat
quickQuestionBtns.forEach(button => {
    button.addEventListener('click', () => {
        const question = button.dataset.question;
        addMessage(question, 'user');
        setTimeout(() => {
            addMessage(getBotResponse(question), 'bot');
        }, 500);
    });
});

// Drag chatbot
let isDragging = false;
let offsetX, offsetY;

chatbotHeader.addEventListener('mousedown', (e) => {
    isDragging = true;
    offsetX = e.clientX - chatbotContainer.getBoundingClientRect().left;
    offsetY = e.clientY - chatbotContainer.getBoundingClientRect().top;
    chatbotContainer.style.cursor = 'grabbing';
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    e.preventDefault(); // Mencegah pemilihan teks saat menyeret
    chatbotContainer.style.left = (e.clientX - offsetX) + 'px';
    chatbotContainer.style.top = (e.clientY - offsetY) + 'px';
});

document.addEventListener('mouseup', () => {
    isDragging = false;
    chatbotContainer.style.cursor = 'grab';
});

// Posisi awal chatbot agar tidak off-screen dan responsif
function setInitialChatbotPosition() {
    // Set default posisi di kanan bawah
    chatbotContainer.style.right = '20px';
    chatbotContainer.style.bottom = '20px';
    chatbotContainer.style.left = 'auto';
    chatbotContainer.style.top = 'auto';

    // Pastikan tidak melampaui batas viewport saat di-resize atau load
    const rect = chatbotContainer.getBoundingClientRect();
    if (rect.right > window.innerWidth - 10) {
        chatbotContainer.style.left = (window.innerWidth - rect.width - 10) + 'px';
        chatbotContainer.style.right = 'auto';
    }
    if (rect.bottom > window.innerHeight - 10) {
        chatbotContainer.style.top = (window.innerHeight - rect.height - 10) + 'px';
        chatbotContainer.style.bottom = 'auto';
    }
}

// Panggil saat halaman dimuat dan saat ukuran jendela berubah
window.addEventListener('load', setInitialChatbotPosition);
window.addEventListener('resize', setInitialChatbotPosition);

</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js" integrity="sha384-eMNCOe7tCwlsaKMso+dFvvzpbBnVhVapLFifB6eoQlvhnhxwj1LCmJWok1mEjqlMj" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
</body>
</html>