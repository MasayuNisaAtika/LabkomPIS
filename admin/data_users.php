<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';
  
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$query = "SELECT * FROM users LIMIT $perPage OFFSET $offset";
$result = mysqli_query($conn, $query);

$totalQuery = "SELECT COUNT(*) as total FROM users";
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
        <h2>User List</h2>
        <a href="add_users.php" class="btn">+ add</a>
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
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["role"]) . "</td>";
                            echo "<td>
                                    <a href='edit_users.php?id=" . $row["id"] . "' class='btn'>Edit</a> 
                                    <a href='delete_users.php?id=" . $row["id"] . "' onclick='return confirm(\"Are you sure you want to remove this user?\")' class='btn'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No user data available</td></tr>";
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
