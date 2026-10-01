<?php
session_start(); // Pastikan session dimulai untuk mengakses $_SESSION

// Sesuaikan path ini jika perlu. Menggunakan path dari checkout.php yang lebih dalam.
include '../../koneksi/koneksi.php';

// Cek login, prioritas utama untuk kedua fungsionalitas
if (!isset($_SESSION['pengguna_id'])) {
    $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
    header('Location: ../../login.php'); // Sesuaikan path ke halaman login Anda
    exit();
}

$pengguna_id = $_SESSION['pengguna_id']; // Menggunakan $pengguna_id sebagai variabel umum

// Inisialisasi variabel profil pelanggan
$nama_pelanggan = "Akun"; // Default
$foto_pelanggan = "";     // Default

// --- Logika untuk mengambil nama dan foto profil pengguna (prioritas: pelanggan, fallback: pengguna) ---
// 1. Coba ambil nama dan foto dari tabel 'pelanggan'
$sql_profil_pelanggan = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
$stmt_profil_pelanggan = $conn->prepare($sql_profil_pelanggan);
if ($stmt_profil_pelanggan) {
    $stmt_profil_pelanggan->bind_param("i", $pengguna_id);
    $stmt_profil_pelanggan->execute();
    $result_profil_pelanggan = $stmt_profil_pelanggan->get_result();

    if ($result_profil_pelanggan->num_rows === 1) {
        $data_profil = $result_profil_pelanggan->fetch_assoc();
        $nama_pelanggan = htmlspecialchars($data_profil['nama']);
        $foto_pelanggan = htmlspecialchars($data_profil['foto']);
    }
    $stmt_profil_pelanggan->close();
} else {
    error_log("Error preparing pelanggan profile query: " . $conn->error);
}

// 2. Jika nama_pelanggan masih default atau kosong (berarti tidak ditemukan di pelanggan),
//    atau jika foto_pelanggan masih kosong (meskipun nama ditemukan, tapi foto tidak ada),
//    coba ambil nama dan foto dari tabel 'pengguna' sebagai fallback.
if (($nama_pelanggan === "Akun" || empty($nama_pelanggan)) || empty($foto_pelanggan)) {
    $sql_profil_pengguna = "SELECT nama, foto FROM pengguna WHERE id = ?";
    $stmt_profil_pengguna = $conn->prepare($sql_profil_pengguna);
    if ($stmt_profil_pengguna) {
        $stmt_profil_pengguna->bind_param("i", $pengguna_id);
        $stmt_profil_pengguna->execute();
        $result_profil_pengguna = $stmt_profil_pengguna->get_result();

        if ($result_profil_pengguna->num_rows === 1) {
            $data_profil_pengguna = $result_profil_pengguna->fetch_assoc();
            // Hanya update nama jika sebelumnya tidak ditemukan di pelanggan
            if ($nama_pelanggan === "Akun" || empty($nama_pelanggan)) {
                $nama_pelanggan = htmlspecialchars($data_profil_pengguna['nama']);
            }
            // Update foto hanya jika sebelumnya kosong
            if (empty($foto_pelanggan)) {
                $foto_pelanggan = htmlspecialchars($data_profil_pengguna['foto']);
            }
        }
        $stmt_profil_pengguna->close();
    } else {
        error_log("Error preparing pengguna profile query: " . $conn->error);
    }
}
// --- Akhir Logika Profil Pengguna ---

// Mengambil jumlah item di keranjang untuk badge navbar
$total_produk_di_keranjang = 0;
if ($pengguna_id) {
    $query_cart_count = "SELECT SUM(quantity) AS total_qty FROM keranjang_customer WHERE customer_id = ?";
    $stmt_cart_count = $conn->prepare($query_cart_count);
    if ($stmt_cart_count) {
        $stmt_cart_count->bind_param("i", $pengguna_id);
        $stmt_cart_count->execute();
        $result_cart_count = $stmt_cart_count->get_result();
        if ($row_cart_count = $result_cart_count->fetch_assoc()) {
            $total_produk_di_keranjang = $row_cart_count['total_qty'] ?? 0;
        }
        $stmt_cart_count->close();
    }
}

// Mengambil jumlah item di wishlist untuk badge navbar
$total_item_wishlist = 0;
if ($pengguna_id) {
    $query_wishlist_count = "SELECT COUNT(id) AS total_wishlist_items FROM wishlist WHERE pelanggan_id = ?";
    $stmt_wishlist_count = $conn->prepare($query_wishlist_count);
    if ($stmt_wishlist_count) {
        $stmt_wishlist_count->bind_param("i", $pengguna_id);
        $stmt_wishlist_count->execute();
        $result_wishlist_count = $stmt_wishlist_count->get_result();
        if ($row_wishlist_count = $result_wishlist_count->fetch_assoc()) {
            $total_item_wishlist = $row_wishlist_count['total_wishlist_items'];
        }
        $stmt_wishlist_count->close();
    }
}
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
    /* General Body Styling */
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        font-family: 'Arial', sans-serif;
        background-color: #f8f9fa;
        color: #333;
    }

    /* Navbar Styling */
    .navbar {
        background-color: #FF4500 !important; /* Primary color */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
        font-weight: bold;
        display: flex;
        align-items: center;
    }

    .navbar-brand img {
        margin-right: 8px;
        max-width: 30px;
    }

    .nav-link {
        color: white !important; /* Set solid white color for nav-links */
        transition: color 0.3s ease;
    }

    .nav-link:hover,
    .nav-link.active {
        color: #f0f0f0 !important; /* Slightly lighter on hover/active */
        font-weight: bold;
    }

    .navbar-toggler {
        border-color: rgba(255, 255, 255, 0.1);
    }

    .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
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
    }

    /* Navbar badge styles (specific to wishlist/cart count) */
    .navbar-nav .nav-link .badge {
        background-color: white !important; /* Set background to white */
        color: #ff4500 !important;          /* Set text color to #ff4500 (OrangeRed) */
        border: 1px solid #ff4500;          /* Add a subtle border matching the text color */
    }

    /* Making the heart icon in the navbar solid white */
    .navbar-nav .nav-item .nav-link .bi-heart-fill,
    .navbar-nav .nav-item .nav-link .bi-heart {
        color: white !important; /* Ensure always white */
    }

    .navbar-nav .dropdown-menu {
        background-color: #FF4500; /* Consistent with navbar color */
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    }

    .navbar-nav .dropdown-item {
        color: rgba(255, 255, 255, 0.8);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .navbar-nav .dropdown-item:hover {
        background-color: #E63C00; /* Darker orange on hover */
        color: white;
    }

    .navbar-nav .dropdown-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
    }

    /* Profile Dropdown Specific */
    .navbar-nav .dropdown-toggle .ms-1 {
        color: white !important; /* Ensure profile name is white */
    }

    /* Wishlist Page Specific Styles */
    .wishlist-item {
        display: flex;
        align-items: center;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 15px;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        padding: 15px;
        cursor: pointer;
        transition: transform 0.2s ease-in-out;
    }

    .wishlist-item:hover {
        transform: translateY(-3px);
    }

    .wishlist-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 5px;
        margin-right: 15px;
        border: 1px solid #eee;
        flex-shrink: 0;
    }

    .wishlist-item-details {
        flex-grow: 1;
    }

    .wishlist-item-details h5 {
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }

    .wishlist-item-details .price {
        font-weight: bold;
        color: #ff4500;
        font-size: 1.1em;
        margin-bottom: 5px;
    }

    .wishlist-item-details .store {
        font-size: 0.9em;
        color: #666;
        margin-bottom: 5px;
    }

    .wishlist-item-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        flex-shrink: 0;
    }

    .btn-view-product, .btn-remove-wishlist {
        padding: 8px 15px;
        font-size: 0.9em;
    }

    .btn-view-product {
        background-color: #FF4500;
        border-color: #FF4500;
        color: white;
    }
    .btn-view-product:hover {
        background-color: #E63C00;
        border-color: #E63C00;
        color: white;
    }

    .btn-remove-wishlist {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    .btn-remove-wishlist:hover {
        background-color: #c82333;
        border-color: #c82333;
        color: white;
    }

    /* Empty Wishlist Styling */
    .text-center.py-5 {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        padding: 40px !important;
    }

    .text-center.py-5 img {
        filter: grayscale(80%);
        opacity: 0.6;
    }

    .text-center.py-5 .btn-primary {
        background-color: #FF4500;
        border-color: #FF4500;
    }
    .text-center.py-5 .btn-primary:hover {
        background-color: #E63C00;
        border-color: #E63C00;
    }

    /* Footer Styling */
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

    /* Specific social media icon colors - Keep these as they are specific brand colors */
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

    /* Responsive adjustments for general layout */
    @media (max-width: 767.98px) {
        .wishlist-item {
            flex-wrap: wrap;
            text-align: center;
            justify-content: center;
        }
        .wishlist-item img {
            margin-right: 0;
            margin-bottom: 15px;
        }
        .wishlist-item-details {
            text-align: center;
            margin-bottom: 15px;
        }
        .wishlist-item-actions {
            flex-direction: row;
            width: 100%;
            justify-content: center;
        }
    }

    /* Product Image Styling (assuming checkout page or similar) */
    .product-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
    }

    /* Payment Methods Container */
    #paymentMethodsContainer {
        width: 100%; /* Ensure container fills full width */
        /* d-flex flex-wrap justify-content-start gap-3 classes are already in HTML */
    }

    /* Payment Method Item Styling (OVERRIDDEN & CONSOLIDATED) */
    .payment-method-item {
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        border: 1px solid #dee2e6; /* Default border */
        border-radius: 0.25rem; /* Rounded corners */
        background-color: #fff;
        text-align: center;
        flex-grow: 1;   /* Allow item to grow */
        flex-shrink: 1; /* Allow item to shrink */

        /* Base width. calc(100% / N - gap) */
        /* For 5 items per row on large screens */
        flex-basis: calc(20% - 1.5rem); /* 100% / 5 = 20%. Subtract gap-3 (1.5rem) */

        min-width: 100px; /* Minimum width for the item */
        max-width: 150px; /* Maximum width so they don't get too large */

        height: 100px; /* Uniform height for all cards */
        /* display: flex; flex-direction: column; align-items: center; justify-content: center;
           These are already in the HTML class, so no need here unless for override */
    }

    /* Hover state styling */
    .payment-method-item:hover {
        background-color: #f0f0f0;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Selected (active) state styling */
    /* Using .selected-payment-method as per JS */
    .payment-method-item.selected-payment-method {
        border-color: #FF4500 !important; /* Orangered border when selected */
        box-shadow: 0 0 0 0.25rem rgba(255, 69, 0, 0.25); /* Orangered glow effect */
        background-color: #fff3e6; /* Slightly lighter/orange background when selected */
    }

    .payment-method-item .payment-logo {
        max-height: 40px; /* Max height for logo */
        width: auto;     /* Auto width to maintain aspect ratio */
        object-fit: contain; /* Ensure logo is not distorted */
        margin-bottom: 5px; /* Space between logo and text */
    }

    .payment-method-item label {
        font-size: 0.85em; /* Smaller font size to fit */
        line-height: 1.2;  /* Adjust line height */
        font-weight: 500;
        margin-top: 0;     /* Remove default Bootstrap label margin-top */
        padding: 0 5px;    /* Slight horizontal padding if text is long */
    }

    /* Summary Card Styling */
    .summary-card .list-group-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Custom Orangered Buttons and Text */
    .btn-custom-orangered {
        background-color: #FF4500;
        border-color: #FF4500;
        color: #fff; /* White text for contrast */
    }

    /* Hover and active states for btn-custom-orangered */
    .btn-custom-orangered:hover {
        background-color: #E03D00; /* Slightly darker than #FF4500 */
        border-color: #E03D00;
        color: #fff;
    }

    .btn-custom-orangered:active {
        background-color: #CC3700; /* Even darker when clicked */
        border-color: #CC3700;
        color: #fff;
    }

    .btn-outline-custom-orangered {
        color: #FF4500;
        border-color: #FF4500;
        background-color: transparent;
    }

    .btn-outline-custom-orangered:hover {
        background-color: #FF4500;
        color: #fff;
    }

    /* Active state for btn-outline-custom-orangered */
    .btn-outline-custom-orangered:active {
        background-color: #E03D00; /* Same as hover or slightly darker */
        border-color: #E03D00;
        color: #fff;
    }

    .text-custom-orangered {
        color: #FF4500 !important; /* !important to ensure Bootstrap override */
    }

    .alert-custom-orangered {
        background-color: #ffe6cc; /* Lighter background color */
        border-color: #FF4500;
        color: #333; /* Dark text color */
    }

    /* Responsive Adjustments for Payment Methods */
    @media (max-width: 991.98px) { /* Medium devices (tablets, 768px and up) */
        .payment-method-item {
            flex-basis: calc(25% - 1.5rem); /* 4 items per row */
            height: 95px;
        }
    }

    @media (max-width: 767.98px) { /* Small devices (landscape phones, 576px and up) */
        .payment-method-item {
            flex-basis: calc(33.33% - 1.5rem); /* 3 items per row */
            height: 90px;
            min-width: unset; /* Remove min-width on small screens */
        }
        .payment-method-item .payment-logo {
            max-height: 35px;
        }
        .payment-method-item label {
            font-size: 0.8em;
        }
    }

    @media (max-width: 575.98px) { /* Extra small devices (portrait phones, less than 576px) */
        .payment-method-item {
            flex-basis: calc(50% - 1.5rem); /* 2 items per row */
            height: 85px;
        }
        .payment-method-item .payment-logo {
            max-height: 30px;
        }
        .payment-method-item label {
            font-size: 0.75em;
        }
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
                    <a class="nav-link " href="../produk.php">Produk</a>
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
                        <span class="badge bg-light text-danger rounded-pill" id="wishlist-badge">
                            <?php echo $total_item_wishlist; ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="position-relative">
                        <a class="nav-link active" href="keranjang.php" id="link-keranjang">
                            <i class="bi bi-cart-fill"></i>
                            <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                <?php echo $total_produk_di_keranjang ?? '0'; ?>
                            </span>
                        </a>
                        </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (isset($foto_pelanggan) && $foto_pelanggan): ?>
                                <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan); ?>" alt="Foto Profil" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <span class="ms-1"><?php echo htmlspecialchars($nama_pelanggan ?? 'Tamu'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="../profil/profil.php">Profil</a></li>
                        <li><a class="dropdown-item" href="pesanan_saya.php">Pesanan Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
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
    <div class="alert alert-custom-orangered main-message" role="alert" style="display:none;"></div>

    <form id="checkoutForm" action="../checkout/process_checkout.php" method="POST">
        <?php if ($is_direct_buy): ?>
            <input type="hidden" name="direct_produk_id" value="<?php echo $direct_produk_id; ?>">
            <input type="hidden" name="direct_quantity" value="<?php echo $direct_quantity; ?>">
            <input type="hidden" name="direct_variasi_id" value="<?php echo $direct_variasi_id; ?>">
        <?php else: ?>
            <input type="hidden" name="selected_cart_ids" value="<?php echo implode(',', $selected_cart_ids); ?>">
        <?php endif; ?>
        <input type="hidden" name="is_direct_buy" value="<?php echo $is_direct_buy ? '1' : '0'; ?>">

        <input type="hidden" name="coupon_code" id="couponCodeApplied" value="">
        <input type="hidden" name="discount_amount" id="couponDiscountAmount" value="0">

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
                        <button type="button" class="btn btn-sm btn-outline-custom-orangered" data-bs-toggle="modal" data-bs-target="#editAddressModal">
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
                        <div id="paymentMethodsContainer" class="d-flex flex-wrap justify-content-start gap-3">
                            <?php if (!empty($available_payment_methods)): ?>
                                <?php foreach ($available_payment_methods as $method): ?>
                                    <div class="payment-method-item d-flex flex-column align-items-center justify-content-center p-2 border rounded"
                                        data-method-id="<?php echo htmlspecialchars($method['id']); ?>"
                                        data-method-code="<?php echo htmlspecialchars($method['kode_metode']); ?>"
                                        data-method-type="<?php echo htmlspecialchars($method['tipe_pembayaran']); ?>">
                                        <input type="radio" name="metode_pembayaran"
                                            id="metode_pembayaran<?php echo htmlspecialchars($method['id']); ?>"
                                            value="<?php echo htmlspecialchars($method['id']); ?>"
                                            class="form-check-input mb-1 d-none">
                                        <?php if (!empty($method['logo_metode'])): ?>
                                            <img src="../../img/metode_pembayaran/<?php echo htmlspecialchars($method['logo_metode']); ?>"
                                                alt="<?php echo htmlspecialchars($method['nama_metode']); ?>"
                                                class="img-fluid mb-1 payment-logo"> <?php endif; ?>
                                        <label for="metode_pembayaran<?php echo htmlspecialchars($method['id']); ?>" class="form-check-label text-center flex-grow-1 d-flex align-items-center justify-content-center">
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
                            <small class="text-custom-orangered">Pastikan Anda mentransfer ke rekening yang benar.</small>
                            <div class="alert alert-danger bank-error mt-2" style="display:none;">Detail bank tidak ditemukan atau tidak lengkap untuk toko ini.</div>
                        </div>

                        <div id="ewalletDetails" class="mt-3 card card-body bg-light" style="display: none;">
                            <h6>Detail E-Wallet:</h6>
                            <p><span id="ewalletName" class="fw-bold"></span>: <span id="ewalletNumber" class="fw-bold"></span></p>
                            <small class="text-custom-orangered">Scan QR code atau transfer ke nomor di atas.</small>
                            <div class="alert alert-danger ewallet-error mt-2" style="display:none;">Detail E-Wallet tidak ditemukan atau tidak lengkap untuk toko ini.</div>
                        </div>
                        <div id="qrisDetails" class="mt-3 card card-body bg-light text-center" style="display: none;">
                            <h6>Pembayaran QRIS:</h6>
                            <img id="qrisImage" src="../../img/metode_pembayaran/qris.png" alt="QRIS Code" class="img-fluid mb-2" style="max-width: 200px;">
                            <p class="text-custom-orangered">Scan QR code di atas menggunakan aplikasi pembayaran Anda.</p>
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
                                <button class="btn btn-outline-custom-orangered" type="button" id="applyCouponBtn">Terapkan</button>
                            </div>
                            <small class="text-danger mt-1" id="couponError" style="display:none;"></small>
                            <small class="text-success mt-1" id="couponSuccess" style="display:none;"></small>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-lg btn-custom-orangered" id="btnBuatPesanan">
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
                <button type="button" class="btn btn-custom-orangered" id="saveAddressBtn">Simpan Perubahan</button>
            </div>
        </div>
    </div>
</div>
        <footer class="footer py-4 text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                    <img src="../../img/logo.png" alt="Logo Desa" width="80" class="mb-2">
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
        $('.kurir-select').change(function() {
            const storeId = $(this).data('store-id');
            const selectedCourierId = $(this).val();
            const totalBerat = parseFloat($(this).data('total-berat')); // totalBerat dalam kg
            let ongkir = 0;
            
            selectedCourierPerStore[storeId] = selectedCourierId;
            
            // --- START: Ini adalah bagian yang perlu diintegrasikan dengan API RajaOngkir atau kurir lainnya ---
            if (selectedCourierId) {
                if (totalBerat > 0) {
                    ongkir = Math.ceil(totalBerat) * 3000; // Pembulatan ke atas untuk berat dan dikalikan Rp 10.000
                } else {
                    ongkir = 0; // Jika tidak ada berat, ongkir tetap 0
                }
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
            // Hapus class 'selected-payment-method' dari semua item
            $('.payment-method-item').removeClass('selected-payment-method');
            // Tambahkan class 'selected-payment-method' ke item yang diklik
            $(this).addClass('selected-payment-method');

            // Pilih radio button yang sesuai di dalam item yang diklik
            const $radioButton = $(this).find('input[type="radio"]');
            $radioButton.prop('checked', true);

            selectedPaymentMethodId = $(this).data('method-id');
            selectedPaymentMethodCode = $(this).data('method-code');
            const methodType = $(this).data('method-type');

            $('.payment-method-error').hide(); // Sembunyikan error metode pembayaran
            $('.bank-error, .ewallet-error, .qris-error').hide(); // Sembunyikan error detail pembayaran

            // Sembunyikan semua detail pembayaran terlebih dahulu
            $('#transferDetails').hide();
            $('#ewalletDetails').hide();
            $('#qrisDetails').hide();

            // Hapus semua konten dinamis dari div detail pembayaran sebelum menambahkan yang baru
            $('#transferDetails').empty();
            $('#ewalletDetails').empty();
            $('#qrisDetails').empty();


            // Dapatkan ID penjual pertama dari item yang dicheckout.
            // Asumsi ini adalah pembayaran ke platform atau satu penjual.
            const firstSellerId = Object.keys(paymentDetailsBySeller)[0];
            let methodDetails = null;

            if (firstSellerId && paymentDetailsBySeller[firstSellerId] && paymentDetailsBySeller[firstSellerId][selectedPaymentMethodCode]) {
                methodDetails = paymentDetailsBySeller[firstSellerId][selectedPaymentMethodCode];
            } else {
                // Fallback jika tidak ada detail pembayaran spesifik per penjual untuk metode ini
                methodDetails = null; // Tandai sebagai tidak ditemukan
            }

            // Tampilkan detail sesuai tipe pembayaran
            if (methodType === 'bank_transfer') {
                $('#transferDetails').show();
                // Tambahkan judul "Detail Transfer Bank:"
                $('#transferDetails').append('<h5 class="fw-bold mb-3">Detail Transfer Bank:</h5>');
                
                if (methodDetails && methodDetails.nama_bank && methodDetails.nomor_rekening && methodDetails.nama_pemilik) {
                    $('#transferDetails').append(`<p>Nama Bank: <span class="fw-bold" id="detailBankName">${methodDetails.nama_bank}</span></p>`);
                    $('#transferDetails').append(`<p>Nomor Rekening: <span class="fw-bold" id="detailRekeningNumber">${methodDetails.nomor_rekening}</span></p>`);
                    $('#transferDetails').append(`<p>Atas Nama: <span class="fw-bold" id="detailAccountName">${methodDetails.nama_pemilik}</span></p>`);
                    // Pesan instruksi dengan warna yang sama dengan e-wallet (text-custom-orangered)
                    $('#transferDetails').append('<p id="transferInstruction" class="text-custom-orangered mt-2">Pastikan Anda mentransfer ke rekening yang benar.</p>');
                    $('.bank-error').hide();
                } else {
                    $('#transferDetails').append('<p>Nama Bank: <span class="fw-bold">Informasi Bank Tidak Tersedia</span></p>');
                    $('#transferDetails').append('<p>Nomor Rekening: <span class="fw-bold">N/A</span></p>');
                    $('#transferDetails').append('<p>Atas Nama: <span class="fw-bold">N/A</span></p>');
                    // Pesan error merah
                    $('#transferDetails').append('<div id="transferErrorInstruction" class="alert alert-danger mt-2" role="alert">Detail transfer bank tidak ditemukan atau tidak lengkap untuk toko ini.</div>');
                    $('.bank-error').show();
                }
            } else if (methodType === 'e_wallet') {
                $('#ewalletDetails').show();
                // Tambahkan judul "Detail E-Wallet:"
                $('#ewalletDetails').append('<h5 class="fw-bold mb-3">Detail E-Wallet:</h5>');
                
                if (methodDetails && methodDetails.nama_metode && methodDetails.nomor_rekening) {
                    // Jika detail E-Wallet lengkap
                    $('#ewalletDetails').append(`<p>Metode E-Wallet: <span class="fw-bold" id="ewalletName">${methodDetails.nama_metode}</span></p>`);
                    $('#ewalletDetails').append(`<p>Nomor: <span class="fw-bold" id="ewalletNumber">${methodDetails.nomor_rekening}</span></p>`);

                    // Tambahkan nama pemilik akun e-wallet (jika ada)
                    if (methodDetails.nama_pemilik) {
                        $('#ewalletDetails').append(`<p>Atas Nama: <span class="fw-bold">${methodDetails.nama_pemilik}</span></p>`);
                    }
                    
                    // Pesan instruksi E-Wallet dengan warna merah (text-custom-orangered)
                    $('#ewalletDetails').append('<small class="text-custom-orangered mt-2">Scan QR code atau transfer ke nomor di atas.</small>');

                    $('.ewallet-error').hide(); // Sembunyikan error karena detail lengkap
                } else {
                    // Jika detail E-Wallet tidak lengkap atau tidak ditemukan
                    $('#ewalletDetails').append('<p>Metode E-Wallet: <span class="fw-bold">Informasi E-Wallet Tidak Tersedia</span></p>');
                    $('#ewalletDetails').append('<p>Nomor: <span class="fw-bold">N/A</span></p>');
                    // Tampilkan pesan error seperti pada gambar image_7edfd6.png
                    $('#ewalletDetails').append('<div id="ewalletErrorInstruction" class="alert alert-danger mt-2" role="alert">Detail E-Wallet tidak ditemukan atau tidak lengkap untuk toko ini.</div>');
                    $('.ewallet-error').show(); // Tampilkan error
                }
            } else if (methodType === 'qris') {
                $('#qrisDetails').show();
                // Untuk QRIS, biasanya tidak perlu judul "Detail QRIS:" karena gambar QR sudah cukup jelas.
                // Jika ingin ditambahkan, bisa di sini juga: $('#qrisDetails').append('<h5 class="fw-bold mb-3">Detail QRIS:</h5>');
                
                if (methodDetails && methodDetails.qr_code_image) {
                     $('#qrisDetails').append(`<img id="qrisImage" src="../../img/metode_pembayaran/${methodDetails.qr_code_image}" alt="QR Code" class="img-fluid mb-2" style="max-width: 200px;">`);
                     // Pesan instruksi QRIS dengan warna oranye/kuning cerah (text-warning)
                     // Diasumsikan Anda ingin ini tetap kuning/oranye seperti di gambar image_737769.png
                     $('#qrisDetails').append('<p id="qrisInstruction" class="text-warning mt-2">Scan QR code di atas untuk melakukan pembayaran.</p>');
                     $('.qris-error').hide();
                } else {
                     $('#qrisDetails').append('<img id="qrisImage" src="../../img/metode_pembayaran/qris.png" alt="QR Code Default" class="img-fluid mb-2" style="max-width: 200px;">'); // Fallback default QRIS
                     // Menambahkan pesan error di bawah QR code default, warnanya merah
                     $('#qrisDetails').append('<div id="qrisErrorInstruction" class="alert alert-danger mt-2" role="alert">Detail QRIS tidak ditemukan atau tidak lengkap untuk toko ini.</div>');
                     $('.qris-error').show();
                }
            }
        });

        // Inisialisasi saat halaman dimuat: pastikan metode pembayaran yang sudah terpilih (jika ada dari PHP)
        // memiliki kelas 'selected-payment-method'
        const initialSelectedMethodId = $('input[name="metode_pembayaran"]:checked').val();
        if (initialSelectedMethodId) {
            $(`.payment-method-item[data-method-id="${initialSelectedMethodId}"]`).addClass('selected-payment-method');
            // Trigger the click event on the selected item to show its details
            $(`.payment-method-item[data-method-id="${initialSelectedMethodId}"]`).trigger('click');
        }


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
                // Validasi detail bank/ewallet/qris jika diperlukan
                // Cek apakah ada pesan error alert-danger yang aktif
                if ($('#transferDetails').is(':visible') && $('#transferDetails').find('.alert-danger').length > 0) {
                    $('.bank-error').show();
                    formIsValid = false;
                }
                if ($('#ewalletDetails').is(':visible') && $('#ewalletDetails').find('.alert-danger').length > 0) {
                    $('.ewallet-error').show();
                    formIsValid = false;
                }
                if ($('#qrisDetails').is(':visible') && $('#qrisDetails').find('.alert-danger').length > 0) {
                    $('.qris-error').show();
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