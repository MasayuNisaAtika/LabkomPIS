<?php
include '../conn.php';
include 'sidebar_admin.php';
session_start();


$lab_id = $_GET['lab_id'] ?? '';
$lab_name = '';
if ($lab_id) {
  $stmt = $conn->prepare("SELECT name FROM labs WHERE id = ?");
  $stmt->bind_param("i", $lab_id);
  $stmt->execute();
  $stmt->bind_result($lab_name);
  $stmt->fetch();
  $stmt->close();
}

$users = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'dosen'");
$classes = mysqli_query($conn, "SELECT id, kode_kelas FROM classes");

if (isset($_POST["add_schedule"])) {
    $lab_id = $_POST['lab_id'];
    $users_id = $_POST['users_id'];
    $class_id = $_POST['class_id'];
    $subject = htmlspecialchars(trim($_POST['subject']));
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $repeat_type = $_POST['repeat_type'];
    $repeat_until = ($repeat_type === 'none') ? $start_date : ($_POST['repeat_until'] ?? $start_date);

    $start_timestamp = strtotime("$start_date $start_time");
    $parts = explode(':', $duration);
    $end_timestamp = strtotime("+{$parts[0]} hours +{$parts[1]} minutes", $start_timestamp);
    $end_time = date("H:i", $end_timestamp);

    // Cek bentrok sebelum simpan
    $cek = $conn->prepare("SELECT * FROM schedules WHERE lab_id = ?");
    $cek->bind_param("i", $lab_id);
    $cek->execute();
    $res = $cek->get_result();

    $conflict = false;
    while ($row = $res->fetch_assoc()) {
        $existing_type = $row['repeat_type'];
        $existing_start_date = $row['start_date'];
        $existing_repeat_until = $row['repeat_until'];
        $existing_start_time = $row['start_time'];
        $existing_end_time = $row['end_time'];

        $is_active_on_date = false;
        if ($existing_type === 'none' && $existing_start_date === $start_date) {
            $is_active_on_date = true;
        } elseif ($existing_type === 'weekly') {
            $day_match = date('w', strtotime($existing_start_date)) == date('w', strtotime($start_date));
            $in_range = ($existing_start_date <= $start_date && $start_date <= $existing_repeat_until);
            if ($day_match && $in_range) {
                $is_active_on_date = true;
            }
        }

        if ($is_active_on_date) {
            if (
                ($start_time < $existing_end_time) &&
                ($end_time > $existing_start_time)
            ) {
                $conflict = true;
                break;
            }
        }
    }

    if ($conflict) {
        $_SESSION['error'] = "Jadwal bentrok dengan jadwal lain di lab yang sama.";
        header("Location: add_booking.php?lab_id=$lab_id&date=$start_date&start_time=$start_time");
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO schedules (lab_id, users_id, class_id, subject, start_date, start_time, duration, end_time, repeat_type, repeat_until)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisssssss", $lab_id, $users_id, $class_id, $subject, $start_date, $start_time, $duration, $end_time, $repeat_type, $repeat_until);
        $stmt->execute();
        $conn->commit();
        $_SESSION['success'] = "Schedule added successfully!";
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error'] = "Failed to add schedule: " . $e->getMessage();
    }
    // Hitung week_offset berdasarkan tanggal start_date
    $today = date('Y-m-d');
    $selected_monday = date('Y-m-d', strtotime("monday this week", strtotime($start_date)));
    $current_monday = date('Y-m-d', strtotime("monday this week", strtotime($today)));
    $diff_days = (strtotime($selected_monday) - strtotime($current_monday)) / (60 * 60 * 24);
    $week_offset = (int)($diff_days / 7);

    // Redirect balik ke halaman minggu dan lab yang sama
    header("Location: dashboard.php?lab_id=$lab_id&week_offset=$week_offset");
    exit();

    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="schedule">
    <div class="container-schedule">
        <h2>Add Schedule (<?= htmlspecialchars($lab_name) ?>)</h2>
        <form method="POST">
            <p>
            <?php
            if (isset($_SESSION['error'])) { echo "<span style='color:red'>" . $_SESSION['error'] . "</span>"; unset($_SESSION['error']); }
            if (isset($_SESSION['success'])) { echo "<span style='color:green'>" . $_SESSION['success'] . "</span>"; unset($_SESSION['success']); }
            ?>
            </p>
            <input type="hidden" name="lab_id" value="<?= htmlspecialchars($lab_id) ?>">
            <div class="form-group">
                <label>Dosen</label>
                <select name="users_id" required>
                    <option value="">-- Select Dosen --</option>
                    <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <option value="<?= $user['id']; ?>"><?= htmlspecialchars($user['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" placeholder="Mata Kuliah / Acara" required>
            </div>
            <div class="form-group">
                <label>Kode Kelas</label>
                <select name="class_id" required>
                    <option value="">-- Select Kelas --</option>
                    <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                        <option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['kode_kelas']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tanggal</label>
                <input type="text" name="start_date" id="start_date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" readonly>
            </div>
            <div class="form-group">
                <label>Jam Mulai</label>
                <input type="text" name="start_time" id="start_time" value="<?= htmlspecialchars($_GET['start_time'] ?? '') ?>" readonly>
            </div>
            <div class="form-group">
                <label>Durasi</label>
                <select name="duration" required>
                    <option value="01:00">1 hour</option>
                    <option value="02:00">2 hours</option>
                    <option value="03:00">3 hours</option>
                    <option value="04:00">4 hours</option>
                    <option value="05:00">5 hours</option>
                    <option value="06:00">6 hours</option>
                    <option value="07:00">7 hours</option>
                    <option value="08:00">8 hours</option>
                </select>
            </div>
            <div class="form-group">
                <label>Repeat Type</label>
                <select name="repeat_type" id="repeat_type" onchange="toggleRepeatUntil()" required>
                    <option value="">-- Select Repeat Type --</option>
                    <option value="none">None</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div class="form-group" id="repeat_until_group" style="display: none;">
                <label>Repeat Until</label>
                <input type="date" name="repeat_until">
            </div>
            <div class="form-group">
                <button type="submit" name="add_schedule">Add Schedule</button>
            </div>
        </form>
        <div class="button">
            <a href="dashboard.php">← Back to Schedule List</a>
        </div>
    </div>
    <script>
        function toggleRepeatUntil() {
            const repeatType = document.getElementById('repeat_type').value;
            const repeatUntilGroup = document.getElementById('repeat_until_group');
            repeatUntilGroup.style.display = (repeatType === 'none') ? 'none' : 'block';
        }
    </script>
</body>
</html>
