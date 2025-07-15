<?php
include '../conn.php';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $schedule_id = intval($_POST['schedule_id']);
      $date = $_POST['date'];
      $action = $_POST['action']; // 'present' atau 'cancelled'
      $note = isset($_POST['note']) ? trim($_POST['note']) : null;

      if (!in_array($action, ['present', 'cancelled'])) {
          die("Invalid action.");
      }

      // 1. Insert ke attendance
      $stmt1 = $conn->prepare("INSERT INTO attendance (schedule_id, date, status, note)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)");
      $stmt1->bind_param("isss", $schedule_id, $date, $action, $note);
      $stmt1->execute();

      // 2. Insert ke schedule_history
      $history_action = ($action === 'present') ? 'presence_confirmed' : 'presence_cancelled';
      $stmt2 = $conn->prepare("INSERT INTO schedule_history (schedule_id, action, note)
          VALUES (?, ?, ?)");
      $stmt2->bind_param("iss", $schedule_id, $history_action, $note);
      $stmt2->execute();

      $stmt1->close();
      $stmt2->close();

      header("Location: view_present.php?status=success&message=Data+berhasil+disimpan");
      exit;
    }

function expandBookings($conn, $month, $year) {
  $results = [];

  $startOfMonth = date('Y-m-01', strtotime("$year-$month-01"));
  $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

  $query = "
    SELECT s.*, l.name AS lab_name, u.username AS lecturer_name
    FROM schedules s
    JOIN labs l ON s.lab_id = l.id
    JOIN users u ON s.users_id = u.id
    WHERE s.start_date <= '$endOfMonth' AND s.repeat_until >= '$startOfMonth'
  ";

  $res = mysqli_query($conn, $query);

  while ($row = mysqli_fetch_assoc($res)) {
    $repeatType = $row['repeat_type'];
    $current = new DateTime($row['start_date']);
    $repeatUntil = new DateTime($row['repeat_until']);

    $startDate = new DateTime($startOfMonth);
    $endDate = new DateTime($endOfMonth);

    while ($current <= $repeatUntil) {
      $currentStr = $current->format('Y-m-d');

      if ($current >= $startDate && $current <= $endDate) {
        $row['date'] = $currentStr;
        $results[] = $row;
      }

      if ($repeatType == 'daily') {
        $current->modify('+1 day');
      } elseif ($repeatType == 'weekly') {
        $current->modify('+7 days');
      } else {
        break;
      }
    }

    if ($repeatType == 'none' && $row['start_date'] >= $startOfMonth && $row['start_date'] <= $endOfMonth) {
      $row['date'] = $row['start_date'];
      $results[] = $row;
    }
  }

  usort($results, function ($a, $b) {
    return strcmp($a['date'] . $a['start_time'], $b['date'] . $b['start_time']);
  });

  return $results;
}

// Ambil bulan dan tahun dari GET
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');

// Ambil data booking yang sudah di-expand
$bookings = expandBookings($conn, $selectedMonth, $selectedYear);

// Ambil semua data attendance untuk bulan ini
$startOfMonth = date('Y-m-01', strtotime("$selectedYear-$selectedMonth-01"));
$endOfMonth = date('Y-m-t', strtotime($startOfMonth));
$attendanceData = [];

$attQuery = "SELECT * FROM attendance WHERE date BETWEEN '$startOfMonth' AND '$endOfMonth'";
$attRes = mysqli_query($conn, $attQuery);
while ($att = mysqli_fetch_assoc($attRes)) {
  $key = $att['schedule_id'] . '_' . $att['date'];
  $attendanceData[$key] = $att;
}

// Navigasi bulan
$prevMonth = date('m', strtotime("$selectedYear-$selectedMonth-01 -1 month"));
$prevYear  = date('Y', strtotime("$selectedYear-$selectedMonth-01 -1 month"));
$nextMonth = date('m', strtotime("$selectedYear-$selectedMonth-01 +1 month"));
$nextYear  = date('Y', strtotime("$selectedYear-$selectedMonth-01 +1 month"));

$bulan = [
  '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
  '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <title>Attendance Confirmation</title>
      <style>
        body {
          background-color: #f4f6f8;
          margin: 0;
          padding: 0;
        }

        h2 {
          color: #2c3e50;
          margin-bottom: 20px;
        }

        button {
          padding: 8px 14px;
          font-size: 14px;
          border-radius: 6px;
          background-color: rgb(125, 2, 136);
          color: white;
          border: none;
          cursor: pointer;
          transition: 0.3s ease;
        }

        button:hover {
          background-color: rgb(125, 42, 173);
        }

        table {
          width: 100%;
          max-width: 1100px;
          border-collapse: collapse;
          background-color: white;
          box-shadow: 0 4px 8px rgba(0,0,0,0.1);
          border-radius: 8px;
          overflow: hidden;
          font-size: 14px;
        }

        th, td {
          padding: 10px 14px;
          border-bottom: 1px solid #e0e0e0;
          text-align: left;
        }

        th {
          background-color: #f0f3f5;
          font-weight: 600;
        }

        tr:nth-child(even) {
          background-color: #fafafa;
        }

        .navigation {
          margin: 20px 0;
        }

        .navigation button {
          margin: 0 8px;
        }

        .confirmed.present {
          color: green;
          font-weight: bold;
          display: inline-block;
          background-color: #e0f7e9;
          padding: 4px 8px;
          border-radius: 5px;
        }

        .confirmed.cancelled {
          color: red;
          font-weight: bold;
          display: inline-block;
          background-color: #e0f7e9;
          padding: 4px 8px;
          border-radius: 5px;
        }

        .note {
          font-size: 12px;
          color: #888;
          font-style: italic;
        }

        .btn-confirm {
          background-color: #2ecc71;
        }

        .btn-cancel {
          background-color: #e74c3c;
        }

        .btn-confirm:hover {
          background-color: #27ae60;
        }

        .btn-cancel:hover {
          background-color: #c0392b;
        }

        .modal {
          display: none;
          position: fixed;
          z-index: 1000;
          left: 0; top: 0;
          width: 100%; height: 100%;
          background-color: rgba(0,0,0,0.5);
          justify-content: center;
          align-items: center;
        }

        .modal-content {
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%); /* geser ke tengah */
          background-color: #fff;
          padding: 20px;
          border-radius: 10px;
          width: 400px;
          box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        textarea {
          width: 100%;
          border-radius: 4px;
          padding: 8px;
          border: 1px solid #ccc;
          resize: vertical;
          font-size: 14px;
        }
        .container {
          margin-left: 250px; /* Sesuai dengan lebar sidebar */
          padding: 40px;
        }
        .sidebar {
        background-color:rgb(83, 28, 114);
        color: white;
        width: 250px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        padding-top: 20px;
        }

        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }

        .sidebar ul li {
            padding: 10px;
            margin: 5px;
            text-align: left;
        }

        .sidebar ul li a {
            text-decoration: none;
            color: white;
            font-weight: bold;
            display: block;
            transition: background-color 0.3s;
        }

        .sidebar ul li a:hover {
            background-color:rgb(203, 179, 216); /* Warna coklat muda */
        }

        /* Menata logo dan nama kantin */
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            width: 100px; /* Sesuaikan ukuran logo */
            height: auto;
        }

        .nama-kampus {
            font-size: 18px;
            font-weight: bold;
            color: white;
            margin-top: 10px;
            font-family: 'Arial', sans-serif;
        }
      </style>
  </head>
  <body>
      <aside class="sidebar">
        <div class="logo-container">
          <img src="../img/logo_pis.png" alt="logo kampus" class="logo">
          <h2 class="nama-kampus">PIS</h2>
        </div>
        <ul>
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="data_users.php">Manage User</a></li>
          <li><a href="data_lab.php">Manage Lab</a></li>
          <li><a href="data_major.php">Manage Major</a></li>
          <li><a href="data_class.php">Manage Class</a></li>
          <li><a href="data_schedule.php">Manage Schedule</a></li>
          <li><a href="schedule_history.php">Schedule History</a></li>
          <li><a href="../logout.php">Logout</a></li>
        </ul>
      </aside>
      <div class="container">
          <h2>View Present</h2>

          <div class="navigation">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>"><button>&laquo; Bulan Sebelumnya</button></a>
            <strong style="margin: 0 20px;"><?= $bulan[$selectedMonth] . ' ' . $selectedYear ?></strong>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"><button>Bulan Berikutnya &raquo;</button></a>
          </div>

          <input type="text" id="searchInput" placeholder="Cari Lab, Dosen, atau Subject..." style="padding: 8px; width: 300px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ccc;">

        <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Lab</th>
            <th>Dosen</th>
            <th>Subject</th>
            <th>Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Repeat</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $i => $b): 
          $key = $b['id'] . '_' . $b['date'];
          $isConfirmed = isset($attendanceData[$key]);
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($b['lab_name']) ?></td>
          <td><?= htmlspecialchars($b['lecturer_name']) ?></td>
          <td><?= htmlspecialchars($b['subject']) ?></td>
          <td><?= htmlspecialchars($b['date']) ?></td>
          <td><?= htmlspecialchars(substr($b['start_time'],0,5)) ?></td>
          <td><?= htmlspecialchars(substr($b['end_time'],0,5)) ?></td>
          <td><?= htmlspecialchars($b['repeat_type']) ?></td>
          <td>
            <?php if ($isConfirmed): ?>
              <?php if ($attendanceData[$key]['status'] == 'present'): ?>
                <span class="confirmed present">✅ Present</span>
              <?php else: ?>
                <span class="confirmed cancelled">❌ Cancelled</span><br>
                <span class="note">"<?= htmlspecialchars($attendanceData[$key]['note']) ?>"</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color: gray;">Not yet confirmed</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$isConfirmed): ?>
              <form style="display:inline" method="POST">
                <input type="hidden" name="schedule_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="date" value="<?= $b['date'] ?>">
                <input type="hidden" name="action" value="present">
                <button type="submit" class="btn btn-confirm">Confirm Present</button>
              </form>

              <button 
                class="btn btn-cancel" 
                onclick="openCancelModal(<?= $b['id'] ?>, '<?= $b['date'] ?>')">
                Cancel
              </button>
            <?php else: ?>
              <em>-</em>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>

        <!-- Modal -->
        <div id="cancelModal" class="modal">
          <div class="modal-content">
            <h3>Cancel Attendance</h3>
            <form method="POST" id="cancelForm">
              <input type="hidden" name="schedule_id" id="cancel_schedule_id">
              <input type="hidden" name="date" id="cancel_date">
              <input type="hidden" name="action" value="cancelled">
              <label for="note">Reason / Note:</label><br>
              <textarea name="note" id="note" rows="4" cols="30" required></textarea><br><br>
              <button type="submit" class="btn btn-cancel">Submit Cancel</button>
              <button type="button" class="btn" onclick="closeCancelModal()">Close</button>
            </form>
          </div>
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
        if (event.target == modal) closeCancelModal();
      }
      document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
          const rowText = row.innerText.toLowerCase();
          row.style.display = rowText.includes(searchTerm) ? '' : 'none';
        });
      });
    </script>

  </body>
</html>
