<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk menggunakan fitur 'Beli Lagi'.";
    header('Location: ../../login.php');
    exit();
}

// Cek apakah order_id diberikan melalui parameter GET
if (!isset($_GET['order_id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak ditemukan untuk fitur 'Beli Lagi'.";
    header('Location: pesanan_saya.php');
    exit();
}

$order_id = $_GET['order_id'];
$pengguna_id = $_SESSION['pengguna_id'];

// 1. Verifikasi kepemilikan pesanan (opsional tapi disarankan untuk keamanan)
// Pastikan pesanan adalah milik pengguna yang login
$query_check_order_ownership = "SELECT COUNT(id) AS total_orders FROM pesanan WHERE id = ? AND pelanggan_id = ?";
$stmt_check_order_ownership = $conn->prepare($query_check_order_ownership);
if ($stmt_check_order_ownership === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memverifikasi pesanan."; // Pesan ringkas
    error_log("Error preparing check order ownership query in beli_lagi.php: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}
$stmt_check_order_ownership->bind_param("ii", $order_id, $pengguna_id);
$stmt_check_order_ownership->execute();
$result_ownership = $stmt_check_order_ownership->get_result();
$ownership_data = $result_ownership->fetch_assoc();
$stmt_check_order_ownership->close();

if ($ownership_data['total_orders'] === 0) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan atau bukan milik Anda.";
    header('Location: pesanan_saya.php');
    exit();
}

// 2. Ambil detail produk dari pesanan lama
$query_get_order_details = "
    SELECT
        dp.produk_id,
        dp.variasi_id,
        dp.quantity,
        p.stok,          -- Ambil stok produk
        pv.stok AS stok_variasi -- Ambil stok variasi jika ada
    FROM
        detail_pesanan dp
    JOIN
        produk p ON dp.produk_id = p.id
    LEFT JOIN
        produk_variasi pv ON dp.variasi_id = pv.id
    WHERE
        dp.pesanan_id = ?
";
$stmt_get_order_details = $conn->prepare($query_get_order_details);
if ($stmt_get_order_details === false) {
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat mengambil detail pesanan."; // Pesan ringkas
    error_log("Error preparing get order details query in beli_lagi.php: " . $conn->error);
    header('Location: pesanan_saya.php');
    exit();
}
$stmt_get_order_details->bind_param("i", $order_id);
$stmt_get_order_details->execute();
$result_order_details = $stmt_get_order_details->get_result();

$products_added = 0;
$products_skipped = 0;
// $messages = []; // Hapus ini jika Anda tidak ingin detail pesan di sesi, hanya pesan ringkasan

if ($result_order_details->num_rows > 0) {
    while ($item = $result_order_details->fetch_assoc()) {
        $produk_id = $item['produk_id'];
        $variasi_id = $item['variasi_id']; // Bisa NULL jika tidak ada variasi
        $quantity_to_add = $item['quantity'];
        $current_product_stock = $item['stok'];
        $current_variasi_stock = $item['stok_variasi'];

        // Cek stok terlebih dahulu
        $available_stock = ($variasi_id !== null) ? $current_variasi_stock : $current_product_stock;

        // Mendapatkan nama produk untuk pesan yang lebih informatif (jika masih diperlukan untuk logging/debugging, bukan untuk user)
        // Jika Anda hanya ingin pesan ringkas, bagian ini bisa dihapus atau diubah untuk hanya log
        $query_get_product_name = "SELECT nama FROM produk WHERE id = ?";
        $stmt_get_product_name = $conn->prepare($query_get_product_name);
        $product_name = "Produk ID {$produk_id}"; // Default
        if ($stmt_get_product_name) {
            $stmt_get_product_name->bind_param("i", $produk_id);
            $stmt_get_product_name->execute();
            $result_product_name = $stmt_get_product_name->get_result();
            if ($row_product_name = $result_product_name->fetch_assoc()) {
                $product_name = htmlspecialchars($row_product_name['nama']);
            }
            $stmt_get_product_name->close();
        }

        if ($available_stock === null || $available_stock < 0 || $available_stock < $quantity_to_add) {
            // Cukup hitung yang diskip, tidak perlu pesan detail di array $messages jika tidak akan ditampilkan
            $products_skipped++;
            error_log("Produk '{$product_name}' (ID: {$produk_id}) dilewati dari beli_lagi karena stok tidak cukup/invalid. Tersedia: {$available_stock}, Diminta: {$quantity_to_add}");
            continue; // Lewati produk ini
        }
        
        // 3. Tambahkan produk ke keranjang atau update quantity jika sudah ada
        $query_check_cart = "SELECT id, quantity FROM keranjang_customer WHERE customer_id = ? AND produk_id = ? AND variasi_id <=> ?";
        $stmt_check_cart = $conn->prepare($query_check_cart);
        if ($stmt_check_cart === false) {
            $products_skipped++; // Hitung sebagai dilewati karena error sistem
            error_log("Error preparing check cart query in beli_lagi.php: " . $conn->error);
            continue;
        }
        $stmt_check_cart->bind_param("iii", $pengguna_id, $produk_id, $variasi_id);
        $stmt_check_cart->execute();
        $result_cart = $stmt_check_cart->get_result();
        $cart_item = $result_cart->fetch_assoc();
        $stmt_check_cart->close();

        if ($cart_item) {
            // Jika sudah ada, update quantity
            $new_quantity = $cart_item['quantity'] + $quantity_to_add;
            // Pastikan quantity yang baru tidak melebihi stok (sudah dicek di atas, tapi ini lapisan pengaman kedua)
            if ($new_quantity > $available_stock) {
                $new_quantity = $available_stock;
                error_log("Kuantitas produk '{$product_name}' (ID: {$produk_id}) di keranjang disesuaikan ke stok tersedia.");
            }

            $query_update_cart = "UPDATE keranjang_customer SET quantity = ? WHERE id = ?";
            $stmt_update_cart = $conn->prepare($query_update_cart);
            if ($stmt_update_cart === false) {
                $products_skipped++; // Hitung sebagai dilewati karena error sistem
                error_log("Error preparing update cart query in beli_lagi.php: " . $conn->error);
                continue;
            }
            $stmt_update_cart->bind_param("ii", $new_quantity, $cart_item['id']);
            if ($stmt_update_cart->execute()) {
                $products_added++;
            } else {
                $products_skipped++; // Gagal update, hitung dilewati
                error_log("Error updating cart item in beli_lagi.php for produk_id {$produk_id}: " . $stmt_update_cart->error);
            }
            $stmt_update_cart->close();
        } else {
            // Jika belum ada, tambahkan sebagai item baru
            $query_insert_cart = "INSERT INTO keranjang_customer (customer_id, produk_id, variasi_id, quantity) VALUES (?, ?, ?, ?)";
            $stmt_insert_cart = $conn->prepare($query_insert_cart);
            if ($stmt_insert_cart === false) {
                $products_skipped++; // Hitung sebagai dilewati karena error sistem
                error_log("Error preparing insert cart query in beli_lagi.php: " . $conn->error);
                continue;
            }
            $stmt_insert_cart->bind_param("iiii", $pengguna_id, $produk_id, $variasi_id, $quantity_to_add);
            if ($stmt_insert_cart->execute()) {
                $products_added++;
            } else {
                $products_skipped++; // Gagal insert, hitung dilewati
                error_log("Error inserting cart item in beli_lagi.php for produk_id {$produk_id}: " . $stmt_insert_cart->error);
            }
            $stmt_insert_cart->close();
        }
    }
} else {
    // Jika tidak ada detail produk ditemukan untuk order_id ini
    $_SESSION['error_message'] = "Tidak ada produk yang ditemukan dalam pesanan #{$order_id} untuk ditambahkan kembali.";
    header('Location: pesanan_saya.php');
    exit();
}

$stmt_get_order_details->close();
$conn->close();

// --- Bagian Penentuan Pesan Ringkas Final ---
if ($products_added > 0 && $products_skipped === 0) {
    // Semua berhasil ditambahkan      
    $_SESSION['success_message'] = "Pesanan #{$order_id} berhasil ditambahkan kembali ke keranjang Anda.";
} elseif ($products_added > 0 && $products_skipped > 0) {
    // Sebagian berhasil ditambahkan, sebagian dilewati
    $_SESSION['success_message'] = "Beberapa produk dari pesanan #{$order_id} berhasil ditambahkan ke keranjang Anda.";
    // Anda bisa tambahkan pesan peringatan jika ingin lebih detail di session error:
    // $_SESSION['error_message'] = "Perhatian: Beberapa item tidak dapat ditambahkan karena stok tidak mencukupi atau masalah lainnya.";
} elseif ($products_added === 0 && $products_skipped > 0) {
    // Tidak ada yang berhasil ditambahkan, ada masalah
    $_SESSION['error_message'] = "Gagal menambahkan produk dari pesanan #{$order_id} ke keranjang. Stok mungkin tidak tersedia atau ada masalah lain.";
} else {
    // Kasus lain yang tidak terduga, atau 0 produk di order
    $_SESSION['error_message'] = "Tidak ada produk yang dapat diproses dari pesanan #{$order_id}.";
}
// --- Akhir Bagian Penentuan Pesan Ringkas Final ---

header('Location: keranjang.php'); // Redirect ke halaman keranjang untuk melihat hasil
exit();
?>