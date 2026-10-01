<?php
include '../koneksi/koneksi.php'; // diperbaiki path-nya

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'admin';
    $status = 'aktif';

    $query = "INSERT INTO pengguna (nama, username, password, email, role, status)
              VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss", $nama, $username, $password, $email, $role, $status);

    if ($stmt->execute()) {
        echo "✅ Admin berhasil didaftarkan.";
    } else {
        echo "❌ Gagal mendaftarkan admin: " . $conn->error;
    }
}
?>
