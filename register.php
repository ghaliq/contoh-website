<?php
// session_start(); // Baris ini dihapus karena session_start() sudah ada di db.php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

$message = ''; // Variabel untuk menyimpan pesan

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Jika formulir disubmit
    $username = $_POST['username']; // Ini akan menjadi nama tampilan
    $email = $_POST['email']; // Email untuk login
    $password = $_POST['password']; // Password
    $role = 'user'; // Atur role secara default menjadi 'user' untuk setiap pendaftaran baru

    // Hash password sebelum menyimpannya ke database untuk keamanan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Cek apakah email atau username sudah ada di database
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check_stmt->bind_param("ss", $email, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) { // Jika email atau username sudah terdaftar
        $existing_user = $check_result->fetch_assoc();
        if ($existing_user['email'] === $email) {
            $message = "Email sudah terdaftar. Silakan gunakan email lain.";
        } else {
            $message = "Nama pengguna sudah terdaftar. Silakan gunakan nama lain.";
        }
    } else {
        // Siapkan dan jalankan query untuk memasukkan user baru
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

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
            background: linear-gradient(135deg, #f0f0f0 0%, #ffffff 100%);
            display: flex;
            flex-direction: column; /* Mengubah arah flex menjadi kolom */
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }
        .welcome-box { /* Menambahkan kelas baru untuk styling kotak selamat datang */
            margin-bottom: 25px;
            padding: 25px;
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
            text-align: center;
            width: 100%;
            max-width: 80%; 
            box-sizing: border-box; /* Memastikan padding tidak menambah lebar total */
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
    <div class="welcome-box">
        <h3 style="color: white; margin-bottom: 10px;"><i class="fas fa-user-plus"></i> Selamat datang di website Sistem Monitoring DBD Pontianak</h3>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">Daftar akun baru Anda untuk memulai.</p>
    </div>
    <div class="register-container">
        <h3>Register</h3> <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'berhasil') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Nama Lengkap" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
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