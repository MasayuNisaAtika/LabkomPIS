<?php
session_start();
include 'sidebar_dosen.php';
include '../conn.php';

// Pastikan dosen sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dosen') {
    header('Location: ../logout.php');
    exit;
}

// Ambil data user dari session
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($password !== '') {
        // Hash password baru jika diisi
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, password=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $username, $hashed_password, $user_id);
    } else {
        // Jika password tidak diubah
        $stmt = $conn->prepare("UPDATE users SET name=?, username=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $username, $user_id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Profil berhasil diperbarui'); window.location='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil Dosen</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="proses-lab">
    <div class="container">
        <div class="form">
            <h2>Edit Profil</h2>
            <form method="POST">
                <label>Nama</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                <br><br>

                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                <br><br>

                <label>Password (kosongkan jika tidak ingin diubah)</label>
                <input type="password" name="password">
                <br><br>

                <button type="submit" name="update_user">Simpan Perubahan</button>
            </form>
        </div>
    </div>
</body>
</html>
