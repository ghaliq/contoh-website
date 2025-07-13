<?php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

// Autentikasi: Hanya user yang login yang bisa mengakses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error_message = '';

// Ambil data user dari database
$stmt = $conn->prepare("SELECT username, email, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'];
    $new_email = $_POST['email'];
    $current_password = $_POST['current_password']; // Untuk verifikasi saat ganti password/email
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // Verifikasi password saat ini
    if (!password_verify($current_password, $user_data['password'])) {
        $error_message = "Password saat ini salah.";
    } else {
        // Update Nama dan Email
        if ($new_username !== $user_data['username'] || $new_email !== $user_data['email']) {
            // Cek duplikasi email baru (jika diubah)
            if ($new_email !== $user_data['email']) {
                $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_email_stmt->bind_param("si", $new_email, $user_id);
                $check_email_stmt->execute();
                $check_email_result = $check_email_stmt->get_result();
                if ($check_email_result->num_rows > 0) {
                    $error_message = "Email baru sudah terdaftar untuk pengguna lain.";
                }
            }

            if (empty($error_message)) {
                $update_profile_stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $update_profile_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
                if ($update_profile_stmt->execute()) {
                    $_SESSION['username'] = $new_username; // Update sesi
                    $_SESSION['email'] = $new_email; // Update sesi
                    $message .= "Profil berhasil diperbarui. ";
                    // Refresh data user setelah update
                    $stmt->execute();
                    $user_data = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message .= "Gagal memperbarui profil. ";
                }
            }
        }

        // Update Password
        if (!empty($new_password)) {
            if ($new_password !== $confirm_new_password) {
                $error_message .= "Password baru dan konfirmasi password tidak cocok.";
            } elseif (strlen($new_password) < 6) { // Contoh: minimal 6 karakter
                $error_message .= "Password baru minimal 6 karakter.";
            } else {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_password_stmt->bind_param("si", $hashed_new_password, $user_id);
                if ($update_password_stmt->execute()) {
                    $message .= "Password berhasil diperbarui. ";
                    // Refresh data user setelah update
                    $stmt->execute();
                    $user_data = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message .= "Gagal memperbarui password. ";
                }
            }
        }

        if (empty($message) && empty($error_message)) {
            $error_message = "Tidak ada perubahan yang disimpan.";
        }
    }
}
$currentPage = 'profile';
include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container-fluid {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            margin: 50px auto;
        }
        h3 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c5530;
            font-weight: bold;
        }
        .form-control {
            border-radius: 8px;
            border-color: #ced4da;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #1a7037;
            box-shadow: 0 0 0 0.25rem rgba(26, 112, 55, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            background: linear-gradient(45deg, #1a7037, #2c5530);
        }
        .btn-secondary {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h3><i class="fas fa-user-edit"></i> Edit Profil Anda</h3>
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
                <label for="username" class="form-label">Nama Lengkap</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
            </div>
            
            <hr>
            <p class="text-muted">Isi bagian ini hanya jika Anda ingin mengubah password.</p>
            <div class="mb-3">
                <label for="current_password" class="form-label">Password Saat Ini (Wajib untuk perubahan profil/email/password)</label>
                <input type="password" name="current_password" id="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Password Baru</label>
                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
            </div>
            <div class="mb-3">
                <label for="confirm_new_password" class="form-label">Konfirmasi Password Baru</label>
                <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dasboard-admin.php' : 'index.php'; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>