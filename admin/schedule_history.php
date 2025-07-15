<?php
include '../conn.php';
include 'sidebar_admin.php';

$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT 
            sh.*, 
            s.subject, s.start_date, s.start_time, s.end_time, s.duration, s.repeat_type,
            l.name AS lab_name,
            u.username AS user_name
          FROM schedule_history sh
          JOIN schedules s ON sh.schedule_id = s.id
          JOIN labs l ON s.lab_id = l.id
          JOIN users u ON s.users_id = u.id
          WHERE 
            l.name LIKE ? OR 
            u.username LIKE ? OR 
            s.subject LIKE ? OR 
            s.start_date LIKE ?
          ORDER BY s.id ASC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$searchTerm = '%' . $search . '%';
$stmt->bind_param("ssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Hitung total data untuk pagination
$countQuery = "SELECT COUNT(*) as total
               FROM schedule_history sh
               JOIN schedules s ON sh.schedule_id = s.id
               JOIN labs l ON s.lab_id = l.id
               JOIN users u ON s.users_id = u.id
               WHERE 
                 l.name LIKE ? OR 
                 u.username LIKE ? OR 
                 s.subject LIKE ? OR 
                 s.start_date LIKE ?";

$countStmt = $conn->prepare($countQuery);
if (!$countStmt) {
    die("Prepare failed: " . $conn->error);
}
$countStmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countData = $countResult->fetch_assoc();
$totalData = $countData['total'];
$totalPages = ceil($totalData / $perPage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Schedule History</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <main class="manage-lab">
        <h2>Schedule History</h2>
        <form method="GET" style="margin-bottom: 10px;">
            <input type="text" name="search" placeholder="Search by Lab, User, Subject, Date" value="<?= htmlspecialchars($search) ?>" />
            <br><br>
            <button type="submit">Search</button>
        </form>
        
        <a href="delete_history.php"><button>Delete All History</button></a>

        <div class="table-container">
            <table border="1" cellpadding="6">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lab</th>
                        <th>Dosen</th>
                        <th>Mata Kuliah</th>
                        <th>Tanggal</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Durasi</th>
                        <th>Repeat</th>
                        <th>Aksi</th>
                        <th>Waktu Aksi</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Format tanggal
                        $formatted_date = date("d-m-Y", strtotime($row["start_date"]));

                        // Hitung jam selesai dari jam mulai + durasi (dalam menit)
                        $start_time = new DateTime($row["start_time"]);
                        $end_time = new DateTime($row["end_time"]);

                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row["schedule_id"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["lab_name"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["user_name"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["subject"]) . "</td>";
                        echo "<td>" . $formatted_date . "</td>";
                        echo "<td>" . $start_time->format('H:i') . "</td>";
                        echo "<td>" . $end_time->format('H:i') . "</td>";
                        echo "<td>" . htmlspecialchars($row["duration"]) . " Jam</td>";
                        echo "<td>" . htmlspecialchars($row["repeat_type"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["action"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["action_date"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["note"]) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='12'>Tidak ada data ditemukan.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>


        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>>
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </main>
</body>
</html>
