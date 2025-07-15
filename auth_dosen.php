<?php

// Cek apakah user sudah login dan punya role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    echo "<script>alert('Akses ditolak. Halaman ini hanya untuk dosen.'); window.location.href='../index.php';</script>";
    exit;
}
