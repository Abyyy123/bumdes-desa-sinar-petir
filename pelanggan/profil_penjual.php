<?php
// Pastikan path koneksi Anda benar.
// Contoh: Jika profil_penjual.php ada di pelanggan/, dan koneksi.php di koneksi/
include '../koneksi/koneksi.php';

// Pastikan parameter ID penjual ada di URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Penjual tidak valid.");
}

$penjual_id = $_GET['id'];
$penjual_data = null;

// Query untuk mengambil data penjual dari tabel 'penjual'
// Menggunakan 'pengguna_id' sebagai kolom ID di tabel 'penjual'
$sql = "SELECT pengguna_id, nama_pemilik, nama_toko, email, username, foto,
               deskripsi_toko, alamat, nomor_telepon, status, tanggal_lahir, jenis_kelamin,
               created_at, updated_at
        FROM penjual
        WHERE pengguna_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $penjual_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $penjual_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    // Tangani error jika prepared statement gagal
    die("Error saat menyiapkan query: " . mysqli_error($conn));
}

// Jika penjual tidak ditemukan
if (!$penjual_data) {
    die("Profil penjual tidak ditemukan.");
}

// Tentukan path default untuk foto profil jika tidak ada
$foto_profil_path = !empty($penjual_data['foto_profil']) ? htmlspecialchars($penjual_data['foto_profil']) : '../img/foto/penjual.jpeg'; // Sesuaikan path default image Anda
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Penjual: <?php echo htmlspecialchars($penjual_data['nama_toko'] ?? $penjual_data['username']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-header img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #007bff;
            margin-bottom: 20px;
        }
        .profile-details h4 {
            color: #007bff;
            margin-top: 20px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .profile-details p {
            margin-bottom: 8px;
        }
        .profile-actions {
            margin-top: 30px;
            text-align: center;
        }

        /* Gaya baru untuk tombol chat */
        .custom-chat-button-icon {
            font-size: 1.1rem; /* Ukuran teks sedikit lebih besar */
            padding: 12px 30px; /* Padding lebih banyak untuk tombol yang lebih besar */
            border-radius: 25px; /* Sudut membulat */
            transition: background-color 0.3s ease, transform 0.2s ease; /* Efek hover halus */
            display: inline-flex; /* Memungkinkan ikon dan teks duduk dengan rapi */
            align-items: center; /* Meratakan ikon dan teks secara vertikal */
            justify-content: center; /* Menengahkan konten secara horizontal */
            background-color: #007bff; /* Warna biru Bootstrap primary */
            border-color: #007bff;
            color: #fff; /* Warna teks putih */
            text-decoration: none; /* Hilangkan garis bawah default untuk link */
        }

        .custom-chat-button-icon i {
            margin-right: 8px; /* Jarak antara ikon dan teks */
        }

        .custom-chat-button-icon:hover {
            background-color: #0056b3; /* Biru lebih gelap saat hover */
            border-color: #0056b3;
            transform: translateY(-2px); /* Sedikit naik saat hover */
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); /* Bayangan halus saat hover */
        }
    </style>
</head>
<body>
    <div class="container profile-container">
        <div class="profile-header">
            <img src="<?php echo $foto_profil_path; ?>" alt="Foto Profil <?php echo htmlspecialchars($penjual_data['nama_toko'] ?? $penjual_data['username']); ?>">
            <h1><?php echo htmlspecialchars($penjual_data['nama_toko'] ?? $penjual_data['username']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($penjual_data['nama_pemilik']); ?></p>
        </div>

        <div class="profile-details">
            <h4>Informasi Kontak</h4>
            <p><strong>Alamat:</strong> <?php echo htmlspecialchars($penjual_data['alamat']); ?></p>
            <h4>Tentang Toko</h4>
            <p><strong>Deskripsi:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($penjual_data['deskripsi_toko'])); ?></p>
            <p><strong>Status Toko:</strong> <?php echo htmlspecialchars($penjual_data['status']); ?></p>

            <h4>Informasi Lainnya</h4>
            <p><strong>Tanggal Lahir Pemilik:</strong> <?php echo htmlspecialchars($penjual_data['tanggal_lahir']); ?></p>
            <p><strong>Jenis Kelamin Pemilik:</strong> <?php echo htmlspecialchars($penjual_data['jenis_kelamin']); ?></p>
            <p><strong>Akun dibuat:</strong> <?php echo date('d M Y, H:i', strtotime($penjual_data['created_at'])); ?></p>
        </div>

        <div class="profile-actions">
            <a href="chat.php?receiver_id=<?php echo htmlspecialchars($penjual_data['pengguna_id']); ?>" class="custom-chat-button-icon">
                <i class="fas fa-comment-dots mr-2"></i> Chat Penjual Ini
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Tutup koneksi database
mysqli_close($conn);
?>