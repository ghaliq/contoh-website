<?php
$password = "master"; // Ganti dengan password yang Anda inginkan untuk akun admin ini
echo password_hash($password, PASSWORD_DEFAULT);
?>