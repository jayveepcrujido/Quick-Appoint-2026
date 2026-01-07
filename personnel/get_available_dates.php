<?php
session_start();
include '../conn.php';

header('Content-Type: application/json');


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$_SESSION['auth_id']]);
    $department_id = $stmt->fetchColumn();

    if (!$department_id) {
        echo json_encode(['success' => false, 'message' => 'No department assigned']);
        exit();
    }
} catch (Exception $e) {
    error_log("Error getting department_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving user department']);
    exit();
}

try {
    $query = "
        SELECT 
            ad.id as date_id,
            DATE(ad.date_time) as date,
            ad.am_slots,
            ad.pm_slots,
            ad.am_booked,
            ad.pm_booked
        FROM available_dates ad
        WHERE ad.department_id = ?
        AND DATE(ad.date_time) >= CURDATE()
        AND DATE(ad.date_time) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY ad.date_time ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$department_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedDates = [];
    foreach ($dates as $date) {
        $am_remaining = max(0, $date['am_slots'] - $date['am_booked']);
        $pm_remaining = max(0, $date['pm_slots'] - $date['pm_booked']);
        
        if ($am_remaining > 0 || $pm_remaining > 0) {
            $processedDates[] = [
                'date_id' => (int)$date['date_id'],
                'date' => $date['date'],
                'am_slots' => (int)$date['am_slots'],
                'pm_slots' => (int)$date['pm_slots'],
                'am_booked' => (int)$date['am_booked'],
                'pm_booked' => (int)$date['pm_booked'],
                'am_remaining' => (int)$am_remaining,
                'pm_remaining' => (int)$pm_remaining,
                'am_open' => $am_remaining > 0,
                'pm_open' => $pm_remaining > 0
            ];
        }
    }
    
    if (empty($processedDates)) {
        echo json_encode([
            'success' => false,
            'message' => 'No available dates in the next 30 days. Please ask your administrator to set up available dates.',
            'debug' => [
                'department_id' => $department_id,
                'total_dates_found' => count($dates),
                'dates_with_availability' => 0
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'dates' => $processedDates,
            'count' => count($processedDates)
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in get_available_dates.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database query error: ' . $e->getMessage(),
        'debug' => [
            'error_code' => $e->getCode(),
            'department_id' => $department_id ?? 'unknown'
        ]
    ]);
} catch (Exception $e) {
    error_log("General error in get_available_dates.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
?>