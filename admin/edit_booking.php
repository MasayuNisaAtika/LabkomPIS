<?php
session_start();
include '../conn.php';
include 'sidebar_admin.php';

if (!isset($_GET['id'])) {
    header('Location: data_schedule.php');
    exit();
}

$schedule_id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dosenList = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'dosen'");
$classList = mysqli_query($conn, "SELECT id, kode_kelas FROM classes");

if (isset($_POST['update_schedule'])) {
    $users_id = $_POST['users_id'];
    $class_id = $_POST['class_id'];
    $subject = $_POST['subject'];
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $repeat_type = $_POST['repeat_type'];
    $repeat_until = ($repeat_type == 'none') ? $start_date : $_POST['repeat_until'];

    $parts = explode(':', $duration);
    $end_timestamp = strtotime("+{$parts[0]} hours +{$parts[1]} minutes", strtotime("$start_date $start_time"));
    $end_time = date("H:i", $end_timestamp);

    // Cek bentrok
    $conflict = false;
    $check = $conn->prepare("SELECT * FROM schedules WHERE lab_id = ? AND id != ?");
    $check->bind_param("ii", $data['lab_id'], $schedule_id);
    $check->execute();
    $result = $check->get_result();

    while ($row = $result->fetch_assoc()) {
        $existing_start = strtotime($row['start_date'] . ' ' . $row['start_time']);
        $existing_end = strtotime($row['start_date'] . ' ' . $row['end_time']);

        // Handle repeat
        $check_dates = [];
        if ($row['repeat_type'] == 'weekly') {
            $current = strtotime($row['start_date']);
            while ($current <= strtotime($row['repeat_until'])) {
                if (date('w', $current) == date('w', strtotime($start_date))) {
                    $check_dates[] = date('Y-m-d', $current);
                }
                $current = strtotime('+1 week', $current);
            }
        } else {
            $check_dates[] = $row['start_date'];
        }

        foreach ($check_dates as $cd) {
            if ($cd == $start_date) {
                if (
                    ($start_time < $row['end_time']) &&
                    ($end_time > $row['start_time'])
                ) {
                    $conflict = true;
                    break 2;
                }
            }
        }
    }

    if ($conflict) {
        $_SESSION['error'] = "Jadwal bentrok dengan jadwal lain di lab yang sama.";
        header("Location: edit_booking.php?id=$schedule_id");
        exit();
    }

    $stmt = $conn->prepare("UPDATE schedules SET users_id=?, class_id=?, subject=?, start_date=?, start_time=?, duration=?, end_time=?, repeat_type=?, repeat_until=? WHERE id=?");
    $stmt->bind_param("iisssssssi", $users_id, $class_id, $subject, $start_date, $start_time, $duration, $end_time, $repeat_type, $repeat_until, $schedule_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Jadwal berhasil diperbarui!";
    header("Location: data_schedule.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Booking</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="schedule">
    <div class="container-schedule">
        <h2>Edit Jadwal</h2>
        <form method="POST">
            <?php if (isset($_SESSION['error'])): ?>
                <p style="color:red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            <?php endif; ?>

            <div class="form-group">
                <label>Dosen</label>
                <select name="users_id" required>
                    <?php while ($dosen = mysqli_fetch_assoc($dosenList)): ?>
                        <option value="<?= $dosen['id']; ?>" <?= $dosen['id'] == $data['users_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dosen['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" value="<?= htmlspecialchars($data['subject']) ?>" required>
            </div>

            <div class="form-group">
                <label>Kode Kelas</label>
                <select name="class_id" required>
                    <?php while ($class = mysqli_fetch_assoc($classList)): ?>
                        <option value="<?= $class['id']; ?>" <?= $class['id'] == $data['class_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['kode_kelas']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="start_date" value="<?= $data['start_date'] ?>" required>
            </div>

            <div class="form-group">
                <label>Jam Mulai</label>
                <input type="time" name="start_time" value="<?= $data['start_time'] ?>" required>
            </div>

            <div class="form-group">
                <label>Durasi</label>
                <select name="duration" required>
                    <?php
                    $durasi_opsi = ['01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00'];
                    foreach ($durasi_opsi as $d): ?>
                        <option value="<?= $d ?>" <?= $d == $data['duration'] ? 'selected' : '' ?>>
                            <?= $d ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Repeat Type</label>
                <select name="repeat_type" id="repeat_type" onchange="toggleRepeatUntil()" required>
                    <option value="none" <?= $data['repeat_type'] == 'none' ? 'selected' : '' ?>>None</option>
                    <option value="weekly" <?= $data['repeat_type'] == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                </select>
            </div>

            <div class="form-group" id="repeat_until_group" style="display: <?= $data['repeat_type'] == 'weekly' ? 'block' : 'none' ?>;">
                <label>Repeat Until</label>
                <input type="date" name="repeat_until" value="<?= $data['repeat_until'] ?>">
            </div>

            <div class="form-group">
                <button type="submit" name="update_schedule">Update</button>
            </div>
        </form>
        <div class="button">
            <a href="data_schedule.php">← Kembali ke Daftar Jadwal</a>
        </div>
    </div>
    <script>
        function toggleRepeatUntil() {
            const repeatType = document.getElementById('repeat_type').value;
            const group = document.getElementById('repeat_until_group');
            group.style.display = (repeatType === 'weekly') ? 'block' : 'none';
        }
    </script>
</body>
</html>
