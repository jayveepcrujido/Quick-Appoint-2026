<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$personnel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personnel) {
    echo "<div class='alert alert-danger'>Invalid user.</div>";
    exit();
}

$departmentId = $personnel['department_id'];

$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

$whereClause = "WHERE a.department_id = ?";
$params = [$departmentId];

if ($startDate && $endDate) {
    $whereClause .= " AND DATE(a.scheduled_for) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate) {
    $whereClause .= " AND DATE(a.scheduled_for) >= ?";
    $params[] = $startDate;
} elseif ($endDate) {
    $whereClause .= " AND DATE(a.scheduled_for) <= ?";
    $params[] = $endDate;
}

$stmt = $pdo->prepare("
    SELECT a.id, a.transaction_id, a.status, a.scheduled_for, a.reason, a.requested_at,
           r.first_name, r.last_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    $whereClause
    ORDER BY a.scheduled_for DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 1rem 0;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: -1.5rem;
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

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .filter-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .filter-label i {
            margin-right: 0.5rem;
            color: #3498db;
        }

        #statusFilter {
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        #statusFilter:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .desktop-table {
            display: none;
        }

        #appointmentsTable {
            margin-bottom: 0;
            border: none;
        }

        #appointmentsTable thead th {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #appointmentsTable tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f3f7;
        }

        #appointmentsTable tbody tr:hover {
            background: linear-gradient(to right, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        #appointmentsTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #4a5568;
            font-size: 0.9rem;
            border: none;
        }

        .transaction-id {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #2c3e50;
            background: #ecf0f1;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            display: inline-block;
        }

        .resident-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .reason-text {
            color: #5a6c7d;
            font-style: italic;
        }

        .scheduled-time {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .scheduled-time i {
            color: #3498db;
        }

        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .badge-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .badge-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .requested-date {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .mobile-cards {
            display: block;
        }

        .appointment-mobile-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .appointment-mobile-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f3f7;
        }

        .mobile-card-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .mobile-card-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 110px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-card-label i {
            color: #3498db;
            width: 20px;
            text-align: center;
        }

        .mobile-card-value {
            color: #4a5568;
            flex: 1;
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 1rem;
            margin: 0;
        }

        @media (min-width: 768px) {
            body {
                padding: 1.5rem 0;
            }

            .page-header h2 {
                font-size: 1.6rem;
            }

            .empty-state {
                padding: 3rem;
            }

            .empty-state i {
                font-size: 3.5rem;
            }

            .empty-state p {
                font-size: 1.05rem;
            }
        }

        @media (min-width: 992px) {
            body {
                padding: 2rem 0;
            }

            .page-header {
                padding: 2rem;
            }

            .page-header h2 {
                font-size: 1.8rem;
            }

            .page-header p {
                font-size: 0.95rem;
            }

            .filter-section {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .filter-label {
                margin-bottom: 0;
                margin-right: 1rem;
            }

            #statusFilter {
                width: auto;
                min-width: 200px;
            }

            .mobile-cards {
                display: none;
            }

            .desktop-table {
                display: block;
            }

            .empty-state {
                padding: 3rem;
            }

            .empty-state i {
                font-size: 4rem;
            }

            .empty-state p {
                font-size: 1.1rem;
            }
        }

        @media (min-width: 1200px) {
            .table-responsive {
                border-radius: 12px;
            }
        }

.datepicker {
    cursor: pointer;
    background-color: white;
}

.datepicker:focus {
    cursor: pointer;
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.6rem 1.2rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
}

.btn i {
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
    color: white;
}

.flatpickr-calendar {
    background: white;
    border: 2px solid #3498db;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.flatpickr-months {
    background: linear-gradient(135deg, #3498db, #2980b9);
    border-radius: 10px 10px 0 0;
}

.flatpickr-current-month {
    color: white;
    font-weight: 700;
}

.flatpickr-prev-month,
.flatpickr-next-month {
    fill: white;
}

.flatpickr-prev-month:hover,
.flatpickr-next-month:hover {
    fill: rgba(255, 255, 255, 0.8);
}

.flatpickr-weekday {
    color: #3498db;
    font-weight: 700;
}

.flatpickr-day {
    color: #2c3e50;
    border-radius: 8px;
    font-weight: 600;
}

.flatpickr-day:hover {
    background: #ebf5fb;
    color: #3498db;
    border-color: #3498db;
}

.flatpickr-day.selected {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border-color: #3498db;
}

.flatpickr-day.today {
    background: #f39c12;
    color: white;
    border-color: #f39c12;
}

.flatpickr-day.today:hover {
    background: #e67e22;
    border-color: #e67e22;
}

.datepicker {
    cursor: pointer;
    background-color: white;
}

.datepicker:focus {
    cursor: pointer;
}

    </style>
</head>
<body>
<div class="container-fluid main-container px-3 px-md-4">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check"></i> Department Appointments</h2>
        <p>Manage and track all appointments for your department</p>
    </div>

<div class="filter-section">
    <form id="filterForm" class="row w-100 align-items-end">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="filter-label">
                <i class="fas fa-calendar-alt"></i>
                <span>Start Date:</span>
            </div>
            <input type="text" 
                   class="form-control datepicker" 
                   id="start_date" 
                   name="start_date" 
                   placeholder="Select start date"
                   value="<?= htmlspecialchars($startDate ?? '') ?>"
                   readonly>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="filter-label">
                <i class="fas fa-calendar-alt"></i>
                <span>End Date:</span>
            </div>
            <input type="text" 
                   class="form-control datepicker" 
                   id="end_date" 
                   name="end_date" 
                   placeholder="Select end date"
                   value="<?= htmlspecialchars($endDate ?? '') ?>"
                   readonly>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="filter-label">
                <i class="fas fa-filter"></i>
                <span>Status:</span>
            </div>
            <select id="statusFilter" class="form-control">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Completed">Completed</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-primary btn-block mb-2" onclick="applyAppointmentFilters()">
                <i class="fas fa-search"></i> Apply
            </button>
            <button type="button" class="btn btn-secondary btn-block" onclick="clearAppointmentFilters()">
                <i class="fas fa-redo"></i> Clear
            </button>
        </div>
    </form>
</div>

    <div class="table-card desktop-table">
        <div class="table-responsive">
            <table id="appointmentsTable" class="table">
                <thead>
                    <tr>
                        <th class="text-center">Transaction ID</th>
                        <th class="text-center">Resident Name</th>
                        <th class="text-center">Reason</th>
                        <th class="text-center">Scheduled For</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Requested At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $row): ?>
                            <?php
                                $status = htmlspecialchars($row['status']);
                                $badgeClass = $status === 'Pending' ? 'warning' : 
                                              ($status === 'Completed' ? 'success' : 'secondary');
                            ?>
                            <tr data-status="<?= $status ?>">
                                <td class="text-center">
                                    <span class="transaction-id"><?= htmlspecialchars($row['transaction_id']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="resident-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="reason-text"><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="scheduled-time">
                                        <i class="far fa-clock"></i>
                                        <span>
                                            <?= $row['scheduled_for'] 
                                                ? date('F j, Y • g:i A', strtotime($row['scheduled_for'])) 
                                                : 'Not Scheduled'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $badgeClass ?>"><?= $status ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="requested-date"><?= date('M d, Y g:i A', strtotime($row['requested_at'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="far fa-calendar-times"></i>
                                    <p>No appointments found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mobile-cards">
        <?php if (!empty($appointments)): ?>
            <?php foreach ($appointments as $row): ?>
                <?php
                    $status = htmlspecialchars($row['status']);
                    $badgeClass = $status === 'Pending' ? 'warning' : 
                                  ($status === 'Completed' ? 'success' : 'secondary');
                ?>
                <div class="appointment-mobile-card" data-status="<?= $status ?>">
                    <div class="mobile-card-header">
                        <span class="transaction-id"><?= htmlspecialchars($row['transaction_id']) ?></span>
                        <span class="badge badge-<?= $badgeClass ?>"><?= $status ?></span>
                    </div>
                    
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-user"></i>
                            <span>Name:</span>
                        </div>
                        <div class="mobile-card-value resident-name">
                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-sticky-note"></i>
                            <span>Reason:</span>
                        </div>
                        <div class="mobile-card-value reason-text">
                            <?= htmlspecialchars($row['reason'] ?? 'N/A') ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="far fa-clock"></i>
                            <span>Scheduled:</span>
                        </div>
                        <div class="mobile-card-value">
                            <?= $row['scheduled_for'] 
                                ? date('M j, Y • g:i A', strtotime($row['scheduled_for'])) 
                                : 'Not Scheduled'; ?>
                        </div>
                    </div>

                    <div class="mobile-card-row">
                        <div class="mobile-card-label">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Requested:</span>
                        </div>
                        <div class="mobile-card-value requested-date">
                            <?= date('M d, Y g:i A', strtotime($row['requested_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <p>No appointments found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyAppointmentFilters() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    let url = 'personnel_view_appointments_status.php';
    const params = [];
    if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
    if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
    if (params.length > 0) url += '?' + params.join('&');
    
    console.log('📍 Loading URL:', url);
    
    if (typeof loadContent === 'function') {
        loadContent(url);
    } else {
        $('#content-area').load(url, function() {
            if (typeof window.initAppointmentStatusPage === 'function') {
                window.initAppointmentStatusPage();
            }
        });
    }
}

function clearAppointmentFilters() {
    console.log('🧹 Clear appointment filters clicked');
    
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    
    if (typeof loadContent === 'function') {
        loadContent('personnel_view_appointments_status.php');
    } else {
        $('#content-area').load('personnel_view_appointments_status.php', function() {
            if (typeof window.initAppointmentStatusPage === 'function') {
                window.initAppointmentStatusPage();
            }
        });
    }
}

(function() {
    'use strict';
    
    console.log('=== Appointment Status Page Loading ===');

    if (window.appointmentStatusCleanup) {
        console.log('Cleaning up previous instance...');
        try {
            window.appointmentStatusCleanup();
        } catch (e) {
            console.error('Error during cleanup:', e);
        }
    }
    
    let startDatePicker = null;
    let endDatePicker = null;
    let statusFilterHandler = null;
    
    function initializePage() {
        setTimeout(function() {
            if (typeof flatpickr === 'undefined') {
                console.error('❌ Flatpickr not loaded!');
                
                if (!document.querySelector('script[src*="flatpickr"]')) {
                    console.log('Attempting to load Flatpickr...');
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
                    script.onload = function() {
                        console.log('✓ Flatpickr loaded dynamically, retrying initialization...');
                        setTimeout(initializeDatePickers, 100);
                    };
                    document.head.appendChild(script);
                }
                return;
            }
            
            initializeDatePickers();
            initializeStatusFilter();
            
        }, 200);
    }
    
    function initializeDatePickers() {
        console.log('Initializing date pickers...');
        
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        if (!startDateInput || !endDateInput) {
            console.error('❌ Date input elements not found!');
            return;
        }
        
        console.log('✓ Date inputs found:', startDateInput.id, endDateInput.id);
        
        if (startDatePicker) {
            try {
                startDatePicker.destroy();
                console.log('Destroyed old start date picker');
            } catch(e) {
                console.warn('Error destroying start picker:', e);
            }
        }
        
        if (endDatePicker) {
            try {
                endDatePicker.destroy();
                console.log('Destroyed old end date picker');
            } catch(e) {
                console.warn('Error destroying end picker:', e);
            }
        }
        
        try {
            startDatePicker = flatpickr(startDateInput, {
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                allowInput: false,
                clickOpens: true,
                onChange: function(selectedDates, dateStr) {
                    console.log('Start date selected:', dateStr);
                    if (endDatePicker && dateStr) {
                        endDatePicker.set('minDate', dateStr);
                    }
                },
                onOpen: function() {
                    console.log('Start date picker opened');
                },
                onClose: function() {
                    console.log('Start date picker closed');
                }
            });
            console.log('✓ Start date picker initialized');
        } catch(e) {
            console.error('❌ Error initializing start date picker:', e);
        }
        
        try {
            endDatePicker = flatpickr(endDateInput, {
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                allowInput: false,
                clickOpens: true,
                onChange: function(selectedDates, dateStr) {
                    console.log('End date selected:', dateStr);
                    if (startDatePicker && dateStr) {
                        startDatePicker.set('maxDate', dateStr);
                    }
                },
                onOpen: function() {
                    console.log('End date picker opened');
                },
                onClose: function() {
                    console.log('End date picker closed');
                }
            });
            console.log('✓ End date picker initialized');
        } catch(e) {
            console.error('❌ Error initializing end date picker:', e);
        }
        
        const startVal = startDateInput.value;
        const endVal = endDateInput.value;
        
        if (startVal && endDatePicker) {
            endDatePicker.set('minDate', startVal);
            console.log('Set minDate for end_date:', startVal);
        }
        if (endVal && startDatePicker) {
            startDatePicker.set('maxDate', endVal);
            console.log('Set maxDate for start_date:', endVal);
        }
        
        console.log('=== Date pickers ready! ===');
        
        // Test click handlers
        startDateInput.addEventListener('click', function() {
            console.log('Start input clicked');
        });
        endDateInput.addEventListener('click', function() {
            console.log('End input clicked');
        });
    }
    
    function initializeStatusFilter() {
        console.log('Initializing status filter...');
        
        const statusFilter = document.getElementById('statusFilter');
        if (!statusFilter) {
            console.error('❌ Status filter not found!');
            return;
        }
        
        if (statusFilterHandler) {
            statusFilter.removeEventListener('change', statusFilterHandler);
        }
        
        statusFilterHandler = function() {
            const selectedStatus = this.value;
            console.log('Filtering by status:', selectedStatus || 'All');

            const tableRows = document.querySelectorAll("#appointmentsTable tbody tr");
            tableRows.forEach(function(row) {
                const rowStatus = row.getAttribute('data-status');
                if (!selectedStatus || rowStatus === selectedStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            const mobileCards = document.querySelectorAll(".appointment-mobile-card");
            mobileCards.forEach(function(card) {
                const cardStatus = card.getAttribute('data-status');
                if (!selectedStatus || cardStatus === selectedStatus) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        };
        
        statusFilter.addEventListener('change', statusFilterHandler);
        console.log('✓ Status filter initialized');
    }
    
    window.appointmentStatusCleanup = function() {
        console.log('=== Cleaning up appointment status page ===');
        
        // Destroy Flatpickr instances
        if (startDatePicker) {
            try {
                startDatePicker.destroy();
                startDatePicker = null;
                console.log('✓ Start date picker destroyed');
            } catch(e) {
                console.warn('Error destroying start picker:', e);
            }
        }
        
        if (endDatePicker) {
            try {
                endDatePicker.destroy();
                endDatePicker = null;
                console.log('✓ End date picker destroyed');
            } catch(e) {
                console.warn('Error destroying end picker:', e);
            }
        }
        
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter && statusFilterHandler) {
            statusFilter.removeEventListener('change', statusFilterHandler);
            statusFilterHandler = null;
            console.log('✓ Status filter handler removed');
        }
        
        console.log('✓ Cleanup complete');
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePage);
        console.log('Waiting for DOMContentLoaded...');
    } else {
        console.log('DOM already ready, initializing immediately...');
        initializePage();
    }
    
    if (typeof jQuery !== 'undefined') {
        $(document).ready(function() {
            console.log('jQuery ready fired');
            if (!startDatePicker && !endDatePicker) {
                initializePage();
            }
        });
    }
    
    window.addEventListener('beforeunload', function() {
        if (window.appointmentStatusCleanup) {
            window.appointmentStatusCleanup();
        }
    });
    
    console.log('=== Appointment Status Script Loaded ===');
    
})();
</script>
</body>
</html>
