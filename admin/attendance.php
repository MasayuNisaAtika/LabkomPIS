<?php
include '../conn.php';

$schedule_id = $_GET['schedule_id'] ?? null;
$date = $_GET['date'] ?? null;
$lab_id = $_GET['lab_id'] ?? null;

// Ambil nama lab
$lab_name = '';
$stmt = $conn->prepare("SELECT name FROM labs WHERE id = ?");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$stmt->bind_result($lab_name);
$stmt->fetch();
$stmt->close();

// Ambil detail dosen dan subject dari schedules
$stmt = $conn->prepare("
    SELECT s.*, n.name, c.kode_kelas 
    FROM schedules s 
    JOIN users n ON n.id = s.users_id 
    JOIN classes c ON c.id = s.class_id 
    WHERE s.id = ?
");
$stmt->bind_param('i', $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();
$stmt->close();

// Ambil data jadwal yang sudah di-expand
function expandBookings($conn, $month, $year) {
    $results = [];

    $startOfMonth = date('Y-m-01', strtotime("$year-$month-01"));
    $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

    $query = "
        SELECT s.*, l.name AS lab_name, n.name AS lecturer_name
        FROM schedules s
        JOIN labs l ON s.lab_id = l.id
        JOIN users n ON s.users_id = n.id
        WHERE s.start_date <= '$endOfMonth' 
          AND s.repeat_until >= '$startOfMonth'
    ";

    $res = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($res)) {
        $repeatType = $row['repeat_type'];
        $current = new DateTime($row['start_date']);
        $repeatUntil = new DateTime($row['repeat_until']);

        $startDate = new DateTime($startOfMonth);
        $endDate = new DateTime($endOfMonth);

        if ($repeatType == 'weekly') {
            while ($current <= $repeatUntil) {
                $currentStr = $current->format('Y-m-d');
                if ($current >= $startDate && $current <= $endDate) {
                    $row['date'] = $currentStr;
                    $results[] = $row;
                }
                $current->modify('+7 days');
            }
        }

        if ($repeatType == 'none' && $row['start_date'] >= $startOfMonth && $row['start_date'] <= $endOfMonth) {
            $row['date'] = $row['start_date'];
            $results[] = $row;
        }
    }

    return $results;
}

$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$bookings = expandBookings($conn, $selectedMonth, $selectedYear);

// Ambil data kehadiran
$attendanceData = [];
$resAttendance = mysqli_query($conn, "SELECT schedule_id, date, status FROM attendance");
while ($row = mysqli_fetch_assoc($resAttendance)) {
    $attendanceData[$row['schedule_id'] . '_' . $row['date']] = $row['status'];
}

// Cari booking yang cocok
$selectedBooking = null;
foreach ($bookings as $b) {
    if ($b['id'] == $schedule_id && $b['date'] == $date) {
        $selectedBooking = $b;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Konfirmasi Kehadiran</title>
  <link rel="stylesheet" href="../css/admin.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f1f3f5;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      color: #333;
    }

    h2 {
      margin-bottom: 20px;
      color: #2c3e50;
    }

    a button {
      background-color: rgb(125, 2, 136);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      margin-bottom: 20px;
    }

    a button:hover {
      background-color: rgb(125, 42, 173);
    }

    .info-box {
      background-color: white;
      padding: 25px 30px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      width: 100%;
      max-width: 500px;
    }

    .info-box p {
      margin: 10px 0;
      font-size: 15px;
    }

    .status {
      font-weight: bold;
      font-size: 16px;
    }

    .status.present {
      color: #27ae60;
    }

    .status.cancelled {
      color: #e74c3c;
    }

    .btn {
      padding: 10px 16px;
      border-radius: 5px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      margin-right: 10px;
    }

    .btn-confirm {
      background-color: #2ecc71;
      color: white;
    }

    .btn-cancel {
      background-color: #e74c3c;
      color: white;
    }

    .btn:hover {
      opacity: 0.9;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background: white;
      margin: 10% auto;
      padding: 25px;
      border-radius: 8px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .modal-content h3 {
      margin-top: 0;
    }

    textarea {
      width: 100%;
      border: 1px solid #ccc;
      border-radius: 5px;
      padding: 10px;
      font-size: 14px;
      resize: vertical;
    }

    .modal-content button {
      margin-top: 10px;
    }

    .manual-link {
      margin-top: 20px;
      text-align: center;
    }

    .manual-link .btn-confirm {
      text-decoration: none;
      display: inline-block;
    }
  </style>
</head>
<body>

  <h2>Konfirmasi Kehadiran</h2>
  <a href="dashboard.php">
    <button>Kembali ke Beranda</button>
  </a>

  <div class="info-box">
    <?php if ($selectedBooking): ?>
      <p><strong>Lab:</strong> <?= htmlspecialchars($lab_name) ?></p>
      <p><strong>Dosen:</strong> <?= htmlspecialchars($schedule['name']) ?></p>
      <p><strong>Subject:</strong> <?= htmlspecialchars($schedule['subject']) ?></p>
      <p><strong>Kelas:</strong> <?= htmlspecialchars($schedule['kode_kelas']) ?></p>
      <p><strong>Tanggal:</strong> <?= htmlspecialchars($date) ?></p>
      <p><strong>Jam:</strong> <?= htmlspecialchars(substr($selectedBooking['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($selectedBooking['end_time'], 0, 5)) ?></p>
      <p><strong>Repeat:</strong> <?= htmlspecialchars($selectedBooking['repeat_type']) ?> - <?= htmlspecialchars($selectedBooking['repeat_until']) ?></p>

      <?php
        $key = $selectedBooking['id'] . '_' . $selectedBooking['date'];
        $status = $attendanceData[$key] ?? null;

        if ($status === 'present') {
            echo '<p class="status present">Present</p>';
        } elseif ($status === 'cancelled') {
            echo '<p class="status cancelled">Cancelled</p>';
        } else {
      ?>
        <form method="POST" action="confirm_attendance.php" style="display:inline;">
          <input type="hidden" name="schedule_id" value="<?= $selectedBooking['id'] ?>">
          <input type="hidden" name="date" value="<?= $selectedBooking['date'] ?>">
          <input type="hidden" name="action" value="present">
          <button type="submit" class="btn btn-confirm">Confirm Present</button>
        </form>
        <button class="btn btn-cancel" onclick="openCancelModal(<?= $selectedBooking['id'] ?>, '<?= $selectedBooking['date'] ?>')">Cancelled</button>
      <?php } ?>
    
        <?php else: ?>
            <p style="color:red;">
                The data cannot be displayed because it has passed 1 Month.<br>
                You can confirm the missed schedule manually via the link below:
            </p>
            <a href="view_present.php" class="btn btn-confirm">Go to Manual Confirmation</a>
        <?php endif; ?>
  </div>

  <!-- Modal untuk Cancel -->
  <div id="cancelModal" class="modal">
    <div class="modal-content">
      <h3>Cancel Attendance</h3>
      <form method="POST" action="confirm_attendance.php" id="cancelForm">
        <input type="hidden" name="schedule_id" id="cancel_schedule_id">
        <input type="hidden" name="date" id="cancel_date">
        <input type="hidden" name="action" value="cancelled">
        <label for="note">Reason / Note:</label><br>
        <textarea name="note" id="note" rows="4" required></textarea><br>
        <button type="submit" class="btn btn-cancel">Submit Cancel</button>
        <button type="button" class="btn" onclick="closeCancelModal()">Close</button>
      </form>
    </div>
  </div>

  <script>
    function openCancelModal(scheduleId, date) {
      document.getElementById('cancel_schedule_id').value = scheduleId;
      document.getElementById('cancel_date').value = date;
      document.getElementById('note').value = '';
      document.getElementById('cancelModal').style.display = 'block';
    }

    function closeCancelModal() {
      document.getElementById('cancelModal').style.display = 'none';
    }

    window.onclick = function(event) {
      const modal = document.getElementById('cancelModal');
      if (event.target == modal) {
        closeCancelModal();
      }
    }
  </script>

</body>
</html>
