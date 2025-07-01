<?php
session_start(); 
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "dbd_db"; // buat di phpMyAdmin

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>