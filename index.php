<?php
  session_start();
  include 'conn.php';

  $selected_major = isset($_GET['major']) ? $_GET['major'] : '';
    // Ambil semua jurusan dari tabel majors
    $majors = [];
    $major_query = $conn->query("SELECT kode_major, nama_major FROM major ORDER BY kode_major ASC");
    while ($row = $major_query->fetch_assoc()) {
        $majors[$row['kode_major']] = $row['nama_major'];
    }
    $sql = "SELECT s.*, u.name, l.name AS lab_name, c.kode_kelas, c.semester, c.kelas, c.jml_mhs, m.kode_major 
            FROM schedules s
            JOIN users u ON u.id = s.users_id
            JOIN labs l ON l.id = s.lab_id
            JOIN classes c ON c.id = s.class_id
            JOIN major m ON m.id = c.major_id
            WHERE m.kode_major = ?
            ORDER BY s.start_date, s.start_time";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_major);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    function indo_day($date) {
        $hariInggris = date('l', strtotime($date)); // ex: Monday, Tuesday
        $hariIndonesia = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu'
        ];
        return $hariIndonesia[$hariInggris] ?? $hariInggris;
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
      <style>
        body {
          margin: 0;
          font-family: 'Poppins', sans-serif;
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
          height: 60px;
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
        .card-container {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
          gap: 20px;
          margin: 40px auto;
          padding: 20px;
          justify-content: center; /* ini yang bikin dia ke tengah */
          max-width: 1000px; /* supaya tidak terlalu lebar */
        }

        .card {
          background-color: white;
          border: 2px solid #e6d3f5;
          border-radius: 16px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
          padding: 20px;
          transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
          transform: translateY(-6px);
          box-shadow: 0 8px 16px rgba(125, 2, 136, 0.2);
        }

        .card-title {
          font-size: 20px;
          font-weight: bold;
          color: rgb(83, 28, 114);
          margin-bottom: 12px;
        }

        .card-info {
          font-size: 14px;
          color: #444;
          margin-bottom: 8px;
        }

        .action-buttons {
          display: flex;
          justify-content: flex-end;
          margin-top: 12px;
        }

        .btn {
          background-color: rgb(187, 0, 204);
          color: white;
          padding: 8px 14px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 500;
          transition: background-color 0.3s;
        }

        .btn:hover {
          background-color: rgb(125, 2, 136);
        }
        
        .filter-buttons .btn {
          display: inline-block;
          background-color: rgb(125, 2, 136);
          color: white;
          padding: 8px 14px;
          margin: 4px;
          border-radius: 6px;
          text-decoration: none;
          font-weight: 500;
          transition: background-color 0.3s;
        }
        .filter-buttons .btn:hover {
          background-color: rgb(187, 0, 204);
        }

      </style>
  </head>
    <body>
        <header>
            <div>
                <img src="img/logo_pis.png" alt="Logo Piksi">
                <span style="font-size: 45px; font-weight: bold; margin-left: 10px;">
                  Laboratorium Komputer Politeknik Piksi Input Serang
                </span>
            </div>
            <a href="login.php" class="login-button">Login</a>
        </header>
      <br>

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
                            echo '<div class="booking-box" data-tooltip="' . htmlspecialchars($tooltip_text) . '">';
                            echo 'Booked';
                            echo '</div>';
                            echo '</a>';
                            echo '</td>';
                          }
                          } else {
                            echo '<td class="available">Available</td>';
                          }
                        ?>
                      <?php endforeach; ?>
                    </tr>
                  <?php endfor; ?>
              </tbody>
      </table>

      <h2 style="text-align: center;">Jadwal Penggunaan Laboratorium Komputer</h2>
      <p class="description">Pilih Jurusan dan lihat jadwal mingguannya</p>

      <?php if ($selected_major): ?>
        <h3 style="text-align: center; color: #531c72;">
          Jadwal untuk Jurusan <?= htmlspecialchars($majors[$selected_major] ?? $selected_major) ?>
        </h3>
      <?php endif; ?>

      <div class="filter-buttons" style="text-align:center; margin-bottom:20px;">
        <?php foreach ($majors as $kode => $kode): ?>
          <a href="?major=<?= urlencode($kode) ?>" class="btn"><?= htmlspecialchars($kode) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="lab-table">
        <table>
          <thead>
            <tr>
              <th>KODE KLS</th>
              <th>SMT</th>
              <th>MHS (R1/A)</th>
              <th>MHS (R2/B)</th>
              <th>JML</th>
              <th>HARI</th>
              <th>WAKTU</th>
              <th>RUANG</th>
              <th>MATA KULIAH</th>
              <th>NAMA DOSEN</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($rows)): ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $kode_kelas = $row['kode_kelas'] ?? '-';
                  $semester   = $row['semester'] ?? '-';
                  $kelas      = strtoupper($row['kelas'] ?? '-');
                  $jml_mhs    = (int)($row['jml_mhs'] ?? 0);
                  $hari       = indo_day($row['start_date']);
                  $start_time = new DateTime($row['start_time']);
                  $duration_parts = explode(':', $row['duration']);
                  $hours      = isset($duration_parts[0]) ? (int)$duration_parts[0] : 0;
                  $minutes    = isset($duration_parts[1]) ? (int)$duration_parts[1] : 0;
                  $end_time   = clone $start_time;
                  $end_time->modify("+{$hours} hours +{$minutes} minutes");
                  $time_range = $start_time->format('H.i') . ' - ' . $end_time->format('H.i');

                  $r1 = ($kelas === 'A') ? $jml_mhs : 0;
                  $r2 = ($kelas === 'B') ? $jml_mhs : 0;
                  $total = $r1 + $r2;
                ?>
                <tr>
                  <td><?= htmlspecialchars($kode_kelas) ?></td>
                  <td><?= htmlspecialchars($semester) ?></td>
                  <td style="text-align:right;"><?= $r1 ?: '-' ?></td>
                  <td style="text-align:right;"><?= $r2 ?: '-' ?></td>
                  <td style="text-align:right;"><strong><?= $total ?: $jml_mhs ?></strong></td>
                  <td><?= $hari ?></td>
                  <td><?= $time_range ?></td>
                  <td><?= htmlspecialchars($row['lab_name']) ?></td>
                  <td><?= htmlspecialchars($row['subject']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="10">Tidak ada data untuk jurusan ini.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
        
      <h2 style="text-align: center;">Informasi Ketersediaan Lab</h2>
        <div class="card-container">
          <?php
            $lab_card_result = $conn->query("SELECT * FROM labs");
            while ($lab = mysqli_fetch_assoc($lab_card_result)) :
          ?>
            <div class="card">
              <div class="card-title"><?= htmlspecialchars($lab['name']) ?></div>
              <div class="card-info">Merek Komputer: <?= htmlspecialchars($lab['merek_pc']) ?></div>
              <div class="card-info">Jumlah Komputer: <?= htmlspecialchars($lab['total_pc']) ?></div>

              <div class="action-buttons">
                <a href="computer_details.php?lab_id=<?= $lab['id'] ?>" class="btn">Lihat Detail</a>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
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
