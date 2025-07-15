<?php
session_start();
include 'sidebar_admin.php';
require '../conn.php';

if (isset($_POST["add_users"])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $confirm = $_POST['password_confirm'];
    $role = $_POST['role'];

    // Validasi konfirmasi password
    if ($password !== $confirm) {
        $_SESSION['error'] = "Confirmation password does not match!";
        header('Location: add_users.php');
        exit();
    }

    // Validasi role 
    if (!in_array($role, ['admin', 'dosen'])) {
        $_SESSION['error'] = "Role not valid!";
        header('Location: add_users.php');
        exit();
    }

    // Cek username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Username already in use!";
        header('Location: add_users.php');
        exit();
    }
    $stmt->close();

    // Simpan user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $username, $hashed_password, $role);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add user.";
    }

    header('Location: data_users.php');
    $stmt->close();
    exit();
}
?>

 
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <title>Manage Users</title>
        <link rel="stylesheet" href="../css/admin.css">
    </head>
    <body class="proses-lab">
    <div class="container">
        <div class="form">
        <h2>Add User</h2>
            <form method="POST">
            <p>
                <?php
                if (isset($_SESSION['error'])) {
                    echo "<span style='color:red'>" . $_SESSION['error'] . "</span>";
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo "<span style='color:green'>" . $_SESSION['success'] . "</span>";
                    unset($_SESSION['success']);
                }
                ?>
            </p>
                <div class="form-group">
                    <input class="form-control" id="name" name="name" type="text" placeholder="Name" required />
                </div>
                <div class="form-group">
                    <input class="form-control" id="username" name="username" type="text" placeholder="Username" required />
                </div>
                <div class="form-group">
                    <input class="form-control" id="password" name="password" type="password" placeholder="Password" required />
                </div>
                <div class="form-group">
                    <input class="form-control" id="password_confirm" name="password_confirm" type="password" placeholder="Confirm Password" required />
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select name="role" id="role" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="admin">Admin</option>
                        <option value="dosen">Dosen</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" name="add_users" class="btn btn-primary">ADD USER</button>
                </div>
            </form>
            <div class="button">
                <a href="data_users.php">← Back to User List</a>
            </div>
        </div>
    </div>
    </body>
</html>