<?php
include '../koneksi/koneksi.php';

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

// Ambil ID produk dari parameter GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $produk_id = $_GET['id'];

    // Ambil data produk utama
    $sql_produk = "SELECT p.*,
                             (SELECT harga FROM produk_variasi WHERE produk_id = p.id ORDER BY harga ASC LIMIT 1) AS harga_variasi_min
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

    // Konversi harga produk utama ke float
    if ($produk) {
        $produk['harga'] = (float) $produk['harga'];
        $produk['harga_lama'] = (float) ($produk['harga_lama'] ?? 0); // Konversi juga, default 0
        $produk['harga_variasi_min'] = (float) ($produk['harga_variasi_min'] ?? 0); // Konversi juga
    }

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
                             pv.harga AS variasi_harga,
                             pv.harga_lama AS original_price_variasi -- Tambahkan kolom harga_lama dari variasi jika ada
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
    while ($row = mysqli_fetch_assoc($result_variasi)) {
        $row['variasi_harga'] = (float) $row['variasi_harga']; // Konversi ke float
        $row['original_price'] = (float) ($row['original_price_variasi'] ?? 0); // Konversi harga_lama variasi
        $variasi_produk[] = $row;
    }
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
</style>
</head>
<body>
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
                            data-media-src="<?php echo $gambar_utama_thumbnail; ?>" onclick="changeMainMedia(this.dataset.mediaSrc, this.dataset.mediaType)">
                    <?php endif; ?>
                    <?php if (isset($media_lain) && is_array($media_lain) && count($media_lain) > 0): ?>
                        <?php foreach ($media_lain as $media): ?>
                            <?php $path_media_lain = cari_media($media['nama_file'], $media['jenis_media']); ?>
                            <?php if ($media['jenis_media'] == 'gambar'): ?>
                                <img src="<?php echo $path_media_lain; ?>" alt="Gambar Produk Lain"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_media_lain; ?>" onclick="changeMainMedia(this.dataset.mediaSrc, this.dataset.mediaType)">
                            <?php elseif ($media['jenis_media'] == 'video'): ?>
                                <div class="thumbnail-video-container position-relative mr-2 thumbnail img-thumbnail"
                                    data-media-type="video" data-media-src="<?php echo $path_media_lain; ?>"
                                    onclick="changeMainMedia(this.dataset.mediaSrc, this.dataset.mediaType)">
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
                            // Check for unique color variations with images
                            if (!empty($variasi['warna_nama']) && !isset($gambar_warna_unik[$variasi['warna_nama']]) && !empty($variasi['variasi_gambar'])):
                                $path_gambar_variasi_warna = cari_media($variasi['variasi_gambar'], 'gambar');
                                // Only add if it's not already the main image thumbnail or another media thumbnail
                                $is_duplicate = false;
                                foreach ($media_lain as $m_item) {
                                    if (cari_media($m_item['nama_file'], $m_item['jenis_media']) === $path_gambar_variasi_warna) {
                                        $is_duplicate = true;
                                        break;
                                    }
                                }
                                if (!$is_duplicate && $gambar_utama_thumbnail !== $path_gambar_variasi_warna) {
                                ?>
                                <img src="<?php echo $path_gambar_variasi_warna; ?>"
                                    alt="Variasi Warna <?php echo $variasi['warna_nama'] ?? ''; ?>"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_gambar_variasi_warna; ?>"
                                    onclick="changeMainMedia(this.dataset.mediaSrc, this.dataset.mediaType)">
                                <?php
                                    $gambar_warna_unik[$variasi['warna_nama']] = true;
                                }
                            endif;

                            // Check for unique flavor variations with images
                            if (!empty($variasi['rasa_nama']) && !isset($gambar_rasa_unik[$variasi['rasa_nama']]) && !empty($variasi['variasi_gambar'])):
                                $path_gambar_variasi_rasa = cari_media($variasi['variasi_gambar'], 'gambar');
                                // Only add if it's not already the main image thumbnail or another media thumbnail
                                $is_duplicate = false;
                                foreach ($media_lain as $m_item) {
                                    if (cari_media($m_item['nama_file'], $m_item['jenis_media']) === $path_gambar_variasi_rasa) {
                                        $is_duplicate = true;
                                        break;
                                    }
                                }
                                if (!$is_duplicate && $gambar_utama_thumbnail !== $path_gambar_variasi_rasa) {
                                ?>
                                <img src="<?php echo $path_gambar_variasi_rasa; ?>"
                                    alt="Variasi Rasa <?php echo $variasi['rasa_nama'] ?? ''; ?>"
                                    class="thumbnail img-thumbnail mr-2" data-media-type="gambar"
                                    data-media-src="<?php echo $path_gambar_variasi_rasa; ?>"
                                    onclick="changeMainMedia(this.dataset.mediaSrc, this.dataset.mediaType)">
                                <?php
                                    $gambar_rasa_unik[$variasi['rasa_nama']] = true;
                                }
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
                <span class="sold ml-2"><?php echo $produk['jumlah_terjual'] ?? '0'; ?>+ Terjual</span>
            </div>

            <div class="price-section">
                <span class="current-price" id="display-price">
                    <?php if (!empty($variasi_produk) && isset($produk['harga_variasi_min']) && $produk['harga_variasi_min'] < $produk['harga'] && $produk['harga_variasi_min'] > 0): ?>
                        Rp <?php echo number_format($produk['harga_variasi_min'], 0, ',', '.'); ?> - Rp
                        <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
                    <?php elseif (!empty($variasi_produk) && isset($produk['harga_variasi_min']) && $produk['harga_variasi_min'] >= 0): ?>
                        Rp <?php echo number_format($produk['harga_variasi_min'], 0, ',', '.'); ?>
                    <?php else: // No variations or min price not less than main price ?>
                        Rp <?php echo number_format($produk['harga'] ?? 0, 0, ',', '.'); ?>
                    <?php endif; ?>
                </span>
                <?php if (isset($produk['harga_lama']) && $produk['harga_lama'] > $produk['harga'] && empty($variasi_produk)): ?>
                    <span class="original-price ml-2" id="original-price-display">
                        Rp <?php echo number_format($produk['harga_lama'], 0, ',', '.'); ?>
                    </span>
                    <?php $discount = (($produk['harga_lama'] - $produk['harga']) / $produk['harga_lama']) * 100; ?>
                    <span class="discount ml-2">-<?php echo round($discount); ?>%</span>
                <?php else: ?>
                    <span class="original-price ml-2 d-none" id="original-price-display"></span>
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
                                                data-rasa="<?php echo htmlspecialchars($variasi['rasa_nama']); ?>"
                                                data-gambar="<?php echo $gambar_variasi_rasa; ?>" data-jenis="rasa"
                                                <?php if ($variasi['variasi_stok'] <= 0) echo 'disabled'; ?>>
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
                                                data-warna="<?php echo htmlspecialchars($variasi['warna_nama']); ?>"
                                                data-gambar="<?php echo $gambar_variasi_warna; ?>" data-jenis="warna"
                                                <?php if ($variasi['variasi_stok'] <= 0) echo 'disabled'; ?>>
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
                                        // Attempt to find an image for this size variation, if it exists in any related variation
                                        $variasi_ukuran_gambar = '';
                                        foreach ($variasi_produk as $v) {
                                            if (!empty($v['ukuran_nama']) && $v['ukuran_nama'] == $ukuran && !empty($v['variasi_gambar'])) {
                                                $variasi_ukuran_gambar = cari_media($v['variasi_gambar']);
                                                break;
                                            }
                                        }
                                        $gambar_ukuran = $variasi_ukuran_gambar ?: '';
                                        ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2 variation-button"
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
            <?php else: ?>
                Stok: <span id="current-stock">0</span> <?php endif; ?>
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
            <div class="other-info mt-3">
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-12">
            <h3>Deskripsi Produk</h3>
            <p><?php echo nl2br(htmlspecialchars($produk['deskripsi'] ?? '')); ?></p>
        </div>
    </div>

    <?php include '../ulasan.php'; // Path disesuaikan ?>
</div>
<script>
// Variabel-variabel untuk elemen DOM
const quantitySection = document.querySelector('.quantity-section');
const quantityInput = document.getElementById('quantity');
const qtyMinusButton = document.getElementById('qty-minus');
const qtyPlusButton = document.getElementById('qty-plus');
const stockInfoSpan = document.getElementById('stock-info');
const priceSpan = document.getElementById('display-price'); // Changed to use ID
const originalPriceDisplay = document.getElementById('original-price-display'); // Changed to use ID
const variationButtons = document.querySelectorAll('.variation-button');
const addToCartButton = document.getElementById('add-to-cart-btn');
const buyNowButton = document.getElementById('buy-now-btn');
const stockErrorMessage = document.getElementById('stock-error-message');
const noVariationMessage = document.getElementById('no-variation-message');

let selectedVariation = {};
// Initialize with product's base price and old price (if no variations or no selection)
let productPrice = <?php echo $produk['harga'] ?? 0; ?>;
let productOriginalPrice = <?php echo $produk['harga_lama'] ?? 0; ?>; // Use the actual `harga_lama` from product
let productStock = <?php echo $produk['stok'] ?? 0; ?>;
let currentProductStock = productStock; // Variabel untuk menyimpan stok produk atau variasi yang dipilih

// PASTIKAN VARIABEL PHP INI TERSEDIA DAN BERNILAI TRUE/FALSE DARI QUERY DATABASE ANDA
const adaRasa = <?php echo $ada_rasa ? 'true' : 'false'; ?>;
const adaWarna = <?php echo $ada_warna ? 'true' : 'false'; ?>;
const adaUkuran = <?php echo $ada_ukuran ? 'true' : 'false'; ?>;

// PASTIKAN VARIABEL PHP INI TERSEDIA DAN MENGANDUNG DATA VARIASI DENGAN RASA_NAMA, WARNA_NAMA, UKURAN_NAMA
const allVariations = <?php echo json_encode($variasi_produk); ?>;

const mainImageElement = document.getElementById('main-image');
const mainVideoElement = document.getElementById('main-video');
const mainImageContainerElement = document.querySelector('.main-image-container');

// Elemen-elemen untuk scroll thumbnail
const thumbnailScrollContainer = document.getElementById('thumbnail-scroll-container');
const scrollLeftButton = document.querySelector('.scroll-button.left'); // Select by class
const scrollRightButton = document.querySelector('.scroll-button.right'); // Select by class

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

    // Set initial stock info based on whether variations exist
    if (allVariations.length === 0) {
        stockInfoSpan.innerHTML = `Stok: <span id="current-stock">${formatRupiah(productStock)}</span>`;
    } else {
        stockInfoSpan.innerHTML = `Stok: <span id="current-stock">0</span>`; // Default to 0 if variations exist but none chosen
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
            noVariationMessage.textContent = 'Tidak ada variasi yang tersedia untuk produk ini.';
        }
        // Aktifkan kontrol kuantitas dan tombol karena tidak ada variasi
        enableQuantityControls(productStock);
        addToCartButton.disabled = false;
        buyNowButton.disabled = false;
        quantitySection.classList.remove('disabled-quantity');
        updatePriceAndStock(productPrice, productStock); // Set initial price and stock for no variations
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
    checkScrollButtons(); // Initial check for scroll buttons visibility
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

    // Remove existing content within main image container
    while (mainImageContainer.firstChild) {
        mainImageContainer.removeChild(mainImageContainer.firstChild);
    }

    if (mediaType === 'video') {
        const video = document.createElement('video');
        video.id = 'main-video';
        video.classList.add('img-fluid', 'main-image');
        video.controls = true;
        video.autoplay = true; // Auto-play when switched to
        video.muted = true; // Mute for auto-play (browser requirement)
        video.loop = true; // Loop video

        const source = document.createElement('source');
        source.src = mediaSrc;
        source.type = 'video/mp4'; // Assuming MP4
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
     // Update active thumbnail
     document.querySelectorAll('.thumbnail, .thumbnail-video-container').forEach(thumb => {
        thumb.classList.remove('active-thumbnail');
    });
    const newActiveThumbnail = document.querySelector(`.thumbnail[data-media-src="${mediaSrc}"]`);
    if (newActiveThumbnail) {
        newActiveThumbnail.classList.add('active-thumbnail');
    }
}


function enableQuantityControls(stock) {
    quantityInput.disabled = false;
    qtyMinusButton.disabled = false;
    qtyPlusButton.disabled = false;
    quantitySection.classList.remove('disabled-quantity');
    stockInfoSpan.innerHTML = `Stok: <span id="current-stock">${formatRupiah(stock)}</span>`;
    currentProductStock = stock; // Update the global stock variable
    updateQuantityInputMax(); // Pastikan state tombol sesuai dengan stok yang baru
}

function disableQuantityControls() {
    quantityInput.disabled = true;
    qtyMinusButton.disabled = true;
    qtyPlusButton.disabled = true;
    quantityInput.value = 1; // Default ke 1 saat disabled
    stockInfoSpan.innerHTML = 'Stok: <span id="current-stock">0</span>'; // Set stock info to 0 or appropriate message
    priceSpan.textContent = 'Rp ' + formatRupiah(productPrice); // Reset to base product price
    if (originalPriceDisplay) {
        if (productOriginalPrice > productPrice) { // Only show if original price is higher
            originalPriceDisplay.classList.remove('d-none');
            originalPriceDisplay.textContent = 'Rp ' + formatRupiah(productOriginalPrice);
        } else {
            originalPriceDisplay.classList.add('d-none');
        }
    }
    addToCartButton.disabled = true;
    buyNowButton.disabled = true;
    quantitySection.classList.add('disabled-quantity');
    stockErrorMessage.classList.add('d-none');
}

function updatePriceAndStock(price, stock, originalPrice = null) {
    priceSpan.textContent = 'Rp ' + formatRupiah(price);
    stockInfoSpan.innerHTML = `Stok: <span id="current-stock">${formatRupiah(stock)}</span>`;
    currentProductStock = stock; // Update the global stock variable

    if (originalPriceDisplay) {
        if (originalPrice !== null && originalPrice > price) {
            originalPriceDisplay.classList.remove('d-none');
            originalPriceDisplay.textContent = 'Rp ' + formatRupiah(originalPrice);
        } else {
            originalPriceDisplay.classList.add('d-none');
        }
    }

    updateButtonStates();
    updateQuantityInputMax();
}

function updateButtonStates() {
    let allVariationsSelected = true;
    if (allVariations.length > 0) { // Only check if variations are defined for this product
        if (adaRasa && selectedVariation.rasa === undefined) allVariationsSelected = false;
        if (adaWarna && selectedVariation.warna === undefined) allVariationsSelected = false;
        if (adaUkuran && selectedVariation.ukuran === undefined) allVariationsSelected = false;
    }

    const isStockAvailable = currentProductStock > 0;

    if (allVariations.length > 0 && !allVariationsSelected) {
        addToCartButton.disabled = true;
        buyNowButton.disabled = true;
        disableQuantityControls(); // Disable quantity controls until selection is complete
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Silakan pilih semua variasi untuk melihat stok dan harga.';
        if (noVariationMessage) { // Ensure noVariationMessage is hidden
            noVariationMessage.style.display = 'none';
        }
    } else if (!isStockAvailable) {
        addToCartButton.disabled = true;
        buyNowButton.disabled = true;
        disableQuantityControls(); // Disable quantity controls if stock is 0
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Stok habis untuk variasi ini.';
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none';
        }
    } else {
        addToCartButton.disabled = false;
        buyNowButton.disabled = false;
        enableQuantityControls(currentProductStock); // Enable and update quantity controls
        stockErrorMessage.classList.add('d-none');
        if (noVariationMessage) {
            noVariationMessage.style.display = 'none';
        }
        // Ensure quantity doesn't exceed current stock
        if (parseInt(quantityInput.value) > currentProductStock) {
            quantityInput.value = currentProductStock > 0 ? currentProductStock : 0;
        } else if (parseInt(quantityInput.value) === 0 && currentProductStock > 0) {
            quantityInput.value = 1;
        }
    }
    updateQuantityInputMax(); // Call this at the end to synchronize +/- buttons
}


function updateQuantityInputMax() {
    quantityInput.max = currentProductStock;
    let currentQty = parseInt(quantityInput.value);

    // If stock is 0, set quantity to 0 and disable everything
    if (currentProductStock === 0) {
        quantityInput.value = 0;
        qtyMinusButton.disabled = true;
        qtyPlusButton.disabled = true;
        return; // Exit, no further checks needed
    }

    // Ensure quantity doesn't exceed available stock
    if (currentQty > currentProductStock) {
        quantityInput.value = currentProductStock;
    } else if (currentQty < 1) { // Ensure quantity is not less than 1
        quantityInput.value = 1;
    }

    // Update state of minus and plus buttons
    qtyMinusButton.disabled = (parseInt(quantityInput.value) <= 1);
    qtyPlusButton.disabled = (parseInt(quantityInput.value) >= currentProductStock);
}

function formatRupiah(angka) {
    if (isNaN(angka) || angka === null) return '0';
    let number_string = angka.toString().replace(/[^,\d]/g, '').toString(),
        split = number_string.split(','),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah;
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
        setTimeout(checkScrollButtons, 50); // Small delay to allow scroll to complete
    }
}

function checkScrollButtons() {
    if (scrollLeftButton && scrollRightButton && thumbnailScrollContainer) {
        // Logika untuk menampilkan/menyembunyikan tombol scroll
        const scrollLeft = thumbnailScrollContainer.scrollLeft;
        const scrollWidth = thumbnailScrollContainer.scrollWidth;
        const clientWidth = thumbnailScrollContainer.clientWidth;
        const scrollMax = scrollWidth - clientWidth;

        // Hide left button if at the very beginning
        scrollLeftButton.style.display = scrollLeft > 5 ? 'flex' : 'none'; // Add a small tolerance
        // Hide right button if at the very end
        scrollRightButton.style.display = scrollLeft < (scrollMax - 5) ? 'flex' : 'none'; // Add a small tolerance
    }
}

// --- Event Listeners ---

addToCartButton.addEventListener('click', function() {
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
    
    // Path disesuaikan: '../' untuk naik satu level ke root
    fetch('keranjang/proses_keranjang.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `produk_id=${productId}&quantity=${quantity}${variationId ? `&variasi_id=${variationId}` : ''}`,
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menambahkan produk ke keranjang.');
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
    window.location.href = 'keranjang/checkout.php?' + params.toString(); // Path disesuaikan
});

variationButtons.forEach(button => {
    button.addEventListener('click', function() {
        const jenisVariasi = this.dataset.jenis;
        const nilaiVariasi = this.dataset[jenisVariasi];
        const gambarVariasi = this.dataset.gambar;

        // Check if the clicked button is already active
        const isActive = this.classList.contains('active');

        // Remove 'active' class from all buttons of the same variation type
        document.querySelectorAll(`.variation-button[data-jenis="${jenisVariasi}"]`).forEach(btn => {
            btn.classList.remove('active');
        });

        if (isActive) {
            // If button was active, deselect it
            delete selectedVariation[jenisVariasi];
        } else {
            // If button was not active, activate it and store the selection
            this.classList.add('active');
            selectedVariation[jenisVariasi] = nilaiVariasi;
        }
        
        // --- Modified image determination logic ---
        let imageToDisplay = '<?php echo htmlspecialchars($gambar_utama); ?>'; // Default to main product image
        let highestPriorityImageFound = false;

        // Prioritize specific variation images if selected
        if (adaWarna && selectedVariation.warna) {
            const warnaButton = document.querySelector(`.variation-button[data-jenis="warna"][data-warna="${selectedVariation.warna}"]`);
            if (warnaButton && warnaButton.dataset.gambar && warnaButton.dataset.gambar !== '') {
                imageToDisplay = warnaButton.dataset.gambar;
                highestPriorityImageFound = true;
            }
        }
        if (adaRasa && selectedVariation.rasa && !highestPriorityImageFound) { // Only check if color didn't provide an image
            const rasaButton = document.querySelector(`.variation-button[data-jenis="rasa"][data-rasa="${selectedVariation.rasa}"]`);
            if (rasaButton && rasaButton.dataset.gambar && rasaButton.dataset.gambar !== '') {
                imageToDisplay = rasaButton.dataset.gambar;
                highestPriorityImageFound = true;
            }
        }
        // If neither color nor rasa provided a specific image, check for size (if it has images)
        if (adaUkuran && selectedVariation.ukuran && !highestPriorityImageFound) {
            const ukuranButton = document.querySelector(`.variation-button[data-jenis="ukuran"][data-ukuran="${selectedVariation.ukuran}"]`);
            if (ukuranButton && ukuranButton.dataset.gambar && ukuranButton.dataset.gambar !== '') {
                imageToDisplay = ukuranButton.dataset.gambar;
                highestPriorityImageFound = true;
            }
        }
        
        // If no specific variation image was found and an initial specific image for the product was provided, use that.
        // Otherwise, it will remain the main product image (placeholder or default).
        if (!highestPriorityImageFound && gambarVariasi !== '' && gambarVariasi !== 'placeholder.jpg') {
             imageToDisplay = gambarVariasi; // This is the image from the clicked button, if it's not empty/placeholder
        }
       
        changeMainMedia(imageToDisplay, 'gambar'); // Change the main display to the determined image
        // --- End modified image determination logic ---


        // Find matching variation based on current selections
        let currentPriceToDisplay = productPrice;
        let currentStockToDisplay = productStock;
        let currentOriginalPriceToDisplay = productOriginalPrice;
        let foundMatchingVariation = null;

        // Ensure to only search for a matching variation if there are actual variations
        if (allVariations.length > 0) {
            foundMatchingVariation = allVariations.find(v => {
                let match = true;
                if (adaRasa && v.rasa_nama !== selectedVariation.rasa) match = false;
                if (adaWarna && v.warna_nama !== selectedVariation.warna) match = false;
                if (adaUkuran && v.ukuran_nama !== selectedVariation.ukuran) match = false;
                return match;
            });
        }
        
        // Logic for determining price and stock
        if (allVariations.length === 0) {
            // If no variations at all, use default product price and stock
            currentPriceToDisplay = productPrice;
            currentStockToDisplay = productStock;
            currentOriginalPriceToDisplay = productOriginalPrice;
            enableQuantityControls(currentStockToDisplay);
            addToCartButton.disabled = false;
            buyNowButton.disabled = false;
            stockErrorMessage.classList.add('d-none');
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
        } else if (!foundMatchingVariation || // No matching variation found
                   (adaRasa && selectedVariation.rasa === undefined) || // Has rasa but not selected
                   (adaWarna && selectedVariation.warna === undefined) || // Has warna but not selected
                   (adaUkuran && selectedVariation.ukuran === undefined) ) { // Has ukuran but not selected
            // If no suitable variation is found OR not all required variations are selected
            currentPriceToDisplay = productPrice; // Revert to base product price (or min variation price range if applicable)
            currentStockToDisplay = 0; // Stock is 0 if no variation selected or mismatch
            currentOriginalPriceToDisplay = productOriginalPrice; // Revert to base product original price
            stockErrorMessage.classList.remove('d-none');
            stockErrorMessage.textContent = 'Silakan pilih semua variasi untuk melihat stok dan harga.';
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
            disableQuantityControls();
            addToCartButton.disabled = true;
            buyNowButton.disabled = true;
        } else {
            // If a suitable variation is found
            currentPriceToDisplay = foundMatchingVariation.variasi_harga;
            currentStockToDisplay = foundMatchingVariation.variasi_stok;
            currentOriginalPriceToDisplay = foundMatchingVariation.original_price; // Use variation's original price
            stockErrorMessage.classList.add('d-none');
            if (noVariationMessage) {
                noVariationMessage.style.display = 'none';
            }
        }

        // Call updatePriceAndStock with determined values
        updatePriceAndStock(currentPriceToDisplay, currentStockToDisplay, currentOriginalPriceToDisplay);
        // Ensure to call updateButtonStates after this
        updateButtonStates();
    });
});


qtyPlusButton.addEventListener('click', () => {
    let currentQty = parseInt(quantityInput.value);
    if (isNaN(currentQty)) currentQty = 0; // Handle if input is empty when plus is clicked

    if (currentQty < currentProductStock) {
        quantityInput.value = currentQty + 1;
    }
    updateQuantityInputMax();
});

qtyMinusButton.addEventListener('click', () => {
    let currentQty = parseInt(quantityInput.value);
    if (isNaN(currentQty)) currentQty = 1; // Handle if input is empty when minus is clicked

    if (currentQty > 1) {
        quantityInput.value = currentQty - 1;
    }
    updateQuantityInputMax();
});

// Event listener for manual quantity input
quantityInput.addEventListener('input', () => {
    let val = quantityInput.value.trim(); // Get value and remove leading/trailing spaces
    let parsedVal = parseInt(val);

    // If input is completely empty (e.g., backspace all numbers)
    if (val === '') {
        stockErrorMessage.classList.add('d-none'); // Hide error message
        qtyMinusButton.disabled = true; // Disable minus when empty
        qtyPlusButton.disabled = true; // Disable plus when empty
        return; // Exit function, don't continue numeric validation
    }

    // If input is not a valid number or less than 1 (after parsed)
    if (isNaN(parsedVal) || parsedVal < 1) {
        quantityInput.value = currentProductStock > 0 ? 1 : 0; // Set to 1 or 0 if stock is 0
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = 'Kuantitas tidak valid. Minimum adalah 1.';
    } else if (parsedVal > currentProductStock) {
        // If input exceeds stock, set to max stock and show message
        quantityInput.value = currentProductStock;
        stockErrorMessage.classList.remove('d-none');
        stockErrorMessage.textContent = `Kuantitas tidak boleh melebihi stok ${formatRupiah(currentProductStock)}.`;
    } else {
        // If valid, hide error message
        stockErrorMessage.classList.add('d-none');
    }
    updateQuantityInputMax(); // Ensure plus/minus buttons are updated
});

// Scroll thumbnail event listeners
if (scrollLeftButton && scrollRightButton && thumbnailScrollContainer) {
    scrollLeftButton.addEventListener('click', function() {
        thumbnailScrollContainer.scrollLeft -= 150;
        checkScrollButtons(); // Update button status after scroll
    });

    scrollRightButton.addEventListener('click', function() {
        thumbnailScrollContainer.scrollLeft += 150;
        checkScrollButtons(); // Update button status after scroll
    });

    // Call checkScrollButtons on scroll or after DOM loaded (with a small delay)
    thumbnailScrollContainer.addEventListener('scroll', checkScrollButtons);
    setTimeout(checkScrollButtons, 100); // Call once after DOM loaded for initialization
}

const thumbnailsContainer = document.querySelector('.thumbnails-container');

thumbnailsContainer.addEventListener('click', function(event) {
    const clickedElement = event.target.closest('.thumbnail, .thumbnail-video-container');

    if (clickedElement) {
        const mediaType = clickedElement.dataset.mediaType;
        const mediaSrc = clickedElement.dataset.mediaSrc;

        // Pause and reset main video if active
        const mainVideoElement = document.getElementById('main-video');
        if (mainVideoElement && !mainVideoElement.paused) {
            mainVideoElement.pause();
            mainVideoElement.currentTime = 0; // Optional: reset to beginning
        }

        // Remove active class from all thumbnails and add to the clicked one
        document.querySelectorAll('.thumbnail, .thumbnail-video-container').forEach(thumb => {
            thumb.classList.remove('active-thumbnail');
        });
        clickedElement.classList.add('active-thumbnail');

        // Change the main media display
        changeMainMedia(mediaSrc, mediaType);
    }
});
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js" integrity="sha384-eMNCOe7tCwlsaKMso+dFvvzpbBnVhVapLFifB6eoQlvhnhxwj1LCmJWok1mEjqlMj" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
</body>
</html>
<?php
mysqli_close($conn);
?>