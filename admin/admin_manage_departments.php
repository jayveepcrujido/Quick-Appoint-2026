<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../conn.php';

$stmt = $pdo->query("SELECT d.*, 
                            GROUP_CONCAT(s.service_name ORDER BY s.id SEPARATOR ', ') AS services
                     FROM departments d
                     LEFT JOIN department_services s ON d.id = s.department_id
                     GROUP BY d.id ORDER BY d.created_at DESC");
$departments = $stmt->fetchAll();

$serviceMap = [];
$stmt = $pdo->query("SELECT ds.id AS service_id, ds.department_id, ds.service_name, sr.requirement
                     FROM department_services ds
                     LEFT JOIN service_requirements sr ON ds.id = sr.service_id
                     ORDER BY ds.department_id, ds.id");

while ($row = $stmt->fetch()) {
    $deptId = $row['department_id'];
    $serviceId = $row['service_id'];

    if (!isset($serviceMap[$deptId][$serviceId])) {
        $serviceMap[$deptId][$serviceId] = [
            'name' => $row['service_name'],
            'requirements' => []
        ];
    }

    if ($row['requirement']) {
        $serviceMap[$deptId][$serviceId]['requirements'][] = $row['requirement'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --primary-light: #3498db;
            --primary-gradient: linear-gradient(135deg, #0D92F4, #27548A);
            --secondary-gradient: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            --success-gradient: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            --warning-gradient: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            --danger-color: #e74c3c;
            --card-shadow: 0 8px 25px rgba(44, 62, 80, 0.12);
            --hover-shadow: 0 12px 35px rgba(44, 62, 80, 0.18);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 3rem;
        }

        /* Header Styles */
        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 20px rgba(44, 62, 80, 0.15);
            position: relative;
            overflow: hidden;
            border-radius: 20px;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.6;
        }

        .page-header > div {
            position: relative;
            z-index: 2; /* Increased just to be safe */
        }

        .page-title {
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
        }

        .page-title i {
            font-size: 2.5rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .btn-add-dept {
            background: white;
            color: var(--primary-dark);
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
            font-size: 0.95rem;
        }

        .btn-add-dept:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
            color: var(--primary-dark);
        }

        .btn-add-dept i {
            font-size: 1.25rem;
        }

        /* Stats Cards - Side-by-Side Layout */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            height: auto;
            display: flex;
            align-items: center;
            gap: 1rem; /* spacing between icon and number */
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            opacity: 0.05;
            border-radius: 0 16px 0 100%;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--hover-shadow);
        }

        /* Icon beside number */
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.primary {
            background: var(--primary-gradient);
        }

        .stat-icon.secondary {
            background: var(--secondary-gradient);
        }

        /* Number and label container */
        .stat-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
            line-height: 1.1;
        }

        .stat-label {
            margin: 0.25rem 0 0;
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.9rem;
        }


        /* Search Section */
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2.5rem;
        }

        .search-wrapper {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 1.25rem;
            z-index: 2;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1.5rem 1rem 3.75rem;
            border: 2px solid #ecf0f1;
            border-radius: 50px;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            background: white;
        }

        .btn-clear {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .btn-clear:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
            color: white;
        }

        /* Department Cards */
        .dept-card-wrapper {
            margin-bottom: 1.5rem;
        }

        .dept-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            cursor: pointer;
            height: 100%;
            border: 2px solid transparent;
        }

        .dept-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary-light);
        }

        .dept-card-header {
            background: var(--primary-gradient);
            padding: 1.25rem 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .dept-card-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 120px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .dept-acronym {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .dept-acronym i {
            color: white !important;
            font-size: 1.75rem;
        }

        .dept-name {
            color: white;
            font-size: 0.875rem;
            opacity: 0.95;
            margin: 0.5rem 0 0;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .dept-card-body {
            padding: 1.25rem 1.5rem;
            min-height: 100px;
        }

        .service-badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            background: linear-gradient(135deg, #ecf0f1 0%, #d5dbdb 100%);
            color: var(--primary-dark);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 0.2rem;
            transition: var(--transition);
            border: 1px solid #d5dbdb;
        }

        .service-badge:hover {
            background: var(--secondary-gradient);
            color: white;
            transform: scale(1.08);
            border-color: transparent;
        }

        .empty-services {
            color: #95a5a6;
            font-style: italic;
            text-align: center;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .empty-services i {
            font-size: 1.25rem;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            border: none;
            padding: 1.75rem 2rem;
            position: relative;
        }

        .modal-header.primary {
            background: var(--primary-gradient);
            color: white;
        }

        .modal-header.success {
            background: var(--success-gradient);
            color: white;
        }

        .modal-header.warning {
            background: var(--warning-gradient);
            color: white;
        }

        .modal-header h5 {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 1.25rem;
            margin: 0;
        }

        .modal-header h5 i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .modal-header .close {
            color: white;
            opacity: 1;
            text-shadow: none;
            font-size: 1.75rem;
            font-weight: 300;
            transition: var(--transition);
            padding: 0;
            margin: -0.5rem -0.5rem -0.5rem auto;
        }

        .modal-header .close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .info-label i {
            color: var(--primary-light);
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .service-item {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-light);
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            transition: var(--transition);
        }

        .service-item:hover {
            background: #ecf0f1;
            transform: translateX(5px);
        }

        .service-name {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-name i {
            color: var(--primary-light);
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirement-list li {
            color: #5a6c7d;
            margin-bottom: 0.625rem;
            display: flex;
            align-items: start;
            gap: 0.625rem;
            padding-left: 0;
            font-size: 0.875rem;
        }

        .requirement-list li i {
            color: var(--primary-light);
            margin-top: 0.125rem;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.625rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .form-label i {
            color: var(--primary-light);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .form-control-custom {
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .form-control-custom:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .service-edit-block {
            background: #f8f9fa;
            border: 2px solid #ecf0f1;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .service-edit-block:hover {
            border-color: var(--primary-light);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        /* Service Group Styling for Add Modal */
        .service-group {
            background: #f8f9fa;
            border: 2px solid #ecf0f1;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .service-group:hover {
            border-color: var(--primary-light);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        .service-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.875rem;
        }

        .service-group-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-group-title i {
            color: var(--primary-light);
            font-size: 1rem;
        }

        .requirements-container {
            margin-top: 0.875rem;
            padding-top: 0.875rem;
            border-top: 1px solid #dee2e6;
        }

        .requirement-input-group {
            margin-bottom: 0.625rem;
        }

        /* Button Styles */
        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
            color: white;
        }

        .btn-success-custom {
            background: var(--success-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
            color: white;
        }

        .btn-warning-custom {
            background: var(--warning-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-warning-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
            color: white;
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-danger-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
            color: white;
        }

        .btn-outline-custom {
            background: transparent;
            border: 2px solid var(--primary-light);
            color: var(--primary-light);
            padding: 0.65rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-outline-custom:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
        }

        .btn-sm-custom {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        .remove-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 0.875rem;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
        }

        .remove-btn:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .remove-btn i {
            font-size: 1rem;
        }

        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            display: flex;
            gap: 0.875rem;
            justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.25rem;
            opacity: 0.5;
            display: block;
        }

        .empty-state h4 {
            color: var(--primary-dark);
            margin-bottom: 0.625rem;
            font-size: 1.125rem;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Responsive Design */
@media (max-width: 992px) {
    .page-header {
        padding: 1.75rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }

    .page-title i {
        font-size: 2rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .dept-acronym {
        font-size: 1.35rem;
    }
    
    .dept-acronym i {
        color: white !important;
        font-size: 1.5rem;
    }
    
    .modal-dialog {
        max-width: 900px; /* Wider modal */
    }
    
    .service-badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.75rem;
    }
}

@media (max-width: 768px) {
    body {
        padding-bottom: 2rem;
    }
    
    .page-header {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 15px;
    }

    .page-title {
        font-size: 1.5rem;
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-title i {
        font-size: 1.75rem;
    }

    .btn-add-dept {
        width: 100%;
        justify-content: center;
        margin-top: 1rem;
        padding: 0.75rem 1.5rem;
    }

    /* Stats Overview */
    .stats-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-icon {
        width: 55px;
        height: 55px;
        font-size: 1.5rem;
    }
    
    .stat-number {
        font-size: 1.75rem;
    }
    
    .stat-label {
        font-size: 0.85rem;
    }

    /* Search Section */
    .search-section {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 15px;
    }
    
    .search-input {
        padding: 0.875rem 1.25rem 0.875rem 3.5rem;
        font-size: 0.95rem;
    }
    
    .search-icon {
        left: 1.25rem;
        font-size: 1.125rem;
    }

    .btn-clear {
        margin-top: 1rem;
        padding: 0.875rem 1.5rem;
    }

    /* Department Cards */
    .dept-card-wrapper {
        margin-bottom: 1.25rem;
    }

    .dept-card-header {
        padding: 1.25rem;
    }

    .dept-acronym {
        font-size: 1.35rem;
    }
    
    .dept-acronym i {
        font-size: 1.5rem;
    }
    
    .dept-name {
        font-size: 0.85rem;
    }

    .dept-card-body {
        padding: 1.25rem;
        min-height: auto;
    }
    
    .service-badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.75rem;
        margin: 0.15rem;
    }

    /* Modal Adjustments */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-content {
        border-radius: 20px;
    }

    .modal-body {
        padding: 1.75rem;
        max-height: 60vh;
    }

    .modal-header {
        padding: 1.5rem;
        border-radius: 20px 20px 0 0;
    }
    
    .modal-header h5 {
        font-size: 1.25rem;
    }
    
    .modal-header h5 i {
        font-size: 1.5rem;
    }

    .modal-footer {
        padding: 1.25rem 1.5rem;
        flex-direction: column;
        gap: 0.75rem;
    }

    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Form Elements */
    .form-label {
        font-size: 0.95rem;
    }
    
    .form-control-custom {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    .service-edit-block {
        padding: 1.25rem;
        margin-bottom: 1rem;
        border-radius: 12px;
    }
    
    .input-group {
        display: flex;
        align-items: stretch;
    }

    .input-group .form-control {
        flex: 1;
    }

    .input-group-append {
        margin-left: -1px;
        display: flex;
    }

    .input-group-append .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    
    .info-label {
        font-size: 1rem;
    }

    .service-name {
        font-size: 1rem;
    }
    
    .service-item {
        padding: 1.25rem;
    }
    
    .service-item .remove-btn {
        opacity: 1;
        min-width: 32px;
        height: 32px;
        padding: 0.5rem;
    }
    
    /* Button Sizes */
    .btn-primary-custom,
    .btn-success-custom,
    .btn-warning-custom,
    .btn-danger-custom {
        padding: 0.75rem 1.75rem;
        font-size: 0.95rem;
    }
    
    .btn-outline-custom {
        padding: 0.65rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .btn-sm-custom {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    /* Empty State */
    .empty-state {
        padding: 3rem 1.5rem;
    }
    
    .empty-state i {
        font-size: 4rem;
    }
    
    .empty-state h4 {
        font-size: 1.25rem;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        border-radius: 12px;
    }

    .page-title {
        font-size: 1.25rem;
        gap: 0.5rem;
    }

    .page-title i {
        font-size: 1.5rem;
    }
    
    .btn-add-dept {
        font-size: 0.9rem;
        padding: 0.7rem 1.25rem;
    }

    /* Stats */
    .stat-card {
        padding: 1rem;
        flex-direction: row;
        gap: 0.75rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.35rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }

    /* Search */
    .search-section {
        padding: 1.25rem;
        border-radius: 12px;
    }
    
    .search-input {
        padding: 0.75rem 1rem 0.75rem 3rem;
        font-size: 0.9rem;
        border-radius: 25px;
    }
    
    .search-icon {
        left: 1rem;
        font-size: 1rem;
    }
    
    .btn-clear {
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        border-radius: 25px;
    }

    /* Department Cards */
    .dept-card {
        border-radius: 12px;
    }
    
    .dept-card-header {
        padding: 1rem;
    }
    
    .dept-acronym {
        font-size: 1.25rem;
    }

    .dept-acronym i {
        font-size: 1.35rem;
        color: white !important;
    }
    
    .dept-name {
        font-size: 0.8rem;
    }

    .dept-card-body {
        padding: 1rem;
    }

    .service-badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.65rem;
        margin: 0.125rem;
    }
    
    .empty-services {
        font-size: 0.85rem;
        padding: 1rem;
    }

    /* Modals */
    .modal-dialog {
        margin: 0.25rem;
        max-width: calc(100% - 0.5rem);
    }
    
    .modal-content {
        border-radius: 16px;
    }
    
    .modal-header {
        padding: 1.25rem;
        border-radius: 16px 16px 0 0;
    }

    .modal-header h5 {
        font-size: 1.125rem;
    }
    
    .modal-header h5 i {
        font-size: 1.35rem;
    }
    
    .modal-header .close {
        font-size: 1.75rem;
    }

    .modal-body {
        padding: 1.25rem;
        max-height: 55vh;
    }
    
    .modal-footer {
        padding: 1rem 1.25rem;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-label {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .form-label i {
        font-size: 1rem;
    }
    
    .form-control-custom {
        padding: 0.65rem 0.875rem;
        font-size: 0.875rem;
        border-radius: 10px;
    }
    
    .service-edit-block {
        padding: 1rem;
        border-radius: 10px;
    }

    /* Service Items */
    .info-label {
        font-size: 0.95rem;
    }
    
    .info-label i {
        font-size: 1.125rem;
    }

    .service-item {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 10px;
    }
    
    .service-item .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .service-item .remove-btn {
        opacity: 1;
        align-self: flex-end;
        margin-top: 0.75rem;
    }

    .service-name {
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
    }
    
    .service-name i {
        font-size: 1.125rem;
    }
    
    .requirement-list li {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    /* Buttons */
    .btn-primary-custom,
    .btn-success-custom,
    .btn-warning-custom,
    .btn-danger-custom {
        padding: 0.65rem 1.5rem;
        font-size: 0.9rem;
        border-radius: 25px;
    }
    
    .btn-outline-custom {
        padding: 0.6rem 1.25rem;
        font-size: 0.875rem;
        border-radius: 25px;
    }
    
    .btn-sm-custom {
        padding: 0.5rem 0.875rem;
        font-size: 0.8rem;
    }
    
    .remove-btn {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
    }
    
    .remove-btn i {
        font-size: 1rem;
    }

    /* Empty State */
    .empty-state {
        padding: 2.5rem 1rem;
    }
    
    .empty-state i {
        font-size: 3.5rem;
        margin-bottom: 1rem;
    }
    
    .empty-state h4 {
        font-size: 1.125rem;
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        font-size: 0.9rem;
    }
    
    /* Notification */
    .custom-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
    }
    
    .custom-notification i {
        font-size: 1.35rem !important;
    }
}
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Tablet Landscape (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
    .dept-card-wrapper {
        flex: 0 0 50%;
        max-width: 50%;
    }

    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-dialog {
        max-width: 90%;
        margin: 1.75rem auto;
    }
    
    .service-item .remove-btn {
        opacity: 1;
    }
}

/* Small Tablets (576px - 768px) */
@media (min-width: 576px) and (max-width: 768px) {
    .dept-card-wrapper {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-dialog {
        max-width: 95%;
    }
}

/* Touch Device Optimizations */
@media (hover: none) and (pointer: coarse) {
    /* Make buttons and interactive elements easier to tap */
    .btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    .close {
        padding: 0.75rem;
        min-width: 44px;
        min-height: 44px;
    }
    
    .dept-card {
        -webkit-tap-highlight-color: rgba(13, 146, 244, 0.1);
    }
    
    /* Always show delete buttons on touch devices */
    .service-item .remove-btn {
        opacity: 1 !important;
    }
    
    /* Improve scrolling on modals */
    .modal-body {
        -webkit-overflow-scrolling: touch;
    }
    
    /* Improve form inputs on touch devices */
    input, textarea, select {
        font-size: 16px !important; /* Prevents zoom on iOS */
    }
}

/* Landscape Mobile Phones */
@media (max-width: 896px) and (orientation: landscape) {
    .modal-dialog {
        margin: 0.5rem auto;
    }
    
    .modal-body {
        max-height: 50vh;
    }
    
    .page-header {
        padding: 1rem;
    }
    
    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Very Small Devices (max-width: 360px) */
@media (max-width: 360px) {
    .page-title {
        font-size: 1.125rem;
    }
    
    .page-title i {
        font-size: 1.35rem;
    }
    
    .stat-number {
        font-size: 1.35rem;
    }
    
    .dept-acronym {
        font-size: 1.125rem;
    }
    
    .dept-acronym i {
        font-size: 1.25rem !important;
        color: white !important;
    }
    
    .service-badge {
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
    }
    
    .modal-header h5 {
        font-size: 1rem;
    }
    
    .btn-primary-custom,
    .btn-success-custom,
    .btn-warning-custom,
    .btn-danger-custom {
        padding: 0.6rem 1.25rem;
        font-size: 0.875rem;
    }
}
/* Prevent horizontal scroll on mobile */
body {
    overflow-x: hidden;
}

.container {
    padding-left: 15px;
    padding-right: 15px;
}

/* Better spacing for mobile */
@media (max-width: 768px) {
    .row {
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .col-md-6, .col-lg-4 {
        padding-left: 10px;
        padding-right: 10px;
    }
}

/* Improve modal scrolling on mobile */
.modal-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
}

/* Better tap targets for mobile */
@media (max-width: 768px) {
    .btn, button, a {
        -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
    }
}
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="page-title">
                    <i class='bx bx-buildings'></i>
                    <span>Department Management</span>
                </h3>
                <button class="btn btn-add-dept" data-toggle="modal" data-target="#addModal">
                    <i class='bx bx-plus-circle'></i>
                    Add Department
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class='bx bx-buildings'></i>
                </div>
                <h2 class="stat-number"><?= count($departments) ?></h2>
                <p class="stat-label">Total Departments</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class='bx bx-list-ul'></i>
                </div>
                <h2 class="stat-number"><?= array_sum(array_map(function($d) use ($serviceMap) { return isset($serviceMap[$d['id']]) ? count($serviceMap[$d['id']]) : 0; }, $departments)) ?></h2>
                <p class="stat-label">Total Services</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="row align-items-center">
                <div class="col-md-9 mb-3 mb-md-0">
                    <div class="search-wrapper">
                        <i class='bx bx-search search-icon'></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search departments, services, or descriptions...">
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-clear" id="clearFilters">
                        <i class='bx bx-x'></i>
                        Clear Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Department Cards -->
        <div class="row" id="departmentGrid">
            <?php foreach ($departments as $d): 
                $searchText = strtolower($d['name'] . ' ' . $d['acronym'] . ' ' . $d['description'] . ' ' . $d['services']);
                $services = array_filter(array_map('trim', explode(',', $d['services'])));
            ?>
            <div class="col-lg-4 col-md-6 dept-card-wrapper" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="dept-card" data-toggle="modal" data-target="#viewModal<?= $d['id'] ?>">
                    <div class="dept-card-header">
                        <h4 class="dept-acronym">
                            <i class='bx bx-building-house'></i>
                            <?= htmlspecialchars($d['acronym']) ?>
                        </h4>
                        <p class="dept-name"><?= htmlspecialchars($d['name']) ?></p>
                    </div>
                    <div class="dept-card-body">
                        <?php if ($services): ?>
                            <div>
                                <?php foreach (array_slice($services, 0, 3) as $s): ?>
                                    <span class="service-badge"><?= htmlspecialchars($s) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($services) > 3): ?>
                                    <span class="service-badge">+<?= count($services) - 3 ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-services">
                                <i class='bx bx-info-circle'></i>
                                No services available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- View Modal -->
            <div class="modal fade" id="viewModal<?= $d['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header primary">
                            <h5 class="modal-title">
                                <i class='bx bx-info-circle'></i>
                                <?= htmlspecialchars($d['name']) ?>
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-4">
                                <p class="info-label">
                                    <i class='bx bx-detail'></i>
                                    Description
                                </p>
                                <p><?= htmlspecialchars($d['description']) ?: 'No description provided.' ?></p>
                            </div>

                            <div>
                                <p class="info-label">
                                    <i class='bx bx-list-check'></i>
                                    Services & Requirements
                                </p>
                                    <?php if (isset($serviceMap[$d['id']])): ?>
                                        <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                            <div class="service-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="service-name">
                                                            <i class='bx bx-check-circle'></i>
                                                            <?= htmlspecialchars($svc['name']) ?>
                                                        </div>
                                                        <?php if (!empty($svc['requirements'])): ?>
                                                            <ul class="requirement-list">
                                                                <?php foreach ($svc['requirements'] as $req): ?>
                                                                    <li>
                                                                        <i class='bx bx-right-arrow-alt'></i>
                                                                        <span><?= htmlspecialchars($req) ?></span>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p class="text-muted mb-0"><em>No specific requirements</em></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button type="button" 
                                                            class="btn btn-sm remove-btn delete-service-btn ml-3" 
                                                            data-service-id="<?= $svcId ?>"
                                                            data-service-name="<?= htmlspecialchars($svc['name']) ?>"
                                                            title="Delete Service">
                                                        <i class='bx bx-trash'></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <i class='bx bx-package'></i>
                                        <h4>No Services Available</h4>
                                        <p>This department has no services listed yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                            <i class='bx bx-x'></i> Close
                        </button>
                        
                        <button type="button" class="btn btn-danger-custom delete-dept-btn" data-id="<?= $d['id'] ?>">
                            <i class='bx bx-trash'></i> Delete Department
                        </button>

                        <button class="btn btn-warning-custom edit-dept-btn" data-dept-id="<?= $d['id'] ?>">
                            <i class='bx bx-edit'></i> Edit Department
                        </button>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content edit-dept-form" method="post" action="ajax/ajax_update_department.php">
                        <div class="modal-header warning">
                            <h5 class="modal-title">
                                <i class='bx bx-edit'></i>
                                Edit Department
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="department_id" value="<?= $d['id'] ?>">

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-building'></i>
                                    Department Name
                                </label>
                                <input name="name" class="form-control form-control-custom" value="<?= htmlspecialchars($d['name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-tag'></i>
                                    Acronym
                                </label>
                                <input name="acronym" class="form-control form-control-custom" value="<?= htmlspecialchars($d['acronym']) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-detail'></i>
                                    Description
                                </label>
                                <textarea name="description" class="form-control form-control-custom" rows="3"><?= htmlspecialchars($d['description']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-list-ul'></i>
                                    Services & Requirements
                                </label>
                                <div class="service-edit-area">
                                    <?php if (isset($serviceMap[$d['id']])): ?>
                                        <?php foreach ($serviceMap[$d['id']] as $svcId => $svc): ?>
                                            <div class="service-edit-block">
                                                <input type="hidden" name="service_ids[]" value="<?= $svcId ?>">
                                                <input type="text" name="service_names[]" class="form-control form-control-custom mb-3" value="<?= htmlspecialchars($svc['name']) ?>" placeholder="Service Name" required>
                                                
                                                <div class="requirement-group">
                                                    <?php if (!empty($svc['requirements'])): ?>
                                                        <?php foreach ($svc['requirements'] as $req): ?>
                                                            <div class="input-group mb-2">
                                                                <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" value="<?= htmlspecialchars($req) ?>" placeholder="Requirement">
                                                                <div class="input-group-append">
                                                                    <button type="button" class="btn remove-btn remove-req">
                                                                        <i class='bx bx-x'></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="requirements[<?= $svcId ?>][]" class="form-control form-control-custom" placeholder="Add new requirement (optional)">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn remove-btn remove-req">
                                                                <i class='bx bx-x'></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-custom btn-sm-custom add-req">
                                                    <i class='bx bx-plus'></i>
                                                    Add Requirement
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success-custom btn-sm-custom mt-2" id="addNewServiceBtn<?= $d['id'] ?>">
                                    <i class='bx bx-plus-circle'></i>
                                    Add New Service
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                                <i class='bx bx-x'></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-warning-custom">
                                <i class='bx bx-save'></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php endforeach; ?>
        </div>

        <?php if (empty($departments)): ?>
            <div class="empty-state">
                <i class='bx bx-building'></i>
                <h4>No Departments Yet</h4>
                <p>Start by adding your first department using the button above.</p>
            </div>
        <?php endif; ?>
    </div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="addForm" class="modal-content">
            <div class="modal-header success">
                <h5 class="modal-title">
                    <i class='bx bx-plus-circle'></i>
                    Add New Department
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Department Name -->
                <div class="form-group">
                    <label class="form-label">
                        <i class='bx bx-building'></i>
                        Department Name
                    </label>
                    <input name="name" class="form-control form-control-custom" placeholder="Enter department name" required>
                </div>

                <!-- Acronym -->
                <div class="form-group">
                    <label class="form-label">
                        <i class='bx bx-tag'></i>
                        Acronym
                    </label>
                    <input name="acronym" class="form-control form-control-custom" placeholder="e.g., DOH, DILG, MSWDO">
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">
                        <i class='bx bx-detail'></i>
                        Description
                    </label>
                    <textarea name="description" class="form-control form-control-custom" rows="3" placeholder="Brief description of the department"></textarea>
                </div>

                <!-- Services Section -->
                <div class="form-group">
                    <label class="form-label">
                        <i class='bx bx-list-ul'></i>
                        Services Offered
                    </label>
                    <div id="serviceFields">
                        <!-- First Service Group -->
                        <div class="service-group">
                            <div class="service-group-header">
                                <span class="service-group-title">
                                    <i class='bx bx-briefcase'></i>
                                    Service #1
                                </span>
                                <button type="button" class="btn remove-btn removeService" style="visibility: hidden;">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                            
                            <!-- Service Name Input -->
                            <div class="mb-3">
                                <input type="text" name="services[]" class="form-control form-control-custom" placeholder="Enter service name" required>
                            </div>
                            
                            <!-- Requirements Container -->
                            <div class="requirements-container">
                                <label class="form-label mb-2" style="font-size: 0.8125rem; font-weight: 500;">
                                    <i class='bx bx-list-check'></i>
                                    Requirements (Optional)
                                </label>
                                <div class="requirement-input-group">
                                    <div class="input-group mb-2">
                                        <input type="text" name="requirements[0][]" class="form-control form-control-custom" placeholder="Enter requirement">
                                        <div class="input-group-append">
                                            <button type="button" class="btn remove-btn removeRequirement" style="visibility: hidden;">
                                                <i class='bx bx-x'></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-custom btn-sm-custom addRequirement" data-service-index="0">
                                    <i class='bx bx-plus'></i>
                                    Add Another Requirement
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add Another Service Button -->
                    <button type="button" id="addService" class="btn btn-outline-custom btn-sm-custom mt-3">
                        <i class='bx bx-plus-circle'></i>
                        Add Another Service
                    </button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-custom" data-dismiss="modal">
                    <i class='bx bx-x'></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-success-custom">
                    <i class='bx bx-check-circle'></i>
                    Add Department
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.js"></script>
<script>
(function() {
    'use strict';

    const NAMESPACE = 'deptMgmt_' + Date.now();
    console.log('Initializing department management with namespace:', NAMESPACE);

    if (window.deptMgmtCleanup) {
        console.log('Cleaning up previous instance...');
        try {
            window.deptMgmtCleanup();
        } catch (e) {
            console.error('Error during cleanup:', e);
        }
    }

    let isModalTransitioning = false;
    let isSubmitting = false;
    let serviceIndex = 0;

    function showModal(modalId) {
        if (isModalTransitioning) return;
        isModalTransitioning = true;
        
        const $modal = $('#' + modalId);
        if ($modal.length === 0) {
            console.error('Modal not found:', modalId);
            isModalTransitioning = false;
            return;
        }

        $modal.modal('show');
        
        setTimeout(() => {
            isModalTransitioning = false;
        }, 300);
    }

    function hideModal(modalId) {
        if (isModalTransitioning) return;
        isModalTransitioning = true;
        
        const $modal = $('#' + modalId);
        if ($modal.length === 0) {
            isModalTransitioning = false;
            return;
        }

        $modal.modal('hide');
        
        setTimeout(() => {
            cleanupModals();
            isModalTransitioning = false;
        }, 300);
    }

    function cleanupModals() {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css('padding-right', '');
        $('body').css('overflow', '');
    }

    function showNotification(message, type) {
        $('.custom-notification').remove();
        
        const bgColor = type === 'success' ? 'linear-gradient(135deg, #27ae60 0%, #229954 100%)' : 
                       type === 'error' ? 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)' : 
                       'linear-gradient(135deg, #0D92F4, #27548A)';
        
        const icon = type === 'success' ? 'bx-check-circle' : 
                    type === 'error' ? 'bx-error-circle' : 
                    'bx-info-circle';
        
        const notification = $(`
            <div class="custom-notification alert alert-dismissible fade show" style="
                position: fixed;
                top: 20px;
                right: 20px;
                max-width: 400px;
                z-index: 9999;
                background: ${bgColor};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                gap: 0.75rem;
                animation: slideInRight 0.3s ease-out;
            ">
                <i class='bx ${icon}' style='font-size: 1.5rem;'></i>
                <span style='font-weight: 500; flex: 1;'>${message}</span>
                <button type="button" class="close text-white" style="padding: 0; margin: 0; font-size: 1.5rem; opacity: 0.8;" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    function loadDepartmentData() {
        $.ajax({
            url: 'ajax/ajax_get_departments.php',
            method: 'GET',
            cache: false,
            dataType: 'html',
            success: function(html) {
                try {
                    const $temp = $('<div>').html(html);
                    
                    const newGridHtml = $temp.find('#departmentGrid').html();
                    if (newGridHtml) {
                        $('#departmentGrid').fadeOut(150, function() {
                            $(this).html(newGridHtml).fadeIn(150);
                        });
                    }
                    
                    const $newStats = $temp.find('.stat-number');
                    if ($newStats.length > 0) {
                        $('.stat-number').each(function(index) {
                            const newVal = $($newStats[index]).text();
                            $(this).fadeOut(100, function() {
                                $(this).text(newVal).fadeIn(100);
                            });
                        });
                    }
                    
                    $('#searchInput').val('');
                    $('#noResultsMessage').remove();
                    
                } catch(e) {
                    console.error('Parse error:', e);
                    showNotification('Data refreshed', 'success');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Refresh Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                showNotification('Unable to refresh. Please reload manually.', 'error');
            }
        });
    }

    function serializeAddForm($form) {
        const formData = new URLSearchParams();
        
        formData.append('name', $form.find('input[name="name"]').val());
        formData.append('acronym', $form.find('input[name="acronym"]').val());
        formData.append('description', $form.find('textarea[name="description"]').val());
        
        $('.service-group').each(function(index) {
            const serviceName = $(this).find('input[name="services[]"]').val();
            
            if (serviceName && serviceName.trim()) {
                formData.append('services[]', serviceName.trim());
                
                const requirements = [];
                $(this).find('input[name^="requirements["]').each(function() {
                    const reqValue = $(this).val();
                    if (reqValue && reqValue.trim()) {
                        requirements.push(reqValue.trim());
                    }
                });
                
                if (requirements.length > 0) {
                    formData.append('requirements[]', requirements.join('|||'));
                } else {
                    formData.append('requirements[]', '');
                }
            }
        });
        
        return formData.toString();
    }

    function submitAjaxForm($form, url, modalSelector, shouldReset) {
        if (isSubmitting) {
            console.log('Form submission already in progress');
            return;
        }

        if (!confirm("Are you sure you want to proceed with this action?")) {
            return;
        }

        isSubmitting = true;
        const $btn = $form.find('button[type="submit"]');
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Processing...');

        let formData;
        if ($form.attr('id') === 'addForm') {
            formData = serializeAddForm($form);
        } else {
            formData = $form.serialize();
        }

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Success response:', response);
                
                if (shouldReset) {
                    $form[0].reset();
                    resetAddModalForm();
                }

                hideModal(modalSelector.replace('#', ''));
                
                showNotification(response.message || 'Operation completed successfully', 'success');
                
                setTimeout(() => {
                    loadDepartmentData();
                }, 300);
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr);
                
                let errorMsg = "An error occurred";
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch(e) {
                    if (xhr.responseText) {
                        errorMsg = xhr.responseText;
                    }
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            }
        });
    }

    function updateServiceNumbers() {
        $('.service-group').each(function(index) {
            $(this).find('.service-group-title').html(`
                <i class='bx bx-briefcase'></i>
                Service #${index + 1}
            `);
            
            if (index === 0) {
                $(this).find('.removeService').css('visibility', 'hidden');
            } else {
                $(this).find('.removeService').css('visibility', 'visible');
            }
        });
    }

    function updateRequirementButtons(container) {
        const inputs = container.find('.input-group');
        
        if (inputs.length === 1) {
            inputs.find('.removeRequirement').css('visibility', 'hidden');
        } else {
            inputs.find('.removeRequirement').css('visibility', 'visible');
        }
    }

    function resetAddModalForm() {
        $('.service-group').not(':first').remove();
        
        $('.requirement-input-group').first().find('.input-group').not(':first').remove();
        
        $('.service-group').first().find('.removeService').css('visibility', 'hidden');
        $('.requirement-input-group').first().find('.removeRequirement').css('visibility', 'hidden');
        
        serviceIndex = 0;
    }

    $('#searchInput').on('input.' + NAMESPACE, function() {
        const val = $(this).val().toLowerCase();
        let visibleCount = 0;
        
        $('.dept-card-wrapper').each(function() {
            const searchable = $(this).data('search');
            const isVisible = searchable.includes(val);
            $(this).toggle(isVisible);
            if (isVisible) visibleCount++;
        });
        
        if (visibleCount === 0 && val !== '') {
            if ($('#noResultsMessage').length === 0) {
                $('#departmentGrid').append(`
                    <div class="col-12 empty-state" id="noResultsMessage">
                        <i class='bx bx-search-alt'></i>
                        <h4>No Results Found</h4>
                        <p>Try adjusting your search terms</p>
                    </div>
                `);
            }
        } else {
            $('#noResultsMessage').remove();
        }
    });

    $('#clearFilters').on('click.' + NAMESPACE, function() {
        $('#searchInput').val('').trigger('input');
    });

    $(document).on('click.' + NAMESPACE, '#addService', function() {
        serviceIndex++;
        const serviceCount = $('.service-group').length + 1;
        
        const group = `
            <div class="service-group">
                <div class="service-group-header">
                    <span class="service-group-title">
                        <i class='bx bx-briefcase'></i>
                        Service #${serviceCount}
                    </span>
                    <button type="button" class="btn remove-btn removeService">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="mb-3">
                    <input type="text" name="services[]" class="form-control form-control-custom" placeholder="Enter service name" required>
                </div>
                
                <div class="requirements-container">
                    <label class="form-label mb-2" style="font-size: 0.8125rem; font-weight: 500;">
                        <i class='bx bx-list-check'></i>
                        Requirements (Optional)
                    </label>
                    <div class="requirement-input-group">
                        <div class="input-group mb-2">
                            <input type="text" name="requirements[${serviceIndex}][]" class="form-control form-control-custom" placeholder="Enter requirement">
                            <div class="input-group-append">
                                <button type="button" class="btn remove-btn removeRequirement" style="visibility: hidden;">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-custom btn-sm-custom addRequirement" data-service-index="${serviceIndex}">
                        <i class='bx bx-plus'></i>
                        Add Another Requirement
                    </button>
                </div>
            </div>`;
        
        $('#serviceFields').append(group);
        updateServiceNumbers();
    });

    $(document).on('click.' + NAMESPACE, '.removeService', function() {
        if ($('.service-group').length > 1) {
            $(this).closest('.service-group').remove();
            updateServiceNumbers();
        }
    });

    $(document).on('click.' + NAMESPACE, '.addRequirement', function() {
        const serviceIdx = $(this).data('service-index');
        const container = $(this).siblings('.requirement-input-group');
        
        const reqField = `
            <div class="input-group mb-2">
                <input type="text" name="requirements[${serviceIdx}][]" class="form-control form-control-custom" placeholder="Enter requirement">
                <div class="input-group-append">
                    <button type="button" class="btn remove-btn removeRequirement">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>`;
        
        container.append(reqField);
        updateRequirementButtons(container);
    });

    $(document).on('click.' + NAMESPACE, '.removeRequirement', function() {
        const container = $(this).closest('.requirement-input-group');
        
        if (container.find('.input-group').length > 1) {
            $(this).closest('.input-group').remove();
            updateRequirementButtons(container);
        }
    });

    $(document).on('click.' + NAMESPACE, '[id^="addNewServiceBtn"]', function() {
        const timestamp = Date.now();
        const uniqueId = 'new_' + timestamp;
        const block = `
            <div class="service-edit-block">
                <input type="hidden" name="service_ids[]" value="${uniqueId}">
                <input type="text" name="service_names[]" class="form-control form-control-custom mb-3" placeholder="Service Name" required>
                <div class="requirement-group">
                    <div class="input-group mb-2">
                        <input type="text" name="requirements[${uniqueId}][]" class="form-control form-control-custom" placeholder="Requirement">
                        <div class="input-group-append">
                            <button type="button" class="btn remove-btn remove-req"><i class='bx bx-x'></i></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-custom btn-sm-custom add-req">
                    <i class='bx bx-plus'></i> Add Requirement
                </button>
            </div>`;
        $(this).siblings('.service-edit-area').append(block);
    });

    $(document).on('click.' + NAMESPACE, '.add-req', function() {
        const reqGroup = $(this).siblings('.requirement-group');
        const serviceId = $(this).closest('.service-edit-block').find('input[name="service_ids[]"]').val();
        
        const reqField = `
            <div class="input-group mb-2">
                <input type="text" name="requirements[${serviceId}][]" class="form-control form-control-custom" placeholder="Requirement">
                <div class="input-group-append">
                    <button type="button" class="btn remove-btn remove-req"><i class='bx bx-x'></i></button>
                </div>
            </div>`;
        reqGroup.append(reqField);
    });

    $(document).on('click.' + NAMESPACE, '.remove-req', function() {
        $(this).closest('.input-group').remove();
    });

    $('#addForm').on('submit.' + NAMESPACE, function(e) {
        e.preventDefault();
        submitAjaxForm($(this), 'ajax/ajax_add_department_with_services.php', '#addModal', true);
    });

    $(document).on('submit.' + NAMESPACE, '.edit-dept-form', function(e) {
        e.preventDefault();
        const modalId = $(this).closest('.modal').attr('id');
        submitAjaxForm($(this), $(this).attr('action'), '#' + modalId, false);
    });


    $(document).on('click.' + NAMESPACE, '.delete-dept-btn', function() {
        const deptId = $(this).data('id');
        const modalId = $(this).closest('.modal').attr('id');
        
        if (confirm('Are you sure you want to delete this department? This action cannot be undone.')) {
            if (isSubmitting) return;
            isSubmitting = true;
            
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Deleting...');

            $.ajax({
                url: 'ajax/ajax_delete_department.php',
                method: 'POST',
                data: { id: deptId },
                dataType: 'json',
                success: function(response) {
                    hideModal(modalId);
                    showNotification(response.message || 'Department deleted successfully', 'success');
                    setTimeout(() => {
                        loadDepartmentData();
                    }, 300);
                },
                error: function(xhr) {
                    let errorMsg = "Failed to delete department";
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.message || errorMsg;
                    } catch(e) {
                        errorMsg = xhr.responseText || errorMsg;
                    }
                    showNotification(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            });
        }
    });

    $(document).on('click.' + NAMESPACE, '.delete-service-btn', function() {
        const serviceId = $(this).data('service-id');
        const serviceName = $(this).data('service-name');
        
        if (confirm(`Are you sure you want to delete the service "${serviceName}"? This will also delete all its requirements.`)) {
            if (isSubmitting) return;
            isSubmitting = true;
            
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i>');

            $.ajax({
                url: 'ajax/ajax_delete_service.php',
                method: 'POST',
                data: { service_id: serviceId },
                dataType: 'json',
                success: function(response) {
                    showNotification(response.message || 'Service deleted successfully', 'success');
                    
                    $btn.closest('.service-item').fadeOut(300, function() {
                        $(this).remove();
                        
                        const $modal = $btn.closest('.modal');
                        if ($modal.find('.service-item').length === 0) {
                            $modal.find('.service-item').parent().html(`
                                <div class="empty-state">
                                    <i class='bx bx-package'></i>
                                    <h4>No Services Available</h4>
                                    <p>This department has no services listed yet.</p>
                                </div>
                            `);
                        }
                    });
                    
                    setTimeout(() => {
                        loadDepartmentData();
                    }, 400);
                },
                error: function(xhr) {
                    let errorMsg = "Failed to delete service";
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.message || errorMsg;
                    } catch(e) {
                        errorMsg = xhr.responseText || errorMsg;
                    }
                    showNotification(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            });
        }
    });

    $(document).on('click.' + NAMESPACE, '.edit-dept-btn', function() {
        if (isModalTransitioning) return;
        isModalTransitioning = true;

        const deptId = $(this).data('dept-id');
        const viewModal = $('#viewModal' + deptId);
        const editModal = $('#editModal' + deptId);

        viewModal.modal('hide');
        
        viewModal.one('hidden.bs.modal', function() {
            cleanupModals();
            setTimeout(function() {
                editModal.modal('show');
                isModalTransitioning = false;
            }, 150);
        });
    });


    $('#addModal').on('hidden.bs.modal.' + NAMESPACE, function() {
        $('#addForm')[0].reset();
        resetAddModalForm();
    });

    $('.modal').on('hidden.bs.modal.' + NAMESPACE, function() {
        cleanupModals();
        isModalTransitioning = false;
    });

    $(window).on('popstate.' + NAMESPACE, function() {
        cleanupModals();
    });

    $(window).on('beforeunload.' + NAMESPACE, function() {
        cleanupModals();
    });

    window.deptMgmtCleanup = function() {
        console.log('Running cleanup for namespace:', NAMESPACE);
        
        $(document).off('.' + NAMESPACE);
        $('#searchInput').off('.' + NAMESPACE);
        $('#clearFilters').off('.' + NAMESPACE);
        $('#addForm').off('.' + NAMESPACE);
        $('#addModal').off('.' + NAMESPACE);
        $('.modal').off('.' + NAMESPACE);
        $(window).off('.' + NAMESPACE);
        
        cleanupModals();
        
        isModalTransitioning = false;
        isSubmitting = false;
        serviceIndex = 0;
        
        console.log('Cleanup completed');
    };

    console.log('Department management initialization complete');

})();
</script>
</body>
</html>