<?php
session_start();
include('../../koneksi/koneksi.php'); // Adjust path as necessary

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'order_id' => null, 'redirect_url' => null];

// 1. Check if user is logged in (pelanggan)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    $response['message'] = 'Anda harus login sebagai pelanggan untuk melakukan pembelian.';
    $response['redirect_url'] = '../../koneksi/login.php'; // Redirect to login page
    echo json_encode($response);
    exit();
}

$pelanggan_id = $_SESSION['user_id'];

// 2. Get cart items from database (keranjang_customer)
// It's crucial to get cart items from the database here to ensure consistency and prevent tampering
$cart_items_from_db = [];
$stmt_cart = $conn->prepare("SELECT produk_id, variasi_id, quantity FROM keranjang_customer WHERE customer_id = ? ORDER BY tanggal_ditambahkan ASC");
if ($stmt_cart) {
    $stmt_cart->bind_param("i", $pelanggan_id);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result();
    while ($row = $result_cart->fetch_assoc()) {
        $cart_items_from_db[] = $row;
    }
    $stmt_cart->close();
}

if (empty($cart_items_from_db)) {
    $response['message'] = 'Keranjang Anda kosong. Tidak ada yang bisa diproses.';
    echo json_encode($response);
    exit();
}

// 3. Process form data (from checkout page)
$metode_pembelian = $_POST['metode_pembelian'] ?? ''; // 'langsung', 'transfer', 'cod'
$metode_pengiriman = $_POST['metode_pengiriman'] ?? ''; // 'ambil_tempat', 'antar'
$alamat_pengiriman = $_POST['alamat_pengiriman'] ?? null; // Only for 'antar'
$kurir_id = filter_var($_POST['kurir_id'] ?? null, FILTER_VALIDATE_INT); // Only for 'antar'
$catatan_pelanggan = $_POST['catatan_pelanggan'] ?? null;
$selected_product_indices = json_decode($_POST['selected_items_indices'] ?? '[]', true);

// Filter cart_items_from_db based on selected_product_indices if provided
// This assumes selected_product_indices are 0-based indices from the fetched $cart_items_from_db array
$final_items_to_checkout = [];
if (!empty($selected_product_indices)) {
    foreach ($selected_product_indices as $index) {
        if (isset($cart_items_from_db[$index])) {
            $final_items_to_checkout[] = $cart_items_from_db[$index];
        }
    }
} else {
    // If no specific indices are provided, assume all items in the DB cart are being checked out
    $final_items_to_checkout = $cart_items_from_db;
}

if (empty($final_items_to_checkout)) {
    $response['message'] = 'Tidak ada item yang dipilih untuk checkout.';
    echo json_encode($response);
    exit();
}


// Basic validation
if (!in_array($metode_pembelian, ['langsung', 'transfer', 'cod'])) {
    $response['message'] = 'Metode pembelian tidak valid.';
    echo json_encode($response);
    exit();
}
if (!in_array($metode_pengiriman, ['ambil_tempat', 'antar'])) {
    $response['message'] = 'Metode pengiriman tidak valid.';
    echo json_encode($response);
    exit();
}

if ($metode_pengiriman === 'antar') {
    if (empty($alamat_pengiriman)) {
        $response['message'] = 'Alamat pengiriman wajib diisi untuk metode "antar".';
        echo json_encode($response);
        exit();
    }
    // You might also add validation for kurir_id if it's dynamic
    // if (!$kurir_id) {
    //     $response['message'] = 'Kurir harus dipilih untuk metode "antar".';
    //     echo json_encode($response);
    //     exit();
    // }
}


$total_harga_pesanan = 0;
$ongkos_kirim = 0;
$items_for_detail = [];
$produk_stok_updates = []; // To store stock changes for transaction

$conn->begin_transaction(); // Start transaction

try {
    // 4. Validate stock and calculate total price
    foreach ($final_items_to_checkout as $item) {
        $produk_id = $item['produk_id'];
        $variasi_id = $item['variasi_id'] ?? null;
        $quantity = $item['quantity'];

        $stok_tersedia = 0;
        $harga_satuan = 0;
        $nama_produk_saat_beli = '';
        $variasi_detail_saat_beli = '';

        if ($variasi_id !== null) {
            $stmt_prod_var = $conn->prepare("SELECT pv.stok, pv.harga, p.nama, r.nama_rasa, w.nama_warna, u.nama_ukuran
                                            FROM produk_variasi pv
                                            JOIN produk p ON pv.produk_id = p.id
                                            LEFT JOIN rasa r ON pv.rasa_id = r.id
                                            LEFT JOIN warna w ON pv.warna_id = w.id
                                            LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                                            WHERE pv.id = ? AND pv.produk_id = ? FOR UPDATE"); // FOR UPDATE for pessimistic locking
            $stmt_prod_var->bind_param("ii", $variasi_id, $produk_id);
            $stmt_prod_var->execute();
            $result_prod_var = $stmt_prod_var->get_result();
            if ($row = $result_prod_var->fetch_assoc()) {
                $stok_tersedia = $row['stok'];
                $harga_satuan = $row['harga'];
                $nama_produk_saat_beli = $row['nama'];
                $variasi_parts = [];
                if (!empty($row['nama_rasa'])) $variasi_parts[] = "Rasa: " . $row['nama_rasa'];
                if (!empty($row['nama_warna'])) $variasi_parts[] = "Warna: " . $row['nama_warna'];
                if (!empty($row['nama_ukuran'])) $variasi_parts[] = "Ukuran: " . $row['nama_ukuran'];
                $variasi_detail_saat_beli = implode(', ', $variasi_parts);
            } else {
                throw new Exception("Produk atau variasi tidak ditemukan.");
            }
            $stmt_prod_var->close();
        } else {
            $stmt_prod = $conn->prepare("SELECT stok, harga, nama FROM produk WHERE id = ? FOR UPDATE"); // FOR UPDATE
            $stmt_prod->bind_param("i", $produk_id);
            $stmt_prod->execute();
            $result_prod = $stmt_prod->get_result();
            if ($row = $result_prod->fetch_assoc()) {
                $stok_tersedia = $row['stok'];
                $harga_satuan = $row['harga'];
                $nama_produk_saat_beli = $row['nama'];
            } else {
                throw new Exception("Produk tidak ditemukan.");
            }
            $stmt_prod->close();
        }

        if ($quantity > $stok_tersedia) {
            throw new Exception("Stok tidak mencukupi untuk " . $nama_produk_saat_beli . ($variasi_detail_saat_beli ? " (" . $variasi_detail_saat_beli . ")" : "") . ". Stok tersedia: " . $stok_tersedia);
        }

        $items_for_detail[] = [
            'produk_id' => $produk_id,
            'variasi_id' => $variasi_id,
            'quantity' => $quantity,
            'harga_satuan' => $harga_satuan,
            'nama_produk_saat_beli' => $nama_produk_saat_beli,
            'variasi_detail_saat_beli' => $variasi_detail_saat_beli
        ];
        $total_harga_pesanan += ($harga_satuan * $quantity);

        // Prepare stock update
        $produk_stok_updates[] = [
            'type' => ($variasi_id !== null) ? 'variasi' : 'produk',
            'id' => ($variasi_id !== null) ? $variasi_id : $produk_id,
            'new_stok' => $stok_tersedia - $quantity
        ];
    }

    // Calculate shipping cost if 'antar'
    if ($metode_pengiriman === 'antar') {
        // You would typically have a more complex shipping calculation here
        // based on total weight, destination, kurir, etc.
        // For simplicity, let's assume a flat rate or simple per-item rate for now.
        // Or fetch from tarif_kurir_wilayah based on kurir_id and customer's address/region.
        // Example: Assume a fixed shipping cost for now.
        $ongkos_kirim = 15000.00; // Example flat rate

        // If you want to use tarif_kurir_wilayah table:
        /*
        $customer_address_region = "Depok"; // This would come from customer's profile or form input
        $stmt_shipping_rate = $conn->prepare("SELECT tarif_per_kg, tarif_flat FROM tarif_kurir_wilayah WHERE kurir_id = ? AND nama_wilayah = ?");
        if ($stmt_shipping_rate) {
            $stmt_shipping_rate->bind_param("is", $kurir_id, $customer_address_region);
            $stmt_shipping_rate->execute();
            $result_shipping_rate = $stmt_shipping_rate->get_result();
            if ($row_rate = $result_shipping_rate->fetch_assoc()) {
                // Assuming you'd calculate total weight here if needed
                // $total_weight = calculate_total_weight_of_cart($final_items_to_checkout);
                // $ongkos_kirim = ($total_weight * $row_rate['tarif_per_kg']) + $row_rate['tarif_flat'];
                $ongkos_kirim = $row_rate['tarif_flat']; // Simpler example
            }
            $stmt_shipping_rate->close();
        }
        */
    }
    
    $total_harga_pesanan += $ongkos_kirim;


    // 5. Insert into `pesanan` table
    $status_pesanan = ($metode_pembelian === 'transfer' || $metode_pembelian === 'cod') ? 'menunggu_pembayaran' : 'diproses'; // 'langsung' might go straight to 'diproses'

    $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (pelanggan_id, kurir_id, status_pesanan, metode_pembelian, metode_pengiriman, alamat_pengiriman, ongkos_kirim, total_harga, catatan_pelanggan, kode_unik) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Generate a simple unique code for the order
    $kode_unik = 'ORD-' . strtoupper(uniqid());

    // Handle kurir_id potentially being null
    if ($kurir_id === null) {
        $stmt_pesanan->bind_param("iisssssdss", $pelanggan_id, $kurir_id_bind, $status_pesanan, $metode_pembelian, $metode_pengiriman, $alamat_pengiriman, $ongkos_kirim, $total_harga_pesanan, $catatan_pelanggan, $kode_unik);
        $kurir_id_bind = null; // Set value for binding
        $stmt_pesanan->execute(); // Execute with null
    } else {
        $stmt_pesanan->bind_param("iisssssdss", $pelanggan_id, $kurir_id, $status_pesanan, $metode_pembelian, $metode_pengiriman, $alamat_pengiriman, $ongkos_kirim, $total_harga_pesanan, $catatan_pelanggan, $kode_unik);
        $stmt_pesanan->execute();
    }
    
    $pesanan_id = $conn->insert_id;
    $stmt_pesanan->close();

    // 6. Insert into `detail_pesanan` table
    $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (pesanan_id, produk_id, variasi_id, quantity, harga_satuan, nama_produk_saat_beli, variasi_detail_saat_beli) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items_for_detail as $item_detail) {
        $prod_id = $item_detail['produk_id'];
        $var_id = $item_detail['variasi_id'];
        $qty = $item_detail['quantity'];
        $price = $item_detail['harga_satuan'];
        $prod_name_at_buy = $item_detail['nama_produk_saat_beli'];
        $var_detail_at_buy = $item_detail['variasi_detail_saat_beli'];

        if ($var_id === null) {
            $var_id_bind = NULL;
            $stmt_detail->bind_param("iiiddss", $pesanan_id, $prod_id, $var_id_bind, $qty, $price, $prod_name_at_buy, $var_detail_at_buy);
        } else {
            $stmt_detail->bind_param("iiiddss", $pesanan_id, $prod_id, $var_id, $qty, $price, $prod_name_at_buy, $var_detail_at_buy);
        }
        $stmt_detail->execute();
    }
    $stmt_detail->close();

    // 7. Update product/variation stock
    foreach ($produk_stok_updates as $update) {
        if ($update['type'] === 'variasi') {
            $stmt_update_stok = $conn->prepare("UPDATE produk_variasi SET stok = ? WHERE id = ?");
        } else {
            $stmt_update_stok = $conn->prepare("UPDATE produk SET stok = ? WHERE id = ?");
        }
        $stmt_update_stok->bind_param("ii", $update['new_stok'], $update['id']);
        $stmt_update_stok->execute();
        $stmt_update_stok->close();
    }

    // 8. Clear processed items from `keranjang_customer` table
    // Build a complex WHERE clause to delete only the items that were included in final_items_to_checkout
    $delete_where_clauses = [];
    $delete_params = [];
    $delete_types = '';

    foreach ($final_items_to_checkout as $item_to_delete) {
        $produk_id_del = $item_to_delete['produk_id'];
        $variasi_id_del = $item_to_delete['variasi_id'] ?? null;

        if ($variasi_id_del !== null) {
            $delete_where_clauses[] = "(produk_id = ? AND variasi_id = ?)";
            $delete_params[] = $produk_id_del;
            $delete_params[] = $variasi_id_del;
            $delete_types .= 'ii';
        } else {
            $delete_where_clauses[] = "(produk_id = ? AND variasi_id IS NULL)";
            $delete_params[] = $produk_id_del;
            $delete_types .= 'i';
        }
    }

    if (!empty($delete_where_clauses)) {
        $sql_delete_cart = "DELETE FROM keranjang_customer WHERE customer_id = ? AND (" . implode(' OR ', $delete_where_clauses) . ")";
        $stmt_delete_cart = $conn->prepare($sql_delete_cart);
        if ($stmt_delete_cart) {
            $delete_params_full = array_merge([$pelanggan_id], $delete_params);
            $delete_types_full = 'i' . $delete_types;
            $stmt_delete_cart->bind_param($delete_types_full, ...$delete_params_full);
            $stmt_delete_cart->execute();
            $stmt_delete_cart->close();
        }
    }

    // Commit the transaction
    $conn->commit();

    // Clear relevant items from session cart as well (if any remain)
    // This part is a bit trickier with direct removal based on DB items.
    // A simpler approach is to rebuild the session cart from the remaining DB cart items.
    // However, if the session cart was primarily handled by sync_cart_to_db, it should be mostly empty.
    // For now, we assume the DB is the source of truth, and session would be updated on next fetch.

    $response['success'] = true;
    $response['message'] = 'Pesanan berhasil dibuat!';
    $response['order_id'] = $pesanan_id;
    // Redirect to order confirmation page or detail page
    $response['redirect_url'] = '../order/detail.php?id=' . $pesanan_id;

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $response['message'] = 'Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
exit();
?>