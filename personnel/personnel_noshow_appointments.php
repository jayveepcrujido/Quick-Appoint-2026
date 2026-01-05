<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

// Get personnel's department
$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);
$department_id = $personnel['department_id'];

if (!$department_id) {
    die("No department assigned!");
}

// Auto-mark No Show
$updateStmt = $pdo->prepare("UPDATE appointments SET status = 'No Show' WHERE status = 'Pending' AND scheduled_for < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND department_id = ?");
$updateStmt->execute([$department_id]);

// Fetch No-Show Appointments
$noShowQuery = "SELECT a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at, a.available_date_id, r.first_name, r.middle_name, r.last_name, r.address, r.phone_number, au.email, ds.service_name FROM appointments a JOIN residents r ON a.resident_id = r.id JOIN auth au ON r.auth_id = au.id LEFT JOIN department_services ds ON a.service_id = ds.id WHERE a.department_id = ? AND a.status = 'No Show' ORDER BY a.scheduled_for DESC";
$noShowStmt = $pdo->prepare($noShowQuery);
$noShowStmt->execute([$department_id]);
$noShowAppointments = $noShowStmt->fetchAll(PDO::FETCH_ASSOC);

// No-Show Stats
$statsQuery = $pdo->prepare("SELECT COUNT(*) as total_noshow, COUNT(CASE WHEN DATE(scheduled_for) = CURDATE() THEN 1 END) as today_noshow, COUNT(CASE WHEN YEARWEEK(scheduled_for) = YEARWEEK(NOW()) THEN 1 END) as week_noshow, COUNT(CASE WHEN MONTH(scheduled_for) = MONTH(NOW()) AND YEAR(scheduled_for) = YEAR(NOW()) THEN 1 END) as month_noshow FROM appointments WHERE department_id = ? AND status = 'No Show'");
$statsQuery->execute([$department_id]);
$noShowStats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Fetch Reschedule Requests
$stmt = $pdo->prepare("
    SELECT 
        rr.id, rr.appointment_id, rr.old_scheduled_for, rr.requested_schedule, 
        rr.reason, rr.created_at, rr.status,
        a.transaction_id, 
        CONCAT(r.first_name, ' ', r.last_name) as resident_name,
        r.phone_number,
        s.service_name
    FROM reschedule_requests rr
    JOIN appointments a ON rr.appointment_id = a.id
    JOIN residents r ON rr.resident_id = r.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.department_id = ? 
    ORDER BY FIELD(rr.status, 'Pending', 'Approved', 'Rejected'), rr.created_at DESC
");
$stmt->execute([$department_id]);
$rescheduleRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary: linear-gradient(135deg, #0D92F4, #27548A);
            --secondary: #764ba2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            padding-bottom: 3rem;
        }

        .container-fluid {
            max-width: 1600px;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .page-header h2 {
            color: white;
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
        }

        .nav-tabs {
            display: flex !important;     
            flex-direction: row !important;    
            flex-wrap: nowrap !important;     
            width: auto !important;           
            border-bottom: none;
            margin-bottom: 2rem;
            gap: 15px;                      
            background: transparent;
        }
        .nav-tabs .nav-item {
            flex: 0 0 auto !important;         
            width: auto !important;            
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            background: white;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .nav-tabs .nav-link:hover {
            border-color: #0D92F4;
            color: #0D92F4;
            background: #f0f7ff;
        }

        .nav-tabs .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(13, 146, 244, 0.3);
        }

        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 5px solid transparent;
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.pending { border-color: var(--warning); }
        .stat-card.approved { border-color: var(--success); }
        .stat-card.rejected { border-color: var(--danger); }
        .stat-card.noshow { border-color: #e74c3c; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--bg-light);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #eee;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-group .btn {
            border-radius: 20px;
            font-size: 0.9rem;
            padding: 0.5rem 1.2rem;
            border: 1px solid #e0e0e0;
            background: white;
            color: var(--text-muted);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-group .btn.active,
        .filter-group .btn:hover {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            border-color: transparent;
        }

        /* Table Styles */
        .table-responsive {
            margin: 0;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .custom-table thead th {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            font-weight: 700;
            white-space: nowrap;
            border: none;
        }

        .custom-table tbody td {
            vertical-align: middle;
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .custom-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .custom-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-success {
            background-color: #d4edda !important;
            transition: background-color 1s ease;
        }

        /* Resident Info */
        .resident-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .resident-meta small {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Schedule Change */
        .schedule-change {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .date-box {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            min-width: 90px;
            text-align: center;
        }
        
        .date-box.old { 
            border-left: 3px solid var(--danger);
            background: #fff5f5;
        }
        
        .date-box.new { 
            border-left: 3px solid var(--success);
            background: #f0fdf4;
        }

        .date-box .font-weight-bold {
            display: block;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }

        .date-box small {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .period-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .date-box.old .period-badge {
            background: #ffe0e0;
            color: #c62828;
        }

        .date-box.new .period-badge {
            background: #c8e6c9;
            color: #2e7d32;
        }

        /* Reason Column */
        .reason-cell {
            max-width: 250px;
        }
        
        .reason-text {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .status-badge.pending { 
            background: #fff3cd; 
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-badge.approved { 
            background: #d4edda; 
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge.rejected { 
            background: #f8d7da; 
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Action Buttons */
        .action-cell {
            white-space: nowrap;
            text-align: right;
        }

        .btn-sm-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-sm-action:hover { 
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .btn-sm-action:active {
            transform: scale(0.95);
        }

        .btn-approve-sm { 
            background: #e0f2f1; 
            color: var(--success);
        }
        
        .btn-approve-sm:hover {
            background: var(--success);
            color: white;
        }

        .btn-reject-sm { 
            background: #ffebee; 
            color: var(--danger);
        }
        
        .btn-reject-sm:hover {
            background: var(--danger);
            color: white;
        }

        /* Date Cards for Reschedule Modal */
        .dates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .date-card {
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            padding: 1rem;
            cursor: default;
            background: white;
            position: relative;
            transition: all 0.2s;
        }

        .date-card.active-date {
            border-color: #3498db;
            background: #f0f7ff;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.2);
        }

        .time-slot-option {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            margin-top: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            background: #f8f9fa;
        }

        .time-slot-option:hover {
            background: #e2e6ea;
            border-color: #adb5bd;
        }

        .time-slot-option.selected {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
            font-weight: bold;
        }

        .time-slot-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Floating Alert */
        .floating-alert {
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Tablet Responsive */
        @media (max-width: 991px) {
            .container-fluid {
                padding: 1rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }

            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .custom-table {
                min-width: 800px;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 767px) {
            .container-fluid {
                padding: 0.5rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .page-header {
                padding: 1rem;
            }

            .custom-table thead {
                display: none;
            }

            .custom-table tbody tr {
                display: block;
                background: white;
                margin-bottom: 1rem;
                border-radius: 12px;
                border: 1px solid #eee;
                box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            }

            .custom-table tbody td {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                gap: 1rem;
                border-bottom: 1px solid #f8f9fa;
                padding: 0.8rem 1rem;
                text-align: left;
            }

            .custom-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                font-size: 0.75rem;
                text-align: left;
                min-width: 120px;
                flex-shrink: 0;
            }

            .schedule-change {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .fa-arrow-right {
                transform: rotate(90deg);
                margin: 5px 0;
            }

            .reason-cell {
                max-width: 100%;
            }

            .reason-text {
                white-space: normal;
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check"></i> Appointments Management</h2>
        <p>Manage reschedule requests and no-show appointments</p>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#noshow-tab">
                <i class="fas fa-user-times"></i> No-Show Appointments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#reschedule-tab">
                <i class="fas fa-calendar-alt"></i> Reschedule Requests
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- RESCHEDULE REQUESTS TAB -->
        <div id="reschedule-tab" class="tab-pane fade">
            <div class="stats-container">
                <div class="stat-card pending">
                    <div class="stat-icon" style="color: var(--warning)">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4 id="statPending" class="mb-0 font-weight-bold">0</h4>
                        <small class="text-muted">Pending Requests</small>
                    </div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon" style="color: var(--success)">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h4 id="statApproved" class="mb-0 font-weight-bold">0</h4>
                        <small class="text-muted">Approved</small>
                    </div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-icon" style="color: var(--danger)">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <h4 id="statRejected" class="mb-0 font-weight-bold">0</h4>
                        <small class="text-muted">Rejected</small>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-calendar-alt text-primary mr-2"></i>Reschedule Requests
                    </h5>
                    
                    <div class="filter-group">
                        <button class="btn active" data-filter="all">All</button>
                        <button class="btn" data-filter="Pending">Pending</button>
                        <button class="btn" data-filter="Approved">Approved</button>
                        <button class="btn" data-filter="Rejected">Rejected</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table custom-table" id="rescheduleTable">
                        <thead>
                            <tr>
                                <th>Resident / Service</th>
                                <th>Schedule Change</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rescheduleRequests)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                        <p class="text-muted">No reschedule requests found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rescheduleRequests as $request): ?>
                                    <tr class="request-row" data-id="<?= $request['id'] ?>" data-status="<?= $request['status'] ?>">
                                        <td data-label="Resident Info">
                                            <div class="font-weight-bold text-dark">
                                                <?= htmlspecialchars($request['resident_name']) ?>
                                            </div>
                                            <div class="resident-meta">
                                                <small><i class="fas fa-phone-alt fa-xs"></i> <?= htmlspecialchars($request['phone_number']) ?></small>
                                                <small class="text-primary mt-1">
                                                    <span class="badge badge-light border">#<?= htmlspecialchars($request['transaction_id']) ?></span> 
                                                    <?= htmlspecialchars($request['service_name']) ?>
                                                </small>
                                            </div>
                                        </td>

                                        <td data-label="Schedule Change">
                                            <div class="schedule-change">
                                                <?php 
                                                    $oldHour = date('H', strtotime($request['old_scheduled_for']));
                                                    $newHour = date('H', strtotime($request['requested_schedule']));
                                                    $oldPeriod = $oldHour < 12 ? 'Morning' : 'Afternoon';
                                                    $newPeriod = $newHour < 12 ? 'Morning' : 'Afternoon';
                                                ?>
                                                <div class="date-box old" title="Original Schedule">
                                                    <div class="font-weight-bold"><?= date('M d', strtotime($request['old_scheduled_for'])) ?></div>
                                                    <small class="period-badge"><?= $oldPeriod ?></small>
                                                </div>
                                                <i class="fas fa-arrow-right text-muted"></i>
                                                <div class="date-box new" title="Requested Schedule">
                                                    <div class="font-weight-bold"><?= date('M d', strtotime($request['requested_schedule'])) ?></div>
                                                    <small class="period-badge"><?= $newPeriod ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <td data-label="Reason" class="reason-cell">
                                            <span class="reason-text" title="<?= htmlspecialchars($request['reason']) ?>">
                                                <?= htmlspecialchars($request['reason']) ?>
                                            </span>
                                        </td>

                                        <td data-label="Status">
                                            <span class="status-badge <?= strtolower($request['status']) ?>">
                                                <?php if ($request['status'] === 'Pending'): ?>
                                                    <i class="fas fa-clock"></i> Pending
                                                <?php elseif ($request['status'] === 'Approved'): ?>
                                                    <i class="fas fa-check"></i> Approved
                                                <?php else: ?>
                                                    <i class="fas fa-times"></i> Rejected
                                                <?php endif; ?>
                                            </span>
                                        </td>

                                        <td data-label="Actions" class="action-cell">
                                            <?php if ($request['status'] === 'Pending'): ?>
                                                <button class="btn-sm-action btn-approve-sm" onclick="handleRequest(<?= $request['id'] ?>, 'Approved', this)" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn-sm-action btn-reject-sm" onclick="handleRequest(<?= $request['id'] ?>, 'Rejected', this)" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- NO-SHOW APPOINTMENTS TAB -->
        <div id="noshow-tab" class="tab-pane fade show active">
            <div class="stats-container">
                <div class="stat-card noshow">
                    <div class="stat-icon" style="color: #e74c3c">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h4 id="statTotalNoShow" class="mb-0 font-weight-bold"><?= $noShowStats['total_noshow'] ?></h4>
                        <small class="text-muted">Total No-Show</small>
                    </div>
                </div>
                <div class="stat-card noshow">
                    <div class="stat-icon" style="color: #e74c3c">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold"><?= $noShowStats['today_noshow'] ?></h4>
                        <small class="text-muted">Today</small>
                    </div>
                </div>
                <div class="stat-card noshow">
                    <div class="stat-icon" style="color: #e74c3c">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold"><?= $noShowStats['week_noshow'] ?></h4>
                        <small class="text-muted">This Week</small>
                    </div>
                </div>
                <div class="stat-card noshow">
                    <div class="stat-icon" style="color: #e74c3c">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 font-weight-bold"><?= $noShowStats['month_noshow'] ?></h4>
                        <small class="text-muted">This Month</small>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-user-times text-danger mr-2"></i>No-Show Appointments
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table custom-table" id="noShowTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Resident Name</th>
                                <th>Service</th>
                                <th>Missed Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="noShowBody">
                            <?php if (empty($noShowAppointments)): ?>
                                <tr id="noRecordsRow">
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                        <p class="text-muted">No no-show appointments found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($noShowAppointments as $app): ?>
                                    <tr id="row_<?= $app['id'] ?>">
                                        <td data-label="Transaction ID">
                                            <span class="badge badge-danger"><?= $app['transaction_id'] ?></span>
                                        </td>
                                        <td data-label="Resident Name">
                                            <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>
                                        </td>
                                        <td data-label="Service">
                                            <?= htmlspecialchars($app['service_name']) ?>
                                        </td>
                                        <td data-label="Missed Date">
                                            <i class="fas fa-calendar-day text-danger mr-1"></i>
                                            <?= date('M j, Y', strtotime($app['scheduled_for'])) ?><br>
                                            <small class="text-muted">
                                                <?= ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? 'Morning' : 'Afternoon' ?>
                                            </small>
                                        </td>
                                        <td data-label="Actions" class="text-center">
                                            <?php 
                                                $curDate = date('M j, Y', strtotime($app['scheduled_for']));
                                                $curTime = ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? ' (Morning)' : ' (Afternoon)';
                                                $fullCurrentStr = $curDate . $curTime;
                                            ?>
                                            <button class="btn btn-sm btn-warning btn-open-reschedule"
                                                    data-id="<?= $app['id'] ?>"
                                                    data-old-date-id="<?= $app['available_date_id'] ?>"
                                                    data-old-time="<?= $app['scheduled_for'] ? date('H:i:s', strtotime($app['scheduled_for'])) : '' ?>"
                                                    data-current-date="<?= $fullCurrentStr ?>"
                                                    data-name="<?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>">
                                                <i class="fas fa-redo"></i> Reschedule
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $app['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Modal for No-Show -->
<div class="modal fade" id="sharedRescheduleModal" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt mr-2"></i> Reschedule Appointment</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="sharedRescheduleForm">
                    <input type="hidden" id="reschApptId" name="appointment_id">
                    <input type="hidden" id="reschOldDateId" name="old_date_id">
                    <input type="hidden" id="reschOldTime" name="old_time_slot">
                    <input type="hidden" id="reschNewDateId" name="new_date_id">
                    <input type="hidden" id="reschNewTime" name="new_time_slot">

                    <h6 class="text-muted mb-3">Rescheduling for: <strong id="modalResidentName" class="text-dark"></strong></h6>

                    <div class="alert alert-info mb-3">
                        <strong>Was Scheduled:</strong> <span id="reschCurrentDisplay"></span>
                    </div>

                    <div id="reschLoading" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Loading available dates...</p>
                    </div>
                    <div id="reschError" class="alert alert-danger" style="display:none;"></div>

                    <div id="reschGridContainer" style="display:none;">
                        <p class="small text-muted mb-2">Select a new date and time slot:</p>
                        <div id="sharedDatesGrid" class="dates-grid"></div>
                    </div>

                    <div id="reschSummary" class="alert alert-success mt-3" style="display:none;">
                        <strong>Selected:</strong> <span id="reschSummaryText"></span>
                    </div>

                    <div class="text-right mt-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="btnConfirmReschedule" class="btn btn-warning" disabled>
                            <i class="fas fa-save mr-2"></i> Confirm Reschedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    updateRescheduleStats();

    // Reschedule Request Filtering
    $('.filter-group .btn').click(function() {
        $('.filter-group .btn').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        const rows = $('.request-row');
        
        if (filter === 'all') {
            rows.fadeIn();
        } else {
            rows.each(function() {
                if ($(this).data('status') === filter) {
                    $(this).fadeIn();
                } else {
                    $(this).fadeOut();
                }
            });
        }
    });

    // No-Show Reschedule Modal
    $(document).on('click', '.btn-open-reschedule', function() {
        const btn = $(this);
        
        $('#reschApptId').val(btn.data('id'));
        $('#reschOldDateId').val(btn.data('old-date-id'));
        $('#reschOldTime').val(btn.data('old-time'));
        $('#modalResidentName').text(btn.data('name'));
        $('#reschCurrentDisplay').text(btn.data('current-date'));
        
        $('#reschNewDateId').val('');
        $('#reschNewTime').val('');
        
        $('#reschLoading').show();
        $('#reschGridContainer').hide();
        $('#reschError').hide();
        $('#reschSummary').hide();
        $('#btnConfirmReschedule').prop('disabled', true);
        
        $('#sharedRescheduleModal').modal('show');

        $.ajax({
            url: 'get_available_dates.php',
            type: 'POST',
            dataType: 'json',
            success: function(res) {
                $('#reschLoading').hide();
                if(res.success && res.dates.length > 0) {
                    renderDates(res.dates);
                    $('#reschGridContainer').fadeIn();
                } else {
                    $('#reschError').text(res.message || 'No dates available.').show();
                }
            },
            error: function() {
                $('#reschLoading').hide();
                $('#reschError').text('Error connecting to server.').show();
            }
        });
    });

    // Delete No-Show
    $(document).on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        if(confirm('Permanently delete this record?')) {
            $.post('delete_appointment.php', { appointment_id: id }, function() {
                removeNoShowRow(id);
            });
        }
    });

    // Reschedule Form Submit
    $('#sharedRescheduleForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnConfirmReschedule');
        const originalText = btn.html();
        const apptId = $('#reschApptId').val();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'process_reschedule.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    $('#sharedRescheduleModal').modal('hide');
                    removeNoShowRow(apptId);
                    showAlert(res.message, 'success');
                } else {
                    showAlert('Error: ' + res.message, 'danger');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showAlert('System error occurred.', 'danger');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Slot Click Handler
    $(document).on('click', '.clickable-slot', function() {
        $('.clickable-slot').removeClass('selected');
        $('.date-card').removeClass('active-date');
        $(this).addClass('selected');
        const parentCard = $(this).closest('.date-card');
        parentCard.addClass('active-date');

        const dateId = parentCard.data('date-id');
        const timeVal = $(this).data('time');
        const dateStr = parentCard.data('date-str');
        const timeLabel = $(this).data('label');

        $('#reschNewDateId').val(dateId);
        $('#reschNewTime').val(timeVal);
        $('#reschSummaryText').text(`${dateStr} at ${timeLabel}`);
        $('#reschSummary').fadeIn();
        $('#btnConfirmReschedule').prop('disabled', false);
    });
});

function updateRescheduleStats() {
    let pending = 0, approved = 0, rejected = 0;
    
    $('.request-row').each(function() {
        const status = $(this).attr('data-status');
        if(status === 'Pending') pending++;
        else if(status === 'Approved') approved++;
        else if(status === 'Rejected') rejected++;
    });

    animateValue("statPending", parseInt($('#statPending').text()), pending, 500);
    animateValue("statApproved", parseInt($('#statApproved').text()), approved, 500);
    animateValue("statRejected", parseInt($('#statRejected').text()), rejected, 500);
}

function animateValue(id, start, end, duration) {
    if (start === end) return;
    const range = end - start;
    let current = start;
    const increment = end > start ? 1 : -1;
    const stepTime = Math.abs(Math.floor(duration / range));
    const obj = document.getElementById(id);
    const timer = setInterval(function() {
        current += increment;
        obj.innerHTML = current;
        if (current == end) clearInterval(timer);
    }, stepTime);
}

function handleRequest(requestId, action, btnElement) {
    let phpAction = (action === 'Approved') ? 'approve' : 'reject';
    let rejectionReason = '';
    
    if (phpAction === 'reject') {
        rejectionReason = prompt('Please provide a reason for rejection:');
        if (rejectionReason === null) return;
        if (rejectionReason.trim() === '') {
            showAlert('Rejection reason is required', 'warning');
            return;
        }
    } else {
        if (!confirm(`Are you sure you want to approve this request?`)) return;
    }

    const $btn = $(btnElement);
    const $row = $btn.closest('tr');
    const originalContent = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
        url: 'ajax/review_reschedule.php',
        method: 'POST',
        data: {
            request_id: requestId,
            action: phpAction,
            rejection_reason: rejectionReason
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                
                const badgeClass = (action === 'Approved') ? 'approved' : 'rejected';
                const icon = (action === 'Approved') ? 'check' : 'times';
                const badgeHtml = `<i class="fas fa-${icon}"></i> ${action}`;
                
                $row.find('.status-badge').removeClass('pending approved rejected').addClass(badgeClass).html(badgeHtml);
                $row.attr('data-status', action).data('status', action);
                $row.find('.action-cell').html('<span class="text-muted small">Processed</span>');
                
                updateRescheduleStats();
            } else {
                showAlert(response.message, 'danger');
                $btn.prop('disabled', false).html(originalContent);
            }
        },
        error: function() {
            showAlert('An error occurred. Please try again.', 'danger');
            $btn.prop('disabled', false).html(originalContent);
        }
    });
}

function removeNoShowRow(id) {
    $('#row_' + id).fadeOut(400, function() {
        $(this).remove();
        if($('#noShowBody tr').length === 0) {
            $('#noShowBody').html('<tr id="noRecordsRow"><td colspan="5" class="text-center p-4"><i class="fas fa-inbox fa-3x mb-3 text-muted"></i><p class="text-muted">No no-show appointments found.</p></td></tr>');
        }
    });
    const totalEl = $('#statTotalNoShow');
    let currentTotal = parseInt(totalEl.text());
    if(!isNaN(currentTotal) && currentTotal > 0) totalEl.text(currentTotal - 1);
}

function renderDates(dates) {
    const grid = $('#sharedDatesGrid');
    grid.empty();

    dates.forEach(date => {
        const amSlot = createSlotHtml('AM', 'Morning', date.am_open, date.am_remaining, '09:00:00');
        const pmSlot = createSlotHtml('PM', 'Afternoon', date.pm_open, date.pm_remaining, '14:00:00');
        
        const dateObj = new Date(date.date);
        const displayDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short' });

        const cardHtml = `
            <div class="date-card" data-date-id="${date.date_id}" data-date-str="${displayDate}">
                <div class="font-weight-bold text-primary mb-2"><i class="fas fa-calendar-day"></i> ${displayDate}</div>
                ${amSlot} ${pmSlot}
            </div>`;
        grid.append(cardHtml);
    });
}

function createSlotHtml(label, timeStr, isOpen, remaining, timeValue) {
    if(!isOpen) return `<div class="time-slot-option disabled"><span>${timeStr}</span> <span class="badge badge-secondary">Full</span></div>`;
    return `<div class="time-slot-option clickable-slot" data-time="${timeValue}" data-label="${timeStr}"><span>${timeStr}</span> <span class="badge badge-success">${remaining} left</span></div>`;
}

function showAlert(message, type) {
    $('.floating-alert').remove();

    const icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle');
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show floating-alert" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            <i class="fas fa-${icon} mr-2"></i> ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    $('body').append(alertHtml);
    setTimeout(() => $('.floating-alert').fadeOut(), 4000);
}
</script>

</body>
</html>