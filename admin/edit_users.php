<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';

if (!isset($_GET['id'])) {
    header('Location: data_users.php');
    exit();
}

$id = intval($_GET['id']); 
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "User tidak ditemukan!";
    header('Location: data_users.php');
    exit();
}

if (isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $name = htmlspecialchars(trim($_POST['name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validasi role
    if (!in_array($role, ['admin', 'dosen'])) {
        $_SESSION['error'] = "Role tidak valid!";
        header("Location: edit_users.php?id=$id");
        exit();
    }

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, password=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $username, $hashed, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $username, $role, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update the user.";
    }
    header("Location: data_users.php?id=$id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage User</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="proses-lab">
    <div class="container">
        <div class="form">
            <h2>Edit User</h2>
            <form method="POST">

                <input type="hidden" name="id" value="<?= $user['id'] ?>">

                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                <br><br>

                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                <br><br>

                <label>Password (kosongkan jika tidak ingin diubah)</label>
                <input type="password" name="password">
                <br><br>

                <label>Role</label>
                <select name="role" required>
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="dosen" <?= $user['role'] == 'dosen' ? 'selected' : '' ?>>Dosen</option>
                </select>
                <br><br>

                <button type="submit" name="update_user">Update</button>
            </form>
            <br>
            <a href="data_users.php"><button>Back</button></a>
        </div>
    </div>
</body>
</html>
