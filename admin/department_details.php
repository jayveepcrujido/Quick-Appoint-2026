<?php
require '../conn.php';

if (!isset($_GET['id'])) {
    die("No department selected.");
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    die("Department not found.");
}

$serviceStmt = $pdo->prepare("SELECT * FROM department_services WHERE department_id = ?");
$serviceStmt->execute([$id]);
$services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($department['acronym'] ?: $department['name']) ?> - <?= htmlspecialchars($department['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <a href="departments.php" class="btn btn-secondary mb-3">⬅ Back to Departments</a>

    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">
                <?= htmlspecialchars($department['acronym'] ?: $department['name']) ?> - <?= htmlspecialchars($department['name']) ?>
            </h5>
        </div>
        <div class="card-body">
            <p><strong>Acronym:</strong> <?= htmlspecialchars($department['acronym']) ?></p>
            <p><strong>Name:</strong> <?= htmlspecialchars($department['name']) ?></p>
            <p><strong>Description:</strong> <?= $department['description'] ? htmlspecialchars($department['description']) : '<em>No description provided.</em>' ?></p>
            <p><strong>Created At:</strong> <?= htmlspecialchars($department['created_at']) ?></p>

            <h6 class="mt-4">Services</h6>
            <ul class="list-unstyled">
                <?php if ($services): ?>
                    <?php foreach ($services as $svc): ?>
                        <li class="mb-2">🔹 <?= htmlspecialchars($svc['service_name']) ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No services available for this department.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-footer text-right">
            <a href="department_edit.php?id=<?= $department['id'] ?>" class="btn btn-warning">✏️ Edit</a>
        </div>
    </div>
</div>
</body>
</html>
