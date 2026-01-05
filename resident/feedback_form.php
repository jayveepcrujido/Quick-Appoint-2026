<?php 
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';
$authId = $_SESSION['auth_id'];

// Get appointment ID from URL
$appointmentId = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Fetch appointment details
$stmt = $pdo->prepare("
    SELECT a.id, a.transaction_id, a.scheduled_for, d.name AS department_name, s.service_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN department_services s ON a.service_id = s.id
    WHERE a.id = ? AND a.status = 'Completed'
    LIMIT 1
");
$stmt->execute([$appointmentId]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Appointment not found or not completed.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cc1 = $_POST['cc1'] ?? '';
    $cc2 = $_POST['cc2'] ?? '';
    $cc3 = $_POST['cc3'] ?? '';
    $sqd0 = $_POST['sqd0'] ?? '';
    $sqd1 = $_POST['sqd1'] ?? '';
    $sqd2 = $_POST['sqd2'] ?? '';
    $sqd3 = $_POST['sqd3'] ?? '';
    $sqd4 = $_POST['sqd4'] ?? '';
    $sqd5 = $_POST['sqd5'] ?? '';
    $sqd6 = $_POST['sqd6'] ?? '';
    $sqd7 = $_POST['sqd7'] ?? '';
    $sqd8 = $_POST['sqd8'] ?? '';
    $suggestions = $_POST['suggestions'] ?? '';
    
    // Insert feedback into database
    $insertStmt = $pdo->prepare("
        INSERT INTO appointment_feedback 
        (appointment_id, sqd0_answer, sqd1_answer, sqd2_answer, sqd3_answer, sqd4_answer, sqd5_answer, sqd6_answer, sqd7_answer, sqd8_answer, cc1_answer, cc2_answer, cc3_answer, suggestions, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if ($insertStmt->execute([$appointmentId, $sqd0, $sqd1, $sqd2, $sqd3, $sqd4, $sqd5, $sqd6, $sqd7, $sqd8, $cc1, $cc2, $cc3, $suggestions])) {
        // Update the appointment to mark feedback as sent
        $updateStmt = $pdo->prepare("UPDATE appointments SET has_sent_feedback = 1 WHERE id = ?");
        $updateStmt->execute([$appointmentId]);
        
        // Set success flag for JavaScript alert
        $feedbackSuccess = true;
    } else {
        $error = "Failed to submit feedback. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"/>
        <style>
    :root {
        --primary-blue: #1e40af;
        --secondary-blue: #3b82f6;
        --light-blue: #dbeafe;
        --dark-blue: #1e3a8a;
        --accent-blue: #60a5fa;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --bg-light: #f8fafc;
    }

    body {
        background: linear-gradient(135deg, rgba(27, 45, 105, 0.85) 0%, rgba(13, 146, 244, 0.85), rgba(39, 84, 138, 0.85) 100%),
                    url('../assets/images/LGU_Unisan.jpg') center/cover fixed;
        min-height: 100vh;
        padding: 1.5rem 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .feedback-container {
        max-width: 850px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .feedback-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 15px 45px rgba(30, 64, 175, 0.25);
    }

    .logo-section {
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light-blue);
    }

    .logo-container {
        display: inline-block;
        background: var(--light-blue);
        padding: 1rem;
        border-radius: 16px;
        margin-bottom: 0.75rem;
        box-shadow: 0 3px 10px rgba(30, 64, 175, 0.1);
    }

    .logo-container img {
        max-width: 80px;
        height: auto;
        display: block;
    }

    .logo-placeholder {
        width: 80px;
        height: 80px;
        background: white;
        border: 2px dashed var(--secondary-blue);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-size: 0.75rem;
        text-align: center;
        padding: 0.75rem;
    }

    .lgu-name {
        font-size: 1.125rem;
        font-weight: 700;
        color: #27548A;
        margin: 0.375rem 0;
    }

    .feedback-header {
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
    }

    .feedback-header h2 {
        color: var(--text-dark);
        font-weight: 700;
        margin-bottom: 0.375rem;
        font-size: 1.375rem;
    }

    .feedback-header h2 i {
        color: var(--secondary-blue);
        margin-right: 0.375rem;
        font-size: 1.25rem;
    }

    .appointment-info {
        background: linear-gradient(135deg, #0D92F4, #27548A);
        padding: 1rem;
        border-radius: 12px;
        color: white;
        margin-bottom: 1.75rem;
        box-shadow: 0 6px 18px rgba(30, 64, 175, 0.2);
    }

    .appointment-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.875rem;
    }

    .appointment-info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .appointment-info-item i {
        font-size: 0.9375rem;
        min-width: 18px;
        text-align: center;
    }

    .cc-section {
        background: rgba(248, 250, 252, 0.9);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.75rem;
    }

    .cc-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border-color);
    }

    .cc-header i {
        font-size: 1.25rem;
        color: var(--secondary-blue);
    }

    .cc-header h4 {
        color: var(--text-dark);
        font-weight: 700;
        margin: 0;
        font-size: 1rem;
    }

    .cc-instructions, .sqd-instructions {
        background: white;
        padding: 0.875rem;
        border-radius: 10px;
        border-left: 3px solid var(--secondary-blue);
        margin-bottom: 1.25rem;
        color: var(--text-dark);
        line-height: 1.5;
        font-size: 0.8125rem;
    }

    .cc-question {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 6px rgba(30, 64, 175, 0.08);
    }

    .cc-question-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .cc-options {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .cc-options.two-columns {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }

    .cc-option {
        position: relative;
        transition: all 0.3s ease;
    }

    .cc-option.selected {
        transform: translateX(3px);
    }

    .cc-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .cc-option label {
        display: flex;
        align-items: center;
        padding: 0.75rem 0.875rem;
        background: var(--bg-light);
        border: 2px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 0;
        font-weight: 500;
        color: var(--text-dark);
        font-size: 0.8125rem;
    }

    .cc-option label:hover {
        background: var(--light-blue);
        border-color: var(--secondary-blue);
    }

    .cc-option input[type="radio"]:checked + label {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        border-color: var(--primary-blue);
        box-shadow: 0 3px 10px rgba(30, 64, 175, 0.3);
    }

    .cc-option label::before {
        content: '';
        width: 16px;
        height: 16px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        margin-right: 0.625rem;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .cc-option input[type="radio"]:checked + label::before {
        background: white;
        border-color: white;
        box-shadow: inset 0 0 0 3px var(--primary-blue);
    }

    .question-section {
        background: white;
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
        box-shadow: 0 3px 12px rgba(30, 64, 175, 0.08);
        border: 1px solid var(--border-color);
    }

    .question-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
        text-align: center;
    }

    .emoji-options {
        display: flex;
        justify-content: center;
        gap: 0.625rem;
        flex-wrap: wrap;
    }

    .emoji-option {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .emoji-option input[type="radio"] {
        display: none;
    }

    .emoji-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.375rem;
        padding: 0.75rem 0.5rem;
        border-radius: 10px;
        background: var(--bg-light);
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
        min-width: 65px;
    }

    .emoji-label:hover {
        background: var(--light-blue);
        border-color: var(--accent-blue);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.15);
    }

    .emoji-option input[type="radio"]:checked + .emoji-label {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        border-color: var(--primary-blue);
        transform: scale(1.05);
        box-shadow: 0 6px 18px rgba(30, 64, 175, 0.35);
    }

    .emoji-icon {
        font-size: 1.75rem;
        filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.1));
    }

    .emoji-text {
        font-size: 0.6875rem;
        font-weight: 600;
        text-align: center;
        line-height: 1.2;
    }

    .suggestions-section {
        background: rgba(248, 250, 252, 0.9);
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 2px solid var(--border-color);
    }

    .suggestions-header {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .suggestions-header::before {
        content: '💡';
        font-size: 1.125rem;
    }

    .suggestions-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.8125rem;
        resize: vertical;
        transition: all 0.3s ease;
        background: white;
    }

    .suggestions-textarea:focus {
        outline: none;
        border-color: var(--secondary-blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .submit-btn {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 0.875rem 2.5rem;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9375rem;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 6px 18px rgba(30, 64, 175, 0.3);
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(30, 64, 175, 0.4);
    }

    .submit-btn:active {
        transform: translateY(-1px);
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        color: white;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 1rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        font-size: 0.875rem;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        text-decoration: none;
        transform: translateX(-4px);
    }

    .alert {
        border-radius: 10px;
        margin-bottom: 1.25rem;
        border: none;
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
    }

    .language-toggle {
        position: fixed;
        top: 15px;
        right: 15px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 40px;
        padding: 6px 16px;
        box-shadow: 0 3px 10px rgba(30, 64, 175, 0.2);
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .language-toggle:hover {
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        transform: translateY(-2px);
    }

    .language-toggle i {
        color: var(--secondary-blue);
        font-size: 1rem;
    }

    .language-toggle .lang-text {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 0.8125rem;
    }

    @media (max-width: 992px) {
        .feedback-card {
            padding: 1.75rem;
        }

        .cc-options.two-columns {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        body {
            padding: 1rem 0;
        }

        .feedback-card {
            padding: 1.5rem;
            border-radius: 16px;
        }

        .logo-container img,
        .logo-placeholder {
            width: 70px;
            height: 70px;
        }

        .lgu-name {
            font-size: 1rem;
        }

        .feedback-header h2 {
            font-size: 1.25rem;
        }

        .feedback-header h2 i {
            font-size: 1.125rem;
        }

        .appointment-info {
            padding: 0.875rem;
        }

        .appointment-info-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.625rem;
        }

        .cc-section {
            padding: 1.25rem;
        }

        .cc-header h4 {
            font-size: 0.9375rem;
        }

        .cc-question {
            padding: 0.875rem;
        }

        .question-section {
            padding: 1rem;
        }

        .emoji-options {
            gap: 0.5rem;
        }

        .emoji-label {
            padding: 0.625rem 0.5rem;
            min-width: 60px;
        }

        .emoji-icon {
            font-size: 1.5rem;
        }

        .emoji-text {
            font-size: 0.625rem;
        }

        .suggestions-section {
            padding: 1rem;
        }

        .submit-btn {
            padding: 0.75rem 2rem;
            font-size: 0.875rem;
        }

        .language-toggle {
            top: 10px;
            right: 10px;
            padding: 5px 14px;
        }
    }

    @media (max-width: 480px) {
        .feedback-card {
            padding: 1.25rem;
        }

        .logo-container {
            padding: 0.75rem;
        }

        .emoji-label {
            min-width: 55px;
            padding: 0.5rem 0.375rem;
        }

        .emoji-icon {
            font-size: 1.375rem;
        }

        .cc-option label {
            padding: 0.625rem 0.75rem;
            font-size: 0.75rem;
        }
    }
    </style>
</head>
<body>
    <div class="feedback-container">
        <a href="#" class="back-btn" onclick="loadContent('residents_completed_appointments.php'); return false;">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
        <button class="language-toggle" onclick="toggleLanguage()">
            <i class="fas fa-language"></i>
            <span class="lang-text" id="langToggleText">Tagalog</span>
        </button>

        <div class="feedback-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-container">
                        <img src="../assets/images/Unisan_Logo.png" alt="LGU Logo">
                </div>
                <h3 class="lgu-name">LGU Unisan</h3>
                <p class="text-muted mb-0" data-translate="helpText" style="font-size: 0.9375rem;"><strong>HELP US SERVE YOU BETTER!</strong></p>
            </div>
            <div class="feedback-header">
                <h2 data-translate="feedbackTitle"><i class="fas fa-comment-dots"></i> Client Satisfaction Measurement (CSM)</h2>
                <p class="text-muted mb-0" data-translate="feedbackSubtitle" >Please share your experience with this appointment</p>
            </div>

            <div class="appointment-info">
                <div class="appointment-info-row">
                    <div class="appointment-info-item">
                        <i class="fas fa-hashtag"></i>
                        <strong><?= htmlspecialchars($appointment['transaction_id']) ?></strong>
                    </div>
                    <div class="appointment-info-item">
                        <i class="fas fa-building"></i>
                        <span><?= htmlspecialchars($appointment['department_name']) ?></span>
                    </div>
                    <div class="appointment-info-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span><?= htmlspecialchars($appointment['service_name']) ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            <form method="POST" id="feedbackForm">
            <!-- Citizen's Charter Section -->
            <div class="cc-section">
                <div class="cc-header">
                    <i class="fas fa-file-alt" style="font-siz  e: 1.5rem; color: #667eea;"></i>
                    <h4 data-translate="ccTitle">Citizen's Charter (CC) Questions</h4>
                </div>

                <div class="cc-instructions" data-translate="ccInstructions">
                    <strong>INSTRUCTIONS:</strong> Please place a <strong>Check mark (✓)</strong> in the designated box that corresponds to your answer on the Citizen's Charter (CC) questions. The Citizen's Charter is an official document that reflects the services of a government agency/office including its requirements, fees, and processing times among others.
                </div>

                <!-- CC1 -->
                <div class="cc-question">
                    <div class="cc-question-label" data-translate="cc1Question">
                        <strong>CC1.</strong> Which of the following best describes your awareness of a CC?
                    </div>
                    <div class="cc-options">
                        <div class="cc-option">
                            <input type="radio" name="cc1" id="cc1_opt1" value="I know what a CC is and I saw this office's CC" required>
                            <label for="cc1_opt1" data-translate="cc1Opt1">1. I know what a CC is and I saw this office's CC.</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc1" id="cc1_opt2" value="I know what a CC is but I did NOT see this office's CC">
                            <label for="cc1_opt2" data-translate="cc1Opt2">2. I know what a CC is but I did NOT see this office's CC.</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc1" id="cc1_opt3" value="I learned of the CC only when I saw this office's CC">
                            <label for="cc1_opt3" data-translate="cc1Opt3">3. I learned of the CC only when I saw this office's CC.</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc1" id="cc1_opt4" value="I do not know what a CC is and I did not see one in this office">
                            <label for="cc1_opt4" data-translate="cc1Opt4">4. I do not know what a CC is and I did not see one in this office. (Answer 'N/A' on CC2 and CC3)</label>
                        </div>
                    </div>
                </div>

                <!-- CC2 -->
                <div class="cc-question">
                    <div class="cc-question-label" data-translate="cc2Question">
                        <strong>CC2.</strong> If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...?
                    </div>
                    <div class="cc-options two-columns">
                        <div class="cc-option">
                            <input type="radio" name="cc2" id="cc2_opt1" value="Easy to see">
                            <label for="cc2_opt1" data-translate="cc2Opt1">1. Easy to see</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc2" id="cc2_opt2" value="Somewhat easy to see">
                            <label for="cc2_opt2" data-translate="cc2Opt2">2. Somewhat easy to see</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc2" id="cc2_opt3" value="Difficult to see">
                            <label for="cc2_opt3" data-translate="cc2Opt3">3. Difficult to see</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc2" id="cc2_opt4" value="Not visible at all">
                            <label for="cc2_opt4" data-translate="cc2Opt4">4. Not visible at all</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc2" id="cc2_opt5" value="N/A">
                            <label for="cc2_opt5" data-translate="cc2Opt5">5. N/A</label>
                        </div>
                        
                    </div>
                </div>

                <!-- CC3 -->
                <div class="cc-question">
                    <div class="cc-question-label" data-translate="cc3Question">
                        <strong>CC3.</strong> If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?
                    </div>
                    <div class="cc-options two-columns">
                        <div class="cc-option">
                            <input type="radio" name="cc3" id="cc3_opt1" value="Helped very much">
                            <label for="cc3_opt1"   data-translate="cc3Opt1">1. Helped very much</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc3" id="cc3_opt2" value="Somewhat helped">
                            <label for="cc3_opt2" data-translate="cc3Opt2">2. Somewhat helped</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc3" id="cc3_opt3" value="Did not help">
                            <label for="cc3_opt3" data-translate="cc3Opt3">3. Did not help</label>
                        </div>
                        <div class="cc-option">
                            <input type="radio" name="cc3" id="cc3_opt4" value="N/A">
                            <label for="cc3_opt4" data-translate="cc3Opt4">4. N/A</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sqd-instructions" data-translate="sqdInstructions">
                <strong>INSTRUCTIONS:</strong> For SQD 0-8, please <strong>Click </strong>an option that best corresponds to your answer.
            </div>

                <!-- Question 1 (SQD0) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd0">
                        <strong>SQD0.</strong> I am satisfied with the service that I availed.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd0" id="sqd0_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd0_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd0" id="sqd0_disagree" value="Disagree">
                            <label for="sqd0_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd0" id="sqd0_neither" value="Neither Agree nor Disagree">
                            <label for="sqd0_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd0" id="sqd0_agree" value="Agree">
                            <label for="sqd0_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd0" id="sqd0_strongly_agree" value="Strongly Agree">
                            <label for="sqd0_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 2 (SQD1) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd1">
                        <strong>SQD1.</strong> I spent a reasonable amount of time for my transaction.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd1" id="sqd1_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd1_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd1" id="sqd1_disagree" value="Disagree">
                            <label for="sqd1_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd1" id="sqd1_neither" value="Neither Agree nor Disagree">
                            <label for="sqd1_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd1" id="sqd1_agree" value="Agree">
                            <label for="sqd1_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd1" id="sqd1_strongly_agree" value="Strongly Agree">
                            <label for="sqd1_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 3 (SQD2) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd2">
                        <strong>SQD2.</strong> The office followed the transaction's requirements and steps based on the information provided.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd2" id="sqd2_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd2_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd2" id="sqd2_disagree" value="Disagree">
                            <label for="sqd2_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd2" id="sqd2_neither" value="Neither Agree nor Disagree">
                            <label for="sqd2_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd2" id="sqd2_agree" value="Agree">
                            <label for="sqd2_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd2" id="sqd2_strongly_agree" value="Strongly Agree">
                            <label for="sqd2_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 4 (SQD3) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd3">
                        <strong>SQD3.</strong> The steps (including payment) I needed to do for my transaction were easy and simple.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd3" id="sqd3_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd3_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd3" id="sqd3_disagree" value="Disagree">
                            <label for="sqd3_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd3" id="sqd3_neither" value="Neither Agree nor Disagree">
                            <label for="sqd3_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd3" id="sqd3_agree" value="Agree">
                            <label for="sqd3_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd3" id="sqd3_strongly_agree" value="Strongly Agree">
                            <label for="sqd3_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 5 (SQD4) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd4">
                        <strong>SQD4.</strong> I easily found information about my transaction from the office's website.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd4" id="sqd4_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd4_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd4" id="sqd4_disagree" value="Disagree">
                            <label for="sqd4_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd4" id="sqd4_neither" value="Neither Agree nor Disagree">
                            <label for="sqd4_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd4" id="sqd4_agree" value="Agree">
                            <label for="sqd4_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd4" id="sqd4_strongly_agree" value="Strongly Agree">
                            <label for="sqd4_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 6 (SQD5) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd5">
                        <strong>SQD5.</strong> I paid a reasonable amount of fees for my transaction. (If service was free, mark the 'N/A' column)
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd5_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_disagree" value="Disagree">
                            <label for="sqd5_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_neither" value="Neither Agree nor Disagree">
                            <label for="sqd5_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_agree" value="Agree">
                            <label for="sqd5_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_strongly_agree" value="Strongly Agree">
                            <label for="sqd5_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd5" id="sqd5_na" value="N/A">
                            <label for="sqd5_na" class="emoji-label">
                                <span class="emoji-icon">➖</span>
                                <span class="emoji-text">N/A</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 7 (SQD6) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd6">
                        <strong>SQD6.</strong> I am confident my online transaction was secure.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd6" id="sqd6_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd6_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd6" id="sqd6_disagree" value="Disagree">
                            <label for="sqd6_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd6" id="sqd6_neither" value="Neither Agree nor Disagree">
                            <label for="sqd6_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd6" id="sqd6_agree" value="Agree">
                            <label for="sqd6_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd6" id="sqd6_strongly_agree" value="Strongly Agree">
                            <label for="sqd6_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 8 (SQD7) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd7">
                        <strong>SQD7.</strong> The office's online support was available, and (if asked questions) online support's response was quick.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd7" id="sqd7_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd7_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd7" id="sqd7_disagree" value="Disagree">
                            <label for="sqd7_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd7" id="sqd7_neither" value="Neither Agree nor Disagree">
                            <label for="sqd7_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd7" id="sqd7_agree" value="Agree">
                            <label for="sqd7_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd7" id="sqd7_strongly_agree" value="Strongly Agree">
                            <label for="sqd7_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question 9 (SQD8) -->
                <div class="question-section">
                    <div class="question-label" data-translate="sqd8">
                        <strong>SQD8.</strong> I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.
                    </div>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="sqd8" id="sqd8_strongly_disagree" value="Strongly Disagree" required>
                            <label for="sqd8_strongly_disagree" class="emoji-label">
                                <span class="emoji-icon">☹️</span>
                                <span class="emoji-text" data-translate="stronglyDisagree">Strongly<br>Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd8" id="sqd8_disagree" value="Disagree">
                            <label for="sqd8_disagree" class="emoji-label">
                                <span class="emoji-icon">🙁</span>
                                <span class="emoji-text" data-translate="disagree">Disagree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd8" id="sqd8_neither" value="Neither Agree nor Disagree">
                            <label for="sqd8_neither" class="emoji-label">
                                <span class="emoji-icon">😐</span>
                                <span class="emoji-text" data-translate="neither">Neither</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd8" id="sqd8_agree" value="Agree">
                            <label for="sqd8_agree" class="emoji-label">
                                <span class="emoji-icon">🙂</span>
                                <span class="emoji-text" data-translate="agree">Agree</span>
                            </label>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="sqd8" id="sqd8_strongly_agree" value="Strongly Agree">
                            <label for="sqd8_strongly_agree" class="emoji-label">
                                <span class="emoji-icon">😊</span>
                                <span class="emoji-text" data-translate="stronglyAgree">Strongly<br>Agree</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Suggestions Section -->
                <div class="suggestions-section">
                    <div class="suggestions-header" data-translate="suggestionsHeader">
                        Suggestions on how we can further improve our services (optional):
                    </div>
                    <textarea 
                        name="suggestions" 
                        class="suggestions-textarea" 
                        data-translate="suggestionsPlaceholder"
                        placeholder="Please share your suggestions here..."
                        rows="4"
                    ></textarea>
                </div>

                <button type="submit" class="submit-btn" data-translate="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>
    </div>
    <?php if (isset($feedbackSuccess) && $feedbackSuccess): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            title: 'Success!',
            text: 'Your feedback has been successfully recorded. Thank you!',
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#1e40af'
        }).then((result) => {
            if (result.isConfirmed) {
                // Check if loadContent function exists (for dynamic loading)
                if (typeof loadContent === 'function') {
                    loadContent('residents_completed_appointments.php');
                } else {
                    // Fallback to regular redirect if loadContent doesn't exist
                    window.location.href = 'residents_dashboard.php';
                }
            }
        });
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const questions = ['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8', 'cc1'];
            let allAnswered = true;
            
            questions.forEach(q => {
                const answered = document.querySelector(`input[name="${q}"]:checked`);
                if (!answered) {
                    allAnswered = false;
                }
            });
            
            // Check CC2 and CC3 based on CC1 answer
            const cc1Answer = document.querySelector('input[name="cc1"]:checked');
            if (cc1Answer && cc1Answer.value !== "I do not know what a CC is and I did not see one in this office") {
                const cc2Answered = document.querySelector('input[name="cc2"]:checked');
                const cc3Answered = document.querySelector('input[name="cc3"]:checked');
                
                if (!cc2Answered || !cc3Answered) {
                    allAnswered = false;
                }
            }
            
            if (!allAnswered) {
                e.preventDefault();
                alert('Please answer all required questions before submitting.');
            }
        });

        // Add visual feedback for selected CC options
        document.querySelectorAll('.cc-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove selected class from all options in this question
                const questionDiv = this.closest('.cc-question');
                questionDiv.querySelectorAll('.cc-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                // Add selected class to chosen option
                this.closest('.cc-option').classList.add('selected');
            });
        });

        //Alternative: Use browser's back button
        document.querySelector('.back-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.history.back();
        });
        
    let currentLang = 'en';

    const translations = {
        en: {
            langToggleText: 'Tagalog',
            helpText: 'HELP US SERVE YOU BETTER!',
            feedbackTitle: 'Client Satisfaction Measurement (CSM)',
            feedbackSubtitle: 'Please share your experience with this appointment',
            backBtn: 'Back',
            
            // CC Section
            ccTitle: "Citizen's Charter (CC) Questions",
            ccInstructions: "INSTRUCTIONS: Please place a <strong>Check mark (✓)</strong> in the designated box that corresponds to your answer on the Citizen's Charter (CC) questions. The Citizen's Charter is an official document that reflects the services of a government agency/office including its requirements, fees, and processing times among others.",
            
            cc1Question: "CC1. Which of the following best describes your awareness of a CC?",
            cc1Opt1: "1. I know what a CC is and I saw this office's CC.",
            cc1Opt2: "2. I know what a CC is but I did NOT see this office's CC.",
            cc1Opt3: "3. I learned of the CC only when I saw this office's CC.",
            cc1Opt4: "4. I do not know what a CC is and I did not see one in this office. (Answer 'N/A' on CC2 and CC3)",
            
            cc2Question: "CC2. If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...?",
            cc2Opt1: "1. Easy to see",
            cc2Opt2: "2. Somewhat easy to see",
            cc2Opt3: "3. Difficult to see",
            cc2Opt4: "4. Not visible at all",
            cc2Opt5: "5. N/A",
            
            cc3Question: "CC3. If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?",
            cc3Opt1: "1. Helped very much",
            cc3Opt2: "2. Somewhat helped",
            cc3Opt3: "3. Did not help",
            cc3Opt4: "4. N/A",

            sqdInstructions: "INSTRUCTIONS: For SQD 0-8, please <strong>Click </strong>an option that best corresponds to your answer.",
            
            // SQD Questions
            sqd0: "SQD0. I am satisfied with the service that I availed.",
            sqd1: "SQD1. I spent a reasonable amount of time for my transaction.",
            sqd2: "SQD2. The office followed the transaction's requirements and steps based on the information provided.",
            sqd3: "SQD3. The steps (including payment) I needed to do for my transaction were easy and simple.",
            sqd4: "SQD4. I easily found information about my transaction from the office's website.",
            sqd5: "SQD5. I paid a reasonable amount of fees for my transaction. (If service was free, mark the 'N/A' column)",
            sqd6: "SQD6. I am confident my online transaction was secure.",
            sqd7: "SQD7. The office's online support was available, and (if asked questions) online support's response was quick.",
            sqd8: "SQD8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.",
            
            // Rating options
            stronglyDisagree: "Strongly\nDisagree",
            disagree: "Disagree",
            neither: "Neither",
            agree: "Agree",
            stronglyAgree: "Strongly\nAgree",
            
            // Suggestions
            suggestionsHeader: "Suggestions on how we can further improve our services (optional):",
            suggestionsPlaceholder: "Please share your suggestions here...",
            submitBtn: "Submit Feedback"
        },
        tl: {
            langToggleText: 'English',
            helpText: 'TULUNGAN KAMING MAGLINGKOD NG MAS MAHUSAY!',
            feedbackTitle: 'Pagsukat ng Kasiyahan ng Kliyente (CSM)',
            feedbackSubtitle: 'Pakibahagi ang inyong karanasan sa appointment na ito',
            backBtn: 'Bumalik',
            
            // CC Section
            ccTitle: "Mga Tanong sa Citizen's Charter (CC)",
            ccInstructions: "PANUTO: Mangyaring maglagay ng <strong>Tsek (✓)</strong> sa kahon na tumutugma sa inyong sagot sa mga tanong tungkol sa Citizen's Charter (CC). Ang Citizen's Charter ay opisyal na dokumento na naglalarawan ng mga serbisyo ng isang ahensya/tanggapan ng gobyerno kasama ang mga kinakailangan, bayad, at oras ng pagproseso.",
            
            cc1Question: "CC1. Alin sa mga sumusunod ang pinakamahusay na naglalarawan ng inyong kaalaman tungkol sa CC?",
            cc1Opt1: "1. Alam ko kung ano ang CC at nakita ko ang CC ng tanggapang ito.",
            cc1Opt2: "2. Alam ko kung ano ang CC ngunit HINDI ko nakita ang CC ng tanggapang ito.",
            cc1Opt3: "3. Nalaman ko lang ang tungkol sa CC nang makita ko ang CC ng tanggapang ito.",
            cc1Opt4: "4. Hindi ko alam kung ano ang CC at hindi ako nakakita nito sa tanggapang ito.(Sagutin ng 'N/A' ang CC2 at CC3)",
            
            cc2Question: "CC2. Kung may kaalaman tungkol sa CC (sumagot ng 1-3 sa CC1), masasabi ba ninyo na ang CC ng tanggapang ito ay ...?",
            cc2Opt1: "1. Madaling makita",
            cc2Opt2: "2. Medyo madaling makita",
            cc2Opt3: "3. Mahirap makita",
            cc2Opt4: "4. Hindi makita",
            cc2Opt5: "5. N/A",
            
            cc3Question: "CC3. Kung may kaalaman tungkol sa CC (sumagot ng 1-3 sa CC1), gaano kalaki ang naitulong ng CC sa inyong transaksyon?",
            cc3Opt1: "1. Nakatulong nang husto",
            cc3Opt2: "2. Medyo nakatulong",
            cc3Opt3: "3. Hindi nakatulong",
            cc3Opt4: "4. N/A",

            sqdInstructions: "PANUTO: Para sa SQD 0-8, mangyaring <strong>I-Click </strong>ang opsyon na pinakaangkop sa inyong sagot.",
            
            // SQD Questions
            sqd0: "SQD0. Nasiyahan ako sa serbisyong aking kinuha.",
            sqd1: "SQD1. Gumugol ako ng katamtamang oras para sa aking transaksyon.",
            sqd2: "SQD2. Sinunod ng tanggapan ang mga kinakailangan at hakbang ng transaksyon batay sa ibinigay na impormasyon.",
            sqd3: "SQD3. Ang mga hakbang (kasama ang pagbabayad) na kailangan kong gawin para sa aking transaksyon ay madali at simple.",
            sqd4: "SQD4. Madali kong nahanap ang impormasyon tungkol sa aking transaksyon mula sa website ng tanggapan.",
            sqd5: "SQD5. Nagbayad ako ng katamtamang halaga ng bayad para sa aking transaksyon. (Kung libre ang serbisyo, markahan ang 'N/A')",
            sqd6: "SQD6. Nagtitiwala ako na ligtas ang aking online na transaksyon.",
            sqd7: "SQD7. Available ang online support ng tanggapan, at (kung nagtanong) mabilis ang tugon ng online support.",
            sqd8: "SQD8. Nakuha ko ang kailangan ko mula sa tanggapan ng gobyerno, o (kung tinanggihan) sapat na ipinaliwanag sa akin ang dahilan ng pagtanggi.",
            
            // Rating options
            stronglyDisagree: "Lubhang\nHindi Sumasang-ayon",
            disagree: "Hindi\nSumasang-ayon",
            neither: "Hindi\nTiyak",
            agree: "Sumasang-ayon",
            stronglyAgree: "Lubhang\nSumasang-ayon",
            
            // Suggestions
            suggestionsHeader: "Mga mungkahi kung paano pa namin mapapabuti ang aming mga serbisyo (opsyonal):",
            suggestionsPlaceholder: "Pakibahagi ang inyong mga mungkahi dito...",
            submitBtn: "Isumite ang Feedback"
        }
    };

    function toggleLanguage() {
        currentLang = currentLang === 'en' ? 'tl' : 'en';
        updateContent();
    }

    function updateContent() {
        const t = translations[currentLang];
        
        // Update toggle button
        document.getElementById('langToggleText').textContent = t.langToggleText;
        
        // Update main content with safe checking
        const helpTextEl = document.querySelector('[data-translate="helpText"]');
        if (helpTextEl) helpTextEl.innerHTML = `<strong>${t.helpText}</strong>`;
        
        const feedbackTitleEl = document.querySelector('[data-translate="feedbackTitle"]');
        if (feedbackTitleEl) feedbackTitleEl.innerHTML = `<i class="fas fa-comment-dots"></i> ${t.feedbackTitle}`;
        
        const feedbackSubtitleEl = document.querySelector('[data-translate="feedbackSubtitle"]');
        if (feedbackSubtitleEl) feedbackSubtitleEl.textContent = t.feedbackSubtitle;
        
        const backBtnEl = document.querySelector('[data-translate="backBtn"]');
        if (backBtnEl) backBtnEl.innerHTML = `<i class="fas fa-arrow-left"></i> ${t.backBtn}`;
        
        // Update CC Section
        const ccTitleEl = document.querySelector('[data-translate="ccTitle"]');
        if (ccTitleEl) ccTitleEl.textContent = t.ccTitle;
        
        const ccInstructionsEl = document.querySelector('[data-translate="ccInstructions"]');
        if (ccInstructionsEl) {
            const instructionText = currentLang === 'en' ? 
                t.ccInstructions : 
                t.ccInstructions;
            ccInstructionsEl.innerHTML = `<strong>${currentLang === 'en' ? 'INSTRUCTIONS' : 'PANUTO'}:</strong> ${instructionText.replace('INSTRUCTIONS: ', '').replace('PANUTO: ', '')}`;
        }

        const sqdInstructionsEl = document.querySelector('[data-translate="sqdInstructions"]');
        if (ccInstructionsEl) {
            const instructionText = currentLang === 'en' ? 
                t.sqdInstructions : 
                t.sqdInstructions;
            sqdInstructionsEl.innerHTML = `<strong>${currentLang === 'en' ? 'INSTRUCTIONS' : 'PANUTO'}:</strong> ${instructionText.replace('INSTRUCTIONS: ', '').replace('PANUTO: ', '')}`;
        }
        
        // Update CC Questions
        const cc1QuestionEl = document.querySelector('[data-translate="cc1Question"]');
        if (cc1QuestionEl) cc1QuestionEl.textContent = t.cc1Question;
        
        const cc2QuestionEl = document.querySelector('[data-translate="cc2Question"]');
        if (cc2QuestionEl) cc2QuestionEl.textContent = t.cc2Question;
        
        const cc3QuestionEl = document.querySelector('[data-translate="cc3Question"]');
        if (cc3QuestionEl) cc3QuestionEl.textContent = t.cc3Question;
        
        // Update CC Options
        for (let i = 1; i <= 4; i++) {
            const cc1OptEl = document.querySelector(`[data-translate="cc1Opt${i}"]`);
            if (cc1OptEl) cc1OptEl.textContent = t[`cc1Opt${i}`];
        }
        
        for (let i = 1; i <= 5; i++) {
            const cc2OptEl = document.querySelector(`[data-translate="cc2Opt${i}"]`);
            if (cc2OptEl) cc2OptEl.textContent = t[`cc2Opt${i}`];
        }
        
        for (let i = 1; i <= 4; i++) {
            const cc3OptEl = document.querySelector(`[data-translate="cc3Opt${i}"]`);
            if (cc3OptEl) cc3OptEl.textContent = t[`cc3Opt${i}`];
        }
        
        // Update SQD Questions
        for (let i = 0; i <= 8; i++) {
            const sqdQuestionEl = document.querySelector(`[data-translate="sqd${i}"]`);
            if (sqdQuestionEl) sqdQuestionEl.textContent = t[`sqd${i}`];
        }
        
        // Update emoji labels for all questions
        const emojiLabels = {
            'strongly_disagree': 'stronglyDisagree',
            'disagree': 'disagree',
            'neither': 'neither',
            'agree': 'agree',
            'strongly_agree': 'stronglyAgree'
        };
        
        Object.keys(emojiLabels).forEach(key => {
            const elements = document.querySelectorAll(`[data-translate="${emojiLabels[key]}"]`);
            elements.forEach(el => {
                el.innerHTML = t[emojiLabels[key]].replace(/\n/g, '<br>');
            });
        });
        
        // Update suggestions section
        const suggestionsHeaderEl = document.querySelector('[data-translate="suggestionsHeader"]');
        if (suggestionsHeaderEl) suggestionsHeaderEl.textContent = t.suggestionsHeader;
        
        const suggestionsTextareaEl = document.querySelector('[data-translate="suggestionsPlaceholder"]');
        if (suggestionsTextareaEl) suggestionsTextareaEl.placeholder = t.suggestionsPlaceholder;
        
        // Update submit button
        const submitBtnEl = document.querySelector('[data-translate="submitBtn"]');
        if (submitBtnEl) submitBtnEl.innerHTML = `<i class="fas fa-paper-plane"></i> ${t.submitBtn}`;
    }
    </script>
</body>
</html>