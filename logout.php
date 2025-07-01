<?php
session_start(); // Memulai sesi PHP
session_unset(); // Menghapus semua variabel sesi
session_destroy(); // Menghancurkan sesi
header("Location: login.php"); // Mengarahkan kembali ke halaman login
exit; // Menghentikan eksekusi script
?>