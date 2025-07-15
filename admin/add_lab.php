<?php
session_start();
require '../conn.php';

// Proses saat form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $merek_pc = $_POST['merek_pc'];
    $total_pc = (int) $_POST['total_pc'];

    if (empty($name) || $total_pc <= 0) {
        $_SESSION['error'] = "Nama lab dan jumlah PC harus diisi dengan benar.";
    } else {
        // 1. Simpan lab baru
        $stmt = $conn->prepare("INSERT INTO labs (name, merek_pc, total_pc) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $merek_pc, $total_pc);
        $stmt->execute();
        $lab_id = $stmt->insert_id;
        $stmt->close();

        $_SESSION['success'] = "Lab dan PC berhasil ditambahkan.";
        header("Location: data_lab.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>Manage Lab</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="proses-lab">
    <?php include 'sidebar_admin.php'; ?>
    <div class="container">
        <div class="form">
            <h2>Add Lab Komputer</h2>
            
            <form method="POST" action="add_lab.php">
                <input type="text" name="name" placeholder="Nama Lab" required>
                <br><br>
                <input type="text" name="merek_pc" placeholder="Merek Komputer" required>
                <br><br>
                <input type="number" name="total_pc" placeholder="Jumlah PC" required min="1">
                <br><br>
                <button type="submit">+ ADD</button>
            </form>
                <br><br>
            <div class="button">
                <a href="data_lab.php">← Kembali ke Daftar Lab</a>
            </div>
        </div>
    </div>
</body>
</html>
