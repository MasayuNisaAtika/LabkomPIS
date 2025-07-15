<?php
include '../conn.php';
include 'sidebar_dosen.php';
session_start();

// Ambil lab_id dari URL
$lab_id = $_GET['lab_id'] ?? '';
$lab_name = '';

// Cek dan ambil nama lab
if ($lab_id) {
    $stmt = $conn->prepare("SELECT name FROM labs WHERE id = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $stmt->bind_result($lab_name);
    $stmt->fetch();
    $stmt->close();
}

// Ambil data kelas
$classes = $conn->query("SELECT id, kode_kelas FROM classes ORDER BY kode_kelas ASC");

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $user_id = $_POST['users_id'];
    $subject = trim($_POST['subject']);
    $class_id = $_POST['class_id'];
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $repeat_type = $_POST['repeat_type'];
    $repeat_until = ($repeat_type === 'none') ? $start_date : ($_POST['repeat_until'] ?? $start_date);

    // Hitung end_time dan duration dalam menit
    $parts = explode(':', $duration);
    $hours = (int)$parts[0];

    $start_timestamp = strtotime("$start_date $start_time");
    $end_timestamp = strtotime("+{$hours} hours", $start_timestamp);
    $end_time = date("H:i", $end_timestamp);

    // Cek bentrok
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
        header("Location: add.php?lab_id=$lab_id&date=$start_date&start_time=$start_time");
        exit();
    }

    // Simpan jadwal
    $stmt = $conn->prepare("INSERT INTO schedules 
        (users_id, lab_id, subject, class_id, start_date, start_time, duration, end_time, repeat_type, repeat_until) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        $_SESSION['error'] = "Prepare gagal: " . $conn->error;
        header("Location: add.php?lab_id=$lab_id&date=$start_date&start_time=$start_time");
        exit();
    }

    $stmt->bind_param(
    "iissssisss",
    $user_id,
    $lab_id,
    $subject,
    $class_id,
    $start_date,
    $start_time,
    $hours, // ← ini nilai duration dalam jam (int)
    $end_time,
    $repeat_type,
    $repeat_until
    );


    if ($stmt->execute()) {
        $_SESSION['success'] = "Jadwal berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan jadwal: " . $stmt->error;
    }

    header("Location: dashboard.php?lab_id=$lab_id&date=$start_date&start_time=$start_time");
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
            if (isset($_SESSION['error'])) {
                echo "<span style='color:red'>" . $_SESSION['error'] . "</span>";
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo "<span style='color:green'>" . $_SESSION['success'] . "</span>";
                unset($_SESSION['success']);
            }
            ?>
            </p>

            <?php
            $currentUserId = $_SESSION['user_id'] ?? 0;
            $currentUname = '';

            if ($currentUserId) {
                $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->bind_param("i", $currentUserId);
                $stmt->execute();
                $stmt->bind_result($currentUname);
                $stmt->fetch();
                $stmt->close();
            }

            ?>
            <div class="form-group">
                <label>Dosen</label>
                <input type="text" value="<?= htmlspecialchars($currentUname); ?>" disabled>
                <input type="hidden" name="users_id" value="<?= $currentUserId; ?>">
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
                <label>Date</label>
                <input type="text" name="start_date" id="start_date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" readonly>
            </div>

            <div class="form-group">
                <label>Start Time</label>
                <input type="text" name="start_time" id="start_time" value="<?= htmlspecialchars($_GET['start_time'] ?? '') ?>" readonly>
            </div>

            <div class="form-group">
                <label>Duration</label>
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

        // Trigger pengisian awal jika date dari URL tersedia
        if (document.getElementById('start_date').value !== '') {
            generateTimeOptions();
        }
    </script>
</body>
</html>
