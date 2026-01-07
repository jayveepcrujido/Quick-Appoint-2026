    <?php 
    session_start();
    if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
        header("Location: ../login.php");
        exit();
    }

    include '../conn.php';
    $authId = $_SESSION['auth_id'];

    $stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$authId]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        die("Personnel information not found.");
    }

    $departmentId = $personnel['department_id'];

    $startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

    $whereClause = "WHERE a.department_id = ?";
    $params = [$departmentId];

    if ($startDate && $endDate) {
        $whereClause .= " AND DATE(af.submitted_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    } elseif ($startDate) {
        $whereClause .= " AND DATE(af.submitted_at) >= ?";
        $params[] = $startDate;
    } elseif ($endDate) {
        $whereClause .= " AND DATE(af.submitted_at) <= ?";
        $params[] = $endDate;
    }

    $feedbackStmt = $pdo->prepare("
        SELECT 
            af.id,
            af.appointment_id,
            af.sqd0_answer,
            af.sqd1_answer,
            af.sqd2_answer,
            af.sqd3_answer,
            af.sqd4_answer,
            af.sqd5_answer,
            af.sqd6_answer,
            af.sqd7_answer,
            af.sqd8_answer,
            af.cc1_answer,
            af.cc2_answer,
            af.cc3_answer,
            af.suggestions,
            af.submitted_at,
            a.transaction_id,
            a.scheduled_for,
            r.first_name,
            r.last_name,
            ds.service_name
        FROM appointment_feedback af
        JOIN appointments a ON af.appointment_id = a.id
        JOIN residents r ON a.resident_id = r.id
        JOIN department_services ds ON a.service_id = ds.id
        $whereClause
        ORDER BY af.submitted_at DESC
    ");
    $feedbackStmt->execute($params);
    $feedbacks = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalFeedbacks = count($feedbacks);
    $satisfactionScores = [];
    $averageScores = [];

    foreach ($feedbacks as $feedback) {
        for ($i = 0; $i <= 8; $i++) {
            $answer = $feedback["sqd{$i}_answer"];
            if ($answer && $answer !== 'N/A') {
                $score = 0;
                switch ($answer) {
                    case 'Strongly Agree': $score = 5; break;
                    case 'Agree': $score = 4; break;
                    case 'Neither Agree nor Disagree': $score = 3; break;
                    case 'Disagree': $score = 2; break;
                    case 'Strongly Disagree': $score = 1; break;
                }
                if (!isset($averageScores["sqd{$i}"])) {
                    $averageScores["sqd{$i}"] = ['total' => 0, 'count' => 0];
                }
                $averageScores["sqd{$i}"]['total'] += $score;
                $averageScores["sqd{$i}"]['count']++;
            }
        }
    }

    $overallSatisfaction = 0;
    if (isset($averageScores['sqd0']) && $averageScores['sqd0']['count'] > 0) {
        $overallSatisfaction = round(($averageScores['sqd0']['total'] / $averageScores['sqd0']['count']) * 20, 1);
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Feedbacks</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"/>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css"/>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <style>
    :root {
        --primary-blue: #0D92F4;
        --secondary-blue: #27548A;
        --light-blue: #E8F4FD;
        --accent-blue: #60a5fa;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --bg-light: #f8fafc;
        --success-green: #10b981;
        --warning-yellow: #f59e0b;
        --danger-red: #ef4444;
        --white: #ffffff;
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 1rem 0;
    }

    .container {
        max-width: 1400px;
        padding: 0 1rem;
    }

    .page-header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(13, 146, 244, 0.3);
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .page-header h2 {
        color: var(--white);
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 1;
    }

    .page-header h2 i {
        font-size: 1.75rem;
        animation: bounceIcon 2s infinite;
    }

    @keyframes bounceIcon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        border-radius: 20px;
        padding: 1.75rem;
        box-shadow: 0 4px 15px rgba(13, 146, 244, 0.15);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 2px solid transparent;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-blue), var(--secondary-blue));
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(13, 146, 244, 0.25);
        border-color: var(--primary-blue);
    }

    .stat-card-header {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        transition: transform 0.3s ease;
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .stat-icon.primary {
        background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
        color: var(--primary-blue);
    }

    .stat-icon.success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-green);
    }

    .stat-value {
        font-size: 2.25rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .content-card {
        background: var(--white);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(13, 146, 244, 0.15);
        margin-bottom: 1.5rem;
        border: 1px solid rgba(13, 146, 244, 0.1);
    }

    .filter-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light-blue);
    }

    .filter-header i {
        font-size: 1.5rem;
        color: var(--primary-blue);
    }

    .filter-header h5 {
        margin: 0;
        color: var(--text-dark);
        font-weight: 700;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .form-label i {
        color: var(--primary-blue);
    }

    .form-control {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        font-size: 0.9375rem;
    }

    .form-control:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(13, 146, 244, 0.1);
        outline: none;
    }

    .form-control:hover {
        border-color: var(--accent-blue);
    }

    .btn {
        border-radius: 12px;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn i {
        font-size: 1rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border: none;
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(13, 146, 244, 0.4);
        color: var(--white);
    }

    .btn-secondary {
        background: #6c757d;
        border: none;
        color: var(--white);
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(108, 117, 125, 0.3);
        color: var(--white);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-green), #059669);
        border: none;
        color: var(--white);
    }

    .btn-success:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        color: var(--white);
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-header h4 {
        margin: 0;
        color: var(--text-dark);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .table-header h4 i {
        color: var(--primary-blue);
        font-size: 1.5rem;
    }

    .table-header small {
        display: block;
        margin-top: 0.25rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.1);
    }

    table.dataTable {
        border-collapse: separate;
        border-spacing: 0;
        width: 100% !important;
    }

    table.dataTable thead th {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        font-weight: 700;
        padding: 1.25rem 1rem;
        border: none;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8125rem;
    }

    table.dataTable thead th:first-child {
        border-top-left-radius: 16px;
    }

    table.dataTable thead th:last-child {
        border-top-right-radius: 16px;
    }

    table.dataTable tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-dark);
        font-size: 0.9375rem;
    }

    table.dataTable tbody tr {
        transition: all 0.2s ease;
    }

    table.dataTable tbody tr:hover {
        background: linear-gradient(to right, var(--light-blue), var(--white));
        transform: scale(1.01);
    }

    table.dataTable tbody tr:last-child td:first-child {
        border-bottom-left-radius: 16px;
    }

    table.dataTable tbody tr:last-child td:last-child {
        border-bottom-right-radius: 16px;
    }

    .btn-view {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        border: none;
        padding: 0.625rem 1.25rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .btn-view:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(13, 146, 244, 0.4);
        color: var(--white);
    }

    .rating-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.625rem 1rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .rating-badge i {
        font-size: 1rem;
    }

    .rating-badge.excellent {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-green);
    }

    .rating-badge.good {
        background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
        color: var(--primary-blue);
    }

    .rating-badge.fair {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: var(--warning-yellow);
    }

    .rating-badge.poor {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-red);
    }

    .modal-content {
        border-radius: 20px;
        border: none;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: var(--white);
        border: none;
        padding: 1.5rem 2rem;
    }

    .modal-header .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .modal-header .close {
        color: var(--white);
        opacity: 1;
        text-shadow: none;
        transition: transform 0.3s ease;
    }

    .modal-header .close:hover {
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 2rem;
        max-height: 70vh;
        overflow-y: auto;
    }

    .feedback-section {
        background: var(--bg-light);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 2px solid var(--light-blue);
    }

    .feedback-section h5 {
        color: var(--primary-blue);
        font-weight: 700;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.125rem;
    }

    .feedback-section h5 i {
        font-size: 1.25rem;
    }

    .feedback-item {
        background: var(--white);
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary-blue);
        box-shadow: 0 2px 8px rgba(13, 146, 244, 0.08);
        transition: all 0.3s ease;
    }

    .feedback-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.15);
    }

    .feedback-item:last-child {
        margin-bottom: 0;
    }

    .feedback-label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .feedback-answer {
        color: var(--text-muted);
        font-size: 1rem;
        line-height: 1.6;
    }

    .feedback-answer strong {
        color: var(--primary-blue);
    }

    .feedback-answer.rating {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9375rem;
    }

    .feedback-answer.rating.excellent {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-green);
    }

    .feedback-answer.rating.good {
        background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
        color: var(--primary-blue);
    }

    .feedback-answer.rating.fair {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: var(--warning-yellow);
    }

    .feedback-answer.rating.poor {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-red);
    }

    .suggestions-box {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 12px;
        border: 2px dashed var(--primary-blue);
        color: var(--text-dark);
        line-height: 1.8;
        min-height: 100px;
        font-size: 1rem;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 5rem;
        color: var(--light-blue);
        margin-bottom: 1.5rem;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }

    .empty-state h4 {
        color: var(--text-dark);
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .empty-state p {
        font-size: 1.0625rem;
        max-width: 500px;
        margin: 0 auto;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 1.5rem;
    }

    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        font-weight: 600;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .dataTables_wrapper .dataTables_filter input {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.625rem 1rem;
        transition: all 0.3s ease;
        margin-left: 0.5rem;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(13, 146, 244, 0.1);
    }

    .dataTables_wrapper .dataTables_length select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.1rem 2.5rem 0.5rem 1rem;
        margin: 0 0.5rem;
        transition: all 0.3s ease;
    }

    .dataTables_wrapper .dataTables_length select:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(13, 146, 244, 0.1);
    }

    .dataTables_wrapper .dataTables_info {
        color: var(--text-muted);
        font-weight: 600;
        padding-top: 1rem;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.5rem 1rem;
        margin: 0 0.25rem;
        border-radius: 8px;
        border: 2px solid var(--border-color);
        background: var(--white);
        color: var(--text-dark) !important;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--light-blue) !important;
        border-color: var(--primary-blue) !important;
        color: var(--primary-blue) !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)) !important;
        border-color: var(--primary-blue) !important;
        color: var(--white) !important;
    }

    @media (max-width: 992px) {
        .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .table-header .btn {
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        body {
            padding: 0.5rem 0;
        }

        .container {
            padding: 0 0.75rem;
        }

        .page-header {
            padding: 1.5rem;
            border-radius: 16px;
        }

        .page-header h2 {
            font-size: 1.5rem;
        }

        .page-header h2 i {
            font-size: 1.5rem;
        }

        .stats-container {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
        }

        .content-card {
            padding: 1.25rem;
            border-radius: 16px;
        }

        .filter-header h5 {
            font-size: 1rem;
        }

        .form-control {
            font-size: 0.875rem;
            padding: 0.625rem 0.875rem;
        }

        .btn {
            padding: 0.625rem 1rem;
            font-size: 0.8125rem;
        }

        .table-header h4 {
            font-size: 1.125rem;
        }

        table.dataTable thead th {
            padding: 1rem 0.75rem;
            font-size: 0.75rem;
        }

        table.dataTable tbody td {
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
        }

        .btn-view {
            padding: 0.5rem 0.875rem;
            font-size: 0.8125rem;
        }

        .rating-badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
        }

        .modal-body {
            padding: 1.25rem;
        }

        .feedback-section {
            padding: 1rem;
        }

        .feedback-section h5 {
            font-size: 1rem;
        }

        .feedback-item {
            padding: 1rem;
        }

        .empty-state {
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 3.5rem;
        }
    }

    @media (max-width: 576px) {
        .page-header h2 {
            font-size: 1.25rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .stat-card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .table-wrapper {
            border-radius: 12px;
        }

        table.dataTable {
            font-size: 0.8125rem;
        }

        .btn-view {
            width: 100%;
            justify-content: center;
        }
    }

    html {
        scroll-behavior: smooth;
    }

    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-track {
        background: var(--bg-light);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--secondary-blue);
    }
    .question-analysis-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--primary-blue);
        box-shadow: 0 2px 8px rgba(13, 146, 244, 0.08);
    }

    .question-analysis-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light-blue);
        gap: 1rem;
    }

    .analysis-question-title {
        flex: 1;
        font-weight: 700;
        color: var(--text-dark);
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .analysis-score-badge {
        text-align: right;
        min-width: 100px;
        flex-shrink: 0;
    }

    .score-display {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 15px;
        font-weight: 700;
        font-size: 1.1rem;
        white-space: nowrap;
    }

    .score-display.excellent {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-green);
    }

    .score-display.good {
        background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
        color: var(--primary-blue);
    }

    .score-display.fair {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: var(--warning-yellow);
    }

    .score-display.poor {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-red);
    }

    .response-breakdown {
        margin-top: 1rem;
    }

    .response-row {
        margin-bottom: 1rem;
    }

    .response-row-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-dark);
    }

    .response-progress {
        height: 30px;
        border-radius: 10px;
        background: #e2e8f0;
        overflow: hidden;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
    }

    .response-progress-bar {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.875rem;
        transition: width 0.6s ease;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .response-progress-bar.strongly-agree {
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .response-progress-bar.agree {
        background: linear-gradient(90deg, #3b82f6, #2563eb);
    }

    .response-progress-bar.neutral {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .response-progress-bar.disagree {
        background: linear-gradient(90deg, #f97316, #ea580c);
    }

    .response-progress-bar.strongly-disagree {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .analysis-summary {
        background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 2px solid var(--primary-blue);
    }

    .analysis-summary h5 {
        color: var(--primary-blue);
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1.5rem;
    }

    .summary-stat {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(13, 146, 244, 0.1);
    }

    .summary-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-blue);
        line-height: 1.2;
        margin-bottom: 0.5rem;
    }

    .summary-stat-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #analysisModal .modal-body {
        padding: 1.5rem;
        background: var(--bg-light);
    }

    @media (max-width: 768px) {
        .question-analysis-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .analysis-score-badge {
            text-align: left;
            width: 100%;
        }
        
        .summary-stats {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
    }
    </style>
    </head>
    <body>
        <div class="container">
            <div class="page-header">
                <h2>
                    <i class="fas fa-comments"></i>
                    Client Feedback Management
                </h2>
            </div>

            <?php if ($totalFeedbacks > 0): ?>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $totalFeedbacks ?></div>
                            <div class="stat-label">Total Feedbacks</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-smile"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $overallSatisfaction ?>%</div>
                            <div class="stat-label">Overall Satisfaction</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
    <div class="content-card mb-4">
        <form id="filterForm" class="row align-items-end" onsubmit="return false;">
            <div class="col-md-4 mb-3 mb-md-0">
                <label for="start_date" class="form-label">
                    <i class="fas fa-calendar-alt"></i> Start Date
                </label>
                <input type="text" 
                    class="form-control datepicker" 
                    id="start_date" 
                    name="start_date" 
                    placeholder="Select start date"
                    value="<?= htmlspecialchars($startDate ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <label for="end_date" class="form-label">
                    <i class="fas fa-calendar-alt"></i> End Date
                </label>
                <input type="text" 
                    class="form-control datepicker" 
                    id="end_date" 
                    name="end_date" 
                    placeholder="Select end date"
                    value="<?= htmlspecialchars($endDate ?? '') ?>">
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-primary btn-block mb-2" id="applyFiltersBtn">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button type="button" class="btn btn-secondary btn-block" id="clearFiltersBtn">
                    <i class="fas fa-redo"></i> Clear
                </button>
            </div>
        </form>
    </div>

    <div class="content-card">
        <div class="table-header">
            <div>
                <h4>
                    <i class="fas fa-table"></i> Feedback Records
                    <?php if ($startDate || $endDate): ?>
                        <small class="text-muted">
                            (<?= $startDate ? date('M d, Y', strtotime($startDate)) : 'All' ?> - 
                            <?= $endDate ? date('M d, Y', strtotime($endDate)) : 'All' ?>)
                        </small>
                    <?php endif; ?>
                </h4>
            </div>
            <?php if ($totalFeedbacks > 0): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary mr-2" onclick="showAnalysis()">
                        <i class="fas fa-chart-bar"></i> View Analysis
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalFeedbacks > 0): ?>
            <div class="table-wrapper">
                <table id="feedbackTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Resident</th>
                            <th>Service</th>
                            <th>Appointment Date</th>
                            <th>Satisfaction</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $feedback): 
                            $satisfactionScore = 0;
                            if ($feedback['sqd0_answer'] && $feedback['sqd0_answer'] !== 'N/A') {
                                switch ($feedback['sqd0_answer']) {
                                    case 'Strongly Agree': $satisfactionScore = 5; break;
                                    case 'Agree': $satisfactionScore = 4; break;
                                    case 'Neither Agree nor Disagree': $satisfactionScore = 3; break;
                                    case 'Disagree': $satisfactionScore = 2; break;
                                    case 'Strongly Disagree': $satisfactionScore = 1; break;
                                }
                            }
                            
                            $ratingClass = 'poor';
                            $ratingText = 'Poor';
                            if ($satisfactionScore >= 4.5) {
                                $ratingClass = 'excellent';
                                $ratingText = 'Excellent';
                            } elseif ($satisfactionScore >= 3.5) {
                                $ratingClass = 'good';
                                $ratingText = 'Good';
                            } elseif ($satisfactionScore >= 2.5) {
                                $ratingClass = 'fair';
                                $ratingText = 'Fair';
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($feedback['transaction_id']) ?></strong></td>
                            <td><?= htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']) ?></td>
                            <td><?= htmlspecialchars($feedback['service_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($feedback['scheduled_for'])) ?></td>
                            <td>
                                <span class="rating-badge <?= $ratingClass ?>">
                                    <i class="fas fa-star"></i>
                                    <?= $ratingText ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y g:i A', strtotime($feedback['submitted_at'])) ?></td>
                            <td>
                                <button class="btn-view" onclick="viewFeedback(<?= $feedback['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Feedback Yet</h4>
                <p>Feedback from residents will appear here once they complete their appointments.</p>
            </div>
        <?php endif; ?>
    </div>
    </div>

    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-comment-dots"></i>
                        Feedback Details
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="feedbackModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="analysisModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-chart-bar"></i>
                        Question-by-Question Analysis
                    </h5>
                    <div class="d-flex align-items-center ml-auto">
                        <button type="button" class="btn btn-success mr-2" onclick="exportAnalysisToExcel()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-file-excel"></i> Export Analysis
                        </button>
                        <button type="button" class="close text-white" data-dismiss="modal" style="margin: 0; padding: 0; margin-left: 1rem;">
                            <span style="font-size: 1.5rem;">&times;</span>
                        </button>
                    </div>
                </div>
                <div class="modal-body" id="analysisModalBody">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <script>
    window.initFeedbackPage = function() {
        console.log('🔄 Initializing Feedback Page...');
        
        if (!$.fn.DataTable.isDataTable('#feedbackTable')) {
            $('#feedbackTable').DataTable({
                order: [[5, 'desc']],
                pageLength: 10,
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search feedbacks..."
                }
            });
            console.log('✅ DataTable initialized');
        }
        
        initializeDatePickers();
        
        attachEventListeners();
    };

    function initializeDatePickers() {
        console.log('📅 Initializing date pickers...');
        
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        if (!startDateInput || !endDateInput) {
            console.warn('⚠️ Date inputs not found');
            return;
        }
        
        if (startDateInput._flatpickr) {
            startDateInput._flatpickr.destroy();
        }
        if (endDateInput._flatpickr) {
            endDateInput._flatpickr.destroy();
        }
        
        const startPicker = flatpickr("#start_date", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            allowInput: false,
            clickOpens: true,
            onChange: function(selectedDates, dateStr) {
                if (endPicker) {
                    endPicker.set('minDate', dateStr);
                }
            }
        });
        
        const endPicker = flatpickr("#end_date", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            allowInput: false,
            clickOpens: true,
            onChange: function(selectedDates, dateStr) {
                if (startPicker) {
                    startPicker.set('maxDate', dateStr);
                }
            }
        });
        
        const startVal = startDateInput.value;
        const endVal = endDateInput.value;
        if (startVal && endPicker) {
            endPicker.set('minDate', startVal);
        }
        if (endVal && startPicker) {
            startPicker.set('maxDate', endVal);
        }
        
        console.log('✅ Date pickers initialized');
    }

    window.feedbackPageCleanup = function() {
        console.log('🧹 Cleaning up Feedback Page...');
        
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        if (startDateInput && startDateInput._flatpickr) {
            console.log('Destroying start date picker');
            startDateInput._flatpickr.destroy();
        }
        
        if (endDateInput && endDateInput._flatpickr) {
            console.log('Destroying end date picker');
            endDateInput._flatpickr.destroy();
        }
        
        const flatpickrCalendars = document.querySelectorAll('.flatpickr-calendar');
        flatpickrCalendars.forEach(function(calendar) {
            console.log('Removing lingering Flatpickr calendar');
            calendar.remove();
        });
        
        if ($.fn.DataTable.isDataTable('#feedbackTable')) {
            console.log('Destroying DataTable');
            $('#feedbackTable').DataTable().destroy();
        }
        
        console.log('✅ Feedback page cleanup complete');
    };

    function attachEventListeners() {
        console.log('🔗 Attaching event listeners...');
        
        $('#applyFiltersBtn').off('click');
        $('#clearFiltersBtn').off('click');
        
        $('#applyFiltersBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            applyFeedbackFilters();
        });
        
        $('#clearFiltersBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearFeedbackFilters();
        });
        
        console.log('✅ Event listeners attached');
    }

    $(document).ready(function() {
        window.initFeedbackPage();
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(function() {
            if (typeof window.initFeedbackPage === 'function') {
                window.initFeedbackPage();
            }
        }, 100);
    }

    const feedbackData = <?= json_encode($feedbacks) ?>;

    function viewFeedback(feedbackId) {
        const feedback = feedbackData.find(f => f.id == feedbackId);
        
        if (!feedback) return;

        const getRatingClass = (answer) => {
            switch(answer) {
                case 'Strongly Agree': return 'excellent';
                case 'Agree': return 'good';
                case 'Neither Agree nor Disagree': return 'fair';
                case 'Disagree': 
                case 'Strongly Disagree': return 'poor';
                default: return 'fair';
            }
        };

        const sqdQuestions = [
            'I am satisfied with the service that I availed.',
            'I spent a reasonable amount of time for my transaction.',
            'The office followed the transaction\'s requirements and steps based on the information provided.',
            'The steps (including payment) I needed to do for my transaction were easy and simple.',
            'I easily found information about my transaction from the office\'s website.',
            'I paid a reasonable amount of fees for my transaction.',
            'I am confident my online transaction was secure.',
            'The office\'s online support was available, and online support\'s response was quick.',
            'I got what I needed from the government office, or denial of request was sufficiently explained to me.'
        ];

        let html = `
            <div class="feedback-section">
                <h5><i class="fas fa-info-circle"></i> Appointment Information</h5>
                <div class="feedback-item">
                    <div class="feedback-label">Transaction ID:</div>
                    <div class="feedback-answer"><strong>${feedback.transaction_id}</strong></div>
                </div>
                <div class="feedback-item">
                    <div class="feedback-label">Resident:</div>
                    <div class="feedback-answer">${feedback.first_name} ${feedback.last_name}</div>
                </div>
                <div class="feedback-item">
                    <div class="feedback-label">Service:</div>
                    <div class="feedback-answer">${feedback.service_name}</div>
                </div>
                <div class="feedback-item">
                    <div class="feedback-label">Appointment Date:</div>
                    <div class="feedback-answer">${new Date(feedback.scheduled_for).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                </div>
            </div>

            <div class="feedback-section">
                <h5><i class="fas fa-file-alt"></i> Citizen's Charter (CC) Responses</h5>
                ${feedback.cc1_answer ? `
                    <div class="feedback-item">
                        <div class="feedback-label">CC1: Awareness of CC</div>
                        <div class="feedback-answer">${feedback.cc1_answer}</div>
                    </div>
                ` : ''}
                ${feedback.cc2_answer ? `
                    <div class="feedback-item">
                        <div class="feedback-label">CC2: Visibility of CC</div>
                        <div class="feedback-answer">${feedback.cc2_answer}</div>
                    </div>
                ` : ''}
                ${feedback.cc3_answer ? `
                    <div class="feedback-item">
                        <div class="feedback-label">CC3: Helpfulness of CC</div>
                        <div class="feedback-answer">${feedback.cc3_answer}</div>
                    </div>
                ` : ''}
            </div>

            <div class="feedback-section">
                <h5><i class="fas fa-star"></i> Service Quality Dimensions (SQD)</h5>
        `;

        for (let i = 0; i <= 8; i++) {
            const answer = feedback[`sqd${i}_answer`];
            if (answer) {
                html += `
                    <div class="feedback-item">
                        <div class="feedback-label">SQD${i}: ${sqdQuestions[i]}</div>
                        <div class="feedback-answer rating ${getRatingClass(answer)}">
                            <i class="fas fa-check-circle"></i>
                            ${answer}
                        </div>
                    </div>
                `;
            }
        }

        html += `</div>`;

        if (feedback.suggestions) {
            html += `
                <div class="feedback-section">
                    <h5><i class="fas fa-lightbulb"></i> Suggestions for Improvement</h5>
                    <div class="suggestions-box">
                        ${feedback.suggestions}
                    </div>
                </div>
            `;
        }

        $('#feedbackModalBody').html(html);
        $('#feedbackModal').modal('show');
    }

    function applyFeedbackFilters() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        console.log('🔍 Apply feedback filters clicked - Start:', startDate, 'End:', endDate);
        
        if (startDate && endDate && startDate > endDate) {
            alert('Start date cannot be after end date');
            return false;
        }
        
        let url = 'personnel_view_feedbacks.php';
        const params = [];
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
        if (params.length > 0) url += '?' + params.join('&');
        
        console.log('📍 Loading URL:', url);
        
        $('#content-area').html(
            '<div class="text-center p-5">' +
            '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">' +
            '<span class="sr-only">Loading...</span>' +
            '</div>' +
            '<p class="mt-3 font-weight-bold">Applying filters...</p>' +
            '</div>'
        );
        
        const separator = url.includes('?') ? '&' : '?';
        const cacheBuster = '_t=' + new Date().getTime();
        
        $('#content-area').load(url + separator + cacheBuster, function(response, status, xhr) {
            if (status === "success") {
                console.log('✅ Filters applied successfully');
                setTimeout(function() {
                    if (typeof window.initFeedbackPage === 'function') {
                        window.initFeedbackPage();
                    }
                }, 100);
            } else {
                console.error('❌ Error applying filters:', xhr.status);
                $('#content-area').html(
                    '<div class="alert alert-danger m-4">' +
                    '<h4><i class="fas fa-exclamation-triangle"></i> Error</h4>' +
                    '<p>Error applying filters. Please try again.</p>' +
                    '<button class="btn btn-primary" onclick="window.initFeedbackPage()">Retry</button>' +
                    '</div>'
                );
            }
        });
        
        return false;
    }

    function clearFeedbackFilters() {
        console.log('🧹 Clear feedback filters clicked');
        
        $('#content-area').html(
            '<div class="text-center p-5">' +
            '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">' +
            '<span class="sr-only">Loading...</span>' +
            '</div>' +
            '<p class="mt-3 font-weight-bold">Clearing filters...</p>' +
            '</div>'
        );
        
        const cacheBuster = '?_t=' + new Date().getTime();
        
        $('#content-area').load('personnel_view_feedbacks.php' + cacheBuster, function(response, status, xhr) {
            if (status === "success") {
                console.log('✅ Filters cleared successfully');
                setTimeout(function() {
                    if (typeof window.initFeedbackPage === 'function') {
                        window.initFeedbackPage();
                    }
                }, 100);
            } else {
                console.error('❌ Error clearing filters:', xhr.status);
                $('#content-area').html(
                    '<div class="alert alert-danger m-4">' +
                    '<h4><i class="fas fa-exclamation-triangle"></i> Error</h4>' +
                    '<p>Error clearing filters. Please try again.</p>' +
                    '<button class="btn btn-primary" onclick="window.initFeedbackPage()">Retry</button>' +
                    '</div>'
                );
            }
        });
        
        return false;
    }
    function exportToExcel() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        let url = 'export_feedback_excel.php';
        const params = [];
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
        if (params.length > 0) url += '?' + params.join('&');
        
        console.log('📊 Exporting to Excel:', url);
        window.open(url, '_blank');
    }
    function showAnalysis() {
        console.log('📊 Generating analysis...');
        
        const sqdQuestions = {
            'sqd0': 'I am satisfied with the service that I availed.',
            'sqd1': 'I spent a reasonable amount of time for my transaction.',
            'sqd2': 'The office followed the transaction\'s requirements and steps.',
            'sqd3': 'The steps I needed to do for my transaction were easy and simple.',
            'sqd4': 'I easily found information about my transaction.',
            'sqd5': 'I paid a reasonable amount of fees for my transaction.',
            'sqd6': 'I am confident my online transaction was secure.',
            'sqd7': 'The office\'s online support was available and quick.',
            'sqd8': 'I got what I needed from the government office.'
        };
        
        const scoreMapping = {
            'Strongly Agree': 5,
            'Agree': 4,
            'Neither Agree nor Disagree': 3,
            'Disagree': 2,
            'Strongly Disagree': 1
        };
        
        const questionStats = {};
        Object.keys(sqdQuestions).forEach(key => {
            questionStats[key] = {
                question: sqdQuestions[key],
                responses: {
                    'Strongly Agree': 0,
                    'Agree': 0,
                    'Neither Agree nor Disagree': 0,
                    'Disagree': 0,
                    'Strongly Disagree': 0
                },
                total: 0,
                scoreTotal: 0,
                scoreCount: 0
            };
        });
        
        feedbackData.forEach(feedback => {
            Object.keys(sqdQuestions).forEach(key => {
                const answer = feedback[key + '_answer'];
                
                if (answer && answer !== 'N/A' && answer !== '') {
                    questionStats[key].total++;
                    
                    if (questionStats[key].responses.hasOwnProperty(answer)) {
                        questionStats[key].responses[answer]++;
                        
                        if (scoreMapping[answer]) {
                            questionStats[key].scoreTotal += scoreMapping[answer];
                            questionStats[key].scoreCount++;
                        }
                    }
                }
            });
        });
        
        let html = '<div class="analysis-summary">';
        html += '<h5><i class="fas fa-info-circle"></i> Overall Summary</h5>';
        html += '<div class="summary-stats">';
        
        let totalScores = 0;
        let totalCount = 0;
        Object.values(questionStats).forEach(stats => {
            totalScores += stats.scoreTotal;
            totalCount += stats.scoreCount;
        });
        
        const overallAverage = totalCount > 0 ? (totalScores / totalCount).toFixed(2) : 0;
        const overallSatisfaction = totalCount > 0 ? ((overallAverage / 5) * 100).toFixed(1) : 0;
        
        html += '<div class="summary-stat">';
        html += '<div class="summary-stat-value">' + feedbackData.length + '</div>';
        html += '<div class="summary-stat-label">Total Responses</div>';
        html += '</div>';
        html += '<div class="summary-stat">';
        html += '<div class="summary-stat-value">' + overallAverage + ' / 5</div>';
        html += '<div class="summary-stat-label">Avg Score</div>';
        html += '</div>';
        html += '<div class="summary-stat">';
        html += '<div class="summary-stat-value">' + overallSatisfaction + '%</div>';
        html += '<div class="summary-stat-label">Satisfaction</div>';
        html += '</div>';
        html += '</div></div>';
        
        Object.keys(sqdQuestions).forEach((key, index) => {
            const stats = questionStats[key];
            const avgScore = stats.scoreCount > 0 ? (stats.scoreTotal / stats.scoreCount).toFixed(2) : 0;
            const satisfaction = stats.scoreCount > 0 ? ((avgScore / 5) * 100).toFixed(1) : 0;
            
            let scoreClass = 'poor';
            if (satisfaction >= 80) scoreClass = 'excellent';
            else if (satisfaction >= 60) scoreClass = 'good';
            else if (satisfaction >= 40) scoreClass = 'fair';
            
            html += '<div class="question-analysis-card">';
            html += '<div class="question-analysis-header">';
            html += '<div class="analysis-question-title">';
            html += '<strong>' + key.toUpperCase() + ':</strong> ' + stats.question;
            html += '</div>';
            html += '<div class="analysis-score-badge">';
            html += '<div class="score-display ' + scoreClass + '">' + satisfaction + '%</div>';
            html += '<small class="d-block mt-1 text-muted">Avg: ' + avgScore + ' / 5.0</small>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="response-breakdown">';
            
            const responseOrder = [
                { label: 'Strongly Agree', class: 'strongly-agree' },
                { label: 'Agree', class: 'agree' },
                { label: 'Neither Agree nor Disagree', class: 'neutral' },
                { label: 'Disagree', class: 'disagree' },
                { label: 'Strongly Disagree', class: 'strongly-disagree' }
            ];
            
            responseOrder.forEach(resp => {
                const count = stats.responses[resp.label];
                const percentage = stats.total > 0 ? ((count / stats.total) * 100).toFixed(1) : 0;
                
                html += '<div class="response-row">';
                html += '<div class="response-row-label">';
                html += '<span>' + resp.label + '</span>';
                html += '<span>' + count + ' (' + percentage + '%)</span>';
                html += '</div>';
                html += '<div class="response-progress">';
                html += '<div class="response-progress-bar ' + resp.class + '" style="width: ' + percentage + '%">';
                if (percentage > 10) {
                    html += percentage + '%';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div></div>';
        });
        
        $('#analysisModalBody').html(html);
        $('#analysisModal').modal('show');
    }

    function exportAnalysisToExcel() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        let url = 'export_personnel_analysis_excel.php';
        const params = [];
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
        if (params.length > 0) url += '?' + params.join('&');
        
        console.log('📊 Exporting analysis to Excel:', url);
        window.open(url, '_blank');
    }
    </script>
    </body>
    </html>
