<?php
session_start();
require '../conn.php';

// Proses saat form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_kelas = strtoupper(trim($_POST['kode_kelas']));
    $semester = $_POST['semester'];
    $kelas = $_POST['kelas'];
    $jml_mhs = (int) $_POST['jml_mhs'];

    $kode_major = substr($kode_kelas, 0, 2); // Ambil 2 karakter pertama

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

        // Cek kode_kelas unik
        $cek = $conn->prepare("SELECT id FROM classes WHERE kode_kelas = ?");
        $cek->bind_param("s", $kode_kelas);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $_SESSION['error'] = "Kode kelas sudah digunakan.";
        } else {
            $stmt = $conn->prepare("INSERT INTO classes (kode_kelas, semester, kelas, jml_mhs, major_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $kode_kelas, $semester, $kelas, $jml_mhs, $major_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['success'] = "Kelas berhasil ditambahkan.";
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
            <h2>Tambah Kelas</h2>
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
            <form method="POST" action="add_class.php">
                <input type="text" name="kode_kelas" placeholder="Kode Kelas (unik)" required>
                <br><br>
                <input type="text" name="semester" placeholder="Semester (Gunakan Romawi)" required>
                <br><br>
                <select name="kelas" required>
                    <option value="">-- Pilih Kelas --</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>
                <br><br>
                <input type="number" name="jml_mhs" placeholder="Jumlah Mahasiswa" required min="1">
                <br><br>
                <button type="submit">+ ADD</button>
            </form>
            <br><br>
            <div class="button">
                <a href="data_class.php">← Kembali ke Daftar Kelas</a>
            </div>
        </div>
    </div>
</body>
</html>
