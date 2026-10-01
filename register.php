<?php
session_start();
include 'koneksi/koneksi.php'; // Pastikan path ke koneksi.php benar

// Aktifkan error reporting untuk membantu debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$success_message = '';
$error_message = '';
$nomor_anggota_display = 'Otomatis'; // Untuk ditampilkan di form

// Menyiapkan nilai untuk mengisi kembali form jika ada error
$nama_lengkap_val = '';
$no_hp_val = '';
$alamat_val = '';
$username_val = '';
$email_val = '';
$tanggal_lahir_val = '';
$jenis_kelamin_val = '';
$nama_toko_val = '';
$nama_pemilik_val = '';
$role_val = '';

if (isset($_POST['register'])) {
    // Ambil semua data POST
    $role = $_POST['role'] ?? '';
    $nama = $_POST['nama_lengkap'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? null;
    $nama_toko = $_POST['nama_toko'] ?? '';
    $nama_pemilik = $_POST['nama_pemilik'] ?? '';

    // Set nilai untuk mengisi kembali form
    $role_val = htmlspecialchars($role);
    $nama_lengkap_val = htmlspecialchars($nama);
    $no_hp_val = htmlspecialchars($no_hp);
    $alamat_val = htmlspecialchars($alamat);
    $username_val = htmlspecialchars($username);
    $email_val = htmlspecialchars($email);
    $tanggal_lahir_val = htmlspecialchars($tanggal_lahir);
    $jenis_kelamin_val = htmlspecialchars($jenis_kelamin);
    $nama_toko_val = htmlspecialchars($nama_toko);
    $nama_pemilik_val = htmlspecialchars($nama_pemilik);


    // Validasi dasar untuk semua field wajib
    if (empty($role)) {
        $error_message .= "Pilih peran Anda (Penjual/Pelanggan). ";
    }
    if (empty($nama)) {
        $error_message .= "Nama lengkap wajib diisi. ";
    }
    if (empty($no_hp)) {
        $error_message .= "Nomor HP wajib diisi. ";
    }
    if (empty($alamat)) {
        $error_message .= "Alamat wajib diisi. ";
    }
    if (empty($username)) {
        $error_message .= "Username wajib diisi. ";
    }
    if (empty($email)) {
        $error_message .= "Email wajib diisi. ";
    }
    if (empty($password)) {
        $error_message .= "Password wajib diisi. ";
    }
    if (empty($confirm_password)) {
        $error_message .= "Konfirmasi Password wajib diisi. ";
    }

    $foto_name = '';
    $upload_error = false;

    // Proses upload foto - WAJIB DIISI
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        $error_message .= "Foto profil wajib diisi. ";
        $upload_error = true;
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $error_message .= "Terjadi kesalahan saat mengupload foto. Kode Error: " . $_FILES['foto']['error'] . ". Silakan coba lagi. ";
        $upload_error = true;
    } else {
        $upload_dir = 'img/foto/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $tmp_name = $_FILES['foto']['tmp_name'];
        $foto_original_name = $_FILES['foto']['name'];
        $imageFileType = strtolower(pathinfo($foto_original_name, PATHINFO_EXTENSION));
        $allowedExtensions = array("jpg", "jpeg", "png");

        if (in_array($imageFileType, $allowedExtensions)) {
            $new_foto_name = uniqid() . '.' . $imageFileType;
            $target_path_unique = $upload_dir . $new_foto_name;

            if (!move_uploaded_file($tmp_name, $target_path_unique)) {
                $error_message .= "Gagal mengupload foto. Silakan coba lagi. ";
                $upload_error = true;
            } else {
                $foto_name = $new_foto_name;
            }
        } else {
            $error_message .= "Jenis file foto tidak diizinkan (hanya JPG, JPEG, PNG). ";
            $upload_error = true;
        }
    }

    // Validasi khusus untuk peran
    if ($role == 'penjual') {
        if (empty($nama_toko)) {
            $error_message .= "Nama Toko wajib diisi untuk penjual. ";
        }
        if (empty($nama_pemilik)) {
            $error_message .= "Nama Brand / Pemilik Usaha wajib diisi untuk penjual. ";
        }
    }

    // Validasi konfirmasi password
    if ($password !== $confirm_password) {
        $error_message .= "Password dan Konfirmasi Password tidak cocok. ";
    }

    // Validasi username dan email agar tidak duplikat
    // Hanya lakukan pengecekan duplikasi jika belum ada error lain
    if (empty($error_message)) {
        $check_query = "SELECT username, email FROM pengguna WHERE username = ? OR email = ?";
        $stmt_check = $conn->prepare($check_query);
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                if ($row['username'] == $username) {
                    $error_message .= "Username sudah terdaftar. Silakan gunakan username lain. ";
                }
                if ($row['email'] == $email) {
                    $error_message .= "Email sudah terdaftar. Silakan gunakan email lain. ";
                }
            }
            $stmt_check->close();
        } else {
            $error_message .= "Error mempersiapkan query cek duplikasi: " . $conn->error . ". ";
        }
    }


    if (empty($error_message) && !$upload_error) {
        // Data bersih dan siap disimpan
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Simpan ke tabel pengguna
        $query_pengguna = "INSERT INTO pengguna (nama, username, password, email, role, foto)
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_pengguna = $conn->prepare($query_pengguna);
        if ($stmt_pengguna) {
            $stmt_pengguna->bind_param("ssssss", $nama, $username, $password_hash, $email, $role, $foto_name);
            $result_pengguna = $stmt_pengguna->execute();
            $stmt_pengguna->close();

            if ($result_pengguna) {
                $pengguna_id = mysqli_insert_id($conn);

                if ($role == 'pelanggan') {
                    $tanggal_bergabung = date('Y-m-d');
                    // Ambil nomor anggota terakhir
                    $query_last = "SELECT nomor_anggota FROM pelanggan WHERE nomor_anggota IS NOT NULL ORDER BY nomor_anggota DESC LIMIT 1";
                    $result_last = mysqli_query($conn, $query_last);

                    $next_number = 1;
                    if ($result_last && mysqli_num_rows($result_last) > 0) {
                        $row = mysqli_fetch_assoc($result_last);
                        $last_nomor = $row['nomor_anggota'];
                        if (strpos($last_nomor, 'ANG') === 0 && is_numeric(substr($last_nomor, 3))) {
                            $last_number = intval(substr($last_nomor, 3));
                            $next_number = $last_number + 1;
                        }
                    }
                    $nomor_anggota_new = 'ANG' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
                    $nomor_anggota_display = $nomor_anggota_new; // Update for display

                    // Simpan ke tabel pelanggan
                    $query_pelanggan = "INSERT INTO pelanggan (pengguna_id, nama, email, username, password, alamat, nomor_telepon, tanggal_bergabung, nomor_anggota, foto, tanggal_lahir, jenis_kelamin)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_pelanggan = $conn->prepare($query_pelanggan);
                    if ($stmt_pelanggan) {
                        $stmt_pelanggan->bind_param("isssssssssss", $pengguna_id, $nama, $email, $username, $password_hash, $alamat, $no_hp, $tanggal_bergabung, $nomor_anggota_new, $foto_name, $tanggal_lahir, $jenis_kelamin);
                        $result_pelanggan = $stmt_pelanggan->execute();
                        $stmt_pelanggan->close();

                        // Simpan juga ke tabel anggota (Pastikan tabel 'anggota' ada dan sesuai dengan kolom)
                        $query_anggota = "INSERT INTO anggota (nama, alamat, nomor_anggota, tanggal_bergabung)
                                             VALUES (?, ?, ?, ?)";
                        $stmt_anggota = $conn->prepare($query_anggota);
                        if ($stmt_anggota) {
                            $stmt_anggota->bind_param("ssss", $nama, $alamat, $nomor_anggota_new, $tanggal_bergabung);
                            $result_anggota = $stmt_anggota->execute();
                            $stmt_anggota->close();

                            if ($result_pelanggan && $result_anggota) {
                                $success_message = "Registrasi berhasil sebagai $role dengan Nomor Anggota: $nomor_anggota_new! Anda dapat login sekarang.";
                                // Reset form data setelah sukses
                                $nama_lengkap_val = $no_hp_val = $alamat_val = $username_val = $email_val = $tanggal_lahir_val = $jenis_kelamin_val = $nama_toko_val = $nama_pemilik_val = $role_val = '';
                            } else {
                                $error_message = "Registrasi sebagai pelanggan gagal. Silakan coba lagi. Error Pelanggan: " . $conn->error . " Error Anggota: " . $conn->error;
                                // Hapus data pengguna yang mungkin sudah masuk jika pendaftaran pelanggan/anggota gagal
                                mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                            }
                        } else {
                             $error_message = "Gagal mempersiapkan query anggota: " . $conn->error;
                             mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                        }
                    } else {
                        $error_message = "Gagal mempersiapkan query pelanggan: " . $conn->error;
                        mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                    }
                } elseif ($role == 'penjual') {
                    $query_penjual = "INSERT INTO penjual (pengguna_id, nama_toko, nama_pemilik, email, username, password, alamat, nomor_telepon, foto, tanggal_lahir, jenis_kelamin)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_penjual = $conn->prepare($query_penjual);
                    if ($stmt_penjual) {
                        $stmt_penjual->bind_param("issssssssss", $pengguna_id, $nama_toko, $nama_pemilik, $email, $username, $password_hash, $alamat, $no_hp, $foto_name, $tanggal_lahir, $jenis_kelamin);
                        $result_penjual = $stmt_penjual->execute();
                        $stmt_penjual->close();

                        if ($result_penjual) {
                            $success_message = "Registrasi berhasil sebagai $role! Anda dapat login sekarang.";
                            // Reset form data setelah sukses
                            $nama_lengkap_val = $no_hp_val = $alamat_val = $username_val = $email_val = $tanggal_lahir_val = $jenis_kelamin_val = $nama_toko_val = $nama_pemilik_val = $role_val = '';
                        } else {
                            $error_message = "Registrasi sebagai penjual gagal. Silakan coba lagi. Error: " . $conn->error;
                            // Hapus data pengguna yang mungkin sudah masuk jika pendaftaran penjual gagal
                            mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                        }
                    } else {
                        $error_message = "Gagal mempersiapkan query penjual: " . $conn->error;
                        mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                    }
                } else {
                    // Ini seharusnya tidak tercapai jika validasi peran di awal sudah benar
                    $error_message .= "Peran pengguna tidak valid. ";
                    mysqli_query($conn, "DELETE FROM pengguna WHERE id = '$pengguna_id'");
                }
            } else {
                $error_message .= "Registrasi ke tabel pengguna gagal. Silakan coba lagi. Error Pengguna: " . $conn->error;
            }
        } else {
            $error_message .= "Gagal mempersiapkan query pengguna: " . $conn->error;
        }
    }
    // Tutup koneksi setelah semua operasi selesai
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi Pengguna BUMDES</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <style>
        /* General Body Styling */
        body {
            background-image: url('images/hp.avif');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', sans-serif;
            color: #333;
        }

        /* Navbar Styling */
        .navbar {
            background-color: #FF4500 !important; /* Primary color */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .navbar .container-fluid {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 1200px) {
            .navbar .container-fluid {
                padding-left: 10rem;
                padding-right: 10rem;
            }
        }
        @media (min-width: 1400px) {
            .navbar .container-fluid {
                padding-left: 15rem;
                padding-right: 15rem;
            }
        }

        .navbar-brand {
            font-weight: bold;
            display: flex;
            align-items: center;
            color: white !important;
        }

        .navbar-brand img {
            margin-right: 8px;
            max-width: 30px;
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
            background-color: #d8570c;
            color: white;
        }

        .navbar-nav .dropdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .navbar-nav .nav-link .badge {
            background-color: white !important;
            color: #ff4500 !important;
            border: 1px solid #ff4500;
        }

        .navbar-nav .nav-item .nav-link .bi-heart-fill,
        .navbar-nav .nav-item .nav-link .bi-heart {
            color: white !important;
        }

        .navbar-nav .dropdown-toggle .ms-1 {
            color: white !important;
        }

        /* Login Form Specific Styles */
        .container {
            background-color: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 15px;
            max-width: 800px;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            margin: 50px auto;
            flex-grow: 1;
        }

        select.form-control:focus,
        input.form-control:focus,
        textarea.form-control:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 0.25rem rgba(249, 115, 22, 0.25);
        }

        /* PERBAIKAN UTAMA: Mengurangi Jarak Antar Kotak Input */
        .container .mb-3 {
            margin-bottom: 0.75rem !important; /* Mengurangi margin-bottom dari 1rem menjadi 0.75rem */
        }

        .container .row.mb-3 {
            margin-bottom: 0.75rem !important; /* Pastikan ini juga diterapkan pada baris yang mengandung kolom */
        }

        .container .col-md-6.mb-3 {
            margin-bottom: 0.75rem !important; /* Pastikan ini juga diterapkan pada kolom */
        }

        /* Khusus untuk bagian foto profil, jika ingin sedikit lebih dekat */
        .mb-3 label.form-label + .custom-file-input-wrapper {
            margin-top: 0.25rem; /* Memberi sedikit jarak antara label dan input kustom */
        }


        /* PERBAIKAN: Untuk select 'Pilih Peran' agar tidak ada kotak hitam */
        select.form-control {
            border: 1px solid #ced4da; /* Pastikan border standar Bootstrap */
            appearance: none; /* Hilangkan styling default OS */
            -webkit-appearance: none; /* Hilangkan styling default OS untuk Webkit */
            -moz-appearance: none; /* Hilangkan styling default OS untuk Mozilla */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e"); /* Custom arrow */
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.25rem; /* Tambah padding agar panah tidak tumpang tindih teks */
            color: #212529; /* Default text color for form controls */
        }

        /* Ini untuk memastikan bahwa panah hitam default tidak muncul saat dropdown dibuka */
        select.form-control::-ms-expand {
            display: none; /* Hilangkan panah default di IE/Edge */
        }

        /* Jika masalah kotak hitam tetap muncul pada Firefox, coba tambahkan ini: */
        @-moz-document url-prefix() {
          select.form-control {
            text-indent: 0.01px;
            text-overflow: "";
          }
        }


        /* Styling untuk input file kustom (Choose File) */
        .custom-file-input-wrapper {
            display: flex;
            align-items: center;
            border: 1px solid #ced4da; /* Border untuk area input */
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            background-color: #fff;
        }

        .custom-file-input {
            /* Sembunyikan input file bawaan */
            visibility: hidden;
            width: 0;
            height: 0;
            position: absolute;
            top: 0;
            left: 0;
        }

        .custom-file-label {
            /* Styling label agar terlihat seperti tombol */
            cursor: pointer;
            background-color: #f97316; /* Warna oranye sesuai tema Anda */
            color: white;
            border: 1px solid #f97316;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
            white-space: nowrap; /* Pastikan teks tidak patah */
            display: flex; /* Untuk mensejajarkan ikon dan teks */
            align-items: center;
            font-size: 0.875rem; /* Ukuran font lebih kecil */
        }

        .custom-file-label:hover {
            background-color: #d8570c; /* Warna oranye lebih gelap saat hover */
            border-color: #d8570c;
        }

        #file-name {
            /* Styling untuk menampilkan nama file */
            flex-grow: 1; /* Biarkan mengambil sisa ruang */
            overflow: hidden; /* Sembunyikan teks yang terlalu panjang */
            white-space: nowrap;
            text-overflow: ellipsis; /* Tampilkan ... jika terlalu panjang */
            padding-left: 0.5rem;
            color: #6c757d; /* Warna teks abu-abu */
        }

        .form-group {
            position: relative;
            margin-bottom: 0.75rem; /* Mengikuti jarak antar kotak */
        }
        .form-group i {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #888;
            z-index: 2;
            pointer-events: none;
            line-height: 1;
        }

        /* Pastikan ukuran form control konsisten */
        .form-control {
            height: calc(2.25rem + 2px); /* Default height for Bootstrap form controls */
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border-radius: 0.375rem;
        }

        /* Override padding-left untuk form-control dengan ikon agar tidak tumpang tindih */
        .form-group .form-control {
            padding-left: 2.5rem !important; /* Sedikit lebih lebar dari 40px */
        }


        .btn-primary {
            background-color: #f97316;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #d8570c;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
        }
        .password-container {
            position: relative;
            width: 100%;
        }
        .password-container .form-control {
            padding-right: 40px !important;
        }
        .password-container i.fa-lock {
            left: 12px;
        }
        .password-container .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ccc;
            z-index: 3;
        }

        p.text-center.mt-3 a {
            color: #f97316 !important;
        }
        p.text-center.mt-3 a:hover {
            color: #d8570c !important;
            text-decoration: underline;
        }

        /* Footer Styling */
        .footer {
            background-color: #FF4500;
            color: white;
            padding: 2rem 0;
            margin-top: auto;
            width: 100%;
        }

        .footer .container-fluid {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 1200px) {
            .footer .container-fluid {
                padding-left: 10rem;
                padding-right: 10rem;
            }
        }
        @media (min-width: 1400px) {
            .footer .container-fluid {
                padding-left: 15rem;
                padding-right: 15rem;
            }
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

        .facebook-icon { color: #1877F2; }
        .twitter-icon { color: #1DA1F2; }
        .youtube-icon { color: #FF0000; }
        .instagram-icon { color: #C13584; }
        .whatsapp-icon { color: #25D366; }
        .telegram-icon { color: #229ED9; }

        .footer .col-md-4 {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .footer .col-md-4:first-child {
            align-items: center;
        }
        .footer .col-md-4:nth-child(2) {
            text-align: center;
        }
        .footer .col-md-4:last-child {
            align-items: center;
        }
        .footer .col-md-4:last-child h5 {
            text-align: center;
        }
        .footer .col-md-4:last-child iframe {
            max-width: 100%;
        }

        .footer .col-md-4 h5.text-orange {
            color: white !important;
        }

        /* Bootstrap utility classes override (untuk berjaga-jaga) */
        /* Ini mungkin tidak perlu lagi karena sudah diatur di atas, tapi biarkan jika ada konflik */
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        /* .mt-3 { margin-top: 1rem !important; } -> Di override di atas */
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mt-2 { margin-top: 0.5rem !important; }
        /* .mb-3 { margin-bottom: 1rem !important; } -> Di override di atas */
    </style>
    <script>
    // Fungsi untuk menampilkan/menyembunyikan form berdasarkan peran
    function toggleForm() {
        const role = document.getElementById('role').value;
        const formPenjual = document.getElementById('form-penjual');
        const formPelanggan = document.getElementById('form-pelanggan');
        const nomorAnggotaField = document.getElementById('nomor-anggota-field');

        // Sembunyikan semua form spesifik peran dan nomor anggota
        formPenjual.style.display = 'none';
        formPelanggan.style.display = 'none';
        nomorAnggotaField.style.display = 'none';

        // Reset required attributes for role-specific fields
        document.querySelectorAll('#form-penjual input').forEach(input => input.removeAttribute('required'));
        document.querySelectorAll('#form-penjual textarea').forEach(textarea => textarea.removeAttribute('required'));
        document.querySelectorAll('#form-pelanggan input').forEach(input => input.removeAttribute('required'));
        document.querySelectorAll('#form-pelanggan textarea').forEach(textarea => textarea.removeAttribute('required'));

        if (role === 'penjual') {
            formPenjual.style.display = 'block';
            // Set required for penjual-specific fields
            document.querySelector('[name="nama_toko"]').setAttribute('required', 'required');
            document.querySelector('[name="nama_pemilik"]').setAttribute('required', 'required');
        } else if (role === 'pelanggan') {
            formPelanggan.style.display = 'block';
            nomorAnggotaField.style.display = 'block';
            // No specific required fields for pelanggan apart from common ones, as per your PHP logic.
        }
        // Jika tidak ada peran yang dipilih atau dipilih kembali ke default, semua form spesifik akan disembunyikan
    }

    // Fungsi untuk toggle visibilitas password
    function togglePasswordVisibility(id) {
        const passwordInput = document.getElementById(id);
        const toggleIcon = passwordInput.nextElementSibling; // Ikon mata adalah sibling berikutnya

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // --- Fungsi baru untuk membersihkan semua kolom ---
    function clearRegistrationForm() {
        const form = document.querySelector('form'); // Ambil elemen form
        if (form) {
            // Mereset semua input teks, email, password, textarea
            form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea').forEach(input => {
                input.value = '';
            });

            // Mereset select (dropdown) ke opsi default (misalnya "-- Pilih Peran --", "-- Pilih Jenis Kelamin --")
            form.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0; // Mengatur ulang ke opsi pertama
            });

            // Membersihkan input file dan teks kustomnya
            const fileInput = document.getElementById('foto');
            const fileNameSpan = document.getElementById('file-name');
            if (fileInput && fileNameSpan) {
                // Untuk membersihkan input type="file", kita perlu membuat ulang elemennya
                const newFileInput = fileInput.cloneNode(true);
                fileInput.parentNode.replaceChild(newFileInput, fileInput);

                // Update event listener untuk input file yang baru
                newFileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        fileNameSpan.textContent = this.files[0].name;
                    } else {
                        fileNameSpan.textContent = 'Belum ada file dipilih';
                    }
                });

                // Reset teks kustom ke default
                fileNameSpan.textContent = 'Belum ada file dipilih';
            }

            // Memastikan tampilan form spesifik peran kembali ke default setelah direset
            toggleForm();
        }
    }


    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        toggleForm(); // Initial call to set the form state
        roleSelect.addEventListener('change', toggleForm);

        // JavaScript untuk update nama file pada input kustom (pastikan ini merujuk ke elemen 'foto' yang benar setelah kemungkinan diganti)
        let fileInput = document.getElementById('foto'); // Ambil ulang elemen jika sudah diganti oleh clearRegistrationForm
        const fileNameSpan = document.getElementById('file-name');

        if (fileInput && fileNameSpan) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                } else {
                    fileNameSpan.textContent = 'Belum ada file dipilih';
                }
            });

            // Set initial file name if a file was previously uploaded and an error occurred
            <?php if (!empty($foto_name)): ?>
                fileNameSpan.textContent = "<?php echo htmlspecialchars($foto_name); ?>";
            <?php endif; ?>
        }

        // --- Tambahkan logika ini untuk memanggil clearRegistrationForm setelah sukses registrasi ---
        // Asumsikan div alert sukses memiliki class 'success-message' seperti di HTML Anda.
        const successAlert = document.querySelector('.alert.success-message');
        if (successAlert) {
            // Cek apakah pesan sukses ditampilkan (misalnya, saat halaman dimuat ulang setelah sukses)
            // Anda mungkin perlu menambahkan kondisi lain jika pesan sukses ditampilkan secara dinamis tanpa refresh halaman.
            // Misalnya, jika Anda menggunakan AJAX, panggil clearRegistrationForm di callback sukses AJAX.
            if (successAlert.style.display !== 'none' && successAlert.innerText.includes('Registrasi berhasil')) {
                 // Beri sedikit penundaan agar pengguna bisa membaca pesan sukses
                setTimeout(function() {
                    clearRegistrationForm();
                }, 100); // 100ms delay
            }
        }
    });
</script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">BUMDes</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door-fill"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="produk_pembeli.php">Produk</a></li>
                    <li class="nav-item"><a class="nav-link" href="artikel.php">Artikel</a></li>
                    <li class="nav-item"><a class="nav-link active" href="login.php">Login</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">
        <h2 class="text-center mb-4">Form Registrasi Pengguna BUMDES</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success success-message mb-3 text-center"><?= $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger error-message mb-3 text-center"><?= $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Daftar Sebagai <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="">-- Pilih Peran --</option>
                        <option value="penjual" <?php echo ($role_val == 'penjual') ? 'selected' : ''; ?>>Penjual</option>
                        <option value="pelanggan" <?php echo ($role_val == 'pelanggan') ? 'selected' : ''; ?>>Pelanggan</option>
                    </select>
                </div>
            </div>

            <div id="form-penjual" class="form-role">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Toko <span class="text-danger">*</span></label>
                        <input type="text" name="nama_toko" class="form-control" value="<?php echo $nama_toko_val; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Brand / Pemilik Usaha <span class="text-danger">*</span></label>
                        <input type="text" name="nama_pemilik" class="form-control" value="<?php echo $nama_pemilik_val; ?>">
                    </div>
                </div>
            </div>

            <div id="form-pelanggan" class="form-role">
                <div class="mb-3" id="nomor-anggota-field">
                    <label class="form-label">Nomor Anggota</label>
                    <input type="text" name="nomor_anggota_display" class="form-control" value="<?= htmlspecialchars($nomor_anggota_display); ?>" readonly>
                </div>
            </div>

            <div class="mb-3">
                <label for="foto" class="form-label">Foto Profil <span class="text-danger">*</span></label>
                <div class="custom-file-input-wrapper">
                    <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*" required>
                    <label for="foto" class="btn custom-file-label">
                        <i class="bi bi-upload me-2"></i> Pilih File
                    </label>
                    <span id="file-name" class="ms-2 text-muted">Belum ada file dipilih</span>
                </div>
                <small class="form-text text-muted">Format yang didukung: JPG, JPEG, PNG.</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" required value="<?php echo $nama_lengkap_val; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nomor HP <span class="text-danger">*</span></label>
                    <input type="text" name="no_hp" class="form-control" required value="<?php echo $no_hp_val; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Alamat <span class="text-danger">*</span></label>
                <textarea name="alamat" class="form-control" rows="4" required><?php echo $alamat_val; ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" required value="<?php echo $username_val; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?php echo $email_val; ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" class="form-control" required>
                        <span class="toggle-password fa fa-eye" onclick="togglePasswordVisibility('password')"></span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <span class="toggle-password fa fa-eye" onclick="togglePasswordVisibility('confirm_password')"></span>
                    </div>
                </div>
            </div>
            <div id="form-tambahan-umum">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?php echo $tanggal_lahir_val; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="pria" <?php echo ($jenis_kelamin_val == 'pria') ? 'selected' : ''; ?>>Pria</option>
                            <option value="wanita" <?php echo ($jenis_kelamin_val == 'wanita') ? 'selected' : ''; ?>>Wanita</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" name="register" class="btn btn-primary w-100">Daftar</button>
        </form>

        <p class="text-center mt-3">Sudah punya akun? <a href="login.php">Login di sini</a>.</p>
    </div>
    <footer class="footer py-4 text-white">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <img src="img/logo.png" alt="Logo Desa" width="80" class="mb-2">
                    <h5 class="fw-bold mt-2">DESA SINAR PETIR</h5>
                    <p>Website Resmi Pemerintah Desa Sinar Petir, Kecamatan Talang Padang, Kabupaten Tanggamus</p>
                    <div class="sosmed-icons mt-3">
                        <a href="#"><i class="bi bi-facebook facebook-icon"></i></a>
                        <a href="#"><i class="bi bi-twitter twitter-icon"></i></a>
                        <a href="#"><i class="bi bi-youtube youtube-icon"></i></a>
                        <a href="https://www.instagram.com/pekonsinarpetir_?igsh=bWpmbHY4bzV0dW1n"><i class="bi bi-instagram instagram-icon"></i></a>
                        <a href="#"><i class="bi bi-whatsapp whatsapp-icon"></i></a>
                        <a href="#"><i class="bi bi-telegram telegram-icon"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>