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
$department_id = $personnel['department_id'];

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
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Management</title>
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
            background-color: #f4f6f9;
            padding-bottom: 3rem;
        }

        .container-fluid {
            max-width: 1600px;
            padding: 2rem;
        }

        /* Stats Header */
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
        }

        .stat-card.pending { border-color: var(--warning); }
        .stat-card.approved { border-color: var(--success); }
        .stat-card.rejected { border-color: var(--danger); }

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

        /* Table Design */
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            padding: 0;
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

        .filter-group .btn {
            border-radius: 20px;
            font-size: 0.9rem;
            padding: 0.5rem 1.2rem;
            margin-right: 0.5rem;
            border: 1px solid #e0e0e0;
            background: white;
            color: var(--text-muted);
            font-weight: 600;
        }

        .filter-group .btn.active, .filter-group .btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .table-responsive {
            margin: 0;
        }

        .custom-table {
            width: 100%;
            margin-bottom: 0;
        }

        .custom-table thead th {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-bottom: 2px solid #e9ecef;
            color: white;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .custom-table tbody td {
            vertical-align: middle;
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* Specific Column Styles */
        .col-resident { min-width: 200px; }
        .col-schedule { min-width: 280px; }
        
        .resident-meta small {
            display: block;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .schedule-change {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .date-box {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        
        .date-box.old { border-left: 3px solid var(--danger); }
        .date-box.new { border-left: 3px solid var(--success); }

        .reason-cell {
            max-width: 200px;
        }
        
        .reason-text {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }

        /* Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }

        /* Action Buttons */
        .btn-sm-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            transition: transform 0.2s;
        }
        
        .btn-sm-action:hover { transform: scale(1.1); }
        .btn-approve-sm { background: #e0f2f1; color: var(--success); }
        .btn-reject-sm { background: #ffebee; color: var(--danger); }

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
        /* Mobile Responsive Stacked Table */
        @media (max-width: 992px) {
            .container-fluid { padding: 1rem; }
            
            .custom-table thead { display: none; }
            
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
                justify-content: flex-start; /* CHANGED from space-between */
                align-items: center;
                gap: 1rem; /* ADDED */
                border-bottom: 1px solid #f8f9fa;
                padding: 0.8rem 1rem;
                text-align: left; /* CHANGED from right */
            }

            .custom-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                font-size: 0.75rem;
                text-align: left;
                min-width: 120px; /* CHANGED from margin-right */
                flex-shrink: 0; /* ADDED */
            }

            .schedule-change {
                flex-direction: column;
                align-items: flex-start; /* CHANGED from flex-end */
                gap: 8px; /* ADDED */
            }
            
            .fa-arrow-right { 
                transform: rotate(90deg); 
                margin: 5px 0; 
            }
            
            .reason-cell { max-width: 100%; }
            .reason-text { 
                white-space: normal; 
                text-align: left; /* CHANGED from right */
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check"></i> Department Appointments</h2>
        <p>Manage and track all appointments for your department</p>
    </div>
    
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
            <table class="table custom-table table-hover" id="requestsTable">
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
                    <?php if (empty($requests)): ?>
                        <tr class="no-data-row">
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                    <p>No reschedule requests found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
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
                                    <span class="status-badge <?= strtolower($request['status']) ?> badge-indicator">
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <i class="fas fa-clock"></i> Pending
                                        <?php elseif ($request['status'] === 'Approved'): ?>
                                            <i class="fas fa-check"></i> Approved
                                        <?php else: ?>
                                            <i class="fas fa-times"></i> Rejected
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td data-label="Actions" class="text-right action-cell">
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

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    updateStats();

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
});

function updateStats() {
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
        if (current == end) {
            clearInterval(timer);
        }
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
                
                const $badge = $row.find('.status-badge');
                $badge.removeClass('pending approved rejected')
                      .addClass(badgeClass)
                      .html(badgeHtml);

                $row.attr('data-status', action);
                $row.data('status', action); 

                $row.find('.action-cell').html('<span class="text-muted small">Processed</span>');

                updateStats();
                
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

function showAlert(message, type) {
    $('.floating-alert').remove();

    const icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle');
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show floating-alert" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border-left: 5px solid rgba(0,0,0,0.1);">
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