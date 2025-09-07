<?php
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = htmlspecialchars($_POST['action']);
    
    if ($action === 'add_user') {
        $username = htmlspecialchars(trim($_POST['username']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $role = htmlspecialchars($_POST['role']);

        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error_message = "Semua field harus diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } elseif (strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password) || !preg_match("/[^\w\d\s]/", $password)) {
            $error_message = "Kata sandi harus minimal 8 karakter dan mengandung huruf, angka, dan simbol.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $check_stmt->bind_param("ss", $email, $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error_message = "Email atau nama pengguna sudah terdaftar.";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                if ($insert_stmt->execute()) {
                    $message = "Pengguna baru berhasil ditambahkan.";
                } else {
                    $error_message = "Terjadi kesalahan saat menambahkan pengguna baru: " . $conn->error;
                }
            }
        }

    } elseif ($action === 'change_role') {
        $user_id_to_manage = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $current_admin_id = $_SESSION['user_id'];

        if ($user_id_to_manage == $current_admin_id) {
            $error_message = "Anda tidak dapat mengubah atau menghapus akun Anda sendiri.";
        } else {
            $new_role = htmlspecialchars($_POST['new_role']);
            if ($new_role !== 'admin' && $new_role !== 'user') {
                $error_message = "Peran tidak valid.";
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_role, $user_id_to_manage);
                if ($update_stmt->execute()) {
                    $message = "Peran pengguna berhasil diperbarui.";
                } else {
                    $error_message = "Gagal memperbarui peran.";
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $user_id_to_manage = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $current_admin_id = $_SESSION['user_id'];
        
        if ($user_id_to_manage == $current_admin_id) {
            $error_message = "Anda tidak dapat menghapus akun Anda sendiri.";
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->bind_param("i", $user_id_to_manage);
            if ($delete_stmt->execute()) {
                $message = "Pengguna berhasil dihapus.";
            } else {
                $error_message = "Gagal menghapus pengguna.";
            }
        }
    }
}

$users_query = "SELECT id, username, email, role FROM users ORDER BY id ASC";
$users_result = $conn->query($users_query);
$users = [];
if ($users_result->num_rows > 0) {
    while($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        #sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1a7037 0%, #2c5530 100%);
            padding: 20px;
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        #sidebar .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        #sidebar .sidebar-header h2 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: bold;
        }
        #sidebar ul.components {
            padding: 0;
            list-style: none;
            flex-grow: 1;
        }
        #sidebar ul li {
            margin-bottom: 10px;
        }
        #sidebar ul li a {
            padding: 10px 15px;
            display: block;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        #sidebar ul li a:hover,
        #sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        #sidebar ul li a i {
            margin-right: 10px;
        }
        #content {
            flex-grow: 1;
            padding: 20px 15px;
            background: linear-gradient(135deg, #f0f0f0 0%, #ffffff 100%);
            overflow-y: auto;
            min-height: 100vh;
        }
        .main-content-area {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }
        .table th {
            background-color: #2c5530;
            color: white;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .btn-add-user {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .modal-header {
            background-color: #2c5530;
            color: white;
        }
    </style>
</head>
<body>
    <?php $currentPage = 'user_management'; include 'sidebar.php'; ?>
    <div id="content">
        <div class="main-content-area">
            <div class="container-fluid">
                <div class="table-header">
                    <h3><i class="fas fa-users-cog"></i> Manajemen Pengguna</h3>
                    <button type="button" class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus"></i> Tambah Pengguna
                    </button>
                </div>
                
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
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Pengguna</th>
                                <th>Email</th>
                                <th>Peran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form action="" method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()" <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                                <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form action="" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nama Pengguna</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Minimal 8 karakter, mengandung huruf, angka, dan simbol.</small>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Peran</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>