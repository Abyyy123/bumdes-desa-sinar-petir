<?php
session_start();
// Pastikan path ke koneksi.php sudah benar sesuai struktur folder Anda
include '../../koneksi/koneksi.php';

// Pastikan pengguna sudah login. Jika belum, arahkan ke halaman login.
$pengguna_id = $_SESSION['pengguna_id'] ?? null;

if (!$pengguna_id) {
    // Menggunakan alert dan JavaScript untuk redirect agar lebih informatif
    echo "<script>alert('Anda harus login untuk mengakses halaman ini.'); window.location.href='../../login.php';</script>";
    exit();
}

// Definisikan base path untuk direktori gambar
$base_image_directory = '../../img/foto/';
$default_profile_image = 'default_profile.png'; // Hanya nama file default

// Array untuk mapping bulan ke nama bulan dalam Bahasa Indonesia
$indonesian_months = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];


// --- LOGIKA UNTUK INFORMASI NAVBAR (Disamakan dengan kategori.php) ---
$nama_pelanggan_navbar = null;
$foto_pelanggan_navbar = null;
if ($pengguna_id) {
    $query_user_info = "SELECT nama, foto FROM pelanggan WHERE pengguna_id = ?";
    $stmt_user_info = $conn->prepare($query_user_info);
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $pengguna_id);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($user_info = $result_user_info->fetch_assoc()) {
            $nama_pelanggan_navbar = $user_info['nama'];
            // Jika foto kosong atau tidak ada, gunakan default dari path yang baru
            // Jika ada foto, bangun path lengkap untuk cek file_exists dan tampilan
            $temp_foto_path_navbar = '../../img/foto/' . $user_info['foto']; // Adjusted path for display
            $foto_pelanggan_navbar = (!empty($user_info['foto']) && file_exists($temp_foto_path_navbar)) ? $user_info['foto'] : $default_profile_image;
        }
        $stmt_user_info->close();
    }
}

// Ambil jumlah item di keranjang untuk badge navbar
$total_produk_di_keranjang = 0; // Mengikuti nama variabel dari kategori.php
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

// Ambil jumlah item di wishlist untuk badge navbar
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
// --- AKHIR LOGIKA UNTUK INFORMASI NAVBAR ---


// --- LOGIKA UNTUK MENAMPILKAN PROFIL PELANGGAN (DASHBOARD UTAMA) ---
$customer_data = null;
// Mengambil data dari tabel 'pelanggan', 'pengguna', dan 'anggota'
$query_customer_profile = "
    SELECT
        pel.nama,
        pel.username,
        pel.email,
        pel.foto,
        pel.alamat,
        pel.nomor_telepon,
        pel.jenis_kelamin,
        pel.tanggal_bergabung,
        pel.nomor_anggota,
        pel.created_at,
        pel.tanggal_lahir,
        p.nama AS nama_from_pengguna,
        p.username AS username_from_pengguna,
        p.email AS email_from_pengguna,
        ang.nama AS nama_from_anggota,
        ang.alamat AS alamat_from_anggota
    FROM
        pelanggan pel
    LEFT JOIN
        pengguna p ON pel.pengguna_id = p.id
    LEFT JOIN
        anggota ang ON pel.nomor_anggota = ang.nomor_anggota
    WHERE
        pel.pengguna_id = ?
";

$stmt_customer_profile = $conn->prepare($query_customer_profile);
if ($stmt_customer_profile) {
    $stmt_customer_profile->bind_param("i", $pengguna_id);
    $stmt_customer_profile->execute();
    $result_customer_profile = $stmt_customer_profile->get_result();
    if ($result_customer_profile->num_rows > 0) {
        $customer_data = $result_customer_profile->fetch_assoc();
        
        // Simpan nama file foto asli dari DB untuk digunakan dalam logika update nanti
        $current_foto_filename_from_db = $customer_data['foto'];

        // Fallback untuk foto profil jika kosong atau tidak ada file, menggunakan path yang baru
        // Bangun path lengkap untuk cek file_exists dan tampilan
        $temp_full_foto_path = $base_image_directory . $customer_data['foto'];
        if (empty($customer_data['foto']) || !file_exists($temp_full_foto_path)) {
            $customer_data['foto'] = $base_image_directory . $default_profile_image;
        } else {
            // Jika file foto ada, gunakan path lengkap ini untuk src gambar
            $customer_data['foto'] = $temp_full_foto_path;
        }
    } else {
        // Jika data pelanggan tidak ditemukan (meskipun ID pengguna ada di sesi)
        echo "<script>alert('Data profil pelanggan tidak ditemukan. Silakan login kembali.'); window.location.href='../auth/logout.php';</script>";
        exit();
    }
    $stmt_customer_profile->close();
} else {
    // Log error jika terjadi masalah pada persiapan query
    error_log("Error preparing customer profile query: " . $conn->error);
    echo "<script>alert('Terjadi kesalahan saat memuat profil Anda.'); window.location.href='../index.php';</script>";
    exit();
}

// --- LOGIKA UNTUK UPDATE PROFIL PELANGGAN ---
$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Ambil data dari form
    $new_nama = trim($_POST['nama'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
    $new_alamat = trim($_POST['alamat'] ?? '');
    $new_jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $new_tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $new_password = $_POST['password'] ?? ''; // Kata sandi baru (opsional)

    // Validasi input dasar
    if (empty($new_nama) || empty($new_username) || empty($new_email)) {
        $message = "Nama, Username, dan Email tidak boleh kosong.";
        $message_type = "error";
    } else {
        // Memulai transaksi database untuk memastikan atomisitas update
        $conn->begin_transaction();
        $all_updates_successful = true; // Flag untuk melacak keberhasilan semua update

        // --- UPDATE TABEL 'pelanggan' ---
        $update_fields_pelanggan = [];
        $update_params_pelanggan = [];
        $update_types_pelanggan = "";

        // Update nama di tabel pelanggan
        $update_fields_pelanggan[] = "nama = ?";
        $update_params_pelanggan[] = $new_nama;
        $update_types_pelanggan .= "s";

        // Tambahkan nomor telepon, alamat, jenis kelamin ke daftar update
        $update_fields_pelanggan[] = "nomor_telepon = ?";
        $update_params_pelanggan[] = $new_nomor_telepon;
        $update_types_pelanggan .= "s";

        // Update alamat di tabel pelanggan
        $update_fields_pelanggan[] = "alamat = ?";
        $update_params_pelanggan[] = $new_alamat;
        $update_types_pelanggan .= "s";

        if (!empty($new_jenis_kelamin)) {
            $update_fields_pelanggan[] = "jenis_kelamin = ?";
            $update_params_pelanggan[] = $new_jenis_kelamin;
            $update_types_pelanggan .= "s";
        }

        // Update tanggal_lahir di tabel pelanggan
        if (!empty($new_tanggal_lahir)) {
            $update_fields_pelanggan[] = "tanggal_lahir = ?";
            $update_params_pelanggan[] = $new_tanggal_lahir;
            $update_types_pelanggan .= "s";
        } else {
            // Jika tanggal lahir dikosongkan, set ke NULL
            $update_fields_pelanggan[] = "tanggal_lahir = NULL";
        }


        // Cek keunikan username di tabel 'pelanggan' (jika diubah)
        if ($customer_data['username'] !== $new_username) { // Perbandingan dengan username yang sudah di-load
            $stmt_check_username_pel = $conn->prepare("SELECT pengguna_id FROM pelanggan WHERE username = ? AND pengguna_id != ?");
            $stmt_check_username_pel->bind_param("si", $new_username, $pengguna_id);
            $stmt_check_username_pel->execute();
            $stmt_check_username_pel->store_result();
            if ($stmt_check_username_pel->num_rows > 0) {
                $message = "Username '" . htmlspecialchars($new_username) . "' sudah digunakan oleh pelanggan lain.";
                $message_type = "error";
                $all_updates_successful = false;
            } else {
                $update_fields_pelanggan[] = "username = ?";
                $update_params_pelanggan[] = $new_username;
                $update_types_pelanggan .= "s";
            }
            $stmt_check_username_pel->close();
        }

        // Cek keunikan email di tabel 'pelanggan' (jika diubah)
        if ($customer_data['email'] !== $new_email) { // Perbandingan dengan email yang sudah di-load
            $stmt_check_email_pel = $conn->prepare("SELECT pengguna_id FROM pelanggan WHERE email = ? AND pengguna_id != ?");
            $stmt_check_email_pel->bind_param("si", $new_email, $pengguna_id);
            $stmt_check_email_pel->execute();
            $stmt_check_email_pel->store_result();
            if ($stmt_check_email_pel->num_rows > 0) {
                $message = "Email '" . htmlspecialchars($new_email) . "' sudah digunakan oleh pelanggan lain.";
                $message_type = "error";
                $all_updates_successful = false;
            } else {
                $update_fields_pelanggan[] = "email = ?";
                $update_params_pelanggan[] = $new_email;
                $update_types_pelanggan .= "s";
            }
            $stmt_check_email_pel->close();
        }

        // Handle upload foto profil baru
        // Direktori target sudah didefinisikan di awal: $base_image_directory
        $foto_to_save_in_db = $current_foto_filename_from_db; // Default: tetap menggunakan nama file yang sudah ada di DB

        if ($all_updates_successful && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
            $new_file_name = $_FILES["foto"]["name"]; // Menggunakan nama file asli
            $target_file_path = $base_image_directory . $new_file_name; // Path lengkap untuk disimpan di server
            $uploadOk = 1;

            // Periksa apakah file adalah gambar asli
            $check = getimagesize($_FILES["foto"]["tmp_name"]);
            if ($check === false) {
                $message = "File bukan gambar.";
                $message_type = "error";
                $uploadOk = 0;
            }

            // Periksa ukuran file (maks 5MB)
            if ($_FILES["foto"]["size"] > 5000000) {
                $message = "Maaf, ukuran file terlalu besar (maks 5MB).";
                $message_type = "error";
                $uploadOk = 0;
            }

            // Izinkan format file tertentu
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $message = "Maaf, hanya file JPG, JPEG, PNG & GIF yang diizinkan.";
                $message_type = "error";
                $uploadOk = 0;
            }

            // Pastikan direktori target ada
            if (!is_dir($base_image_directory)) {
                mkdir($base_image_directory, 0777, true); // Buat direktori jika tidak ada, dengan izin penuh
            }

            if ($uploadOk == 1) {
                // Periksa apakah file dengan nama yang sama sudah ada di direktori tujuan
                if (file_exists($target_file_path)) {
                    $message = "Nama file foto sudah ada di server. Harap ganti nama file Anda sebelum mengunggah.";
                    $message_type = "error";
                    $all_updates_successful = false; // Menghentikan proses upload dan update
                } else {
                    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file_path)) {
                        // Hapus foto lama jika bukan default_profile.png dan ada file-nya di server
                        if ($current_foto_filename_from_db != $default_profile_image && !empty($current_foto_filename_from_db)) {
                             $old_full_path = $base_image_directory . $current_foto_filename_from_db;
                             if (file_exists($old_full_path)) {
                                 unlink($old_full_path);
                             }
                        }
                        $foto_to_save_in_db = $new_file_name; // Simpan HANYA nama file ke database
                        $update_fields_pelanggan[] = "foto = ?";
                        $update_params_pelanggan[] = $foto_to_save_in_db;
                        $update_types_pelanggan .= "s";
                    } else {
                        $message = "Maaf, terjadi kesalahan saat mengunggah foto Anda.";
                        $message_type = "error";
                        $all_updates_successful = false;
                    }
                }
            } else {
                $all_updates_successful = false; // Upload tidak berhasil karena validasi
            }
        }


        // Hanya lakukan update jika belum ada error dari upload atau validasi sebelumnya
        if ($all_updates_successful) {
            if (!empty($update_fields_pelanggan)) {
                $query_update_pelanggan = "UPDATE pelanggan SET " . implode(", ", $update_fields_pelanggan) . " WHERE pengguna_id = ?";
                $stmt_update_pelanggan = $conn->prepare($query_update_pelanggan);

                if ($stmt_update_pelanggan) {
                    $update_params_pelanggan[] = $pengguna_id; // Parameter terakhir untuk klausa WHERE
                    $update_types_pelanggan .= "i";

                    // bind_param membutuhkan argumen by reference, gunakan call_user_func_array
                    // Khusus untuk NULL pada tanggal_lahir, kita harus menanganinya secara terpisah
                    $params_to_bind = [];
                    $types_to_bind = $update_types_pelanggan;
                    $param_index = 0;
                    foreach (explode(",", implode(", ", $update_fields_pelanggan)) as $field_expression) {
                        if (strpos($field_expression, '= ?') !== false) {
                            $params_to_bind[] = &$update_params_pelanggan[$param_index];
                            $param_index++;
                        }
                    }
                    $params_to_bind[] = &$update_params_pelanggan[$param_index]; // untuk $pengguna_id

                    call_user_func_array([$stmt_update_pelanggan, 'bind_param'], array_merge([$types_to_bind], $params_to_bind));
                    
                    if (!$stmt_update_pelanggan->execute()) {
                        $all_updates_successful = false;
                        $message = "Gagal memperbarui data pelanggan: " . $stmt_update_pelanggan->error;
                        $message_type = "error";
                    }
                    $stmt_update_pelanggan->close();
                } else {
                    $all_updates_successful = false;
                    $message = "Gagal mempersiapkan query update pelanggan: " . $conn->error;
                    $message_type = "error";
                }
            }


            // --- UPDATE TABEL 'pengguna' ---
            $update_fields_pengguna = [];
            $update_params_pengguna = [];
            $update_types_pengguna = "";

            // Update nama di tabel pengguna
            // Pastikan nama_from_pengguna diambil dari $customer_data
            if ($customer_data['nama_from_pengguna'] !== $new_nama) {
                $update_fields_pengguna[] = "nama = ?";
                $update_params_pengguna[] = $new_nama;
                $update_types_pengguna .= "s";
            }

            // Jika foto diubah dan berhasil, update juga foto di tabel pengguna jika ada kolomnya.
            if ($all_updates_successful && isset($foto_to_save_in_db) && ($foto_to_save_in_db !== $current_foto_filename_from_db)) {
                 $update_fields_pengguna[] = "foto = ?";
                 $update_params_pengguna[] = $foto_to_save_in_db;
                 $update_types_pengguna .= "s";
            }

            // Update username di tabel pengguna
            if ($all_updates_successful && $customer_data['username_from_pengguna'] !== $new_username) {
                $stmt_check_username_pengguna = $conn->prepare("SELECT id FROM pengguna WHERE username = ? AND id != ?");
                $stmt_check_username_pengguna->bind_param("si", $new_username, $pengguna_id);
                $stmt_check_username_pengguna->execute();
                $stmt_check_username_pengguna->store_result();
                if ($stmt_check_username_pengguna->num_rows > 0) {
                    $message = "Username '" . htmlspecialchars($new_username) . "' sudah digunakan oleh pengguna lain di tabel pengguna.";
                    $message_type = "error";
                    $all_updates_successful = false;
                } else {
                    $update_fields_pengguna[] = "username = ?";
                    $update_params_pengguna[] = $new_username;
                    $update_types_pengguna .= "s";
                }
                $stmt_check_username_pengguna->close();
            }

            // Update email di tabel pengguna
            if ($all_updates_successful && $customer_data['email_from_pengguna'] !== $new_email) {
                $stmt_check_email_pengguna = $conn->prepare("SELECT id FROM pengguna WHERE email = ? AND id != ?");
                $stmt_check_email_pengguna->bind_param("si", $new_email, $pengguna_id);
                $stmt_check_email_pengguna->execute();
                $stmt_check_email_pengguna->store_result();
                if ($stmt_check_email_pengguna->num_rows > 0) {
                    $message = "Email '" . htmlspecialchars($new_email) . "' sudah digunakan oleh pengguna lain di tabel pengguna.";
                    $message_type = "error";
                    $all_updates_successful = false;
                } else {
                    $update_fields_pengguna[] = "email = ?";
                    $update_params_pengguna[] = $new_email;
                    $update_types_pengguna .= "s";
                }
                $stmt_check_email_pengguna->close();
            }

            // Update password di tabel 'pengguna' (jika diisi)
            if ($all_updates_successful && !empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_fields_pengguna[] = "password = ?";
                $update_params_pengguna[] = $hashed_password;
                $update_types_pengguna .= "s";

                // Perbarui juga password di tabel pelanggan untuk konsistensi, jika ada kolomnya 'password'
                $query_update_pelanggan_pwd = "UPDATE pelanggan SET password = ? WHERE pengguna_id = ?";
                $stmt_update_pelanggan_pwd = $conn->prepare($query_update_pelanggan_pwd);
                if ($stmt_update_pelanggan_pwd) {
                    $stmt_update_pelanggan_pwd->bind_param("si", $hashed_password, $pengguna_id);
                    if (!$stmt_update_pelanggan_pwd->execute()) {
                        $all_updates_successful = false;
                        $message = "Gagal memperbarui password di tabel pelanggan: " . $stmt_update_pelanggan_pwd->error;
                        $message_type = "error";
                    }
                    $stmt_update_pelanggan_pwd->close();
                } else {
                    $all_updates_successful = false;
                    $message = "Gagal mempersiapkan query update password pelanggan: " . $conn->error;
                    $message_type = "error";
                }
            }

            // Jalankan update untuk tabel pengguna jika ada perubahan
            if ($all_updates_successful && !empty($update_fields_pengguna)) {
                $query_update_pengguna = "UPDATE pengguna SET " . implode(", ", $update_fields_pengguna) . " WHERE id = ?";
                $stmt_update_pengguna = $conn->prepare($query_update_pengguna);
                if ($stmt_update_pengguna) {
                    $update_params_pengguna[] = $pengguna_id; // Parameter terakhir untuk klausa WHERE
                    $update_types_pengguna .= "i";

                    call_user_func_array([$stmt_update_pengguna, 'bind_param'], array_merge([$update_types_pengguna], $update_params_pengguna));
                    if (!$stmt_update_pengguna->execute()) {
                        $all_updates_successful = false;
                        $message = "Gagal memperbarui data pengguna: " . $stmt_update_pengguna->error;
                        $message_type = "error";
                    }
                    $stmt_update_pengguna->close();
                } else {
                    $all_updates_successful = false;
                    $message = "Gagal mempersiapkan query update pengguna: " . $conn->error;
                    $message_type = "error";
                }
            }

            // --- UPDATE TABEL 'anggota' ---
            // Hanya update jika pelanggan memiliki nomor_anggota yang valid dan nama di anggota berbeda dari yang baru
            if ($all_updates_successful && !empty($customer_data['nomor_anggota']) && $customer_data['nama_from_anggota'] !== $new_nama) {
                $query_update_anggota = "UPDATE anggota SET nama = ? WHERE nomor_anggota = ?";
                $stmt_update_anggota = $conn->prepare($query_update_anggota);
                if ($stmt_update_anggota) {
                    $stmt_update_anggota->bind_param("ss", $new_nama, $customer_data['nomor_anggota']);
                    if (!$stmt_update_anggota->execute()) {
                        $all_updates_successful = false;
                        $message = "Gagal memperbarui data anggota: " . $stmt_update_anggota->error;
                        $message_type = "error";
                    }
                    $stmt_update_anggota->close();
                } else {
                    $all_updates_successful = false;
                    $message = "Gagal mempersiapkan query update anggota: " . $conn->error;
                    $message_type = "error";
                }
            }

            // Update alamat di tabel 'anggota'
            // Hanya update jika pelanggan memiliki nomor_anggota yang valid dan alamat di anggota berbeda dari yang baru
            if ($all_updates_successful && !empty($customer_data['nomor_anggota']) && $customer_data['alamat_from_anggota'] !== $new_alamat) {
                $query_update_anggota_alamat = "UPDATE anggota SET alamat = ? WHERE nomor_anggota = ?";
                $stmt_update_anggota_alamat = $conn->prepare($query_update_anggota_alamat);
                if ($stmt_update_anggota_alamat) {
                    $stmt_update_anggota_alamat->bind_param("ss", $new_alamat, $customer_data['nomor_anggota']);
                    if (!$stmt_update_anggota_alamat->execute()) {
                        $all_updates_successful = false;
                        $message = "Gagal memperbarui alamat di tabel anggota: " . $stmt_update_anggota_alamat->error;
                        $message_type = "error";
                    }
                    $stmt_update_anggota_alamat->close();
                } else {
                    $all_updates_successful = false;
                    $message = "Gagal mempersiapkan query update alamat anggota: " . $conn->error;
                    $message_type = "error";
                }
            }

        } // End of if ($all_updates_successful) for database operations


        // Komit atau rollback transaksi
        if ($all_updates_successful && $message_type !== "error") {
            $conn->commit();
            $message = "Profil berhasil diperbarui!";
            $message_type = "success";
            // Redirect untuk menghindari resubmission form dan menampilkan pesan
            // Menggunakan parameter GET untuk status dan pesan, lalu JavaScript akan menanganinya
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=success&msg=" . urlencode($message));
            exit();
        } else {
            $conn->rollback();
            if ($message_type === "") { // Jika tidak ada pesan error spesifik yang diset sebelumnya
                $message = "Terjadi kesalahan saat memperbarui profil. Mohon coba lagi.";
                $message_type = "error";
            }
        }
    }
}

// Menampilkan pesan dari URL setelah redirect
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['status']);
}

// Menutup koneksi database
$conn->close();

$page_title = "Profil Pelanggan"; // Judul halaman
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Body Styling */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Inter', sans-serif; /* Preferred font */
            background-color: #f8f9fa;
            color: #333;
        }

        /* Navbar Styling (Disalin dari kategori.php) */
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
            height: auto;
        }

        .nav-link {
            color: white !important; /* Ubah dari rgba menjadi putih solid */
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: white !important;
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

        /* Navbar badge styles (Disalin dan Disesuaikan) */
        .navbar-nav .nav-item {
            position: relative; /* Ini sangat penting untuk posisi badge */
        }
        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        .navbar-nav .badge {
            font-size: 0.75em;
            transform: translateY(-50%);
            top: 40%;
            right: -18px;
            padding: 0.4em 0.7em;
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
            background-color: #ffe0b2;
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* Profile image in navbar (Disesuaikan) */
        .profile-img-sm {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 5px; /* DIUBAH: Tambahkan margin kanan untuk jarak dengan nama */
        }

        /* Dropdown for cart items in navbar (Disalin dari kategori.php) */
        #dropdown-keranjang {
            width: 430px;
            z-index: 1000;
            display: none; /* Awalnya tersembunyi, akan diatur oleh JS */
            position: absolute; /* Added for correct positioning relative to parent */
            top: 100%; /* Position below the nav item */
            right: 0;
            left: auto; /* Memastikan dropdown berada di kanan */
            min-width: 280px;
            background-color: white; /* Diubah menjadi putih untuk kontras yang lebih baik */
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
        }

        #dropdown-keranjang h5 {
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 3px;
            display: inline-block;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }

        #daftar-produk-keranjang li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            flex-direction: row;
            gap: 10px;
        }
        #daftar-produk-keranjang li:last-child {
            border-bottom: none;
        }
        #daftar-produk-keranjang li img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        #daftar-produk-keranjang li .item-details {
            flex-grow: 1;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #daftar-produk-keranjang li .item-details .product-name {
            font-weight: bold;
            font-size: 0.9rem;
            color: #333; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .product-variation {
            font-size: 0.75rem;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }
        #daftar-produk-keranjang li .item-details .item-price {
            color: #FF4500;
            font-size: 0.85rem;
            text-align: right;
            margin-left: auto;
            flex-shrink: 0;
        }
        #pesan-keranjang-kosong {
            text-align: center;
            margin-top: 10px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
            color: #666; /* Warna teks yang cocok dengan latar belakang putih */
        }

        .d-flex.justify-content-between.align-items-center.mt-2 {
            margin-top: 15px;
            background-color: white;
            padding: 10px;
            border-radius: 3px;
        }

        #jumlah-produk-lainnya {
            color: #6c757d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
            background-color: #FF4500;
            color: white;
            text-decoration: none;
        }


        /* Content Area Styling */
        .container.my-5.flex-grow-1 {
            max-width: 850px; /* Slightly wider content area */
            margin-top: 4rem !important; /* Adjust top margin for larger navbar */
            margin-bottom: 4rem !important; /* Adjust bottom margin for larger footer */
            padding-left: 1.5rem; /* Increased padding */
            padding-right: 1.5rem; /* Increased padding */
        }

        .card {
            padding: 2rem; /* Increased padding for the content card */
        }

        .profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 2rem; /* Increased margin */
        }

        .profile-img {
            width: 150px; /* Larger profile image in content */
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f97316; /* Larger border */
            box-shadow: 0 0 0 8px rgba(249, 115, 22, 0.2); /* Larger shadow */
            margin-bottom: 1rem; /* Increased margin */
        }

        .profile-name {
            font-size: 1.8rem; /* Larger font size */
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.35rem;
        }

        .profile-username {
            font-size: 1rem; /* Larger font size */
            color: #4a5568;
        }
        
        .input-field {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem; /* Larger input field padding */
            width: 100%;
            transition: all 0.2s ease-in-out;
            font-size: 1rem; /* Larger font size */
        }
        .input-field:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 0.25rem rgba(249, 115, 22, 0.25);
        }
        .btn-primary-custom {
            background-color: #f97316;
            color: white;
            padding: 0.75rem 1.5rem; /* Larger button padding */
            border-radius: 0.375rem;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            border: none;
            font-size: 1rem; /* Larger button font size */
        }
        .btn-primary-custom:hover {
            background-color: #ea580c;
            transform: translateY(-2px);
            color: white;
        }
        .btn-secondary-custom {
            background-color: #f1f5f9;
            color: #475569;
            padding: 0.75rem 1.5rem; /* Larger button padding */
            border-radius: 0.375rem;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out;
            border: none;
            font-size: 1rem; /* Larger button font size */
        }
        .btn-secondary-custom:hover {
            background-color: #e2e8f0;
            color: #475569;
        }
        .alert-success-custom {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        .alert-danger-custom {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .information-section h3 {
            font-size: 1.3rem; /* Larger heading for information section */
            margin-bottom: 1rem; /* Increased margin */
        }

        .information-section p {
            font-size: 1rem; /* Larger text for information details */
        }

        /* Modal specific styles for larger content */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Slightly darker overlay */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050; /* Higher z-index to be above navbar (navbar is 1030 in Bootstrap) */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content-custom {
            background-color: #ffffff;
            padding: 2.5rem; /* Increased padding in modal */
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25); /* More prominent shadow */
            width: 90%;
            max-width: 550px; /* Increased max-width for modal */
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-50px);
            transition: transform 0.3s ease-in-out;
            position: relative;
        }
        .modal-overlay.show .modal-content-custom {
            transform: translateY(0);
        }
        .modal-close-button {
            position: absolute;
            top: 1rem; /* Adjust close button position */
            right: 1rem; /* Adjust close button position */
            background: none;
            border: none;
            font-size: 1.8rem; /* Slightly larger close button */
            cursor: pointer;
            color: #64748b;
            z-index: 10; /* Ensure button is above modal content */
            padding: 0.2rem 0.5rem; /* Add padding for easier click */
            border-radius: 50%; /* Make it round */
            transition: background-color 0.2s ease;
        }
        .modal-close-button:hover {
            color: #1e293b;
            background-color: #f0f0f0; /* Light background on hover */
        }
        .modal-content-custom h3 {
            font-size: 1.85rem; /* Larger modal heading */
            margin-bottom: 1.75rem; /* Increased bottom margin */
            padding-bottom: 0.75rem; /* Padding below heading */
            border-bottom: 1px solid #ececec; /* Subtle line below heading */
        }
        .form-label {
            font-size: 1.05rem; /* Slightly larger form label font size */
            font-weight: 500; /* Medium font weight */
            margin-bottom: 0.5rem;
        }
        .form-text {
            font-size: 0.875rem; /* Slightly larger helper text */
        }

        /* Footer Styling (Disalin dari kategori.php) */
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

        /* Responsive adjustments for Navbar (Disalin dari kategori.php) */
        @media (max-width: 991.98px) { /* This is Bootstrap's `lg` breakpoint */
            .navbar-collapse {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }

            .navbar-nav {
                width: 100%;
                margin-top: 15px;
            }

            .navbar-nav .nav-item {
                width: 100%;
                text-align: left;
            }

            .navbar-nav .nav-link {
                padding: 8px 15px; /* Adjust padding for mobile nav links */
                font-size: 1rem;
            }

            .navbar .d-flex.me-2 { /* Adjust search form */
                width: 100%;
                margin-right: 0 !important;
                margin-bottom: 15px;
                margin-top: 10px; /* Adjusted spacing */
            }

            .navbar .input-group {
                width: 100%;
            }

            .navbar .form-control-sm {
                flex-grow: 1;
                width: auto;
                min-width: 120px;
                max-width: none; /* Allow full width on small screens */
            }

            /* Adjust for stacked icons and badges on small screens (Disesuaikan) */
            .navbar-nav .nav-link .badge {
                position: static; /* Kembali ke static untuk mobile menu agar sejajar dengan teks */
                transform: none;
                margin-left: 5px; /* Tambah margin kiri sedikit pada badge */
                font-size: 0.8em; /* Ukuran teks badge sedikit lebih besar untuk keterbacaan */
                min-width: unset; /* Hapus min-width agar ukuran ditentukan oleh konten */
                height: auto; /* Hapus tinggi tetap */
                border-radius: 0.25rem; /* Kembali ke sudut standar */
                padding: 0.2em 0.5em; /* Padding standar */
            }
            .navbar-nav .dropdown {
                width: 100%;
            }

            .navbar-nav .dropdown-toggle {
                width: 100%;
                text-align: left;
                padding: 10px 15px;
            }

            .navbar-nav .dropdown-menu {
                width: 100%;
                left: 0 !important;
                right: auto !important;
            }

            .navbar-nav .dropdown-toggle img {
                width: 25px; /* Larger profile image for mobile navbar */
                height: 25px;
                margin-right: 8px;
            }

            /* Cart dropdown on small screens (adjust to full width) */
            #dropdown-keranjang {
                width: calc(100% - 30px); /* Full width minus some padding */
                right: 15px;
                left: 15px;
            }
        }

        /* Optional: If you want navbar-toggler to stay on the right when collapse is open */
        @media (max-width: 991.98px) {
            .navbar-toggler {
                position: absolute;
                right: 15px;
                top: 15px;
                z-index: 1001;
            }
            .navbar-brand {
                margin-right: auto;
            }
        }
    </style>
</head>
<body class="bg-light min-h-screen">

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #FF4500;">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../../img/logo.png" alt="Logo BUMDes" height="30" class="d-inline-block align-top">
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
                            <a class="nav-link" href="../keranjang/keranjang.php" id="link-keranjang">
                                <i class="bi bi-cart-fill"></i>
                                <span class="badge bg-light text-danger rounded-pill" id="jumlah-keranjang">
                                    <?php echo $total_produk_di_keranjang; ?>
                                </span>
                            </a>
                            <div id="dropdown-keranjang" class="card shadow p-3 position-absolute mt-2">
                                <h5>Baru Ditambahkan</h5>
                                <ul class="list-unstyled" id="daftar-produk-keranjang">
                                    <li id="pesan-keranjang-kosong" class="text-center text-muted">Keranjang belanja kosong.</li>
                                </ul>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span id="jumlah-produk-lainnya" class="text-muted" style="display: none;"></span>
                                    <a href="../keranjang/keranjang.php" class="btn btn-sm" style="background-color: #FF4500; color: white;">Tampilkan Keranjang Belanja</a>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (isset($foto_pelanggan_navbar) && $foto_pelanggan_navbar): ?>
                                    <img src="../../img/foto/<?php echo htmlspecialchars($foto_pelanggan_navbar); ?>" alt="Foto Profil" class="rounded-circle me-1 profile-img-sm">
                            <?php else: ?>
                                    <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <span class="ms-1 text-white"><?php echo htmlspecialchars($nama_pelanggan_navbar ?? 'Tamu'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <?php if ($pengguna_id): ?>
                                <li><a class="dropdown-item active" href="profil.php">Profil</a></li>
                                <li><a class="dropdown-item" href="../keranjang/pesanan_saya.php">Pesanan Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="../../login.php">Login</a></li>
                                <li><a class="dropdown-item" href="../../registrasi.php">Daftar</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5 flex-grow-1">
        <?php if ($message): ?>
            <div id="alertMessage" class="alert <?php echo $message_type === 'success' ? 'alert-success-custom' : 'alert-danger-custom'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card p-6 md:p-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Profil Pelanggan</h2>

            <?php if ($customer_data): ?>
                <div class="profile-section">
                    <img src="<?php echo htmlspecialchars($customer_data['foto']); ?>"
                         alt="Foto Profil" class="profile-img">
                    <p class="profile-name"><?php echo htmlspecialchars($customer_data['nama']); ?></p>
                    <p class="profile-username">@<?php echo htmlspecialchars($customer_data['username']); ?></p>
                </div>

                <div class="information-section mt-8">
                    <h3 class="text-xl font-bold text-orange-500 mb-4 border-bottom pb-2 border-gray-200">Informasi Pribadi</h3>
                    <div class="space-y-3 text-gray-700">
                        <p><span class="fw-semibold">Email:</span> <?php echo htmlspecialchars($customer_data['email']); ?></p>
                        <p><span class="fw-semibold">No. Telepon:</span> <?php echo htmlspecialchars($customer_data['nomor_telepon'] ?: '-'); ?></p>
                        <p><span class="fw-semibold">Alamat:</span> <?php echo htmlspecialchars($customer_data['alamat'] ?: '-'); ?></p>
                        <p><span class="fw-semibold">Jenis Kelamin:</span> <span class="text-capitalize"><?php echo htmlspecialchars($customer_data['jenis_kelamin'] ?: '-'); ?></span></p>
                        <p><span class="fw-semibold">Tanggal Lahir:</span> <?php
                            if (!empty($customer_data['tanggal_lahir'])) {
                                $timestamp = strtotime($customer_data['tanggal_lahir']);
                                $day = date('d', $timestamp);
                                $month_num = date('n', $timestamp);
                                $year = date('Y', $timestamp);
                                echo htmlspecialchars($day . ' ' . $indonesian_months[$month_num] . ' ' . $year);
                            } else {
                                echo '-';
                            }
                        ?></p>
                        <p><span class="fw-semibold">Tanggal Bergabung:</span> <?php echo htmlspecialchars($customer_data['created_at'] ? date('d M Y H:i:s', strtotime($customer_data['created_at'])) : '-'); ?></p>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <button id="editProfileBtn" class="btn-primary-custom">Edit Profil</button>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-600">Tidak dapat memuat data profil. Pastikan Anda sudah login.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="editProfileModal" class="modal-overlay hidden">
        <div class="modal-content-custom">
            <button id="closeModalBtn" class="modal-close-button">×</button>
            <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Edit Profil</h3>
            <?php if ($customer_data): // Pastikan data pelanggan tersedia sebelum menampilkan form ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-12">
                        <label for="nama" class="form-label fw-bold">Nama Lengkap:</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($customer_data['nama']); ?>" required class="form-control input-field">
                    </div>
                    <div class="col-12">
                        <label for="username" class="form-label fw-bold">Username:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($customer_data['username']); ?>" required class="form-control input-field">
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label fw-bold">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer_data['email']); ?>" required class="form-control input-field">
                    </div>
                    <div class="col-12">
                        <label for="nomor_telepon" class="form-label fw-bold">Nomor Telepon:</label>
                        <input type="text" id="nomor_telepon" name="nomor_telepon" value="<?php echo htmlspecialchars($customer_data['nomor_telepon']); ?>" class="form-control input-field">
                    </div>
                    <div class="col-12">
                        <label for="alamat" class="form-label fw-bold">Alamat:</label>
                        <textarea id="alamat" name="alamat" rows="3" class="form-control input-field"><?php echo htmlspecialchars($customer_data['alamat']); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label for="jenis_kelamin" class="form-label fw-bold">Jenis Kelamin:</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-select input-field">
                            <option value="">Pilih</option>
                            <option value="pria" <?php echo ($customer_data['jenis_kelamin'] === 'pria') ? 'selected' : ''; ?>>Pria</option>
                            <option value="wanita" <?php echo ($customer_data['jenis_kelamin'] === 'wanita') ? 'selected' : ''; ?>>Wanita</option>
                            <option value="lainnya" <?php echo ($customer_data['jenis_kelamin'] === 'lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="tanggal_lahir" class="form-label fw-bold">Tanggal Lahir:</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($customer_data['tanggal_lahir'] ?: ''); ?>" class="form-control input-field">
                    </div>
                    <div class="col-12">
                        <label for="foto" class="form-label fw-bold">Ganti Foto Profil:</label>
                        <input type="file" id="foto" name="foto" accept="image/*" class="form-control">
                        <p class="form-text text-muted mt-1">Ukuran maks: 5MB. Format: JPG, JPEG, PNG, GIF.</p>
                    </div>
                    <div class="col-12">
                        <label for="password" class="form-label fw-bold">Ganti Password (kosongkan jika tidak ingin ganti):</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control input-field">
                            <button type="button" id="togglePasswordVisibility" class="btn btn-outline-secondary">
                                <i id="eyeIconOpen" class="bi bi-eye"></i>
                                <i id="eyeIconClosed" class="bi bi-eye-slash hidden" style="display: none;"></i>
                            </button>
                        </div>
                        <p class="form-text text-muted mt-1">Isi hanya jika ingin mengganti password Anda.</p>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" id="cancelModalBtn" class="btn-secondary-custom">Batal</button>
                        <button type="submit" name="update_profile" class="btn-primary-custom">Simpan Perubahan</button>
                    </div>
                </form>
            <?php endif; ?>
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
                    <h5 class="fw-bold text-white"><i class="bi bi-map"></i> PETA LOKASI</h5>
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
    <script>
        const editProfileBtn = document.getElementById('editProfileBtn');
        const editProfileModal = document.getElementById('editProfileModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const alertMessage = document.getElementById('alertMessage');

        // Password visibility toggle
        const passwordInput = document.getElementById('password');
        const togglePasswordVisibility = document.getElementById('togglePasswordVisibility');
        const eyeIconOpen = document.getElementById('eyeIconOpen');
        const eyeIconClosed = document.getElementById('eyeIconClosed');

        function showModal() {
            editProfileModal.classList.add('show');
            document.body.classList.add('modal-open');
        }

        function hideModal() {
            editProfileModal.classList.remove('show');
            document.body.classList.remove('modal-open');
            setTimeout(() => {
                editProfileModal.classList.add('hidden');
            }, 300);
        }

        editProfileBtn.addEventListener('click', () => {
            editProfileModal.classList.remove('hidden');
            showModal();
        });

        closeModalBtn.addEventListener('click', hideModal);
        cancelModalBtn.addEventListener('click', hideModal);

        editProfileModal.addEventListener('click', (event) => {
            if (event.target === editProfileModal) {
                hideModal();
            }
        });

        // Hide alert after some seconds
        if (alertMessage) {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertMessage); // Get Bootstrap alert instance
                bsAlert.close(); // Use Bootstrap's close method for proper fading
                // Clear URL parameters after alert is hidden
                alertMessage.addEventListener('closed.bs.alert', () => {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('status');
                    url.searchParams.delete('msg');
                    window.history.replaceState({}, document.title, url.toString());
                }, { once: true });
            }, 5000); // Hide after 5 seconds
        }

        togglePasswordVisibility.addEventListener('click', () => {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIconOpen.style.display = 'none';
                eyeIconClosed.style.display = 'inline-block';
            } else {
                passwordInput.type = 'password';
                eyeIconOpen.style.display = 'inline-block';
                eyeIconClosed.style.display = 'none';
            }
        });

        // Functions for Navbar Cart Dropdown (Disalin dari kategori.php)
        function formatRupiah(angka) {
            let number = parseFloat(angka);
            if (isNaN(number)) {
                console.error("Input ke formatRupiah bukan angka yang valid:", angka);
                return "0";
            }
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(number);
        }

        function muatJumlahKeranjangNav() {
            $.ajax({
                url: '../keranjang/get_cart_count.php', // Adjusted path relative to profil.php
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#jumlah-keranjang').text(response.count);
                    } else {
                        console.error('Failed to load cart count in navbar:', response.message);
                        $('#jumlah-keranjang').text('0');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error (get_cart_count):', status, error);
                    $('#jumlah-keranjang').text('0');
                }
            });
        }

        function muatIsiKeranjangDropdown() {
            const daftarProdukKeranjang = document.getElementById('daftar-produk-keranjang');
            const pesanKeranjangKosong = document.getElementById('pesan-keranjang-kosong');
            const jumlahProdukLainnyaSpan = document.getElementById('jumlah-produk-lainnya');

            if (jumlahProdukLainnyaSpan) {
                jumlahProdukLainnyaSpan.style.display = 'none';
                jumlahProdukLainnyaSpan.textContent = '';
            }

            fetch('../keranjang/ambil_keranjang_sementara.php') // Adjusted path relative to profil.php
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (daftarProdukKeranjang) {
                        daftarProdukKeranjang.innerHTML = '';
                    }

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
                                if (daftarProdukKeranjang) {
                                    daftarProdukKeranjang.appendChild(listItem);
                                }
                            } else {
                                totalQuantityOtherProducts += item.quantity;
                            }
                        });

                        if (totalQuantityOtherProducts > 0 && jumlahProdukLainnyaSpan) {
                            jumlahProdukLainnyaSpan.textContent = `${totalQuantityOtherProducts} Produk Lainnya`;
                            jumlahProdukLainnyaSpan.style.display = 'inline-block';
                        } else if (jumlahProdukLainnyaSpan) {
                            jumlahProdukLainnyaSpan.style.display = 'none';
                        }

                    } else {
                        if (pesanKeranjangKosong) {
                            pesanKeranjangKosong.textContent = "Keranjang belanja kosong.";
                            pesanKeranjangKosong.style.display = 'block';
                        }
                        if (jumlahProdukLainnyaSpan) {
                            jumlahProdukLainnyaSpan.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching cart items for dropdown:', error);
                    if (pesanKeranjangKosong) {
                        pesanKeranjangKosong.textContent = "Failed to load cart details. Please try again.";
                        pesanKeranjangKosong.style.display = 'block';
                    }
                    if (jumlahProdukLainnyaSpan) {
                        jumlahProdukLainnyaSpan.style.display = 'none';
                    }
                });
        }

        // Event listener for showing/hiding cart dropdown
        document.addEventListener('DOMContentLoaded', () => {
            const linkKeranjang = document.getElementById('link-keranjang');
            const dropdownKeranjang = document.getElementById('dropdown-keranjang');

            muatJumlahKeranjangNav();

            if (linkKeranjang && dropdownKeranjang) {
                linkKeranjang.addEventListener('mouseenter', () => {
                    muatIsiKeranjangDropdown();
                    dropdownKeranjang.style.display = 'block';
                });

                // Corrected: Add event listener to the dropdown itself for mouseleave
                dropdownKeranjang.addEventListener('mouseleave', () => {
                    dropdownKeranjang.style.display = 'none';
                });

                // Hide dropdown when clicking outside of the link and dropdown
                document.addEventListener('click', (event) => {
                    if (!linkKeranjang.contains(event.target) && !dropdownKeranjang.contains(event.target)) {
                        dropdownKeranjang.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>