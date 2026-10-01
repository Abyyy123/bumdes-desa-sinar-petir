<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan tidak dikenal.',
    'discount_amount' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['pengguna_id'])) {
        $response['message'] = 'Anda harus login untuk menggunakan kupon.';
        echo json_encode($response);
        exit();
    }

    $coupon_code = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
    $total_harga_produk = isset($_POST['total_harga_produk']) ? (float)$_POST['total_harga_produk'] : 0;

    if (empty($coupon_code)) {
        $response['message'] = 'Kode kupon tidak boleh kosong.';
        echo json_encode($response);
        exit();
    }

    // Ambil detail diskon dari tabel 'diskon'
    // HANYA memilih kolom yang terlihat di gambar struktur tabel Anda.
    $stmt = $conn->prepare("SELECT id, nama_diskon, kode_diskon, jenis_diskon, nilai_diskon, tanggal_mulai, tanggal_berakhir, status FROM diskon WHERE kode_diskon = ? AND status = 'aktif'");
    
    if ($stmt) {
        $stmt->bind_param('s', $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $diskon = $result->fetch_assoc(); // Mengganti $coupon menjadi $diskon
        $stmt->close();

        if ($diskon) {
            $today = date('Y-m-d');

            // Validasi tanggal berlaku
            // Perhatikan kolom tanggal_berakhir yang bisa NULL
            if ($today < $diskon['tanggal_mulai'] || ($diskon['tanggal_berakhir'] !== null && $today > $diskon['tanggal_berakhir'])) {
                $response['message'] = 'Kupon ini belum berlaku atau sudah kadaluarsa.';
                echo json_encode($response);
                exit();
            }

            // --- Catatan Penting ---
            // Validasi minimal_pembelian dan batas_penggunaan/digunakan
            // TIDAK DAPAT dilakukan di sini karena kolom-kolom tersebut
            // TIDAK ADA di struktur tabel diskon yang Anda berikan.
            // Jika Anda memerlukan fitur ini, Anda harus menambahkan kolom tersebut ke tabel diskon.
            // ---------------------

            // Hitung jumlah diskon
            $discount_amount = 0;
            if ($diskon['jenis_diskon'] == 'persen') {
                $discount_amount = $total_harga_produk * ($diskon['nilai_diskon'] / 100);
            } elseif ($diskon['jenis_diskon'] == 'fixed') {
                $discount_amount = $diskon['nilai_diskon'];
            }

            // Pastikan diskon tidak melebihi total harga produk
            if ($discount_amount > $total_harga_produk) {
                $discount_amount = $total_harga_produk;
            }

            // Simpan diskon yang berhasil ke sesi
            $_SESSION['applied_coupon'] = [
                'code' => $diskon['kode_diskon'],
                'discount_amount' => $discount_amount,
                'coupon_id' => $diskon['id'] // Simpan ID diskon jika Anda ingin melacaknya di pesanan
            ];

            $response['success'] = true;
            $response['message'] = 'Kupon berhasil diterapkan! Diskon: Rp ' . number_format($discount_amount, 0, ',', '.') . ' (' . htmlspecialchars($diskon['nama_diskon']) . ')';
            $response['discount_amount'] = $discount_amount;

        } else {
            $response['message'] = 'Kode kupon tidak valid atau tidak aktif.';
        }
    } else {
        $response['message'] = 'Gagal menyiapkan statement database: ' . $conn->error;
        error_log("Failed to prepare statement for coupon validation: " . $conn->error);
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

$conn->close();
echo json_encode($response);
?>