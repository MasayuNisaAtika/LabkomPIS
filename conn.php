<?php
$host = "sql.freedb.tech";
$user = "freedb_labkom";
$pass = "sZRM%NjWy\$xDk7Z";  // tanda $ perlu di-escape
$db   = "freedb_labkompis";
$port = 3306;

// Koneksi ke MySQL
$conn = new mysqli($host, $user, $pass, $db, $port);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// echo "Koneksi berhasil";
?>