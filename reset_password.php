<?php
include 'db.php';

$message = '';
$error_message = '';
$token_valid = false;
$user_id = null;

if (isset($_GET['token'])) {
    $token = htmlspecialchars(trim($_GET['token']));
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $token_valid = true;
    } else {
        $error_message = "Token reset password tidak valid atau sudah kadaluarsa.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = htmlspecialchars(trim($_POST['token']));
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $token_valid = true;

        if ($new_password !== $confirm_new_password) {
            $error_message = "Password baru dan konfirmasi password tidak cocok.";
        } elseif (strlen($new_password) < 8 || !preg_match("#[0-9]+#", $new_password) || !preg_match("#[a-zA-Z]+#", $new_password) || !preg_match("/[^\w\d\s]/", $new_password)) {
            $error_message = "Password baru minimal 8 karakter dan mengandung huruf, angka, dan simbol.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                session_regenerate_id(true);
                $message = "Password Anda berhasil diatur ulang. Silakan <a href='login.php'>Login</a>.";
            } else {
                $error_message = "Terjadi kesalahan saat mengatur ulang password.";
            }
        }
    } else {
        $error_message = "Token reset password tidak valid atau sudah kadaluarsa. Silakan coba lagi proses lupa password.";
    }
} else {
    $error_message = "Token reset password tidak ditemukan.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        <h3><i class="fas fa-key"></i> Reset Password</h3>
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

        <?php if ($token_valid && empty($message)): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-3">
                    <input type="password" name="new_password" class="form-control" placeholder="Password Baru" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="confirm_new_password" class="form-control" placeholder="Konfirmasi Password Baru" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Atur Ulang Password</button>
            </form>
        <?php elseif (empty($message)): ?>
            <p>Silakan kembali ke halaman <a href="forgot_password.php">Lupa Password</a> untuk mencoba lagi.</p>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
             <p><a href="login.php">Kembali ke Login</a></p>
        <?php endif; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>