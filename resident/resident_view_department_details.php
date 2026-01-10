    <?php
    require '../conn.php';

    if (!isset($_GET['id'])) {
        die("No department selected.");
    }

    $id = intval($_GET['id']);

    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        die("Department not found.");
    }

    $serviceStmt = $pdo->prepare("SELECT * FROM department_services WHERE department_id = ?");
    $serviceStmt->execute([$id]);
    $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

    $requirementsByService = [];
    if ($services) {
        $serviceIds = array_column($services, 'id');
        $in = str_repeat('?,', count($serviceIds) - 1) . '?';
        $reqStmt = $pdo->prepare("SELECT * FROM service_requirements WHERE service_id IN ($in)");
        $reqStmt->execute($serviceIds);
        $requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($requirements as $req) {
            $requirementsByService[$req['service_id']][] = $req['requirement'];
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($department['acronym'] ?: $department['name']) ?> - Department Details</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
        <style>
            body {
                background-color: #f9fafc;
                font-family: "Segoe UI", Tahoma, sans-serif;
            }

            .page-header {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                color: white;
                padding: 1rem;
                border-radius: 1rem;
                margin-bottom: 1rem;
            }

            .page-header h2 {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }

            .page-header h2 i {
                font-size: 1.75rem;
                flex-shrink: 0;
            }

            .page-header .lead {
                font-size: 1rem;
            }

            @media (max-width: 767px) {
                .page-header {
                    flex-direction: column;
                    align-items: flex-start !important;
                }

                .page-header h2 {
                    font-size: 1.25rem;
                }

                .page-header h2 i {
                    font-size: 1.5rem;
                }

                .page-header .lead {
                    font-size: 0.9rem;
                }

                .page-header .btn {
                    margin-top: 1rem;
                    width: 100%;
                    justify-content: center;
                }
            }

            @media (min-width: 768px) {
                .page-header {
                    padding: 1.2rem;
                }

                .page-header h2 {
                    font-size: 1.75rem;
                }

                .page-header h2 i {
                    font-size: 2rem;
                }
            }

            .service-card {
                border: none;
                border-radius: 1rem;
                transition: all 0.3s ease;
                cursor: pointer;
                background: #ffffff;
            }

            .service-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
            }

            .service-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
                box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
                flex-shrink: 0;
            }

            @media (min-width: 576px) {
                .service-icon {
                    width: 45px;
                    height: 45px;
                    font-size: 1.5rem;
                }
            }

            .service-card .card-title {
                font-size: 1rem;
                line-height: 1.3;
            }

            @media (min-width: 768px) {
                .service-card .card-title {
                    font-size: 1.1rem;
                }
            }

            .modal-content {
                border: none;
                border-radius: 1rem;
                overflow: hidden;
            }

            .modal-header {
                border-bottom: none;
                padding: 1.5rem 2rem;
                position: relative;
            }

            .modal-header::before {
                content: '';
                position: absolute;
                bottom: 0;
                left: 2rem;
                right: 2rem;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            }

            .modal-body {
                padding: 2rem;
            }

            .modal-footer {
                border-top: none;
                padding: 1.5rem 2rem;
                background-color: #f8f9fa;
            }
            #serviceModal .modal-dialog {
                max-width: 700px;
            }

            @media (min-width: 992px) {
                #serviceModal .modal-dialog {
                    max-width: 900px;
                }
            }
            #serviceModal .modal-header {
                background: linear-gradient(135deg, #2c3e50, #3498db);
            }

            #serviceModal .modal-body {
                padding: 2rem;
            }

            #serviceModal .requirements-list li {
                padding: 0.75rem 0;
                border-bottom: 1px solid #e9ecef;
                font-size: 0.9rem;
                text-align: left;
            }

            #serviceModal .requirements-list li:last-child {
                border-bottom: none;
            }

            #appointmentModal .modal-dialog {
                max-width: 800px;
            }

            #appointmentModal .modal-header {
                background: linear-gradient(135deg, #2c3e50, #3498db);
            }

            #appointmentModal .form-section {
                background: #ffffff;
                border-radius: 0.75rem;
                padding: 1.25rem;
                margin-bottom: 1.25rem;
                border: 1px solid #e9ecef;
            }

            #appointmentModal .section-title {
                color: #2c3e50;
                font-weight: 600;
                font-size: 0.95rem;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            #appointmentModal .section-title i {
                color: #3498db;
                font-size: 1.1rem;
                flex-shrink: 0;
            }

            #appointmentModal .form-control,
            #appointmentModal .form-control-file {
                border-radius: 0.5rem;
                border: 1px solid #dee2e6;
                padding: 0.75rem 1rem;
                transition: all 0.3s ease;
            }

            #appointmentModal .form-control:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            }

            #appointmentModal label {
                color: #2c3e50;
                font-weight: 500;
                margin-bottom: 0.5rem;
            }

            .btn-submit-appointment {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                border: none;
                color: white;
                font-weight: 600;
                padding: 0.95rem 2rem;
                border-radius: 0.75rem;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(52, 152, 219, 0.25);
            }

            .btn-submit-appointment:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(52, 152, 219, 0.35);
                color: white;
            }

            .btn-submit-appointment i {
                font-size: 1.2rem;
                vertical-align: middle;
            }

            .calendar-container {
                background: #ffffff;
                border-radius: 0.75rem;
                padding: 0.5rem;
                border: 1px solid #e9ecef;
                max-width: 100%;
                margin: 0 auto;
            }

            .calendar-nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.6rem;
                padding: 0.4rem 0.5rem;
                background: linear-gradient(135deg, #2c3e50, #3498db);
                border-radius: 0.5rem;
                color: white;
                gap: 0.4rem;
                flex-wrap: nowrap;
            }

            .calendar-nav button {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                padding: 0.3rem 0.4rem;
                border-radius: 0.4rem;
                transition: all 0.3s ease;
                font-size: 0.7rem;
                white-space: nowrap;
                display: flex;
                align-items: center;
                gap: 0.2rem;
                line-height: 1;
                flex-shrink: 0;
            }

            .calendar-nav button:hover:not(:disabled) {
                background: rgba(255, 255, 255, 0.3);
            }

            .calendar-nav button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .calendar-nav button i {
                font-size: 0.85rem;
                display: flex;
                align-items: center;
            }

            .calendar-nav button span {
                display: none;
            }

            #calendar-header {
                font-weight: 600;
                font-size: 0.8rem;
                text-align: center;
                flex: 1;
                min-width: 0;
            }

            #calendar {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 2px;
                margin-top: 0.5rem;
                max-width: 100%;
                width: 100%;
            }

            .calendar-day {
                aspect-ratio: 1;
                min-height: 35px;
                padding: 2px;
                border: 1px solid #e9ecef;
                border-radius: 0.3rem;
                font-size: 0.65rem;
                background-color: #f8f9fa;
                text-align: center;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 1px;
                position: relative;
            }

            .calendar-day.available {
                background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
                border-color: #4caf50;
                cursor: pointer;
            }

            .calendar-day.available:hover {
                transform: scale(1.05);
                box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
                z-index: 1;
            }

            .calendar-day.unavailable {
                background: #f5f5f5;
                border-color: #e0e0e0;
                color: #9e9e9e;
                cursor: not-allowed;
                opacity: 0.6;
            }

            .calendar-day.selected {
                background: linear-gradient(135deg, #3498db, #2c3e50) !important;
                color: white;
                font-weight: bold;
                border-color: #2c3e50;
                box-shadow: 0 2px 8px rgba(44, 62, 80, 0.4);
                z-index: 2;
            }

            .calendar-day-header {
                font-weight: 600;
                text-align: center;
                padding: 0.3rem 0.1rem;
                background: linear-gradient(135deg, #2c3e50, #3498db);
                color: white;
                border-radius: 0.3rem;
                font-size: 0.6rem;
                aspect-ratio: 1;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .calendar-day .day-number {
                font-weight: 600;
                font-size: 0.75rem;
                line-height: 1;
            }

            .calendar-day .badge {
                font-size: 0.45rem;
                padding: 1px 2px;
                line-height: 1;
                white-space: nowrap;
            }

            #slotSelector {
                margin-top: 0.8rem;
            }

            #slotSelector .form-check {
                padding: 0.75rem;
                margin-bottom: 0.6rem;
                background: linear-gradient(135deg, #f8f9fa, #ffffff);
                border-radius: 0.6rem;
                border: 2px solid #e9ecef;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
            }

            #slotSelector .form-check:hover:not(.disabled) {
                border-color: #3498db;
                background: linear-gradient(135deg, #e3f2fd, #f8f9fa);
                transform: translateX(2px);
            }

            #slotSelector .form-check-input {
                margin-top: 0.1rem;
                flex-shrink: 0;
            }

            #slotSelector .form-check-input:checked ~ .form-check-label {
                color: #2c3e50;
            }

            #slotSelector .form-check-input:disabled ~ .form-check-label {
                color: #9e9e9e;
                opacity: 0.6;
                cursor: not-allowed;
            }

            #slotSelector .form-check.disabled {
                background: #f5f5f5;
                border-color: #e0e0e0;
                cursor: not-allowed;
                opacity: 0.6;
            }

            #slotSelector .form-check.disabled:hover {
                transform: none;
                border-color: #e0e0e0;
                background: #f5f5f5;
            }

            #slotSelector .form-check-input:checked ~ .form-check-label::after {
                content: '✓';
                position: absolute;
                right: 0.75rem;
                top: 50%;
                transform: translateY(-50%);
                color: #4caf50;
                font-size: 1rem;
                font-weight: bold;
            }

            #slotSelector .form-check-label {
                width: 100%;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                position: relative;
                padding-right: 2rem;
            }

            #slotSelector .form-check-label strong {
                color: #2c3e50;
                font-size: 0.85rem;
                margin-bottom: 0.2rem;
                display: flex;
                align-items: center;
                gap: 0.3rem;
                line-height: 1.2;
            }

            #slotSelector .form-check-label strong i {
                font-size: 0.95rem;
                display: flex;
                align-items: center;
                flex-shrink: 0;
            }

            #slotSelector .form-check-label small {
                color: #6c757d;
                font-size: 0.7rem;
            }

            @media (max-width: 479px) {
                .calendar-container {
                    padding: 0.4rem;
                }

                .calendar-nav {
                    padding: 0.35rem 0.4rem;
                }

                .calendar-nav button {
                    padding: 0.25rem 0.35rem;
                    font-size: 0.65rem;
                }

                .calendar-nav button i {
                    font-size: 0.8rem;
                }

                #calendar-header {
                    font-size: 0.75rem;
                }

                .calendar-day {
                    min-height: 33px;
                    font-size: 0.6rem;
                }

                .calendar-day .day-number {
                    font-size: 0.7rem;
                }

                .calendar-day .badge {
                    font-size: 0.4rem;
                    padding: 0.5px 1.5px;
                }

                .calendar-day-header {
                    font-size: 0.55rem;
                    padding: 0.25rem 0.05rem;
                }

                #slotSelector .form-check {
                    padding: 0.65rem;
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.8rem;
                }

                #slotSelector .form-check-label strong i {
                    font-size: 0.9rem;
                }

                #slotSelector .form-check-label small {
                    font-size: 0.65rem;
                }
            }

            @media (min-width: 480px) {
                .calendar-nav button span {
                    display: inline;
                }

                .calendar-day {
                    min-height: 38px;
                }

                .calendar-day .day-number {
                    font-size: 0.75rem;
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.87rem;
                }
            }

            @media (min-width: 576px) {
                .calendar-container {
                    padding: 0.7rem;
                    max-width: 480px;
                }

                .calendar-nav {
                    padding: 0.5rem 0.6rem;
                    margin-bottom: 0.7rem;
                }

                .calendar-nav button {
                    padding: 0.35rem 0.5rem;
                    font-size: 0.75rem;
                }

                .calendar-nav button i {
                    font-size: 0.9rem;
                }

                #calendar-header {
                    font-size: 0.85rem;
                }

                .calendar-day {
                    min-height: 42px;
                    font-size: 0.7rem;
                    padding: 3px;
                    gap: 2px;
                }

                .calendar-day .day-number {
                    font-size: 0.8rem;
                }

                .calendar-day .badge {
                    font-size: 0.5rem;
                    padding: 1px 3px;
                }

                .calendar-day-header {
                    font-size: 0.65rem;
                    padding: 0.35rem 0.15rem;
                }

                #calendar {
                    gap: 3px;
                }

                #slotSelector {
                    margin-top: 0.9rem;
                }

                #slotSelector .form-check {
                    padding: 0.8rem;
                    margin-bottom: 0.65rem;
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.88rem;
                }

                #slotSelector .form-check-label strong i {
                    font-size: 1rem;
                }

                #slotSelector .form-check-label small {
                    font-size: 0.72rem;
                }
            }

            @media (min-width: 768px) {
                .calendar-container {
                    padding: 0.8rem;
                    max-width: 520px;
                }

                .calendar-nav {
                    padding: 0.55rem 0.7rem;
                }

                .calendar-nav button {
                    padding: 0.38rem 0.6rem;
                    font-size: 0.78rem;
                }

                #calendar-header {
                    font-size: 0.9rem;
                }

                .calendar-day {
                    min-height: 46px;
                    font-size: 0.72rem;
                }

                .calendar-day .day-number {
                    font-size: 0.85rem;
                }

                .calendar-day .badge {
                    font-size: 0.52rem;
                }

                .calendar-day .badge {
                    font-size: 0.52rem;
                }

                .calendar-day-header {
                    font-size: 0.68rem;
                }

                #slotSelector .form-check {
                    padding: 0.85rem;
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.9rem;
                }
            }

            @media (min-width: 992px) {
                .calendar-container {
                    padding: 0.9rem;
                    max-width: 560px;
                }

                .calendar-nav {
                    padding: 0.6rem 0.8rem;
                    margin-bottom: 0.75rem;
                }

                .calendar-nav button {
                    padding: 0.4rem 0.7rem;
                    font-size: 0.8rem;
                }

                .calendar-nav button i {
                    font-size: 0.95rem;
                }

                #calendar-header {
                    font-size: 0.95rem;
                }

                .calendar-day {
                    min-height: 50px;
                    font-size: 0.75rem;
                    padding: 4px;
                }

                .calendar-day .day-number {
                    font-size: 0.9rem;
                }

                .calendar-day .badge {
                    font-size: 0.55rem;
                    padding: 2px 4px;
                }

                .calendar-day-header {
                    font-size: 0.7rem;
                    padding: 0.4rem 0.2rem;
                }

                #calendar {
                    gap: 4px;
                }

                #slotSelector {
                    margin-top: 1rem;
                }

                #slotSelector .form-check {
                    padding: 0.9rem;
                    margin-bottom: 0.7rem;
                }

                #slotSelector .form-check:hover:not(.disabled) {
                    transform: translateX(4px);
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.92rem;
                }

                #slotSelector .form-check-label strong i {
                    font-size: 1.05rem;
                }

                #slotSelector .form-check-label small {
                    font-size: 0.75rem;
                }
            }

            @media (min-width: 1200px) {
                .calendar-container {
                    padding: 1rem;
                    max-width: 600px;
                }

                .calendar-day {
                    min-height: 55px;
                    font-size: 0.78rem;
                }

                .calendar-day .day-number {
                    font-size: 0.95rem;
                }

                .calendar-day .badge {
                    font-size: 0.58rem;
                }

                .calendar-day-header {
                    font-size: 0.72rem;
                }

                #calendar {
                    gap: 5px;
                }

                #slotSelector .form-check {
                    padding: 0.95rem;
                }

                #slotSelector .form-check-label strong {
                    font-size: 0.95rem;
                }

                #slotSelector .form-check-input:checked ~ .form-check-label::after {
                    font-size: 1.1rem;
                }

                #appointmentModal .form-section {
                    padding: 1.45rem;
                }

                .btn-submit-appointment {
                    padding: 1rem 2rem;
                }
            }

            @media (min-width: 1200px) {
                .calendar-day {
                    min-height: 58px;
                }
            }

            select.form-control {
                height: auto !important;
                padding-top: 0.6rem !important;
                padding-bottom: 0.6rem !important;
                line-height: 1.4 !important;
                font-size: 1rem;
                background-color: #fff;
                color: #2c3e50;
                appearance: none;
            }

            @media (max-width: 768px) {
                .modal-dialog {
                    margin: 1rem auto;
                    max-width: 95% !important;
                    width: auto !important;
                }

                .modal-content {
                    border-radius: 1rem;
                    overflow: hidden;
                }

                .modal-header {
                    flex-wrap: wrap;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem 1rem;
                    text-align: center;
                }

                .modal-title {
                    font-size: 1rem;
                    text-align: center;
                    flex: 1 1 100%;
                }

                .modal-body {
                    max-height: 70vh;
                    overflow-y: auto;
                    padding: 1rem;
                }

                .modal-footer {
                    flex-wrap: wrap;
                    justify-content: center;
                }
            }

            @media (max-width: 480px) {
                .modal-dialog {
                    margin: 0.5rem;
                    width: 100% !important;
                }

                .modal-body {
                    max-height: 68vh;
                    overflow-y: auto;
                }

                .modal-header .btn-close {
                    font-size: 1.2rem;
                }
            }

            #appointmentModal .modal-dialog {
                max-width: 600px;
                height: 90vh;
                display: flex;
                align-items: center;
            }

            #appointmentModal .modal-content {
                height: 100%;
                display: flex;
                flex-direction: column;
                border-radius: 1rem;
                overflow: hidden;
            }

            #appointmentModal .modal-body {
                flex: 1 1 auto;
                overflow-y: auto;
                padding: 1rem 1.5rem;
            }

            @media (max-width: 768px) {
                #appointmentModal .modal-dialog {
                    margin: 0.5rem;
                    max-width: calc(100% - 1rem);
                    height: auto;
                    max-height: 95vh;
                }

                #appointmentModal .modal-content {
                    height: auto;
                    max-height: 95vh;
                }

                #appointmentModal .modal-body {
                    padding: 1rem;
                    max-height: calc(95vh - 120px);
                    overflow-y: auto;
                }

                #appointmentModal .form-section {
                    padding: 1rem;
                    margin-bottom: 1rem;
                }

                #appointmentModal .section-title {
                    font-size: 0.9rem;
                }

                .btn-submit-appointment {
                    padding: 0.8rem 1.2rem;
                    font-size: 0.9rem;
                }
            }

            @media (min-width: 769px) {
                #appointmentModal .modal-dialog {
                    max-width: 700px;
                    margin: 1.75rem auto;
                }

                #appointmentModal .modal-body {
                    padding: 1.5rem 2rem;
                }
            }

            @media (min-width: 992px) {
                #appointmentModal .modal-dialog {
                    max-width: 800px;
                }

                #appointmentModal .modal-body {
                    padding: 2rem;
                }
            }

            #transactionModal .modal-header {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                border-bottom: none;
                padding: 1.5rem 2rem;
            }

            #transactionModal .modal-header .close {
                color: white;
                opacity: 0.8;
            }

            #transactionModal .modal-header .close:hover {
                opacity: 1;
            }

            .transaction-number-box {
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                border-radius: 0.75rem;
                padding: 1.5rem;
                margin: 1.5rem 0;
                border: 2px solid #3498db;
            }

            .alert-reminder {
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 0.75rem;
                padding: 1rem;
                margin-top: 1.5rem;
            }

            .no-capture-capturing {
                display: none !important;
            }

            #downloadTransactionBtn {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                border: none;
                transition: all 0.3s ease;
            }

            #downloadTransactionBtn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(52, 152, 219, 0.35);
            }

            #downloadTransactionBtn i {
                font-size: 1.1rem;
                vertical-align: middle;
            }
        </style>
    </head>
    <body>
        <div id="content-area">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="bx bx-building-house"></i> 
                        <?= htmlspecialchars($department['name']) ?>
                    </h2>
                    <p class="lead mb-0">
                        <?= $department['description'] ? htmlspecialchars($department['description']) : '<em>No description provided.</em>' ?>
                    </p>
                </div>

                <a href="#" class="btn btn-light text-primary d-inline-flex align-items-center px-4 py-2 rounded-pill shadow-sm" id="backButton">
                    <i class="bx bx-arrow-back mr-2"></i> Back
                </a>
            </div>
            
            <div class="container">
                <h5 class="mb-3 text-secondary">
                    <i class="bx bx-cog"></i> Services & Requirements
                </h5>
                
                <?php if ($services): ?>
                    <div class="row">
                        <?php foreach ($services as $svc): ?>
                        <div class="col-md-6 mb-4">
                        <div class="card service-card shadow-sm h-100"
                            data-id="<?= $svc['id'] ?>"
                            data-name="<?= htmlspecialchars($svc['service_name']) ?>"
                            data-req="<?= htmlspecialchars(json_encode($requirementsByService[$svc['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                            data-desc="<?= htmlspecialchars($svc['description'] ?? '') ?>"> <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="service-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center mr-3">
                                            <i class="bx bx-envelope"></i>
                                        </div>
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($svc['service_name']) ?></h5>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        <?= !empty($svc['description']) ? htmlspecialchars($svc['description']) : 'Click to view details & requirements' ?>
                                    </p>
                                </div>
                                    <div class="text-right mt-3">
                                        <span class="badge badge-pill badge-primary px-3 py-2">
                                            <i class="bx bx-info-circle mr-1"></i> View
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border text-muted">No services available for this department.</div>
                <?php endif; ?>
            </div>

            <div class="modal fade" id="serviceModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow-lg">
                        <div class="modal-header text-white">
                            <h5 class="modal-title d-flex align-items-center">
                                <i class="bx bx-info-circle mr-2"></i> Service Details
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <h5 id="serviceName" style="color: #2c3e50; font-weight: 600;"></h5>
                            
                            <p id="serviceDescription" class="text-muted mt-2" style="font-size: 0.95rem;"></p>
                            
                            <div class="requirements-list mt-3">
                                <h6 class="d-flex align-items-center" style="color: #2c3e50; margin-bottom: 1rem;">
                                    <i class="bx bx-list-ul mr-2"></i>Requirements:
                                </h6>
                                <ul id="serviceRequirements" class="list-unstyled mb-0"></ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn text-white d-inline-flex align-items-center" id="bookNowBtn" style="background: linear-gradient(135deg, #2c3e50, #3498db);">
                                <i class="bx bx-calendar mr-2"></i> Book Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="appointmentModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <form id="appointment-form" class="modal-content shadow-lg" enctype="multipart/form-data">
                        <div class="modal-header text-white">
                            <h5 class="modal-title d-flex align-items-center">
                                <i class="bx bx-calendar-check mr-2"></i> Book Appointment
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="department_id" id="department_id" value="<?= $department['id'] ?>">
                            <input type="hidden" name="available_date_id" id="available_date_id">

                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bx bx-briefcase"></i>
                                    Service Information
                                </div>
                                <div class="form-group mb-0">
                                    <label for="service">Select Service</label>
                                    <select class="form-control" name="service" id="service" required></select>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bx bx-calendar"></i>
                                    Select Date & Time
                                </div>
                                
                                <div class="calendar-container">
                                    <div class="calendar-nav">
                                        <button type="button" id="prevMonth">
                                            <i class="bx bx-chevron-left"></i> <span>Prev</span>
                                        </button>
                                        <strong id="calendar-header"></strong>
                                        <button type="button" id="nextMonth">
                                            <span>Next</span> <i class="bx bx-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div id="calendar"></div>
                                </div>
                                
                                <div id="slotSelector"></div>
                            </div>

                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bx bx-message-square-detail"></i>
                                    Purpose of Visit
                                </div>
                                <div class="form-group mb-0">
                                    <textarea class="form-control" name="reason" id="reason" rows="3" placeholder="Please describe the purpose of your appointment..." required></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-submit-appointment btn-block text-white d-inline-flex align-items-center justify-content-center">
                                Confirm Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true" data-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" id="transactionModalContent">
                        
                        <div class="modal-header text-white justify-content-center">
                            <div class="d-flex align-items-center">
                                <img src="../assets/images/logo.png" alt="LGU Logo" style="height: 40px; margin-right: 10px;">
                                <h4 class="font-weight-bold mb-0">LGU Quick Appoint</h4>
                            </div>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="bx bx-check-circle d-inline-flex" style="font-size: 4rem; color: #4caf50;"></i>
                            </div>
                            
                            <h5 class="font-weight-semibold text-dark mb-2">Appointment Confirmed!</h5>
                            
                            <div class="transaction-number-box">
                                <p class="mb-2 text-muted">Transaction Number</p>
                                <h3 id="transactionNumber" class="font-weight-bold">-</h3>
                            </div>

                            <div class="alert-reminder">
                                <div class="d-flex align-items-start">
                                    <i class="bx bx-info-circle" style="color: #ff9800; font-size: 1.5rem; margin-right: 12px; margin-top: 2px;"></i>
                                    <div class="text-left">
                                        <strong style="color: #856404;">Important Reminder</strong>
                                        <p class="mb-0 mt-1 small" style="color: #856404;">
                                            Please save or screenshot your transaction number. Bring all required documents and present this slip to the assigned personnel.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 justify-content-center no-capture">
                            <button id="downloadTransactionBtn" class="btn text-white px-4 py-2 font-weight-semibold d-inline-flex align-items-center" style="background: linear-gradient(135deg, #2c3e50, #3498db); border-radius: 0.75rem;">
                                <i class="bx bx-download mr-2"></i> Download Slip
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

    <script>
    var currentMonth = new Date().getMonth() + 1;
    var currentYear = new Date().getFullYear();
    var currentDepartmentId = null;
    var isSubmitting = false;

    function openBooking(departmentId) {
        console.log('Opening booking for department:', departmentId);
        currentMonth = new Date().getMonth() + 1;
        currentYear = new Date().getFullYear();
        currentDepartmentId = departmentId;
        $('#appointmentModal').modal('show');
        $('#department_id').val(departmentId);
        $('#available_date_id').val('');
        $('#calendar').empty();
        $('#slotSelector').empty();
        $.get('get_services_by_department.php', { department_id: departmentId }, function(data) {
            $('#service').html(data);
        });
        loadCalendar(departmentId);
    }

    function loadCalendar(departmentId) {
        console.log('Loading calendar for:', departmentId, currentMonth, currentYear);
        $.ajax({
            url: 'get_available_dates.php',
            type: 'GET',
            data: { 
                department_id: departmentId, 
                month: currentMonth, 
                year: currentYear
            },
            cache: false,
            success: function(data) {
                try {
                    const availableDates = JSON.parse(data);
                    generateCalendar(availableDates);
                } catch(e) {
                    console.error('Error parsing dates:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
            }
        });
    }

    function generateCalendar(availableDates) {
        const calendar = $('#calendar');
        calendar.empty();

        const firstDay = new Date(currentYear, currentMonth - 1, 1);
        const lastDate = new Date(currentYear, currentMonth, 0).getDate();
        const startDay = firstDay.getDay();
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        $('#calendar-header').text(firstDay.toLocaleString('default', { month: 'long' }) + ' ' + currentYear);

        days.forEach(day => calendar.append(`<div class='calendar-day-header'>${day}</div>`));

        for (let i = 0; i < startDay; i++) {
            calendar.append('<div class="calendar-day"></div>');
        }

        for (let day = 1; day <= lastDate; day++) {
            const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const currentDate = new Date(currentYear, currentMonth - 1, day);
            currentDate.setHours(0, 0, 0, 0);
            
            const isPastDate = currentDate < today;
            const data = availableDates[dateStr] || null;
            
            const amSlots = data ? (data.am_slots - data.am_booked) : 0;
            const pmSlots = data ? (data.pm_slots - data.pm_booked) : 0;
            const hasAvailableSlots = amSlots > 0 || pmSlots > 0;
            
            let dayClass = 'calendar-day';
            if (isPastDate || (data && !hasAvailableSlots)) {
                dayClass += ' unavailable';
            } else if (data && hasAvailableSlots) {
                dayClass += ' available';
            }
            
            const div = $(`<div class='${dayClass}' data-date='${dateStr}'></div>`);
            div.append(`<div class='day-number'>${day}</div>`);

            if (data) {
                div.append(`<div class='badge ${amSlots > 0 && !isPastDate ? 'badge-success' : 'badge-secondary'}'>AM: ${isPastDate ? 0 : amSlots}</div>`);
                div.append(`<div class='badge ${pmSlots > 0 && !isPastDate ? 'badge-info' : 'badge-secondary'}'>PM: ${isPastDate ? 0 : pmSlots}</div>`);

                if (!isPastDate && hasAvailableSlots) {
                    div.click(function() {
                        $('.calendar-day').removeClass('selected');
                        $(this).addClass('selected');
                        renderSlots(data, amSlots, pmSlots);
                    });
                } else if (isPastDate) {
                    div.attr('title', 'This date has passed').css('cursor', 'not-allowed');
                }
            }
            calendar.append(div);
        }
    }

    function renderSlots(data, amSlots, pmSlots) {
        let slotsHtml = '';
        let amDisabled = amSlots <= 0 ? 'disabled' : '';
        let amSubtext = amSlots > 0 ? `${amSlots} slots available` : 'No slots available';
        slotsHtml += `
            <div class="form-check ${amDisabled}">
                <input class="form-check-input" type="radio" name="slot_period" id="slot_am_${data.id}" value="am" data-id="${data.id}" ${amDisabled ? 'disabled' : 'required'}>
                <label class="form-check-label" for="slot_am_${data.id}">
                    <strong><i class="bx bx-sun"></i>Morning Slot (AM)</strong>
                    <small class="d-block mt-1">${amSubtext}</small>
                </label>
            </div>`;

        let pmDisabled = pmSlots <= 0 ? 'disabled' : '';
        let pmSubtext = pmSlots > 0 ? `${pmSlots} slots available` : 'No slots available';
        slotsHtml += `
            <div class="form-check ${pmDisabled}">
                <input class="form-check-input" type="radio" name="slot_period" id="slot_pm_${data.id}" value="pm" data-id="${data.id}" ${pmDisabled ? 'disabled' : ''}>
                <label class="form-check-label" for="slot_pm_${data.id}">
                    <strong><i class="bx bx-moon"></i>Afternoon Slot (PM)</strong>
                    <small class="d-block mt-1">${pmSubtext}</small>
                </label>
            </div>`;

        $('#slotSelector').html(slotsHtml);
        
        $('input[name="slot_period"]:not(:disabled)').on('change', function() {
            $('#available_date_id').val($(this).data('id'));
        });
    }

    $(document).off('click', '#prevMonth').on('click', '#prevMonth', function(e) {
        e.preventDefault();
        if ($(this).prop('disabled')) return;
        $(this).prop('disabled', true);
        currentMonth--; 
        if (currentMonth < 1) { currentMonth = 12; currentYear--; }
        const deptId = $('#department_id').val(); 
        loadCalendar(deptId);
        setTimeout(() => $(this).prop('disabled', false), 500);
    });

    $(document).off('click', '#nextMonth').on('click', '#nextMonth', function(e) {
        e.preventDefault();
        if ($(this).prop('disabled')) return;
        $(this).prop('disabled', true);
        currentMonth++; 
        if (currentMonth > 12) { currentMonth = 1; currentYear++; }
        const deptId = $('#department_id').val();
        loadCalendar(deptId);
        setTimeout(() => $(this).prop('disabled', false), 500);
    });

    $(document).off('click', '#backButton').on('click', '#backButton', function(e) {
        e.preventDefault();
        $.ajax({
            url: "residents_view_departments.php",
            type: "GET",
            success: function(response) {
                $("#content-area").html(response);
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
            },
            error: function() {
                alert("Failed to load departments list.");
            }
        });
    });

    $(document).off("click", ".service-card").on("click", ".service-card", function() {
    const serviceId = $(this).data("id");
    const serviceName = $(this).data("name");
    
    // 1. Robust data retrieval
    let requirements = $(this).data("req");

    // 2. Safety check: If it's a string (parsing failed), try to parse it manually
    if (typeof requirements === 'string') {
        try {
            requirements = JSON.parse(requirements);
        } catch (e) {
            console.error("Failed to parse requirements JSON", e);
            requirements = [];
        }
    }

    // 3. Final Safety: Ensure it is an array before looping
    if (!Array.isArray(requirements)) {
        requirements = [];
    }

    const description = $(this).data("desc");

    $("#serviceName").text(serviceName);

    if (description) {
        $("#serviceDescription").text(description);
    } else {
        $("#serviceDescription").text("No description available.");
    }

    $("#serviceRequirements").empty();

    // 4. Now this is safe to run
    if (requirements.length > 0) {
        requirements.forEach(r => {
            $("#serviceRequirements").append(
                `<li class="mb-2 d-flex align-items-start"><i class="bx bx-check-circle text-success mr-2" style="margin-top: 2px; font-size: 1rem;"></i><span>${r}</span></li>`
            );
        });
    } else {
        $("#serviceRequirements").html('<p class="text-muted">No specific requirements listed.</p>');
    }

    $("#bookNowBtn").data("service-id", serviceId);
    $("#serviceModal").modal("show");
});

    $(document).off('click', '#bookNowBtn').on('click', '#bookNowBtn', function(e) {
        const serviceId = $(this).data("service-id");
        $("#serviceModal").modal("hide");
        const deptId = $('#department_id').val(); 
        console.log('Book now clicked - DOM Department ID:', deptId);
        openBooking(deptId);
        setTimeout(() => {
            $("#service").val(serviceId);
        }, 500);
    });

    $(document).off('submit', '#appointment-form').on('submit', '#appointment-form', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (isSubmitting) {
            console.log('Already submitting, ignoring duplicate submission');
            return false;
        }
        
        const selectedSlot = $('input[name="slot_period"]:checked');
        if (!selectedSlot.length) {
            alert('⚠️ Please select a date and time slot.');
            return false;
        }

        isSubmitting = true;
        
        const formData = new FormData(this);
        formData.append('slot_period', selectedSlot.val());

        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bx bx-loader-circle bx-spin mr-2"></i> Processing...');

        $.ajax({
            url: 'residents_submit_appointment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                isSubmitting = false;
                submitBtn.prop('disabled', false).html(originalText);
                
                if (res.status === 'success') {
                    $('#appointmentModal').modal('hide');
                    $('#appointment-form')[0].reset();
                    $('#slotSelector').empty();
                    $('#calendar').empty();
                    
                    const transactionNum = res.transaction_id || res.transaction_number || 'N/A';
                    $('#transactionNumber').text(transactionNum);
                    
                    setTimeout(function() { $('#transactionModal').modal('show'); }, 500);
                    
                    if (currentDepartmentId) loadCalendar(currentDepartmentId);
                } else {
                    alert('❌ ' + (res.message || 'Booking failed.'));
                }
            },
            error: function(xhr, status, error) {
                isSubmitting = false;
                submitBtn.prop('disabled', false).html(originalText);
                alert('❌ Failed to book appointment. Check connection.');
                console.error('Submission error:', xhr.responseText);
            }
        });
        
        return false;
    });

    $(document).off('click', '#downloadTransactionBtn').on('click', '#downloadTransactionBtn', function(e) {
        const modalContent = document.getElementById("transactionModalContent");
        const footer = modalContent.querySelector(".no-capture");
        footer.classList.add("no-capture-capturing");

        html2canvas(modalContent, { scale: 2 }).then((canvas) => {
            footer.classList.remove("no-capture-capturing");
            const link = document.createElement("a");
            link.download = "appointment_slip_" + $('#transactionNumber').text() + ".png";
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
    });

    $('#appointmentModal').on('hidden.bs.modal', function() {
        isSubmitting = false;
        $('#appointment-form')[0].reset();
        $('#slotSelector').empty();
        $('.calendar-day').removeClass('selected');
    });
    </script>
    </body>
    </html>
