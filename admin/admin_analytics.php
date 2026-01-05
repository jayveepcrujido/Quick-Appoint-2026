<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row ? reset($row) : 0;
}

$totalAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments");
$completedAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE status='Completed'");
$pendingAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE status='Pending'");
$todaysAppointments = getCount($conn, "SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_for) = CURDATE()");
$registeredResidents = getCount($conn, "SELECT COUNT(*) FROM auth WHERE role='Resident'");
$lguPersonnel = getCount($conn, "SELECT COUNT(*) FROM auth WHERE role='LGU Personnel'");
$totalDepartments = getCount($conn, "SELECT COUNT(*) FROM departments");

$deptLabels = [];
$deptByDate = [];
$res = $conn->query("SELECT d.name, DATE_FORMAT(a.requested_at, '%Y-%m-%d') as date, COUNT(a.id) AS total 
                     FROM appointments a 
                     JOIN departments d ON a.department_id = d.id 
                     GROUP BY d.id, DATE_FORMAT(a.requested_at, '%Y-%m-%d') 
                     ORDER BY d.name, date ASC");
while ($row = $res->fetch_assoc()) {
    if (!in_array($row['name'], $deptLabels)) {
        $deptLabels[] = $row['name'];
    }
    $deptByDate[] = $row;
}

$serviceByDate = [];
$res = $conn->query("SELECT s.service_name, DATE_FORMAT(a.requested_at, '%Y-%m-%d') as date, COUNT(a.id) AS total 
                     FROM appointments a 
                     JOIN department_services s ON a.service_id = s.id 
                     GROUP BY s.id, DATE_FORMAT(a.requested_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $serviceByDate[] = $row;
}

$monthlyTrend = [];
$res = $conn->query("SELECT DATE_FORMAT(requested_at, '%Y-%m-%d') as date, COUNT(*) as total
                     FROM appointments 
                     GROUP BY DATE_FORMAT(requested_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $monthlyTrend[] = $row;
}

$sexByDate = [];
$res = $conn->query("SELECT r.sex, DATE_FORMAT(r.created_at, '%Y-%m-%d') as date, COUNT(*) as total 
                     FROM residents r 
                     GROUP BY r.sex, DATE_FORMAT(r.created_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $sexByDate[] = $row;
}

$ageByDate = [];
$res = $conn->query("SELECT 
                        CASE 
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) < 18 THEN '<18'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) <= 30 THEN '18-30'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) <= 50 THEN '31-50'
                            ELSE '50+'
                        END as age_group,
                        DATE_FORMAT(created_at, '%Y-%m-%d') as date,
                        COUNT(*) as total
                     FROM residents 
                     WHERE birthday IS NOT NULL
                     GROUP BY age_group, DATE_FORMAT(created_at, '%Y-%m-%d')
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $ageByDate[] = $row;
}

$appointmentsWithDates = [];
$res = $conn->query("SELECT DATE_FORMAT(requested_at, '%Y-%m-%d') as date, status, COUNT(*) as total 
                     FROM appointments 
                     GROUP BY DATE_FORMAT(requested_at, '%Y-%m-%d'), status 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $appointmentsWithDates[] = $row;
}

$residentsWithDates = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, COUNT(*) as total 
                     FROM residents 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $residentsWithDates[] = $row;
}


$personnelWithDates = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, COUNT(*) as total 
                     FROM lgu_personnel 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $personnelWithDates[] = $row;
}


$departmentsWithDates = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, COUNT(*) as total 
                     FROM departments 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d') 
                     ORDER BY date ASC");
while ($row = $res->fetch_assoc()) {
    $departmentsWithDates[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Analytics</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    body { 
        font-family: "Segoe UI", sans-serif; 
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        margin: 0;
        padding: 0;
    }

    .analytics-header { 
        background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
        padding: 20px 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        position: relative;
        top: 1;
        backdrop-filter: blur(10px);
        border-radius: 15px;
    }

    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        flex-wrap: wrap;
        gap: 15px;
    }

    .title { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        font-weight: 700; 
        font-size: 2rem; 
        color: #ffffff;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .title:hover {
        transform: translateY(-2px);
    }

    .title i { 
        font-size: 2.5rem;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }

    .datetime { 
        font-size: 1rem; 
        font-weight: 600; 
        color: rgba(255, 255, 255, 0.95);
        background: rgba(255, 255, 255, 0.15);
        padding: 10px 20px;
        border-radius: 25px;
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .datetime i {
        font-size: 1.1rem;
    }

    .summary { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 20px; 
        margin: 30px;
        padding: 0 10px;
    }

    .summary-card { 
        background: #fff; 
        padding: 20px; 
        border-radius: 15px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); 
        text-align: left; 
        display: flex; 
        flex-direction: column; 
        gap: 12px; 
        border-left: 6px solid transparent; 
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle, rgba(0, 0, 0, 0.03) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(30%, -30%);
    }

    .summary-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .summary-card .icon { 
        width: 50px; 
        height: 50px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 12px; 
        color: #fff; 
        font-size: 22px;
        transition: all 0.3s ease;
    }

    .summary-card:hover .icon {
        transform: scale(1.1) rotate(5deg);
    }

    .summary-card .value { 
        font-size: 28px; 
        font-weight: 700; 
        color: #333;
        line-height: 1;
    }

    .summary-card .label { 
        font-size: 14px; 
        font-weight: 600; 
        color: #666; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .purple { border-left-color: #6a11cb; } 
    .purple .icon { background: linear-gradient(135deg, #6a11cb, #2575fc); }

    .pink { border-left-color: #ff6a88; } 
    .pink .icon { background: linear-gradient(135deg, #ff6a88, #ff99ac); }

    .blue { border-left-color: #36d1dc; } 
    .blue .icon { background: linear-gradient(135deg, #36d1dc, #5b86e5); }

    .green { border-left-color: #2ecc71; } 
    .green .icon { background: linear-gradient(135deg, #2ecc71, #27ae60); }

    .orange { border-left-color: #ff9800; } 
    .orange .icon { background: linear-gradient(135deg, #ff9800, #ffb74d); }

    .red { border-left-color: #e74c3c; } 
    .red .icon { background: linear-gradient(135deg, #e74c3c, #ff6b6b); }

    .summary.small-cards {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        margin-top: 10px;
    }

    .summary-card.small {
        padding: 15px;
        border-radius: 10px;
        gap: 8px;
    }

    .summary-card.small .icon {
        width: 38px;
        height: 38px;
        font-size: 18px;
        border-radius: 8px;
    }

    .summary-card.small .value {
        font-size: 20px;
    }

    .summary-card.small .label {
        font-size: 12px;
    }

    .charts { 
        margin: 30px; 
        padding: 0 10px;
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
        gap: 20px;
    }

    .card { 
        background: #fff; 
        padding: 20px; 
        border-radius: 15px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        transform: translateY(-4px);
    }

    .card h5 { 
        font-weight: 700; 
        font-size: 16px; 
        color: #333; 
        margin-bottom: 15px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        border-bottom: 2px solid #f0f0f0; 
        padding-bottom: 10px;
    }

    .card h5 i { 
        color: #0D92F4; 
        font-size: 18px;
    }

    canvas { 
        height: 220px !important;
        max-width: 100%;
    }

    @media (max-width: 1024px) {
        .summary { 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 15px;
            margin: 20px;
        }
        
        .charts { 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            margin: 20px;
        }
        
        header {
            padding: 18px 20px;
        }
        
        .title {
            font-size: 1.75rem;
        }
        
        .title i {
            font-size: 2.2rem;
        }
    }

    @media (max-width: 767px) {
        header {
            padding: 15px;
        }
        
        .header-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .title {
            font-size: 1.5rem;
            gap: 10px;
        }
        
        .title i {
            font-size: 1.8rem;
        }
        
        .datetime {
            font-size: 0.9rem;
            padding: 8px 16px;
            width: 100%;
            justify-content: center;
        }
        
        .summary {
            grid-template-columns: 1fr;
            gap: 15px;
            margin: 15px;
            padding: 0;
        }
        
        .summary-card {
            padding: 18px;
        }
        
        .summary-card .value {
            font-size: 24px;
        }
        
        .summary.small-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .summary-card.small {
            padding: 12px;
        }
        
        .summary-card.small .value {
            font-size: 18px;
        }
        
        .charts {
            grid-template-columns: 1fr;
            gap: 15px;
            margin: 15px;
            padding: 0;
        }
        
        .card {
            padding: 15px;
        }
        
        .card h5 {
            font-size: 15px;
        }
        
        canvas {
            height: 200px !important;
        }
    }

    @media (max-width: 480px) {
        .summary {
            margin: 10px;
        }
        
        .charts {
            margin: 10px;
        }
        
        .summary-card {
            padding: 15px;
            gap: 10px;
        }
        
        .summary-card .icon {
            width: 42px;
            height: 42px;
            font-size: 20px;
        }
        
        .summary-card .value {
            font-size: 22px;
        }
        
        .summary-card .label {
            font-size: 13px;
        }
        
        .summary.small-cards {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .summary-card.small .icon {
            width: 32px;
            height: 32px;
            font-size: 16px;
        }
        
        canvas {
            height: 180px !important;
        }
    }

    @media (max-width: 767px) and (orientation: landscape) {
        .summary {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .charts {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .summary-card {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }

    .summary-card:nth-child(1) { animation-delay: 0.1s; }
    .summary-card:nth-child(2) { animation-delay: 0.2s; }
    .summary-card:nth-child(3) { animation-delay: 0.3s; }
    .summary-card:nth-child(4) { animation-delay: 0.4s; }
    .summary-card:nth-child(5) { animation-delay: 0.5s; }
    .summary-card:nth-child(6) { animation-delay: 0.6s; }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .global-filter-bar {
        background: linear-gradient(135deg, #ffffff, #f8f9fa);
        padding: 20px 30px;
        margin: 0 30px 25px 30px;
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

    .filter-btn {
        padding: 8px 16px;
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
        border-color: #0D92F4;
        color: #0D92F4;
        transform: translateY(-2px);
    }

    .filter-btn.active {
        background: linear-gradient(135deg, #0D92F4, #27548A);
        color: #fff;
        border-color: #0D92F4;
        box-shadow: 0 4px 12px rgba(13, 146, 244, 0.3);
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .global-filter-bar {
            padding: 15px 18px;
            margin: 0 15px 18px 15px;
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
        }
        
        .filter-btn {
            flex: 1;
            min-width: auto;
        }
    }
    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }

    .summary-card .value {
        transition: all 0.3s ease;
    }
    </style>
</head>
<body>
<header class="analytics-header">
    <div class="header-container">
        <h2 class="title"><i class='bx bxs-dashboard bx-tada'></i>Admin Analytics</h2>
        <div class="datetime"><span id="today-date"></span> | <span id="clock"></span></div>
    </div>
</header>

<div class="summary">
    <div class="summary-card purple">
        <div class="icon"><i class='bx bx-calendar-check'></i></div>
        <div class="value" id="total-appointments"><?= $totalAppointments ?></div>
        <div class="label">Appointments</div>
    </div>

    <div class="summary-card pink">
        <div class="icon"><i class='bx bx-user'></i></div>
        <div class="value" id="total-residents"><?= $registeredResidents ?></div>
        <div class="label">Registered Residents</div>
    </div>

    <div class="summary-card purple">
        <div class="icon"><i class='bx bx-id-card'></i></div>
        <div class="value" id="total-personnel"><?= $lguPersonnel ?></div>
        <div class="label">LGU Personnel</div>
    </div>

    <div class="summary-card green">
        <div class="icon"><i class='bx bx-building'></i></div>
        <div class="value" id="total-departments"><?= $totalDepartments ?></div>
        <div class="label">Departments</div>
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

<div class="charts">
    <div class="card">
        <h5><i class='bx bx-bar-chart-alt-2'></i> Appointments by Department</h5>
        <canvas id="deptChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-category-alt'></i> Appointments by Service</h5>
        <canvas id="serviceChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-line-chart'></i> Monthly Appointment Trend</h5>
        <canvas id="monthChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-male-female'></i> Residents by Sex</h5>
        <canvas id="sexChart"></canvas>
    </div>
    <div class="card">
        <h5><i class='bx bx-group'></i> Residents by Age Group</h5>
        <canvas id="ageChart"></canvas>
    </div>
</div>

<script>

let dateTimeInterval = null;

function updateDateTime() {
    const dateEl = document.getElementById("today-date");
    const clockEl = document.getElementById("clock");
    if (dateEl && clockEl) {
        const now = new Date();
        const options = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
        dateEl.textContent = now.toLocaleDateString('en-PH', options);
        clockEl.textContent = now.toLocaleTimeString('en-PH', { hour12:false });
    }
}

function initDateTime() {

    if (dateTimeInterval) {
        clearInterval(dateTimeInterval);
    }
    
    updateDateTime();
    
    dateTimeInterval = setInterval(updateDateTime, 1000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDateTime);
} else {
    initDateTime();
}

document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        initDateTime();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

updateDateTime();
setInterval(updateDateTime, 1000);

const appointmentsWithDatesData = <?= json_encode($appointmentsWithDates) ?>;
const residentsWithDatesData = <?= json_encode($residentsWithDates) ?>;
const personnelWithDatesData = <?= json_encode($personnelWithDates) ?>;
const departmentsWithDatesData = <?= json_encode($departmentsWithDates) ?>;
const deptByDateData = <?= json_encode($deptByDate) ?>;
const deptLabelsData = <?= json_encode($deptLabels) ?>;
const serviceByDateData = <?= json_encode($serviceByDate) ?>;
const monthlyTrendData = <?= json_encode($monthlyTrend) ?>;
const sexByDateData = <?= json_encode($sexByDate) ?>;
const ageByDateData = <?= json_encode($ageByDate) ?>;

let currentPeriod = 'weekly';
window.chartInstances = {};

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

function processDeptData(period) {
    const filtered = filterDataByPeriod(deptByDateData, period);
    const deptTotals = {};
    
    filtered.forEach(item => {
        deptTotals[item.name] = (deptTotals[item.name] || 0) + parseInt(item.total);
    });
    
    return {
        labels: Object.keys(deptTotals),
        values: Object.values(deptTotals)
    };
}

function processServiceData(period) {
    const filtered = filterDataByPeriod(serviceByDateData, period);
    const serviceTotals = {};
    
    filtered.forEach(item => {
        serviceTotals[item.service_name] = (serviceTotals[item.service_name] || 0) + parseInt(item.total);
    });
    
    const sorted = Object.entries(serviceTotals)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 8);
    
    return {
        labels: sorted.map(s => s[0]),
        values: sorted.map(s => s[1])
    };
}

function processMonthlyData(period) {
    const filtered = filterDataByPeriod(monthlyTrendData, period);
    const groupedData = {};
    
    filtered.forEach(item => {
        const date = new Date(item.date);
        let key;
        
        switch(period) {
            case 'weekly':
                const weekStart = new Date(date);
                weekStart.setDate(date.getDate() - date.getDay());
                key = weekStart.toISOString().split('T')[0];
                break;
            case 'monthly':
            case 'all':
                key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                break;
            case 'yearly':
                key = date.getFullYear().toString();
                break;
        }
        
        groupedData[key] = (groupedData[key] || 0) + parseInt(item.total);
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
        }
    });
    
    return {
        labels: labels,
        values: sortedKeys.map(key => groupedData[key])
    };
}

function processSexData(period) {
    const filtered = filterDataByPeriod(sexByDateData, period);
    const sexTotals = {};
    
    filtered.forEach(item => {
        const sex = item.sex.charAt(0).toUpperCase() + item.sex.slice(1);
        sexTotals[sex] = (sexTotals[sex] || 0) + parseInt(item.total);
    });
    
    return {
        labels: Object.keys(sexTotals),
        values: Object.values(sexTotals)
    };
}

function processAgeData(period) {
    const filtered = filterDataByPeriod(ageByDateData, period);
    const ageTotals = {};
    
    filtered.forEach(item => {
        ageTotals[item.age_group] = (ageTotals[item.age_group] || 0) + parseInt(item.total);
    });
    
    const ageGroups = ['<18', '18-30', '31-50', '50+'];
    ageGroups.forEach(group => {
        if (!ageTotals[group]) ageTotals[group] = 0;
    });
    
    return {
        labels: ageGroups,
        values: ageGroups.map(g => ageTotals[g] || 0)
    };
}


function processSummaryData(data, period) {
    if (!data || data.length === 0) return 0;
    const filtered = filterDataByPeriod(data, period);
    if (!filtered || filtered.length === 0) return 0;
    return filtered.reduce((sum, item) => sum + parseInt(item.total || 0), 0);
}


function updateSummaryCards(period) {
    const totalAppts = processSummaryData(appointmentsWithDatesData, period);
    document.getElementById('total-appointments').textContent = totalAppts || 0;
    
    const totalResidents = processSummaryData(residentsWithDatesData, period);
    document.getElementById('total-residents').textContent = totalResidents || 0;
    
    const totalPersonnel = processSummaryData(personnelWithDatesData, period);
    document.getElementById('total-personnel').textContent = totalPersonnel || 0;
    
    const totalDepts = processSummaryData(departmentsWithDatesData, period);
    document.getElementById('total-departments').textContent = totalDepts || 0;
    
    animateSummaryCards();
}

function animateSummaryCards() {
    const cards = document.querySelectorAll('.summary-card .value');
    cards.forEach((valueEl) => {
        valueEl.style.animation = 'pulse 0.5s ease';
    });
}

function updateAllCharts(period) {
    currentPeriod = period;
    
    updateSummaryCards(period);
    
    const deptData = processDeptData(period);
    if (window.chartInstances.deptChart && deptData.labels.length > 0) {
        window.chartInstances.deptChart.data.labels = deptData.labels;
        window.chartInstances.deptChart.data.datasets[0].data = deptData.values;
        window.chartInstances.deptChart.update('active');
    }
    
    const serviceData = processServiceData(period);
    if (window.chartInstances.serviceChart && serviceData.labels.length > 0) {
        window.chartInstances.serviceChart.data.labels = serviceData.labels;
        window.chartInstances.serviceChart.data.datasets[0].data = serviceData.values;
        window.chartInstances.serviceChart.update('active');
    }
    
    const monthData = processMonthlyData(period);
    if (window.chartInstances.monthChart) {
        window.chartInstances.monthChart.data.labels = monthData.labels;
        window.chartInstances.monthChart.data.datasets[0].data = monthData.values;
        window.chartInstances.monthChart.update('active');
    }
    
    const sexData = processSexData(period);
    if (window.chartInstances.sexChart && sexData.labels.length > 0) {
        window.chartInstances.sexChart.data.labels = sexData.labels;
        window.chartInstances.sexChart.data.datasets[0].data = sexData.values;
        window.chartInstances.sexChart.update('active');
    }
    
    const ageData = processAgeData(period);
    if (window.chartInstances.ageChart) {
        window.chartInstances.ageChart.data.labels = ageData.labels;
        window.chartInstances.ageChart.data.datasets[0].data = ageData.values;
        window.chartInstances.ageChart.update('active');
    }
}

window.initializeCharts = function() {
    Object.values(window.chartInstances).forEach(chart => {
        if (chart) chart.destroy();
    });
    window.chartInstances = {};

    if (!document.getElementById('deptChart')) return;

    const deptData = processDeptData('weekly');
    const serviceData = processServiceData('weekly');
    const monthData = processMonthlyData('weekly');
    const sexData = processSexData('weekly');
    const ageData = processAgeData('weekly');

    window.chartInstances.deptChart = new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: { 
            labels: deptData.labels, 
            datasets: [{ 
                label: 'Appointments', 
                data: deptData.values, 
                backgroundColor:'#36d1dc',
                borderRadius: 8 
            }] 
        },
        options: { 
            responsive:true, 
            maintainAspectRatio: false,
            plugins:{legend:{display:false}}, 
            scales:{ y:{beginAtZero:true,ticks:{precision:0}} } 
        }
    });

    window.chartInstances.serviceChart = new Chart(document.getElementById('serviceChart'), {
        type: 'bar',
        data: { 
            labels: serviceData.labels, 
            datasets: [{ 
                label: 'Appointments', 
                data: serviceData.values, 
                backgroundColor:'#9c27b0',
                borderRadius: 8
            }] 
        },
        options: { 
            indexAxis:'y', 
            responsive:true, 
            maintainAspectRatio: false,
            plugins:{legend:{display:false}}, 
            scales:{ x:{beginAtZero:true,ticks:{precision:0}} } 
        }
    });

    window.chartInstances.monthChart = new Chart(document.getElementById('monthChart'), {
        type: 'line',
        data: { 
            labels: monthData.labels, 
            datasets: [{ 
                label:'Appointments', 
                data: monthData.values, 
                borderColor:'#673ab7', 
                backgroundColor:'rgba(103,58,183,0.2)', 
                fill:true, 
                tension:0.3,
                pointBackgroundColor: '#673ab7',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }] 
        },
        options: { 
            responsive:true, 
            maintainAspectRatio: false,
            scales:{ y:{beginAtZero:true,ticks:{precision:0}} } 
        }
    });

    window.chartInstances.sexChart = new Chart(document.getElementById('sexChart'), {
        type: 'pie',
        data: { 
            labels: sexData.labels, 
            datasets: [{ 
                data: sexData.values, 
                backgroundColor:['#4caf50','#2196f3','#ff9800'] 
            }] 
        },
        options: { 
            responsive:true, 
            maintainAspectRatio: false 
        }
    });

    window.chartInstances.ageChart = new Chart(document.getElementById('ageChart'), {
        type: 'bar',
        data: { 
            labels: ageData.labels, 
            datasets: [{ 
                label: 'Residents', 
                data: ageData.values, 
                backgroundColor:'#ff9800',
                borderRadius: 8 
            }] 
        },
        options: { 
            responsive:true, 
            maintainAspectRatio: false,
            plugins:{legend:{display:false}}, 
            scales:{ y:{beginAtZero:true,ticks:{precision:0}} } 
        }
    });

    document.querySelectorAll('#global-filter .filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#global-filter .filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateAllCharts(this.dataset.period);
        });
    });
};

initializeCharts();
</script>
</body>
</html>
