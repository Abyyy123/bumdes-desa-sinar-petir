<!DOCTYPE html>
<html>
<head>
    <title>Registrasi Admin</title>
    <style>
        body { font-family: Arial; background: #f2f2f2; }
        form {
            width: 400px; margin: auto; padding: 20px;
            background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 50px; border-radius: 10px;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box;
        }
        button {
            padding: 10px 20px; background: green; color: white;
            border: none; border-radius: 5px; cursor: pointer;
        }
    </style>
</head>
<body>
    <form action="register_admin.php" method="POST">
        <h2>Form Registrasi Admin</h2>
        <label>Nama:</label>
        <input type="text" name="nama" required>
        
        <label>Username:</label>
        <input type="text" name="username" required>
        
        <label>Email:</label>
        <input type="email" name="email" required>
        
        <label>Password:</label>
        <input type="password" name="password" required>
        
        <button type="submit">Daftar Admin</button>
    </form>
</body>
</html>
