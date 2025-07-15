<?php
session_start();
require '../conn.php';

// Cek apakah ada ID
if (!isset($_GET['id'])) {
    header("Location: data_class.php");
    exit();
}

$id = (int) $_GET['id'];

// Ambil data kelas berdasarkan ID
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

if (!$class) {
    $_SESSION['error'] = "Data kelas tidak ditemukan.";
    header("Location: data_class.php");
    exit();
}

    // Proses update data saat form disubmit
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $kode_kelas = strtoupper(trim($_POST['kode_kelas']));
        $semester = $_POST['semester'];
        $kelas = $_POST['kelas'];
        $jml_mhs = (int) $_POST['jml_mhs'];

        $kode_major = substr($kode_kelas, 0, 2); // Ambil 2 huruf pertama

        // Cek major berdasarkan 2 huruf awal
        $stmt_major = $conn->prepare("SELECT id FROM major WHERE kode_major = ?");
        $stmt_major->bind_param("s", $kode_major);
        $stmt_major->execute();
        $result_major = $stmt_major->get_result();
        $major = $result_major->fetch_assoc();
        $stmt_major->close();

        if (!$major) {
            $_SESSION['error'] = "Kode major \"$kode_major\" tidak ditemukan. Pastikan kode_kelas dimulai dengan MI, AP, dsb.";
        } elseif (empty($kode_kelas) || empty($semester) || empty($kelas) || $jml_mhs <= 0) {
            $_SESSION['error'] = "Semua kolom wajib diisi dan jumlah mahasiswa harus valid.";
        } else {
            $major_id = $major['id'];

            // Cek kode_kelas unik (selain ID ini sendiri)
            $cek = $conn->prepare("SELECT id FROM classes WHERE kode_kelas = ? AND id != ?");
            $cek->bind_param("si", $kode_kelas, $id);
            $cek->execute();
            $cek->store_result();

            if ($cek->num_rows > 0) {
                $_SESSION['error'] = "Kode kelas sudah digunakan oleh kelas lain.";
            } else {
                $stmt = $conn->prepare("UPDATE classes SET kode_kelas = ?, semester = ?, kelas = ?, jml_mhs = ?, major_id = ? WHERE id = ?");
                $stmt->bind_param("sssiii", $kode_kelas, $semester, $kelas, $jml_mhs, $major_id, $id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['success'] = "Data kelas berhasil diperbarui.";
                header("Location: data_class.php");
                exit();
            }
            $cek->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>Manage Class</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="proses-lab">
    <?php include 'sidebar_admin.php'; ?>
    <div class="container">
        <div class="form">
            <h2>Edit Kelas</h2>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<span style='color:red'>" . $_SESSION['error'] . "</span><br><br>";
                unset($_SESSION['error']);
            }
            ?>

            <form method="POST" action="">
                <input type="text" name="kode_kelas" value="<?= htmlspecialchars($class['kode_kelas']) ?>" placeholder="Kode Kelas" required>
                <br><br>
                <input type="text" name="semester" value="<?= htmlspecialchars($class['semester']) ?>" placeholder="Semester" required>
                <br><br>
                <select name="kelas" required>
                    <option value="">-- Pilih Kelas --</option>
                    <option value="A" <?= $class['kelas'] == 'A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= $class['kelas'] == 'B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= $class['kelas'] == 'C' ? 'selected' : '' ?>>C</option>
                </select>
                <br><br>
                <input type="number" name="jml_mhs" value="<?= htmlspecialchars($class['jml_mhs']) ?>" placeholder="Jumlah Mahasiswa" required min="1">
                <br><br>
                <button type="submit">SIMPAN PERUBAHAN</button>
            </form>
            <br><br>
            <div class="button">
                <a href="data_class.php">← Kembali ke Daftar Kelas</a>
            </div>
        </div>
    </div>
</body>
</html>
