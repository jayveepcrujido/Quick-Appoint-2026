<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Residents') {
    echo "<div class='alert alert-danger'>Unauthorized access.</div>";
    exit();
}

include '../conn.php';
$userId = $_SESSION['user_id'];

$query = "
    SELECT a.id AS appointment_id, d.name AS department_name, a.scheduled_for
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    WHERE a.user_id = :user_id
      AND a.status = 'Completed'
      AND a.commendation_status = 'pending'
";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $userId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($appointments)) {
    echo "<div class='alert alert-warning'>You have no completed appointments eligible for commendation submission.</div>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $appointmentId = $_POST['appointment_id'] ?? null;
    $employeeName = $_POST['employee_name'] ?? '';
    $office = $_POST['office'] ?? '';
    $service = $_POST['service_requested'] ?? '';
    $commendation = $_POST['commendation_text'] ?? '';

    if ($appointmentId && $employeeName && $commendation) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO commendations 
            (user_id, appointment_id, employee_name, office, service_requested, commendation_text)
            VALUES (:user_id, :appointment_id, :employee_name, :office, :service_requested, :commendation_text)");
            $stmtInsert->execute([
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
            'employee_name' => $employeeName,
            'office' => $office,
            'service_requested' => $service,
            'commendation_text' => $commendation
        ]);

            $stmtUpdate = $pdo->prepare("UPDATE appointments SET commendation_status = 'done' WHERE id = :appointment_id");
            $stmtUpdate->execute(['appointment_id' => $appointmentId]);

            echo json_encode(['success' => true, 'message' => 'Commendation submitted successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please fill out all required fields.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Commendation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="p-4"></body>
<div class="container my-4">
    <div class="card shadow-lg rounded-lg border-0">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-thumbs-up mr-2"></i> Commendation Form</h4>
            <button type="button" class="btn btn-light btn-sm" onclick="$('#content-area').load('residents_select_form.php')">
                ← Back to Form Selector
            </button>
        </div>

        <div class="card-body">
            <form id="commendation-form">
                <div class="form-group">
                    <label for="appointment_id" class="font-weight-bold">Select Completed Appointment:</label>
                    <select name="appointment_id" class="form-control custom-select" required>
                        <option value="">-- Select Appointment --</option>
                        <?php foreach ($appointments as $appt): ?>
                            <option value="<?= $appt['appointment_id'] ?>">
                                <?= $appt['department_name'] . ' - ' . date('F d, Y h:i A', strtotime($appt['scheduled_for'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="employee_name" class="font-weight-bold">Name of Employee:</label>
                    <input type="text" name="employee_name" class="form-control" placeholder="e.g., Juan Dela Cruz" required>
                </div>

                <div class="form-group">
                    <label for="office" class="font-weight-bold">Office:</label>
                    <input type="text" name="office" class="form-control" placeholder="e.g., Civil Registry">
                </div>

                <div class="form-group">
                    <label for="service_requested" class="font-weight-bold">Service Requested / Data:</label>
                    <input type="text" name="service_requested" class="form-control" placeholder="e.g., Birth Certificate Issuance">
                </div>

                <div class="form-group">
                    <label for="commendation_text" class="font-weight-bold">Brief Narration of Commendable Act:</label>
                    <textarea name="commendation_text" rows="4" class="form-control" placeholder="Describe what made the employee commendable..." required></textarea>
                </div>

                <button type="submit" class="btn btn-success btn-block py-2 mt-3">
                    <i class="fas fa-paper-plane"></i> Submit Commendation
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    $('#commendation-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'residents_submit_commendation.php',
            type: 'POST',
            data: $('#commendation-form').serialize() + '&ajax=true',
            success: function (res) {
                alert(res.message);
                if (res.success) {
                    $('#commendation-form')[0].reset();
                    $('#content-area').load('residents_select_form.php');
                }
            },
            error: function () {
                alert("An unexpected error occurred.");
            }
        });
    });
</script>
</body>
</html>
