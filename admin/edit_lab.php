<?php
session_start();
include 'sidebar_admin.php';
require '../conn.php';

if (!isset($_GET['id'])) {
    header('Location: data_lab.php');
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM labs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$name = $result->fetch_assoc();

if (!$name) {
    $_SESSION['error'] = "lab name not found";
    header('Location: data_lab.php');
    exit();
}

if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $total_pc = $_POST['total_pc'];
    $merek_pc = trim($_POST['merek_pc']);

    $update_stmt = $conn->prepare("UPDATE labs SET name = ?, total_pc = ?, merek_pc = ? WHERE id = ?");
    $update_stmt->bind_param("sisi", $name, $total_pc, $merek_pc, $id);

    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Updated successfully";
    } else {
        $_SESSION['error'] = "Failed to update";
    }
    header("Location: data_lab.php?id=$id");
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
        <title>Manage Lab</title>
        <link rel="stylesheet" href="../css/admin.css">
    </head>
    <body class="proses-lab">
    <div class="container">
        <div class="form">
        <h2>Edit Lab Computer</h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $name['id'] ?>">

            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($name['name']) ?>" required>
            <br><br>
            <label>Merek Komputer</label>
            <input type="text" name="merek_pc" value="<?= htmlspecialchars($name['merek_pc']) ?>" required>
            <br><br>
            <label>Total PC</label>
            <input type="number" name="total_pc" value="<?= htmlspecialchars($name['total_pc']) ?>" required>
            <br><br>
            <button type="submit" name="update">Update</button>
        </form>
        <br>
            <a href="data_lab.php">
            <button type="submit" name="back">Back</button>
            </a>
        </div>
    </div>
    </body>
</html>