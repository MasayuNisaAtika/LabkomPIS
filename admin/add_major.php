<?php
session_start();
require '../conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_major = $_POST['kode_major'];
    $name_major = $_POST['nama_major'];

    // Cek apakah kode_major sudah ada
    $cek = $conn->prepare("SELECT id FROM major WHERE kode_major = ?");
    $cek->bind_param("s", $kode_major);
    $cek->execute();
    $result = $cek->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Kode jurusan sudah digunakan.";
    } else {
        $stmt = $conn->prepare("INSERT INTO major (kode_major, nama_major) VALUES (?, ?)");
        $stmt->bind_param("ss", $kode_major, $name_major);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Data jurusan berhasil ditambahkan.";
        header("Location: data_major.php");
        exit();
    }

    $cek->close();
}
?>



<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>Manage Major</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="proses-lab">
    <?php include 'sidebar_admin.php'; ?>
    <div class="container">
        <div class="form">
            <h2>Add Major</h2>
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
            <form method="POST" action="add_major.php">
                <input type="text" name="kode_major" placeholder="Kode Jurusan" required>
                <br><br>
                <input type="text" name="nama_major" placeholder="Nama Jurusan" required>
                <br><br>
                <button type="submit">+ ADD</button>
            </form>
                <br><br>
            <div class="button">
                <a href="data_major.php">← Kembali ke List Jurusan</a>
            </div>
        </div>
    </div>
</body>
</html>
