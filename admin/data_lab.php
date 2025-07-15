<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';

$query = "SELECT * FROM labs";
$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title>Manage Lab</title>
  <style>
    body {
      margin-left: 250px; /* Ruang untuk sidebar */
      background-color: #f8f8fc;
      padding: 20px;
      color: #331a47;
    }

    h1 {
      text-align: center;
      margin-bottom: 30px;
      color: rgb(83, 28, 114);
    }

    .btn {
      display: inline-block;
      background-color: rgb(83, 28, 114);
      color: white;
      padding: 10px 16px;
      border-radius: 8px;
      text-decoration: none;
      margin-bottom: 20px;
      transition: background-color 0.3s;
    }

    .btn:hover {
      background-color: rgba(129, 43, 179, 0.8);
    }

    .card-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
    }

    .card {
      background-color: white;
      border-left: 6px solid rgb(83, 28, 114);
      padding: 20px;
      border-radius: 12px;
      width: 280px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .card-title {
      font-size: 1.2rem;
      font-weight: bold;
      color: rgb(83, 28, 114);
    }

    .card-info {
      font-size: 0.95rem;
      color: #331a47;
    }

    .action-buttons {
      margin-top: 10px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }

    .pagination {
      text-align: center;
      margin-top: 30px;
    }

    .pagination a {
      margin: 0 6px;
      padding: 8px 14px;
      background-color: rgb(83, 28, 114);
      color: white;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
    }

    .pagination a:hover {
      background-color: rgba(83, 28, 114, 0.85);
    }
  </style>
</head>
<body>
  <h1>List Laboratorium Komputer</h1>
  <a href="add_lab.php" class="btn">+ Add Lab</a>
  
    <?php if (isset($_SESSION['error'])): ?>
        <p style="color:red"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <p style="color:green"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
    <?php endif; ?>

  <div class="card-container">
    <?php while ($lab = mysqli_fetch_assoc($result)) : ?>
      <div class="card">
        <div class="card-title"><?= htmlspecialchars($lab['name']) ?></div>
        <div class="card-info">Merek Komputer: <?= htmlspecialchars($lab['merek_pc']) ?></div>
        <div class="card-info">Jumlah Komputer: <?= htmlspecialchars($lab['total_pc']) ?></div>

        <div class="action-buttons">
          <a href="computer_details.php?lab_id=<?= $lab['id'] ?>" class="btn" style="flex: 1; text-align: center;">Lihat</a>
          <a href="edit_lab.php?id=<?= $lab['id'] ?>" class="btn" style="flex: 1; text-align: center;">Edit</a>
          <a href="delete_lab.php?id=<?= $lab['id'] ?>" onclick="return confirm('Yakin ingin menghapus lab ini?')" class="btn" style="flex: 1; text-align: center; background-color: #ff4d4d;">Hapus</a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

</body>
</html>
