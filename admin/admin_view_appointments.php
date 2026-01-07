<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

$departments = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

$query = "
    SELECT a.id, a.status, a.scheduled_for, a.reason, a.requested_at,
           r.first_name, r.last_name,
           d.name AS department_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    JOIN departments d ON a.department_id = d.id
    ORDER BY r.last_name ASC, a.scheduled_for ASC
    LIMIT 10
";
$recentAppointments = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .appointments-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background: white;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 25px;
            border: none;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .card-header-custom i {
            font-size: 1.5rem;
            margin-right: 10px;
            vertical-align: middle;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .filter-section label {
            color: white;
            font-weight: 500;
            margin-right: 10px;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }

        .filter-section select {
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem; /* balanced padding */
            font-size: 0.95rem;
            line-height: 1.4; /* ensures proper vertical centering */
            height: auto; /* let it adjust naturally */
            background: white;
            appearance: none; /* optional: for consistent styling across browsers */
            -webkit-appearance: none;
            -moz-appearance: none;
            box-sizing: border-box;
        }


        .filter-section select:focus {
            border-color: white;
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
            outline: none;
        }

        .filter-section .row {
            margin: 0;
        }

        .filter-section .col-md-6 {
            padding: 0 10px;
        }

        .filter-section .col-md-6:first-child {
            padding-left: 0;
        }

        .filter-section .col-md-6:last-child {
            padding-right: 0;
        }

        .table-container {
            padding: 30px;
        }

        .appointments-table {
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            border-radius: 10px;
            overflow: hidden;
        }

        .appointments-table thead th {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 18px 15px;
            border: none;
            white-space: nowrap;
        }

        .appointments-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e9ecef;
        }

        .appointments-table tbody tr:hover {
            background-color: #e3f2fd;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.15);
        }

        .appointments-table tbody td {
            padding: 18px 15px;
            vertical-align: middle;
            color: #495057;
            font-size: 0.95rem;
        }

        .resident-name {
            font-weight: 600;
            color: #1565c0;
        }

        .department-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .badge-pending {
            background: #bbdefb;
            color: #0d47a1;
        }

        .badge-approved {
            background: #64b5f6;
            color: #ffffff;
        }

        .badge-declined {
            background: #90caf9;
            color: #01579b;
        }

        .badge-completed {
            background: #1976d2;
            color: #ffffff;
        }

        .date-text {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .date-time {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .date-time .date {
            font-weight: 600;
            color: #495057;
        }

        .date-time .time {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            color: #90caf9;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .appointments-table {
                font-size: 0.9rem;
            }
            
            .appointments-table thead th,
            .appointments-table tbody td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 992px) {
            .card-header-custom {
                padding: 20px;
            }

            .table-container {
                padding: 20px;
            }

            .appointments-table thead th {
                font-size: 0.75rem;
                padding: 12px 8px;
            }

            .appointments-table tbody td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 10px;
            }

            .card-header-custom h5 {
                font-size: 1.2rem;
            }

            .filter-section {
                padding: 12px;
            }

            .filter-section select {
                max-width: 100%;
            }

            .table-container {
                padding: 15px;
                overflow-x: auto;
            }

            .appointments-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .appointments-table thead th,
            .appointments-table tbody td {
                padding: 10px 8px;
                font-size: 0.8rem;
            }

            .status-badge {
                padding: 6px 12px;
                font-size: 0.75rem;
            }

            .department-badge {
                padding: 5px 10px;
                font-size: 0.75rem;
            }

            .date-time .date {
                font-size: 0.85rem;
            }

            .date-time .time {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .card-header-custom {
                padding: 15px;
            }

            .card-header-custom h5 {
                font-size: 1rem;
            }

            .card-header-custom i {
                font-size: 1.2rem;
            }

            .appointments-table thead th {
                font-size: 0.7rem;
                padding: 8px 6px;
            }

            .appointments-table tbody td {
                font-size: 0.75rem;
                padding: 10px 6px;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-state i {
                font-size: 3rem;
            }
        }

        /* Smooth loading animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .appointments-card {
            animation: fadeIn 0.5s ease-out;
        }
        .filter-section input[type="date"] {
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    line-height: 1.4;
    height: auto;
    background: white;
    box-sizing: border-box;
}

.filter-section input[type="date"]:focus {
    border-color: white;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
    outline: none;
}
    </style>
</head>
<body>
<div class="main-container">
    <div class="card appointments-card">
        <div class="card-header-custom">
            <h5><i class='bx bx-calendar-event'></i> View Appointments</h5>
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="departmentFilter">Filter by Department:</label>
                        <select id="departmentFilter" class="form-control" onchange="filterAppointments()">
                            <option value="">-- All Departments --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="statusFilter">Filter by Status:</label>
                        <select id="statusFilter" class="form-control" onchange="filterAppointments()">
                            <option value="">-- All Status --</option>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="startDate">Start Date:</label>
                        <input type="date" id="startDate" class="form-control" onchange="filterAppointments()">
                    </div>
                    <div class="col-md-6">
                        <label for="endDate">End Date:</label>
                        <input type="date" id="endDate" class="form-control" onchange="filterAppointments()">
                    </div>
                </div>
            </div>
        </div>
        <div class="table-container">
            <div id="appointmentsTable">
                <div class="table-responsive">
                    <table class="table appointments-table">
                        <thead>
                            <tr>
                                <th>Resident Name</th>
                                <th>Department</th>
                                <th>Reason</th>
                                <th>Scheduled For</th>
                                <th>Status</th>
                                <th>Requested At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAppointments)): ?>
                                <?php foreach ($recentAppointments as $row): ?>
                                    <tr>
                                        <td><span class="resident-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span></td>
                                        <td><span class="department-badge"><?= htmlspecialchars($row['department_name']) ?></span></td>
                                        <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="date-time">
                                                <span class="date"><?= date('F j, Y', strtotime($row['scheduled_for'] ?? '')) ?></span>
                                                <span class="time"><?= date('g:i A', strtotime($row['scheduled_for'] ?? '')) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                                $status = htmlspecialchars($row['status']);
                                                $badgeClass = match($status) {
                                                    'Pending' => 'badge-pending',
                                                    'Approved' => 'badge-approved',
                                                    'Declined' => 'badge-declined',
                                                    'Completed' => 'badge-completed',
                                                    default => 'badge-secondary'
                                                };
                                            ?>
                                            <span class="status-badge <?= $badgeClass ?>"><?= $status ?></span>
                                        </td>
                                        <td>
                                            <div class="date-time">
                                                <span class="date"><?= date('M d, Y', strtotime($row['requested_at'])) ?></span>
                                                <span class="time"><?= date('g:i A', strtotime($row['requested_at'])) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class='bx bx-calendar-x'></i>
                                            <h5>No Appointments Found</h5>
                                            <p>There are no appointments to display at this time.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function filterAppointments() {
        const departmentId = $('#departmentFilter').val();
        const status = $('#statusFilter').val();
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        $.ajax({
            url: 'ajax/ajax_get_appointments_by_department.php',
            method: 'GET',
            data: { 
                department_id: departmentId,
                status: status,
                start_date: startDate,
                end_date: endDate
            },
            success: function (data) {
                let html = `
                    <div class="table-responsive">
                        <table class="table appointments-table">
                            <thead>
                                <tr>
                                    <th>Resident Name</th>
                                    <th>Department</th>
                                    <th>Reason</th>
                                    <th>Scheduled For</th>
                                    <th>Status</th>
                                    <th>Requested At</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                if (data.length > 0) {
                    data.forEach(row => {
                        const statusClass = {
                            'Pending': 'badge-pending',
                            'Approved': 'badge-approved',
                            'Declined': 'badge-declined',
                            'Completed': 'badge-completed'
                        }[row.status] || 'badge-secondary';

                        html += `
                            <tr>
                                <td><span class="resident-name">${row.first_name} ${row.last_name}</span></td>
                                <td><span class="department-badge">${row.department_name}</span></td>
                                <td>${row.reason || 'N/A'}</td>
                                <td>
                                    <div class="date-time">
                                        <span class="date">${row.scheduled_for || 'N/A'}</span>
                                    </div>
                                </td>
                                <td><span class="status-badge ${statusClass}">${row.status}</span></td>
                                <td>
                                    <div class="date-time">
                                        <span class="date">${row.requested_at}</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html += `
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class='bx bx-calendar-x'></i>
                                    <h5>No Appointments Found</h5>
                                    <p>There are no appointments matching the selected filters.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }

                html += '</tbody></table></div>';
                $('#appointmentsTable').html(html);
            },
            error: function () {
                alert('Failed to fetch appointments. Please try again.');
            }
        });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>