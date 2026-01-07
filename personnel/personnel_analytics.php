<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id']) || !in_array($_SESSION['role'], ['Admin','LGU Personnel'])) {
    header("Location: ../login.php");
    exit();
}

$authId = $_SESSION['auth_id'];
$role   = $_SESSION['role'];

$departmentId = null;
$departmentName = null;

if ($role === 'LGU Personnel') {
    $stmt = $pdo->prepare("SELECT lp.department_id, d.name 
                           FROM lgu_personnel lp 
                           JOIN departments d ON lp.department_id = d.id 
                           WHERE lp.auth_id = ?");
    $stmt->execute([$authId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $departmentId = $row['department_id'];
    $departmentName = $row['name'];
}

$query = "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) AS completed
          FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_for) = CURDATE()";
if ($departmentId) $query .= " AND department_id = :dept";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$todayAppointments = $stmt->fetchColumn();

$query = "SELECT DATE_FORMAT(requested_at, '%Y-%m-%d') as date, COUNT(*) as total
          FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$query .= " GROUP BY date ORDER BY date ASC";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$allTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT status, DATE_FORMAT(requested_at, '%Y-%m-%d') as date, COUNT(*) as total 
          FROM appointments";
if ($departmentId) $query .= " WHERE department_id = :dept";
$query .= " GROUP BY status, DATE_FORMAT(requested_at, '%Y-%m-%d') ORDER BY date ASC";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$statusByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT s.service_name, DATE_FORMAT(a.requested_at, '%Y-%m-%d') as date, COUNT(a.id) as total
          FROM appointments a
          JOIN department_services s ON a.service_id = s.id";
if ($departmentId) $query .= " WHERE a.department_id = :dept";
$query .= " GROUP BY s.service_name, DATE_FORMAT(a.requested_at, '%Y-%m-%d') ORDER BY date ASC";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$serviceByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT DATE_FORMAT(requested_at, '%Y-%m-%d') as date,
          CASE WHEN HOUR(scheduled_for) < 12 THEN 'AM' ELSE 'PM' END as period,
          COUNT(*) as total
          FROM appointments
          WHERE scheduled_for IS NOT NULL";
if ($departmentId) $query .= " AND department_id = :dept";
$query .= " GROUP BY DATE_FORMAT(requested_at, '%Y-%m-%d'), period ORDER BY date ASC";
$stmt = $pdo->prepare($query);
if ($departmentId) $stmt->bindValue(':dept', $departmentId);
$stmt->execute();
$amPmByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Personnel Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        header {
            padding: 0;
            margin-top: -1.3rem;
            background: transparent;
            border: none;
        }

        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            padding: 25px 30px;
            margin: 20px;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(30, 60, 114, 0.25), 0 4px 8px rgba(0, 0, 0, 0.1);
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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, -20px) scale(1.1); }
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .title-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
            transition: all 0.3s ease;
        }

        .title-icon:hover {
            transform: translateY(-3px) rotate(5deg);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.6);
        }

        .title-icon i {
            font-size: 28px;
            color: #ffffff;
        }

        .title-content h2 {
            font-weight: 700;
            font-size: 1.75rem;
            color: #ffffff;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        .title-content p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            margin: 5px 0 0 0;
            font-weight: 500;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .info-card i {
            font-size: 20px;
            color: #ffffff;
            opacity: 0.9;
        }

        .info-card .info-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-card .info-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card .info-value {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 700;
        }

        .department-badge {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            padding: 10px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .department-badge i {
            font-size: 18px;
            color: #ffffff;
        }

        .department-badge span {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }

        main { padding: 20px; }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-left: 6px solid transparent;
        }

        .summary-card.purple { border-left-color: #667eea; }
        .summary-card.pink { border-left-color: #f093fb; }
        .summary-card.blue { border-left-color: #4facfe; }
        .summary-card.green { border-left-color: #43e97b; }

        .summary-card .icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #fff;
            font-size: 20px;
        }

        .summary-card .icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .summary-card .icon.pink { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .summary-card .icon.blue { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .summary-card .icon.green { background: linear-gradient(135deg, #43e97b, #38f9d7); }

        .summary-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .summary-card .label {
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }
        .global-filter-bar {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            padding: 20px 30px;
            margin: 0 20px 25px 20px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }

        .global-filter-bar .filter-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }

        .global-filter-bar .filter-title i {
            font-size: 24px;
            color: #667eea;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .global-filter-bar .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .grid .card.full-width {
            grid-column: 1 / -1;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
            flex-wrap: wrap;
        }

        .card h3 i {
            font-size: 18px;
            color: #2a5298;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 14px;
            border: 2px solid #e0e0e0;
            background: #fff;
            color: #666;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-btn:hover {
            border-color: #2a5298;
            color: #2a5298;
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: #fff;
            border-color: #2a5298;
            box-shadow: 0 4px 12px rgba(42, 82, 152, 0.3);
        }

        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        canvas { max-width: 100%; }

        @media (hover: none) and (pointer: coarse) {
            .filter-btn {
                min-height: 44px;
                min-width: 44px;
            }
            
            .info-card {
                min-height: 44px;
            }
            
            .filter-btn:hover,
            .info-card:hover,
            .title-icon:hover {
                transform: none;
            }
        }

        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        body {
            overflow-x: hidden;
        }

        @media (max-width: 1400px) {
    .grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 1024px) {
        main {
            padding: 15px;
        }
        
        .page-header {
            margin: 15px;
            padding: 20px;
        }
        
        .header-container {
            gap: 15px;
        }
        
        .title-content h2 {
            font-size: 1.5rem;
        }
        
        .info-card {
            padding: 10px 15px;
        }
        
        .info-card .info-label {
            font-size: 0.65rem;
        }
        
        .info-card .info-value {
            font-size: 0.85rem;
        }
        
        .summary {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .global-filter-bar {
            padding: 18px 25px;
            margin: 0 15px 20px 15px;
        }
        
        .chart-container {
            height: 280px;
        }
    }

    @media (max-width: 768px) {
        body {
            font-size: 14px;
        }
        
        main {
            padding: 12px;
        }
        
        .page-header {
            padding: 18px;
            margin: 12px;
            border-radius: 14px;
        }
        
        .page-header::before,
        .page-header::after {
            display: none;
        }
        
        .header-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .title-section {
            width: 100%;
            gap: 12px;
        }
        
        .title-icon {
            width: 50px;
            height: 50px;
        }
        
        .title-icon i {
            font-size: 24px;
        }
        
        .title-content h2 {
            font-size: 1.4rem;
        }
        
        .title-content p {
            font-size: 0.85rem;
        }
        
        .header-info {
            width: 100%;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .info-card {
            flex: 1 1 calc(50% - 5px);
            min-width: 140px;
            padding: 10px 12px;
        }
        
        .department-badge {
            width: 100%;
            justify-content: center;
            padding: 12px 18px;
        }
        
        .summary {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            padding: 15px;
        }
        
        .summary-card .icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .summary-card .value {
            font-size: 20px;
        }
        
        .summary-card .label {
            font-size: 11px;
        }
        
        .global-filter-bar {
            padding: 15px 18px;
            margin: 0 12px 18px 12px;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .global-filter-bar .filter-title {
            font-size: 15px;
            width: 100%;
        }
        
        .global-filter-bar .filter-buttons {
            width: 100%;
            justify-content: flex-start;
            gap: 8px;
        }
        
        .filter-btn {
            flex: 1;
            min-width: auto;
            padding: 8px 12px;
            font-size: 11px;
        }
        
        .grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .card {
            padding: 15px;
        }
        
        .card h3 {
            font-size: 15px;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .card h3 i {
            font-size: 16px;
        }
        
        .chart-container {
            height: 260px;
        }
    }

    @media (max-width: 480px) {
        body {
            font-size: 13px;
        }
        
        main {
            padding: 10px;
        }
        
        .page-header {
            padding: 15px;
            margin: 10px;
            border-radius: 12px;
        }
        
        .title-section {
            gap: 10px;
        }
        
        .title-icon {
            width: 45px;
            height: 45px;
        }
        
        .title-icon i {
            font-size: 22px;
        }
        
        .title-content h2 {
            font-size: 1.2rem;
            letter-spacing: 0.3px;
        }
        
        .title-content p {
            font-size: 0.8rem;
        }
        
        .header-info {
            gap: 8px;
        }
        
        .info-card {
            flex: 1 1 100%;
            padding: 10px 15px;
        }
        
        .info-card i {
            font-size: 18px;
        }
        
        .info-card .info-label {
            font-size: 0.6rem;
        }
        
        .info-card .info-value {
            font-size: 0.85rem;
        }
        
        .department-badge {
            padding: 10px 16px;
        }
        
        .department-badge i {
            font-size: 16px;
        }
        
        .department-badge span {
            font-size: 0.85rem;
        }
        
        .summary {
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .summary-card {
            padding: 12px;
            flex-direction: row;
            align-items: center;
            gap: 12px;
        }
        
        .summary-card .icon {
            width: 45px;
            height: 45px;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .summary-card .content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .summary-card .value {
            font-size: 22px;
        }
        
        .summary-card .label {
            font-size: 11px;
        }
        
        .global-filter-bar {
            padding: 12px 15px;
            margin: 0 10px 15px 10px;
            border-radius: 12px;
        }
        
        .global-filter-bar .filter-title {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .global-filter-bar .filter-title i {
            font-size: 20px;
        }
        
        .global-filter-bar .filter-buttons {
            gap: 6px;
        }
        
        .filter-btn {
            padding: 8px 10px;
            font-size: 10px;
            border-radius: 6px;
            letter-spacing: 0.3px;
        }
        
        .grid {
            gap: 12px;
        }
        
        .card {
            padding: 12px;
            border-radius: 10px;
        }
        
        .card h3 {
            font-size: 14px;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }
        
        .chart-container {
            height: 240px;
        }
    }

    @media (max-width: 360px) {
        .page-header {
            padding: 12px;
            margin: 8px;
        }
        
        .title-content h2 {
            font-size: 1.1rem;
        }
        
        .summary-card .value {
            font-size: 20px;
        }
        
        .filter-btn {
            padding: 7px 8px;
            font-size: 9px;
        }
        
        .chart-container {
            height: 220px;
        }
    }

    @media (max-height: 500px) and (orientation: landscape) {
        .page-header {
            padding: 12px 20px;
        }
        
        .title-icon {
            width: 40px;
            height: 40px;
        }
        
        .title-content h2 {
            font-size: 1.2rem;
        }
        
        .summary {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .summary-card {
            padding: 10px;
        }
        
        .chart-container {
            height: 200px;
        }
    }

    @media print {
        .global-filter-bar {
            display: none;
        }
        
        .page-header::before,
        .page-header::after {
            display: none;
        }
        
        .card {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
    </style>
</head>
<body>
    <header>
        <div class="page-header">
            <div class="header-container">
                <div class="title-section">
                    <div class="title-icon">
                        <i class='bx bxs-dashboard bx-tada'></i>
                    </div>
                    <div class="title-content">
                        <h2>LGU Personnel Analytics</h2>
                        <p>Real-time dashboard & insights</p>
                    </div>
                </div>

                <div class="header-info">
                    <div class="info-card">
                        <i class='bx bx-calendar'></i>
                        <div class="info-text">
                            <span class="info-label">Date</span>
                            <span class="info-value" id="today-date"></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <i class='bx bx-time-five'></i>
                        <div class="info-text">
                            <span class="info-label">Time</span>
                            <span class="info-value" id="clock"></span>
                        </div>
                    </div>

                    <?php if ($departmentName): ?>
                    <div class="department-badge">
                        <i class='bx bx-building'></i>
                        <span><?= htmlspecialchars($departmentName) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="summary">
            <div class="summary-card purple">
                <div class="icon purple"><i class="fas fa-calendar-check"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['total'] ?? 0 ?></div>
                    <div class="label">Total Appointments</div>
                </div>
            </div>

            <div class="summary-card pink">
                <div class="icon pink"><i class="fas fa-clock"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['pending'] ?? 0 ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>

            <div class="summary-card blue">
                <div class="icon blue"><i class="fas fa-check-circle"></i></div>
                <div class="content">
                    <div class="value"><?= $totals['completed'] ?? 0 ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>

            <div class="summary-card green">
                <div class="icon green"><i class="fas fa-calendar-day"></i></div>
                <div class="content">
                    <div class="value"><?= $todayAppointments ?? 0 ?></div>
                    <div class="label">Appointments Today</div>
                </div>
            </div>
        </div>

        <div class="global-filter-bar">
            <div class="filter-title">
                <i class='bx bx-filter-alt'></i>
                <span>Time Period Filter</span>
            </div>
            <div class="filter-buttons" id="global-filter">
                <button class="filter-btn active" data-period="weekly">Weekly</button>
                <button class="filter-btn" data-period="monthly">Monthly</button>
                <button class="filter-btn" data-period="yearly">Yearly</button>
                <button class="filter-btn" data-period="all">All Time</button>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>
                    <i class='bx bx-pie-chart-alt-2'></i> Appointments Status
                </h3>
                <div class="chart-container">
                    <canvas id="apptChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>
                    <i class='bx bx-category-alt'></i> Appointments by Service
                </h3>
                <div class="chart-container">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>
                    <i class='bx bx-time-five'></i> AM vs PM Appointments
                </h3>
                <div class="chart-container">
                    <canvas id="amPmChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>
                    <i class='bx bx-line-chart'></i> Appointments Over Time
                </h3>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </main>

<script>
window.initDashboardCharts = function() {
    console.log('Initializing dashboard charts...');
    
    if (window.dashboardCharts) {
        Object.values(window.dashboardCharts).forEach(chart => {
            if (chart) chart.destroy();
        });
    }
    
    window.dashboardCharts = {
        status: null,
        service: null,
        amPm: null,
        trends: null
    };
    
    setTimeout(() => {
        const canvases = {
            status: document.getElementById('apptChart'),
            service: document.getElementById('serviceChart'),
            amPm: document.getElementById('amPmChart'),
            trends: document.getElementById('monthlyChart')
        };
        
        const allExist = Object.values(canvases).every(canvas => canvas !== null);
        
        if (!allExist) {
            console.error('Some canvas elements not found:', {
                status: !!canvases.status,
                service: !!canvases.service,
                amPm: !!canvases.amPm,
                trends: !!canvases.trends
            });
            return;
        }
        
        console.log('All canvas elements found, creating charts...');
        createAllCharts();
        animateElements();
    }, 200);
};

const allTrendsData = <?= json_encode($allTrends) ?>;
const statusByDateData = <?= json_encode($statusByDate) ?>;
const serviceByDateData = <?= json_encode($serviceByDate) ?>;
const amPmByDateData = <?= json_encode($amPmByDate) ?>;

let currentGlobalPeriod = 'weekly';

function filterDataByPeriod(data, period) {
    if (!data || data.length === 0) return [];
    
    const now = new Date();
    return data.filter(item => {
        const date = new Date(item.date);
        
        switch(period) {
            case 'weekly':
                const weekDiff = Math.floor((now - date) / (7 * 24 * 60 * 60 * 1000));
                return weekDiff < 12;
            case 'monthly':
                const monthDiff = (now.getFullYear() - date.getFullYear()) * 12 + (now.getMonth() - date.getMonth());
                return monthDiff < 12;
            case 'yearly':
                const yearDiff = now.getFullYear() - date.getFullYear();
                return yearDiff < 5;
            case 'all':
                return true;
            default:
                return true;
        }
    });
}

function processStatusData(period) {
    const filtered = filterDataByPeriod(statusByDateData, period);
    const statusTotals = {};
    
    filtered.forEach(item => {
        statusTotals[item.status] = (statusTotals[item.status] || 0) + parseInt(item.total);
    });
    
    return {
        labels: Object.keys(statusTotals),
        values: Object.values(statusTotals)
    };
}

function processServiceData(period) {
    const filtered = filterDataByPeriod(serviceByDateData, period);
    const serviceTotals = {};
    
    filtered.forEach(item => {
        serviceTotals[item.service_name] = (serviceTotals[item.service_name] || 0) + parseInt(item.total);
    });
    
    return {
        labels: Object.keys(serviceTotals),
        values: Object.values(serviceTotals)
    };
}

function processAmPmData(period) {
    const filtered = filterDataByPeriod(amPmByDateData, period);
    let amCount = 0;
    let pmCount = 0;
    
    filtered.forEach(item => {
        const total = parseInt(item.total);
        if (item.period === 'AM') {
            amCount += total;
        } else {
            pmCount += total;
        }
    });
    
    return { am: amCount, pm: pmCount };
}

function processDataByPeriod(data, period) {
    if (!data || data.length === 0) {
        return { labels: [], values: [] };
    }

    const groupedData = {};
    const now = new Date();
    
    data.forEach(item => {
        const date = new Date(item.date);
        let key;
        let include = false;
        
        switch(period) {
            case 'weekly':
                const weekDiff = Math.floor((now - date) / (7 * 24 * 60 * 60 * 1000));
                if (weekDiff < 12) {
                    const weekStart = new Date(date);
                    weekStart.setDate(date.getDate() - date.getDay());
                    key = weekStart.toISOString().split('T')[0];
                    include = true;
                }
                break;
                
            case 'monthly':
                const monthDiff = (now.getFullYear() - date.getFullYear()) * 12 + (now.getMonth() - date.getMonth());
                if (monthDiff < 12) {
                    key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                    include = true;
                }
                break;
                
            case 'yearly':
                const yearDiff = now.getFullYear() - date.getFullYear();
                if (yearDiff < 5) {
                    key = date.getFullYear().toString();
                    include = true;
                }
                break;
                
            case 'all':
                key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                include = true;
                break;
        }
        
        if (include) {
            groupedData[key] = (groupedData[key] || 0) + parseInt(item.total);
        }
    });
    
    const sortedKeys = Object.keys(groupedData).sort();
    const labels = sortedKeys.map(key => {
        switch(period) {
            case 'weekly':
                const d = new Date(key);
                return 'Week of ' + d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            case 'monthly':
            case 'all':
                const [year, month] = key.split('-');
                return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
            case 'yearly':
                return key;
            default:
                return key;
        }
    });
    
    const values = sortedKeys.map(key => groupedData[key]);
    
    return { labels, values };
}

function updateAllCharts(period) {
    currentGlobalPeriod = period;
    
    if (!window.dashboardCharts) return;
    
    const statusData = processStatusData(period);
    if (window.dashboardCharts.status && statusData.labels.length > 0) {
        window.dashboardCharts.status.data.labels = statusData.labels;
        window.dashboardCharts.status.data.datasets[0].data = statusData.values;
        window.dashboardCharts.status.update('active');
    }
    
    const serviceData = processServiceData(period);
    if (window.dashboardCharts.service && serviceData.labels.length > 0) {
        window.dashboardCharts.service.data.labels = serviceData.labels;
        window.dashboardCharts.service.data.datasets[0].data = serviceData.values;
        const colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#f5576c', '#764ba2', '#f5af19'];
        window.dashboardCharts.service.data.datasets[0].backgroundColor = colors.slice(0, serviceData.labels.length);
        window.dashboardCharts.service.update('active');
    }
    
    const amPmData = processAmPmData(period);
    if (window.dashboardCharts.amPm) {
        window.dashboardCharts.amPm.data.datasets[0].data = [amPmData.am, amPmData.pm];
        window.dashboardCharts.amPm.update('active');
    }
    
    const trendsData = processDataByPeriod(allTrendsData, period);
    if (window.dashboardCharts.trends) {
        window.dashboardCharts.trends.data.labels = trendsData.labels;
        window.dashboardCharts.trends.data.datasets[0].data = trendsData.values;
        window.dashboardCharts.trends.update('active');
    }
}

function createAllCharts() {
    const apptCanvas = document.getElementById('apptChart');
    const serviceCanvas = document.getElementById('serviceChart');
    const amPmCanvas = document.getElementById('amPmChart');
    const monthlyCanvas = document.getElementById('monthlyChart');
    
    const initialStatusData = processStatusData('weekly');
    if (initialStatusData.labels.length > 0 && apptCanvas) {
        window.dashboardCharts.status = new Chart(apptCanvas, {
            type: 'doughnut',
            data: {
                labels: initialStatusData.labels,
                datasets: [{
                    data: initialStatusData.values,
                    backgroundColor: ['#43e97b', '#f5576c', '#4facfe', '#f093fb'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 15
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        position: window.innerWidth < 480 ? 'bottom' : 'right',
                        labels: {
                            padding: 15,
                            font: { size: 13, weight: 'bold' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ` ${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const initialServiceData = processServiceData('weekly');
    if (initialServiceData.labels.length > 0 && serviceCanvas) {
        const colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#f5576c', '#764ba2', '#f5af19'];
        window.dashboardCharts.service = new Chart(serviceCanvas, {
            type: 'bar',
            data: {
                labels: initialServiceData.labels,
                datasets: [{
                    label: 'Appointments',
                    data: initialServiceData.values,
                    backgroundColor: colors.slice(0, initialServiceData.labels.length),
                    borderRadius: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                indexAxis: 'y',
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart',
                    delay: (context) => context.dataIndex * 150
                },
                scales: { 
                    x: { 
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { precision: 0 }
                    },
                    y: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(102, 126, 234, 0.9)',
                        padding: 12
                    }
                }
            }
        });
    }
    
    const initialAmPmData = processAmPmData('weekly');
    if (amPmCanvas) {
        window.dashboardCharts.amPm = new Chart(amPmCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Morning (AM)', 'Afternoon (PM)'],
                datasets: [{
                    data: [initialAmPmData.am, initialAmPmData.pm],
                    backgroundColor: ['#4facfe', '#f093fb'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 15
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        position: window.innerWidth < 480 ? 'bottom' : 'right',
                        labels: {
                            padding: 15,
                            font: { size: 13, weight: 'bold' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ` ${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    const initialTrendsData = processDataByPeriod(allTrendsData, 'weekly');
    if (initialTrendsData.labels.length > 0 && monthlyCanvas) {
        window.dashboardCharts.trends = new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: initialTrendsData.labels,
                datasets: [{
                    label: 'Appointments',
                    data: initialTrendsData.values,
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderColor: '#667eea',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                animation: { duration: 1000, easing: 'easeInOutQuart' },
                scales: { 
                    x: { 
                        grid: { display: false },
                        ticks: { maxRotation: 45, minRotation: 0 }
                    },
                    y: { 
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(102, 126, 234, 0.9)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return ` Appointments: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    document.querySelectorAll('#global-filter .filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#global-filter .filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateAllCharts(this.dataset.period);
        });
    });
    
    console.log('Charts created successfully');
}

function animateElements() {
    const containers = document.querySelectorAll('.chart-container, .card');
    containers.forEach((c, i) => {
        c.style.opacity = '0';
        c.style.transform = 'translateY(30px)';
        c.style.transition = 'all 0.6s ease';
        setTimeout(() => {
            c.style.opacity = '1';
            c.style.transform = 'translateY(0)';
        }, i * 150);
    });
    
    const header = document.querySelector('.page-header');
    if (header) {
        header.style.opacity = '0';
        header.style.transform = 'translateY(-20px)';
        header.style.transition = 'all 0.8s ease';
        setTimeout(() => {
            header.style.opacity = '1';
            header.style.transform = 'translateY(0)';
        }, 100);
    }
}

let clockInterval = null;

function updateDateTime() {
    const now = new Date();
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const dateEl = document.getElementById("today-date");
    const clockEl = document.getElementById("clock");
    
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', options);
    }
    if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }
}

function startClock() {
    if (clockInterval) {
        clearInterval(clockInterval);
        clockInterval = null;
    }
    
    updateDateTime();
    if (typeof originalSetInterval !== 'undefined') {
        clockInterval = originalSetInterval(updateDateTime, 1000);
        console.log('✓ Clock started (using original setInterval)');
    } else {
        clockInterval = setInterval(updateDateTime, 1000);
        console.log('✓ Clock started (using regular setInterval)');
    }
}

function stopClock() {
    if (clockInterval) {
        clearInterval(clockInterval);
        clockInterval = null;
        console.log('✓ Clock stopped');
    }
}

window.analyticsPageCleanup = function() {
    console.log('🧹 Analytics page cleanup...');
    stopClock();
    
    if (window.dashboardCharts) {
        Object.values(window.dashboardCharts).forEach(function(chart) {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        window.dashboardCharts = null;
    }
};

window.startAnalyticsClock = startClock;

window.initDashboardCharts();
startClock();
</script>
</body>
</html>
