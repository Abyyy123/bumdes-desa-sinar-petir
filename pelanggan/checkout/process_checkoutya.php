<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path ke file koneksi Anda

// Aktifkan error reporting untuk debugging yang lebih jelas (hanya untuk development, matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Pastikan user sudah login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melanjutkan pembelian.";
    header('Location: ../../login.php'); // Pastikan path ini benar untuk login
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];

// Ambil flag is_direct_buy dari form POST (lebih reliable untuk Buy Now)
// Ini adalah variabel yang Anda butuhkan di form checkout.php untuk Buy Now:
// <input type="hidden" name="is_direct_buy" value="1">
$is_buy_now = isset($_POST['is_direct_buy']) && (int)$_POST['is_direct_buy'] === 1;

$produk_id_buy_now = null;
$quantity_buy_now = null;
$variasi_id_buy_now = null;

if ($is_buy_now) {
    // Ambil detail produk untuk 'Beli Sekarang' dari POST
    $produk_id_buy_now = filter_input(INPUT_POST, 'direct_produk_id', FILTER_VALIDATE_INT);
    $quantity_buy_now = filter_input(INPUT_POST, 'direct_quantity', FILTER_VALIDATE_INT);
    $variasi_id_buy_now = filter_input(INPUT_POST, 'direct_variasi_id', FILTER_VALIDATE_INT); // Bisa NULL
}

// Data metode pembayaran, kurir, catatan pelanggan (selalu dari POST)
$metode_pembayaran_id = filter_input(INPUT_POST, 'metode_pembayaran', FILTER_VALIDATE_INT);
$catatan_pelanggan_umum = isset($_POST['catatan_pelanggan']) ? htmlspecialchars(trim($_POST['catatan_pelanggan'])) : '';

// Ambil kurir_id dari array 'kurir' yang dikirim dari form (jika multiple seller, ini akan menjadi array)
// Atau ambil dari single kurir_id_pilihan jika hanya ada satu input kurir
$kurir_id_final = 1; // Default value, pastikan ada kurir dengan ID 1 di DB Anda
if (isset($_POST['kurir']) && is_array($_POST['kurir']) && !empty($_POST['kurir'])) {
    // Jika ada multiple kurir (untuk checkout keranjang dengan multiple penjual)
    // Ambil saja kurir pertama untuk contoh ini, atau Anda perlu looping untuk setiap pesanan sub-penjual
    // Untuk penyederhanaan, kita asumsikan 1 pesanan utama untuk 1 kurir
    $first_seller_kurir_id = reset($_POST['kurir']);
    $kurir_id_final = filter_var($first_seller_kurir_id, FILTER_VALIDATE_INT);
    if ($kurir_id_final === false || $kurir_id_final <= 0) {
        $kurir_id_final = 1; // Fallback jika validasi gagal
    }
} elseif (isset($_POST['kurir_id_pilihan'])) { // Untuk kasus single seller/buy now
    $kurir_id_final = filter_input(INPUT_POST, 'kurir_id_pilihan', FILTER_VALIDATE_INT);
    if ($kurir_id_final === false || $kurir_id_final <= 0) {
        $kurir_id_final = 1; // Fallback jika validasi gagal
    }
}


// Untuk checkout dari keranjang
$selected_cart_ids_str = $_POST['selected_cart_ids'] ?? '';
$selected_cart_ids = [];
if (!empty($selected_cart_ids_str)) {
    $selected_cart_ids = array_map('intval', explode(',', $selected_cart_ids_str));
}

// === AMBIL KODE KUPON DARI FORM ===
$coupon_code_input = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
$coupon_code_applied = null;
$coupon_discount_amount = 0;
// ===================================

// Validasi jika metode pembayaran atau kurir belum dipilih
if ($metode_pembayaran_id === false || $metode_pembayaran_id === null || $kurir_id_final === false || $kurir_id_final === null) {
    $_SESSION['error_message'] = "Silakan pilih metode pembayaran dan kurir.";
    $redirect_url = '../keranjang/checkout.php'; // Default redirect
    if ($is_buy_now) {
        $redirect_params = http_build_query([
            'produk_id' => $produk_id_buy_now,
            'quantity' => $quantity_buy_now,
            'variasi_id' => $variasi_id_buy_now,
            // Anda mungkin perlu meneruskan kembali data kupon jika sudah dimasukkan di halaman checkout
            // 'coupon_code' => urlencode($coupon_code_input) // Contoh
        ]);
        $redirect_url .= '?' . $redirect_params;
    } else {
        if (!empty($selected_cart_ids)) {
             $redirect_url .= '?cart_ids=' . implode(',', $selected_cart_ids);
        }
    }
    header('Location: ' . $redirect_url);
    exit();
}

// --- Start Transaction ---
$conn->begin_transaction();

try {
    // 1. Pastikan metode_pembayaran_id yang dipilih valid dan ambil nama metode
    $query_pm_check = "SELECT nama_metode FROM metode_pembayaran WHERE id = ? AND aktif_platform = TRUE";
    $stmt_pm_check = $conn->prepare($query_pm_check);
    if ($stmt_pm_check === false) {
        throw new Exception("Error preparing payment method check query: " . $conn->error);
    }
    $stmt_pm_check->bind_param('i', $metode_pembayaran_id);
    $stmt_pm_check->execute();
    $result_pm_check = $stmt_pm_check->get_result();
    $metode_pembelian_data = $result_pm_check->fetch_assoc();
    $stmt_pm_check->close();

    if (!$metode_pembelian_data || empty($metode_pembelian_data['nama_metode'])) {
        throw new Exception("Metode pembayaran yang dipilih tidak valid atau tidak aktif.");
    }
    $metode_pembelian_string = $metode_pembelian_data['nama_metode']; // e.g., "COD", "Transfer Bank BCA"

    // 2. Ambil alamat pengiriman pelanggan dari tabel 'pelanggan'
    $query_alamat = "SELECT nama, alamat, nomor_telepon FROM pelanggan WHERE pengguna_id = ?";
    $stmt_alamat = $conn->prepare($query_alamat);
    if ($stmt_alamat === false) {
        throw new Exception("Error preparing address query: " . $conn->error);
    }
    $stmt_alamat->bind_param('i', $pengguna_id);
    $stmt_alamat->execute();
    $result_alamat = $stmt_alamat->get_result();
    $customer_address = $result_alamat->fetch_assoc();
    $stmt_alamat->close();

    if (!$customer_address || empty($customer_address['alamat']) || empty($customer_address['nomor_telepon'])) {
        throw new Exception("Alamat pengiriman atau nomor telepon pelanggan tidak ditemukan. Mohon lengkapi profil Anda.");
    }

    $items_by_seller = [];
    $total_harga_semua_produk = 0;
    $total_berat_semua_produk = 0;
    $items_to_update_stock = [];

    if ($is_buy_now) {
        // --- Logic for 'Buy Now' ---
        // Validasi lagi bahwa produk_id dan quantity valid untuk Buy Now
        if ($produk_id_buy_now === null || $produk_id_buy_now <= 0 || $quantity_buy_now === null || $quantity_buy_now <= 0) {
             throw new Exception("Data produk atau kuantitas untuk 'Beli Sekarang' tidak valid.");
        }

        // Handle query for product with or without variation
        $item = null;
        if ($variasi_id_buy_now !== null && $variasi_id_buy_now > 0) {
            // Query for product with variation
            $query_product_detail = "
                SELECT
                    prod.id AS produk_id,
                    prod.nama AS nama_produk,
                    prod.harga AS harga_produk_utama,
                    prod.berat,
                    prod.stok AS stok_produk_utama,
                    prod.penjual_id,
                    pen.nama_toko AS nama_toko_penjual,
                    prod.gambar AS gambar_produk,
                    pv.id AS variasi_id,
                    pv.harga AS harga_variasi,
                    pv.stok AS stok_variasi,
                    CASE
                        WHEN pv.rasa_id IS NOT NULL AND pv.warna_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Warna: ', w.nama_warna, ', Ukuran: ', u.nama_ukuran)
                        WHEN pv.rasa_id IS NOT NULL AND pv.warna_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Warna: ', w.nama_warna)
                        WHEN pv.rasa_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Ukuran: ', u.nama_ukuran)
                        WHEN pv.warna_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Warna: ', w.nama_warna, ', Ukuran: ', u.nama_ukuran)
                        WHEN pv.rasa_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa)
                        WHEN pv.warna_id IS NOT NULL THEN CONCAT('Warna: ', w.nama_warna)
                        WHEN pv.ukuran_id IS NOT NULL THEN CONCAT('Ukuran: ', u.nama_ukuran)
                        ELSE 'N/A'
                    END AS variasi_detail_produk
                FROM
                    produk prod
                JOIN
                    penjual pen ON prod.penjual_id = pen.pengguna_id
                JOIN
                    produk_variasi pv ON pv.produk_id = prod.id
                LEFT JOIN
                    rasa r ON pv.rasa_id = r.id
                LEFT JOIN
                    warna w ON pv.warna_id = w.id
                LEFT JOIN
                    ukuran u ON pv.ukuran_id = u.id
                WHERE
                    prod.id = ? AND pv.id = ?
                FOR UPDATE
            ";
            $stmt_buy_now_item = $conn->prepare($query_product_detail);
            if ($stmt_buy_now_item === false) {
                throw new Exception("Error preparing buy now item query (with variation): " . $conn->error);
            }
            $stmt_buy_now_item->bind_param('ii', $produk_id_buy_now, $variasi_id_buy_now);
        } else {
            // Query for product without variation
            $query_product_detail = "
                SELECT
                    prod.id AS produk_id,
                    prod.nama AS nama_produk,
                    prod.harga AS harga_produk_utama,
                    prod.berat,
                    prod.stok AS stok_produk_utama,
                    prod.penjual_id,
                    pen.nama_toko AS nama_toko_penjual,
                    prod.gambar AS gambar_produk,
                    NULL AS variasi_id,
                    NULL AS harga_variasi,
                    NULL AS stok_variasi,
                    'N/A' AS variasi_detail_produk
                FROM
                    produk prod
                JOIN
                    penjual pen ON prod.penjual_id = pen.pengguna_id
                WHERE
                    prod.id = ?
                FOR UPDATE
            ";
            $stmt_buy_now_item = $conn->prepare($query_product_detail);
            if ($stmt_buy_now_item === false) {
                throw new Exception("Error preparing buy now item query (no variation): " . $conn->error);
            }
            $stmt_buy_now_item->bind_param('i', $produk_id_buy_now);
        }

        $stmt_buy_now_item->execute();
        $result_buy_now_item = $stmt_buy_now_item->get_result();
        $item = $result_buy_now_item->fetch_assoc();
        $stmt_buy_now_item->close();

        if (!$item) {
            throw new Exception("Produk tidak ditemukan atau tidak valid.");
        }

        // Adjusting item details for 'Buy Now' to match cart item structure
        $item['quantity'] = $quantity_buy_now;
        // Determine the correct price based on variation or main product
        if ($item['variasi_id'] && $item['harga_variasi'] !== null) {
            $item['harga_satuan'] = $item['harga_variasi'];
        } else {
            $item['harga_satuan'] = $item['harga_produk_utama'];
        }

        // --- Stock Validation for Buy Now ---
        $required_quantity = $item['quantity'];
        if ($item['variasi_id']) {
            if ($item['stok_variasi'] === null || $item['stok_variasi'] < $required_quantity) {
                throw new Exception("Stok untuk produk '" . $item['nama_produk'] . "' dengan variasi '" . $item['variasi_detail_produk'] . "' tidak mencukupi. Tersedia: " . ($item['stok_variasi'] ?? 0) . ", Dibutuhkan: " . $required_quantity);
            }
            $items_to_update_stock[] = [
                'type' => 'variasi',
                'id' => $item['variasi_id'],
                'quantity' => $required_quantity
            ];
        } else {
            if ($item['stok_produk_utama'] === null || $item['stok_produk_utama'] < $required_quantity) {
                throw new Exception("Stok untuk produk '" . $item['nama_produk'] . "' tidak mencukupi. Tersedia: " . ($item['stok_produk_utama'] ?? 0) . ", Dibutuhkan: " . $required_quantity);
            }
            $items_to_update_stock[] = [
                'type' => 'produk',
                'id' => $item['produk_id'],
                'quantity' => $required_quantity
            ];
        }

        $penjual_id = $item['penjual_id'];
        if (!isset($items_by_seller[$penjual_id])) {
            $items_by_seller[$penjual_id] = [
                'nama_toko_penjual' => $item['nama_toko_penjual'],
                'items' => [],
                'total_harga_produk_penjual' => 0,
                'total_berat_penjual' => 0,
            ];
        }

        $subtotal = $item['quantity'] * $item['harga_satuan'];

        $items_by_seller[$penjual_id]['items'][] = $item;
        $items_by_seller[$penjual_id]['total_harga_produk_penjual'] += $subtotal;
        $items_by_seller[$penjual_id]['total_berat_penjual'] += $item['quantity'] * $item['berat'];
        $total_harga_semua_produk += $subtotal;
        $total_berat_semua_produk += $item['quantity'] * $item['berat'];

    } else {
        // --- Original Logic for Cart Checkout ---
        if (empty($selected_cart_ids)) {
            throw new Exception("Pilihan keranjang tidak valid."); // Lebih baik throw exception daripada redirect langsung
        }

        $placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
        $query_cart_items = "
            SELECT
                kc.id AS keranjang_id,
                kc.produk_id,
                kc.variasi_id,
                kc.quantity,
                CASE
                    WHEN kc.variasi_id IS NOT NULL AND pv.harga IS NOT NULL THEN pv.harga
                    ELSE prod.harga
                END AS harga_satuan,
                prod.berat,
                prod.stok AS stok_produk_utama,
                prod.penjual_id,
                pen.nama_toko AS nama_toko_penjual,
                prod.nama AS nama_produk,
                prod.gambar AS gambar_produk,
                pv.stok AS stok_variasi,
                CASE
                    WHEN pv.rasa_id IS NOT NULL AND pv.warna_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Warna: ', w.nama_warna, ', Ukuran: ', u.nama_ukuran)
                    WHEN pv.rasa_id IS NOT NULL AND pv.warna_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Warna: ', w.nama_warna)
                    WHEN pv.rasa_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa, ', Ukuran: ', u.nama_ukuran)
                    WHEN pv.warna_id IS NOT NULL AND pv.ukuran_id IS NOT NULL THEN CONCAT('Warna: ', w.nama_warna, ', Ukuran: ', u.nama_ukuran)
                    WHEN pv.rasa_id IS NOT NULL THEN CONCAT('Rasa: ', r.nama_rasa)
                    WHEN pv.warna_id IS NOT NULL THEN CONCAT('Warna: ', w.nama_warna)
                    WHEN pv.ukuran_id IS NOT NULL THEN CONCAT('Ukuran: ', u.nama_ukuran)
                    ELSE 'N/A'
                END AS variasi_detail_produk
            FROM
                keranjang_customer kc
            JOIN
                produk prod ON kc.produk_id = prod.id
            JOIN
                penjual pen ON prod.penjual_id = pen.pengguna_id
            LEFT JOIN
                produk_variasi pv ON kc.variasi_id = pv.id
            LEFT JOIN
                rasa r ON pv.rasa_id = r.id
            LEFT JOIN
                warna w ON pv.warna_id = w.id
            LEFT JOIN
                ukuran u ON pv.ukuran_id = u.id
            WHERE
                kc.id IN ($placeholders) AND kc.customer_id = ?
            FOR UPDATE
        ";
        $stmt_cart = $conn->prepare($query_cart_items);
        if ($stmt_cart === false) {
            throw new Exception("Error preparing cart items query: " . $conn->error);
        }
        $types = str_repeat('i', count($selected_cart_ids)) . 'i';
        $params_bind_cart = array_merge($selected_cart_ids, [$pengguna_id]);
        $stmt_cart->bind_param($types, ...$params_bind_cart); // Menggunakan spread operator
        $stmt_cart->execute();
        $result_cart = $stmt_cart->get_result();

        if ($result_cart->num_rows === 0) {
            throw new Exception("Tidak ada item keranjang yang valid ditemukan.");
        }

        while ($item = $result_cart->fetch_assoc()) {
            // --- Stock Validation ---
            $required_quantity = $item['quantity'];
            if ($item['variasi_id']) {
                if ($item['stok_variasi'] === null || $item['stok_variasi'] < $required_quantity) {
                    throw new Exception("Stok untuk produk '" . $item['nama_produk'] . "' dengan variasi '" . $item['variasi_detail_produk'] . "' tidak mencukupi. Tersedia: " . ($item['stok_variasi'] ?? 0) . ", Dibutuhkan: " . $required_quantity);
                }
                $items_to_update_stock[] = [
                    'type' => 'variasi',
                    'id' => $item['variasi_id'],
                    'quantity' => $required_quantity
                ];
            } else {
                if ($item['stok_produk_utama'] === null || $item['stok_produk_utama'] < $required_quantity) {
                    throw new Exception("Stok untuk produk '" . $item['nama_produk'] . "' tidak mencukupi. Tersedia: " . ($item['stok_produk_utama'] ?? 0) . ", Dibutuhkan: " . $required_quantity);
                }
                $items_to_update_stock[] = [
                    'type' => 'produk',
                    'id' => $item['produk_id'],
                    'quantity' => $required_quantity
                ];
            }

            $penjual_id = $item['penjual_id'];
            if (!isset($items_by_seller[$penjual_id])) {
                $items_by_seller[$penjual_id] = [
                    'nama_toko_penjual' => $item['nama_toko_penjual'],
                    'items' => [],
                    'total_harga_produk_penjual' => 0,
                    'total_berat_penjual' => 0,
                ];
            }

            $subtotal = $item['quantity'] * $item['harga_satuan'];

            $items_by_seller[$penjual_id]['items'][] = $item;
            $items_by_seller[$penjual_id]['total_harga_produk_penjual'] += $subtotal;
            $items_by_seller[$penjual_id]['total_berat_penjual'] += $item['quantity'] * $item['berat'];
            $total_harga_semua_produk += $subtotal;
            $total_berat_semua_produk += $item['quantity'] * $item['berat'];
        }
        $stmt_cart->close();
    }

    // --- Ambil Total Ongkir dari form (hidden input) ---
    // Ini adalah asumsi bahwa Anda mengirim total ongkir dari checkout.php
    // dalam input hidden atau dari perhitungan AJAX.
    // Misal: <input type="hidden" name="total_ongkir_from_js" value="15000">
    $total_ongkir_pesanan_utama = 0;
    if (isset($_POST['ongkir_values']) && is_array($_POST['ongkir_values']) && !empty($_POST['ongkir_values'])) {
        foreach ($_POST['ongkir_values'] as $ongkir_value) {
            $ongkir = filter_var($ongkir_value, FILTER_VALIDATE_FLOAT);
            if ($ongkir !== false && $ongkir > 0) {
                $total_ongkir_pesanan_utama += $ongkir;
            }
        }
    } else {
        // Fallback atau default ongkir jika tidak dikirim dari form
        // Anda mungkin perlu logika yang lebih kompleks di sini
        $total_ongkir_pesanan_utama = 10000.0; // Biaya dasar
        if ($total_berat_semua_produk > 1000) { // Jika total berat lebih dari 1kg
            $total_ongkir_pesanan_utama += 5000.0 * ceil(($total_berat_semua_produk - 1000) / 1000);
        }
    }

    $tanggal_pesanan = date('Y-m-d H:i:s');
    $metode_pengiriman_string = 'antar'; // Asumsi metode pengiriman default
    $order_code = 'ORD' . time() . rand(100, 999);

    $status_pesanan = '';
    if (strtolower($metode_pembelian_string) == 'cod' || strtolower($metode_pembelian_string) == 'cash on delivery') {
        $status_pesanan = 'diproses';
    } else {
        $status_pesanan = 'menunggu_pembayaran';
    }

    $total_pembayaran_final = $total_harga_semua_produk + $total_ongkir_pesanan_utama;

    // === LOGIC UNTUK MENGAPLIKASIKAN DISKON KUPON ===
    if (!empty($coupon_code_input)) {
        $current_date = date('Y-m-d');
        $query_coupon = "SELECT * FROM diskon WHERE kode_diskon = ? AND status = 'aktif' AND tanggal_mulai <= ? AND tanggal_berakhir >= ?";
        $stmt_coupon = $conn->prepare($query_coupon);
        if ($stmt_coupon === false) {
            throw new Exception("Error preparing coupon query: " . $conn->error);
        }
        $stmt_coupon->bind_param('sss', $coupon_code_input, $current_date, $current_date);
        $stmt_coupon->execute();
        $result_coupon = $stmt_coupon->get_result();
        $coupon_data = $result_coupon->fetch_assoc();
        $stmt_coupon->close();

        if ($coupon_data) {
            $coupon_code_applied = $coupon_data['kode_diskon'];
            $discount_value = (double)$coupon_data['nilai_diskon'];
            $discount_type = $coupon_data['jenis_diskon'];

            if ($discount_type === 'fixed') {
                $coupon_discount_amount = $discount_value;
            } elseif ($discount_type === 'persen') {
                $coupon_discount_amount = ($total_harga_semua_produk * $discount_value) / 100;
            }

            // Pastikan diskon tidak lebih dari total harga produk
            if ($coupon_discount_amount > $total_harga_semua_produk) {
                $coupon_discount_amount = $total_harga_semua_produk;
            }

            // Terapkan diskon ke total pembayaran final
            $total_pembayaran_final -= $coupon_discount_amount;
            if ($total_pembayaran_final < 0) {
                $total_pembayaran_final = 0; // Pastikan total tidak negatif
            }
        } else {
            // Jika kode kupon tidak valid atau tidak aktif, berikan pesan error
            $_SESSION['error_message'] = "Kode kupon '" . htmlspecialchars($coupon_code_input) . "' tidak valid, sudah kadaluarsa, atau tidak aktif.";
            // Penting: Jika Anda ingin ini menghentikan transaksi, tambahkan 'throw new Exception(...)' di sini.
            // Saat ini, transaksi akan dilanjutkan tanpa diskon.
            $coupon_code_applied = null; // Pastikan tidak ada kupon yang dicatat jika tidak valid
            $coupon_discount_amount = 0;
        }
    }
    // ===============================================

    // 4. Insert into `pesanan` table
    // PASTIKAN KOLOM 'kode_kupon_terpakai' dan 'diskon_kupon' ADA DI TABEL `pesanan` ANDA!
    $query_insert_pesanan = "
        INSERT INTO pesanan (
            pelanggan_id,
            kurir_id,
            tanggal_pesanan,
            status_pesanan,
            metode_pembelian,
            metode_pengiriman,
            alamat_pengiriman,
            ongkos_kirim,
            total_harga,
            kode_unik,
            catatan_pelanggan,
            kode_kupon_terpakai, -- Kolom baru
            diskon_kupon        -- Kolom baru
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_pesanan = $conn->prepare($query_insert_pesanan);
    if ($stmt_pesanan === false) {
        throw new Exception("Error preparing pesanan statement: " . $conn->error);
    }
    $stmt_pesanan->bind_param(
        "iisssssddsssd", // Tambahkan 's' untuk kode_kupon_terpakai dan 'd' untuk diskon_kupon
        $pengguna_id,
        $kurir_id_final,
        $tanggal_pesanan,
        $status_pesanan,
        $metode_pembelian_string,
        $metode_pengiriman_string,
        $customer_address['alamat'],
        $total_ongkir_pesanan_utama,
        $total_pembayaran_final,
        $order_code,
        $catatan_pelanggan_umum,
        $coupon_code_applied,    // Bind nilai kode kupon
        $coupon_discount_amount  // Bind nilai diskon kupon
    );
    if (!$stmt_pesanan->execute()) {
        throw new Exception("Error executing pesanan insert: " . $stmt_pesanan->error);
    }
    $pesanan_id = $conn->insert_id;
    $stmt_pesanan->close();

    // 5. Insert into `pembayaran` table
    $status_pembayaran_awal = (strtolower($metode_pembelian_string) == 'cod' || strtolower($metode_pembelian_string) == 'cash on delivery') ? 'sudah_bayar' : 'belum_bayar';
    $bukti_transfer_default = '';
    $keterangan_default = 'Pembayaran untuk pesanan ' . $order_code;

    $query_insert_pembayaran = "
        INSERT INTO pembayaran (
            pesanan_id,
            metode_pembayaran,
            jumlah_bayar,
            status_pembayaran,
            bukti_transfer,
            keterangan
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt_pembayaran = $conn->prepare($query_insert_pembayaran);
    if ($stmt_pembayaran === false) {
        throw new Exception("Error preparing pembayaran statement: " . $conn->error);
    }
    $stmt_pembayaran->bind_param(
        "isdsss",
        $pesanan_id,
        $metode_pembelian_string,
        $total_pembayaran_final, // total_pembayaran_final sudah termasuk diskon
        $status_pembayaran_awal,
        $bukti_transfer_default,
        $keterangan_default
    );
    if (!$stmt_pembayaran->execute()) {
        throw new Exception("Error executing pembayaran insert: " . $stmt_pembayaran->error);
    }
    $stmt_pembayaran->close();

    // 6. Insert into `pengiriman` table
    $query_insert_pengiriman = "
        INSERT INTO pengiriman (
            pesanan_id,
            kurir_id,
            tanggal_pengiriman,
            status_pengiriman,
            keterangan,
            nomor_resi
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt_pengiriman = $conn->prepare($query_insert_pengiriman);
    if ($stmt_pengiriman === false) {
        throw new Exception("Error preparing pengiriman statement: " . $conn->error);
    }

    $tanggal_pengiriman_default = null;
    $status_pengiriman_default = 'menunggu_konfirmasi';
    $keterangan_pengiriman = '';
    $nomor_resi = '';

    $stmt_pengiriman->bind_param(
        "iissss",
        $pesanan_id,
        $kurir_id_final,
        $tanggal_pengiriman_default,
        $status_pengiriman_default,
        $keterangan_pengiriman,
        $nomor_resi
    );

    if (!$stmt_pengiriman->execute()) {
        throw new Exception("Error executing pengiriman insert: " . $stmt_pengiriman->error);
    }
    $stmt_pengiriman->close();

    // 7. Insert into `detail_pesanan` table
    $query_insert_detail_pesanan = "
        INSERT INTO detail_pesanan (
            pesanan_id,
            produk_id,
            variasi_id,
            quantity,
            harga_satuan,
            nama_produk_saat_beli,
            variasi_detail_saat_beli
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_detail_pesanan = $conn->prepare($query_insert_detail_pesanan);
    if ($stmt_detail_pesanan === false) {
        throw new Exception("Error preparing detail pesanan statement: " . $conn->error);
    }

    foreach ($items_by_seller as $seller_data) {
        foreach ($seller_data['items'] as $item) {
            $produk_id_detail = $item['produk_id'];
            $variasi_id_detail = $item['variasi_id'];
            $quantity_detail = $item['quantity'];
            $harga_satuan_detail = (double)$item['harga_satuan'];
            $nama_produk_saat_beli_detail = $item['nama_produk'];
            $variasi_detail_saat_beli_detail = $item['variasi_detail_produk'];

            $stmt_detail_pesanan->bind_param(
                "iiiidss",
                $pesanan_id,
                $produk_id_detail,
                $variasi_id_detail,
                $quantity_detail,
                $harga_satuan_detail,
                $nama_produk_saat_beli_detail,
                $variasi_detail_saat_beli_detail
            );
            if (!$stmt_detail_pesanan->execute()) {
                throw new Exception("Error executing detail pesanan insert for product " . $item['nama_produk'] . ": " . $stmt_detail_pesanan->error);
            }
        }
    }
    $stmt_detail_pesanan->close();

    // 8. Update product stock
    $query_update_produk_stock = "UPDATE produk SET stok = stok - ? WHERE id = ?";
    $stmt_update_produk_stock = $conn->prepare($query_update_produk_stock);
    if ($stmt_update_produk_stock === false) {
        throw new Exception("Error preparing product stock update statement: " . $conn->error);
    }

    $query_update_variasi_stock = "UPDATE produk_variasi SET stok = stok - ? WHERE id = ?";
    $stmt_update_variasi_stock = $conn->prepare($query_update_variasi_stock);
    if ($stmt_update_variasi_stock === false) {
        throw new Exception("Error preparing variation stock update statement: " . $conn->error);
    }

    foreach ($items_to_update_stock as $stock_item) {
        if ($stock_item['type'] === 'produk') {
            $stmt_update_produk_stock->bind_param("ii", $stock_item['quantity'], $stock_item['id']);
            if (!$stmt_update_produk_stock->execute()) {
                throw new Exception("Error updating product stock for ID " . $stock_item['id'] . ": " . $stmt_update_produk_stock->error);
            }
        } elseif ($stock_item['type'] === 'variasi') {
            $stmt_update_variasi_stock->bind_param("ii", $stock_item['quantity'], $stock_item['id']);
            if (!$stmt_update_variasi_stock->execute()) {
                throw new Exception("Error updating variation stock for ID " . $stock_item['id'] . ": " . $stmt_update_variasi_stock->error);
            }
        }
    }
    $stmt_update_produk_stock->close();
    $stmt_update_variasi_stock->close();

    // 9. Hapus item dari keranjang HANYA JIKA ini adalah checkout dari keranjang
    if (!$is_buy_now) {
        if (!empty($selected_cart_ids)) { // Pastikan ada ID keranjang yang dipilih
            $placeholders_delete = implode(',', array_fill(0, count($selected_cart_ids), '?'));
            $query_delete_cart_items = "DELETE FROM keranjang_customer WHERE id IN ($placeholders_delete) AND customer_id = ?";
            $stmt_delete_cart = $conn->prepare($query_delete_cart_items);
            if ($stmt_delete_cart === false) {
                throw new Exception("Error preparing delete cart items statement: " . $conn->error);
            }
            $types_delete = str_repeat('i', count($selected_cart_ids)) . 'i';
            $params_delete_bind = array_merge($selected_cart_ids, [$pengguna_id]);
            $stmt_delete_cart->bind_param($types_delete, ...$params_delete_bind); // Menggunakan spread operator

            if (!$stmt_delete_cart->execute()) {
                throw new Exception("Error executing delete cart items: " . $stmt_delete_cart->error);
            }
            $stmt_delete_cart->close();
        }
    }

    $conn->commit();
    $_SESSION['success_message'] = "Pesanan Anda berhasil dibuat! Kode Pesanan: " . $order_code;
    // Jika ada diskon yang diterapkan, tambahkan info ke pesan sukses
    if ($coupon_discount_amount > 0 && $coupon_code_applied) {
        $_SESSION['success_message'] .= " Diskon Kupon '" . htmlspecialchars($coupon_code_applied) . "' sebesar Rp " . number_format($coupon_discount_amount, 0, ',', '.') . " telah diterapkan.";
    }
    header('Location: riwayat_pesanan.php'); // Arahkan ke halaman riwayat pesanan setelah berhasil
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage();
    // Redirect kembali ke halaman asal dengan pesan error
    $redirect_url = '../keranjang/checkout.php'; // Default redirect
    if ($is_buy_now) {
        $redirect_params = http_build_query([
            'produk_id' => $produk_id_buy_now,
            'quantity' => $quantity_buy_now,
            'variasi_id' => $variasi_id_buy_now,
            'coupon_code' => urlencode($coupon_code_input ?? '') // Kirim kembali kupon agar user tidak perlu input ulang
        ]);
        $redirect_url .= '?' . $redirect_params;
    } else {
        if (!empty($selected_cart_ids)) {
             $redirect_url .= '?cart_ids=' . implode(',', $selected_cart_ids);
        }
        $redirect_url .= '&coupon_code=' . urlencode($coupon_code_input ?? ''); // Kirim kembali kupon jika dari keranjang
    }
    header('Location: ' . $redirect_url);
    exit();
} finally {
    if ($conn->ping()) {
        $conn->close();
    }
}
?>