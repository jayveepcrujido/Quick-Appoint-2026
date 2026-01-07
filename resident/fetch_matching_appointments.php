<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Residents') {
    http_response_code(403);
    exit('Unauthorized access.');
}

if (!isset($_POST['date'])) {
    echo "<p class='text-danger'>No date selected.</p>";
    exit();
}

$userId = $_SESSION['user_id'];
$selectedDate = $_POST['date'];

$query = "SELECT COUNT(*) AS count FROM appointments 
          WHERE scheduled_for = :scheduled_for 
          AND user_id != :user_id 
          AND status IN ('Confirmed', 'Pending')";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'scheduled_for' => $selectedDate,
    'user_id' => $userId
]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $result ? $result['count'] : 0;

echo "
<table class='table table-bordered'>
    <thead class='thead-light'>
        <tr>
            <th>Selected Schedule</th>
            <th>Number of Other Appointments</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><span class='badge badge-info p-2'>" . date('F d, Y h:i A', strtotime($selectedDate)) . "</span></td>
            <td>" . ($count > 0 ? "<strong class='text-primary'>$count</strong>" : "<span class='text-muted'>None</span>") . "</td>
        </tr>
    </tbody>
</table>
";
?>
