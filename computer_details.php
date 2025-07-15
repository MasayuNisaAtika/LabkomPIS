<?php
include 'conn.php';

if (!isset($_GET['lab_id'])) {
  echo "ID Lab tidak ditemukan.";
  exit;
}

$lab_id = intval($_GET['lab_id']);
$sql = "SELECT * FROM labs WHERE id = $lab_id";
$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = intval($_POST['lab_id']);
    $pc_number = intval($_POST['pc_number']);
    $status = $_POST['status'];
    $note = isset($_POST['note']) ? $_POST['note'] : null;

    // Cek apakah data sudah ada
    $check = $conn->prepare("SELECT id FROM pc_status WHERE lab_id = ? AND pc_number = ?");
    $check->bind_param("ii", $lab_id, $pc_number);
    $check->execute();
    $check->store_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Detail Lab Computer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body {
    font-family: 'Poppins', sans-serif;
    background: #f2f2f9;
    padding: 30px;
    color: #331a47;
  }

  header {
    background-color: #531c72;
    color: white;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px;
  }

  .button {
    background-color: #d3bce7;
    color: #531c72;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: 0.3s;
  }

  .button:hover {
    background-color: #531c72;
    color: white;
  }

  h3 {
    text-align: center;
    margin: 25px 0;
    color: #531c72;
  }

  .lab-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .card {
    background-color: white;
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: 0.3s;
  }

  .card:hover {
    transform: translateY(-5px);
  }

  .icon-pc {
    font-size: 2.2rem;
    margin-bottom: 10px;
    color: #531c72;
  }

  .label {
    font-weight: bold;
    margin-bottom: 8px;
  }

  .status {
    padding: 6px 12px;
    border-radius: 16px;
    display: inline-block;
    cursor: pointer;
    color: white;
    font-weight: bold;
  }

  .status.available {
    background-color: #28a745;
  }

  .status.error {
    background-color: #dc3545;
  }
</style>
</head>
<body>
  <header>
    <div><strong>Detail Komputer Laboratorium</strong></div>
    <a href="index.php" class="button"><i class="bi bi-arrow-left"></i> Kembali</a>
  </header>

<?php
if ($result->num_rows > 0) {
    while ($lab = $result->fetch_assoc()) {
        $lab_id = $lab['id'];
        $pcStatusData = [];
        $statusResult = $conn->query("SELECT pc_number, status, note FROM pc_status WHERE lab_id = $lab_id");

        if ($statusResult->num_rows > 0) {
            while ($row = $statusResult->fetch_assoc()) {
                $pcStatusData[$row['pc_number']] = [
                    'status' => $row['status'],
                    'note' => $row['note']
                ];
            }
        }

        echo "<h3>{$lab['name']} ({$lab['merek_pc']})</h3>";
        echo "<div class='lab-layout'>";

        for ($i = 1; $i <= $lab['total_pc']; $i++) {
            $status = 'available';
            $note = '';
            if (isset($pcStatusData[$i])) {
                $status = $pcStatusData[$i]['status'];
                $note = htmlspecialchars($pcStatusData[$i]['note']);
            }
            $statusClass = $status === 'error' ? 'error' : 'available';
            $statusLabel = $status === 'error' ? 'Rusak' : 'Tersedia';

            echo "
            <div class='card'>
              <div class='icon-pc'><i class='bi bi-pc-display'></i></div>
              <div class='label'>PC $i</div>
              <div class='status $statusClass' title='$note'>$statusLabel</div>
            </div>";
        }

        echo "</div>";
    }
} else {
    echo "<p style='text-align:center'>Lab tidak ditemukan.</p>";
}
$conn->close();
?>

  <script>
    function toggleStatus(elem, labId, pcNumber) {
      const currentStatus = elem.classList.contains('available') ? 'available' : 'error';
      const newStatus = currentStatus === 'available' ? 'error' : 'available';
      let note = "";

      if (newStatus === 'error') {
        note = prompt("Masukkan catatan kerusakan untuk PC " + pcNumber);
        if (note === null) return;
      }

          fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `lab_id=${labId}&pc_number=${pcNumber}&status=${newStatus}&note=${encodeURIComponent(note)}`
        })
      .then(res => res.text())
      .then(() => location.reload());
    }
  </script>

  <?php
  // Tangani POST langsung dari halaman ini
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $pc_number = $_POST['pc_number'];
    $status = $_POST['status'];
    $note = $_POST['note'];

    $stmt = $conn->prepare("SELECT id FROM pc_status WHERE lab_id = ? AND pc_number = ?");
    $stmt->bind_param("ii", $lab_id, $pc_number);
    $stmt->execute();
    $stmt->store_result();
  }
  ?>

</body>
</html>
