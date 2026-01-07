<?php
include '../conn.php';

if (!isset($_GET['department_id'])) {
    exit('Missing department_id');
}

$departmentId = $_GET['department_id'];
$stmt = $pdo->prepare("SELECT id, service_name FROM department_services WHERE department_id = ?");
$stmt->execute([$departmentId]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($services as $s) {
    echo "<option value='" . htmlspecialchars($s['id']) . "'>" . htmlspecialchars($s['service_name']) . "</option>";
}
