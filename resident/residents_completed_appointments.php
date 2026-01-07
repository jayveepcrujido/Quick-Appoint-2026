<?php 
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';
$authId = $_SESSION['auth_id'];

$stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$authId]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    die("Resident profile not found.");
}
$residentId = $resident['id'];

$highlightAppointmentId = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;

$queryCompleted = "
    SELECT a.id, a.transaction_id, a.scheduled_for, a.has_sent_feedback, 
           d.name AS department_name, s.service_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.resident_id = :resident_id AND a.status = 'Completed'
    ORDER BY a.scheduled_for DESC
";
$stmtCompleted = $pdo->prepare($queryCompleted);
$stmtCompleted->execute(['resident_id' => $residentId]);
$completedAppointments = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .appointments-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
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
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 3rem;
            color: #3498db;
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

        .appointment-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #27ae60;
            position: relative;
            overflow: hidden;
        }

        .appointment-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), transparent);
            border-radius: 0 0 0 100%;
        }

        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(39, 174, 96, 0.15);
        }

        .appointment-number {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .card-header-section {
            margin-bottom: 1.25rem;
        }

        .transaction-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .info-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #27ae60;
            font-size: 1.1rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .schedule-highlight {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .schedule-icon {
            width: 40px;
            height: 40px;
            background: #28a745;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .schedule-text {
            flex: 1;
        }

        .schedule-date {
            font-weight: 700;
            color: #155724;
            font-size: 1rem;
            margin-bottom: 0.125rem;
        }

        .schedule-time {
            font-size: 0.85rem;
            color: #155724;
            opacity: 0.8;
        }

        .button-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .feedback-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            flex: 1;
            min-width: 200px;
        }

        .feedback-btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .feedback-btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
            color: white;
            text-decoration: none;
        }

        .feedback-btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            cursor: default;
        }

        .feedback-btn-purple {
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .feedback-btn-purple:hover {
            background: linear-gradient(135deg, #21618c, #2980b9);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
            color: white;
            text-decoration: none;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-dialog {
            max-width: 900px;
        }

        .modal-header {
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
            border-bottom: 3px solid #21618c;
        }

        .modal-header .close {
            color: white;
            opacity: 0.9;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 2rem;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-title i {
            font-size: 1.5rem;
        }

        @media (min-width: 769px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .button-group {
                flex-direction: column;
            }
            
            .button-group .feedback-btn {
                width: 100%;
                min-width: unset;
            }
        }

        @media (max-width: 480px) {
            .page-header h3 {
                font-size: 1.2rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        .highlight-appointment {
            animation: highlightFade 2s ease-in-out;
        }

        @keyframes highlightFade {
            0% {
                background-color: #fff3cd;
                box-shadow: 0 0 20px rgba(255, 193, 7, 0.5);
                transform: scale(1.02);
            }
            100% {
                background-color: white;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                transform: scale(1);
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
                    <i class="fas fa-check-circle"></i>
                </div>
                <span>Completed Appointments</span>
            </h3>
            <p>View all your successfully completed appointments</p>
        </div>

        <?php if (empty($completedAppointments)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h5>No Completed Appointments</h5>
                <p>You have no completed appointments yet.</p>
            </div>
        <?php else: ?>
            <!-- Appointment Cards -->
            <?php foreach ($completedAppointments as $index => $appt): ?>
                <div class="appointment-card <?php echo ($appt['id'] == $highlightAppointmentId) ? 'to-highlight' : ''; ?>" 
                            id="appointment-<?php echo $appt['id']; ?>">
                    <div class="appointment-number"><?= $index + 1 ?></div>

                    <div class="card-header-section">
                        <div class="transaction-badge">
                            <i class="fas fa-hashtag"></i>
                            <?= htmlspecialchars($appt['transaction_id']) ?>
                        </div>
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
                            <i class="far fa-calendar-alt"></i>
                        </div>
                        <div class="schedule-text">
                            <div class="schedule-date">
                                <?= date('F d, Y', strtotime($appt['scheduled_for'])) ?>
                            </div>
                            <div class="schedule-time">
                                <i class="far fa-clock"></i>
                                <?= date('h:i A', strtotime($appt['scheduled_for'])) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($appt['has_sent_feedback'] == 1): ?>
                        <div class="button-group">
                            <button class="feedback-btn feedback-btn-success" disabled>
                                <i class="fas fa-check-circle"></i>
                                <span>Feedback Submitted</span>
                            </button>
                            <button onclick="viewFeedbackResponse(<?= $appt['id'] ?>)" class="feedback-btn feedback-btn-purple">
                                <i class="fas fa-eye"></i>
                                <span>View Response</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="feedback_form.php?appointment_id=<?= $appt['id'] ?>" class="feedback-btn feedback-btn-primary">
                            <i class="fas fa-comment-dots"></i>
                            <span>Answer Feedback</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Feedback Response Modal -->
    <div class="modal fade" id="feedbackResponseModal" tabindex="-1" role="dialog" aria-labelledby="feedbackResponseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackResponseModalLabel">
                        <i class="fas fa-chart-bar"></i> Feedback Response
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="feedbackResponseContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading feedback...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeedbackResponse(appointmentId) {
            console.log('Opening feedback for appointment ID:', appointmentId);
            
            $('#feedbackResponseModal').modal('show');
            
            $('#feedbackResponseContent').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading feedback...</p>
                </div>
            `);
            
            $.ajax({
                url: 'view_feedback_response.php',
                method: 'GET',
                data: { appointment_id: appointmentId },
                dataType: 'json',
                success: function(data) {
                    console.log('Feedback data received:', data);
                    
                    if (data.error) {
                        $('#feedbackResponseContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${data.error}
                            </div>
                        `);
                        return;
                    }
                    
                    let html = generateFeedbackHTML(data);
                    $('#feedbackResponseContent').html(html);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        responseText: xhr.responseText
                    });
                    
                    let errorMessage = 'Error loading feedback. ';
                    
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.error) {
                            errorMessage = errorData.error;
                        }
                    } catch (e) {
                        if (xhr.status === 404) {
                            errorMessage = 'The feedback file was not found. Please check the file path.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error occurred. Please check error logs.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Network error. Please check your connection.';
                        } else {
                            errorMessage += 'Please try again later.';
                        }
                    }
                    
                    $('#feedbackResponseContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> 
                            <strong>${errorMessage}</strong><br>
                            <small class="text-muted">Status Code: ${xhr.status} | ${status}</small>
                        </div>
                    `);
                }
            });
        }
        
        function generateFeedbackHTML(data) {
            const appt = data.appointment;
            const total = data.totalResponses;
            
            const percentages = {};
            Object.keys(data.ratingCounts).forEach(key => {
                percentages[key] = total > 0 ? (data.ratingCounts[key] / total * 100).toFixed(0) : 0;
            });
            
            const barColors = {
                'Strongly Agree': '#27ae60',
                'Agree': '#3498db',
                'Neither': '#f39c12',
                'Disagree': '#e67e22',
                'Strongly Disagree': '#e74c3c'
            };
            
            const badgeColors = {
                'Strongly Agree': 'linear-gradient(135deg, #27ae60, #2ecc71)',
                'Agree': 'linear-gradient(135deg, #3498db, #5dade2)',
                'Neither Agree nor Disagree': 'linear-gradient(135deg, #f39c12, #f1c40f)',
                'Disagree': 'linear-gradient(135deg, #e67e22, #e74c3c)',
                'Strongly Disagree': 'linear-gradient(135deg, #c0392b, #e74c3c)'
            };
            
            let html = `
                <!-- Appointment Info -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 2px solid #f0f0f0;">
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2980b9; font-size: 0.9rem;">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">Transaction ID</div>
                            <div style="color: #2c3e50; font-weight: 600;">${appt.transaction_id}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2980b9; font-size: 0.9rem;">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">Department</div>
                            <div style="color: #2c3e50; font-weight: 600;">${appt.department_name}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2980b9; font-size: 0.9rem;">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; font-weight: 600;">Service</div>
                            <div style="color: #2c3e50; font-weight: 600;">${appt.service_name}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Feedback Summary -->
                <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border-left: 4px solid #2980b9;">
                    <h6 style="color: #2980b9; margin-bottom: 1.5rem; font-weight: 700; font-size: 1.1rem;">
                        <i class="fas fa-chart-line" style="font-size: 0.95rem;"></i> Feedback Summary
                    </h6>
                    
                    <div style="display: flex; align-items: center; justify-content: space-around; flex-wrap: wrap; gap: 2rem; margin-bottom: 2rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 3rem; font-weight: 700; color: ${data.satisfactionColor};">
                                ${data.averageRating}
                            </div>
                            <div style="font-size: 1rem; font-weight: 600; color: ${data.satisfactionColor}; text-transform: uppercase;">
                                ${data.satisfactionLevel}
                            </div>
                            <div style="color: #7f8c8d; font-size: 0.85rem; margin-top: 0.5rem;">
                                out of 5.0
                            </div>
                        </div>
                        
                        <div style="flex: 1; min-width: 250px;">
                            ${Object.keys(data.ratingCounts).map(label => `
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="min-width: 110px; font-size: 0.85rem; color: #555; font-weight: 500;">${label}</div>
                                    <div style="flex: 1; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                                        <div style="height: 100%; background: ${barColors[label]}; width: ${percentages[label]}%; transition: width 0.6s ease;"></div>
                                    </div>
                                    <div style="min-width: 30px; text-align: center; font-weight: 600; color: #555; font-size: 0.85rem;">${data.ratingCounts[label]}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <!-- Insights -->
                    <div style="border-top: 2px solid #dee2e6; padding-top: 1.5rem;">
                        <h6 style="color: #2980b9; margin-bottom: 1rem; font-weight: 600; font-size: 1rem;">
                            <i class="fas fa-lightbulb" style="font-size: 0.95rem;"></i> Key Insights
                        </h6>
                        
                        ${data.positiveCount > 0 ? `
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 10px;">
                            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #d4edda, #c3e6cb); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #27ae60; font-size: 0.95rem;">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0 0 0.5rem 0; color: #2c3e50; font-weight: 600;">Positive Experience</h6>
                                <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">${data.positiveCount} out of ${total} responses were positive, indicating overall satisfaction.</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${data.negativeCount > 0 ? `
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 10px;">
                            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #e74c3c; font-size: 0.95rem;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0 0 0.5rem 0; color: #2c3e50; font-weight: 600;">Areas for Improvement</h6>
                                <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">${data.negativeCount} response${data.negativeCount > 1 ? 's' : ''} indicated dissatisfaction.</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${appt.suggestions ? `
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 10px;">
                            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #d1ecf1, #bee5eb); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 0.95rem;">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0 0 0.5rem 0; color: #2c3e50; font-weight: 600;">Your Suggestions</h6>
                                <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">${appt.suggestions.replace(/\n/g, '<br>')}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div style="display: flex; gap: 1rem; padding: 1rem; background: white; border-radius: 10px;">
                            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #d6eaf8, #aed6f1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #2980b9; font-size: 0.95rem;">
                                <i class="far fa-clock"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0 0 0.5rem 0; color: #2c3e50; font-weight: 600;">Feedback Submitted</h6>
                                <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">${new Date(appt.submitted_at).toLocaleString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: 'numeric',
                                    minute: 'numeric',
                                    hour12: true
                                })}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Responses -->
                <div>
                    <h6 style="color: #2980b9; margin-bottom: 1rem; font-weight: 600; font-size: 1rem;">
                        <i class="fas fa-clipboard-list" style="font-size: 0.95rem;"></i> Detailed Responses
                    </h6>
                    ${data.responses.map(item => `
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 10px; margin-bottom: 0.75rem; border-left: 4px solid #2980b9;">
                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem; font-size: 0.9rem;">${item.question}</div>
                            <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.8rem; color: white; background: ${badgeColors[item.answer]};">
                                <i class="fas fa-check-circle" style="font-size: 0.75rem;"></i>
                                ${item.answer}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            return html;
        }

        $(document).ready(function() {
            var $highlightedAppointment = $('.to-highlight');
            
            if ($highlightedAppointment.length) {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: $highlightedAppointment.offset().top - 100
                    }, 500);
                    
                    $highlightedAppointment.addClass('highlight-appointment');
                    setTimeout(function() {
                        $highlightedAppointment.removeClass('highlight-appointment');
                    }, 2000);
                }, 300);
            }
        });
    </script>
</body>
</html>