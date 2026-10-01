<?php
include('../koneksi/koneksi.php');

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    $sql = "SELECT * FROM pengguna WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            // Di sini bisa ditambahkan logika kirim email / token reset password
            $success_message = "Email ditemukan! Silakan cek email Anda untuk instruksi reset password.";
        } else {
            $error_message = "Email tidak ditemukan dalam sistem.";
        }
    } else {
        // Debugging log jika query gagal
        $error_message = "Terjadi kesalahan saat memeriksa email: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 450px;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h3 class="text-center mb-4">Lupa Password</h3>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message; ?></div>
    <?php elseif ($success_message): ?>
        <div class="alert alert-success"><?= $success_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="email" class="form-label">Masukkan Email Anda</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Kirim Permintaan</button>
    </form>

    <p class="text-center mt-3"><a href="../login.php">← Kembali ke Login</a></p>
</div>

</body>
</html>
