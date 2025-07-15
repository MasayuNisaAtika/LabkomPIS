<?php
session_start();
include '../conn.php';

$labName = isset($_GET['lab']) ? urldecode($_GET['lab']) : '';
if (!$labName) {
    echo "Lab tidak ditemukan.";
    exit;
}

$query = "SELECT s.*, l.name AS lab_name, u.name AS dosen_name, c.kode_kelas, c.semester, c.kelas, c.jml_mhs
          FROM schedules s
          JOIN labs l ON s.lab_id = l.id
          JOIN users u ON s.users_id = u.id
          LEFT JOIN classes c ON s.class_id = c.id
          WHERE s.repeat_type = 'weekly' AND l.name = ?
          ORDER BY s.start_time ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $labName);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

function indo_day($date) {
    $map = ['Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu'];
    return $map[date('D', strtotime($date))] ?? '';
}

function getSemesterInfoByRoman($roman, $date) {
    $number = match($roman) {
        'I' => 1,
        'II' => 2,
        'III' => 3,
        'IV' => 4,
        'V' => 5,
        'VI' => 6,
        'VII' => 7,
        'VIII' => 8,
        default => 0
    };
    $year = (int)date('Y', strtotime($date));

    if ($number % 2 === 0) {
        return "Semester Genap Tahun ".($year - 1)."/".$year;
    } else {
        return "Semester Ganjil Tahun ".$year."/".($year + 1);
    }
}

$semesterInfo = ($rows && !empty($rows[0]['semester']) && !empty($rows[0]['start_date']))
    ? getSemesterInfoByRoman($rows[0]['semester'], $rows[0]['start_date'])
    : '';

$today = date('j F Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Jadwal - <?= htmlspecialchars($labName) ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; color: #000; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { padding: 10px;
        text-align: left;
        border: 1px solid #ddd;
        white-space: nowrap;}
    th { background-color:rgb(83, 28, 114);
        color: white; }
    .header { text-align: center; }
    .header img { float: left; width: 100px; }
    .title { font-size: 18px; font-weight: bold; margin-top: 10px; }
    .subtitle { margin-bottom: 10px; font-weight: bold; }
    .signature { margin-top: 50px; width: 100%; text-align: center; }
    .signature td { padding: 30px 10px 0 10px; }
    .nowrap { white-space: nowrap; }
    @media print {
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="header">
    <img src="../img/logo_pis.png" alt="Logo">
    <br>
    <div class="title">
      JADWAL PRAKTIKUM <?= strtoupper(htmlspecialchars($labName)) ?><br>
      <?= $semesterInfo ?><br>
      PROGRAM STUDI DIPLOMA III & IV
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>KODE/KLS</th>
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
      <?php foreach ($rows as $row):
        $kelas = strtoupper($row['kelas'] ?? '-');
        $jml_mhs = (int)($row['jml_mhs'] ?? 0);
        $r1 = ($kelas === 'A') ? $jml_mhs : 0;
        $r2 = ($kelas === 'B') ? $jml_mhs : 0;
        $total = $r1 + $r2;
        $hari = indo_day($row['start_date']);
        $start_time = new DateTime($row['start_time']);
        $duration_parts = explode(':', $row['duration']);
        $hours   = isset($duration_parts[0]) ? (int)$duration_parts[0] : 0;
        $minutes = isset($duration_parts[1]) ? (int)$duration_parts[1] : 0;

        $end_time = clone $start_time;
        $end_time->modify("+{$hours} hours +{$minutes} minutes");

        $time_range = $start_time->format('H.i') . ' - ' . $end_time->format('H.i');
      ?>
      <tr>
        <td><?= $row['kode_kelas'] ?></td>
        <td><?= $row['semester'] ?></td>
        <td class="nowrap" style="text-align:right;"><?= $r1 ?: '-' ?></td>
        <td class="nowrap" style="text-align:right;"><?= $r2 ?: '-' ?></td>
        <td class="nowrap" style="text-align:right;"><strong><?= $total ?: $jml_mhs ?></strong></td>
        <td><?= $hari ?></td>
        <td><?= $time_range ?></td>
        <td><?= $row['lab_name'] ?></td>
        <td><?= $row['subject'] ?></td>
        <td><?= $row['dosen_name'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="signature">
    <tr>
      <td>Kapuskom</td>
      <td>Kepala BAAK</td>
      <td style="text-align:center;">Serang, <?= $today ?><br>Kepala Laboratorium</td>
    </tr>
    <tr>
      <td><br><br><br>Yudiansyah Fauzi, S.Kom.,M.Kom</td>
      <td><br><br><br>Siti Sofiyah, S.E.,M.M</td>
      <td><br><br><br>Solahudin Al Ayubi</td>
    </tr>
  </table>

  <script>window.print();</script>
</body>
</html>
