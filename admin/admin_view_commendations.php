<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

$departments = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

$filter = $_GET['department_id'] ?? '';

$sql = "SELECT c.*, r.first_name, r.last_name, d.name AS department_name
        FROM commendations c
        JOIN appointments a ON c.appointment_id = a.id
        JOIN residents r ON a.resident_id = r.id
        JOIN departments d ON a.department_id = d.id";

if ($filter) {
    $sql .= " WHERE d.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($sql);
}
$commendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="card shadow mb-3">
        <div class="card-header bg-success text-white">
            <i class='bx bx-like'></i> Personnel Commendations
        </div>
        <div class="card-body">
            <form id="filterForm" class="form-inline mb-3">
                <label class="mr-2">Filter by Department:</label>
                <select name="department_id" id="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($commendations): ?>
                <?php foreach ($commendations as $c): ?>
                    <div class="card mb-3 border-left-success">
                        <div class="card-body">
                            <h5><i class='bx bx-user'></i> <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></h5>
                            <p><strong>Employee:</strong> <?= htmlspecialchars($c['employee_name']) ?></p>
                            <p><strong>Office:</strong> <?= htmlspecialchars($c['office']) ?></p>
                            <p><strong>Service:</strong> <?= htmlspecialchars($c['service_requested']) ?></p>
                            <p><strong>Commendation:</strong> <?= nl2br(htmlspecialchars($c['commendation_text'])) ?></p>
                            <small class="text-muted"><i class='bx bx-calendar'></i> <?= htmlspecialchars($c['created_at']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class='bx bx-info-circle'></i> No commendations found for the selected department.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('filterForm').addEventListener('change', function () {
        const selectedDeptId = document.getElementById('department_id').value;
        const url = 'admin_view_commendations.php?department_id=' + encodeURIComponent(selectedDeptId);
        loadContent(url);
    });
</script>
