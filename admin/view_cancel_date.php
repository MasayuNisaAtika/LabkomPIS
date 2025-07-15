<?php
session_start();
include 'sidebar_admin.php';
require '../conn.php';

$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Hitung total data cancelled dari attendance
$countQuery = "SELECT COUNT(*) as total
               FROM attendance a
               JOIN schedules s ON a.schedule_id = s.id
               JOIN labs l ON s.lab_id = l.id
               JOIN users u ON s.users_id = u.id
               WHERE 
                 a.status = 'cancelled' AND (
                   l.name LIKE ? OR 
                   u.username LIKE ? OR 
                   s.subject LIKE ? OR 
                   a.date LIKE ?
               )";
$countStmt = $conn->prepare($countQuery);
$searchTerm = '%' . $search . '%';
$countStmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalData = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalData / $perPage);

// Ambil data cancelled dari attendance
$query = "SELECT 
            a.id AS attendance_id,
            a.date,
            a.note,
            s.id AS schedule_id,
            l.name AS lab_name,
            u.username AS user_name,
            s.subject
          FROM attendance a
          JOIN schedules s ON a.schedule_id = s.id
          JOIN labs l ON s.lab_id = l.id
          JOIN users u ON s.users_id = u.id
          WHERE 
            a.status = 'cancelled' AND (
              l.name LIKE ? OR 
              u.username LIKE ? OR 
              s.subject LIKE ? OR 
              a.date LIKE ?
          )
          ORDER BY a.date DESC
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cancelled Attendance</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .btn-red {
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
        }

        .btn-red:hover {
            background-color: #c0392b;
        }    
    </style>
</head>
<body>
    <main class="manage-lab">
        <h2>Cancelled Attendance</h2>
        <a href="data_schedule.php" class="btn"><< Back</a>

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

        <form method="GET" style="margin-bottom: 10px;">
            <input type="text" name="search" placeholder="Search by Lab, User, Subject, Date" value="<?= htmlspecialchars($search) ?>" />
            <br><br>
            <button type="submit">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cancel Date</th>
                    <th>Lab</th>
                    <th>Dosen</th>
                    <th>Subject</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["attendance_id"]) ?></td>
                        <td><?= htmlspecialchars($row["date"]) ?></td>
                        <td><?= htmlspecialchars($row["lab_name"]) ?></td>
                        <td><?= htmlspecialchars($row["user_name"]) ?></td>
                        <td><?= htmlspecialchars($row["subject"]) ?></td>
                        <td><?= htmlspecialchars($row["note"]) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No cancelled attendance found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

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
