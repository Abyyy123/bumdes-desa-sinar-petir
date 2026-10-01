<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk mengkonfirmasi pembayaran.";
    header('Location: ../../login.php');
    exit();
}

$order_id = $_GET['order_id'] ?? null; // Gunakan null coalescing operator untuk handle jika order_id tidak ada
$pengguna_id = $_SESSION['pengguna_id']; // ID pelanggan yang login

// --- Bagian untuk Navbar (PASTIKAN INI ADA UNTUK MENGHINDARI NOTICE UNDEFINED VARIABLE) ---
// Inisialisasi variabel untuk navbar
$user_id = $_SESSION['pengguna_id'];
$nama_pelanggan_navbar = 'Akun';
$foto_pelanggan_navbar = '';
$search_term = ''; 

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
$total_item_keranjang_badge = 0;
$query_cart_count = "SELECT COUNT(*) AS total_count FROM keranjang_customer WHERE customer_id = ?";
$stmt_cart_count = $conn->prepare($query_cart_count);
if ($stmt_cart_count) {
    $stmt_cart_count->bind_param("i", $user_id);
    $stmt_cart_count->execute();
    $result_cart_count = $stmt_cart_count->get_result();
    if ($row_cart_count = $result_cart_count->fetch_assoc()) {
        $total_item_keranjang_badge = $row_cart_count['total_count'];
    }
    $stmt_cart_count->close();
}

// Ambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0;
$query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
$stmt_wishlist_count = $conn->prepare($query_wishlist_count);
if ($stmt_wishlist_count) {
    $stmt_wishlist_count->bind_param("i", $user_id);
    $stmt_wishlist_count->execute();
    $result_wishlist_count = $stmt_wishlist_count->get_result();
    if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
        $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
    }
    $stmt_wishlist_count->close();
}
// --- Akhir Bagian Navbar ---

// --- Validasi ID Pesanan ---
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id === 0) {
    $_SESSION['error_message'] = "ID pesanan tidak valid.";
    header('Location: pesanan_saya.php');
    exit();
}

// --- Verifikasi Kepemilikan dan Status Pesanan ---
$query_check_order = "
    SELECT
        status_pesanan
    FROM
        pesanan
    WHERE
        id = ? AND pelanggan_id = ?";
$stmt_check_order = $conn->prepare($query_check_order);
if ($stmt_check_order === false) {
    die('Error preparing query for order check: ' . $conn->error);
}
$stmt_check_order->bind_param("ii", $order_id, $pengguna_id);
$stmt_check_order->execute();
$result_check_order = $stmt_check_order->get_result();
$order_info = $result_check_order->fetch_assoc();
$stmt_check_order->close();

if (!$order_info || $order_info['status_pesanan'] !== 'selesai') {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengulas pesanan ini atau pesanan belum selesai.";
    header('Location: pesanan_saya.php');
    exit();
}

// --- Konfigurasi Batas Waktu dan Jumlah Edit Ulasan ---
$EDIT_WINDOW_DAYS = 30; // Batas waktu edit ulasan dalam hari
$MAX_EDITS = 7;        // Maksimal jumlah edit setelah ulasan pertama dikirim
$UPLOAD_DIR = '../../img/ulasan_media/'; // Direktori penyimpanan media ulasan

// Pastikan direktori upload ada
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0777, true);
}

// --- Ambil Detail Produk untuk Ulasan ---
$products_to_review = [];

$query_products = "
    SELECT
        dp.produk_id,
        dp.variasi_id, -- <--- variasi_id diambil dari detail_pesanan
        prod.nama AS nama_produk,
        prod.gambar AS gambar_produk,
        r.nama_rasa,
        w.nama_warna,
        u.nama_ukuran,
        up.id AS ulasan_id,
        up.komentar AS existing_komentar,
        up.rating AS existing_rating,
        up.tanggal_ulasan,
        up.tanggal_ulasan_pertama,
        up.jumlah_edit
    FROM
        detail_pesanan dp
    JOIN
        produk prod ON dp.produk_id = prod.id
    LEFT JOIN
        produk_variasi pv ON dp.variasi_id = pv.id -- <--- Join ke produk_variasi
    LEFT JOIN
        rasa r ON pv.rasa_id = r.id
    LEFT JOIN
        warna w ON pv.warna_id = w.id
    LEFT JOIN
        ukuran u ON pv.ukuran_id = u.id
    LEFT JOIN
        ulasan_produk up ON up.produk_id = dp.produk_id
                            AND up.pelanggan_id = ?
                            AND up.pesanan_id = dp.pesanan_id
                            AND (up.variasi_id = dp.variasi_id OR (up.variasi_id IS NULL AND dp.variasi_id IS NULL)) -- <--- Ini sangat penting!
    WHERE
        dp.pesanan_id = ?;";

$stmt_products = $conn->prepare($query_products);
if ($stmt_products === false) {
    die('Error preparing query for products list: ' . $conn->error);
}
$stmt_products->bind_param("ii", $pengguna_id, $order_id);
$stmt_products->execute();
$result_products = $stmt_products->get_result();

if ($result_products->num_rows > 0) {
    while ($row = $result_products->fetch_assoc()) {
        $variasi_detail = [];
        if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
        if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
        if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }
        $row['variasi_string'] = implode(', ', $variasi_detail);

        $row['can_edit'] = false;
        $row['edit_status_message'] = '';

        if ($row['ulasan_id']) {
            $tanggal_ulasan_pertama = new DateTime($row['tanggal_ulasan_pertama']);
            $now = new DateTime();
            $jumlah_edit_saat_ini = (int)$row['jumlah_edit'];

            $tanggal_batas_edit = clone $tanggal_ulasan_pertama;
            $tanggal_batas_edit->modify('+' . $EDIT_WINDOW_DAYS . ' days');

            $is_within_time_limit = ($now <= $tanggal_batas_edit);
            $has_remaining_edits = ($jumlah_edit_saat_ini < $MAX_EDITS);

            if ($is_within_time_limit && $has_remaining_edits) {
                $row['can_edit'] = true;
                $row['edit_status_message'] = 'Anda dapat mengedit ulasan ini (Tersisa ' . ($MAX_EDITS - $jumlah_edit_saat_ini) . ' kali edit). Batas waktu: ' . $tanggal_batas_edit->format('d M Y H:i:s');
            } else {
                $status_keterangan = [];
                if (!$is_within_time_limit) {
                    $status_keterangan[] = 'melewati batas waktu edit (' . $tanggal_batas_edit->format('d M Y H:i:s') . ')';
                }
                if (!$has_remaining_edits) {
                    $status_keterangan[] = 'sudah mencapai batas maksimal edit (' . $MAX_EDITS . ' kali)';
                }
                $row['edit_status_message'] = 'Ulasan sudah dikirim dan tidak bisa diubah karena ' . implode(' dan ', $status_keterangan) . '.';
            }

            // Ambil media yang sudah ada untuk ulasan ini
            $query_media = "SELECT jenis_media, nama_file FROM ulasan_media WHERE ulasan_id = ?";
            $stmt_media = $conn->prepare($query_media);
            if ($stmt_media) {
                $stmt_media->bind_param("i", $row['ulasan_id']);
                $stmt_media->execute();
                $result_media = $stmt_media->get_result();
                $row['existing_media'] = $result_media->fetch_all(MYSQLI_ASSOC);
                $stmt_media->close();
            } else {
                $row['existing_media'] = [];
                error_log("Error preparing media query: " . $conn->error);
            }

        } else {
            $row['can_edit'] = true;
            $row['edit_status_message'] = 'Anda dapat menambahkan ulasan untuk produk ini.';
            $row['existing_media'] = []; // Tidak ada media jika belum ada ulasan
        }
        $products_to_review[] = $row;
    }
}
$stmt_products->close();

// --- Logika Pemrosesan Form Ulasan (Ketika Form Disubmit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_success = true;
    $error_messages_for_user = [];

    foreach ($products_to_review as $product) {
        $produk_id = $product['produk_id'];
        // Pastikan variasi_id digunakan secara konsisten, termasuk untuk penamaan input form
        $variasi_id_for_form_name = $product['variasi_id'];
        $form_input_suffix = $variasi_id_for_form_name ? '_' . $variasi_id_for_form_name : '';

        $komentar_text = trim($_POST['ulasan_produk_' . $produk_id . $form_input_suffix] ?? '');
        $rating_value = isset($_POST['rating_produk_' . $produk_id . $form_input_suffix]) ? intval($_POST['rating_produk_' . $produk_id . $form_input_suffix]) : 0;

        $can_process_review_for_this_product = false;
        $current_product_info = null;
        foreach ($products_to_review as $p_check) {
            if ($p_check['produk_id'] === $produk_id && $p_check['variasi_id'] === $variasi_id_for_form_name) {
                $current_product_info = $p_check;
                if ($p_check['can_edit']) {
                    $can_process_review_for_this_product = true;
                }
                break;
            }
        }

        if (!$can_process_review_for_this_product) {
            // Ini akan menangani kasus di mana pengguna mencoba memanipulasi form untuk mengirim ulasan yang tidak dapat diedit
            // Namun, karena tombol submit disabled, ini lebih ke fallback keamanan.
            // Jangan tambahkan pesan error spesifik jika tidak ada komentar/rating/media baru, karena itu normal.
            if (!empty($komentar_text) || ($rating_value >= 1 && $rating_value <= 5) || (isset($_FILES['media_ulasan_' . $produk_id . $form_input_suffix]) && !empty($_FILES['media_ulasan_' . $produk_id . $form_input_suffix]['name'][0]))) {
                 $error_messages_for_user[] = "Ulasan untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "' tidak dapat diproses karena sudah melewati batas waktu edit atau mencapai batas maksimal edit.";
                 $review_success = false;
            }
            continue; // Lanjutkan ke produk berikutnya
        }

        // Hanya proses jika ada input (komentar, rating, ATAU media baru)
        // Pengecekan media baru lebih baik dilakukan dengan looping $_FILES['name']
        $has_new_media = false;
        $media_input_name = 'media_ulasan_' . $produk_id . $form_input_suffix;
        if (isset($_FILES[$media_input_name]) && is_array($_FILES[$media_input_name]['name'])) {
            foreach ($_FILES[$media_input_name]['name'] as $fileName) {
                if (!empty($fileName)) {
                    $has_new_media = true;
                    break;
                }
            }
        }

        if (!empty($komentar_text) || ($rating_value >= 1 && $rating_value <= 5) || $has_new_media) {

            $query_check_existing = "SELECT id, tanggal_ulasan_pertama, jumlah_edit FROM ulasan_produk WHERE pelanggan_id = ? AND pesanan_id = ? AND produk_id = ? AND (variasi_id = ? OR (variasi_id IS NULL AND ? IS NULL))";
            $stmt_check_existing = $conn->prepare($query_check_existing);
            if ($stmt_check_existing === false) {
                error_log("Error preparing check existing review query: " . $conn->error);
                $review_success = false;
                $error_messages_for_user[] = "Terjadi kesalahan sistem saat memeriksa ulasan produk.";
                continue;
            }
            // Bind variasi_id_for_form_name dua kali untuk OR (variasi_id IS NULL AND ? IS NULL)
            $stmt_check_existing->bind_param("iiisi", $pengguna_id, $order_id, $produk_id, $variasi_id_for_form_name, $variasi_id_for_form_name);
            $stmt_check_existing->execute();
            $result_check_existing = $stmt_check_existing->get_result();
            $existing_review = $result_check_existing->fetch_assoc();
            $stmt_check_existing->close();

            $ulasan_id = null;

            if (!$existing_review) {
                // --- Masukkan Ulasan Baru ---
                $query_insert_review = "INSERT INTO ulasan_produk (pelanggan_id, pesanan_id, produk_id, variasi_id, komentar, rating, tanggal_ulasan, tanggal_ulasan_pertama, jumlah_edit) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)";
                $stmt_insert_review = $conn->prepare($query_insert_review);
                if ($stmt_insert_review === false) {
                    error_log("Error preparing insert review query: " . $conn->error);
                    $review_success = false;
                    $error_messages_for_user[] = "Terjadi kesalahan sistem saat menyimpan ulasan baru.";
                    continue;
                }
                // Perhatikan penambahan 'i' untuk variasi_id
                $stmt_insert_review->bind_param("iiiisi", $pengguna_id, $order_id, $produk_id, $variasi_id_for_form_name, $komentar_text, $rating_value);
                if (!$stmt_insert_review->execute()) {
                    error_log("Error inserting product review: " . $stmt_insert_review->error);
                    $review_success = false;
                    $error_messages_for_user[] = "Gagal menyimpan ulasan untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "'.";
                } else {
                    $ulasan_id = $conn->insert_id;
                    error_log("Review inserted with ID: " . $ulasan_id);
                }
                $stmt_insert_review->close();
            } else {
                // --- Perbarui Ulasan yang Sudah Ada ---
                $ulasan_id = $existing_review['id'];
                $current_review_tanggal_pertama = new DateTime($existing_review['tanggal_ulasan_pertama']);
                $current_review_jumlah_edit = (int)$existing_review['jumlah_edit'];
                $now = new DateTime();

                $tanggal_batas_edit = clone $current_review_tanggal_pertama;
                $tanggal_batas_edit->modify('+' . $EDIT_WINDOW_DAYS . ' days');

                $is_within_time_limit_for_update = ($now <= $tanggal_batas_edit);
                $has_remaining_edits_for_update = ($current_review_jumlah_edit < $MAX_EDITS);

                if ($is_within_time_limit_for_update && $has_remaining_edits_for_update) {
                    $query_update_review = "UPDATE ulasan_produk SET komentar = ?, rating = ?, tanggal_ulasan = NOW(), jumlah_edit = jumlah_edit + 1 WHERE id = ?";
                    $stmt_update_review = $conn->prepare($query_update_review);
                    if ($stmt_update_review === false) {
                        error_log("Error preparing update review query: " . $conn->error);
                        $review_success = false;
                        $error_messages_for_user[] = "Terjadi kesalahan sistem saat memperbarui ulasan.";
                        continue;
                    }
                    $stmt_update_review->bind_param("sii", $komentar_text, $rating_value, $existing_review['id']);
                    if (!$stmt_update_review->execute()) {
                        error_log("Error updating product review: " . $stmt_update_review->error);
                        $review_success = false;
                        $error_messages_for_user[] = "Gagal memperbarui ulasan untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "'.";
                    } else {
                        error_log("Review updated with ID: " . $ulasan_id);
                    }
                    $stmt_update_review->close();
                } else {
                    $review_success = false;
                    $error_messages_for_user[] = "Ulasan untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "' tidak dapat diperbarui karena sudah melewati batas waktu atau jumlah edit.";
                }
            }

            // --- Logika Upload Media ---
            if ($ulasan_id) { // Hanya proses upload media jika ulasan berhasil disimpan/diperbarui
                $input_file_name_for_php = 'media_ulasan_' . $produk_id . $form_input_suffix;

                // Periksa apakah ada file yang diunggah untuk input ini
                if (isset($_FILES[$input_file_name_for_php]) && is_array($_FILES[$input_file_name_for_php]['name'])) {
                    $files = $_FILES[$input_file_name_for_php];
                    $total_files_uploaded_for_this_product = count(array_filter($files['name'])); // Hitung file yang benar-benar ada

                    for ($i = 0; $i < count($files['name']); $i++) {
                        // KONDISI UTAMA UNTUK MENGHILANGKAN ERROR KODE 4
                        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                            continue; // Lanjutkan ke file berikutnya dalam loop
                        } elseif ($files['error'][$i] !== UPLOAD_ERR_OK) {
                            $error_messages_for_user[] = "Error upload file untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "': " . htmlspecialchars($files['name'][$i]) . " (Kode: " . $files['error'][$i] . ")";
                            $review_success = false;
                            continue;
                        }

                        $file_name = $files['name'][$i];
                        $file_tmp_name = $files['tmp_name'][$i];
                        $file_size = $files['size'][$i];
                        $file_type = $files['type'][$i];

                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif'];
                        $allowed_video_ext = ['mp4', 'webm', 'ogg', 'mov', 'avi']; // Tambahkan ekstensi video umum

                        $is_image = in_array($file_ext, $allowed_image_ext);
                        $is_video = in_array($file_ext, $allowed_video_ext);

                        if (!$is_image && !$is_video) {
                            $error_messages_for_user[] = "Jenis file tidak didukung untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "': " . $file_name . ". Hanya JPG, JPEG, PNG, GIF, MP4, WEBM, OGG, MOV, AVI yang diizinkan.";
                            $review_success = false;
                            continue;
                        }

                        // Maksimal ukuran file: 5MB untuk gambar, 20MB untuk video
                        $max_file_size = ($is_image) ? 5 * 1024 * 1024 : 20 * 1024 * 1024;
                        if ($file_size > $max_file_size) {
                            $error_messages_for_user[] = "Ukuran file terlalu besar untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "': " . $file_name . ". Maksimal " . ($is_image ? "5MB" : "20MB") . ".";
                            $review_success = false;
                            continue;
                        }

                        $new_file_name = uniqid('ulasan_') . '.' . $file_ext;
                        $destination = $UPLOAD_DIR . $new_file_name;

                        if (move_uploaded_file($file_tmp_name, $destination)) {
                            $media_type = $is_image ? 'foto' : 'video';
                            $query_insert_media = "INSERT INTO ulasan_media (ulasan_id, jenis_media, nama_file) VALUES (?, ?, ?)";
                            $stmt_insert_media = $conn->prepare($query_insert_media);
                            if ($stmt_insert_media === false) {
                                error_log("Error preparing insert media query: " . $conn->error);
                                $review_success = false;
                                $error_messages_for_user[] = "Terjadi kesalahan sistem saat menyimpan data media.";
                                // Hapus file yang sudah terupload jika query insert gagal
                                unlink($destination);
                                continue;
                            }
                            $stmt_insert_media->bind_param("iss", $ulasan_id, $media_type, $new_file_name);
                            if (!$stmt_insert_media->execute()) {
                                error_log("Error inserting media: " . $stmt_insert_media->error);
                                $review_success = false;
                                $error_messages_for_user[] = "Gagal menyimpan media '" . htmlspecialchars($file_name) . "' untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "'.";
                                unlink($destination); // Hapus file jika gagal insert ke DB
                            }
                            $stmt_insert_media->close();
                        } else {
                            $error_messages_for_user[] = "Gagal memindahkan file '" . htmlspecialchars($file_name) . "' untuk produk '" . htmlspecialchars($current_product_info['nama_produk']) . "'.";
                            $review_success = false;
                        }
                    }
                }
            }
        }
    }

    if ($review_success) {
        $_SESSION['success_message'] = "Ulasan dan rating Anda telah berhasil disimpan!";
    } else {
        if (!empty($error_messages_for_user)) {
            $_SESSION['error_message'] = implode("<br>", $error_messages_for_user);
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan ulasan. Silakan coba lagi.";
        }
    }
    header('Location: ulas_produk.php?order_id=' . $order_id);
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulas & Rating Produk Pesanan #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
            /* General Body Styling */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Navbar Styling (Sama dengan promo.php & artikel.php) */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
            color: white !important; /* Pastikan brand juga putih */
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

        .nav-link:hover,
        .nav-link.active {
            color: #f0f0f0 !important;
            font-weight: bold;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3csvg>");
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
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
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

        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important;
        }

        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Order Page Specific Styles (dari kode Anda) */
        .order-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .order-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-body {
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
        .order-summary-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            text-transform: capitalize;
        }
        /* Update status colors as needed, pastikan 'menunggu_verifikasi' ditambahkan */
        .status-menunggu-pembayaran { background-color: #ffc107; color: #343a40; } /* Yellow */
        .status-menunggu-verifikasi { background-color: #fd7e14; color: #fff; } /* Orange */
        .status-diproses { background-color: #0dcaf0; color: #fff; } /* Cyan */
        .status-dikirim { background-color: #0d6efd; color: #fff; } /* Blue */
        .status-selesai { background-color: #198754; color: #fff; } /* Green */
        .status-dibatalkan { background-color: #dc3545; color: #fff; } /* Red */
        .status-dikembalikan { background-color: #6c757d; color: #fff; } /* Gray */

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
        .order-actions {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* Footer Styling (Sama dengan promo.php & artikel.php) */
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

        /* Specific social media icon colors */
        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

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
        /* Card utama untuk bagian ulasan */
        .review-section-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 20px;
        }

        /* Item produk individu dalam daftar ulasan */
        .product-review-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #eee;
        }

        /* Hapus border bawah untuk item terakhir */
        .product-review-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        /* Gambar produk dalam item ulasan */
        .product-review-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }

        /* Gaya dasar untuk bintang rating */
        .rating-stars label {
            font-size: 1.5em; /* Ukuran bintang */
            color: #ddd; /* Warna abu-abu default */
            cursor: pointer;
            transition: color 0.2s ease;
        }

        /* Gaya bintang saat di-hover atau sudah dipilih */
        .rating-stars input[type="radio"]:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #ffc107; /* Warna kuning untuk bintang yang aktif */
        }

        /* Sembunyikan input radio asli */
        .rating-stars input[type="radio"] {
            display: none;
        }

        /* Atur arah rating dari kanan ke kiri */
        .rating-stars {
            direction: rtl;
            display: inline-block;
        }

        /* Gaya untuk bintang yang sudah terisi saat dimuat (dari data yang ada) */
        .rating-stars label.checked-star {
            color: #ffc107;
        }

        /* Gaya untuk form control yang disabled */
        .form-control:disabled {
            background-color: #e9ecef;
        }

        /* Gaya untuk bintang yang disabled */
        .rating-stars input[type="radio"]:disabled + label {
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Gaya tambahan untuk tampilan media */
        .review-media-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .review-media-preview img, .review-media-preview video {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        /* Container untuk tombol upload media */
        .media-upload-buttons {
            display: flex;
            gap: 15px; /* Jarak antara tombol foto dan video */
            margin-top: 10px;
            flex-wrap: wrap; /* Agar responsif jika layar kecil */
        }

        /* Gaya untuk tombol upload kustom (menggantikan input file default) */
        .custom-file-upload {
            border: 1px solid #ff4500; /* Warna border sesuai tema Anda */
            display: inline-flex; /* Menggunakan flexbox untuk ikon dan teks */
            align-items: center; /* Pusatkan secara vertikal */
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            background-color: #fff;
            color: #ff4500; /* Warna teks dan ikon */
            font-weight: 600;
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
            flex-grow: 1; /* Agar tombol bisa membesar mengisi ruang */
            justify-content: center; /* Pusatkan konten horizontal */
            max-width: 200px; /* Batasi lebar agar tidak terlalu besar */
        }

        .custom-file-upload:hover {
            background-color: #ff4500;
            color: #fff;
        }

        .custom-file-upload i {
            margin-right: 8px; /* Jarak antara ikon dan teks */
            font-size: 1.2em; /* Ukuran ikon */
        }

        /* Sembunyikan input file asli */
        .custom-file-upload input[type="file"] {
            display: none;
        }

        /* Gaya untuk tombol yang dinonaktifkan */
        .custom-file-upload.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #f0f0f0;
            color: #999;
            border-color: #ccc;
        }

        .custom-file-upload.disabled:hover {
            background-color: #f0f0f0;
            color: #999;
            border-color: #ccc;
        }
        .new-media-preview, .review-media-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            border: 1px dashed #ccc; /* Optional: to show preview area */
            padding: 10px;
            border-radius: 5px;
        }

        .new-media-preview img,
        .new-media-preview video,
        .review-media-preview img,
        .review-media-preview video {
            max-width: 100px; /* Ukuran thumbnail */
            max-height: 100px; /* Ukuran thumbnail */
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .media-preview-item {
            position: relative;
            display: inline-block;
        }

        .media-preview-item .remove-media {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.8em;
            cursor: pointer;
            line-height: 1;
            z-index: 10;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <img src="../../img/logoo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
            BUMDes
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPembeli" aria-controls="navbarPembeli" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarPembeli">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../produk.php">Produk</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../kategori/kategori.php">Kategori</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../promo/promo.php">Promo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../artikel/artikel.php">Artikel</a>
                </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../wishlist.php">
                        <i class="bi bi-heart-fill"></i>
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-count">
                            <?php echo $total_item_wishlist; ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="position-relative">
                        <a class="nav-link" href="keranjang.php" id="link-keranjang">
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
                                <a href="keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan_navbar) && $foto_pelanggan_navbar && $foto_pelanggan_navbar != 'default.png'): ?>
                                <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan_navbar); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan_navbar); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item active" href="pesanan_saya.php">Pesanan Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2 class="mb-4">Ulas & Beri Rating Produk Pesanan #<?php echo htmlspecialchars($order_id); ?></h2>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="statusAlert" class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="statusAlert" class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php
    $any_product_can_edit = false;
    foreach ($products_to_review as $product) {
        if ($product['can_edit']) {
            $any_product_can_edit = true;
            break;
        }
    }
    ?>
    <?php if (empty($products_to_review)): ?>
        <div class="alert alert-info text-center" role="alert">
            Tidak ada produk yang dapat diulas pada pesanan ini.
        </div>
    <?php else: ?>
        <?php if (!$any_product_can_edit): ?>
            <div class="alert alert-info text-center mb-4" role="alert">
                Semua ulasan produk pada pesanan ini telah diselesaikan atau tidak dapat diedit lagi.
            </div>
        <?php endif; ?>
        <form action="ulas_produk.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" enctype="multipart/form-data">
            <div class="review-section-card mb-4">
                <h4 class="mb-3"><i class="fas fa-box-open me-2"></i>Produk yang Dibeli</h4>
                <?php foreach ($products_to_review as $product):
                    $is_already_reviewed = !empty($product['ulasan_id']);
                    $can_edit = $product['can_edit'];
                    $form_disabled = !$can_edit;
                    $form_input_suffix = $product['variasi_id'] ? '_' . $product['variasi_id'] : '';
                ?>
                    <div class="product-review-item"
     data-existing-photos="<?php echo count(array_filter($product['existing_media'] ?? [], function($m){ return $m['jenis_media'] === 'foto'; })); ?>"
     data-existing-videos="<?php echo count(array_filter($product['existing_media'] ?? [], function($m){ return $m['jenis_media'] === 'video'; })); ?>">
                        <?php if (!empty($product['gambar_produk'])): ?>
                            <img src="../../img/barang/<?php echo htmlspecialchars($product['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 5px;">
                                <i class="fas fa-image text-muted fa-2x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h5><?php echo htmlspecialchars($product['nama_produk']); ?></h5>
                            <?php if (!empty($product['variasi_string'])): ?>
                                <small class="text-muted mb-2 d-block"><?php echo $product['variasi_string']; ?></small>
                            <?php endif; ?>
                            <?php if ($is_already_reviewed): ?>
                                <div class="alert <?php echo $can_edit ? 'alert-info' : 'alert-warning'; ?> p-2 small" role="alert">
                                    <i class="fas <?php echo $can_edit ? 'fa-edit' : 'fa-info-circle'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($product['edit_status_message']); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success p-2 small" role="alert">
                                    <i class="fas fa-plus-circle me-1"></i> <?php echo htmlspecialchars($product['edit_status_message']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label d-block">Rating Produk:</label>
                                <div class="rating-stars">
                                    <?php
                                    $current_rating = $product['existing_rating'] ?? 0;
                                    $input_name_prefix_rating = "rating_produk_" . $product['produk_id'] . $form_input_suffix;
                                    for ($i = 5; $i >= 1; $i--):
                                        $checked = ($i == $current_rating) ? 'checked' : '';
                                    ?>
                                        <input type="radio"
                                            id="star<?php echo $i; ?>_prod_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                            name="<?php echo $input_name_prefix_rating; ?>"
                                            value="<?php echo $i; ?>"
                                            <?php echo $checked; ?>
                                            <?php echo $form_disabled ? 'disabled' : ''; ?>>
                                        <label for="star<?php echo $i; ?>_prod_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="ulasan_produk_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>" class="form-label">Ulasan Produk:</label>
                                <textarea class="form-control"
                                    id="ulasan_produk_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                    name="ulasan_produk_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                    rows="2"
                                    placeholder="Bagaimana pendapat Anda tentang produk ini..."
                                    <?php echo $form_disabled ? 'disabled' : ''; ?>><?php echo htmlspecialchars($product['existing_komentar'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block">Unggah Foto/Video (Opsional):</label>
                                <div class="media-upload-buttons">
                                    <label for="media_ulasan_foto_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>" class="custom-file-upload <?php echo $form_disabled ? 'disabled' : ''; ?>">
                                        <input type="file"
                                            id="media_ulasan_foto_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                            name="media_ulasan_foto_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>[]"
                                            accept="image/*"
                                            <?php echo $form_disabled ? 'disabled' : ''; ?>
                                            multiple> <i class="fas fa-camera"></i> Tambah Foto <span class="photo-count">(0/5)</span>
                                    </label>

                                    <label for="media_ulasan_video_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>" class="custom-file-upload <?php echo $form_disabled ? 'disabled' : ''; ?>">
                                        <input type="file"
                                            id="media_ulasan_video_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                            name="media_ulasan_video_helper_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>[]"
                                            accept="video/*"
                                            <?php echo $form_disabled ? 'disabled' : ''; ?>
                                            multiple> <i class="fas fa-video"></i> Tambahkan Video <span class="video-count">(0/1)</span>
                                    </label>
                                </div>
                                <small class="form-text text-muted">Maks. 5MB untuk gambar, 20MB untuk video.</small>
                                <div class="new-media-preview mt-2"
                                    id="new_media_preview_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>">
                                </div>
                                <input type="file"
                                    id="all_media_ulasan_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>"
                                    name="media_ulasan_<?php echo $product['produk_id']; ?><?php echo $form_input_suffix; ?>[]"
                                    style="display: none;"
                                    multiple>
                            </div>
                            <?php if (!empty($product['existing_media'])): ?>
                                <div class="review-media-preview">
                                    <strong>Media Terunggah:</strong>
                                    <?php foreach ($product['existing_media'] as $media): ?>
                                        <?php if ($media['jenis_media'] === 'foto'): ?>
                                            <img src="../../img/ulasan_media/<?php echo htmlspecialchars($media['nama_file']); ?>" alt="Foto Ulasan">
                                        <?php elseif ($media['jenis_media'] === 'video'): ?>
                                            <video controls muted>
                                                <source src="../../img/ulasan_media/<?php echo htmlspecialchars($media['nama_file']); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($any_product_can_edit): ?>
                <button type="submit" name="submit_review" class="btn btn-primary mt-3 me-2 mb-4" style="background-color: #ff4500; border-color: #ff4500;"><i class="fas fa-paper-plane me-2"></i>Kirim Ulasan & Rating Produk</button>
            <?php endif; ?>
        </form>
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
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();
    
    // Fungsi formatRupiah yang sudah ada
    function formatRupiah(angka) {
        let number = parseFloat(angka);
        if (isNaN(number)) {
            return ""; // Mengembalikan string kosong jika bukan angka
        }
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    }

    // Fungsi untuk membersihkan format Rupiah menjadi angka murni
    function cleanRupiah(rupiahString) {
        return rupiahString.replace(/\./g, '').replace(/,/g, ''); // Hapus titik dan koma
    }

    // Fungsi untuk memperbarui jumlah item di keranjang pada navbar via AJAX
    function muatJumlahKeranjangNav() {
        $.ajax({
            url: 'get_cart_count.php',
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

    // Fungsi untuk memuat detail item di dropdown keranjang
    function muatIsiKeranjangDropdown() {
        const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
        const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
        const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

        jumlahProdukLainnyaSpan.style.display = 'none';
        jumlahProdukLainnyaSpan.textContent = '';

        fetch('ambil_keranjang_sementara.php')
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
                                <img src="../../img/barang/${item.gambar_produk}" alt="${item.nama_produk}" class="img-fluid rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
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

    // Event listener untuk menampilkan/menyembunyikan dropdown keranjang
    document.addEventListener('DOMContentLoaded', () => {
        const linkKeranjang = document.getElementById('link-keranjang');
        const dropdownKeranjang = document.getElementById('dropdown-keranjang');

        muatJumlahKeranjangNav();

        if (linkKeranjang && dropdownKeranjang) {
            linkKeranjang.addEventListener('mouseenter', () => {
                muatIsiKeranjangDropdown();
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

        // --- Fungsi untuk copyToClipboard ---
        function copyToClipboard(elementId) {
            var copyText = document.getElementById(elementId);
            if (copyText) {
                var textToCopy = copyText.textContent || copyText.innerText;
                navigator.clipboard.writeText(textToCopy).then(function() {
                    alert('Nomor rekening/akun berhasil disalin: ' + textToCopy);
                }, function(err) {
                    console.error('Gagal menyalin teks: ', err);
                    alert('Gagal menyalin nomor rekening/akun. Silakan salin manual.');
                });
            }
        }
        window.copyToClipboard = copyToClipboard;


        // --- Fungsi untuk menampilkan/menyembunyikan input 'Lainnya' ---
        function toggleOtherBankInput() {
            var bankSelect = document.getElementById('bank_pengirim');
            var otherBankGroup = document.getElementById('bank_pengirim_lainnya_group');
            var otherBankInput = document.getElementById('bank_pengirim_lainnya');

            if (bankSelect.value === 'Lainnya') {
                otherBankGroup.style.display = 'block';
                otherBankInput.setAttribute('required', 'required');
            } else {
                otherBankGroup.style.display = 'none';
                otherBankInput.removeAttribute('required');
                otherBankInput.value = '';
            }
        }
        toggleOtherBankInput(); 

        const bankPengirimSelect = document.getElementById('bank_pengirim');
        if (bankPengirimSelect) {
            bankPengirimSelect.addEventListener('change', toggleOtherBankInput);
        }

        // --- JavaScript baru untuk format jumlah transfer di input text ---
        const jumlahTransferInput = document.getElementById('jumlah_transfer');

        // Fungsi untuk memformat input secara real-time
        jumlahTransferInput.addEventListener('input', function(e) {
            let value = e.target.value;
            // Hapus semua karakter non-digit kecuali tanda koma (untuk desimal, jika diperlukan)
            // Untuk Rupiah, kita biasanya tidak pakai desimal di ribuan, jadi hapus semua non-digit kecuali angka
            value = value.replace(/\D/g, ''); 
            
            // Konversi ke angka untuk memformat
            let number = parseInt(value, 10);
            
            // Jika bukan angka valid, biarkan kosong atau 0
            if (isNaN(number)) {
                e.target.value = "";
                return;
            }

            // Format angka dengan pemisah ribuan
            e.target.value = formatRupiah(number);
        });

        // Tangani saat form disubmit untuk membersihkan format
        document.querySelector('form').addEventListener('submit', function() {
            const currentJumlahTransfer = jumlahTransferInput.value;
            jumlahTransferInput.value = cleanRupiah(currentJumlahTransfer);
        });

        // Set nilai awal input dengan format Rupiah saat dimuat (sudah di PHP, tapi ini untuk memastikan)
        jumlahTransferInput.value = formatRupiah(cleanRupiah(jumlahTransferInput.value));

    });



    // Script for auto-dismiss alert
    document.addEventListener('DOMContentLoaded', function() {
    // --- Auto-dismiss alert ---
    var statusAlert = document.getElementById('statusAlert');
    if (statusAlert) {
        var bsAlert = new bootstrap.Alert(statusAlert);
        setTimeout(function() {
            bsAlert.close();
        }, 5000); // Alert akan hilang setelah 5 detik
    }

    // --- Script for filling rating stars on load and dynamic changes ---
    document.querySelectorAll('.rating-stars').forEach(function(starContainer) {
        function updateStarDisplay() {
            const checkedRadio = starContainer.querySelector('input[type="radio"]:checked');
            let labels = Array.from(starContainer.querySelectorAll('label'));

            const selectedValue = checkedRadio ? parseInt(checkedRadio.value) : 0;
            labels.forEach(label => {
                const labelForStar = parseInt(label.htmlFor.match(/star(\d+)/)[1]);
                if (labelForStar <= selectedValue) {
                    label.classList.add('checked-star');
                } else {
                    label.classList.remove('checked-star');
                }
            });
        }

        updateStarDisplay(); // Panggil saat halaman dimuat
        starContainer.querySelectorAll('input[type="radio"]').forEach(function(radio) {
            radio.addEventListener('change', updateStarDisplay); // Panggil saat rating berubah
        });
    });

    // --- Script for Media Preview and Count Updates ---

    // Map untuk menyimpan objek DataTransfer yang berisi file-file terpilih untuk SETIAP PRODUK
    const allProductMediaFiles = new Map(); // Kunci: product_id_variasi_id, Nilai: DataTransfer object

    document.querySelectorAll('.product-review-item').forEach(function(productReviewItem) {
        // ID unik untuk produk ini, diambil dari nama input rating
        const ratingInputName = productReviewItem.querySelector('[name^="rating_produk_"]');
        const productIdIdentifier = ratingInputName ? ratingInputName.name.replace(/rating_produk_/, '') : null;
        
        if (!productIdIdentifier) {
            console.warn("Could not determine product ID for a review item. Skipping media script.");
            return;
        }

        // Input tersembunyi utama yang akan mengirim semua file ke PHP
        const photoInputHidden = productReviewItem.querySelector(`#all_media_ulasan_${productIdIdentifier}`); 
        // Input pembantu untuk FOTO (digunakan oleh tombol "Tambah Foto")
        const photoInputHelper = productReviewItem.querySelector(`#media_ulasan_foto_helper_${productIdIdentifier}`); 
        // Input pembantu untuk VIDEO (digunakan oleh tombol "Tambah Video")
        const videoInputHelper = productReviewItem.querySelector(`#media_ulasan_video_helper_${productIdIdentifier}`); 

        const photoCountSpan = photoInputHelper ? photoInputHelper.closest('label').querySelector('.photo-count') : null;
        const videoCountSpan = videoInputHelper ? videoInputHelper.closest('label').querySelector('.video-count') : null;
        const previewContainer = productReviewItem.querySelector(`.new-media-preview#new_media_preview_${productIdIdentifier}`);

        // Pastikan semua elemen yang dibutuhkan ada
        if (!photoInputHidden || !photoInputHelper || !videoInputHelper || !previewContainer) {
            console.warn(`Missing necessary media inputs or preview container for product: ${productIdIdentifier}. Skipping media script for this product.`);
            return;
        }

        // Inisialisasi DataTransfer object untuk produk ini jika belum ada
        if (!allProductMediaFiles.has(productIdIdentifier)) {
            allProductMediaFiles.set(productIdIdentifier, new DataTransfer());
        }
        let currentDataTransfer = allProductMediaFiles.get(productIdIdentifier);

        const maxPhotos = 5;
        const maxVideos = 1;

        // Ambil jumlah media yang sudah ada dari data-attributes
        const existingPhotosCount = parseInt(productReviewItem.dataset.existingPhotos || '0');
        const existingVideosCount = parseInt(productReviewItem.dataset.existingVideos || '0');

        // Simpan status disabled awal dari PHP untuk input-input pembantu (yang sebenarnya adalah disabled pada label)
        const isPhotoInputInitiallyDisabled = photoInputHelper.disabled;
        const isVideoInputInitiallyDisabled = videoInputHelper.disabled;

        // Fungsi untuk merender pratinjau media dan memperbarui hitungan
        function renderMediaPreviews() {
            previewContainer.innerHTML = ''; // Hapus pratinjau yang sudah ada

            let newPhotosCount = 0;
            let newVideosCount = 0;

            // Sort files to keep videos at the end (optional, for display consistency)
            const sortedFiles = Array.from(currentDataTransfer.files).sort((a, b) => {
                if (a.type.startsWith('video/') && !b.type.startsWith('video/')) return 1;
                if (!a.type.startsWith('video/') && b.type.startsWith('video/')) return -1;
                return 0;
            });

            sortedFiles.forEach((file, index) => {
                const fileType = file.type;
                let mediaElement;

                if (fileType.startsWith('image/')) {
                    mediaElement = document.createElement('img');
                    mediaElement.alt = "Preview Foto";
                    newPhotosCount++;
                } else if (fileType.startsWith('video/')) {
                    mediaElement = document.createElement('video');
                    mediaElement.controls = true;
                    mediaElement.muted = true;
                    mediaElement.autoplay = false;
                    mediaElement.loop = false;
                    mediaElement.alt = "Preview Video";
                    newVideosCount++;
                }

                if (mediaElement) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        mediaElement.src = e.target.result;
                        mediaElement.classList.add('img-thumbnail'); // Tambahkan kelas untuk styling
                    };
                    reader.readAsDataURL(file);

                    const wrapper = document.createElement('div');
                    wrapper.classList.add('media-preview-item');
                    wrapper.appendChild(mediaElement);

                    // Tombol hapus media
                    const removeButton = document.createElement('span');
                    removeButton.classList.add('remove-media');
                    removeButton.innerHTML = '&times;';
                    removeButton.title = 'Hapus Media';
                    removeButton.addEventListener('click', function() {
                        // Untuk menghapus file dari DataTransfer, kita harus membuat DataTransfer baru
                        const filesArray = Array.from(currentDataTransfer.files);
                        
                        // Temukan index file yang akan dihapus berdasarkan objek file-nya
                        // Kita tidak bisa hanya splice(index) karena index bisa berubah setelah render ulang
                        const fileToRemoveIndex = filesArray.findIndex(f => f === file);
                        if (fileToRemoveIndex > -1) {
                            filesArray.splice(fileToRemoveIndex, 1);
                        }

                        const newDataTransfer = new DataTransfer();
                        filesArray.forEach(f => newDataTransfer.items.add(f));
                        currentDataTransfer = newDataTransfer; // Perbarui referensi DataTransfer

                        allProductMediaFiles.set(productIdIdentifier, currentDataTransfer); // Perbarui Map

                        // Perbarui properti .files pada input file tersembunyi
                        photoInputHidden.files = currentDataTransfer.files;
                        
                        renderMediaPreviews(); // Render ulang untuk memperbarui UI dan hitungan
                    });
                    wrapper.appendChild(removeButton);
                    previewContainer.appendChild(wrapper);
                }
            });

            // HITUNG TOTAL FOTO/VIDEO (YANG SUDAH ADA DI DB + YANG BARU DIPILIH DI UI)
            const totalPhotos = existingPhotosCount + newPhotosCount;
            const totalVideos = existingVideosCount + newVideosCount;

            // Perbarui hitungan yang ditampilkan pada tombol
            if (photoCountSpan) {
                photoCountSpan.textContent = `(${totalPhotos}/${maxPhotos})`;
            }
            if (videoCountSpan) {
                videoCountSpan.textContent = `(${totalVideos}/${maxVideos})`;
            }

            // Kelola status disabled tombol upload media, dengan mempertimbangkan status disabled awal dari PHP
            if (photoInputHelper.closest('label')) {
                if (totalPhotos >= maxPhotos || isPhotoInputInitiallyDisabled) {
                    photoInputHelper.disabled = true;
                    photoInputHelper.closest('label').classList.add('disabled');
                } else {
                    photoInputHelper.disabled = false;
                    photoInputHelper.closest('label').classList.remove('disabled');
                }
            }

            if (videoInputHelper.closest('label')) {
                if (totalVideos >= maxVideos || isVideoInputInitiallyDisabled) {
                    videoInputHelper.disabled = true;
                    videoInputHelper.closest('label').classList.add('disabled');
                } else {
                    videoInputHelper.disabled = false;
                    videoInputHelper.closest('label').classList.remove('disabled');
                }
            }
        }

        // Event listener untuk input foto pembantu
        if (photoInputHelper) {
            photoInputHelper.addEventListener('change', function(event) {
                const filesToAdd = Array.from(event.target.files);
                event.target.value = ''; // Reset input helper agar bisa upload file yang sama lagi jika user mau

                let photosAddedThisClick = 0;
                for (const file of filesToAdd) {
                    if (file.type.startsWith('image/')) {
                        const currentNewPhotosInTransfer = Array.from(currentDataTransfer.files).filter(f => f.type.startsWith('image/')).length;
                        if (existingPhotosCount + currentNewPhotosInTransfer + photosAddedThisClick < maxPhotos) {
                            currentDataTransfer.items.add(file);
                            photosAddedThisClick++;
                        } else {
                            alert(`Batas maksimal foto (${maxPhotos}) tercapai (termasuk yang sudah ada di database). Tidak dapat menambahkan lebih banyak foto.`);
                            break; // Hentikan loop jika batas tercapai
                        }
                    } else {
                        alert("Hanya file gambar yang diizinkan untuk tombol 'Tambah Foto'.");
                    }
                }
                photoInputHidden.files = currentDataTransfer.files; // UPDATE INPUT TERSEMBUNYI
                renderMediaPreviews();
            });
        }

        // Event listener untuk input video pembantu
        if (videoInputHelper) {
            videoInputHelper.addEventListener('change', function(event) {
                const filesToAdd = Array.from(event.target.files);
                event.target.value = ''; // Reset input helper

                if (filesToAdd.length > 0 && filesToAdd[0].type.startsWith('video/')) {
                    // Cek apakah sudah ada video baru di DataTransfer
                    const currentNewVideosInTransfer = Array.from(currentDataTransfer.files).filter(f => f.type.startsWith('video/')).length;
                    
                    if (existingVideosCount + currentNewVideosInTransfer < maxVideos) {
                        // Jika belum ada video baru dan total video (lama+baru) tidak melebihi batas
                        // Hapus video yang sudah ada di DataTransfer (yang baru saja ditambahkan oleh user) sebelum menambah yang baru
                        // Ini memastikan hanya ada 1 video baru yang bisa diunggah
                        for (let i = currentDataTransfer.items.length - 1; i >= 0; i--) {
                            if (currentDataTransfer.items[i].getAsFile().type.startsWith('video/')) {
                                currentDataTransfer.items.remove(i);
                            }
                        }
                        currentDataTransfer.items.add(filesToAdd[0]);
                    } else {
                        alert(`Batas maksimal video (${maxVideos}) tercapai (termasuk yang sudah ada di database). Hanya satu video yang diizinkan per ulasan.`);
                    }
                } else if (filesToAdd.length > 0) {
                    alert("Hanya file video yang diizinkan untuk tombol 'Tambahkan Video'.");
                }
                photoInputHidden.files = currentDataTransfer.files; // UPDATE INPUT TERSEMBUNYI
                renderMediaPreviews();
            });
        }

        // Panggil renderMediaPreviews saat halaman dimuat pertama kali
        renderMediaPreviews();
    });
});
</script>
</body>
</html>