<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo "<script>alert('Unauthorized access!'); window.location.href='../login.php';</script>";
    exit();
}

$stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$stmt->execute([$_SESSION['auth_id']]);
$department_id = $stmt->fetchColumn();

if (!$department_id) {
    echo "<script>alert('No department assigned!'); window.location.href='../login.php';</script>";
    exit();
}

$query = "
    SELECT 
        a.id, a.transaction_id, a.status, a.reason, a.scheduled_for, a.requested_at, a.available_date_id,
        r.first_name, r.middle_name, r.last_name, r.address, r.birthday, r.age, r.sex, r.civil_status,
        r.id_front_image, r.selfie_with_id_image, r.phone_number,
        au.email,
        ds.service_name
    FROM appointments a
    JOIN residents r ON a.resident_id = r.id
    JOIN auth au ON r.auth_id = au.id
    LEFT JOIN department_services ds ON a.service_id = ds.id
    WHERE a.department_id = ? AND a.status = 'Pending'
    ORDER BY a.scheduled_for ASC
";

$appointments = $pdo->prepare($query);
$appointments->execute([$department_id]);
$appointmentData = $appointments->fetchAll(PDO::FETCH_ASSOC);

$serviceQuery = $pdo->prepare("SELECT DISTINCT ds.service_name FROM appointments a LEFT JOIN department_services ds ON a.service_id = ds.id WHERE a.department_id = ?");
$serviceQuery->execute([$department_id]);
$services = $serviceQuery->fetchAll(PDO::FETCH_ASSOC);

$statsQuery = $pdo->prepare("SELECT COUNT(*) as total_pending, COUNT(CASE WHEN DATE(scheduled_for) = CURDATE() THEN 1 END) as today_pending, COUNT(CASE WHEN YEARWEEK(scheduled_for) = YEARWEEK(NOW()) THEN 1 END) as week_pending, COUNT(CASE WHEN MONTH(scheduled_for) = MONTH(NOW()) AND YEAR(scheduled_for) = YEAR(NOW()) THEN 1 END) as month_pending FROM appointments WHERE department_id = ? AND status = 'Pending'");
$statsQuery->execute([$department_id]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%); font-family: 'Segoe UI', sans-serif; min-height: 100vh; padding: 2rem 0; }
        .page-header { background: linear-gradient(135deg, #0D92F4, #27548A); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); color: white; }
        
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.08); transition: transform 0.3s; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 2.5rem; color: #3498db; margin-bottom: 0.5rem; }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0.5rem 0; color: #2c3e50; }
        
        .filter-section, .table-responsive { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); margin-bottom: 2rem; }
        #appointments-table thead { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; }
        #appointments-table th { border: none; padding: 1rem; vertical-align: middle; }
        #appointments-table td { vertical-align: middle; padding: 1rem; color: #4a5568; }
        .table-success { background-color: #d4edda !important; transition: background-color 1s ease; }
        
        .dates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; max-height: 400px; overflow-y: auto; padding: 0.5rem; }
        .date-card { border: 2px solid #e0e6ed; border-radius: 12px; padding: 1rem; cursor: default; background: white; position: relative; transition: all 0.2s; }
        .date-card.active-date { border-color: #3498db; background: #f0f7ff; box-shadow: 0 0 10px rgba(52, 152, 219, 0.2); }
        .time-slot-option { display: flex; justify-content: space-between; padding: 0.5rem; margin-top: 0.5rem; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; background: #f8f9fa; }
        .time-slot-option:hover { background: #e2e6ea; border-color: #adb5bd; }
        .time-slot-option.selected { background: #d4edda; border-color: #28a745; color: #155724; font-weight: bold; }
        .time-slot-option.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .info-section { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
        .clickable-id { cursor: zoom-in; transition: transform 0.2s; }
        .clickable-id:hover { transform: scale(1.02); }
        #viewIdImage { max-height: 400px; width: auto; object-fit: contain; }
        .table-warning { background-color: #fff3cd !important; transition: background-color 0.3s ease;}
        tr.table-success {background-color: #d4edda !important;transition: background-color 1s ease;}
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-calendar-check mr-2"></i> Manage Appointments</h2>
        <p>Review and process pending appointment requests</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-hourglass-half"></i><h3 id="statTotal"><?= $stats['total_pending'] ?></h3><p>Total Pending</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-day"></i><h3><?= $stats['today_pending'] ?></h3><p>Today</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-week"></i><h3><?= $stats['week_pending'] ?></h3><p>This Week</p></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="fas fa-calendar-alt"></i><h3><?= $stats['month_pending'] ?></h3><p>This Month</p></div></div>
    </div>

    <div class="filter-section">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0"><input type="text" class="form-control" id="searchInput" placeholder="🔍 Search..."></div>
            <div class="col-md-4 mb-3 mb-md-0">
                <select id="serviceFilter" class="form-control">
                    <option value="">All Services</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?= htmlspecialchars(strtolower($srv['service_name'])) ?>"><?= htmlspecialchars($srv['service_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100" id="clearFilters"><i class="fas fa-eraser"></i> Clear</button></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover" id="appointments-table">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Resident Name</th>
                    <th>Service</th>
                    <th>Scheduled For</th>
                    <th>Requested At</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="appointments-tbody">
                <?php if (!empty($appointmentData)): ?>
                    <?php foreach ($appointmentData as $app): ?>
                        <tr class="appointment-row" id="row_<?= $app['id'] ?>" data-service="<?= strtolower($app['service_name'] ?? '') ?>">
                            <td><span class="badge badge-primary p-2"><?= $app['transaction_id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars($app['service_name'] ?? 'N/A') ?></td>
                            <td class="schedule-cell">
                                <?php if ($app['scheduled_for']): ?>
                                    <i class="fas fa-calendar-day text-info mr-1"></i> <?= date('M j, Y', strtotime($app['scheduled_for'])) ?><br>
                                    <small class="text-muted">
                                        <?= ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? 'Morning' : 'Afternoon' ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Not Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('M j, Y', strtotime($app['requested_at'])) ?></small></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-info btn-view-details" 
                                            data-details='<?= json_encode($app, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php 
                                        $curDate = date('M j, Y', strtotime($app['scheduled_for']));
                                        $curTime = ((int)date('H', strtotime($app['scheduled_for'])) < 12) ? ' (Morning)' : ' (Afternoon)';
                                        $fullCurrentStr = $curDate . $curTime;
                                    ?>
                                    <button class="btn btn-sm btn-warning btn-open-reschedule" 
                                            data-id="<?= $app['id'] ?>"
                                            data-current-date="<?= $fullCurrentStr ?>"
                                            data-old-date-id="<?= $app['available_date_id'] ?>"
                                            data-old-time="<?= $app['scheduled_for'] ? date('H:i:s', strtotime($app['scheduled_for'])) : '' ?>">
                                        <i class="fas fa-calendar-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="no-results-row"><td colspan="6" class="text-center py-5"><div class="empty-state"><p>No pending appointments</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="sharedViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i> <span id="viewTransId"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="info-section">
                    <h6><i class="fas fa-user-circle mr-2"></i>Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="viewName"></span></p>
                            <p><strong>Email:</strong> <span id="viewEmail"></span></p>
                            <p><strong>Address:</strong> <span id="viewAddress"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Age/Sex:</strong> <span id="viewAgeSex"></span></p>
                            <p><strong>Civil Status:</strong> <span id="viewCivil"></span></p>
                            <p><strong>Phone:</strong> <span id="viewPhone"></span></p>
                        </div>
                    </div>
                </div>
                <div class="info-section">
                    <h6><i class="fas fa-clipboard-list mr-2"></i>Appointment Info</h6>
                    <p><strong>Service:</strong> <span id="viewService"></span></p>
                    <p><strong>Reason:</strong> <span id="viewReason"></span></p>
                    <p><strong>Scheduled:</strong> <span id="viewScheduled"></span></p>
                </div>
                <div class="info-section">
                    <h6><i class="fas fa-id-card mr-2"></i>Valid ID</h6>
                    <img src="" id="viewIdImage" class="img-thumbnail w-100 clickable-id">
                </div>
                <div class="text-right mt-3">
                    <button class="btn btn-success btn-action btn-complete-action" data-id=""><i class="fas fa-check-circle mr-2"></i> Complete</button>
                    <button class="btn btn-danger btn-action btn-delete-action" data-id=""><i class="fas fa-trash-alt mr-2"></i> Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

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

                    <div class="alert alert-info mb-3">
                        <strong>Current:</strong> <span id="reschCurrentDisplay"></span>
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

<div class="modal fade" id="fullImageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="close text-white position-absolute" style="right: 10px; top: 10px; font-size: 2rem; z-index: 1000; text-shadow: 0 0 5px black;" data-dismiss="modal">&times;</button>
                <img src="" id="fullImageDisplay" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {

    // ============================================
    // SCROLL TO HIGHLIGHTED APPOINTMENT
    // ============================================
    const highlightTransactionId = sessionStorage.getItem('highlightAppointment');
    
    if (highlightTransactionId) {
        console.log('Looking for transaction:', highlightTransactionId);
        
        setTimeout(function() {
            const targetRow = $('#appointments-tbody tr').filter(function() {
                return $(this).find('.badge-primary').text().trim() === highlightTransactionId;
            });
            
            if (targetRow.length > 0) {
                console.log('Found appointment row, scrolling...');
                targetRow.addClass('table-warning');
                
                $('html, body').animate({
                    scrollTop: targetRow.offset().top - 150
                }, 800, function() {
                    targetRow.addClass('table-success');
                    setTimeout(function() {
                        targetRow.removeClass('table-warning table-success');
                    }, 3000);
                });
            } else {
                console.log('Appointment not found in current table');
            }
            
            sessionStorage.removeItem('highlightAppointment');
        }, 500);
    }

    // ============================================
    // VIEW DETAILS
    // ============================================
    $('.btn-view-details').click(function() {
        const data = $(this).data('details');
        $('#viewTransId').text(data.transaction_id);
        $('#viewName').text(data.first_name + ' ' + data.last_name);
        $('#viewEmail').text(data.email);
        $('#viewAddress').text(data.address);
        $('#viewAgeSex').text(data.age + ' / ' + data.sex);
        $('#viewCivil').text(data.civil_status);
        $('#viewPhone').text(data.phone_number); 
        $('#viewService').text(data.service_name || 'N/A');
        $('#viewReason').text(data.reason);
        
        let schedText = 'Not Scheduled';
        if(data.scheduled_for) {
            const dateObj = new Date(data.scheduled_for);
            const dateStr = dateObj.toLocaleDateString();
            const hour = dateObj.getHours();
            const timeStr = hour < 12 ? 'Morning' : 'Afternoon';
            schedText = `${dateStr} (${timeStr})`;
        }
        $('#viewScheduled').text(schedText);
        $('#viewIdImage').attr('src', '../' + data.id_front_image);
        
        $('.btn-complete-action').data('id', data.id);
        $('.btn-delete-action').data('id', data.id);
        $('#sharedViewModal').modal('show');
    });

    $('#viewIdImage').click(function() {
        $('#fullImageDisplay').attr('src', $(this).attr('src'));
        $('#fullImageModal').modal('show');
    });

    // ============================================
    // RESCHEDULE LOGIC
    // ============================================
    $('.btn-open-reschedule').click(function() {
        const btn = $(this);
        
        $('#reschApptId').val(btn.data('id'));
        $('#reschOldDateId').val(btn.data('old-date-id'));
        $('#reschOldTime').val(btn.data('old-time'));
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

    $(document).on('click', '.clickable-slot', function() {
        $('.clickable-slot').removeClass('selected');
        $('.date-card').removeClass('active-date');
        $(this).addClass('selected');
        const parentCard = $(this).closest('.date-card');
        parentCard.addClass('active-date');

        $('#reschNewDateId').val(parentCard.data('date-id'));
        $('#reschNewTime').val($(this).data('time'));
        
        $('#reschSummaryText').text(`${parentCard.data('date-str')} at ${$(this).data('label')}`);
        $('#reschSummary').fadeIn();
        $('#btnConfirmReschedule').prop('disabled', false);
    });

    $('#sharedRescheduleForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnConfirmReschedule');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: 'process_reschedule.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    $('#sharedRescheduleModal').modal('hide');
                    btn.html(originalText);
                    
                    const apptId = $('#reschApptId').val();
                    const row = $('#row_' + apptId);
                    const newTime = $('#reschSummaryText').text();
                    
                    const parts = newTime.split(' at ');
                    const datePart = parts[0];
                    const timePart = parts[1];
                    
                    row.find('.schedule-cell').html(`<i class="fas fa-calendar-day text-info mr-1"></i> ${datePart}<br><small class="text-muted">${timePart}</small>`);
                    row.addClass('table-success');
                    setTimeout(() => row.removeClass('table-success'), 2000);
                    
                    alert('Success! Appointment rescheduled.');
                } else {
                    alert('Error: ' + res.message);
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('System error.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ============================================
    // COMPLETE & DELETE ACTIONS (UPDATED!)
    // ============================================
    $(document).on('click', '.btn-complete-action, .btn-delete-action', function() {
        const id = $(this).data('id');
        const isDelete = $(this).hasClass('btn-delete-action');
        const url = isDelete ? 'delete_appointment.php' : 'complete_appointment.php';
        const actionName = isDelete ? 'Delete' : 'Complete';
        const msg = isDelete ? 'Delete this appointment?' : 'Mark this appointment as completed?';
        const btn = $(this);
        const originalHtml = btn.html();
        
        if(confirm(msg)) {
            // Disable button and show loading
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: url,
                type: 'POST',
                data: { appointment_id: id },
                dataType: 'json', // CRITICAL: Parse JSON response
                success: function(res) {
                    console.log('Response:', res);
                    
                    if(res.success) {
                        // Success animation
                        $('#row_' + id).addClass('table-success');
                        
                        setTimeout(function() {
                            $('#row_' + id).fadeOut(400, function() { 
                                $(this).remove(); 
                                
                                // Check if table is empty
                                if($('#appointments-tbody tr:visible').length === 0) {
                                    $('#appointments-tbody').html(
                                        '<tr id="no-results-row">' +
                                        '<td colspan="6" class="text-center py-5">' +
                                        '<div class="empty-state">' +
                                        '<i class="fas fa-check-circle fa-3x text-success mb-3"></i>' +
                                        '<p>No pending appointments</p>' +
                                        '</div></td></tr>'
                                    );
                                }
                            });
                        }, 500);
                        
                        $('#sharedViewModal').modal('hide');
                        
                        // Update stats
                        const totalEl = $('#statTotal');
                        let currentTotal = parseInt(totalEl.text());
                        if(!isNaN(currentTotal) && currentTotal > 0) {
                            totalEl.text(currentTotal - 1);
                        }
                        
                        // Build notification message
                        let notificationMsg = res.message || (actionName + ' successful!');
                        let notificationDetails = [];
                        
                        if(res.notifications) {
                            if(res.notifications.email_sent) {
                                notificationDetails.push('✅ Email sent');
                            } else {
                                notificationDetails.push('⚠️ Email failed');
                            }
                            
                            if(res.notifications.sms_sent) {
                                notificationDetails.push('✅ SMS sent');
                            } else {
                                notificationDetails.push('⚠️ SMS failed');
                            }
                        }
                        
                        if(notificationDetails.length > 0) {
                            alert(notificationMsg + '\n\n' + notificationDetails.join('\n'));
                        } else {
                            alert(notificationMsg);
                        }
                        
                    } else {
                        alert('Error: ' + (res.message || 'Failed to process request'));
                        btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let errorMsg = 'System error occurred.';
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMsg = errorData.message || errorMsg;
                    } catch(e) {
                        errorMsg += ' Check console for details.';
                    }
                    
                    alert(errorMsg);
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    });

    // ============================================
    // FILTERS
    // ============================================
    function applyFilters() {
        const searchVal = $('#searchInput').val().toLowerCase();
        const selectedService = $('#serviceFilter').val().toLowerCase();
        $('.appointment-row').each(function () {
            const text = $(this).text().toLowerCase();
            const service = $(this).data('service');
            const matchesSearch = text.includes(searchVal);
            const matchesService = selectedService === "" || service === selectedService;
            $(this).toggle(matchesSearch && matchesService);
        });
    }
    
    $('#searchInput').on('input', applyFilters);
    $('#serviceFilter').on('change', applyFilters);
    
    $('#clearFilters').click(function () { 
        $('#searchInput').val(''); 
        $('#serviceFilter').val(''); 
        $('.appointment-row').show(); 
    });

});
</script>
</body>
</html>
