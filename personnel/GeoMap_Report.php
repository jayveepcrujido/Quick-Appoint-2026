<?php
session_start();

if (!isset($_SESSION['personnel_id'])) {
    die("Unauthorized access");
}

$date_filter = $_POST['date_filter'] ?? 'all';
$department_id = $_POST['department_id'] ?? $_SESSION['department_id'];

$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dept_sql = "SELECT name, acronym FROM departments WHERE id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$department = $dept_result->fetch_assoc();
$dept_name = $department['acronym'] ?: $department['name'];
$dept_stmt->close();

$sql = "SELECT r.address, 
        COUNT(DISTINCT a.id) as appointment_count,
        COUNT(DISTINCT r.id) as resident_count,
        MAX(a.requested_at) as last_appointment,
        MIN(a.requested_at) as first_appointment
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL 
        AND r.address != ''
        AND a.department_id = ?";

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'week':
            $sql .= " AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $sql .= " AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $sql .= " AND a.requested_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

$sql .= " GROUP BY r.address ORDER BY appointment_count DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();
$conn->close();

$totalLocations = count($data);
$totalAppointments = array_sum(array_column($data, 'appointment_count'));
$totalResidents = array_sum(array_column($data, 'resident_count'));

$filter_labels = [
    'all' => 'All Time',
    'week' => 'Past Week',
    'month' => 'Past Month',
    'year' => 'Past Year'
];

$filename = "Appointment_Hotspot_Report_" . date('Ymd') . ".xls";

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body {
            font-family: Calibri, Arial, sans-serif;
            font-size: 11pt;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        .header-row {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            font-size: 14pt;
        }
        
        .info-table {
            margin: 10px 0;
        }
        
        .info-label {
            background-color: #D9E1F2;
            font-weight: bold;
            padding: 8px;
            width: 200px;
        }
        
        .info-value {
            padding: 8px;
            background-color: white;
        }
        
        .data-table {
            margin-top: 20px;
        }
        
        .data-table th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            padding: 10px;
            text-align: center;
            border: 1px solid white;
        }
        
        .data-table td {
            padding: 8px;
            border: 1px solid #D9D9D9;
            text-align: center;
        }
        
        .data-table tbody tr:nth-child(odd) {
            background-color: #F2F2F2;
        }
        
        .data-table tbody tr:nth-child(even) {
            background-color: white;
        }
        
        .summary-row {
            background-color: #E7E6E6;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- Header -->
<table cellpadding="0" cellspacing="0">
    <tr>
        <td class="header-row" colspan="6">
            Appointment Hotspot Report
        </td>
    </tr>
</table>

<!-- Report Info -->
<table class="info-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="info-label">Department:</td>
        <td class="info-value"><?= htmlspecialchars($dept_name) ?></td>
        <td class="info-label">Date Filter:</td>
        <td class="info-value"><?= $filter_labels[$date_filter] ?></td>
    </tr>
    <tr>
        <td class="info-label">Generated:</td>
        <td class="info-value"><?= date('F d, Y h:i A') ?></td>
        <td class="info-label">Total Locations:</td>
        <td class="info-value"><?= $totalLocations ?></td>
    </tr>
</table>

<!-- Data Table -->
<table class="data-table" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <th width="5%">No.</th>
            <th width="40%">Location / Address</th>
            <th width="15%">Total Appointments</th>
            <th width="15%">Total Residents</th>
            <th width="12%">First Appointment</th>
            <th width="13%">Last Appointment</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($data as $row): 
        ?>
        <tr>
            <td><?= $no ?></td>
            <td style="text-align: left; padding-left: 10px;"><?= htmlspecialchars($row['address']) ?></td>
            <td><?= $row['appointment_count'] ?></td>
            <td><?= $row['resident_count'] ?></td>
            <td><?= $row['first_appointment'] ? date('M d, Y', strtotime($row['first_appointment'])) : 'N/A' ?></td>
            <td><?= $row['last_appointment'] ? date('M d, Y', strtotime($row['last_appointment'])) : 'N/A' ?></td>
        </tr>
        <?php 
            $no++;
        endforeach; 
        ?>
        
        <!-- Summary Row -->
        <tr class="summary-row">
            <td colspan="2" style="text-align: right; padding-right: 10px;">TOTAL:</td>
            <td><?= $totalAppointments ?></td>
            <td><?= $totalResidents ?></td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

</body>
</html>
<?php
exit;
?>