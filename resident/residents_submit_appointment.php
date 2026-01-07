//RESIDENT SUBMIT APPOINTMENT - WITH EMAIL & SMS NOTIFICATIONS (FIXED)
<?php
session_start();
include '../conn.php';

// ADD THESE INCLUDES AT THE TOP
require_once '../send_reset_email.php';
require_once '../notification_service.php';

ob_clean();
header('Content-Type: application/json');

// ✅ Must be logged in as Resident and request must be POST
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$auth_id = $_SESSION['auth_id'];

// ✅ Get the corresponding resident_id and name from auth_id
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM residents WHERE auth_id = ? LIMIT 1");
$stmt->execute([$auth_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    echo json_encode(['status' => 'error', 'message' => 'Resident profile not found']);
    exit();
}
$resident_id = $resident['id'];
$resident_name = $resident['first_name'] . ' ' . $resident['last_name'];

// ✅ Collect form inputs
$department_id = $_POST['department_id'] ?? null;
$available_date_id = $_POST['available_date_id'] ?? null;
$service_id = $_POST['service'] ?? null;
$reason = $_POST['reason'] ?? '';
$slot_period = $_POST['slot_period'] ?? null;

if (!$department_id || !$available_date_id || !$service_id || !$reason || !$slot_period) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

if (!in_array($slot_period, ['am', 'pm'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid slot period']);
    exit();
}

function generateTransactionId($pdo, $department_id) {
    $stmt = $pdo->prepare("SELECT acronym FROM departments WHERE id = ? LIMIT 1");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    $acronym = $dept && $dept['acronym'] ? strtoupper($dept['acronym']) : 'GEN';

    do {
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $transactionId = 'APPT-' . $acronym . '-' . date('Ymd') . '-' . $random;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        $exists = $stmt->fetchColumn();
    } while ($exists);

    return $transactionId;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM available_dates WHERE id = ?");
    $stmt->execute([$available_date_id]);
    $available = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$available) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Invalid available date']);
        exit();
    }

    $slot_key = $slot_period . '_slots';
    $booked_key = $slot_period . '_booked';

    if ($available[$booked_key] >= $available[$slot_key]) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No available slots for selected period']);
        exit();
    }

    $base_date = date('Y-m-d', strtotime($available['date_time']));
    $scheduled_for = $base_date . ($slot_period === 'am' ? ' 09:00:00' : ' 14:00:00');

    $stmt = $pdo->prepare("
        SELECT lp.id, lp.auth_id
        FROM lgu_personnel lp
        JOIN auth a ON lp.auth_id = a.id
        WHERE lp.department_id = ? AND a.role = 'LGU Personnel'
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$department_id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No LGU Personnel found']);
        exit();
    }

    $personnel_id = $personnel['id'];
    $personnel_auth_id = $personnel['auth_id'];

    $transactionId = generateTransactionId($pdo, $department_id);

    $stmt = $pdo->prepare("INSERT INTO appointments (
        transaction_id, resident_id, department_id, service_id, available_date_id,
        reason, status, requested_at, personnel_id, scheduled_for
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

    $stmt->execute([
        $transactionId,
        $resident_id,
        $department_id,
        $service_id,
        $available_date_id,
        $reason,
        'Pending',
        $personnel_id,
        $scheduled_for
    ]);

    $appointmentId = $pdo->lastInsertId();

    $pdo->prepare("UPDATE available_dates SET {$booked_key} = {$booked_key} + 1 WHERE id = ?")
        ->execute([$available_date_id]);

    $slot_text = strtoupper($slot_period);
    $formatted_date = date('F d, Y', strtotime($base_date));
    $notification_message = "{$resident_name}, you have successfully booked an appointment for {$formatted_date} ({$slot_text} slot)";

    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications 
        (appointment_id, resident_id, message, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    
    $notificationStmt->execute([
        $appointmentId,
        $resident_id,
        $notification_message
    ]);

    // ==================== GET ALL PERSONNEL IN THE DEPARTMENT ====================
    $allPersonnelStmt = $pdo->prepare("
        SELECT lp.id, lp.auth_id, lp.first_name, lp.last_name
        FROM lgu_personnel lp
        JOIN auth a ON lp.auth_id = a.id
        WHERE lp.department_id = ? AND a.role = 'LGU Personnel'
    ");
    $allPersonnelStmt->execute([$department_id]);
    $allPersonnel = $allPersonnelStmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== CREATE NOTIFICATIONS FOR ALL PERSONNEL ====================
    $slot_text = strtoupper($slot_period);
    $formatted_date = date('F d, Y', strtotime($base_date));
    $notification_message = "New appointment booked by {$resident_name} for {$formatted_date} ({$slot_text} slot)";

    // Insert notification for resident first
    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications 
        (appointment_id, resident_id, message, recipient_type, created_at, is_read) 
        VALUES (?, ?, ?, 'Resident', NOW(), 0)
    ");

    $notificationStmt->execute([
        $appointmentId,
        $resident_id,
        $notification_message
    ]);

    // Insert notification for EACH personnel in the department
    foreach ($allPersonnel as $personnel_member) {
        $personnelNotificationStmt = $pdo->prepare("
            INSERT INTO notifications 
            (appointment_id, personnel_id, message, recipient_type, created_at, is_read) 
            VALUES (?, ?, ?, 'Personnel', NOW(), 0)
        ");
        
        $personnelNotificationStmt->execute([
            $appointmentId,
            $personnel_member['id'],
            $notification_message
        ]);
    }

    $pdo->commit();

    // ==================== RESIDENT NOTIFICATION (EMAIL + SMS) ====================
    $notifier = new NotificationService(true, true);
    
    try {
        // FIXED: Get phone from RESIDENTS table, not auth table
        $residentQuery = "
            SELECT a.email, r.phone_number as phone 
            FROM auth a
            INNER JOIN residents r ON a.id = r.auth_id
            WHERE a.id = ?
        ";
        $residentStmt = $pdo->prepare($residentQuery);
        $residentStmt->execute([$auth_id]);
        $residentData = $residentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($residentData) {
            // Get department name
            $deptQuery = "SELECT name FROM departments WHERE id = ?";
            $deptStmt = $pdo->prepare($deptQuery);
            $deptStmt->execute([$department_id]);
            $deptData = $deptStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get service name
            $serviceQuery = "SELECT service_name FROM department_services WHERE id = ?";
            $serviceStmt = $pdo->prepare($serviceQuery);
            $serviceStmt->execute([$service_id]);
            $serviceData = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get service requirements
            $reqQuery = "SELECT requirement FROM service_requirements WHERE service_id = ?";
            $reqStmt = $pdo->prepare($reqQuery);
            $reqStmt->execute([$service_id]);
            $requirements = $reqStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Format time slot
            $timeSlot = ($slot_period === 'am') ? '8:00 AM - 11:00 AM' : '1:00 PM - 4:00 PM';
            
            // Prepare notification data
            $notificationData = [
                'email' => $residentData['email'] ?? null,
                'phone' => $residentData['phone'] ?? null,  // FIXED: Now using phone_number from residents
                'name' => $resident_name,
                'service_name' => $serviceData['service_name'] ?? 'Service',
                'date' => $formatted_date,
                'time' => $timeSlot,
                'transaction_id' => $transactionId,
                'department_name' => $deptData['name'] ?? 'Department',
                'requirements' => !empty($requirements) ? $requirements : ['Valid Government ID']
            ];
            
            // Send Email + SMS notification
            $result = $notifier->sendAppointmentConfirmation($notificationData);
            
            // Log results
            if ($result['email']) {
                error_log("✓ Resident email sent to: " . $residentData['email']);
            } else {
                error_log("✗ Resident email FAILED");
            }
            
            if ($result['sms']) {
                error_log("✓ Resident SMS sent to: " . $residentData['phone']);
            } else {
                error_log("✗ Resident SMS FAILED - Phone: " . ($residentData['phone'] ?? 'NULL'));
            }
            
            if (!empty($result['errors'])) {
                error_log("⚠ Notification errors: " . implode(', ', $result['errors']));
            }
        }
    } catch (Exception $notifyError) {
        error_log("Resident notification error: " . $notifyError->getMessage());
    }

    // ==================== PERSONNEL NOTIFICATION (EMAIL + SMS) ====================
    try {
        // FIXED: Get phone from auth table OR lgu_personnel table
        $personnelQuery = "
            SELECT a.email, a.phone,
                   lp.first_name, lp.last_name
            FROM auth a
            INNER JOIN lgu_personnel lp ON a.id = lp.auth_id
            WHERE a.id = ?
        ";
        $personnelStmt = $pdo->prepare($personnelQuery);
        $personnelStmt->execute([$personnel_auth_id]);
        $personnelAuthData = $personnelStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($personnelAuthData) {
            $personnel_full_name = $personnelAuthData['first_name'] . ' ' . $personnelAuthData['last_name'];
            
            // Get department, service, requirements (same as resident)
            $deptQuery = "SELECT name FROM departments WHERE id = ?";
            $deptStmt = $pdo->prepare($deptQuery);
            $deptStmt->execute([$department_id]);
            $deptData = $deptStmt->fetch(PDO::FETCH_ASSOC);
            
            $serviceQuery = "SELECT service_name FROM department_services WHERE id = ?";
            $serviceStmt = $pdo->prepare($serviceQuery);
            $serviceStmt->execute([$service_id]);
            $serviceData = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            
            $reqQuery = "SELECT requirement FROM service_requirements WHERE service_id = ?";
            $reqStmt = $pdo->prepare($reqQuery);
            $reqStmt->execute([$service_id]);
            $requirements = $reqStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $timeSlot = ($slot_period === 'am') ? '8:00 AM - 11:00 AM' : '1:00 PM - 4:00 PM';
            
            $personnelNotificationData = [
                'personnel_email' => $personnelAuthData['email'] ?? null,
                'personnel_phone' => $personnelAuthData['phone'] ?? null,
                'personnel_name' => $personnel_full_name,
                'resident_name' => $resident_name,
                'service_name' => $serviceData['service_name'] ?? 'Service',
                'date' => $formatted_date,
                'time' => $timeSlot,
                'transaction_id' => $transactionId,
                'department_name' => $deptData['name'] ?? 'Department',
                'reason' => $reason,
                'requirements' => !empty($requirements) ? $requirements : ['Valid Government ID']
            ];
            
            $personnelResult = $notifier->sendPersonnelAppointmentNotification($personnelNotificationData);
            
            if ($personnelResult['email']) {
                error_log("✓ Personnel email sent to: " . $personnelAuthData['email']);
            } else {
                error_log("✗ Personnel email FAILED");
            }
            
            if ($personnelResult['sms']) {
                error_log("✓ Personnel SMS sent to: " . ($personnelAuthData['phone'] ?? 'NULL'));
            } else {
                error_log("✗ Personnel SMS FAILED - Phone: " . ($personnelAuthData['phone'] ?? 'NULL'));
            }
            
            if (!empty($personnelResult['errors'])) {
                error_log("⚠ Personnel errors: " . implode(', ', $personnelResult['errors']));
            }
        }
    } catch (Exception $personnelNotifyError) {
        error_log("Personnel notification error: " . $personnelNotifyError->getMessage());
    }

    echo json_encode([
        'status' => 'success',
        'appointment_id' => $appointmentId,
        'transaction_id' => $transactionId,
        'message' => 'Appointment booked successfully! Check your email and phone for confirmation.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Appointment booking error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>