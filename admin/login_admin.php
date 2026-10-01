<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; font-family: Arial; }
        .login-box {
            width: 400px; margin: 80px auto; padding: 30px;
            background: white; border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h3>Login Admin</h3>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php
                if ($_GET['error'] == 1) echo "❌ Password salah!";
                else if ($_GET['error'] == 2) echo "❌ Username tidak ditemukan atau bukan admin!";
                ?>
            </div>
        <?php endif; ?>
        <form action="proses_login_admin.php" method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success">Login</button>
        </form>
    </div>
</body>
</html>
