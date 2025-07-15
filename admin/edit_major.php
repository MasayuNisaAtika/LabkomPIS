<?php
session_start();
require '../conn.php';

// Cek apakah ada parameter ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID jurusan tidak ditemukan.";
    header("Location: data_major.php");
    exit();
}

$id = $_GET['id'];

// Ambil data jurusan berdasarkan ID
$stmt = $conn->prepare("SELECT * FROM major WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$major = $result->fetch_assoc();
$stmt->close();

if (!$major) {
    $_SESSION['error'] = "Data jurusan tidak ditemukan.";
    header("Location: data_major.php");
    exit();
}

// Proses update jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_major = $_POST['kode_major'];
    $nama_major = $_POST['nama_major'];

    if (empty($kode_major) || empty($nama_major)) {
        $_SESSION['error'] = "Masukkan kode dan nama jurusan dengan benar.";
    } else {
        $stmt = $conn->prepare("UPDATE major SET kode_major = ?, nama_major = ? WHERE id = ?");
        $stmt->bind_param("ssi", $kode_major, $nama_major, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Data jurusan berhasil diperbarui.";
        header("Location: data_major.php");
        exit();
    }
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
            <h2>Edit Major</h2>
            <p>
                <?php
                if (isset($_SESSION['error'])) {
                    echo "<span style='color:red'>" . $_SESSION['error'] . "</span>";
                    unset($_SESSION['error']);
                }
                ?>
            </p>
            <form method="POST" action="">
                <input type="text" name="kode_major" placeholder="Kode Jurusan" value="<?php echo htmlspecialchars($major['kode_major']); ?>" required>
                <br><br>
                <input type="text" name="nama_major" placeholder="Nama Jurusan" value="<?php echo htmlspecialchars($major['nama_major']); ?>" required>
                <br><br>
                <button type="submit">Simpan Perubahan</button>
            </form>
            <br><br>
            <div class="button">
                <a href="data_major.php">← Kembali ke List Jurusan</a>
            </div>
        </div>
    </div>
</body>
</html>
