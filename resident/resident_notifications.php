<?php
session_start();
include '../conn.php';

// Check if user is logged in
if (!isset($_SESSION['auth_id'])) {
    header('Location: ../login.php');
    exit;
}

$authId = $_SESSION['auth_id'];

// Get resident_id from residents table using auth_id
try {
    $residentStmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
    $residentStmt->execute([$authId]);
    $residentData = $residentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$residentData) {
        die("Resident profile not found.");
    }
    
    $residentId = $residentData['id'];
} catch (PDOException $e) {
    die("Error fetching resident data: " . $e->getMessage());
}

// Mark all notifications as read when page is loaded
try {
    $markSeenStmt = $pdo->prepare("
        UPDATE appointments 
        SET is_seen_by_resident = 1
        WHERE resident_id = ? AND status = 'Completed' AND is_seen_by_resident = 0
    ");
    $markSeenStmt->execute([$residentId]);
} catch (PDOException $e) {
    // Log error but don't stop page load
    error_log("Error marking appointments as seen: " . $e->getMessage());
}

// Fetch all notifications for the resident
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.message,
            n.created_at,
            n.is_read,
            n.appointment_id,
            a.transaction_id,
            a.scheduled_for,
            a.status as appointment_status,
            a.department_id,
            d.name as department_name,
            d.acronym as department_acronym,
            ds.service_name,
            CONCAT(p.first_name, ' ', p.last_name) as personnel_name
        FROM notifications n
        INNER JOIN appointments a ON n.appointment_id = a.id
        INNER JOIN departments d ON a.department_id = d.id
        LEFT JOIN department_services ds ON a.service_id = ds.id
        LEFT JOIN lgu_personnel p ON a.personnel_id = p.id
        WHERE n.resident_id = ?
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute([$residentId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count notifications by status
    $pendingCount = 0;
    $completedCount = 0;
    foreach ($notifications as $notification) {
        if ($notification['appointment_status'] === 'Pending') {
            $pendingCount++;
        } elseif ($notification['appointment_status'] === 'Completed') {
            $completedCount++;
        }
    }
    
} catch (PDOException $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
    $notifications = [];
    $pendingCount = 0;
    $completedCount = 0;
}
?>

<style>
    .notification-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        margin-bottom: 10px;
        cursor: pointer;
    }
    .notification-card .card-body {
        padding: 12px 15px;
    }
    .notification-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .notification-card.unread {
        background-color: #f0f9ff;
        border-left-color: #3b82f6;
    }
    
    .notification-card.read {
        background-color: #ffffff;
        border-left-color: #e5e7eb;
    }
    
    .notification-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }
    
    .icon-completed {
        background-color: #dcfce7;
        color: #166534;
    }
    
    .icon-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .icon-info {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .notification-time {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .notification-badge {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }
    
    .empty-state i {
        font-size: 80px;
        color: #d1d5db;
        margin-bottom: 20px;
    }
    
    .filter-tabs {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .filter-btn {
        white-space: nowrap;
    }
    
    @media (max-width: 768px) {
        .notification-icon {
            width: 40px;
            height: 40px;
            font-size: 20px;
        }
        
        .filter-tabs {
            flex-direction: column;
        }
        
        .filter-btn {
            width: 100%;
        }
    }
    /* Add these new classes to your existing CSS */
    .icon-rejected {
        background-color: #fee2e2;
        color: #dc2626;
    }

    .icon-approved {
        background-color: #d1fae5;
        color: #059669;
    }

    /* Optional: specific badge for rejection */
    .badge-danger {
        background-color: #dc3545;
        color: #fff;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="mb-2 mb-md-0"><i class='bx bx-bell'></i> My Notifications</h2>
        <?php if (count($notifications) > 0): ?>
        <button class="btn btn-outline-secondary btn-sm" onclick="clearAllNotifications()">
            <i class='bx bx-trash'></i> Clear All
        </button>
        <?php endif; ?>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="btn btn-primary filter-btn active" data-filter="all">
            <i class='bx bx-list-ul'></i> All (<?php echo count($notifications); ?>)
        </button>
        <button class="btn btn-outline-primary filter-btn" data-filter="pending">
            <i class='bx bx-time'></i> Pending (<?php echo $pendingCount; ?>)
        </button>
        <button class="btn btn-outline-primary filter-btn" data-filter="completed">
            <i class='bx bx-check-circle'></i> Completed (<?php echo $completedCount; ?>)
        </button>
    </div>

    <!-- Notifications List -->
<div class="notifications-list">
    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notification): ?>
            <?php
            // 1. Initialize variables
            $iconClass = 'icon-info';
            $iconName = 'bx-bell';
            $statusClass = 'secondary';
            $statusText = $notification['appointment_status']; // Default
            $notificationType = strtolower($notification['appointment_status']);

            // 2. Check Message Content for Reschedule Logic
            $msgLower = strtolower($notification['message']);
            $isRescheduleApproved = strpos($msgLower, 'reschedule request has been approved') !== false;
            $isRescheduleRejected = strpos($msgLower, 'reschedule request has been rejected') !== false;

            // 3. Determine Styling based on specific logic
            if ($isRescheduleRejected) {
                // REJECTED STATE
                $iconClass = 'icon-rejected';
                $iconName = 'bx-x-circle';
                $statusText = 'Reschedule Rejected';
                $statusClass = 'danger';
            } 
            elseif ($isRescheduleApproved) {
                // APPROVED STATE
                $iconClass = 'icon-approved';
                $iconName = 'bx-calendar-check';
                $statusText = 'Reschedule Approved';
                $statusClass = 'success';
            } 
            elseif ($notification['appointment_status'] === 'Completed') {
                // COMPLETED STATE
                $iconClass = 'icon-completed';
                $iconName = 'bx-check-circle';
                $statusClass = 'success';
            } 
            elseif ($notification['appointment_status'] === 'Pending') {
                // PENDING STATE
                $iconClass = 'icon-pending';
                $iconName = 'bx-time';
                $statusClass = 'warning';
            }
            
            // 4. Calculate time ago (Your existing logic)
            $timeAgo = time() - strtotime($notification['created_at']);
            if ($timeAgo < 60) {
                $timeText = 'Just now';
            } elseif ($timeAgo < 3600) {
                $timeText = floor($timeAgo / 60) . ' minute' . (floor($timeAgo / 60) > 1 ? 's' : '') . ' ago';
            } elseif ($timeAgo < 86400) {
                $timeText = floor($timeAgo / 3600) . ' hour' . (floor($timeAgo / 3600) > 1 ? 's' : '') . ' ago';
            } elseif ($timeAgo < 604800) {
                $timeText = floor($timeAgo / 86400) . ' day' . (floor($timeAgo / 86400) > 1 ? 's' : '') . ' ago';
            } else {
                $timeText = date('M d, Y', strtotime($notification['created_at']));
            }
            
            // 5. Parse scheduled_for
            $appointmentDate = '';
            $appointmentTime = '';
            if ($notification['scheduled_for']) {
                $scheduledDateTime = new DateTime($notification['scheduled_for']);
                $appointmentDate = $scheduledDateTime->format('M d, Y');
                $appointmentTime = $scheduledDateTime->format('h:i A');
            }
            
            // 6. Department Display
            $departmentDisplay = $notification['department_acronym'] 
                ? $notification['department_acronym'] 
                : $notification['department_name'];
            ?>
            
            <div class="card notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                data-type="<?php echo $notificationType; ?>"
                onclick="viewAppointment(<?php echo $notification['appointment_id']; ?>, '<?php echo $notificationType; ?>')">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="notification-icon <?php echo $iconClass; ?> mr-3">
                            <i class='bx <?php echo $iconName; ?>'></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap">
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($departmentDisplay); ?>
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="badge badge-primary badge-pill ml-2">New</span>
                                    <?php endif; ?>
                                </h5>
                                <span class="notification-time">
                                    <i class='bx bx-time-five'></i> <?php echo $timeText; ?>
                                </span>
                            </div>
                            
                            <p class="mb-2">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <?php if ($notification['transaction_id']): ?>
                                <span class="badge badge-light mr-2 mb-1">
                                    <i class='bx bx-receipt'></i> 
                                    <?php echo htmlspecialchars($notification['transaction_id']); ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($notification['service_name']): ?>
                                <span class="badge badge-info mr-2 mb-1">
                                    <i class='bx bx-briefcase'></i> 
                                    <?php echo htmlspecialchars($notification['service_name']); ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($appointmentDate && !$isRescheduleRejected): ?>
                                <span class="badge badge-light mr-2 mb-1">
                                    <i class='bx bx-calendar'></i> 
                                    <?php echo $appointmentDate; ?>
                                </span>
                                <span class="badge badge-light mr-2 mb-1">
                                    <i class='bx bx-time'></i> 
                                    <?php echo $appointmentTime; ?>
                                </span>
                                <?php endif; ?>
                                
                                <span class="badge badge-<?php echo $statusClass; ?> mb-1">
                                    <?php echo ucfirst($statusText); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class='bx bx-bell-off'></i>
            <h4>No Notifications</h4>
            <p>You're all caught up! You'll be notified here when there are updates to your appointments.</p>
        </div>
    <?php endif; ?>
</div>
</div>

<script>
    // Filter notifications
    $('.filter-btn').click(function() {
        $('.filter-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        
        const filter = $(this).data('filter');
        
        if (filter === 'all') {
            $('.notification-card').show();
        } else {
            $('.notification-card').hide();
            $('.notification-card[data-type="' + filter + '"]').show();
        }
        
        // Check if any notifications are visible
        checkEmptyState();
    });
    
    // View appointment details
    function viewAppointment(appointmentId, status) {
        // Redirect based on appointment status with appointment ID as parameter
        if (status === 'completed') {
            loadContent('residents_completed_appointments.php?highlight=' + appointmentId);
        } else if (status === 'pending') {
            loadContent('residents_pending_appointments.php?highlight=' + appointmentId);
        }else if (status === 'rejected') {
            loadContent('residents_noshow_appointments.php?highlight=' + appointmentId);
        } else {
            // Default fallback
            loadContent('residents_view_appointments.php?id=' + appointmentId);
        }
    }
    
    // Delete single notification
    function deleteNotification(notificationId, button) {
        if (confirm('Are you sure you want to delete this notification?')) {
            $.ajax({
                url: 'delete_notification.php',
                method: 'POST',
                data: { notification_id: notificationId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(button).closest('.notification-card').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if no notifications left
                            checkEmptyState();
                            
                            // Update filter counts
                            updateFilterCounts();
                        });
                    } else {
                        alert('Error deleting notification: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error deleting notification. Please try again.');
                }
            });
        }
    }
    
    // Clear all notifications
    function clearAllNotifications() {
        if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
            $.ajax({
                url: 'clear_all_notifications.php',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showEmptyState();
                        
                        // Update the header to remove notification badge
                        $('#notificationBadge').hide();
                        
                        // Update filter counts
                        updateFilterCounts();
                    } else {
                        alert('Error clearing notifications: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error clearing notifications. Please try again.');
                }
            });
        }
    }
    
    // Check if empty state should be shown
    function checkEmptyState() {
        const visibleCards = $('.notification-card:visible').length;
        const emptyState = $('.empty-state');
        
        if (visibleCards === 0) {
            if (emptyState.length === 0) {
                $('.notifications-list').append(`
                    <div class="empty-state">
                        <i class='bx bx-bell-off'></i>
                        <h4>No Notifications</h4>
                        <p>No notifications found for this filter.</p>
                    </div>
                `);
            }
        } else {
            emptyState.remove();
        }
    }
    
    // Show empty state
    function showEmptyState() {
        $('.notifications-list').html(`
            <div class="empty-state">
                <i class='bx bx-bell-off'></i>
                <h4>No Notifications</h4>
                <p>You're all caught up! You'll be notified here when there are updates to your appointments.</p>
            </div>
        `);
    }
    
    // Update filter counts
    function updateFilterCounts() {
        const totalCount = $('.notification-card').length;
        const pendingCount = $('.notification-card[data-type="pending"]').length;
        const completedCount = $('.notification-card[data-type="completed"]').length;
        
        $('.filter-btn[data-filter="all"]').html('<i class="bx bx-list-ul"></i> All (' + totalCount + ')');
        $('.filter-btn[data-filter="pending"]').html('<i class="bx bx-time"></i> Pending (' + pendingCount + ')');
        $('.filter-btn[data-filter="completed"]').html('<i class="bx bx-check-circle"></i> Completed (' + completedCount + ')');
    }
</script>