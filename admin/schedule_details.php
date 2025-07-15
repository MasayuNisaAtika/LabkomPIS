<?php
session_start();
include 'sidebar_admin.php';
include '../conn.php';

$perPage = 30;
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset   = ($page - 1) * $perPage;

$search   = isset($_GET['search']) ? $_GET['search'] : '';
$searchTerm = '%' . $search . '%';

$query = "SELECT s.*, 
                 l.name         AS lab_name,
                 u.name         AS name,
                 c.kode_kelas,  c.semester, c.kelas, c.jml_mhs
          FROM schedules s
          JOIN labs     l ON s.lab_id   = l.id
          JOIN users    u ON s.users_id = u.id
          LEFT JOIN classes c ON s.class_id = c.id
          WHERE (
              l.name       LIKE ? OR 
              u.name       LIKE ? OR 
              s.subject    LIKE ? OR 
              s.start_date LIKE ? OR 
              c.kode_kelas LIKE ? OR 
              c.semester   LIKE ?
          )
          AND s.repeat_type = 'weekly'
          ORDER BY s.id ASC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Ambil semua nama lab yang ada di jadwal weekly
$labSchedules = [];
$labQuery = $conn->query("SELECT DISTINCT l.name 
                          FROM schedules s 
                          JOIN labs l ON s.lab_id = l.id 
                          WHERE s.repeat_type = 'weekly'");
while ($lab = $labQuery->fetch_assoc()) {
    $labSchedules[$lab['name']] = [];
}

// Kelompokkan berdasarkan nama lab
while ($row = $result->fetch_assoc()) {
    if (isset($labSchedules[$row['lab_name']])) {
        $labSchedules[$row['lab_name']][] = $row;
    }
}
function indo_day($date) {
    $map = ['Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu'];
    return $map[date('D', strtotime($date))] ?? '';
}

function getSemesterInfoByRoman($semester, $date) {
    $romawi = ['I'=>1, 'II'=>2, 'III'=>3, 'IV'=>4, 'V'=>5, 'VI'=>6, 'VII'=>7, 'VIII'=>8];
    $number = $romawi[strtoupper(trim($semester))] ?? 0;

    if ($number === 0) return "";

    $month = (int)date('m', strtotime($date));
    $year = (int)date('Y', strtotime($date));

    // Logika akademik:
    // Semester Ganjil: Juli–Des -> tahun ajaran sekarang/berikutnya
    // Semester Genap : Jan–Juni -> tahun ajaran sebelumnya/sekarang
    if ($number % 2 === 0) {
        $label = "Semester Genap";
        $startYear = $year - 1;
        $endYear   = $year;
    } else {
        $label = "Semester Ganjil";
        $startYear = $year;
        $endYear   = $year + 1;
    }

    return "$label Tahun Ajaran $startYear/$endYear";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
      .lab-section {
        border: 1px solid #ccc;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 30px;
        background: #fdfdfd;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      }
      .lab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #007BFF;
        color: white;
        padding: 10px 15px;
        border-radius: 10px;
      }
      .btn-cetak {
        background: white;
        color: #007BFF;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
      }
      .btn-cetak:hover {
        background: #e9e9e9;
      }
      .btn-cetak-all {
        display: inline-block;
        margin-bottom: 20px;
        padding: 10px 20px;
        background: #28a745;
        color: white;
        text-decoration: none;
        font-weight: bold;
        border-radius: 6px;
      }
      .btn-cetak-all:hover {
        background: #218838;
      }

    </style>
</head>
  <body>
    <main class="manage-lab">
      <h2>Schedule Details</h2>

      <p>
        <?php 
          if (isset($_SESSION['error']))  { echo "<span style='color:red'>".$_SESSION['error']."</span>";  unset($_SESSION['error']); }
          if (isset($_SESSION['success'])){ echo "<span style='color:green'>".$_SESSION['success']."</span>"; unset($_SESSION['success']); } 
        ?>
      </p>

      <form method="GET" style="margin-bottom: 10px;">
        <input type="text" name="search" placeholder="Cari Lab, Dosen, Mata Kuliah, Tanggal, Kode Kelas, Semester" value="<?= htmlspecialchars($search) ?>" />
        <br><br>
        <button type="submit">Search</button>
      </form>

      <a href="cetak_all.php" target="_blank" class="btn-cetak-all">Cetak Semua Jadwal</a>

      <?php foreach ($labSchedules as $labName => $rows): 
        $firstRow = $rows[0] ?? null;
        $semesterInfo = '';
        if ($firstRow && !empty($firstRow['semester']) && !empty($firstRow['start_date'])) {
            $semesterInfo = getSemesterInfoByRoman($firstRow['semester'], $firstRow['start_date']);
        }
      ?>
        <div class="lab-section">
          <div class="lab-header">
            <h3><?= htmlspecialchars($labName) ?> - <?= $semesterInfo ?></h3>
            <a href="cetak.php?lab=<?= urlencode($labName) ?>" target="_blank" class="btn-cetak">Cetak</a>
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
              <?php if (count($rows)):
                  foreach ($rows as $row):
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
                  <td><?= htmlspecialchars($labName) ?></td>
                  <td><?= htmlspecialchars($row['subject']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="10">Tidak ada data untuk lab ini.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </main>
  </body>

</html>
