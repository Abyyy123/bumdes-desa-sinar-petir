<?php
session_start();
include('../koneksi/koneksi.php'); // Ensure the path to koneksi.php is correct

// Pastikan koneksi database berhasil
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Aktifkan pelaporan error MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ensure user is logged in
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];
$query_user = "SELECT * FROM pengguna WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $query_user);
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    $user = mysqli_fetch_assoc($result_user);
    mysqli_stmt_close($stmt_user);
} else {
    die("Gagal menyiapkan statement pengguna: " . mysqli_error($conn));
}


// Ensure only admin can access this page
if (!($user['role'] === 'admin')) {
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.');window.location.href='dashboard_admin.php';</script>";
    exit;
}

// --- Admin Profile Edit Logic (from navbar) ---
// Logika ini sudah ada dan tidak terkait langsung dengan masalah artikel,
// tetapi memastikan pesan alert dan redirect yang tepat.
if (isset($_POST['simpan_profil'])) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Jangan trim password sebelum hash

    $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ? WHERE id = ?";
    $params_profil = [$nama, $username, $email, $user_id];
    $types_profil = 'sssi';

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ?, password = ? WHERE id = ?";
        $params_profil = [$nama, $username, $email, $hashed_password, $user_id];
        $types_profil = 'ssssi';
    }

    $foto_name = $user['foto'] ?? 'default.png';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "../img/foto/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $new_foto_name = uniqid() . '_' . basename($_FILES['foto']['name']);
        $target_file = $target_dir . $new_foto_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            echo "<script>alert('Foto profil tidak valid (format).');window.location.href='artikel.php';</script>";
            exit; // Keep exit here as this is a form unrelated to the main issue
        } else if ($_FILES['foto']['size'] > 5000000) {
            echo "<script>alert('Foto profil tidak valid (ukuran).');window.location.href='artikel.php';</script>";
            exit; // Keep exit here as this is a form unrelated to the main issue
        } else {
            if (file_exists($target_file)) {
                echo "<script>alert('Nama file sudah ada, mohon ganti nama file.');window.location.href='artikel.php';</script>";
                exit; // Keep exit here as this is a form unrelated to the main issue
            } else if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                if ($user['foto'] && $user['foto'] !== 'default.png' && file_exists($target_dir . $user['foto'])) {
                    unlink($target_dir . $user['foto']);
                }
                $foto_name = $new_foto_name;
                $update_profil_query = str_replace(" WHERE id = ?", ", foto = ? WHERE id = ?", $update_profil_query);
                array_splice($params_profil, count($params_profil) - 1, 0, [$foto_name]);
                $types_profil = substr_replace($types_profil, 's', strlen($types_profil) - 1, 0);
            } else {
                error_log("Gagal mengunggah foto profil. Error code: " . $_FILES['foto']['error']);
                echo "<script>alert('Gagal mengunggah foto profil. Error: " . $_FILES['foto']['error'] . "');window.location.href='artikel.php';</script>";
                exit; // Keep exit here as this is a form unrelated to the main issue
            }
        }
    }

    $stmt_profil = mysqli_prepare($conn, $update_profil_query);
    if ($stmt_profil) {
        mysqli_stmt_bind_param($stmt_profil, $types_profil, ...$params_profil);
        if (mysqli_stmt_execute($stmt_profil)) {
            $_SESSION['nama'] = $nama;
            $_SESSION['foto'] = $foto_name;
            // Refetch user data to update $user variable
            $query_refetch = "SELECT * FROM pengguna WHERE id = ?";
            $stmt_refetch = mysqli_prepare($conn, $query_refetch);
            mysqli_stmt_bind_param($stmt_refetch, 'i', $user_id);
            mysqli_stmt_execute($stmt_refetch);
            $result_refetch = mysqli_stmt_get_result($stmt_refetch);
            $user = mysqli_fetch_assoc($result_refetch);
            mysqli_stmt_close($stmt_refetch);

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='artikel.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_stmt_error($stmt_profil) . "');window.location.href='artikel.php';</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');window.location.href='artikel.php';</script>";
    }
    exit;
}

// Function to generate slug
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9 -]/', '', $string);
    $string = str_replace(' ', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// --- Add Article Logic ---
if (isset($_POST['tambah_artikel'])) {
    $judul = trim($_POST['judul']);
    $konten = trim($_POST['konten']);
    $url_berita = trim($_POST['url_berita']);
    $status = trim($_POST['status']);
    $penulis_id = $_SESSION['pengguna_id']; // The logged-in admin is the author
    $slug = generateSlug($judul);

    $gambar_utama_name = null;
    $upload_success_flag = true; // Flag to track if image upload was successful

    if (isset($_FILES['gambar_utama']) && $_FILES['gambar_utama']['error'] == 0) {
        $target_dir = "../img/artikel/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $gambar_utama_name = uniqid() . '_' . basename($_FILES['gambar_utama']['name']);
        $target_file = $target_dir . $gambar_utama_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            echo "<script>alert('Maaf, hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan untuk gambar utama.');</script>";
            $upload_success_flag = false;
            $gambar_utama_name = null;
        } elseif ($_FILES['gambar_utama']['size'] > 5000000) { // 5MB
            echo "<script>alert('Maaf, ukuran file gambar utama terlalu besar (maksimal 5MB).');</script>";
            $upload_success_flag = false;
            $gambar_utama_name = null;
        } elseif (!move_uploaded_file($_FILES['gambar_utama']['tmp_name'], $target_file)) {
            error_log("Error uploading file: " . $_FILES['gambar_utama']['error'] . " to " . $target_file);
            echo "<script>alert('Maaf, terjadi kesalahan saat mengunggah gambar utama. Kode error: " . $_FILES['gambar_utama']['error'] . "');</script>";
            $upload_success_flag = false;
            $gambar_utama_name = null;
        }
    } else if ($_FILES['gambar_utama']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors like UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE etc.
        echo "<script>alert('Terjadi kesalahan unggah gambar: " . $_FILES['gambar_utama']['error'] . "');</script>";
        $upload_success_flag = false;
        $gambar_utama_name = null;
    }

    if ($upload_success_flag) { // Proceed only if image upload was successful or no image was attempted
        // Check for duplicate slug before inserting
        $original_slug = $slug;
        $counter = 1;
        while (true) {
            $check_slug_query = "SELECT COUNT(*) FROM artikel WHERE slug = ?";
            $stmt_check_slug = mysqli_prepare($conn, $check_slug_query);
            if ($stmt_check_slug) {
                mysqli_stmt_bind_param($stmt_check_slug, 's', $slug);
                mysqli_stmt_execute($stmt_check_slug);
                mysqli_stmt_bind_result($stmt_check_slug, $count);
                mysqli_stmt_fetch($stmt_check_slug);
                mysqli_stmt_close($stmt_check_slug);
            } else {
                echo "<script>alert('Gagal menyiapkan statement cek slug: " . mysqli_error($conn) . "');</script>";
                header('Location: artikel.php'); // Redirect even if slug check fails for cleaner UX
                exit;
            }

            if ($count == 0) {
                break; // Slug is unique
            }
            $slug = $original_slug . '-' . $counter++;
        }

        $insert_query = "INSERT INTO artikel (penulis_id, judul, slug, konten, gambar_utama, url_berita, status, tanggal_publikasi, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $insert_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssss', $penulis_id, $judul, $slug, $konten, $gambar_utama_name, $url_berita, $status);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>alert('Artikel berhasil ditambahkan!');</script>";
            } else {
                echo "<script>alert('Gagal menambahkan artikel: " . mysqli_stmt_error($stmt) . "');</script>";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "<script>alert('Gagal menyiapkan statement tambah artikel: " . mysqli_error($conn) . "');</script>";
        }
    }
    header('Location: artikel.php'); // Always redirect after processing form submission
    exit;
}

// --- Edit Article Logic ---
if (isset($_POST['edit_artikel'])) {
    $id = $_POST['artikel_id'];
    $judul = trim($_POST['judul']);
    $konten = trim($_POST['konten']);
    $url_berita = trim($_POST['url_berita']);
    $status = trim($_POST['status']);
    $slug = generateSlug($judul); // Re-generate slug if title changes

    $current_gambar = $_POST['current_gambar_utama'];
    $gambar_utama_name = $current_gambar; // Inisialisasi dengan gambar yang sudah ada

    $upload_success_flag = true; // Flag for edit image upload

    // Check if a new image is uploaded
    if (isset($_FILES['gambar_utama']) && $_FILES['gambar_utama']['error'] == 0) {
        $target_dir = "../img/artikel/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $new_gambar_name = uniqid() . '_' . basename($_FILES['gambar_utama']['name']);
        $target_file = $target_dir . $new_gambar_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            echo "<script>alert('Maaf, hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan untuk gambar utama.');</script>";
            $upload_success_flag = false;
        } elseif ($_FILES['gambar_utama']['size'] > 5000000) { // 5MB
            echo "<script>alert('Maaf, ukuran file gambar utama terlalu besar (maksimal 5MB).');</script>";
            $upload_success_flag = false;
        } else {
            if (move_uploaded_file($_FILES['gambar_utama']['tmp_name'], $target_file)) {
                // Delete old image if it exists and is not default
                if ($current_gambar && $current_gambar !== 'default_artikel.png' && file_exists($target_dir . $current_gambar)) {
                    unlink($target_dir . $current_gambar);
                }
                $gambar_utama_name = $new_gambar_name; // Update nama gambar
            } else {
                error_log("Error uploading new file during edit: " . $_FILES['gambar_utama']['error'] . " to " . $target_file);
                echo "<script>alert('Maaf, terjadi kesalahan saat mengunggah gambar utama baru. Kode error: " . $_FILES['gambar_utama']['error'] . "');</script>";
                $upload_success_flag = false;
            }
        }
    } else if ($_FILES['gambar_utama']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi kesalahan unggah gambar baru: " . $_FILES['gambar_utama']['error'] . "');</script>";
        $upload_success_flag = false;
    }

    if ($upload_success_flag) { // Proceed only if image upload was successful or no new image was attempted
        // Check for duplicate slug, excluding the current article
        $original_slug = $slug;
        $counter = 1;
        while (true) {
            $query_check_slug = "SELECT COUNT(id) FROM artikel WHERE slug = ? AND id != ?";
            $stmt_check_slug = mysqli_prepare($conn, $query_check_slug);
            if ($stmt_check_slug) {
                mysqli_stmt_bind_param($stmt_check_slug, 'si', $slug, $id);
                mysqli_stmt_execute($stmt_check_slug);
                mysqli_stmt_bind_result($stmt_check_slug, $count);
                mysqli_stmt_fetch($stmt_check_slug);
                mysqli_stmt_close($stmt_check_slug);
            } else {
                echo "<script>alert('Gagal menyiapkan statement cek slug (edit): " . mysqli_error($conn) . "');</script>";
                header('Location: artikel.php');
                exit;
            }

            if ($count == 0) {
                break; // Slug is unique
            }
            $slug = $original_slug . '-' . $counter++;
        }

        $update_query = "UPDATE artikel SET judul = ?, slug = ?, konten = ?, url_berita = ?, gambar_utama = ?, status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssi', $judul, $slug, $konten, $url_berita, $gambar_utama_name, $status, $id);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>alert('Artikel berhasil diperbarui!');</script>";
            } else {
                echo "<script>alert('Gagal memperbarui artikel: " . mysqli_stmt_error($stmt) . "');</script>";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "<script>alert('Gagal menyiapkan statement edit artikel: " . mysqli_error($conn) . "');</script>";
        }
    }
    header('Location: artikel.php'); // Always redirect after processing form submission
    exit;
}

// --- Delete Article Logic ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    // Get image file name before deleting data
    $get_image_query = "SELECT gambar_utama FROM artikel WHERE id = ?";
    $stmt_get_image = mysqli_prepare($conn, $get_image_query);
    if ($stmt_get_image) {
        mysqli_stmt_bind_param($stmt_get_image, 'i', $id_hapus);
        mysqli_stmt_execute($stmt_get_image);
        $result_get_image = mysqli_stmt_get_result($stmt_get_image);
        $row_image = mysqli_fetch_assoc($result_get_image);
        $gambar_to_delete = $row_image['gambar_utama'] ?? null;
        mysqli_stmt_close($stmt_get_image);
    } else {
        echo "<script>alert('Gagal menyiapkan statement ambil gambar: " . mysqli_error($conn) . "');window.location.href='artikel.php';</script>";
        exit;
    }


    $delete_query = "DELETE FROM artikel WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            // Delete image file from server if it exists and is not default
            if ($gambar_to_delete && $gambar_to_delete !== 'default_artikel.png' && file_exists("../img/artikel/" . $gambar_to_delete)) {
                unlink("../img/artikel/" . $gambar_to_delete);
            }
            echo "<script>alert('Artikel berhasil dihapus!');window.location.href='artikel.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus artikel: " . mysqli_stmt_error($stmt_delete) . "');window.location.href='artikel.php';</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');window.location.href='artikel.php';</script>";
    }
    exit; // Pastikan tidak ada eksekusi kode lebih lanjut setelah hapus
}

// --- Pagination Setup ---
$articles_per_page = 10; // Jumlah artikel per halaman

// Hitung total jumlah artikel
$total_articles_query = "SELECT COUNT(*) AS total FROM artikel";
$result_total_articles = mysqli_query($conn, $total_articles_query);
$row_total_articles = mysqli_fetch_assoc($result_total_articles);
$total_articles = $row_total_articles['total'];

// Hitung total halaman
$total_pages = ceil($total_articles / $articles_per_page);

// Tentukan halaman saat ini
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// Hitung offset
$offset = ($current_page - 1) * $articles_per_page;

// --- Query to retrieve articles with pagination ---
$sql_artikel = "SELECT
                    a.id,
                    a.judul,
                    a.slug,
                    a.konten,
                    a.url_berita,
                    a.tanggal_publikasi,
                    a.gambar_utama,
                    a.status,
                    u.nama AS nama_penulis
                FROM
                    artikel a
                LEFT JOIN
                    pengguna u ON a.penulis_id = u.id
                ORDER BY
                    a.created_at DESC
                LIMIT ? OFFSET ?";
$stmt_artikel = mysqli_prepare($conn, $sql_artikel);
if ($stmt_artikel) {
    mysqli_stmt_bind_param($stmt_artikel, 'ii', $articles_per_page, $offset);
    mysqli_stmt_execute($stmt_artikel);
    $result_artikel = mysqli_stmt_get_result($stmt_artikel);
} else {
    die("Gagal menyiapkan statement artikel: " . mysqli_error($conn));
}


// Status badge mapping for display
$status_badge_map = [
    'draft' => ['text' => 'Draft', 'class' => 'badge-secondary'],
    'publikasi' => ['text' => 'Publikasi', 'class' => 'badge-success'],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Artikel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #F8FBFD;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #343A40;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #E0F2F7;
            min-height: 100vh;
            padding: 20px 0;
            color: #343A40;
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: #343A40;
            margin-bottom: 30px;
        }
        .sidebar a {
            color: #343A40;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #CCEEF5;
            color: #343A40;
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #343A40;
        }
        .sidebar .submenu:hover {
            color: #343A40;
        }
        .sidebar .menu-text {
            margin-left: 10px;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .sidebar.collapsed .submenu {
            display: none;
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: #343A40;
            margin-left: 20px;
            font-size: 20px;
        }

        .card-dashboard {
            border-radius: 10px;
            background-color: #F8FBFD;
            color: #343A40;
        }
        .card {
            background-color: #F0F4F7;
            color: #343A40;
            border: none;
            border-radius: 8px;
        }
        .card-header {
            background-color: #A7D9ED;
            color: #343A40;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #A7D9ED;
            border-color: #A7D9ED;
            color: #343A40;
        }
        .btn-primary:hover {
            background-color: #8FD1E8;
            border-color: #8FD1E8;
            color: #343A40;
        }
        .btn-warning {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #343A40;
        }
        .btn-warning:hover {
            background-color: #E6C200;
            border-color: #E6C200;
        }
        .btn-danger {
            background-color: #FF6347;
            border-color: #FF6347;
            color: #F8FBFD;
        }
        .btn-danger:hover {
            background-color: #E0523C;
            border-color: #E0523C;
        }
        .btn-secondary {
            background-color: #D1C4E9;
            border-color: #D1C4E9;
            color: #343A40;
        }
        .btn-secondary:hover {
            background-color: #BCAFE0;
            border-color: #BCAFE0;
        }
        .form-control {
            background-color: #FFFFFF;
            border-color: #A7D9ED;
            color: #343A40;
        }
        .form-control:focus {
            border-color: #8FD1E8;
            box-shadow: 0 0 0 0.25rem rgba(167, 217, 237, 0.25);
        }
        .table thead th {
            background-color: #E0F2F7;
            color: #343A40;
            border-bottom: 2px solid #A7D9ED;
        }
        .table tbody tr:nth-of-type(odd) {
            background-color: #F8FBFD;
        }
        .table tbody tr:nth-of-type(even) {
            background-color: #F0F4F7;
        }

        .modal-content {
            background-color: #F8FBFD;
            color: #343A40;
        }
        .modal-header {
            background-color: #E0F2F7;
            border-bottom: 1px solid #CCEEF5;
        }
        .modal-title {
            color: #343A40;
        }
        .modal-footer {
            border-top: 1px solid #CCEEF5;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
        }
        .profile-section {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .profile-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }
        .profile-menu {
            position: absolute;
            top: 60px;
            right: 0;
            background: #F8FBFD;
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            color: #343A40;
        }
        .profile-menu h5 {
            margin-top: 0;
        }
        .profile-menu p {
            margin: 0;
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: #A7D9ED;
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: #F8FBFD;
            padding: 20px;
            border-radius: 10px;
            color: #343A40;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .alert-info {
            background-color: #D1C4E9;
            color: #343A40;
            border-color: #BCAFE0;
        }
        .sidebar .nav-link.active {
            background-color: #CCEEF5;
            color: #343A40;
        }

        /* Status Badge Styling */
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            white-space: nowrap;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }
        .badge-secondary { background-color: #6c757d; }
        .badge-success { background-color: #28a745; }

        /* Table Styling */
        .table-artikel {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-artikel thead { display: table-header-group !important; }
        .table-artikel tbody { display: table-row-group !important; }
        .table-artikel tr { display: table-row !important; }
        .table-artikel th,
        .table-artikel td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-artikel th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Column width adjustments */
        .table-artikel td:nth-child(1), .table-artikel th:nth-child(1) { width: 3% !important; min-width: 25px !important; text-align: center !important; white-space: nowrap !important; }
        .table-artikel td:nth-child(2), .table-artikel th:nth-child(2) { width: 4% !important; min-width: 35px !important; text-align: center !important; white-space: nowrap !important; }
        .table-artikel td:nth-child(3), .table-artikel th:nth-child(3) { width: 15% !important; min-width: 120px !important; white-space: normal !important; }
        .table-artikel td:nth-child(4), .table-artikel th:nth-child(4) { width: 12% !important; min-width: 100px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
        .table-artikel td:nth-child(5), .table-artikel th:nth-child(5) { width: 15% !important; min-width: 120px !important; white-space: normal !important; overflow: hidden !important; text-overflow: ellipsis !important; }
        .table-artikel td:nth-child(6), .table-artikel th:nth-child(6) { width: 10% !important; min-width: 80px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
        .table-artikel td:nth-child(7), .table-artikel th:nth-child(7) { width: 8% !important; min-width: 60px !important; text-align: center !important; }
        .table-artikel td:nth-child(8), .table-artikel th:nth-child(8) { width: 10% !important; min-width: 80px !important; white-space: nowrap !important; }
        .table-artikel td:nth-child(9), .table-artikel th:nth-child(9) { width: 10% !important; min-width: 80px !important; white-space: nowrap !important; }
        .table-artikel td:nth-child(10), .table-artikel th:nth-child(10) { width: 5% !important; min-width: 60px !important; text-align: center !important; white-space: nowrap !important; }
        .table-artikel td:nth-child(11), .table-artikel th:nth-child(11) { width: 8% !important; min-width: 90px !important; text-align: center !important; white-space: normal !important; display: flex !important; flex-direction: column !important; justify-content: center !important; align-items: center !important; gap: 3px !important; }

        /* Action button adjustments */
        .table-artikel .btn-sm {
            padding: 0.1rem 0.3rem !important;
            font-size: 0.7rem !important;
            white-space: nowrap !important;
            width: 100% !important;
            max-width: 60px !important;
        }
        .table-artikel .btn-info { background-color: #17a2b8 !important; border-color: #17a2b8 !important; color: white !important; }
        .table-artikel .btn-warning { background-color: #ffc107 !important; border-color: #ffc107 !important; color: #212529 !important; }
        .table-artikel .btn-danger { background-color: #dc3545 !important; border-color: #dc3545 !important; color: white !important; }

        .table-responsive { overflow-x: auto !important; width: 100% !important; border: 1px solid #dee2e6 !important; border-radius: 0.25rem !important; }

        /* Media queries for responsiveness */
        @media (max-width: 1200px) {
            .table-artikel th, .table-artikel td { font-size: 0.7rem !important; padding: 0.25rem 0.45rem !important; }
            .table-artikel td:nth-child(3) { min-width: 100px !important; }
            .table-artikel td:nth-child(4) { min-width: 80px !important; }
            .table-artikel td:nth-child(5) { min-width: 100px !important; }
            .table-artikel td:nth-child(6) { min-width: 70px !important; }
            .table-artikel td:nth-child(8) { min-width: 70px !important; }
            .table-artikel td:nth-child(9) { min-width: 70px !important; }
            .table-artikel td:nth-child(11) { width: 10% !important; min-width: 100px !important; }
        }

        @media (max-width: 992px) {
            .table-artikel th, .table-artikel td { font-size: 0.65rem !important; padding: 0.2rem 0.35rem !important; }
            .table-artikel td:nth-child(3) { min-width: 80px !important; }
            .table-artikel td:nth-child(4) { min-width: 60px !important; }
            .table-artikel td:nth-child(5) { min-width: 80px !important; }
            .table-artikel td:nth-child(6) { min-width: 60px !important; }
            .table-artikel td:nth-child(8) { min-width: 60px !important; }
            .table-artikel td:nth-child(9) { min-width: 60px !important; }
            .table-artikel td:nth-child(11) { width: 12% !important; min-width: 90px !important; }
            .table-artikel .btn-sm { max-width: 50px !important; }
        }

        @media (max-width: 767px) {
            .table-artikel th, .table-artikel td { font-size: 0.6rem !important; padding: 0.15rem 0.25rem !important; }
            .table-artikel td:nth-child(3) { min-width: 70px !important; }
            .table-artikel td:nth-child(4) { min-width: 50px !important; }
            .table-artikel td:nth-child(5) { min-width: 70px !important; }
            .table-artikel td:nth-child(6) { min-width: 50px !important; }
            .table-artikel td:nth-child(8) { min-width: 50px !important; }
            .table-artikel td:nth-child(9) { min-width: 50px !important; }
            .table-artikel td:nth-child(11) { width: 15% !important; min-width: 70px !important; }
            .table-artikel .btn-sm { font-size: 0.55rem !important; padding: 0.08rem 0.15rem !important; max-width: 40px !important; }
        }

        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .page-item .page-link {
            color: #343A40;
            background-color: #E0F2F7;
            border: 1px solid #A7D9ED;
        }
        .page-item.active .page-link {
            background-color: #A7D9ED;
            border-color: #A7D9ED;
            color: #343A40;
        }
        .page-item.disabled .page-link {
            color: #6C757D;
            background-color: #F0F4F7;
            border-color: #CED4DA;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background-color: #E0F2F7;">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline" style="color: #343A40;">Profil</span> </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama'] ?? 'Pengguna'); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username'] ?? '-'); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email'] ?? '-'); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                    <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                    <a class="btn btn-link text-danger p-0 d-block" href="../logout.php">Logout</a>
                </div>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if (!empty($user['foto'])) : ?>
                            <img src="../img/foto/<?= htmlspecialchars($user['foto']); ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                        <small class="form-text text-muted">Maksimal 5MB, format JPG, JPEG, PNG, GIF.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_profil" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <h4>&nbsp;</h4>
        <li class="nav-item">
            <a class="nav-link" href="dashboard_admin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span class="ml-2">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenBUMDes" role="button" aria-expanded="false" aria-controls="manajemenBUMDes">
                <i class="fas fa-building-columns"></i>
                <span class="ml-2">Manajemen BUMDes</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenBUMDes">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="manajemen.php?type=informasi">Informasi BUMDes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="manajemen.php?type=unit">Unit Usaha</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPengguna" role="button" aria-expanded="false" aria-controls="manajemenPengguna">
                <i class="fas fa-user-gear"></i>
                <span class="ml-2">Manajemen Pengguna</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenPengguna">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pengguna.php?type=admin">Data Admin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pengguna.php?type=penjual_pelanggan">Data Pengguna</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPelanggan" role="button" aria-expanded="false" aria-controls="manajemenPelanggan">
                <i class="fas fa-user-friends"></i>
                <span class="ml-2">Manajemen Pelanggan</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenPelanggan">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pelanggan.php">Daftar Pelanggan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pelanggan_persetujuan.php">Persetujuan Pelanggan</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPenjual" role="button" aria-expanded="false" aria-controls="manajemenPenjual">
                <i class="fas fa-store"></i>
                <span class="ml-2">Manajemen Penjual</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenPenjual">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="penjual.php">Daftar Penjual</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="penjual_persetujuan.php">Persetujuan Penjual</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenKurir" role="button" aria-expanded="false" aria-controls="manajemenKurir">
                <i class="fas fa-truck"></i>
                <span class="ml-2">Manajemen Kurir</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenKurir">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="kurir.php">Daftar Kurir</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="kurir_persetujuan.php">Persetujuan Kurir</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="anggota.php">
                <i class="fas fa-people-group"></i>
                <span class="ml-2">Manajemen Anggota</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="false" aria-controls="manajemenEcommerce">
                <i class="fas fa-shopping-cart"></i>
                <span class="ml-2">E-commerce</span>
                <i class="fas fa-caret-down float-right"></i>
            </a>
            <div class="collapse" id="manajemenEcommerce">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link submenu" href="kategori_produk.php">Kategori Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="produk.php">Data Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pesanan.php">Data Pesanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pembayaran.php">Data Pembayaran</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="pengiriman.php">Data Pengiriman</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="ulasan_produk.php">Ulasan Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="diskon.php">Diskon</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link submenu" href="wishlist.php">Wishlist</a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="pengembalian_barang.php">
                <i class="fas fa-undo-alt"></i>
                <span class="ml-2">Pengembalian Barang</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="artikel.php">
                <i class="fas fa-newspaper"></i>
                <span class="ml-2">Manajemen Artikel</span>
            </a>
        </li>
        <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <h2 class="mb-4">Manajemen Artikel</h2>

        <div class="mb-3">
            <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahArtikelModal">Tambah Artikel Baru</button>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                Daftar Artikel
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-artikel">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Slug</th>
                                <th>Konten</th>
                                <th>URL Berita</th>
                                <th>Gambar</th>
                                <th>Penulis</th>
                                <th>Tgl. Publikasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $offset + 1; // Start numbering from the correct offset
                            if (mysqli_num_rows($result_artikel) > 0) {
                                while ($row = mysqli_fetch_assoc($result_artikel)) {
                                    $status_info = $status_badge_map[$row['status']] ?? ['text' => 'Tidak Diketahui', 'class' => 'badge-secondary'];
                                    $konten_singkat = strip_tags($row['konten']);
                                    $konten_singkat = mb_substr($konten_singkat, 0, 100);
                                    if (mb_strlen($konten_singkat) >= 100) {
                                        $konten_singkat .= '...';
                                    }
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['judul'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['slug'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($konten_singkat ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($row['url_berita'])) : ?>
                                                <a href="<?= htmlspecialchars($row['url_berita']); ?>" target="_blank" class="btn btn-sm btn-info">Lihat Link</a>
                                            <? else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['gambar_utama'])) : ?>
                                                <img src="../img/artikel/<?= htmlspecialchars($row['gambar_utama']); ?>" alt="Gambar" style="width: 50px; height: auto;">
                                            <? else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['nama_penulis'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_publikasi'] ?? ''))); ?></td>
                                        <td><span class="badge <?= $status_info['class']; ?> status-badge"><?= htmlspecialchars($status_info['text']); ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info mb-1 edit-artikel-btn" data-toggle="modal" data-target="#editArtikelModal"
                                                data-id="<?= htmlspecialchars($row['id']); ?>"
                                                data-judul="<?= htmlspecialchars($row['judul']); ?>"
                                                data-konten="<?= htmlspecialchars($row['konten']); ?>"
                                                data-url_berita="<?= htmlspecialchars($row['url_berita']); ?>"
                                                data-gambar_utama="<?= htmlspecialchars($row['gambar_utama']); ?>"
                                                data-status="<?= htmlspecialchars($row['status']); ?>">
                                                Edit
                                            </button>
                                            <a href="artikel.php?action=hapus&id=<?= htmlspecialchars($row['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus artikel ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='11' class='text-center'>Tidak ada artikel.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content="center">
                        <?php if ($current_page > 1) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php else : ?>
                            <li class="page-item disabled">
                                <span class="page-link" aria-hidden="true">&laquo;</span>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php else : ?>
                            <li class="page-item disabled">
                                <span class="page-link" aria-hidden="true">&raquo;</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="tambahArtikelModal" tabindex="-1" aria-labelledby="tambahArtikelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahArtikelModalLabel">Tambah Artikel Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="judul_tambah">Judul Artikel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul_tambah" name="judul" required>
                    </div>
                    <div class="form-group">
                        <label for="konten_tambah">Konten Artikel</label>
                        <textarea class="form-control" id="konten_tambah" name="konten" rows="8"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="gambar_utama_tambah">Gambar Artikel (opsional)</label>
                        <input type="file" class="form-control-file" id="gambar_utama_tambah" name="gambar_utama" accept="image/*">
                        <small class="form-text text-muted">Maksimal 5MB, format JPG, JPEG, PNG, GIF.</small>
                    </div>
                    <div class="form-group">
                        <label for="url_berita_tambah">Link Berita (URL)</label>
                        <input type="url" class="form-control" id="url_berita_tambah" name="url_berita" placeholder="Contoh: https://example.com/berita-terbaru">
                    </div>
                    <div class="form-group">
                        <label for="status_tambah">Status</label>
                        <select class="form-control" id="status_tambah" name="status">
                            <option value="draft">Draft</option>
                            <option value="publikasi">Diterbitkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_artikel" class="btn btn-primary">Simpan Artikel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editArtikelModal" tabindex="-1" aria-labelledby="editArtikelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editArtikelModalLabel">Edit Artikel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="artikel_id_edit" name="artikel_id">
                    <input type="hidden" id="current_gambar_utama_edit" name="current_gambar_utama">
                    <div class="form-group">
                        <label for="judul_edit">Judul Artikel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul_edit" name="judul" required>
                    </div>
                    <div class="form-group">
                        <label for="konten_edit">Konten Artikel</label>
                        <textarea class="form-control" id="konten_edit" name="konten" rows="8"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="gambar_utama_edit">Gambar Artikel</label><br>
                        <div id="gambar_preview_container_edit" style="display: none; margin-bottom: 10px;">
                            <img id="gambar_preview_edit" src="" alt="Gambar Utama" style="max-width: 150px; height: auto;">
                        </div>
                        <input type="file" class="form-control-file" id="gambar_utama_edit" name="gambar_utama" accept="image/*">
                        <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah gambar. Maksimal 5MB, format JPG, JPEG, PNG, GIF.</small>
                    </div>
                    <div class="form-group">
                        <label for="url_berita_edit">Link Berita (URL)</label>
                        <input type="url" class="form-control" id="url_berita_edit" name="url_berita" placeholder="Contoh: https://example.com/berita-terbaru">
                    </div>
                    <div class="form-group">
                        <label for="status_edit">Status</label>
                        <select class="form-control" id="status_edit" name="status">
                            <option value="draft">Draft</option>
                            <option value="publikasi">Diterbitkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_artikel" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<footer class="text-center py-3 mt-auto" style="background-color: #E0F2F7; color: #343A40; position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" style="color: #343A40;">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    $(document).ready(function() {
        // Handle "Tambah Artikel Baru" modal reset
        $('#tambahArtikelModal').on('show.bs.modal', function (event) {
            var modal = $(this);
            var form = modal.find('form');
            form[0].reset(); // Reset form fields
            // Ensure any preview or specific settings for add modal are reset
            $('#gambar_utama_tambah').prop('required', true); // Gambar wajib diisi saat tambah baru, adjust as needed
        });

        // Handle "Edit Artikel" modal population
        $('#editArtikelModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var id = button.data('id');
            var judul = button.data('judul');
            var konten = button.data('konten');
            var url_berita = button.data('url_berita');
            var gambar_utama = button.data('gambar_utama');
            var status = button.data('status');

            var modal = $(this);
            modal.find('.modal-title').text('Edit Artikel');

            // Populate form fields
            modal.find('#artikel_id_edit').val(id);
            modal.find('#judul_edit').val(judul);
            modal.find('#konten_edit').val(konten);
            modal.find('#url_berita_edit').val(url_berita);
            modal.find('#status_edit').val(status);
            modal.find('#current_gambar_utama_edit').val(gambar_utama);

            // Handle image preview
            if (gambar_utama) {
                $('#gambar_preview_edit').attr('src', '../img/artikel/' + gambar_utama);
                $('#gambar_preview_container_edit').show();
            } else {
                $('#gambar_preview_container_edit').hide();
                $('#gambar_preview_edit').attr('src', '');
            }
            // Make sure the file input for new image is not required for edit
            $('#gambar_utama_edit').prop('required', false);
        });

        // Optional: Clear image preview if user selects no file for edit
        $('#gambar_utama_edit').on('change', function() {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#gambar_preview_edit').attr('src', e.target.result);
                    $('#gambar_preview_container_edit').show();
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                // If no file is selected (e.g., user clears selection), hide preview but retain current_gambar_utama value
                $('#gambar_preview_container_edit').hide();
                $('#gambar_preview_edit').attr('src', '');
            }
        });
    });
</script>
</body>
</html>