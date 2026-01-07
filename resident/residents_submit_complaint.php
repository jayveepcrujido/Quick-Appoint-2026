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
      AND a.complaint_status = 'pending'
";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $userId]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($appointments)) {
    echo "<div class='alert alert-warning'>You have no completed appointments eligible for complaint submission.</div>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $appointmentId = $_POST['appointment_id'] ?? null;
    $employeeName = $_POST['employee_name'] ?? '';
    $office = $_POST['office'] ?? '';
    $complaints = $_POST['complaint_type'] ?? [];
    $additional = $_POST['additional_details'] ?? '';

    if ($appointmentId && $employeeName && !empty($complaints)) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO complaints 
            (user_id, appointment_id, employee_name, office, complaint_type, additional_details)
            VALUES (:user_id, :appointment_id, :employee_name, :office, :complaint_type, :additional_details)");
            $stmtInsert->execute([
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
            'employee_name' => $employeeName,
            'office' => $office,
            'complaint_type' => implode(', ', $complaints),
            'additional_details' => $additional
        ]);


            $stmtUpdate = $pdo->prepare("UPDATE appointments SET complaint_status = 'done' WHERE id = :appointment_id");
            $stmtUpdate->execute(['appointment_id' => $appointmentId]);

            echo json_encode(['success' => true, 'message' => 'Complaint submitted successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please select an appointment and provide your complaint.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Complaints</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="p-4">
<!-- HTML -->
<div class="container my-4">
    <div class="card shadow-lg border-0 rounded-lg">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-exclamation-circle mr-2"></i> Complaint Form</h4>
            <button type="button" class="btn btn-light btn-sm" onclick="$('#content-area').load('residents_select_form.php')">
                ← Back to Form Selector
            </button>
        </div>

        <div class="card-body">
            <form id="complaint-form">
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
                    <input type="text" name="employee_name" class="form-control" placeholder="e.g., John Doe" required>
                </div>

                <div class="form-group">
                    <label for="office" class="font-weight-bold">Office:</label>
                    <input type="text" name="office" class="form-control" placeholder="e.g., Civil Registry">
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Nature of Complaint:</label>
                    <div class="border rounded p-3 bg-light">
                        <?php
                        $complaintTypes = [
                            "Discourteous Employee",
                            "Employee was not familiar with the data requested or needed",
                            "No employee was available to accommodate the request",
                            "Employee was biased in rendering services",
                            "Unreasonable waiting time",
                            "Office was disorganized",
                            "Document given was incomplete or incorrect"
                        ];
                        foreach ($complaintTypes as $index => $label): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="complaint_type[]" value="<?= $label ?>" id="c<?= $index ?>">
                                <label class="form-check-label" for="c<?= $index ?>"><?= $label ?></label>
                            </div>
                        <?php endforeach; ?>

                        <div class="form-group mt-3 mb-0">
                            <label class="mb-1">Other Complaint:</label>
                            <input type="text" class="form-control" name="complaint_type[]" placeholder="Specify other concern">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="additional_details" class="font-weight-bold">Additional Details:</label>
                    <textarea class="form-control" name="additional_details" rows="4" placeholder="Please describe the issue in detail..."></textarea>
                </div>

                <button type="submit" class="btn btn-danger btn-block py-2">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Complaint
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    $('#complaint-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'residents_submit_complaint.php',
            type: 'POST',
            data: $('#complaint-form').serialize() + '&ajax=true',
            success: function (res) {
                alert(res.message);
                if (res.success) {
                    $('#complaint-form')[0].reset();
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
