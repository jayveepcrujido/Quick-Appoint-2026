<?php 
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Resident') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['appointment_id']) || empty($_GET['appointment_id'])) {
    echo json_encode(['error' => 'No appointment ID provided']);
    exit();
}

try {
    include '../conn.php';
    $authId = $_SESSION['auth_id'];
    $appointmentId = intval($_GET['appointment_id']);

    $stmt = $pdo->prepare("SELECT id FROM residents WHERE auth_id = ? LIMIT 1");
    $stmt->execute([$authId]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        echo json_encode(['error' => 'Resident profile not found']);
        exit();
    }
    
    $residentId = $resident['id'];

    $query = "
        SELECT 
            a.id, 
            a.transaction_id, 
            a.scheduled_for,
            d.name AS department_name, 
            s.service_name,
            af.sqd0_answer,
            af.sqd1_answer,
            af.sqd2_answer,
            af.sqd4_answer,
            af.sqd5_answer,
            af.sqd6_answer,
            af.sqd7_answer,
            af.sqd8_answer,
            af.suggestions,
            af.submitted_at
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        JOIN department_services s ON a.service_id = s.id
        LEFT JOIN appointment_feedback af ON a.id = af.appointment_id
        WHERE a.id = :appointment_id 
        AND a.resident_id = :resident_id 
        AND a.has_sent_feedback = 1
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'appointment_id' => $appointmentId,
        'resident_id' => $residentId
    ]);
    
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        echo json_encode(['error' => 'Appointment not found or feedback not submitted']);
        exit();
    }

    $questions = [
        'sqd0' => 'I am satisfied with the service I availed.',
        'sqd1' => 'I spent a reasonable amount of time for my transaction.',
        'sqd2' => 'The office followed the transaction\'s requirements and steps based on the information provided.',
        'sqd4' => 'The steps I needed to do for my transaction were easy and simple.',
        'sqd5' => 'I easily found information about my transaction from the office or its website.',
        'sqd6' => 'I paid a reasonable amount of fees for my transaction.',
        'sqd7' => 'I feel the office was fair to everyone, or "walang palakasan," during my transaction.',
        'sqd8' => 'I was treated courteously by the staff, and (if asked for help) the staff was helpful.'
    ];

    $ratingValues = [
        'Strongly Agree' => 5,
        'Agree' => 4,
        'Neither Agree nor Disagree' => 3,
        'Disagree' => 2,
        'Strongly Disagree' => 1
    ];

    $ratings = [];
    $responses = [];
    
    foreach ($questions as $key => $question) {
        $answerKey = $key . '_answer';
        if (!empty($appointment[$answerKey])) {
            $answer = $appointment[$answerKey];
            $responses[] = [
                'question' => $question,
                'answer' => $answer
            ];
            if (isset($ratingValues[$answer])) {
                $ratings[] = $ratingValues[$answer];
            }
        }
    }

    $averageRating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;

    $ratingCounts = [
        'Strongly Agree' => 0,
        'Agree' => 0,
        'Neither' => 0,
        'Disagree' => 0,
        'Strongly Disagree' => 0
    ];

    foreach ($ratings as $rating) {
        if ($rating == 5) $ratingCounts['Strongly Agree']++;
        elseif ($rating == 4) $ratingCounts['Agree']++;
        elseif ($rating == 3) $ratingCounts['Neither']++;
        elseif ($rating == 2) $ratingCounts['Disagree']++;
        elseif ($rating == 1) $ratingCounts['Strongly Disagree']++;
    }

    $satisfactionLevel = '';
    $satisfactionColor = '';

    if ($averageRating >= 4.5) {
        $satisfactionLevel = 'Excellent';
        $satisfactionColor = '#27ae60';
    } elseif ($averageRating >= 3.5) {
        $satisfactionLevel = 'Good';
        $satisfactionColor = '#3498db';
    } elseif ($averageRating >= 2.5) {
        $satisfactionLevel = 'Fair';
        $satisfactionColor = '#f39c12';
    } elseif ($averageRating >= 1.5) {
        $satisfactionLevel = 'Poor';
        $satisfactionColor = '#e67e22';
    } else {
        $satisfactionLevel = 'Very Poor';
        $satisfactionColor = '#e74c3c';
    }

    $data = [
        'appointment' => [
            'id' => $appointment['id'],
            'transaction_id' => $appointment['transaction_id'],
            'scheduled_for' => $appointment['scheduled_for'],
            'department_name' => $appointment['department_name'],
            'service_name' => $appointment['service_name'],
            'suggestions' => $appointment['suggestions'],
            'submitted_at' => $appointment['submitted_at']
        ],
        'responses' => $responses,
        'averageRating' => round($averageRating, 1),
        'satisfactionLevel' => $satisfactionLevel,
        'satisfactionColor' => $satisfactionColor,
        'ratingCounts' => $ratingCounts,
        'totalResponses' => count($ratings),
        'positiveCount' => $ratingCounts['Strongly Agree'] + $ratingCounts['Agree'],
        'negativeCount' => $ratingCounts['Disagree'] + $ratingCounts['Strongly Disagree']
    ];

    echo json_encode($data);

} catch (Exception $e) {
    error_log('Feedback Response Error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while fetching feedback data']);
}
?>