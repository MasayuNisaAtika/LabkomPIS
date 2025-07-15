<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';

$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT schedules.*, labs.name AS lab_name, users.name AS name, classes.kode_kelas
          FROM schedules
          JOIN labs ON schedules.lab_id = labs.id
          JOIN users ON schedules.users_id = users.id
          JOIN classes ON schedules.class_id = classes.id
          WHERE 
            labs.name LIKE ? OR 
            users.name LIKE ? OR 
            schedules.subject LIKE ? OR 
            schedules.start_date LIKE ?
          ORDER BY schedules.id ASC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$searchTerm = '%' . $search . '%';
$stmt->bind_param("ssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Total data untuk pagination
$countQuery = "SELECT COUNT(*) as total
               FROM schedules
               JOIN labs ON schedules.lab_id = labs.id
               JOIN users ON schedules.users_id = users.id
               WHERE 
                 labs.name LIKE ? OR 
                 users.name LIKE ? OR 
                 schedules.subject LIKE ? OR 
                 schedules.start_date LIKE ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countData = $countResult->fetch_assoc();
$totalData = $countData['total'];
$totalPages = ceil($totalData / $perPage);

$cancelQuery = "SELECT COUNT(*) as total_cancelled FROM attendance WHERE status = 'cancelled'";
$cancelResult = $conn->query($cancelQuery);
$totalCancelled = $cancelResult ? $cancelResult->fetch_assoc()['total_cancelled'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<main class="manage-lab">
    <h2>Schedule List</h2>

    <?php if (isset($_SESSION['error'])) { echo "<p style='color:red'>{$_SESSION['error']}</p>"; unset($_SESSION['error']); } ?>
    <?php if (isset($_SESSION['success'])) { echo "<p style='color:green'>{$_SESSION['success']}</p>"; unset($_SESSION['success']); } ?>

    <form method="GET" style="margin-bottom: 15px;">
        <input type="text" name="search" placeholder="Cari berdasarkan Lab, User, Subject, Tanggal" value="<?= htmlspecialchars($search) ?>" />
        <br><br>
        <button type="submit">Cari</button>
    </form>

    <div style="margin-bottom: 10px;">
        <a href="view_cancel_date.php"><button>Lihat Jadwal Dibatalkan (<?= $totalCancelled ?>)</button></a>
        <a href="schedule_details.php"><button>Detail Jadwal</button></a>
        <a href="view_present.php"><button>Lihat Kehadiran</button></a>
        <a href="delete_all.php"><button>Delete All</button></a>
    </div>

    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Nama Lab</th>
                <th>Dosen</th>
                <th>Kelas</th>
                <th>Mata Kuliah</th>
                <th>Tanggal</th>
                <th>Jam Mulai</th>
                <th>Jam Selesai</th>
                <th>Durasi (perjam)</th>
                <th>Repeat</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $no = $offset + 1;
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $no++ . "</td>";
                    echo "<td>" . htmlspecialchars($row["lab_name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["kode_kelas"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["subject"]) . "</td>";
                    echo "<td>" . date("d-m-Y", strtotime($row["start_date"])) . "</td>";
                    echo "<td>" . substr($row["start_time"], 0, 5) . "</td>";
                    echo "<td>" . substr($row["end_time"], 0, 5) . "</td>";
                    echo "<td>" . htmlspecialchars($row["duration"]) . "</td>";
                    echo "<td>" . ($row["repeat_type"] != 'none' ? ucfirst($row["repeat_type"]) . " s/d " . $row["repeat_until"] : '-') . "</td>";
                    echo "<td>
                            <a href='edit_booking.php?id={$row["id"]}' class='btn'>Edit</a>
                            <a href='delete_schedule.php?id={$row["id"]}' onclick='return confirm(\"Yakin ingin menghapus jadwal ini?\")' class='btn' style='background:#dc3545;'>Hapus</a>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='11'>Tidak ada data ditemukan.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
</main>
</body>
</html>
