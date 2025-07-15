<?php
session_start();
include 'sidebar_dosen.php';
include '../conn.php';

$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;
$users_id = intval($_SESSION['user_id']);

$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT schedules.*, labs.name AS lab_name, users.name AS user_name, classes.kode_kelas
          FROM schedules
          JOIN labs ON schedules.lab_id = labs.id
          JOIN users ON schedules.users_id = users.id
          JOIN classes ON schedules.class_id = classes.id
          WHERE schedules.users_id = ?
            AND (
                labs.name LIKE ? OR 
                users.name LIKE ? OR 
                schedules.subject LIKE ? OR 
                DATE_FORMAT(schedules.start_date, '%Y-%m-%d') = ?
            )
          ORDER BY schedules.id ASC
          LIMIT ? OFFSET ?";


$searchTerm = '%' . $search . '%';
$stmt = $conn->prepare($query);
$stmt->bind_param("issssii", $users_id, $searchTerm, $searchTerm, $searchTerm, $search, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$countQuery = "SELECT COUNT(*) as total
               FROM schedules
               JOIN labs ON schedules.lab_id = labs.id
               JOIN users ON schedules.users_id = users.id
               JOIN classes ON schedules.class_id = classes.id
               WHERE schedules.users_id = ?
                 AND (
                    labs.name LIKE ? OR 
                    users.name LIKE ? OR 
                    schedules.subject LIKE ? OR 
                    DATE_FORMAT(schedules.start_date, '%Y-%m-%d') = ?
                )";

$stmtCount = $conn->prepare($countQuery);
$stmtCount->bind_param("issss", $users_id, $searchTerm, $searchTerm, $searchTerm, $search);
$stmtCount->execute();
$countResult = $stmtCount->get_result();
$totalRow = $countResult->fetch_assoc();
$totalData = $totalRow['total'];
$totalPages = ceil($totalData / $perPage);
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
        <form method="GET" style="margin-bottom: 15px;">
            <input type="text" name="search" placeholder="Cari berdasarkan Lab, User, Subject dan Tanggal" value="<?= htmlspecialchars($search) ?>" />
            <br><br>
            <button type="submit">Cari</button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lab</th>
                        <th>Users</th>
                        <th>Subject</th>
                        <th>Start Date</th>
                        <th>Strat Time</th>
                        <th>Duration</th>
                        <th>End Time</th>
                        <th>Repeat Type</th>
                        <th>Repeat Until</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format tanggal ke dd-mm-yyyy
                            $formatted_date = date("d-m-Y", strtotime($row["start_date"]));
                            $formatted_repeat_until = date("d-m-Y", strtotime($row["repeat_until"]));
                        
                            // Hitung end_time dari start_time + duration
                            $start_time = new DateTime($row["start_time"]);
                        
                            // Convert duration dari HH:MM ke total menit
                            $duration_parts = explode(':', $row['duration']);
                            $hours = isset($duration_parts[0]) ? (int)$duration_parts[0] : 0;
                            $minutes = isset($duration_parts[1]) ? (int)$duration_parts[1] : 0;
                            $total_minutes = ($hours * 60) + $minutes;

                        
                            $end_time = clone $start_time;
                            $end_time->modify("+{$total_minutes} minutes");
                        
                           $formatted_start_time = $start_time->format('H:i');
                            $formatted_end_time = $end_time->format('H:i');

                            echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["lab_name"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["user_name"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["subject"]) . "</td>";
                                echo "<td>" . $formatted_date . "</td>";
                                echo "<td>" . $formatted_start_time . "</td>";
                                echo "<td>" . htmlspecialchars($row["duration"]) . "</td>";
                                echo "<td>" . $formatted_end_time . "</td>";
                                echo "<td>" . htmlspecialchars($row["repeat_type"]) . "</td>";
                                echo "<td>" . $formatted_repeat_until . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11'>No data available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i; ?>"><?= $i; ?></a>
            <?php endfor; ?>
        </div>

    </main>
</body>
</html>
