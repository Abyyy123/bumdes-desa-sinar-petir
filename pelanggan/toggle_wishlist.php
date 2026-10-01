<?php
session_start(); // Pastikan session dimulai untuk mengakses $_SESSION

include('../koneksi/koneksi.php'); // Sesuaikan path ini jika perlu

header('Content-Type: application/json'); // Beri tahu browser bahwa respons adalah JSON

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("toggle_wishlist.php: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

// Pastikan pengguna sudah login
if (!isset($_SESSION['pengguna_id'])) {
    error_log("toggle_wishlist.php: User not logged in.");
    echo json_encode(['status' => 'error', 'message' => 'Anda harus login untuk mengelola wishlist.']);
    exit();
}

$pelanggan_id = $_SESSION['pengguna_id']; // Ambil ID pengguna dari sesi

// Ambil data dari body request JSON
$input = json_decode(file_get_contents('php://input'), true);

$produk_id = $input['product_id'] ?? null;
$action = $input['action'] ?? null;

// Validasi input
if (empty($produk_id) || !is_numeric($produk_id) || empty($action)) {
    error_log("toggle_wishlist.php: Invalid input data - Produk ID: " . var_export($produk_id, true) . ", Action: " . var_export($action, true));
    echo json_encode(['status' => 'error', 'message' => 'Data produk atau aksi tidak valid.']);
    exit();
}

$produk_id = (int)$produk_id;

$conn->begin_transaction(); // Mulai transaksi untuk integritas data

try {
    $message = ''; // Inisialisasi pesan
    if ($action === 'add') {
        // Cek apakah produk sudah ada di wishlist
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE pelanggan_id = ? AND produk_id = ?");
        if ($stmt === false) {
            throw new Exception("Gagal mempersiapkan query cek wishlist: " . $conn->error);
        }
        $stmt->bind_param("ii", $pelanggan_id, $produk_id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menjalankan query cek wishlist: " . $stmt->error);
        }
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Jika belum ada, tambahkan ke wishlist
            $stmt_insert = $conn->prepare("INSERT INTO wishlist (pelanggan_id, produk_id, tanggal_ditambahkan) VALUES (?, ?, NOW())");
            if ($stmt_insert === false) {
                throw new Exception("Gagal mempersiapkan query tambah wishlist: " . $conn->error);
            }
            $stmt_insert->bind_param("ii", $pelanggan_id, $produk_id);
            if (!$stmt_insert->execute()) {
                throw new Exception("Gagal menambahkan produk ke wishlist: " . $stmt_insert->error);
            }
            $stmt_insert->close();
            $message = 'Produk berhasil ditambahkan ke wishlist.';
        } else {
            $message = 'Produk sudah ada di wishlist.';
        }
        $stmt->close();

    } elseif ($action === 'remove') {
        // Hapus produk dari wishlist
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE pelanggan_id = ? AND produk_id = ?");
        if ($stmt === false) {
            throw new Exception("Gagal mempersiapkan query hapus wishlist: " . $conn->error);
        }
        $stmt->bind_param("ii", $pelanggan_id, $produk_id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menghapus produk dari wishlist: " . $stmt->error);
        }
        $stmt->close();
        $message = 'Produk berhasil dihapus dari wishlist.';

    } else {
        throw new Exception('Aksi tidak dikenal.');
    }

    // Ambil jumlah item wishlist terbaru untuk badge di navbar
    // PERBAIKAN KRUSIAL: Menggunakan $pelanggan_id yang benar
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS total_count FROM wishlist WHERE pelanggan_id = ?");
    if ($stmt_count === false) {
        throw new Exception("Gagal mempersiapkan query hitung wishlist: " . $conn->error);
    }
    $stmt_count->bind_param("i", $pelanggan_id); // <--- INI PERBAIKANNYA!
    if (!$stmt_count->execute()) {
        throw new Exception("Gagal menjalankan query hitung wishlist: " . $stmt_count->error);
    }
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $wishlist_count = $row_count['total_count'];
    $stmt_count->close();

    $conn->commit(); // Commit transaksi jika semua berhasil

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'wishlist_count' => $wishlist_count
    ]);

} catch (Exception $e) {
    $conn->rollback(); // Rollback transaksi jika terjadi kesalahan
    error_log("Wishlist Error for customer_id {$pelanggan_id}: " . $e->getMessage()); // Catat error ke log server
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    $conn->close(); // Tutup koneksi database
}
?>