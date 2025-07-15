<?php
session_start();
include '../conn.php';
include '../auth_admin.php';

  // Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<script>alert('Anda harus login terlebih dahulu.'); window.location.href='../login.php';</script>";
    exit;
}

// Cek apakah user memiliki role admin
if ($_SESSION['role'] !== 'admin') {
    echo "<script>alert('Anda tidak memiliki izin untuk mengakses halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Jika tombol konfirmasi belum ditekan, tampilkan peringatan
if (!isset($_POST['confirm_delete'])) {
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Hapus Semua Data Jadwal</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #fcb2b2ff;
                padding: 40px;
            }
            .warning-box {
                border: 1px solid #856404;
                background-color: #6e1111ff;
                padding: 20px;
                border-radius: 8px;
                color: white;
                max-width: 600px;
                margin: auto;
            }
            .danger-button {
                background-color: #dc3545;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                margin-top: 20px;
            }
            .danger-button:hover {
                background-color: #c82333;
            }
        </style>
    </head>
    <body>
        <div class="warning-box">
            <h2>Peringatan!</h2>
            <p>Anda akan menghapus <strong>seluruh data jadwal</strong>, <strong>riwayat kehadiran</strong>, dan <strong>history tindakan</strong>.</p>
            <p>Tindakan ini <strong>tidak bisa dikembalikan</strong>. Pastikan Anda sudah melakukan backup data jika diperlukan.</p>
            <form method="post">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="danger-button">Saya Yakin, Hapus Semua Data</button>
            </form>
        </div>
    </body>
    </html>
<?php
    exit();
}

// Eksekusi penghapusan data
try {
    // Nonaktifkan foreign key checks sementara
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Hapus data dari tabel yang saling terhubung
    $conn->query("DELETE FROM attendance");
    $conn->query("DELETE FROM schedule_history");
    $conn->query("DELETE FROM schedules");

    // Reset AUTO_INCREMENT ke 1
    $conn->query("ALTER TABLE schedules AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE attendance AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE schedules_history AUTO_INCREMENT = 1");

    // Aktifkan kembali foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<script>alert('Semua data jadwal, kehadiran, dan history berhasil dihapus!'); window.location.href='data_schedule.php';</script>";
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage();
}
?>
