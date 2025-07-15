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

    // Ambil lab_id dan start_date dari schedule_id
    $stmt_info = $conn->prepare("SELECT lab_id, start_date FROM schedules WHERE id = ?");
    $stmt_info->bind_param("i", $schedule_id);
    $stmt_info->execute();
    $stmt_info->bind_result($lab_id, $start_date);
    $stmt_info->fetch();
    $stmt_info->close();

    // Hitung week_offset
    $today = date('Y-m-d');
    $selected_monday = date('Y-m-d', strtotime("monday this week", strtotime($start_date)));
    $current_monday = date('Y-m-d', strtotime("monday this week", strtotime($today)));
    $diff_days = (strtotime($selected_monday) - strtotime($current_monday)) / (60 * 60 * 24);
    $week_offset = (int)($diff_days / 7);

    // Redirect ke dashboard minggu dan lab yang sesuai
    header("Location: dashboard.php?lab_id=$lab_id&week_offset=$week_offset&status=success&message=Data+berhasil+disimpan");
    exit;

    } else {
        die("Invalid request");
}
?>
