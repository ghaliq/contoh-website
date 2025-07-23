<?php
// session_start(); // Baris ini dihapus karena session_start() sudah ada di db.php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

$error_message = ''; // Variabel untuk menyimpan pesan error

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Jika formulir disubmit (metode POST)
    $email = $_POST['email']; // Ambil email dari input form (bukan lagi username)
    $password = $_POST['password']; // Ambil password dari input form

    // Siapkan dan jalankan query untuk mengambil data user berdasarkan email
    $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); // Bind parameter email
    $stmt->execute(); // Jalankan query
    $result = $stmt->get_result(); // Dapatkan hasilnya

    if ($result->num_rows > 0) { // Jika ada user dengan email tersebut
        $user = $result->fetch_assoc(); // Ambil data user
        if (password_verify($password, $user['password'])) { // Verifikasi password yang dimasukkan dengan hash di database
            // Jika password cocok, set variabel sesi
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username']; // Username sekarang berperan sebagai nama tampilan
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

    // --- TAMBAHKAN KODE DEBUGGING DI SINI ---
    echo "Nilai role yang ditemukan dari database: " . $user['role'] . "<br>";
    echo "Nilai role yang disimpan di sesi: " . $_SESSION['role'] . "<br>";
    // ----------------------------------------

            // Arahkan pengguna berdasarkan perannya
            if ($user['role'] === 'admin') {
                header("Location: dasboard-admin.php"); // Redirect ke dashboard admin jika admin
            } else {
                header("Location: index.php"); // Redirect ke index jika user biasa
            }
            exit; // Hentikan eksekusi script
        } else {
            $error_message = "Email atau password salah."; // Pesan error jika password tidak cocok
        }
    } else {
        $error_message = "Email atau password salah."; // Pesan error jika email tidak ditemukan
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
            margin-top: -130px;
            padding: 25px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
            text-align: center;
            width: 100%;
            max-width: 100%; 
            box-sizing: border-box; /* Memastikan padding tidak menambah lebar total */
        }
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h3 {
            margin-bottom: 30px;
            color: #2c5530;
            font-weight: bold;
        }
        .form-control {
            border-radius: 8px;
            border-color: #ced4da;
            padding: 12px;
        }
        .form-control:focus {
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
        <h3 style="color: white; margin-bottom: 10px;"><i class="fas fa-sign-in-alt"></i> Selamat datang di website Sistem Monitoring DBD Pontianak</h3>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;"> Silakan masuk ke akun Anda.</p>
    </div>
    <div class="login-container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Login</button>
        </form>
        <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>