<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

include '../conn.php';

if (isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get the auth_id
        $stmt = $pdo->prepare("SELECT auth_id FROM residents WHERE id = ?");
        $stmt->execute([$deleteId]);
        $authId = $stmt->fetchColumn();

        if ($authId) {
            $pdo->prepare("
                DELETE af FROM appointment_feedback af
                INNER JOIN appointments a ON af.appointment_id = a.id
                WHERE a.resident_id = ?
            ")->execute([$deleteId]);
            
            $pdo->prepare("DELETE FROM reschedule_requests WHERE resident_id = ?")->execute([$deleteId]);
            
            $pdo->prepare("DELETE FROM notifications WHERE resident_id = ?")->execute([$deleteId]);
            
            $pdo->prepare("DELETE FROM appointments WHERE resident_id = ?")->execute([$deleteId]);
            
            $pdo->prepare("DELETE FROM residents WHERE id = ?")->execute([$deleteId]);
            
            $pdo->prepare("DELETE FROM auth WHERE id = ?")->execute([$authId]);
            
            $pdo->commit();
            $_SESSION['delete_success'] = true;
        } else {
            $pdo->rollBack();
            $_SESSION['delete_error'] = true;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['delete_error'] = true;
        error_log("Delete error: " . $e->getMessage());
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_POST['view_id'])) {
    $viewId = $_POST['view_id'];
    
    $stmt = $pdo->prepare("
        SELECT r.*, a.email
        FROM residents r
        JOIN auth a ON r.auth_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$viewId]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resident) {
        echo json_encode(['status' => 'success', 'data' => $resident]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Resident not found']);
    }
    exit();
}

$showSuccessAlert = false;
$showErrorAlert = false;
if (isset($_SESSION['delete_success'])) {
    $showSuccessAlert = true;
    unset($_SESSION['delete_success']);
}
if (isset($_SESSION['delete_error'])) {
    $showErrorAlert = true;
    unset($_SESSION['delete_error']);
}

$stmt = $pdo->prepare("
    SELECT r.id, r.first_name, r.middle_name, r.last_name, r.created_at, a.email
    FROM residents r
    JOIN auth a ON r.auth_id = a.id
    WHERE a.role = 'Resident'
    ORDER BY r.created_at DESC
");
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalResidents = count($residents);

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM residents r
    WHERE MONTH(r.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(r.created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$newThisMonth = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM residents r
    JOIN auth a ON r.auth_id = a.id
    WHERE a.role = 'Resident'
");
$stmt->execute();
$genderData = $stmt->fetch(PDO::FETCH_ASSOC);
$maleCount = $genderData['male_count'];
$femaleCount = $genderData['female_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resident Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(78, 115, 223, 0.3);
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 1.75rem;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
            font-size: 1rem;
        }

        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-card .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 0;
            flex-shrink: 0;
        }

        .stat-card .stat-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
            line-height: 1;
        }

        .stat-card .stat-label {
            color: #718096;
            font-size: 0.9rem;
            margin: 0;
            font-weight: 500;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .search-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            border-radius: 10px;
            padding-left: 2.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 10;
        }

        /* Modern Table Styles */
        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem;
            white-space: nowrap;
        }

        .table-modern tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-modern tbody tr:hover {
            background: #f7fafc;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table-modern tbody td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }

        .user-details small {
            color: #718096;
        }

        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-email {
            background: #e6f2ff;
            color: #2b6cb0;
        }

        .badge-date {
            background: #f0f4ff;
            color: #5a67d8;
        }

        .btn-delete {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 62, 62, 0.4);
            color: white;
        }

        .btn-delete:active {
            transform: translateY(0);
        }

        .btn-view {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
            color: white;
        }

        .btn-view:active {
            transform: translateY(0);
        }

        .me-1 {
            margin-right: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #a0aec0;
        }

        /* Mobile Card View */
        #mobileCards {
            display: none;
            padding: 1rem;
        }

        .mobile-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #4e73df;
            transition: all 0.3s ease;
        }

        .mobile-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .mobile-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .mobile-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .mobile-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-card-label {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .mobile-card-value {
            color: #2d3748;
            font-weight: 500;
            text-align: right;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h4 {
                font-size: 1.5rem;
            }

            .stat-card {
                min-width: calc(50% - 0.5rem);
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.25rem;
            }

            .page-header h4 {
                font-size: 1.25rem;
            }

            .page-header p {
                font-size: 0.875rem;
            }

            .stat-card {
                min-width: 100%;
            }

            .table-responsive {
                display: none;
            }

            #mobileCards {
                display: block;
            }

            .search-container {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 0.5rem;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .mobile-card {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .main-card, .stat-card, .mobile-card {
            animation: fadeIn 0.5s ease-in;
        }
        .resident-profile {
            padding: 1rem 0;
        }

        .profile-header {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .info-section {
            background: #f8f9fc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .section-title {
            color: #2d3748;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #2d3748;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        .id-image-container {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .id-image-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .id-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }

        .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            color: white;
            padding: 0.75rem;
            font-size: 0.875rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .id-image-container:hover .image-overlay {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 1.5rem 1rem;
            }
            
            .info-section {
                padding: 1rem;
            }
            
            .id-image {
                height: 150px;
            }
        }
    </style>
</head>
<body class="p-3 p-md-4">
    <?php if ($showSuccessAlert): ?>
<div class="alert alert-success alert-dismissible fade show position-fixed" 
     style="top: 20px; right: 20px; z-index: 9999; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);">
    <i class='bx bx-check-circle'></i> Resident account deleted successfully!
    <button type="button" class="close" data-dismiss="alert">
        <span>&times;</span>
    </button>
</div>
<script>
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 3000);
</script>
<?php endif; ?>

<?php if ($showErrorAlert): ?>
<div class="alert alert-danger alert-dismissible fade show position-fixed" 
     style="top: 20px; right: 20px; z-index: 9999; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);">
    <i class='bx bx-error'></i> Failed to delete resident account!
    <button type="button" class="close" data-dismiss="alert">
        <span>&times;</span>
    </button>
</div>
<script>
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 3000);
</script>
<?php endif; ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h4><i class='bx bx-group'></i> Manage Resident Accounts</h4>
        <p>View and manage all registered resident accounts in the system</p>
    </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(13, 148, 244, 1); background: linear-gradient(135deg, #0D92F4, #27548A);">
                    <i class='bx bx-user' style="color:white;"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $totalResidents ?></h3>
                    <p class="stat-label">Total Residents</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); background: linear-gradient(135deg, #0df44bff, #408a27ff);">
                    <i class='bx bx-user-plus'style="color:white;"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $newThisMonth ?></h3>
                    <p class="stat-label">New This Month</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); background: linear-gradient(135deg, #ca0df4ff, #7e278aff);">
                    <i class='bx bx-male-sign'style="color:white;"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $maleCount ?> / <?= $femaleCount ?></h3>
                    <p class="stat-label">Male / Female</p>
                </div>
            </div>
        </div>

    <!-- Search Box -->
    <div class="search-container">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="main-card">
        <div class="card-header-custom">
            <h5><i class='bx bx-table'></i> Resident Accounts List</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($residents)): ?>
                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="table table-modern" id="residentsTable">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Email Address</th>
                                <th>Date Registered</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $resident): 
                                $fullName = trim($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']);
                                $initials = strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1));
                            ?>
                                <tr class="resident-row">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar"><?= $initials ?></div>
                                            <div class="user-details">
                                                <h6><?= htmlspecialchars($fullName) ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-email">
                                            <i class='bx bx-envelope'></i>
                                            <?= htmlspecialchars($resident['email']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-date">
                                            <i class='bx bx-calendar'></i>
                                            <?= date('M j, Y', strtotime($resident['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-view btn-sm me-1" onclick="viewResident(<?= $resident['id'] ?>)">
                                            <i class='bx bx-show'></i> View
                                        </button>
                                        <button class="btn btn-delete btn-sm" onclick="deleteResident(<?= $resident['id'] ?>, '<?= htmlspecialchars($fullName) ?>')">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div id="mobileCards">
                    <?php foreach ($residents as $resident): 
                        $fullName = trim($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name']);
                        $initials = strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1));
                    ?>
                        <div class="mobile-card resident-card" data-name="<?= htmlspecialchars(strtolower($fullName)) ?>" data-email="<?= htmlspecialchars(strtolower($resident['email'])) ?>">
                            <div class="mobile-card-header">
                                <div class="user-avatar"><?= $initials ?></div>
                                <div class="user-details flex-grow-1">
                                    <h6><?= htmlspecialchars($fullName) ?></h6>
                                </div>
                            </div>
                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">
                                        <i class='bx bx-envelope'></i> Email
                                    </span>
                                    <span class="mobile-card-value">
                                        <?= htmlspecialchars($resident['email']) ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">
                                        <i class='bx bx-calendar'></i> Registered
                                    </span>
                                    <span class="mobile-card-value">
                                        <?= date('M j, Y', strtotime($resident['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row mt-2">
                                    <button class="btn btn-view btn-block mb-2" onclick="viewResident(<?= $resident['id'] ?>)">
                                        <i class='bx bx-show'></i> View Details
                                    </button>
                                    <button class="btn btn-delete btn-block" onclick="deleteResident(<?= $resident['id'] ?>, '<?= htmlspecialchars($fullName) ?>')">
                                        <i class='bx bx-trash'></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-user-x'></i>
                    <h5>No Resident Accounts Found</h5>
                    <p>There are currently no registered resident accounts in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class='bx bx-trash'></i> Confirm Deletion
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center py-4">
                <i class='bx bx-error-circle' style="font-size: 4rem; color: #e53e3e;"></i>
                <h5 class="mt-3 mb-2">Are you sure?</h5>
                <p class="text-muted mb-0">You are about to delete the account for:</p>
                <p class="font-weight-bold" id="residentName"></p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">
                    <i class='bx bx-x'></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDelete" style="border-radius: 8px;">
                    <i class='bx bx-trash'></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Resident Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class='bx bx-user-circle'></i> Resident Information
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="residentDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">
                    <i class='bx bx-x'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteId = null;

function deleteResident(id, name) {
    deleteId = id;
    $('#residentName').text(name);
    $('#deleteModal').modal('show');
}

$('#confirmDelete').off('click').on('click', function() {
    if (!deleteId) return;
    
    // Create and submit a hidden form
    const form = $('<form>', {
        'method': 'POST',
        'action': 'admin_manage_residents_accounts.php'
    });
    
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'delete_id',
        'value': deleteId
    }));
    
    $('body').append(form);
    form.submit();
});

$('#confirmDelete').click(function() {
    if (deleteId) {
        $.post('admin_manage_residents_accounts.php', { delete_id: deleteId }, function(response) {
            if (response.status === 'success') {
                $('#deleteModal').modal('hide');
                
                $('body').append(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" 
                         style="top: 20px; right: 20px; z-index: 9999; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);">
                        <i class='bx bx-check-circle'></i> Resident account deleted successfully!
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                `);
                
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                alert('Failed to delete resident account.');
            }
        }, 'json').fail(function() {
            alert('An error occurred. Please try again.');
        });
    }
});

$('#searchInput').on('keyup', function() {
    const value = $(this).val().toLowerCase().trim();
    
    if (value === '') {
        $('#residentsTable tbody tr').show();
        $('.mobile-card').show();
    } else {
        $('#residentsTable tbody tr').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
        $('.mobile-card').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
    }
});
function viewResident(id) {
    $('#viewModal').modal('show');
    $('#residentDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    `);
    
    $.post('admin_manage_residents_accounts.php', { view_id: id }, function(response) {
        if (response.status === 'success') {
            const r = response.data;
            const fullName = `${r.first_name} ${r.middle_name || ''} ${r.last_name}`.trim();
            const initials = (r.first_name.charAt(0) + r.last_name.charAt(0)).toUpperCase();
            
            $('#residentDetails').html(`
                <div class="resident-profile">
                    <!-- Header Section -->
                    <div class="profile-header text-center mb-4">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            ${initials}
                        </div>
                        <h4 class="mb-1">${fullName}</h4>
                        <p class="text-muted mb-2">
                            <i class='bx bx-envelope'></i> ${r.email}
                        </p>
                    </div>

                    <!-- Personal Information -->
                    <div class="info-section mb-4">
                        <h5 class="section-title">
                            <i class='bx bx-user'></i> Personal Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Birthday</label>
                                <p class="info-value">${new Date(r.birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Age</label>
                                <p class="info-value">${r.age || 'N/A'} years old</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Sex</label>
                                <p class="info-value">${r.sex}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Civil Status</label>
                                <p class="info-value">${r.civil_status}</p>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="info-label">Address</label>
                                <p class="info-value">${r.address}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Phone Number</label>
                                <p class="info-value">${r.phone_number}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Valid ID Type</label>
                                <p class="info-value">${r.valid_id_type}</p>
                            </div>
                        </div>
                    </div>

                    <!-- ID Images -->
                    ${r.id_front_image || r.id_back_image || r.selfie_with_id_image ? `
                    <div class="info-section">
                        <h5 class="section-title">
                            <i class='bx bx-id-card'></i> Identification Documents
                        </h5>
                        <div class="row">
                            ${r.id_front_image ? `
                            <div class="col-md-4 mb-3">
                                <label class="info-label">ID Front</label>
                                <div class="id-image-container">
                                    <img src="../${r.id_front_image}" alt="ID Front" class="img-fluid id-image" onclick="openImageModal(this.src)">
                                    <div class="image-overlay">
                                        <i class='bx bx-zoom-in'></i> Click to enlarge
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            ${r.id_back_image ? `
                            <div class="col-md-4 mb-3">
                                <label class="info-label">ID Back</label>
                                <div class="id-image-container">
                                    <img src="../${r.id_back_image}" alt="ID Back" class="img-fluid id-image" onclick="openImageModal(this.src)">
                                    <div class="image-overlay">
                                        <i class='bx bx-zoom-in'></i> Click to enlarge
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            ${r.selfie_with_id_image ? `
                            <div class="col-md-4 mb-3">
                                <label class="info-label">Selfie with ID</label>
                                <div class="id-image-container">
                                    <img src="../${r.selfie_with_id_image}" alt="Selfie with ID" class="img-fluid id-image" onclick="openImageModal(this.src)">
                                    <div class="image-overlay">
                                        <i class='bx bx-zoom-in'></i> Click to enlarge
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}

                    <!-- Registration Info -->
                    <div class="info-section mt-4">
                        <h5 class="section-title">
                            <i class='bx bx-info-circle'></i> Registration Details
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Registered On</label>
                                <p class="info-value">${new Date(r.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="info-label">Last Updated</label>
                                <p class="info-value">${new Date(r.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#residentDetails').html(`
                <div class="alert alert-danger">
                    <i class='bx bx-error'></i> Failed to load resident information.
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#residentDetails').html(`
            <div class="alert alert-danger">
                <i class='bx bx-error'></i> An error occurred while loading resident information.
            </div>
        `);
    });
}

function openImageModal(src) {
    const imageModal = `
        <div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content bg-transparent border-0">
                    <div class="modal-body p-0 text-center">
                        <button type="button" class="close text-white position-absolute" style="right: 10px; top: 10px; z-index: 1000; font-size: 2rem; opacity: 1;" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                        <img src="${src}" class="img-fluid" style="max-height: 90vh; border-radius: 10px;">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(imageModal);
    $('#imageModal').modal('show');
    $('#imageModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}
</script>
</body>
</html>