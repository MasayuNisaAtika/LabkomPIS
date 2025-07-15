<?php
session_start();
include '../conn.php';
include '../auth_admin.php';
// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<script>alert('Anda harus login terlebih dahulu.'); window.location.href='../login.php';</script>";
    exit;
}

// Cek apakah user memiliki role admin
if ($_SESSION['role'] !== 'admin') {
    echo "<script>alert('Anda tidak memiliki izin untuk mengakses halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $menu_id = $_GET['id'];

   
    $query = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $menu_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = " Deleted Successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete " . $stmt->error;
    }

    $stmt->close();
} else {
    $_SESSION['error'] = "Invalid ID!";
}


header("Location: data_users.php");
exit();
?>
