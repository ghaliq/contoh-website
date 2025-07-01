<?php
include 'db.php'; // Memasukkan koneksi database

$message = ''; // Variabel untuk menyimpan pesan

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Jika formulir disubmit
    $username = $_POST['username']; // Ambil username
    $password = $_POST['password']; // Ambil password
    $role = 'user'; // Atur role secara default menjadi 'user'

    // Hash password sebelum menyimpannya ke database untuk keamanan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Cek apakah username sudah ada di database
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) { // Jika username sudah terdaftar
        $message = "Username sudah terdaftar. Silakan gunakan username lain.";
    } else {
        // Siapkan dan jalankan query untuk memasukkan user baru
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        if ($stmt->execute()) { // Jika berhasil disimpan
            $message = "Registrasi berhasil! Silakan <a href='login.php'>Login</a>.";
        } else { // Jika terjadi error
            $message = "Terjadi kesalahan saat registrasi: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        h3 {
            margin-bottom: 30px;
            color: #2c5530;
            font-weight: bold;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border-color: #ced4da;
            padding: 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a7037;
            box-shadow: 0 0 0 0.25rem rgba(26, 112, 55, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            background: linear-gradient(45deg, #1a7037, #2c5530);
        }
        .alert {
            margin-top: 20px;
            border-radius: 8px;
        }
        p {
            margin-top: 20px;
        }
        p a {
            color: #1a7037;
            text-decoration: none;
            font-weight: bold;
        }
        p a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h3><i class="fas fa-user-plus"></i> Register</h3>
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'berhasil') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</button>
        </form>
        <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>