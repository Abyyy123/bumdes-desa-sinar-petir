<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Aktifkan error reporting untuk debugging yang lebih jelas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Pastikan user sudah login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk membatalkan pesanan.";
    header('Location: ../../login.php');
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$order_id_to_cancel = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

// Validasi ID pesanan
if (!$order_id_to_cancel) {
    $_SESSION['error_message'] = "ID Pesanan tidak valid.";
    header('Location: pesanan_saya.php');
    exit();
}

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Verifikasi kepemilikan pesanan dan status pesanan yang memungkinkan pembatalan
    // Hanya izinkan pembatalan jika statusnya 'menunggu_pembayaran', 'diproses', atau 'menunggu_verifikasi'.
    // Menggunakan FOR UPDATE untuk mengunci baris pesanan agar tidak ada perubahan lain selama transaksi ini.
    $query_check_order = "
        SELECT status_pesanan
        FROM pesanan
        WHERE id = ? AND pelanggan_id = ? AND status_pesanan IN ('menunggu_pembayaran', 'diproses', 'menunggu_verifikasi')
        FOR UPDATE;
    ";
    $stmt_check = $conn->prepare($query_check_order);
    if ($stmt_check === false) {
        throw new Exception("Error preparing order check query: " . $conn->error);
    }
    $stmt_check->bind_param('ii', $order_id_to_cancel, $pengguna_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $order_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$order_data) {
        // Jika pesanan tidak ditemukan, bukan milik pengguna, atau statusnya tidak memungkinkan pembatalan
        throw new Exception("Gagal membatalkan pesanan. Pesanan tidak ditemukan, bukan milik Anda, atau statusnya tidak dapat dibatalkan (mungkin sudah selesai atau dikirim).");
    }

    // 2. Ambil detail produk dari pesanan yang akan dibatalkan
    $query_get_order_details = "
        SELECT
            produk_id,
            variasi_id,
            quantity
        FROM
            detail_pesanan
        WHERE
            pesanan_id = ?;
    ";
    $stmt_details = $conn->prepare($query_get_order_details);
    if ($stmt_details === false) {
        throw new Exception("Error preparing details query: " . $conn->error);
    }
    $stmt_details->bind_param('i', $order_id_to_cancel);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();

    if ($result_details->num_rows === 0) {
        // Ini bisa terjadi jika ada pesanan tanpa detail (yang seharusnya tidak terjadi)
        throw new Exception("Tidak ada detail produk ditemukan untuk pesanan ini.");
    }

    $products_to_restore = [];
    while ($row = $result_details->fetch_assoc()) {
        $products_to_restore[] = $row;
    }
    $stmt_details->close();

    // 3. Kembalikan stok untuk setiap produk yang ada di pesanan
    $stmt_update_produk_stock = $conn->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
    $stmt_update_variasi_stock = $conn->prepare("UPDATE produk_variasi SET stok = stok + ? WHERE id = ?");

    if ($stmt_update_produk_stock === false || $stmt_update_variasi_stock === false) {
        throw new Exception("Error preparing stock update statements: " . $conn->error);
    }

    foreach ($products_to_restore as $item) {
        if ($item['variasi_id'] !== null) { // Jika produk memiliki variasi
            $stmt_update_variasi_stock->bind_param("ii", $item['quantity'], $item['variasi_id']);
            if (!$stmt_update_variasi_stock->execute()) {
                throw new Exception("Error restoring variation stock for ID " . $item['variasi_id'] . ": " . $stmt_update_variasi_stock->error);
            }
        } else { // Jika produk tidak memiliki variasi (produk utama)
            $stmt_update_produk_stock->bind_param("ii", $item['quantity'], $item['produk_id']);
            if (!$stmt_update_produk_stock->execute()) {
                throw new Exception("Error restoring product stock for ID " . $item['produk_id'] . ": " . $stmt_update_produk_stock->error);
            }
        }
    }
    $stmt_update_produk_stock->close();
    $stmt_update_variasi_stock->close();

    // 4. Perbarui status pesanan menjadi 'dibatalkan'
    $status_dibatalkan = 'dibatalkan';
    $query_update_pesanan_status = "UPDATE pesanan SET status_pesanan = ? WHERE id = ?";
    $stmt_update_pesanan = $conn->prepare($query_update_pesanan_status);
    if ($stmt_update_pesanan === false) {
        throw new Exception("Error preparing update pesanan status: " . $conn->error);
    }
    $stmt_update_pesanan->bind_param('si', $status_dibatalkan, $order_id_to_cancel);
    if (!$stmt_update_pesanan->execute()) {
        throw new Exception("Error updating pesanan status: " . $stmt_update_pesanan->error);
    }
    $stmt_update_pesanan->close();

    // Commit transaksi jika semua operasi berhasil
    $conn->commit();
    $_SESSION['success_message'] = "Pesanan #" . htmlspecialchars($order_id_to_cancel) . " berhasil dibatalkan dan stok telah dikembalikan.";

} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan apapun
    $conn->rollback();
    $_SESSION['error_message'] = "Gagal membatalkan pesanan: " . $e->getMessage();
    error_log("Cancel Order Error (User ID: $pengguna_id, Order ID: $order_id_to_cancel): " . $e->getMessage());

} finally {
    // Tutup koneksi database
    $conn->close();
    header('Location: pesanan_saya.php');
    exit();
}
?>