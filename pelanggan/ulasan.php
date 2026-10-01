<?php
// Pastikan koneksi database sudah di-include di detail_produk.php
// Pastikan variabel $conn sudah tersedia dan merupakan objek koneksi mysqli yang valid.
// Anda MUNGKIN perlu mengaktifkan baris di bawah ini jika file ini tidak di-include oleh file lain
// yang sudah memiliki koneksi database (misalnya, di detail_produk.php Anda).
// Contoh: include '../koneksi/koneksi.php'; // Sesuaikan path jika ulasan.php berada di subfolder

// Ambil ID produk dari parameter GET
// Ini akan menjadi null jika tidak ada parameter 'id' di URL, yang berarti kita bisa menampilkan semua ulasan.
$produk_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// --- PAGINATION SETUP ---
$limit = 5; // Jumlah ulasan per halaman
$current_page = isset($_GET['page_ulasan']) && is_numeric($_GET['page_ulasan']) ? (int)$_GET['page_ulasan'] : 1;
$offset = ($current_page - 1) * $limit;

// --- FILTER & SORTING SETUP ---
$filter_media = isset($_GET['filter_media']) && $_GET['filter_media'] == 'true';
$filter_rating = isset($_GET['filter_rating']) && is_numeric($_GET['filter_rating']) ? (int)$_GET['filter_rating'] : null;
$filter_komentar = isset($_GET['filter_komentar']) && $_GET['filter_komentar'] == 'true';
$order_by = "up.tanggal_ulasan DESC"; // Default: Terbaru (Ulasan terbaru akan muncul di atas)

// --- Logic untuk menentukan apakah kita harus memfilter berdasarkan produk_id ---
// Ini akan bernilai TRUE jika ada parameter 'id' di URL (misalnya, di halaman detail_produk).
// Ini akan bernilai FALSE jika tidak ada parameter 'id' di URL (misalnya, saat klik tombol 'Semua').
$filter_by_product_id_for_query = ($produk_id !== null);

// --- Query untuk mengambil data agregat rating (Rata-rata, Total Bintang, dll.) ---
$aggregate_where_clause = "";
$aggregate_params = [];
$aggregate_types = "";

if ($filter_by_product_id_for_query) {
    $aggregate_where_clause = " WHERE produk_id = ?";
    $aggregate_params[] = $produk_id;
    $aggregate_types .= "i";
}

$sql_aggregate_ratings = "SELECT 
                            COUNT(id) AS total_ulasan_keseluruhan,
                            AVG(rating) AS rata_rata_rating,
                            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS total_5_bintang,
                            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS total_4_bintang,
                            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS total_3_bintang,
                            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS total_2_bintang,
                            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS total_1_bintang,
                            SUM(CASE WHEN komentar IS NOT NULL AND komentar != '' THEN 1 ELSE 0 END) AS total_dengan_komentar
                          FROM ulasan_produk" . $aggregate_where_clause;

$stmt_aggregate = mysqli_prepare($conn, $sql_aggregate_ratings);
if ($stmt_aggregate === false) {
    error_log('Error preparing aggregate query: ' . mysqli_error($conn));
    $aggregate_data = [
        'total_ulasan_keseluruhan' => 0, 'rata_rata_rating' => 0,
        'total_5_bintang' => 0, 'total_4_bintang' => 0, 'total_3_bintang' => 0,
        'total_2_bintang' => 0, 'total_1_bintang' => 0, 'total_dengan_komentar' => 0
    ];
} else {
    if (!empty($aggregate_params)) {
        mysqli_stmt_bind_param($stmt_aggregate, $aggregate_types, ...$aggregate_params);
    }
    mysqli_stmt_execute($stmt_aggregate);
    $result_aggregate = mysqli_stmt_get_result($stmt_aggregate);
    $aggregate_data = mysqli_fetch_assoc($result_aggregate);
    mysqli_stmt_close($stmt_aggregate);
}

$total_ulasan_keseluruhan = $aggregate_data['total_ulasan_keseluruhan'];
$rata_rata_rating = ($total_ulasan_keseluruhan > 0) ? round($aggregate_data['rata_rata_rating'], 1) : 0;

// Query untuk menghitung ulasan dengan media secara akurat (juga disesuaikan dengan produk_id)
$sql_count_media_ulasan = "SELECT COUNT(DISTINCT um.ulasan_id) AS count_media 
                            FROM ulasan_media um
                            JOIN ulasan_produk up ON um.ulasan_id = up.id";
if ($filter_by_product_id_for_query) { 
    $sql_count_media_ulasan .= " WHERE up.produk_id = ?";
}
$stmt_count_media = mysqli_prepare($conn, $sql_count_media_ulasan);
if ($stmt_count_media === false) {
    error_log('Error preparing count media query: ' . mysqli_error($conn));
    $total_dengan_media = 0;
} else {
    if ($filter_by_product_id_for_query) {
        mysqli_stmt_bind_param($stmt_count_media, "i", $produk_id);
    }
    mysqli_stmt_execute($stmt_count_media);
    $res_count_media = mysqli_stmt_get_result($stmt_count_media);
    $row_count_media = mysqli_fetch_assoc($res_count_media);
    $total_dengan_media = $row_count_media['count_media'];
    mysqli_stmt_close($stmt_count_media);
}


// --- Base SQL Query and WHERE Clause untuk daftar ulasan ---
$sql_base = "SELECT
                up.id AS ulasan_id,
                up.rating,
                up.komentar,
                up.tanggal_ulasan,
                up.produk_id, 
                up.variasi_id,                  
                p.nama AS nama_pelanggan,
                p.foto AS foto_pelanggan,
                -- prod.nama AS nama_produk,             -- Dihilangkan sesuai permintaan
                w.nama_warna,                        
                u.nama_ukuran,                       
                r.nama_rasa                          
            FROM
                ulasan_produk up
            JOIN
                pelanggan p ON up.pelanggan_id = p.pengguna_id
            LEFT JOIN
                produk prod ON up.produk_id = prod.id  -- Tetap join ke produk jika nanti dibutuhkan data lain
            LEFT JOIN
                produk_variasi pv ON up.variasi_id = pv.id 
            LEFT JOIN
                warna w ON pv.warna_id = w.id        
            LEFT JOIN
                ukuran u ON pv.ukuran_id = u.id      
            LEFT JOIN
                rasa r ON pv.rasa_id = r.id         
            ";

$where_clauses = [];
$params = [];
$types = "";

// Tambahkan filter produk_id jika kita sedang melihat ulasan untuk produk spesifik
if ($filter_by_product_id_for_query) {
    $where_clauses[] = "up.produk_id = ?";
    $params[] = $produk_id;
    $types .= "i";
}

if ($filter_rating !== null) {
    $where_clauses[] = "up.rating = ?";
    $params[] = $filter_rating;
    $types .= "i";
}

if ($filter_media) {
    $where_clauses[] = "EXISTS (SELECT 1 FROM ulasan_media um WHERE um.ulasan_id = up.id)";
}

if ($filter_komentar) { 
    $where_clauses[] = "up.komentar IS NOT NULL AND up.komentar != ''";
}

// Tambahkan klausa WHERE jika ada
if (!empty($where_clauses)) {
    $sql_base .= " WHERE " . implode(" AND ", $where_clauses);
}

// --- HITUNG TOTAL ULASAN UNTUK PAGINATION (dengan filter aktif) ---
// Perhatikan, JOINs juga harus disertakan dalam query COUNT
$sql_count_ulasan = "SELECT COUNT(up.id) AS total_ulasan FROM ulasan_produk up ";
$sql_count_ulasan .= "JOIN pelanggan p ON up.pelanggan_id = p.pengguna_id ";
$sql_count_ulasan .= "LEFT JOIN produk prod ON up.produk_id = prod.id ";
$sql_count_ulasan .= "LEFT JOIN produk_variasi pv ON up.variasi_id = pv.id ";
$sql_count_ulasan .= "LEFT JOIN warna w ON pv.warna_id = w.id ";
$sql_count_ulasan .= "LEFT JOIN ukuran u ON pv.ukuran_id = u.id ";
$sql_count_ulasan .= "LEFT JOIN rasa r ON pv.rasa_id = r.id ";

if (!empty($where_clauses)) {
    $sql_count_ulasan .= " WHERE " . implode(" AND ", $where_clauses);
}

$stmt_count = mysqli_prepare($conn, $sql_count_ulasan);
if ($stmt_count === false) {
    error_log('Error preparing count query: ' . mysqli_error($conn));
    $total_ulasan_filtered = 0;
} else {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $row_count = mysqli_fetch_assoc($result_count);
    $total_ulasan_filtered = $row_count['total_ulasan']; // Total ulasan setelah filter
    mysqli_stmt_close($stmt_count);
}

$total_pages = ceil($total_ulasan_filtered / $limit);

// --- Query untuk mengambil ulasan produk (dengan semua filter dan pagination) ---
$sql_ulasan = $sql_base . " ORDER BY " . $order_by . " LIMIT ? OFFSET ?";

$stmt_ulasan = mysqli_prepare($conn, $sql_ulasan);
if ($stmt_ulasan === false) {
    error_log('Error preparing ulasan query: ' . mysqli_error($conn));
    $daftar_ulasan = [];
} else {
    $params_ulasan = $params; // Copy initial parameters
    $params_ulasan[] = $limit;
    $params_ulasan[] = $offset;
    $types_ulasan = $types . "ii"; // Add types for limit and offset

    mysqli_stmt_bind_param($stmt_ulasan, $types_ulasan, ...$params_ulasan);
    mysqli_stmt_execute($stmt_ulasan);
    $result_ulasan = mysqli_stmt_get_result($stmt_ulasan);
    $daftar_ulasan = mysqli_fetch_all($result_ulasan, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_ulasan);
}


// Ambil data media untuk setiap ulasan
foreach ($daftar_ulasan as &$ulasan) {
    $ulasan_id = $ulasan['ulasan_id'];
    $sql_media = "SELECT nama_file, jenis_media FROM ulasan_media WHERE ulasan_id = ?";
    $stmt_media = mysqli_prepare($conn, $sql_media);
    if ($stmt_media === false) {
        error_log('Error preparing media query: ' . mysqli_error($conn));
        $ulasan['media'] = [];
        continue;
    }
    mysqli_stmt_bind_param($stmt_media, "i", $ulasan_id);
    mysqli_stmt_execute($stmt_media);
    $result_media = mysqli_stmt_get_result($stmt_media);
    $ulasan['media'] = mysqli_fetch_all($result_media, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_media);
}
unset($ulasan); // Putuskan referensi terakhir

// Fungsi untuk membangun URL dengan menjaga parameter yang ada
// $product_id_param: Parameter 'id' yang akan dimasukkan ke URL.
//                   Jika null, 'id' akan dihilangkan dari URL.
// $page: Halaman pagination.
// $filter_media_status, $filter_rating_val, $filter_komentar_status: Status filter.
function build_url($product_id_param = null, $page = 1, $filter_media_status = false, $filter_rating_val = null, $filter_komentar_status = false) {
    $url_params = [];
    
    // Hanya tambahkan produk_id ke URL jika $product_id_param tidak null.
    // Ini memungkinkan tombol 'Semua' untuk menghilangkan 'id' dari URL.
    if ($product_id_param !== null) {
        $url_params[] = "id=" . $product_id_param;
    }
    
    if ($page > 1) {
        $url_params[] = "page_ulasan=" . $page;
    }
    if ($filter_media_status) {
        $url_params[] = "filter_media=true";
    }
    if ($filter_rating_val !== null) {
        $url_params[] = "filter_rating=" . $filter_rating_val;
    }
    if ($filter_komentar_status) {
        $url_params[] = "filter_komentar=true";
    }
    
    // Hapus parameter page_ulasan=1 jika ada dan bukan satu-satunya parameter
    // atau jika hanya page_ulasan=1 dan kita ingin URLnya lebih bersih (tanpa ?page_ulasan=1)
    if (in_array("page_ulasan=1", $url_params)) {
        // Jika 'page_ulasan=1' adalah satu-satunya parameter, kosongkan array
        if (count($url_params) === 1) {
            $url_params = [];
        } else {
            // Jika ada parameter lain, hapus hanya 'page_ulasan=1'
            unset($url_params[array_search("page_ulasan=1", $url_params)]);
        }
    }

    // Buat string query URL
    return (!empty($url_params) ? "?" . implode("&", $url_params) : '') . "#ulasan-pembeli";
}
?>

<style>
    /* Tambahan Gaya untuk Kotak Ringkasan Rating */
    .ulasan-ringkasan-box {
        background-color: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        flex-wrap: wrap; /* Untuk responsivitas */
        gap: 10px; /* Jarak antar elemen di dalam kotak */
    }
    .ulasan-ringkasan-box .avg-rating {
        font-size: 2.5em;
        font-weight: bold;
        color: #FF4500; /* OrangeRed */
        margin-right: 15px; /* Jarak antara rating angka dan bintang */
        flex-shrink: 0; /* Mencegah elemen ini menyusut */
    }
    .ulasan-ringkasan-box .star-display {
        font-size: 1.5em;
        color: #FFC107; /* Warna bintang kuning */
        margin-right: 20px; /* Jarak antara bintang dan filter group */
        flex-shrink: 0; /* Mencegah elemen ini menyusut */
    }
    .ulasan-ringkasan-box .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px; /* Jarak antar tombol filter */
        flex-grow: 1; /* Memungkinkan grup ini mengisi ruang yang tersedia */
    }
    .ulasan-ringkasan-box .filter-group .btn {
        font-size: 0.9em;
        padding: 6px 12px;
        border-radius: 20px; /* Bentuk pil */
        white-space: nowrap; /* Mencegah teks tombol pecah baris */
        /* Tambahkan ini jika Anda menduga masalah pointer-events */
        pointer-events: auto !important; /* Memastikan tombol dapat diklik */
    }
    /* Gaya untuk tombol rating aktif/default */
    .ulasan-ringkasan-box .filter-group .btn-primary {
        background-color: #FF4500;
        border-color: #FF4500;
        color: white;
    }
    .ulasan-ringkasan-box .filter-group .btn-primary:hover {
        background-color: #e63e00;
        border-color: #e63e00;
    }
    /* Gaya untuk tombol rating non-aktif */
    .ulasan-ringkasan-box .filter-group .btn-outline-primary {
        color: #FF4500;
        border: 1px solid #FF4500;
        background-color: white;
    }
    .ulasan-ringkasan-box .filter-group .btn-outline-primary:hover {
        background-color: #FF4500;
        color: white;
    }


    /* Gaya kustom untuk pagination */
    .pagination .page-item.active .page-link {
        background-color: #FF4500; /* OrangeRed */
        border-color: #FF4500;   /* OrangeRed */
        color: #fff;
    }
    .pagination .page-item.active .page-link:hover {
        background-color: #e63e00; /* Sedikit lebih gelap dari OrangeRed saat hover */
        border-color: #e63e00;
    }
    .pagination .page-link {
        color: #FF4500; /* Warna teks tautan normal */
    }
    .pagination .page-link:hover {
        color: #e63e00; /* Warna teks tautan saat hover */
    }
    /* Jika ingin mengubah warna untuk tombol disable */
    .pagination .page-item.disabled .page-link {
        color: #8c8c8c; /* Abu-abu terang untuk tautan disable */
    }

    /* Gaya kustom untuk gambar profil pelanggan */
    .ulasan-profil-gambar {
        width: 30px;  /* Pastikan lebar tetap 30px */
        height: 30px; /* Pastikan tinggi tetap 30px */
        object-fit: cover; /* Ini yang akan memotong gambar agar pas tanpa meregangkan */
        border-radius: 50%; /* Memastikan bentuk lingkaran */
        flex-shrink: 0; /* Mencegah gambar menyusut jika ruang terbatas */
    }

    /* Gaya untuk gambar media ulasan (foto/video) */
    .ulasan-media-thumbnail {
        max-width: 100px;
        max-height: 100px;
        object-fit: cover; /* Memastikan gambar/video mengisi area tanpa distorsi */
        border-radius: 5px; /* Opsional: sedikit border-radius agar tidak terlalu kaku */
    }
</style>

<div class="row mt-4">
    <div class="col-12">
        <h3 id="ulasan-pembeli">Ulasan Pembeli</h3>

        <div class="ulasan-ringkasan-box">
            <div class="avg-rating">
                <?php echo number_format($rata_rata_rating, 1); ?> dari 5
            </div>
            <div class="star-display mr-3">
                <?php 
                // Tampilkan bintang sesuai rata-rata rating
                $full_stars = floor($rata_rata_rating);
                $half_star = ($rata_rata_rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - $full_stars - $half_star;

                for ($i = 0; $i < $full_stars; $i++) echo '<i class="fas fa-star"></i>';
                if ($half_star) echo '<i class="fas fa-star-half-alt"></i>';
                for ($i = 0; $i < $empty_stars; $i++) echo '<i class="far fa-star"></i>';
                ?>
            </div>
            <div class="filter-group">
                <a href="<?php echo build_url(null, 1, false, null, false); ?>" 
                   class="btn <?php 
                        $is_all_active = ($filter_rating === null && !$filter_media && !$filter_komentar && $produk_id === null);
                        echo ($is_all_active) ? 'btn-primary' : 'btn-outline-primary'; 
                   ?>">
                    Semua (<?php echo $total_ulasan_keseluruhan; ?>)
                </a>
                
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <a href="<?php echo build_url($produk_id, 1, false, $i, false); ?>" 
                       class="btn <?php 
                           $is_rating_active = ($filter_rating == $i && !$filter_media && !$filter_komentar);
                           echo ($is_rating_active) ? 'btn-primary' : 'btn-outline-primary'; 
                       ?>">
                        <?php echo $i; ?> Bintang (<?php echo $aggregate_data['total_' . $i . '_bintang']; ?>)
                    </a>
                <?php endfor; ?>
                <a href="<?php echo build_url($produk_id, 1, true, null, false); ?>" 
                   class="btn <?php 
                       $is_media_active = ($filter_media && $filter_rating === null && !$filter_komentar);
                       echo ($is_media_active) ? 'btn-primary' : 'btn-outline-primary'; 
                   ?>">
                    Dengan Media (<?php echo $total_dengan_media; ?>)
                </a>
                <a href="<?php echo build_url($produk_id, 1, false, null, true); ?>" 
                   class="btn <?php 
                       $is_komentar_active = ($filter_komentar && $filter_rating === null && !$filter_media);
                       echo ($is_komentar_active) ? 'btn-primary' : 'btn-outline-primary'; 
                   ?>">
                    Dengan Komentar (<?php echo $aggregate_data['total_dengan_komentar']; ?>)
                </a>
            </div>
        </div>


        <?php if (!empty($daftar_ulasan)): ?>
            <?php foreach ($daftar_ulasan as $ulasan): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <?php
                            $foto_pelanggan_path = !empty($ulasan['foto_pelanggan']) ? '../img/foto/' . htmlspecialchars($ulasan['foto_pelanggan']) : 'img/foto/user_default.png';
                            ?>
                            <img src="<?php echo $foto_pelanggan_path; ?>" 
                                 alt="<?php echo htmlspecialchars($ulasan['nama_pelanggan']); ?>" 
                                 class="ulasan-profil-gambar mr-2"> 
                            <div>
                                <h6 class="card-subtitle mb-0"><?php echo htmlspecialchars($ulasan['nama_pelanggan']); ?></h6>
                                <div class="rating">
                                    <?php for ($i = 0; $i < $ulasan['rating']; $i++) echo '<i class="fas fa-star text-warning"></i>'; ?>
                                    <?php for ($i = $ulasan['rating']; $i < 5; $i++) echo '<i class="far fa-star text-warning"></i>'; ?>
                                </div>
                                <p class="text-muted small mb-1"><?php echo date('d F Y H:i', strtotime($ulasan['tanggal_ulasan'])); ?></p>
                                
                                <?php if (!empty($ulasan['variasi_id'])): ?>
                                    <?php if (!empty($ulasan['nama_warna'])): ?>
                                        <p class="text-muted small mb-0">
                                            Warna: <?php echo htmlspecialchars($ulasan['nama_warna']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($ulasan['nama_ukuran'])): ?>
                                        <p class="text-muted small mb-0">
                                            Ukuran: <?php echo htmlspecialchars($ulasan['nama_ukuran']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($ulasan['nama_rasa'])): ?>
                                        <p class="text-muted small mb-0">
                                            Rasa: <?php echo htmlspecialchars($ulasan['nama_rasa']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>

                            </div>
                        </div>
                        <p class="card-text mt-2"><?php echo nl2br(htmlspecialchars($ulasan['komentar'])); ?></p>
                        <?php if (!empty($ulasan['media'])): ?>
                            <div class="mt-2 d-flex flex-wrap">
                                <?php foreach ($ulasan['media'] as $media): ?>
                                    <?php
                                    $media_path = '';
                                    if ($media['jenis_media'] === 'foto') {
                                        $media_path = '../img/ulasan_media/' . htmlspecialchars($media['nama_file']);
                                        echo '<img src="' . $media_path . '" alt="Gambar Ulasan" class="img-thumbnail mr-2 mb-2 ulasan-media-thumbnail">';
                                    } elseif ($media['jenis_media'] === 'video') {
                                        $media_path = '../img/ulasan_media/' . htmlspecialchars($media['nama_file']);
                                        echo '<video controls class="img-thumbnail mr-2 mb-2 ulasan-media-thumbnail"><source src="' . $media_path . '" type="video/mp4"></video>';
                                    }
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <nav aria-label="Page navigation for reviews">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo build_url($produk_id, $current_page - 1, $filter_media, $filter_rating, $filter_komentar); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo build_url($produk_id, $i, $filter_media, $filter_rating, $filter_komentar); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo build_url($produk_id, $current_page + 1, $filter_media, $filter_rating, $filter_komentar); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>

        <?php else: ?>
            <p>Belum ada ulasan yang ditemukan.</p>
        <?php endif; ?>
    </div>
</div>