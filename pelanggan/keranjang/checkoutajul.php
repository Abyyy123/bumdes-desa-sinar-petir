<?php
session_start();
include '../../koneksi/koneksi.php'; // Sesuaikan path jika berbeda

// Cek login
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk melanjutkan checkout.";
    header('Location: ../../login.php');
    exit();
}

$pengguna_id = $_SESSION['pengguna_id'];
$selected_cart_ids = [];
$is_direct_buy = false; // Flag untuk menandakan apakah ini pembelian langsung

// --- START: Tambahan untuk handle pembelian langsung dari tombol 'Beli Sekarang' ---
$direct_produk_id = isset($_GET['produk_id']) ? (int)$_GET['produk_id'] : 0;
$direct_quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 0;
$direct_variasi_id = isset($_GET['variasi_id']) ? (int)$_GET['variasi_id'] : null;

if ($direct_produk_id > 0 && $direct_quantity > 0) {
    $is_direct_buy = true;
    // Kita tidak perlu selected_cart_ids dalam kasus ini karena tidak ada item di keranjang
    // Proses akan langsung dari produk_id, quantity, variasi_id yang dikirim
}
// --- END: Tambahan untuk handle pembelian langsung ---

// Jika ini BUKAN pembelian langsung, baru ambil ID keranjang dari parameter URL atau semua dari database
if (!$is_direct_buy) {
    if (isset($_GET['cart_ids']) && !empty($_GET['cart_ids'])) {
        $raw_ids = explode(',', $_GET['cart_ids']);
        foreach ($raw_ids as $id) {
            $clean_id = (int)trim($id);
            if ($clean_id > 0) {
                $selected_cart_ids[] = $clean_id;
            }
        }
    }

    // Jika tidak ada ID keranjang yang dipilih dari URL, ambil semua dari database
    if (empty($selected_cart_ids)) {
        $stmt_all_cart = $conn->prepare("SELECT id FROM keranjang_customer WHERE customer_id = ?");
        if ($stmt_all_cart) {
            $stmt_all_cart->bind_param("i", $pengguna_id);
            $stmt_all_cart->execute();
            $result_all_cart = $stmt_all_cart->get_result();
            while ($row = $result_all_cart->fetch_assoc()) {
                $selected_cart_ids[] = $row['id'];
            }
            $stmt_all_cart->close();
        }
    }

    // Jika setelah semua upaya masih tidak ada item, redirect
    if (empty($selected_cart_ids)) {
        $_SESSION['error_message'] = "Tidak ada produk yang dipilih untuk checkout.";
        header('Location: keranjang.php'); // Kembali ke keranjang jika tidak ada item terpilih
        exit();
    }
}

// Inisialisasi variabel untuk query
$total_harga_semua_produk = 0;
$total_berat_semua_produk = 0;
$grouped_checkout_items = [];

// --- Logika pengambilan data produk berdasarkan alur (direct buy atau dari keranjang) ---
if ($is_direct_buy) {
    // ALUR 1: Pembelian Langsung (Beli Sekarang)
    $query_direct_buy = "
        SELECT
            p.id AS produk_id,
            p.nama AS nama_produk,
            p.gambar AS gambar_produk,
            p.harga AS harga_produk,
            p.berat AS berat_produk,
            p.stok AS stok_produk,
            pen.pengguna_id AS penjual_id,
            pen.nama_toko AS nama_toko_penjual,
            pv.id AS variasi_id,
            pv.harga AS harga_variasi,
            pv.stok AS stok_variasi,
            r.nama_rasa,
            w.nama_warna,
            u.nama_ukuran,
            d.jenis_diskon,
            d.nilai_diskon,
            d.tanggal_mulai AS diskon_tanggal_mulai,
            d.tanggal_berakhir AS diskon_tanggal_berakhir,
            d.status AS diskon_status
        FROM
            produk p
        JOIN
            penjual pen ON p.penjual_id = pen.pengguna_id
        LEFT JOIN
            produk_variasi pv ON p.id = pv.produk_id AND pv.id = ? -- Join dengan variasi hanya jika ada
        LEFT JOIN
            rasa r ON pv.rasa_id = r.id
        LEFT JOIN
            warna w ON pv.warna_id = w.id
        LEFT JOIN
            ukuran u ON pv.ukuran_id = u.id
        LEFT JOIN
            produk_diskon pd ON p.id = pd.produk_id
        LEFT JOIN
            diskon d ON pd.diskon_id = d.id
        WHERE
            p.id = ?
    ";
    $stmt_direct = $conn->prepare($query_direct_buy);

    if ($stmt_direct === false) {
        $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat produk langsung: " . htmlspecialchars($conn->error);
        header('Location: ../../index.php'); // Redirect ke halaman utama
        exit();
    }

    if ($direct_variasi_id) {
        $stmt_direct->bind_param("ii", $direct_variasi_id, $direct_produk_id);
    } else {
        // Jika tidak ada variasi_id, bind hanya produk_id, dan set variasi_id ke null
        // Perlu query yang sedikit berbeda atau penanganan binding yang cerdas
        // Untuk simplifikasi, kita asumsikan produk_variasi.id akan null jika variasi_id tidak ada
        // Atau, bisa buat query terpisah untuk produk tanpa variasi
        $stmt_direct->bind_param("ii", $direct_produk_id, $direct_produk_id); // Bound twice to make sure it matches structure, but variasi_id will be ignored by NULL
                                                                            // This specific 'ii' binding is problematic for `pv.id = ? AND p.id = ?` if `direct_variasi_id` is null.
                                                                            // A better approach is to modify the query or use conditional prepare/bind.
                                                                            // For now, let's just make sure the `pv.id = ?` part is properly handled.
                                                                            // Corrected logic:
        $query_direct_buy_no_variation = "
            SELECT
                p.id AS produk_id,
                p.nama AS nama_produk,
                p.gambar AS gambar_produk,
                p.harga AS harga_produk,
                p.berat AS berat_produk,
                p.stok AS stok_produk,
                pen.pengguna_id AS penjual_id,
                pen.nama_toko AS nama_toko_penjual,
                NULL AS variasi_id, -- Set variasi_id null
                NULL AS harga_variasi,
                NULL AS stok_variasi,
                NULL AS nama_rasa,
                NULL AS nama_warna,
                NULL AS nama_ukuran,
                d.jenis_diskon,
                d.nilai_diskon,
                d.tanggal_mulai AS diskon_tanggal_mulai,
                d.tanggal_berakhir AS diskon_tanggal_berakhir,
                d.status AS diskon_status
            FROM
                produk p
            JOIN
                penjual pen ON p.penjual_id = pen.pengguna_id
            LEFT JOIN
                produk_diskon pd ON p.id = pd.produk_id
            LEFT JOIN
                diskon d ON pd.diskon_id = d.id
            WHERE
                p.id = ?
        ";
        if ($direct_variasi_id === null) {
            $stmt_direct = $conn->prepare($query_direct_buy_no_variation);
            $stmt_direct->bind_param("i", $direct_produk_id);
        } else {
            // original query for variation
            $stmt_direct->bind_param("ii", $direct_variasi_id, $direct_produk_id);
        }
    }


    $stmt_direct->execute();
    $result_direct = $stmt_direct->get_result();

    if ($result_direct->num_rows > 0) {
        $row = $result_direct->fetch_assoc();
        
        $harga_satuan_awal = $row['variasi_id'] ? $row['harga_variasi'] : $row['harga_produk'];
        // Fallback jika variasi harga null
        if ($row['variasi_id'] && $harga_satuan_awal === null) {
            $harga_satuan_awal = $row['harga_produk'];
        }

        $harga_satuan_setelah_diskon = $harga_satuan_awal;
        $ada_diskon_terapkan = false;

        // Cek dan terapkan diskon jika ada dan valid
        if ($row['diskon_status'] == 'aktif' && $row['nilai_diskon'] !== null) {
            $today = date('Y-m-d');
            $diskon_mulai = $row['diskon_tanggal_mulai'];
            $diskon_berakhir = $row['diskon_tanggal_berakhir'];

            if ($today >= $diskon_mulai && ($diskon_berakhir === null || $today <= $diskon_berakhir)) {
                $ada_diskon_terapkan = true;
                if ($row['jenis_diskon'] == 'persen') {
                    $harga_satuan_setelah_diskon = $harga_satuan_awal * (1 - ($row['nilai_diskon'] / 100));
                } elseif ($row['jenis_diskon'] == 'fixed') {
                    $harga_satuan_setelah_diskon = $harga_satuan_awal - $row['nilai_diskon'];
                    if ($harga_satuan_setelah_diskon < 0) $harga_satuan_setelah_diskon = 0; // Harga tidak boleh negatif
                }
            }
        }

        $stok_tersedia = $row['variasi_id'] ? $row['stok_variasi'] : $row['stok_produk'];

        // Cek stok untuk pembelian langsung
        if ($direct_quantity > $stok_tersedia) {
            $_SESSION['error_message'] = "Kuantitas produk '{$row['nama_produk']}' melebihi stok yang tersedia ({$stok_tersedia}).";
            header('Location: ../../detail_produk.php?id=' . $direct_produk_id); // Kembali ke detail produk
            exit();
        }

        $variasi_detail = [];
        if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
        if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
        if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }

        $item_data = [
            // keranjang_id tidak relevan untuk direct buy, bisa set null atau 0
            'keranjang_id' => null,
            'produk_id' => $row['produk_id'],
            'variasi_id' => $row['variasi_id'],
            'nama_produk' => htmlspecialchars($row['nama_produk']),
            'gambar_produk' => htmlspecialchars($row['gambar_produk']),
            'harga_satuan_awal' => $harga_satuan_awal,
            'harga_satuan_final' => $harga_satuan_setelah_diskon,
            'ada_diskon' => $ada_diskon_terapkan,
            'jenis_diskon' => $row['jenis_diskon'],
            'nilai_diskon' => $row['nilai_diskon'],
            'quantity' => $direct_quantity, // Gunakan kuantitas dari URL
            'subtotal' => $harga_satuan_setelah_diskon * $direct_quantity,
            'stok_tersedia' => $stok_tersedia,
            'berat_total_item' => $row['berat_produk'] * $direct_quantity,
            'variasi_string' => implode(', ', $variasi_detail)
        ];

        // Kelompokkan item ini di bawah penjualnya
        if (!isset($grouped_checkout_items[$row['penjual_id']])) {
            $grouped_checkout_items[$row['penjual_id']] = [
                'nama_toko' => htmlspecialchars($row['nama_toko_penjual']),
                'items' => [],
                'total_per_toko' => 0,
                'total_berat_per_toko' => 0
            ];
        }
        $grouped_checkout_items[$row['penjual_id']]['items'][] = $item_data;
        $grouped_checkout_items[$row['penjual_id']]['total_per_toko'] += $item_data['subtotal'];
        $grouped_checkout_items[$row['penjual_id']]['total_berat_per_toko'] += $item_data['berat_total_item'];
        $total_harga_semua_produk += $item_data['subtotal'];
        $total_berat_semua_produk += $item_data['berat_total_item'];

    } else {
        $_SESSION['error_message'] = "Produk yang ingin Anda beli tidak ditemukan.";
        header('Location: ../../index.php'); // Kembali ke halaman utama
        exit();
    }
    $stmt_direct->close();

} else {
    // ALUR 2: Checkout dari Keranjang (Logika yang sudah ada)

    // Ubah array ID menjadi string untuk query SQL IN clause
    $placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
    $types = str_repeat('i', count(array_filter($selected_cart_ids, 'is_numeric'))); // Filter non-numeric to avoid error

    // Query untuk mengambil detail produk yang dipilih dari keranjang
    // Termasuk informasi diskon dan penjual
    $query_checkout = "
        SELECT
            kc.id AS keranjang_id,
            kc.produk_id,
            kc.variasi_id,
            kc.quantity,
            p.nama AS nama_produk,
            p.gambar AS gambar_produk,
            p.harga AS harga_produk,
            p.berat AS berat_produk,
            pv.harga AS harga_variasi,
            pv.stok AS stok_variasi,
            p.stok AS stok_produk,
            r.nama_rasa,
            w.nama_warna,
            u.nama_ukuran,
            pen.pengguna_id AS penjual_id,
            pen.nama_toko AS nama_toko_penjual,
            d.jenis_diskon,
            d.nilai_diskon,
            d.tanggal_mulai AS diskon_tanggal_mulai,
            d.tanggal_berakhir AS diskon_tanggal_berakhir,
            d.status AS diskon_status
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
        JOIN
            penjual pen ON p.penjual_id = pen.pengguna_id
        LEFT JOIN
            produk_diskon pd ON p.id = pd.produk_id
        LEFT JOIN
            diskon d ON pd.diskon_id = d.id
        WHERE
            kc.id IN ($placeholders) AND kc.customer_id = ?
        ORDER BY pen.nama_toko, kc.tanggal_ditambahkan DESC
    ";

    $stmt_checkout = $conn->prepare($query_checkout);
    if ($stmt_checkout === false) {
        $_SESSION['error_message'] = "Terjadi kesalahan sistem saat memuat produk checkout: " . htmlspecialchars($conn->error);
        header('Location: keranjang.php');
        exit();
    }

    // Bind parameter (ID keranjang dan customer_id)
    $params = array_merge($selected_cart_ids, [$pengguna_id]);

    // Perbaikan bind_param untuk array dinamis (menggunakan referensi)
    $bind_params = [];
    $bind_params[] = $types . 'i'; // String tipe data pertama
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key]; // Menggunakan referensi untuk setiap parameter
    }
    call_user_func_array([$stmt_checkout, 'bind_param'], $bind_params);

    $stmt_checkout->execute();
    $result_checkout = $stmt_checkout->get_result();

    if ($result_checkout->num_rows > 0) {
        while ($row = $result_checkout->fetch_assoc()) {
            $harga_satuan_awal = $row['variasi_id'] ? $row['harga_variasi'] : $row['harga_produk'];
            // Fallback jika variasi harga null
            if ($row['variasi_id'] && $harga_satuan_awal === null) {
                $harga_satuan_awal = $row['harga_produk'];
            }

            $harga_satuan_setelah_diskon = $harga_satuan_awal;
            $ada_diskon_terapkan = false;

            // Cek dan terapkan diskon jika ada dan valid
            if ($row['diskon_status'] == 'aktif' && $row['nilai_diskon'] !== null) {
                $today = date('Y-m-d');
                $diskon_mulai = $row['diskon_tanggal_mulai'];
                $diskon_berakhir = $row['diskon_tanggal_berakhir'];

                if ($today >= $diskon_mulai && ($diskon_berakhir === null || $today <= $diskon_berakhir)) {
                    $ada_diskon_terapkan = true;
                    if ($row['jenis_diskon'] == 'persen') {
                        $harga_satuan_setelah_diskon = $harga_satuan_awal * (1 - ($row['nilai_diskon'] / 100));
                    } elseif ($row['jenis_diskon'] == 'fixed') {
                        $harga_satuan_setelah_diskon = $harga_satuan_awal - $row['nilai_diskon'];
                        if ($harga_satuan_setelah_diskon < 0) $harga_satuan_setelah_diskon = 0; // Harga tidak boleh negatif
                    }
                }
            }

            $stok_tersedia = $row['variasi_id'] ? $row['stok_variasi'] : $row['stok_produk'];

            // Cek stok
            if ($row['quantity'] > $stok_tersedia) {
                $_SESSION['error_message'] = "Kuantitas produk '{$row['nama_produk']}' melebihi stok yang tersedia ({$stok_tersedia}). Silakan sesuaikan di keranjang.";
                header('Location: keranjang.php'); // Redirect kembali ke keranjang
                exit();
            }

            $variasi_detail = [];
            if (!empty($row['nama_rasa'])) { $variasi_detail[] = 'Rasa: ' . htmlspecialchars($row['nama_rasa']); }
            if (!empty($row['nama_warna'])) { $variasi_detail[] = 'Warna: ' . htmlspecialchars($row['nama_warna']); }
            if (!empty($row['nama_ukuran'])) { $variasi_detail[] = 'Ukuran: ' . htmlspecialchars($row['nama_ukuran']); }

            $item_data = [
                'keranjang_id' => $row['keranjang_id'],
                'produk_id' => $row['produk_id'],
                'variasi_id' => $row['variasi_id'],
                'nama_produk' => htmlspecialchars($row['nama_produk']),
                'gambar_produk' => htmlspecialchars($row['gambar_produk']),
                'harga_satuan_awal' => $harga_satuan_awal,
                'harga_satuan_final' => $harga_satuan_setelah_diskon,
                'ada_diskon' => $ada_diskon_terapkan,
                'jenis_diskon' => $row['jenis_diskon'],
                'nilai_diskon' => $row['nilai_diskon'],
                'quantity' => $row['quantity'],
                'subtotal' => $harga_satuan_setelah_diskon * $row['quantity'],
                'stok_tersedia' => $stok_tersedia,
                'berat_total_item' => $row['berat_produk'] * $row['quantity'],
                'variasi_string' => implode(', ', $variasi_detail)
            ];

            if (!isset($grouped_checkout_items[$row['penjual_id']])) {
                $grouped_checkout_items[$row['penjual_id']] = [
                    'nama_toko' => htmlspecialchars($row['nama_toko_penjual']),
                    'items' => [],
                    'total_per_toko' => 0,
                    'total_berat_per_toko' => 0
                ];
            }
            $grouped_checkout_items[$row['penjual_id']]['items'][] = $item_data;
            $grouped_checkout_items[$row['penjual_id']]['total_per_toko'] += $item_data['subtotal'];
            $grouped_checkout_items[$row['penjual_id']]['total_berat_per_toko'] += $item_data['berat_total_item'];
            $total_harga_semua_produk += $item_data['subtotal'];
            $total_berat_semua_produk += $item_data['berat_total_item'];
        }
    } else {
        $_SESSION['error_message'] = "Produk yang dipilih tidak ditemukan di keranjang Anda atau sudah tidak valid.";
        header('Location: keranjang.php');
        exit();
    }
    $stmt_checkout->close();
}
// --- END: Logika pengambilan data produk ---

// --- Logika untuk Alamat Pengiriman (TETAP SAMA) ---
$customer_address = null;
$query_address = "SELECT nama, alamat, nomor_telepon FROM pelanggan WHERE pengguna_id = ?";
$stmt_address = $conn->prepare($query_address);
if ($stmt_address) {
    $stmt_address->bind_param('i', $pengguna_id);
    $stmt_address->execute();
    $result_address = $stmt_address->get_result();
    if ($result_address->num_rows > 0) {
        $customer_address = $result_address->fetch_assoc();
    } else {
        $customer_address = [
            'nama' => 'Nama Belum Diatur',
            'alamat' => 'Alamat Belum Diatur',
            'nomor_telepon' => 'Nomor Telepon Belum Diatur'
        ];
    }
    $stmt_address->close();
} else {
    $customer_address = [
        'nama' => 'Nama Tidak Tersedia',
        'alamat' => 'Alamat Tidak Tersedia',
        'nomor_telepon' => 'Nomor Telepon Tidak Tersedia'
    ];
}

// --- Logika untuk Metode Pengiriman (Kurir) (TETAP SAMA) ---
$available_couriers = [];
$query_couriers = "SELECT id, nama FROM kurir WHERE status = 'aktif' ORDER BY nama ASC";
$result_couriers = $conn->query($query_couriers);
if ($result_couriers) {
    while ($row = $result_couriers->fetch_assoc()) {
        $available_couriers[] = $row;
    }
    $result_couriers->close();
}

// --- Logika untuk Metode Pembayaran (Dari database) (TETAP SAMA) ---
$available_payment_methods = [];
$query_payment_methods = "SELECT id, nama_metode, kode_metode, logo_metode, tipe_pembayaran FROM metode_pembayaran WHERE aktif_platform = TRUE ORDER BY nama_metode ASC";
$result_payment_methods = $conn->query($query_payment_methods);
if ($result_payment_methods) {
    while ($row = $result_payment_methods->fetch_assoc()) {
        $available_payment_methods[] = $row;
    }
    $result_payment_methods->close();
} else {
    error_log("Database Error (Metode Pembayaran): " . $conn->error);
    $_SESSION['error_message'] = "Gagal memuat metode pembayaran. Silakan coba lagi nanti.";
    $available_payment_methods = [];
}

// --- Logika untuk Mengambil Detail Pembayaran dari pengaturan_pembayaran_penjual (TETAP SAMA) ---
$payment_details_by_seller = [];
$query_seller_payment_details = "
    SELECT
        p.pengguna_id AS penjual_id,
        m.kode_metode,
        m.tipe_pembayaran,
        m.nama_metode,
        ppp.detail_akun AS nomor_rekening,
        ppp.nama_pemilik_akun AS nama_pemilik,
        m.nama_metode AS nama_bank_metode,
        m.logo_metode AS qr_code_image_metode,
        m.aktif_platform
    FROM
        penjual p
    JOIN
        pengaturan_pembayaran_penjual ppp ON p.pengguna_id = ppp.pengguna_id
    JOIN
        metode_pembayaran m ON ppp.metode_pembayaran_id = m.id
    WHERE
        m.aktif_platform = 1
";
$result_seller_payment_details = $conn->query($query_seller_payment_details);
if ($result_seller_payment_details) {
    while ($row = $result_seller_payment_details->fetch_assoc()) {
        $penjual_id = $row['penjual_id'];
        $kode_metode = $row['kode_metode'];

        if (!isset($payment_details_by_seller[$penjual_id])) {
            $payment_details_by_seller[$penjual_id] = [];
        }

        if ($row['aktif_platform'] == 1) {
            $payment_details_by_seller[$penjual_id][$kode_metode] = [
                'tipe_pembayaran' => $row['tipe_pembayaran'],
                'nama_metode' => $row['nama_metode'],
                'nomor_rekening' => $row['nomor_rekening'],
                'nama_pemilik' => $row['nama_pemilik'],
                'nama_bank' => $row['nama_bank_metode'],
                'qr_code_image' => $row['qr_code_image_metode']
            ];
        }
    }
    $result_seller_payment_details->close();
} else {
    error_log("Database Error (Pengaturan Pembayaran Penjual): " . $conn->error);
}


$conn->close(); // Tutup koneksi setelah semua data diambil
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .product-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .payment-method-item {
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-radius: .25rem;
            margin-bottom: 10px;
            padding: 10px;
            transition: all 0.2s;
        }
        .payment-method-item:hover {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .payment-method-item.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            background-color: #e7f0ff;
        }
        .payment-method-item img {
            max-height: 40px;
            margin-right: 10px;
        }
        .summary-card .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="mb-4 text-center">Checkout Pesanan</h2>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="alert alert-info main-message" role="alert" style="display:none;"></div>

        <form id="checkoutForm" action="../checkout/process_checkout.php" method="POST">
            <?php if ($is_direct_buy): ?>
                <input type="hidden" name="direct_produk_id" value="<?php echo $direct_produk_id; ?>">
                <input type="hidden" name="direct_quantity" value="<?php echo $direct_quantity; ?>">
                <input type="hidden" name="direct_variasi_id" value="<?php echo $direct_variasi_id; ?>">
            <?php else: ?>
                <input type="hidden" name="selected_cart_ids" value="<?php echo implode(',', $selected_cart_ids); ?>">
            <?php endif; ?>
            <input type="hidden" name="is_direct_buy" value="<?php echo $is_direct_buy ? '1' : '0'; ?>">


            <input type="hidden" name="coupon_code_applied" id="couponCodeApplied" value="">
            <input type="hidden" name="coupon_discount_amount" id="couponDiscountAmount" value="0">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5><i class="bi bi-geo-alt"></i> Alamat Pengiriman</h5>
                        </div>
                        <div class="card-body">
                            <p id="display_nama"><b><?php echo htmlspecialchars($customer_address['nama']); ?></b></p>
                            <p id="display_alamat"><?php echo htmlspecialchars($customer_address['alamat']); ?></p>
                            <p id="display_telepon"><?php echo htmlspecialchars($customer_address['nomor_telepon']); ?></p>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAddressModal">
                                <i class="bi bi-pencil"></i> Ubah Alamat
                            </button>
                        </div>
                    </div>

                    <?php foreach ($grouped_checkout_items as $penjual_id => $store_data): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <strong>Toko: <?php echo $store_data['nama_toko']; ?></strong>
                            </div>
                            <div class="card-body">
                                <?php foreach ($store_data['items'] as $item): ?>
                                    <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                                        <img src="../../img/barang/<?php echo $item['gambar_produk']; ?>" alt="<?php echo $item['nama_produk']; ?>" class="product-img me-3">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo $item['nama_produk']; ?></h6>
                                            <?php if (!empty($item['variasi_string'])): ?>
                                                <small class="text-muted"><?php echo $item['variasi_string']; ?></small><br>
                                            <?php endif; ?>
                                            <small class="text-muted">Kuantitas: <?php echo $item['quantity']; ?></small><br>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    Harga:
                                                    <?php if ($item['ada_diskon']): ?>
                                                        <small class="text-decoration-line-through text-muted">Rp <?php echo number_format($item['harga_satuan_awal'], 0, ',', '.'); ?></small>
                                                        <span class="text-success fw-bold">Rp <?php echo number_format($item['harga_satuan_final'], 0, ',', '.'); ?></span>
                                                    <?php else: ?>
                                                        <span class="fw-bold">Rp <?php echo number_format($item['harga_satuan_final'], 0, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="fw-bold text-end">Subtotal: Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="mt-3">
                                    <label for="kurir_<?php echo $penjual_id; ?>" class="form-label">Pilih Kurir untuk <?php echo $store_data['nama_toko']; ?> (Total Berat: <?php echo number_format($store_data['total_berat_per_toko'], 2, ',', '.'); ?> kg):</label>
                                    <select class="form-select kurir-select" id="kurir_<?php echo $penjual_id; ?>" name="kurir[<?php echo $penjual_id; ?>]" data-store-id="<?php echo $penjual_id; ?>" data-total-berat="<?php echo $store_data['total_berat_per_toko']; ?>">
                                        <option value="">Pilih Kurir</option>
                                        <?php foreach ($available_couriers as $courier): ?>
                                            <option value="<?php echo htmlspecialchars($courier['id']); ?>">
                                                <?php echo htmlspecialchars($courier['nama']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-danger mt-1 kurir-error" style="display:none;">Silakan pilih kurir.</small>
                                    <div class="mt-2 text-end">
                                        Ongkos Kirim: <span id="ongkir_<?php echo $penjual_id; ?>" class="fw-bold">Rp 0</span>
                                        <input type="hidden" name="ongkir_values[<?php echo $penjual_id; ?>]" id="hidden_ongkir_<?php echo $penjual_id; ?>" value="0">
                                    </div>
                                    <div class="mt-3">
                                        <label for="catatan_penjual_<?php echo $penjual_id; ?>" class="form-label">Catatan untuk Penjual <?php echo $store_data['nama_toko']; ?> (Opsional):</label>
                                        <textarea class="form-control" id="catatan_penjual_<?php echo $penjual_id; ?>" name="catatan_penjual[<?php echo $penjual_id; ?>]" rows="2" placeholder="Contoh: Tolong bungkus bubble wrap tebal..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h5><i class="bi bi-chat-dots"></i> Catatan Umum untuk Pesanan (Opsional)</h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" id="catatan_pelanggan" name="catatan_pelanggan" rows="3" placeholder="Tambahkan catatan umum untuk seluruh pesanan Anda (misal: jam pengiriman yang diinginkan)..."></textarea>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h5><i class="bi bi-credit-card"></i> Pilih Metode Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div id="paymentMethodsContainer">
                                <?php if (!empty($available_payment_methods)): ?>
                                    <?php foreach ($available_payment_methods as $method): ?>
                                        <div class="payment-method-item d-flex align-items-center" data-method-id="<?php echo htmlspecialchars($method['id']); ?>" data-method-code="<?php echo htmlspecialchars($method['kode_metode']); ?>" data-method-type="<?php echo htmlspecialchars($method['tipe_pembayaran']); ?>">
                                            <input type="radio" name="metode_pembayaran" id="metode_pembayaran<?php echo htmlspecialchars($method['id']); ?>" value="<?php echo htmlspecialchars($method['id']); ?>" class="form-check-input me-2 d-none">
                                            <?php if (!empty($method['logo_metode'])): ?>
                                                <img src="../../img/metode_pembayaran/<?php echo htmlspecialchars($method['logo_metode']); ?>" alt="<?php echo htmlspecialchars($method['nama_metode']); ?>">
                                            <?php endif; ?>
                                            <label for="metode_<?php echo htmlspecialchars($method['id']); ?>" class="form-check-label flex-grow-1">
                                                <?php echo htmlspecialchars($method['nama_metode']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-danger">Tidak ada metode pembayaran yang tersedia.</p>
                                <?php endif; ?>
                            </div>
                            <small class="text-danger mt-1 payment-method-error" style="display:none;">Silakan pilih metode pembayaran.</small>

                            <div id="transferDetails" class="mt-3 card card-body bg-light" style="display: none;">
                                <h6>Detail Transfer Bank:</h6>
                                <p>Bank: <span id="detailBankName" class="fw-bold"></span></p>
                                <p>Nomor Rekening: <span id="detailRekeningNumber" class="fw-bold"></span></p>
                                <p>Atas Nama: <span id="detailAccountName" class="fw-bold"></span></p>
                                <small class="text-info">Pastikan Anda mentransfer ke rekening yang benar.</small>
                                <div class="alert alert-danger bank-error mt-2" style="display:none;">Detail bank tidak ditemukan atau tidak lengkap untuk toko ini.</div>
                            </div>

                            <div id="ewalletDetails" class="mt-3 card card-body bg-light" style="display: none;">
                                <h6>Detail E-Wallet:</h6>
                                <p><span id="ewalletName" class="fw-bold"></span>: <span id="ewalletNumber" class="fw-bold"></span></p>
                                <small class="text-info">Scan QR code atau transfer ke nomor di atas.</small>
                                <div class="alert alert-danger ewallet-error mt-2" style="display:none;">Detail E-Wallet tidak ditemukan atau tidak lengkap untuk toko ini.</div>
                            </div>
                            <div id="qrisDetails" class="mt-3 card card-body bg-light text-center" style="display: none;">
                                <h6>Pembayaran QRIS:</h6>
                                <img id="qrisImage" src="../../img/metode_pembayaran/qris.png" alt="QRIS Code" class="img-fluid mb-2" style="max-width: 200px;">
                                <p class="text-info">Scan QR code di atas menggunakan aplikasi pembayaran Anda.</p>
                                <div class="alert alert-danger qris-error mt-2" style="display:none;">QR code tidak ditemukan atau tidak valid untuk toko ini.</div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card summary-card">
                        <div class="card-header">
                            <h5>Ringkasan Belanja</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    Total Harga Produk
                                    <span>Rp <span id="summaryProductTotal"><?php echo number_format($total_harga_semua_produk, 0, ',', '.'); ?></span></span>
                                </li>
                                <li class="list-group-item">
                                    Total Ongkos Kirim
                                    <span>Rp <span id="summaryShippingTotal">0</span></span>
                                </li>
                                <li class="list-group-item" id="couponDiscountRow" style="display: none;">
                                    Diskon Kupon (<span id="couponCodeDisplay"></span>)
                                    <span class="text-success">- Rp <span id="summaryCouponDiscount">0</span></span>
                                </li>
                                <li class="list-group-item fw-bold">
                                    Total Pembayaran
                                    <span>Rp <span id="summaryGrandTotal"><?php echo number_format($total_harga_semua_produk, 0, ',', '.'); ?></span></span>
                                </li>
                            </ul>
                            <div class="mt-3">
                                <label for="couponCode" class="form-label">Kode Kupon (Opsional):</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="couponCode" placeholder="Masukkan kode kupon">
                                    <button class="btn btn-outline-secondary" type="button" id="applyCouponBtn">Terapkan</button>
                                </div>
                                <small class="text-danger mt-1" id="couponError" style="display:none;"></small>
                                <small class="text-success mt-1" id="couponSuccess" style="display:none;"></small>
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="btnBuatPesanan">
                                    <i class="bi bi-cart-check"></i> Buat Pesanan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">Ubah Alamat Pengiriman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_nama" class="form-label">Nama Penerima</label>
                        <input type="text" class="form-control" id="modal_nama" value="<?php echo htmlspecialchars($customer_address['nama']); ?>">
                        <small class="text-danger" id="error_nama" style="display:none;"></small>
                    </div>
                    <div class="mb-3">
                        <label for="modal_alamat" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="modal_alamat" rows="3"><?php echo htmlspecialchars($customer_address['alamat']); ?></textarea>
                        <small class="text-danger" id="error_alamat" style="display:none;"></small>
                    </div>
                    <div class="mb-3">
                        <label for="modal_telepon" class="form-label">Nomor Telepon</label>
                        <input type="tel" class="form-control" id="modal_telepon" value="<?php echo htmlspecialchars($customer_address['nomor_telepon']); ?>">
                        <small class="text-danger" id="error_telepon" style="display:none;"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="saveAddressBtn">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            let totalProductPrice = <?php echo $total_harga_semua_produk; ?>;
            let totalShippingCost = 0;
            let storeShippingCosts = {}; // Menyimpan ongkir per toko
            let couponDiscount = 0; // Tambahkan variabel untuk diskon kupon
            let selectedPaymentMethodCode = '';
            let selectedPaymentMethodId = '';
            let selectedCourierPerStore = {}; // Menyimpan kurir terpilih per toko

            // Inisialisasi total berat per toko dari PHP
            const storeWeights = {};
            <?php foreach ($grouped_checkout_items as $penjual_id => $store_data): ?>
                storeWeights[<?php echo $penjual_id; ?>] = <?php echo $store_data['total_berat_per_toko']; ?>;
                storeShippingCosts[<?php echo $penjual_id; ?>] = 0; // Inisialisasi ongkir toko
            <?php endforeach; ?>

            // PHP variable to JS for payment details by seller
            const paymentDetailsBySeller = <?php echo json_encode($payment_details_by_seller); ?>;
            // console.log("Payment Details by Seller:", paymentDetailsBySeller); // Debugging

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(amount);
            }

            function updateGrandTotal() {
                totalShippingCost = Object.values(storeShippingCosts).reduce((a, b) => a + b, 0);
                let grandTotal = totalProductPrice + totalShippingCost - couponDiscount;
                if (grandTotal < 0) grandTotal = 0; // Pastikan total tidak negatif

                $('#summaryShippingTotal').text(formatRupiah(totalShippingCost).replace('Rp', '').trim());
                $('#summaryGrandTotal').text(formatRupiah(grandTotal).replace('Rp', '').trim());
            }

            // Inisialisasi awal
            updateGrandTotal();

            // Handle perubahan pilihan kurir
            // NOTE: Perhitungan ongkir di sini masih dummy. Anda perlu mengintegrasikan dengan API RajaOngkir atau sejenisnya.
            // Data 'courierRates' dummy telah dihapus.
            $('.kurir-select').change(function() {
                const storeId = $(this).data('store-id');
                const selectedCourierId = $(this).val();
                const totalBerat = parseFloat($(this).data('total-berat'));
                let ongkir = 0;

                selectedCourierPerStore[storeId] = selectedCourierId;

                // --- START: Ini adalah bagian yang perlu diintegrasikan dengan API RajaOngkir atau kurir lainnya ---
                // Untuk DEMO, kita akan anggap ada tarif dasar per kg atau per pengiriman
                // Di sini Anda akan membuat AJAX call ke endpoint backend Anda
                // yang kemudian memanggil API pihak ketiga (misal: RajaOngkir)
                // Contoh dummy rate (GANTI DENGAN LOGIKA SESUNGGUHNYA!)
                if (selectedCourierId) {
                    // Simulasikan tarif: misalnya 5000 per kg untuk kurir apapun yang dipilih
                    ongkir = Math.ceil(totalBerat) * 5000;
                    if (ongkir === 0 && totalBerat > 0) ongkir = 5000; // Minimal ongkir jika ada berat
                }
                // --- END: Bagian integrasi API ---

                if (selectedCourierId) {
                    $(`#ongkir_${storeId}`).text(formatRupiah(ongkir).replace('Rp', '').trim());
                    $(`#hidden_ongkir_${storeId}`).val(ongkir);
                    storeShippingCosts[storeId] = ongkir;
                    $(this).siblings('.kurir-error').hide(); // Sembunyikan error
                } else {
                    $(`#ongkir_${storeId}`).text('Rp 0');
                    $(`#hidden_ongkir_${storeId}`).val(0);
                    storeShippingCosts[storeId] = 0;
                    $(this).siblings('.kurir-error').show(); // Tampilkan error jika tidak ada kurir terpilih
                }
                updateGrandTotal();
            });

            // Handle perubahan pilihan metode pembayaran
            $('.payment-method-item').click(function() {
                $('.payment-method-item').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);

                selectedPaymentMethodId = $(this).data('method-id');
                selectedPaymentMethodCode = $(this).data('method-code');
                const methodType = $(this).data('method-type');

                $('.payment-method-error').hide(); // Sembunyikan error metode pembayaran
                $('.bank-error, .ewallet-error, .qris-error').hide(); // Sembunyikan error detail pembayaran

                // Sembunyikan semua detail pembayaran terlebih dahulu
                $('#transferDetails').hide();
                $('#ewalletDetails').hide();
                $('#qrisDetails').hide();

                // Dapatkan ID penjual dari item pertama yang dicheckout (asumsi semua item dari satu penjual untuk simplicity
                // atau Anda perlu loop semua penjual dan cari metode pembayaran untuk setiap toko)
                // Untuk checkout ini, kita berasumsi semua item yang dicheckout akan dibayar ke platform,
                // dan platform yang akan mendistribusikan ke penjual.
                // Jika pembayaran langsung ke penjual, Anda perlu menentukan 'penjual_id' yang relevan.
                // Untuk simplifikasi, kita akan mengambil detail dari penjual pertama yang ada di grouped_checkout_items.
                const firstSellerId = Object.keys(paymentDetailsBySeller)[0];
                let methodDetails = null;

                if (firstSellerId && paymentDetailsBySeller[firstSellerId] && paymentDetailsBySeller[firstSellerId][selectedPaymentMethodCode]) {
                    methodDetails = paymentDetailsBySeller[firstSellerId][selectedPaymentMethodCode];
                } else {
                    // Fallback: Jika tidak ada detail pembayaran spesifik per penjual,
                    // Anda bisa menambahkan logika untuk mengambil detail pembayaran platform (jika ada).
                    // Untuk saat ini, kita akan menandai sebagai tidak ditemukan.
                    methodDetails = null;
                }

                // Tampilkan detail sesuai tipe pembayaran
                if (methodType === 'bank_transfer') {
                    $('#transferDetails').show();
                    if (methodDetails && methodDetails.nama_bank && methodDetails.nomor_rekening && methodDetails.nama_pemilik) {
                        $('#detailBankName').text(methodDetails.nama_bank); // Ini akan mengambil nama metode pembayaran
                        $('#detailRekeningNumber').text(methodDetails.nomor_rekening);
                        $('#detailAccountName').text(methodDetails.nama_pemilik);
                    } else {
                        $('#detailBankName').text('Informasi Bank Tidak Tersedia');
                        $('#detailRekeningNumber').text('N/A');
                        $('#detailAccountName').text('N/A');
                        $('.bank-error').show();
                    }
                } else if (methodType === 'e_wallet') {
                    $('#ewalletDetails').show();
                    if (methodDetails && methodDetails.nama_metode && methodDetails.nomor_rekening) {
                        $('#ewalletName').text(methodDetails.nama_metode);
                        $('#ewalletNumber').text(methodDetails.nomor_rekening);
                    } else {
                        $('#ewalletName').text('Informasi E-Wallet Tidak Tersedia');
                        $('#ewalletNumber').text('N/A');
                        $('.ewallet-error').show();
                    }
                } else if (methodType === 'qris') {
                    $('#qrisDetails').show();
                    if (methodDetails && methodDetails.qr_code_image) {
                         $('#qrisImage').attr('src', `../../img/metode_pembayaran/${methodDetails.qr_code_image}`);
                    } else {
                         $('#qrisImage').attr('src', '../../img/metode_pembayaran/qris.png'); // Fallback default QRIS
                         $('.qris-error').show(); // Tampilkan error jika tidak ada QRIS spesifik
                    }
                }
            });

            // Handle simpan perubahan alamat (modal)
            $('#saveAddressBtn').click(function() {
                let isValid = true;
                const nama = $('#modal_nama').val().trim();
                const alamat = $('#modal_alamat').val().trim();
                const telepon = $('#modal_telepon').val().trim();

                if (nama === '') {
                    $('#error_nama').text('Nama tidak boleh kosong.').show();
                    isValid = false;
                } else {
                    $('#error_nama').hide();
                }

                if (alamat === '') {
                    $('#error_alamat').text('Alamat tidak boleh kosong.').show();
                    isValid = false;
                } else {
                    $('#error_alamat').hide();
                }

                if (telepon === '') {
                    $('#error_telepon').text('Nomor telepon tidak boleh kosong.').show();
                    isValid = false;
                } else {
                    $('#error_telepon').hide();
                }

                if (isValid) {
                    // AJAX call to save address changes
                    $.ajax({
                        url: 'update_address.php', // Create this file to handle address updates
                        method: 'POST',
                        data: {
                            nama: nama,
                            alamat: alamat,
                            nomor_telepon: telepon,
                            _token: 'YOUR_CSRF_TOKEN' // If you use CSRF protection
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#display_nama').html(`<b>${nama}</b>`);
                                $('#display_alamat').text(alamat);
                                $('#display_telepon').text(telepon);

                                const modal = bootstrap.Modal.getInstance(document.getElementById('editAddressModal'));
                                modal.hide();

                                $('.main-message').removeClass('alert-danger').addClass('alert-success').text('Alamat berhasil diperbarui!').show();
                                setTimeout(() => $('.main-message').fadeOut(), 3000);
                            } else {
                                $('.main-message').removeClass('alert-success').addClass('alert-danger').text('Gagal memperbarui alamat: ' + response.message).show();
                            }
                        },
                        error: function() {
                            $('.main-message').removeClass('alert-success').addClass('alert-danger').text('Terjadi kesalahan saat berkomunikasi dengan server.').show();
                        }
                    });
                }
            });

            // --- Logika untuk Kupon Diskon ---
            $('#applyCouponBtn').click(function() {
                const couponCode = $('#couponCode').val().trim();
                if (couponCode === '') {
                    $('#couponError').text('Kode kupon tidak boleh kosong.').show();
                    $('#couponSuccess').hide();
                    return;
                }

                // Sembunyikan pesan sebelumnya
                $('#couponError').hide();
                $('#couponSuccess').hide();

                // Lakukan AJAX call untuk memvalidasi kupon
                $.ajax({
                    url: 'apply_coupon.php', // Endpoint untuk validasi kupon
                    method: 'POST',
                    data: {
                        coupon_code: couponCode,
                        total_harga_produk: totalProductPrice // Kirim total harga produk untuk validasi min purchase
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            couponDiscount = response.discount_amount;
                            $('#couponCodeApplied').val(couponCode);
                            $('#couponDiscountAmount').val(couponDiscount);

                            $('#couponCodeDisplay').text(couponCode);
                            $('#summaryCouponDiscount').text(formatRupiah(couponDiscount).replace('Rp', '').trim());
                            $('#couponDiscountRow').show();
                            $('#couponSuccess').text(response.message).show();
                            $('#couponCode').prop('disabled', true); // Nonaktifkan input setelah berhasil diterapkan
                            $('#applyCouponBtn').prop('disabled', true); // Nonaktifkan tombol
                        } else {
                            couponDiscount = 0;
                            $('#couponCodeApplied').val('');
                            $('#couponDiscountAmount').val(0);
                            $('#couponDiscountRow').hide();
                            $('#couponError').text(response.message).show();
                            $('#couponCode').prop('disabled', false); // Aktifkan kembali input
                            $('#applyCouponBtn').prop('disabled', false); // Aktifkan kembali tombol
                        }
                        updateGrandTotal();
                    },
                    error: function() {
                        couponDiscount = 0;
                        $('#couponCodeApplied').val('');
                        $('#couponDiscountAmount').val(0);
                        $('#couponDiscountRow').hide();
                        $('#couponError').text('Terjadi kesalahan saat menerapkan kupon.').show();
                        $('#couponCode').prop('disabled', false); // Aktifkan kembali input
                        $('#applyCouponBtn').prop('disabled', false); // Aktifkan kembali tombol
                        updateGrandTotal();
                    }
                });
            });

            // Handle submit form checkout
            $('#checkoutForm').submit(function(e) {
                let formIsValid = true;
                $('.main-message').hide(); // Sembunyikan pesan sebelumnya

                // Validasi kurir untuk setiap toko
                $('.kurir-select').each(function() {
                    if ($(this).val() === '') {
                        $(this).siblings('.kurir-error').show();
                        formIsValid = false;
                    } else {
                        $(this).siblings('.kurir-error').hide();
                    }
                });

                // Validasi metode pembayaran
                if (selectedPaymentMethodId === '') {
                    $('.payment-method-error').show();
                    formIsValid = false;
                } else {
                    $('.payment-method-error').hide();
                    // Validasi detail bank/ewallet jika diperlukan
                    if ($('#transferDetails').is(':visible') && $('#detailBankName').text() === 'Informasi Bank Tidak Tersedia') {
                         $('.bank-error').show();
                         formIsValid = false;
                    }
                     if ($('#ewalletDetails').is(':visible') && $('#ewalletName').text() === 'Informasi E-Wallet Tidak Tersedia') {
                         $('.ewallet-error').show();
                         formIsValid = false;
                    }
                    if ($('#qrisDetails').is(':visible') && $('.qris-error').is(':visible')) {
                        formIsValid = false;
                    }
                }

                if (!formIsValid) {
                    e.preventDefault(); // Mencegah form submit jika validasi gagal
                    $('.main-message').removeClass('alert-success').addClass('alert-danger').text('Mohon lengkapi semua pilihan yang diperlukan.').show();
                    $('html, body').animate({ scrollTop: 0 }, 'fast'); // Scroll ke atas
                } else {
                    // Jika semua valid, form akan disubmit ke proses_checkout.php
                    // Anda bisa menambahkan spinner atau loading state di sini
                    $('#btnBuatPesanan').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...');
                }
            });
        });
    </script>
</body>
</html>