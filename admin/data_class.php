<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';
  
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$query = "SELECT c.*, m.nama_major 
          FROM classes c 
          JOIN major m ON c.major_id = m.id 
          ORDER BY c.id ASC
          LIMIT $perPage OFFSET $offset";
$result = mysqli_query($conn, $query);

$totalQuery = "SELECT COUNT(*) as total FROM classes";
$totalResult = mysqli_query($conn, $totalQuery); 
$totalRow = mysqli_fetch_assoc($totalResult);
$totalData = $totalRow['total'];
$totalPages = ceil($totalData / $perPage);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <main class="manage-lab">
        <h2>Class List</h2>
        <a href="add_class.php" class="btn">+ add</a>
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
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kode Kelas</th>
                        <th>Semester</th>
                        <th>Kelas</th>
                        <th>Jml Mhs</th>
                        <th>Jurusan</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["kode_kelas"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["semester"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["kelas"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["jml_mhs"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nama_major"]) . "</td>";
                            echo "<td>
                                    <a href='edit_class.php?id=" . $row["id"] . "' class='btn'>Edit</a> 
                                    <a href='delete_class.php?id=" . $row["id"] . "' onclick='return confirm(\"Are you sure you want to remove this user?\")' class='btn'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No user data available</td></tr>";
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
