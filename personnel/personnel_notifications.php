<?php
session_start();
include '../conn.php';

// Check if user is logged in
if (!isset($_SESSION['auth_id'])) {
    header('Location: ../login.php');
    exit;
}

$authId = $_SESSION['auth_id'];

// Get personnel_id from lgu_personnel table using auth_id
try {
    $personnelStmt = $pdo->prepare("SELECT id FROM lgu_personnel WHERE auth_id = ? LIMIT 1");
    $personnelStmt->execute([$authId]);
    $personnelData = $personnelStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personnelData) {
        die("Personnel profile not found.");
    }
    
    $personnelId = $personnelData['id'];
} catch (PDOException $e) {
    die("Error fetching personnel data: " . $e->getMessage());
}

// Mark all notifications as read when page is loaded
try {
    $markReadStmt = $pdo->prepare("
        UPDATE notifications n
        INNER JOIN appointments a ON n.appointment_id = a.id
        SET n.is_read = 1
        WHERE a.personnel_id = ? AND n.is_read = 0
    ");
    $markReadStmt->execute([$personnelId]);
} catch (PDOException $e) {
    // Log error but don't stop page load
    error_log("Error marking notifications as read: " . $e->getMessage());
}

// Fetch all notifications
try {
    $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.message,
                n.created_at,
                n.is_read,
                n.appointment_id,
                a.scheduled_for,
                a.status as appointment_status,
                a.transaction_id,
                r.first_name,
                r.last_name,
                (SELECT email FROM auth WHERE id = r.auth_id) as email
            FROM notifications n
            INNER JOIN appointments a ON n.appointment_id = a.id
            INNER JOIN residents r ON a.resident_id = r.id  -- Changed this JOIN
            WHERE n.personnel_id = ? 
            AND n.recipient_type = 'Personnel'              -- Added this filter
            ORDER BY n.created_at DESC
            LIMIT 100
        ");
    
    $stmt->execute([$personnelId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
    $notifications = [];
}
?>

<style>
    .notification-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        margin-bottom: 15px;
        cursor: pointer;
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
    }
    
    .icon-success {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .icon-warning {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .icon-danger {
        background-color: #fee2e2;
        color: #991b1b;
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
    }
    
    .filter-btn {
        margin-right: 10px;
        margin-bottom: 10px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class='bx bx-bell'></i> Notifications</h2>
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
            All (<?php echo count($notifications); ?>)
        </button>
        <button class="btn btn-outline-primary filter-btn" data-filter="new">
            <i class='bx bx-calendar-plus'></i> New Appointments
        </button>
        <button class="btn btn-outline-primary filter-btn" data-filter="rescheduled">
            <i class='bx bx-calendar-edit'></i> Rescheduled
        </button>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                // Determine icon and color based on notification type
                $iconClass = 'icon-info';
                $iconName = 'bx-bell';
                $notificationType = '';
                
                if (stripos($notification['message'], 'cancelled') !== false) {
                    $iconClass = 'icon-danger';
                    $iconName = 'bx-calendar-x';
                    $notificationType = 'cancelled';
                } elseif (stripos($notification['message'], 'rescheduled') !== false) {
                    $iconClass = 'icon-warning';
                    $iconName = 'bx-calendar-edit';
                    $notificationType = 'rescheduled';
                } elseif (stripos($notification['message'], 'new') !== false || stripos($notification['message'], 'booked') !== false) {
                    $iconClass = 'icon-success';
                    $iconName = 'bx-calendar-plus';
                    $notificationType = 'new';
                }
                
                // Calculate time ago
                $timeAgo = time() - strtotime($notification['created_at']);
                if ($timeAgo < 60) {
                    $timeText = 'Just now';
                } elseif ($timeAgo < 3600) {
                    $timeText = floor($timeAgo / 60) . ' minutes ago';
                } elseif ($timeAgo < 86400) {
                    $timeText = floor($timeAgo / 3600) . ' hours ago';
                } else {
                    $timeText = floor($timeAgo / 86400) . ' days ago';
                }
                
                // Parse scheduled_for datetime
                $appointmentDate = '';
                $appointmentTime = '';
                if ($notification['scheduled_for']) {
                    $scheduledDateTime = new DateTime($notification['scheduled_for']);
                    $appointmentDate = $scheduledDateTime->format('M d, Y');
                    $appointmentTime = $scheduledDateTime->format('h:i A');
                }
                ?>
                
                <div class="card notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                    data-type="<?php echo $notificationType; ?>"
                    data-transaction-id="<?php echo htmlspecialchars($notification['transaction_id']); ?>"
                    style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="notification-icon <?php echo $iconClass; ?> mr-3">
                                <i class='bx <?php echo $iconName; ?>'></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                    </h5>
                                    <span class="notification-time">
                                        <i class='bx bx-time-five'></i> <?php echo $timeText; ?>
                                    </span>
                                </div>
                                <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="d-flex align-items-center flex-wrap">
                                    <?php if ($notification['transaction_id']): ?>
                                    <span class="badge badge-light mr-2 mb-1">
                                        <i class='bx bx-receipt'></i> 
                                        <?php echo htmlspecialchars($notification['transaction_id']); ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointmentDate): ?>
                                    <span class="badge badge-light mr-2 mb-1">
                                        <i class='bx bx-calendar'></i> 
                                        <?php echo $appointmentDate; ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointmentTime): ?>
                                    <span class="badge badge-light mr-2 mb-1">
                                        <i class='bx bx-time'></i> 
                                        <?php echo $appointmentTime; ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $statusClass = 'secondary';
                                    $statusText = $notification['appointment_status'];
                                    switch(strtolower($notification['appointment_status'])) {
                                        case 'pending': 
                                            $statusClass = 'warning'; 
                                            break;
                                        case 'completed': 
                                            $statusClass = 'success'; 
                                            break;
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?> mb-1">
                                        <?php echo ucfirst($statusText); ?>
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="event.stopPropagation(); deleteNotification(<?php echo $notification['id']; ?>, this)">
                                        <i class='bx bx-trash'></i> Delete
                                    </button>
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
                <p>You're all caught up! Check back later for new updates.</p>
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
    });
    
    // Make entire card clickable to view appointment
    $(document).on('click', '.notification-card', function(e) {
        // Don't trigger if clicking the delete button
        if ($(e.target).closest('.btn-outline-danger').length > 0) {
            return;
        }
        
        const transactionId = $(this).data('transaction-id');
        
        // Store transaction ID in sessionStorage for highlighting
        sessionStorage.setItem('highlightAppointment', transactionId);
        
        // Redirect to manage appointments page
        loadContent('personnel_manage_appointments.php');
    });
    
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
                            if ($('.notification-card').length === 0) {
                                $('.notifications-list').html(`
                                    <div class="empty-state">
                                        <i class='bx bx-bell-off'></i>
                                        <h4>No Notifications</h4>
                                        <p>You're all caught up! Check back later for new updates.</p>
                                    </div>
                                `);
                            }
                            
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
                        $('.notifications-list').html(`
                            <div class="empty-state">
                                <i class='bx bx-bell-off'></i>
                                <h4>No Notifications</h4>
                                <p>You're all caught up! Check back later for new updates.</p>
                            </div>
                        `);
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
    
    // Update filter counts
    function updateFilterCounts() {
        const totalCount = $('.notification-card').length;
        $('.filter-btn[data-filter="all"]').html('All (' + totalCount + ')');
    }
</script>