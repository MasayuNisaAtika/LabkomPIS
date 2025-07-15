<?php
  session_start();
  require 'sidebar_admin.php';
  include '../conn.php';
  include '../auth_admin.php';

  // Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<script>alert('Anda harus login terlebih dahulu.'); window.location.href='../login.php';</script>";
    exit;
}

// Cek apakah user memiliki role admin
if ($_SESSION['role'] !== 'admin') {
    echo "<script>alert('Anda tidak memiliki izin untuk mengakses halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

  $lab_options = [];
  $lab_result = $conn->query("SELECT id, name FROM labs ORDER BY name ASC");
  while ($lab = $lab_result->fetch_assoc()) {
    $lab_options[] = $lab;
  }

  $lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : $lab_options[0]['id'];
  $week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;

  $today = date('Y-m-d');
  $monday = date('Y-m-d', strtotime("monday this week +$week_offset week", strtotime($today)));

  $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  $hours = [
    'Monday' => range(8, 17),
    'Tuesday' => range(8, 17),
    'Wednesday' => range(8, 17),
    'Thursday' => range(8, 17),
    'Friday' => range(8, 17),
    'Saturday' => range(9, 16),
  ];

    function get_weekly_schedule_map($conn, $lab_id, $monday) {
      $start_of_week = $monday;
      $end_of_week = date('Y-m-d', strtotime('+5 days', strtotime($monday))); // Senin–Sabtu

      $query = "SELECT s.*, n.name, l.name AS lab_name, c.kode_kelas 
                FROM schedules s
                JOIN users n ON n.id = s.users_id
                JOIN labs l ON l.id = s.lab_id
                JOIN classes c ON c.id = s.class_id
                WHERE s.lab_id = ?
                  AND s.start_date <= ?
                  AND (
                    (s.repeat_type = 'none' AND s.start_date BETWEEN ? AND ?) OR
                    (s.repeat_type = 'weekly' AND s.repeat_until >= ?)
                  )";

      $stmt = $conn->prepare($query);
      $stmt->bind_param('issss', $lab_id, $end_of_week, $start_of_week, $end_of_week, $start_of_week);
      $stmt->execute();
      $result = $stmt->get_result();

      $map = [];

      while ($row = $result->fetch_assoc()) {
        $schedule_id = $row['id'];
        $repeat = $row['repeat_type'];
        $start_date = $row['start_date'];
        $start_time = $row['start_time'];
        $end_time = $row['end_time'];
        $username = $row['name'];
        $subject = $row['subject'];
        $kode_kelas = $row['kode_kelas'];

        $start_hour = intval(date('H', strtotime($start_time)));
        $end_hour = intval(date('H', strtotime($end_time)));

        // ⏱ Tambahkan 1 jam kalau menit pada end_time lebih dari 0
        $end_minute = intval(date('i', strtotime($end_time)));
        if ($end_minute > 0) {
          $end_hour += 1;
        }

        // ⏱ JIKA end_time dianggap MASIH DIPAKAI, TAMBAHKAN 1 JAM
        // Supaya slot jam end_hour juga ikut masuk
        // (karena for loop kita pakai $h < $end_hour di bawah)
        $end_hour += 1;

        for ($i = 0; $i <= 5; $i++) {
          $date = date('Y-m-d', strtotime("+$i days", strtotime($monday)));

          $day_match = (
            ($repeat == 'none' && $date == $start_date) ||
            ($repeat == 'weekly' &&
            date('N', strtotime($date)) == date('N', strtotime($start_date)) &&
            $date >= $start_date && $date <= $row['repeat_until'])
          );

          if ($day_match) {
            for ($h = $start_hour; $h < $end_hour; $h++) {
              $map[$date][$h] = [
                'schedule_id' => $schedule_id,
                'subject' => $subject,
                'name' => $username,
                'kode_kelas' => $kode_kelas,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'start_hour' => $start_hour,
                'end_hour' => $end_hour - 1, // karena tadi kita tambah 1 jam
                'is_first' => ($h === $start_hour),
              ];
            }
          }
        }
      }

      return $map;
    }

    $attendance_map = [];
    $query = "SELECT schedule_id, date, status, note FROM attendance";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['schedule_id'] . '-' . $row['date'];
        $attendance_map[$key] = $row;
    }
    $schedule_map = get_weekly_schedule_map($conn, $lab_id, $monday);
?>


<!DOCTYPE html>
<html lang="en">
  <title>Dashboard</title>
  <head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <style>   
      body {
        margin: 0;
        background: linear-gradient(135deg, #f0f4f8,rgb(235, 217, 236));
      }

      header {
        background-color: rgb(83, 28, 114);
        color: white;
        padding: 20px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }

      header img {
        height: 50px;
      }

      .login-button {
        background-color: white;
        color: rgb(125, 2, 136);
        padding: 10px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.3s;
      }

      .login-button:hover {
        background-color: #e6e6e6;
      }

      h2 {
        text-align: center;
        margin-top: 30px;
        color: rgb(125, 2, 136);
      }

      .description {
        text-align: center;
        font-style: italic;
        margin-bottom: 30px;
        color: #444;
      }

      .container {
        max-width: 1200px;
        margin: auto;
        padding: 20px;
        background-color: #ffffffc7;
        border-radius: 12px;
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
      }

      form, .nav-buttons {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 20px 0;
        flex-wrap: wrap;
        gap: 10px;
      }

      select {
        padding: 10px 14px;
        font-size: 16px;
        border-radius: 8px;
        border: 1px solid #ccc;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      }

      .nav-buttons a {
        background-color:rgb(187, 0, 204);
        color: white;
        text-decoration: none;
        padding: 10px 16px;
        border-radius: 8px;
        transition: background-color 0.3s, transform 0.2s;
        font-weight: 500;
      }

      .nav-buttons a:hover {
        background-color: rgb(125, 2, 136);
        transform: scale(1.05);
      }

      table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-radius: 12px;
        overflow: hidden;
      }

      th, td {
        border: 1px solid #e5e7eb;
        padding: 14px;
        text-align: center;
        vertical-align: middle;
      }

      th {
        background-color: rgb(83, 28, 114);
        color: white;
        font-weight: 600;
        font-size: 14px;
      }

      .available {
        background-color: #e7fbe7;
        color: #237b23;
        font-weight: 500;
        transition: background-color 0.3s;
      }

      .available:hover {
        background-color: #c4f0c4;
      }

      .booked {
        background-color: #fde2e2;
        color: #b60000;
        position: relative;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.3s;
      }

      .booked:hover {
        background-color: #f7baba;
      }

      .tooltip {
        visibility: hidden;
        background-color: #333;
        color: #fff;
        text-align: left;
        border-radius: 6px;
        padding: 8px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: opacity 0.3s;
        width: 200px;
        font-size: 14px;
      }

      td:hover .tooltip {
        visibility: visible;
        opacity: 1;
        transform: translateX(-50%) translateY(-5px);
      }
      .tooltip-box {
        position: absolute;
        background: #333;
        color: white;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 13px;
        white-space: pre-line;
        z-index: 1000;
        display: none;
      }
      .booked a {
        color: red;
        font-weight: bold;
      }

      @media (max-width: 768px) {
        table, thead, tbody, th, td, tr {
          display: block;
        }

        thead tr {
          display: none;
        }

        tr {
          margin-bottom: 10px;
          background-color: #fff;
          border-radius: 10px;
          box-shadow: 0 1px 5px rgba(0,0,0,0.1);
          overflow: hidden;
        }

        td {
          padding: 10px;
          border: none;
          position: relative;
        }

        td:before {
          content: attr(data-label);
          position: absolute;
          left: 16px;
          top: 12px;
          font-weight: bold;
          color: #555;
        }
      }
    </style>
  </head>
    <body class="dasboard">
        <main class="content">
            <h1>Dashboard</h1>
            <p>
            <?php
            if (isset($_SESSION['error'])) { echo "<span style='color:red'>" . $_SESSION['error'] . "</span>"; unset($_SESSION['error']); }
            if (isset($_SESSION['success'])) { echo "<span style='color:green'>" . $_SESSION['success'] . "</span>"; unset($_SESSION['success']); }
            ?>
            </p>
            
      <h2><?= date('M Y', strtotime($monday)) ?></h2>
      <form method="get">
        <label for="lab_id">Select Lab:</label>
        <select name="lab_id" id="lab_id" onchange="this.form.submit()">
          <?php foreach ($lab_options as $lab): ?>
            <option value="<?= $lab['id'] ?>" <?= $lab['id'] == $lab_id ? 'selected' : '' ?>><?= htmlspecialchars($lab['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="week_offset" value="<?= $week_offset ?>">
      </form>
      <div class="nav-buttons">
        <a href="?lab_id=<?= $lab_id ?>&week_offset=<?= $week_offset - 1 ?>">&laquo; Previous Week</a>
        <a href="?lab_id=<?= $lab_id ?>&week_offset=<?= $week_offset + 1 ?>">Next Week &raquo;</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <?php foreach ($days as $i => $day): ?>
              <th><?= $day . '<br>' . date('d M', strtotime("+{$i} day", strtotime($monday))) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody> 
          <?php
            $skip = []; // untuk skip cell yang sudah kena rowspan

            for ($h = 8; $h <= 17; $h++): ?>
              <tr>
                <td><?= sprintf('%02d:00', $h) ?></td>
                <?php foreach ($days as $i => $day): ?>
                  <?php
                    $current_date = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
                    $show = in_array($h, $hours[$day]);
                    $cell_key = $current_date . '-' . $h;

                    if (!$show) {
                      echo "<td>-</td>";
                      continue;
                    }

                    if (in_array($cell_key, $skip)) {
                      continue;
                    }

                    $info = $schedule_map[$current_date][$h] ?? null;

                    if ($info) {
                      if ($info['is_first']) {
                        $rowspan = ($info['end_hour'] - $info['start_hour']) + 1;
                        for ($s = 1; $s < $rowspan; $s++) {
                          $skip[] = $current_date . '-' . ($h + $s);
                        }

                        $schedule_id = $info['schedule_id'];
                        $date = $current_date;
                        $start_time = sprintf('%02d:00:00', $info['start_hour']);
                        $attendance_link = "attendance.php?schedule_id={$schedule_id}&lab_id={$lab_id}&date={$date}&start_time={$start_time}";
                        $attendance_key = $schedule_id . '-' . $current_date;
                        $status = isset($attendance_map[$attendance_key]['status']) ? $attendance_map[$attendance_key]['status'] : 'Belum dikonfirmasi';
                        $note = isset($attendance_map[$attendance_key]['note']) ? $attendance_map[$attendance_key]['note'] : '';
                        
                        echo '<td rowspan="' . $rowspan . '" class="booked">';
                        $tooltip_text = "Acara : " . htmlspecialchars($info['subject']) . 
                                        "\nDosen : " . htmlspecialchars($info['name']) . 
                                        "\nKelas : " . htmlspecialchars($info['kode_kelas']) .
                                        "\nStatus : " . htmlspecialchars($status);

                        if (!empty($note)) {
                          $tooltip_text .= "\nCatatan : " . htmlspecialchars($note);
                        }
                        echo "<!-- checking key: $attendance_key -->";
                        echo '<a href="' . $attendance_link . '" style="text-decoration: none; color: inherit;">';
                        echo '<div class="booking-box" data-tooltip="' . htmlspecialchars($tooltip_text) . '">';
                        echo 'Booked';
                        echo '</div>';
                        echo '</a>';
                        echo '</td>';
                      }
                    } else {
                      // ini bagian untuk Available
                      $start_time = sprintf('%02d:00:00', $h);
                      $end_time = sprintf('%02d:00:00', $h + 1);
                      $date_label = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
                      $add_link = "add_booking.php?lab_id={$lab_id}&date={$date_label}&start_time={$start_time}";

                      echo "<td class='available'><a href='{$add_link}' style='text-decoration: none; color: inherit;'>Available</a></td>";
                    }
                    ?>
                <?php endforeach; ?>
              </tr>
            <?php endfor; ?>

        </tbody>
      </table>

      <script>
      document.addEventListener('DOMContentLoaded', function () {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip-box';
        document.body.appendChild(tooltip);

        document.querySelectorAll('.booking-box').forEach(box => {
          box.addEventListener('mouseenter', (e) => {
            tooltip.textContent = box.getAttribute('data-tooltip');
            tooltip.style.display = 'block';
          });

          box.addEventListener('mousemove', (e) => {
            tooltip.style.left = (e.pageX + 10) + 'px';
            tooltip.style.top = (e.pageY + 10) + 'px';
          });

          box.addEventListener('mouseleave', () => {
            tooltip.style.display = 'none';
          });
        });
      });
      </script>
    </body>
</html>

 