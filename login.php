<?php
session_start();
include 'conn.php';

if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

if (isset($_POST["login"])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Gunakan prepared statement
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect berdasarkan role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'dosen':
                    header('Location: dosen/dashboard.php');
                    break;
                default:
                    $_SESSION['error'] = "Role tidak dikenali.";
                    header('Location: login.php');
                    break;
            }
            exit();
        }
    }

    // Jika login gagal
    $_SESSION['error'] = "Username / Password tidak sesuai";
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/layout.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="logo">
        <img src="img/Lab.jpg" alt="Logo Anda">
        </div>
        <div class="form">
        <h2>Login</h2>
            <form method="POST">
                <div class="form-group">
                    <input class="form-control" id="username" name="username" type="text" placeholder="Username" required />
                </div>
                <div class="form-group">
                    <input class="form-control" id="password" name="password" type="password" placeholder="Password" required />
                </div>
                <div class="form-group">
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </div>
            </form>

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
            <div class="mt-3 text-center">
                <p>I just looked at the schedule | <a href="index.php">Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>