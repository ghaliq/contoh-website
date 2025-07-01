<?php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Cari user berdasarkan email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Hasilkan token unik dan atur kadaluarsa (misal 1 jam dari sekarang)
        $token = bin2hex(random_bytes(32)); // Token acak 64 karakter
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Simpan token dan kadaluarsa di database
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $token, $expires, $user_id);
        
        if ($update_stmt->execute()) {
            // --- SIMULASI PENGIRIMAN EMAIL ---
            // Di sini adalah bagian di mana Anda akan mengirim email ke pengguna
            // yang berisi tautan untuk mereset password mereka.
            // Contoh tautan reset: http://localhost/nama_folder_proyek/reset_password.php?token=<?php echo $token; ?>
            //
            // Karena saya tidak bisa mengakses server email, ini adalah simulasinya:
            $reset_link = "http://localhost/nama_folder_proyek/reset_password.php?token=" . $token;
            $message = "Tautan reset password telah 'dikirim' ke email Anda. " .
                       "Mohon cek inbox Anda (atau folder spam).<br>" .
                       "Untuk tujuan demonstrasi, klik tautan ini: <a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a>";
            // --- AKHIR SIMULASI ---

        } else {
            $error_message = "Terjadi kesalahan saat membuat token reset password.";
        }
    } else {
        $error_message = "Email tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
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
        .container-fluid {
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
    <div class="container-fluid">
        <h3><i class="fas fa-lock"></i> Lupa Password</h3>
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Masukkan Email Anda" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Link Reset</button>
        </form>
        <p><a href="login.php">Kembali ke Login</a></p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>