<?php 
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';
$authId = $_SESSION['auth_id'];

// Resolve resident_id from auth_id
$stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$authId]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    die("Resident profile not found.");
}
$residentId = $resident['id'];

// Fetch No Show appointments
$queryNoShow = "
    SELECT a.id, a.transaction_id, a.scheduled_for, a.reason,
           d.name AS department_name, s.service_name, a.updated_at
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.resident_id = :resident_id AND a.status = 'No Show'
    ORDER BY a.scheduled_for DESC
";
$stmtNoShow = $pdo->prepare($queryNoShow);
$stmtNoShow->execute(['resident_id' => $residentId]);
$noShowAppointments = $stmtNoShow->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No-Show Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        /* Modern Card Styles */
.appointments-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
}

.page-header {
    background: linear-gradient(to right, #0D92F4, #27548A);
    border-radius: 20px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.page-header h3 {
    color: white;
    font-weight: 700;
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    z-index: 1;
}

.page-header p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    font-size: 1rem;
    position: relative;
    z-index: 1;
}

.header-icon {
    background: rgba(255, 255, 255, 0.2);
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

/* Info Alert */
.info-alert {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-left: 4px solid #f39c12;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: start;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.15);
}

.info-alert-icon {
    width: 40px;
    height: 40px;
    background: #f39c12;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.info-alert-content h6 {
    color: #856404;
    font-weight: 700;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.info-alert-content p {
    color: #856404;
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    font-size: 3rem;
    color: #27ae60;
}

.empty-state h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #7f8c8d;
    margin: 0;
}

/* Appointment Cards - COMPACT VERSION */
.appointment-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    border-left: 4px solid #e74c3c;
    position: relative;
    overflow: hidden;
}

.appointment-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.08), transparent);
    border-radius: 0 0 0 100%;
}

.appointment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(231, 76, 60, 0.12);
}

.appointment-number {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.card-header-section {
    margin-bottom: 1rem;
}

.transaction-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.25);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: linear-gradient(135deg, #e67e22, #d35400);
    color: white;
    padding: 0.3rem 0.65rem;
    border-radius: 16px;
    font-weight: 600;
    font-size: 0.75rem;
    margin-left: 0.5rem;
    box-shadow: 0 2px 6px rgba(230, 126, 34, 0.25);
}

.info-grid {
    display: grid;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
}

.info-icon {
    width: 34px;
    height: 34px;
    background: linear-gradient(135deg, #fadbd8, #f5b7b1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #e74c3c;
    font-size: 1rem;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.7rem;
    color: #7f8c8d;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.2rem;
}

.info-value {
    color: #2c3e50;
    font-weight: 600;
    font-size: 0.9rem;
}

.schedule-highlight {
    background: linear-gradient(135deg, #fadbd8, #f5b7b1);
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.schedule-icon {
    width: 36px;
    height: 36px;
    background: #e74c3c;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.schedule-text {
    flex: 1;
}

.schedule-date {
    font-weight: 700;
    color: #922b21;
    font-size: 0.95rem;
    margin-bottom: 0.1rem;
}

.schedule-time {
    font-size: 0.8rem;
    color: #922b21;
    opacity: 0.8;
}

.reason-box {
    background: #f8f9fa;
    border-left: 3px solid #e74c3c;
    border-radius: 8px;
    padding: 0.85rem;
    margin-bottom: 0.85rem;
}

.reason-label {
    font-size: 0.7rem;
    color: #7f8c8d;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.reason-text {
    color: #2c3e50;
    font-size: 0.85rem;
    line-height: 1.4;
    margin: 0;
}

.timestamp-box {
    background: #fff3cd;
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.timestamp-icon {
    width: 28px;
    height: 28px;
    background: #f39c12;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
}

.timestamp-content {
    flex: 1;
}

.timestamp-label {
    font-size: 0.65rem;
    color: #856404;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.1rem;
}

.timestamp-value {
    color: #856404;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Compact Button Styles */
.btn-block {
    padding: 0.65rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9, #21618c);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
}

.btn-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e67e22, #d35400);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
    color: white;
}

.alert {
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-info {
    background: linear-gradient(135deg, #d6eaf8, #aed6f1);
    border-left: 3px solid #3498db;
    color: #1b4f72;
}

.alert strong {
    display: block;
    margin-bottom: 0.25rem;
}

/* Modal Styles */
.modal-content {
    border-radius: 15px;
    overflow: hidden;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 1.25rem 1.5rem;
    border-bottom: none;
}

.modal-title {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header .close {
    color: white;
    opacity: 0.9;
    text-shadow: none;
    padding: 0;
    margin: 0;
}

.modal-header .close:hover {
    opacity: 1;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e9ecef;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-group label i {
    color: #3498db;
    font-size: 1rem;
}

.form-control {
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
}

textarea.form-control {
    resize: vertical;
}

.form-text {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.4rem;
    font-size: 0.8rem;
}

.form-text i {
    font-size: 0.75rem;
}

/* Responsive Design */
@media (min-width: 769px) {
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Tablet Responsive (768px - 991px) */
@media (max-width: 991px) {
    .modal-dialog {
        max-width: 90%;
        margin: 1rem auto;
    }

    .modal-header {
        padding: 1rem 1.25rem;
    }

    .modal-title {
        font-size: 1.1rem;
    }

    .modal-title i {
        font-size: 1.1rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .modal-footer {
        padding: 0.85rem 1.25rem;
    }

    .form-group label {
        font-size: 0.85rem;
    }

    .form-control {
        font-size: 0.85rem;
        padding: 0.6rem 0.75rem;
    }

    .btn-block {
        padding: 0.6rem 0.9rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 768px) {
    .appointments-container {
        padding: 1rem;
    }

    .page-header {
        padding: 1.5rem 1.25rem;
        border-radius: 16px;
    }

    .page-header h3 {
        font-size: 1.35rem;
    }

    .info-alert {
        flex-direction: column;
    }

    /* Modal Mobile Styles */
    .modal-dialog {
        max-width: 95%;
        margin: 0.5rem auto;
    }

    .modal-content {
        border-radius: 12px;
    }

    .modal-header {
        padding: 1rem;
        flex-wrap: wrap;
    }

    .modal-title {
        font-size: 1rem;
        flex: 1;
    }

    .modal-header .close {
        font-size: 1.3rem;
    }

    .modal-body {
        padding: 1rem;
        max-height: 70vh;
        overflow-y: auto;
    }

    .modal-footer {
        padding: 0.75rem 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    .modal-footer .btn {
        width: 100%;
        margin: 0;
    }

    .modal-footer .btn-secondary {
        order: 2;
    }

    .modal-footer .btn-primary {
        order: 1;
    }

    /* Modal Alert Box */
    .modal-body .alert {
        padding: 0.85rem;
        margin-bottom: 1.25rem;
    }

    .modal-body .alert > div {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }

    .modal-body .alert i {
        font-size: 1.1rem;
    }

    .modal-body .alert strong {
        font-size: 0.9rem;
    }

    .modal-body .alert small {
        font-size: 0.8rem;
    }

    /* Form Elements Mobile */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        font-size: 0.85rem;
        flex-wrap: wrap;
    }

    .form-group label i {
        font-size: 0.95rem;
    }

    .form-control {
        font-size: 0.85rem;
        padding: 0.6rem 0.75rem;
    }

    textarea.form-control {
        min-height: 100px;
    }

    .form-text {
        font-size: 0.75rem;
    }

    .form-text i {
        font-size: 0.7rem;
    }
}

@media (max-width: 480px) {
    .page-header h3 {
        font-size: 1.2rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .transaction-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }

    .appointment-card {
        padding: 1rem;
    }

    .info-grid {
        gap: 0.6rem;
    }

    /* Modal Extra Small Screens */
    .modal-dialog {
        max-width: 100%;
        margin: 0;
        height: 100vh;
        display: flex;
        align-items: center;
    }

    .modal-content {
        border-radius: 0;
        max-height: 95vh;
    }

    .modal-header {
        padding: 0.85rem;
    }

    .modal-title {
        font-size: 0.95rem;
    }

    .modal-title i {
        font-size: 1rem;
    }

    .modal-body {
        padding: 0.85rem;
        max-height: 60vh;
    }

    .modal-footer {
        padding: 0.75rem;
    }

    .form-group label {
        font-size: 0.8rem;
    }

    .form-control {
        font-size: 0.8rem;
        padding: 0.55rem 0.7rem;
    }

    .btn-block {
        padding: 0.6rem 0.85rem;
        font-size: 0.85rem;
    }

    .modal-body .alert {
        padding: 0.75rem;
    }

    .modal-body .alert strong {
        font-size: 0.85rem;
    }

    .modal-body .alert small {
        font-size: 0.75rem;
    }
}

/* Landscape Mobile Fix */
@media (max-width: 768px) and (orientation: landscape) {
    .modal-dialog {
        margin: 0.5rem auto;
        max-height: 95vh;
    }

    .modal-body {
        max-height: 50vh;
        overflow-y: auto;
    }

    .modal-footer {
        flex-direction: row;
    }

    .modal-footer .btn {
        width: auto;
        flex: 1;
    }
}
    </style>
</head>
<body>
    <div class="appointments-container">
        <!-- Page Header -->
        <div class="page-header">
            <h3>
                <div class="header-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <span>No-Show Appointments</span>
            </h3>
            <p>Appointments you missed without prior cancellation</p>
        </div>

        <!-- Info Alert -->
        <div class="info-alert">
            <div class="info-alert-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-alert-content">
                <h6>What is a No-Show?</h6>
                <p>A no-show occurs when you don't attend your scheduled appointment and it becomes more than 24 hours past the appointment time.</p>
            </div>
        </div>

        <?php if (empty($noShowAppointments)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h5>No Missed Appointments</h5>
                <p>Great! You have no no-show appointments. Keep up the good attendance!</p>
            </div>
        <?php else: ?>
            <!-- Appointment Cards -->
            <?php foreach ($noShowAppointments as $index => $appt): ?>
                <div class="appointment-card">
                    <div class="appointment-number"><?= $index + 1 ?></div>

                    <div class="card-header-section">
                        <div class="transaction-badge">
                            <i class="fas fa-hashtag"></i>
                            <?= htmlspecialchars($appt['transaction_id']) ?>
                        </div>
                        <span class="status-badge">
                            <i class="fas fa-exclamation-circle"></i>
                            No Show
                        </span>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?= htmlspecialchars($appt['department_name']) ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-concierge-bell"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Service</div>
                                <div class="info-value"><?= htmlspecialchars($appt['service_name']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="schedule-highlight">
                        <div class="schedule-icon">
                            <i class="far fa-calendar-times"></i>
                        </div>
                        <div class="schedule-text">
                            <div class="schedule-date">
                                Missed: <?= date('F d, Y', strtotime($appt['scheduled_for'])) ?>
                            </div>
                            <div class="schedule-time">
                                <i class="far fa-clock"></i>
                                <?= date('h:i A', strtotime($appt['scheduled_for'])) ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($appt['reason'])): ?>
                    <div class="reason-box">
                        <div class="reason-label">
                            <i class="fas fa-comment-dots"></i>
                            Original Request Reason
                        </div>
                        <p class="reason-text"><?= htmlspecialchars($appt['reason']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php 
                    $checkRequest = $pdo->prepare("
                        SELECT id, status, requested_schedule 
                        FROM reschedule_requests 
                        WHERE appointment_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $checkRequest->execute([$appt['id']]);
                    $rescheduleRequest = $checkRequest->fetch(PDO::FETCH_ASSOC);
                    ?>

                    <?php if ($rescheduleRequest && $rescheduleRequest['status'] == 'Pending'): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-clock"></i>
                            <strong>Reschedule Request Pending</strong><br>
                            <small>Requested new date: <?= date('F d, Y h:i A', strtotime($rescheduleRequest['requested_schedule'])) ?></small>
                        </div>
                    <?php elseif ($rescheduleRequest && $rescheduleRequest['status'] == 'Rejected'): ?>
                        <button class="btn btn-warning btn-block" onclick="openRescheduleModal(<?= $appt['id'] ?>, '<?= htmlspecialchars($appt['transaction_id']) ?>', '<?= $appt['scheduled_for'] ?>')">
                            <i class="fas fa-redo"></i> Request Reschedule Again
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-block" onclick="openRescheduleModal(<?= $appt['id'] ?>, '<?= htmlspecialchars($appt['transaction_id']) ?>', '<?= $appt['scheduled_for'] ?>')">
                            <i class="fas fa-calendar-plus"></i> Request Reschedule
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- Reschedule Request Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 1.25rem 1.5rem;">
                <h5 class="modal-title d-flex align-items-center gap-2" style="margin: 0; font-weight: 600;">
                    <i class="fas fa-calendar-plus" style="font-size: 1.2rem;"></i>
                    <span>Request Reschedule</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.9; text-shadow: none; padding: 0; margin: 0;">
                    <span style="font-size: 1.5rem;">&times;</span>
                </button>
            </div>
            <form id="rescheduleForm">
                <input type="hidden" id="reschedule_appointment_id" name="appointment_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="alert" style="background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-left: 4px solid #3498db; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="color: #2874a6; font-size: 1.2rem; margin-top: 2px;"></i>
                            <div style="flex: 1;">
                                <strong style="color: #1b4f72; display: block; margin-bottom: 0.5rem; font-size: 0.95rem;">Original Appointment:</strong>
                                <div style="color: #1b4f72;">
                                    <div id="original_transaction" style="font-weight: 600; margin-bottom: 0.25rem;"></div>
                                    <small id="original_schedule" style="font-size: 0.85rem; opacity: 0.9;"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem; font-size: 0.9rem;">
                            <i class="fas fa-calendar" style="color: #3498db; font-size: 1rem;"></i>
                            <span>Select New Date & Time</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" class="form-control" name="requested_schedule" required 
                            min="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>"
                            style="padding: 0.65rem 0.85rem; border-radius: 8px; border: 2px solid #e0e0e0; font-size: 0.9rem;">
                        <small class="form-text text-muted" style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.4rem; font-size: 0.8rem;">
                            <i class="fas fa-exclamation-circle" style="font-size: 0.75rem;"></i>
                            <span>Must be at least 24 hours from now</span>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem; font-size: 0.9rem;">
                            <i class="fas fa-comment" style="color: #3498db; font-size: 1rem;"></i>
                            <span>Reason for Rescheduling</span>
                            <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="reason" rows="4" required 
                                placeholder="Please explain why you missed the appointment and why you need to reschedule..."
                                style="padding: 0.65rem 0.85rem; border-radius: 8px; border: 2px solid #e0e0e0; font-size: 0.9rem; resize: vertical;"></textarea>
                        <small class="form-text text-muted" style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.4rem; font-size: 0.8rem;">
                            <i class="fas fa-info-circle" style="font-size: 0.75rem;"></i>
                            <span>This will be reviewed by the department personnel</span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8f9fa; padding: 1rem 1.5rem; border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" 
                        style="padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" 
                        style="padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; background: linear-gradient(135deg, #3498db, #2980b9); border: none; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-paper-plane"></i>
                        <span>Submit Request</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function openRescheduleModal(appointmentId, transactionId, originalSchedule) {
    $('#reschedule_appointment_id').val(appointmentId);
    $('#original_transaction').text(transactionId);
    $('#original_schedule').text('Originally scheduled: ' + new Date(originalSchedule).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true
    }));
    $('#rescheduleModal').modal('show');
}

$('#rescheduleForm').on('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
    
    $.ajax({
        url: 'ajax/submit_reschedule_request.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            submitBtn.prop('disabled', false).html(originalBtnText);
            
            if (response.success) {
                $('#rescheduleModal').modal('hide');
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr) {
            submitBtn.prop('disabled', false).html(originalBtnText);
            console.error('Error:', xhr.responseText);
            showAlert('An error occurred. Please try again.', 'danger');
        }
    });
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    $('body').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(), 5000);
}
</script>
</body>
</html>