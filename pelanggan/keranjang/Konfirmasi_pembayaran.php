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


// Cek apakah order_id diberikan melalui parameter GET
if ($order_id === null) {
    $_SESSION['error_message'] = "ID Pesanan tidak ditemukan.";
    header('Location: pesanan_saya.php');
    exit();
}

// Ambil detail pesanan dari tabel 'pesanan' untuk validasi, mendapatkan total harga, dan metode pembayaran
$query_check_order = "SELECT status_pesanan, total_harga, metode_pembelian FROM pesanan WHERE id = ? AND pelanggan_id = ?";
$stmt_check_order = $conn->prepare($query_check_order);
if ($stmt_check_order === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem: " . htmlspecialchars($conn->error);
    error_log("Error preparing check order query: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}
$stmt_check_order->bind_param("ii", $order_id, $pengguna_id);
$stmt_check_order->execute();
$result_check_order = $stmt_check_order->get_result();
$order_data = $result_check_order->fetch_assoc();
$stmt_check_order->close();

if (!$order_data) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: pesanan_saya.php');
    exit();
}

// Cek status pesanan. Jika sudah selesai, dikirim, atau dibatalkan, tidak bisa konfirmasi lagi.
if ($order_data['status_pesanan'] === 'selesai' || $order_data['status_pesanan'] === 'dikirim' || $order_data['status_pesanan'] === 'dibatalkan') {
    $_SESSION['error_message'] = "Pesanan ini sudah tidak dapat dikonfirmasi pembayarannya. Status saat ini: " . htmlspecialchars($order_data['status_pesanan']);
    header('Location: pesanan_saya.php');
    exit();
}

$total_harga_pesanan = $order_data['total_harga'];
$metode_pembayaran_pesanan = $order_data['metode_pembelian']; // Ambil metode pembayaran yang dipilih untuk pesanan ini

// Ambil Informasi Rekening Bank Penjual yang Sesuai dengan Metode Pembayaran Pesanan
$rekening_tujuan_pembayaran = null; // Akan menyimpan data rekening tujuan yang spesifik

// Cek apakah metode pembayaran pesanan bukan 'cod'
if (strtolower($metode_pembayaran_pesanan) !== 'cod') {
    // Cari ID metode pembayaran di tabel metode_pembayaran
    $query_get_method_id = "SELECT id FROM metode_pembayaran WHERE nama_metode = ?";
    $stmt_get_method_id = $conn->prepare($query_get_method_id);
    if ($stmt_get_method_id === false) {
        error_log("Error preparing get method ID query: " . $conn->error);
    } else {
        $stmt_get_method_id->bind_param("s", $metode_pembayaran_pesanan);
        $stmt_get_method_id->execute();
        $result_method_id = $stmt_get_method_id->get_result();
        $method_id_data = $result_method_id->fetch_assoc();
        $stmt_get_method_id->close();

        if ($method_id_data) {
            $metode_pembayaran_id_db = $method_id_data['id'];

            // Ambil detail rekening penjual yang aktif dan sesuai dengan metode pembayaran pesanan
            $query_rekening_penjual = "
                SELECT spp.id AS id_pengaturan, mp.nama_metode AS nama_metode, spp.detail_akun AS nomor_rekening, spp.nama_pemilik_akun AS nama_pemilik
                FROM pengaturan_pembayaran_penjual spp
                JOIN metode_pembayaran mp ON spp.metode_pembayaran_id = mp.id
                WHERE spp.aktif_penjual = 1 AND spp.metode_pembayaran_id = ?
                LIMIT 1
            ";
            $stmt_rekening_penjual = $conn->prepare($query_rekening_penjual);
            if ($stmt_rekening_penjual === false) {
                error_log("Error preparing get rekening penjual query: " . $conn->error);
            } else {
                $stmt_rekening_penjual->bind_param("i", $metode_pembayaran_id_db);
                $stmt_rekening_penjual->execute();
                $result_rekening_penjual = $stmt_rekening_penjual->get_result();
                $rekening_tujuan_pembayaran = $result_rekening_penjual->fetch_assoc();
                $stmt_rekening_penjual->close();

                if (!$rekening_tujuan_pembayaran) {
                    $_SESSION['error_message'] = "Informasi rekening tujuan untuk metode pembayaran ini tidak ditemukan atau tidak aktif. Silakan hubungi admin.";
                    error_log("No active payment account found for method: " . $metode_pembayaran_pesanan);
                }
            }
        } else {
            $_SESSION['error_message'] = "Metode pembayaran pesanan ('" . htmlspecialchars($metode_pembayaran_pesanan) . "') tidak valid atau tidak dikenali.";
            error_log("Payment method from order not found in metode_pembayaran table: " . $metode_pembayaran_pesanan);
        }
    }
}

// Ambil Daftar Metode Pembayaran untuk Dropdown Pengirim
$metode_pengirim_options = [];
$query_metode_pengirim = "SELECT nama_metode FROM metode_pembayaran WHERE nama_metode != 'COD' ORDER BY nama_metode ASC";
$result_metode_pengirim = $conn->query($query_metode_pengirim);
if ($result_metode_pengirim) {
    while ($row = $result_metode_pengirim->fetch_assoc()) {
        $metode_pengirim_options[] = $row['nama_metode'];
    }
    $result_metode_pengirim->free();
} else {
    error_log("Error fetching sender payment methods: " . $conn->error);
}


// Inisialisasi variabel untuk nilai form (jika ada error, nilai ini akan diisi kembali)
$nama_pengirim_val = '';
$bank_pengirim_val = ''; // Ini akan disimpan di kolom 'metode_pembayaran' tabel 'pembayaran'
$jumlah_transfer_val = $total_harga_pesanan;
$tanggal_transfer_val = date('Y-m-d');
$catatan_val = '';
$errors = []; // Inisialisasi array error

// Variabel untuk menyimpan nilai 'lainnya' jika dipilih
$bank_pengirim_lainnya_val = '';

// Proses ketika form dikirim (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengirim = htmlspecialchars(trim($_POST['nama_pengirim']));
    $bank_pengirim_selected = htmlspecialchars(trim($_POST['bank_pengirim'])); // Nilai dari dropdown
    $bank_pengirim_lainnya = isset($_POST['bank_pengirim_lainnya']) ? htmlspecialchars(trim($_POST['bank_pengirim_lainnya'])) : ''; // Nilai dari input 'lainnya'

    // Tentukan nilai akhir untuk $bank_pengirim yang akan disimpan ke DB
    $bank_pengirim_to_db = '';
    if ($bank_pengirim_selected === 'Lainnya') {
        $bank_pengirim_to_db = $bank_pengirim_lainnya;
        $bank_pengirim_lainnya_val = $bank_pengirim_lainnya; // Untuk mengisi kembali form
    } else {
        $bank_pengirim_to_db = $bank_pengirim_selected;
        $bank_pengirim_lainnya_val = ''; // Reset jika bukan 'Lainnya'
    }

    // Untuk mengisi kembali dropdown setelah POST, simpan nilai yang dipilih
    $bank_pengirim_val = $bank_pengirim_selected;

    // --- PENTING: Bersihkan format Rupiah dari inputan jumlah_transfer sebelum validasi dan penyimpanan ---
    // Pastikan nilai yang diambil dari POST adalah string, kemudian bersihkan
    $raw_jumlah_transfer_string = $_POST['jumlah_transfer'];
    $cleaned_jumlah_transfer_string = str_replace('.', '', $raw_jumlah_transfer_string); // Hapus titik
    $jumlah_transfer = filter_var($cleaned_jumlah_transfer_string, FILTER_VALIDATE_FLOAT); // Validasi sebagai float

    $tanggal_transfer = htmlspecialchars(trim($_POST['tanggal_transfer']));
    $catatan = htmlspecialchars(trim($_POST['catatan'])); // Ini akan masuk ke kolom 'keterangan'

    // Simpan nilai input lain untuk ditampilkan kembali jika ada error
    // IMPORTANT: $jumlah_transfer_val harus dalam format "bersih" atau disiapkan untuk format ulang di HTML
    $nama_pengirim_val = $nama_pengirim;
    // Kita akan format $jumlah_transfer_val saat ditampilkan di input untuk "value" atribut
    $jumlah_transfer_val = $jumlah_transfer; 
    $tanggal_transfer_val = $tanggal_transfer;
    $catatan_val = $catatan;

    // Validasi input
    if (empty($nama_pengirim)) { $errors[] = "Nama pengirim tidak boleh kosong."; }
    
    // Validasi untuk bank_pengirim (dropdown atau input 'Lainnya')
    if (empty($bank_pengirim_selected)) {
        $errors[] = "Bank/Platform pengirim tidak boleh kosong.";
    } elseif ($bank_pengirim_selected === 'Lainnya' && empty($bank_pengirim_lainnya)) {
        $errors[] = "Mohon masukkan nama bank/platform lainnya.";
    } elseif ($bank_pengirim_selected !== 'Lainnya' && !in_array($bank_pengirim_selected, $metode_pengirim_options)) {
        $errors[] = "Pilihan Bank/Platform pengirim tidak valid.";
    }

    // Validasi $jumlah_transfer setelah dibersihkan
    if ($jumlah_transfer === false || $jumlah_transfer <= 0) { $errors[] = "Jumlah transfer tidak valid."; }
    if (empty($tanggal_transfer)) { $errors[] = "Tanggal transfer tidak boleh kosong."; }

    // Validasi jumlah transfer agar sesuai dengan total harga pesanan (toleransi kecil bisa ditambahkan)
    if (abs($jumlah_transfer - $total_harga_pesanan) > 1000) {
        $errors[] = "Jumlah transfer tidak sesuai dengan total harga pesanan. Mohon periksa kembali.";
    }

    $bukti_transfer_path = null;
    // Bukti transfer hanya wajib jika bukan COD
    if (strtolower($metode_pembayaran_pesanan) !== 'cod') {
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../img/bukti_pembayaran/'; // Pastikan direktori ini ada dan writable
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
            }

            $file_extension = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                $errors[] = "Format file tidak didukung. Hanya JPG, JPEG, PNG, GIF, PDF yang diperbolehkan.";
            }

            $max_file_size = 5 * 1024 * 1024; // 5 MB
            if ($_FILES['bukti_transfer']['size'] > $max_file_size) {
                $errors[] = "Ukuran file terlalu besar. Maksimal 5 MB.";
            }

            $new_file_name = uniqid('bukti_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (empty($errors)) {
                if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target_file)) {
                    $bukti_transfer_path = 'bukti_pembayaran/' . $new_file_name; // Simpan path relatif ke database
                } else {
                    $errors[] = "Gagal mengupload bukti transfer. Kode Error: " . $_FILES['bukti_transfer']['error'];
                    error_log("File upload failed for order {$order_id}. Error: " . $_FILES['bukti_transfer']['error']);
                }
            }
        } else {
            // Only add this error if it's not COD, and no file was uploaded
            if (strtolower($metode_pembayaran_pesanan) !== 'cod') {
                $errors[] = "Bukti transfer harus diunggah.";
                if ($_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Error upload file: " . $_FILES['bukti_transfer']['error'];
                }
            }
        }
    }


    // Jika tidak ada error validasi, simpan ke database
    if (empty($errors)) {
        // Mulai transaksi database untuk memastikan atomicity
        $conn->begin_transaction();
        try {
            // 1. Simpan/Update detail pembayaran ke tabel `pembayaran`
            // Periksa apakah sudah ada entri pembayaran untuk pesanan ini
            $query_check_payment_entry = "SELECT id FROM pembayaran WHERE pesanan_id = ?";
            $stmt_check_payment_entry = $conn->prepare($query_check_payment_entry);
            if ($stmt_check_payment_entry === false) {
                throw new Exception("Gagal menyiapkan statement cek pembayaran: " . $conn->error);
            }
            $stmt_check_payment_entry->bind_param("i", $order_id);
            $stmt_check_payment_entry->execute();
            $result_payment_entry = $stmt_check_payment_entry->get_result();
            $existing_payment_id = null;
            if ($row = $result_payment_entry->fetch_assoc()) {
                $existing_payment_id = $row['id'];
            }
            $stmt_check_payment_entry->close();

            // Set bukti_transfer_path to NULL if COD, as no file is expected
            if (strtolower($metode_pembayaran_pesanan) === 'cod') {
                $bukti_transfer_path = null;
            }


            if ($existing_payment_id) {
                // Jika sudah ada, update entri pembayaran yang sudah ada
                $query_update_pembayaran = "
                    UPDATE pembayaran SET
                        tanggal_pembayaran = ?,
                        metode_pembayaran = ?,
                        jumlah_bayar = ?,
                        status_pembayaran = 'menunggu_konfirmasi',
                        bukti_transfer = ?,
                        keterangan = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND pesanan_id = ?
                ";
                $stmt_update_pembayaran = $conn->prepare($query_update_pembayaran);
                if ($stmt_update_pembayaran === false) {
                    throw new Exception("Gagal menyiapkan statement update pembayaran: " . $conn->error);
                }
                $stmt_update_pembayaran->bind_param("ssdssii", $tanggal_transfer, $bank_pengirim_to_db, $jumlah_transfer, $bukti_transfer_path, $catatan, $existing_payment_id, $order_id);

                if (!$stmt_update_pembayaran->execute()) {
                    throw new Exception("Gagal mengupdate detail pembayaran: " . $stmt_update_pembayaran->error);
                }
                $stmt_update_pembayaran->close();

            } else {
                // Jika belum ada, masukkan entri pembayaran baru
                $query_insert_pembayaran = "
                    INSERT INTO pembayaran (pesanan_id, tanggal_pembayaran, metode_pembayaran, jumlah_bayar, status_pembayaran, bukti_transfer, keterangan)
                    VALUES (?, ?, ?, ?, 'menunggu_konfirmasi', ?, ?)
                ";
                $stmt_insert_pembayaran = $conn->prepare($query_insert_pembayaran);
                if ($stmt_insert_pembayaran === false) {
                    throw new Exception("Gagal menyiapkan statement insert pembayaran: " . $conn->error);
                }
                $stmt_insert_pembayaran->bind_param("isdsss", $order_id, $tanggal_transfer, $bank_pengirim_to_db, $jumlah_transfer, $bukti_transfer_path, $catatan);

                if (!$stmt_insert_pembayaran->execute()) {
                    throw new Exception("Gagal menyimpan detail pembayaran: " . $stmt_insert_pembayaran->error);
                }
                $stmt_insert_pembayaran->close();
            }

            // 2. Update status pesanan di tabel `pesanan` menjadi 'menunggu_verifikasi'
            $new_order_status = 'menunggu_verifikasi';
            $query_update_order_status = "UPDATE pesanan SET status_pesanan = ? WHERE id = ?";
            $stmt_update_order_status = $conn->prepare($query_update_order_status);
            if ($stmt_update_order_status === false) {
                throw new Exception("Gagal menyiapkan statement update status pesanan: " . $conn->error);
            }
            $stmt_update_order_status->bind_param("si", $new_order_status, $order_id);

            if (!$stmt_update_order_status->execute()) {
                throw new Exception("Gagal mengupdate status pesanan: " . $stmt_update_order_status->error);
            }
            $stmt_update_order_status->close();

            $conn->commit(); // Commit transaksi jika semua berhasil
            $_SESSION['success_message'] = "Konfirmasi pembayaran untuk pesanan #{$order_id} berhasil dikirim. Pesanan Anda akan segera diverifikasi.";
            header('Location: pesanan_saya.php');
            exit();

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaksi jika ada error
            $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan konfirmasi pembayaran: " . $e->getMessage();
            error_log("Error konfirmasi_pembayaran: " . $e->getMessage());
            // Hapus file bukti transfer yang sudah diupload jika terjadi error database
            if ($bukti_transfer_path && file_exists('../../img/bukti_pembayaran/' . $bukti_transfer_path)) {
                unlink('../../img/bukti_pembayaran/' . $bukti_transfer_path);
            }
        } finally {
            // Tutup koneksi di bagian akhir, baik berhasil maupun gagal
            if ($conn && $conn->ping()) {
                $conn->close();
            }
        }
    } else {
        // Jika ada error validasi, simpan pesan error ke session
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
} else {
    // Jika ini adalah GET request, bersihkan pesan error/sukses sebelumnya
    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);
}

// Tutup koneksi jika belum ditutup (misalnya jika ini GET request awal)
// (Koneksi sudah ditutup di blok finally jika POST request, tapi aman untuk memastikannya)
if ($conn && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran Pesanan #<?php echo htmlspecialchars($order_id); ?></title>
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
        .form-label {
            font-weight: bold;
        }
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            font-weight: bold;
        }
        .copy-button {
            cursor: pointer;
            text-decoration: underline;
        }
        /* Style untuk input 'lainnya' agar tersembunyi secara default */
        #bank_pengirim_lainnya_group {
            display: none;
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
<div class="container mt-5 mb-5">
    <h2 class="mb-4">Konfirmasi Pembayaran Pesanan #<?php echo htmlspecialchars($order_id); ?></h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Total yang harus dibayar: <strong>Rp <?php echo number_format($total_harga_pesanan, 0, ',', '.'); ?></strong>
    </div>

    <?php if (strtolower($metode_pembayaran_pesanan) === 'cod'): ?>
        <div class="alert alert-success mb-4">
            Metode pembayaran untuk pesanan ini adalah **Cash On Delivery (COD)**. Anda tidak perlu melakukan transfer bank. Pembayaran akan dilakukan saat pesanan tiba.
        </div>
    <?php elseif ($rekening_tujuan_pembayaran): // Display specific bank/e-wallet details if found ?>
        <div class="card mb-4">
            <div class="card-header">
                Informasi Rekening Tujuan Pembayaran (<span class="text-primary"><?php echo htmlspecialchars($rekening_tujuan_pembayaran['nama_metode']); ?></span>)
            </div>
            <div class="card-body">
                <p>Silakan lakukan transfer sejumlah <strong>Rp <?php echo number_format($total_harga_pesanan, 0, ',', '.'); ?></strong> ke rekening di bawah ini:</p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>Metode:</strong> <?php echo htmlspecialchars($rekening_tujuan_pembayaran['nama_metode']); ?><br>
                        <strong>Nomor Rekening/Akun:</strong> <span class="fw-bold text-primary" id="nomorRekening"><?php echo htmlspecialchars($rekening_tujuan_pembayaran['nomor_rekening']); ?></span> <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('nomorRekening')"><i class="far fa-copy"></i> Salin</button><br>
                        <strong>Atas Nama:</strong> <?php echo htmlspecialchars($rekening_tujuan_pembayaran['nama_pemilik']); ?>
                    </li>
                </ul>
                <p class="mt-3 text-muted">Mohon transfer sesuai dengan jumlah total pesanan untuk mempercepat proses verifikasi.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-3">
            Maaf, informasi rekening tujuan untuk metode pembayaran **<?php echo htmlspecialchars($metode_pembayaran_pesanan); ?>** tidak ditemukan atau tidak aktif. Silakan hubungi admin untuk bantuan.
        </div>
    <?php endif; ?>

    <?php if (strtolower($metode_pembayaran_pesanan) !== 'cod'): // Form konfirmasi pembayaran hanya ditampilkan jika bukan COD ?>
    <div class="card p-4">
        <form action="konfirmasi_pembayaran.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nama_pengirim" class="form-label">Nama Pengirim (Nama Pemilik Rekening Anda)</label>
                <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" value="<?php echo htmlspecialchars($nama_pengirim_val); ?>" required>
                <small class="form-text text-muted">Nama pada rekening/sumber dana yang Anda gunakan untuk transfer.</small>
            </div>
            <div class="mb-3">
                <label for="bank_pengirim" class="form-label">Bank/Platform Pengirim Anda</label>
                <select class="form-select" id="bank_pengirim" name="bank_pengirim" required onchange="toggleOtherBankInput()">
                    <option value="">-- Pilih Bank/Platform --</option>
                    <?php foreach ($metode_pengirim_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($bank_pengirim_val == $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Lainnya" <?php echo ($bank_pengirim_val == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                </select>
                <small class="form-text text-muted">Pilih bank atau platform (e-wallet) yang Anda gunakan untuk mengirim dana.</small>
            </div>
            <div class="mb-3" id="bank_pengirim_lainnya_group">
                <label for="bank_pengirim_lainnya" class="form-label">Nama Bank/Platform Lainnya</label>
                <input type="text" class="form-control" id="bank_pengirim_lainnya" name="bank_pengirim_lainnya" value="<?php echo htmlspecialchars($bank_pengirim_lainnya_val); ?>" placeholder="Contoh: Bank XYZ, LinkAja, dll.">
                <small class="form-text text-muted">Masukkan nama bank atau platform lainnya jika tidak ada di daftar.</small>
            </div>
            <div class="mb-3">
                <label for="jumlah_transfer" class="form-label">Jumlah Transfer</label>
                <input type="text" class="form-control" id="jumlah_transfer" name="jumlah_transfer" 
                       value="<?php echo number_format($jumlah_transfer_val, 0, ',', '.'); ?>" required>
                <small class="form-text text-muted">Isi dengan jumlah yang sama persis seperti yang Anda transfer. Contoh: 21.000</small>
            </div>
            <div class="mb-3">
                <label for="tanggal_transfer" class="form-label">Tanggal Transfer</label>
                <input type="date" class="form-control" id="tanggal_transfer" name="tanggal_transfer" value="<?php echo htmlspecialchars($tanggal_transfer_val); ?>" required>
                <small class="form-text text-muted">Tanggal saat Anda melakukan transfer pembayaran.</small>
            </div>
            <div class="mb-3">
                <label for="bukti_transfer" class="form-label">Bukti Transfer (Foto/Screenshot)</label>
                <input class="form-control" type="file" id="bukti_transfer" name="bukti_transfer" accept="image/*,.pdf" required>
                <small class="form-text text-muted">Unggah bukti transfer yang jelas (maks. 5MB, format JPG, PNG, PDF).</small>
            </div>
            <div class="mb-3">
                <label for="catatan" class="form-label">Catatan (Opsional)</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="3"><?php echo htmlspecialchars($catatan_val); ?></textarea>
                <small class="form-text text-muted">Informasi tambahan jika ada.</small>
            </div>
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-paper-plane me-1"></i> Kirim Konfirmasi</button>
            <a href="pesanan_saya.php" class="btn btn-secondary"><i class="fas fa-times-circle me-1"></i> Batal</a>
        </form>
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
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
</script>

</body>
</html>