<?php
include '../koneksi/koneksi.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM pengguna WHERE username = ? AND role = 'admin' AND status = 'aktif'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Simpan data ke session dengan nama yang konsisten
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_nama'] = $user['nama'];
            $_SESSION['admin_role'] = $user['role'];

            header("Location: dashboard_admin.php");
            exit();
        } else {
            header("Location: login_admin.php?error=1");
            exit();
        }
    } else {
        header("Location: login_admin.php?error=2");
        exit();
    }
}
?>
