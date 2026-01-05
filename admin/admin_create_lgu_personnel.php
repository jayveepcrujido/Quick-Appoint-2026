<?php
session_start();
if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../conn.php';

// Fetch departments
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Fetch LGU Personnel (joined with auth to get email) - UPDATED QUERY
$stmt = $pdo->prepare("
    SELECT lp.id, lp.first_name, lp.middle_name, lp.last_name, lp.department_id, 
           lp.is_department_head, lp.created_by_personnel_id,
           a.email, d.name AS dept_name 
    FROM lgu_personnel lp
    JOIN auth a ON lp.auth_id = a.id
    LEFT JOIN departments d ON lp.department_id = d.id
    WHERE a.role = 'LGU Personnel'
    ORDER BY lp.is_department_head DESC, lp.created_at DESC
");
$stmt->execute();
$personnel = $stmt->fetchAll();

// Count department heads
$dept_heads_count = count(array_filter($personnel, fn($p) => $p['is_department_head'] == 1));
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage LGU Personnel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
    --primary-gradient: linear-gradient(135deg, #0D92F4, #27548A);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --danger-gradient: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
    --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    --hover-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e8edf2 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.page-header {
    background: var(--primary-gradient);
    color: white;
    padding: 2rem;
    margin-bottom: 2rem;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.page-header h4 {
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-header h4 i {
    font-size: 2rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.stats-card .icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: var(--primary-gradient);
    color: white;
    flex-shrink: 0;
}

.stats-card h3 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0.5rem 0 0 0;
    color: #2d3748;
}

.stats-card p {
    color: #718096;
    margin: 0;
    font-size: 0.9rem;
}

.main-card {
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header-custom {
    background: var(--primary-gradient);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.card-header-custom h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add {
    background: white;
    color: #667eea;
    border: none;
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
    color: #667eea;
}

.search-filter-bar {
    padding: 1.5rem 2rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}

.search-box {
    position: relative;
}

.search-box input {
    border-radius: 50px;
    padding: 0.75rem 1.25rem 0.75rem 3rem;
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;
    width: 100%;
}

.search-box input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.search-box i {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.table-container {
    padding: 2rem;
}

.custom-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.custom-table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    border: none;
    white-space: nowrap;
}

.custom-table thead th:first-child {
    border-radius: 10px 0 0 0;
}

.custom-table thead th:last-child {
    border-radius: 0 10px 0 0;
}

.custom-table tbody tr {
    transition: all 0.3s ease;
    background: white;
}

.custom-table tbody tr:hover {
    background: #f7fafc;
    transform: scale(1.01);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.custom-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.badge-dept {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
    display: inline-block;
    white-space: nowrap;
}

.badge-head {
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.75rem;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin-left: 0.5rem;
    white-space: nowrap;
}

.badge-created-by {
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
}

.badge-admin {
    background: #dbeafe;
    color: #1e40af;
}

.badge-personnel {
    background: #d1fae5;
    color: #065f46;
}

.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border: 2px solid;
    margin: 0 0.25rem;
    cursor: pointer;
}

.btn-edit {
    background: white;
    color: #3182ce;
    border-color: #3182ce;
}

.btn-edit:hover {
    background: #3182ce;
    color: white;
    transform: scale(1.1);
}

.btn-delete {
    background: white;
    color: #e53e3e;
    border-color: #e53e3e;
}

.btn-delete:hover {
    background: #e53e3e;
    color: white;
    transform: scale(1.1);
}

.modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 1.5rem 2rem;
}

.modal-header .close {
    color: white;
    opacity: 1;
    font-size: 1.5rem;
}

.modal-body {
    padding: 2rem;
}

.form-group label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control-custom {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.form-control-custom:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-submit {
    background: var(--primary-gradient);
    border: none;
    color: white;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #a0aec0;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.custom-control-label {
    cursor: pointer;
    user-select: none;
}

.dept-head-checkbox {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    margin-top: 0.5rem;
}

.alert-info-custom {
    background: #e0f2fe;
    border: 2px solid #7dd3fc;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: start;
    gap: 0.75rem;
}

.alert-info-custom i {
    color: #0284c7;
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

/* ========================================
   RESPONSIVE DESIGN - MOBILE & TABLET
   ======================================== */

/* Large Tablets and Small Desktops (1024px and below) */
@media (max-width: 1024px) {
    .page-header {
        padding: 1.75rem;
    }

    .stats-card {
        padding: 1.25rem;
    }

    .stats-card .icon {
        width: 55px;
        height: 55px;
        font-size: 1.5rem;
    }

    .stats-card h3 {
        font-size: 1.75rem;
    }

    .table-container {
        padding: 1.5rem;
    }

    .custom-table thead th {
        padding: 0.875rem;
        font-size: 0.8rem;
    }

    .custom-table tbody td {
        padding: 0.875rem;
    }
}

/* Tablets (768px and below) */
@media (max-width: 768px) {
    body {
        padding: 0;
    }

    .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    .page-header {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 15px;
    }

    .page-header h4 {
        font-size: 1.25rem;
    }

    .page-header h4 i {
        font-size: 1.5rem;
    }

    /* Stats Cards - Stack on mobile */
    .stats-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }

    .stats-card .d-flex {
        flex-direction: row;
        align-items: center;
    }

    .stats-card .icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }

    .stats-card h3 {
        font-size: 1.5rem;
    }

    .stats-card p {
        font-size: 0.85rem;
    }

    /* Card Header */
    .card-header-custom {
        padding: 1rem 1.5rem;
        flex-direction: column;
        align-items: stretch;
    }

    .card-header-custom h5 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }

    .btn-add {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1.5rem;
    }

    /* Search Bar */
    .search-filter-bar {
        padding: 1rem 1.5rem;
    }

    .search-box input {
        font-size: 0.9rem;
        padding: 0.65rem 1rem 0.65rem 2.75rem;
    }

    .search-box i {
        left: 1rem;
        font-size: 1.1rem;
    }

    /* Table Container */
    .table-container {
        padding: 0;
    }

    .table-responsive {
        border-radius: 0;
    }

    /* Mobile Card-Style Table */
    .custom-table {
        font-size: 0.875rem;
    }

    .custom-table thead {
        display: none;
    }

    .custom-table tbody {
        display: block;
    }

    .custom-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1rem;
        background: white;
    }

    .custom-table tbody tr:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .custom-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 0.75rem 0;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    .custom-table tbody td:first-child {
        border-top: none;
        padding-top: 0;
    }

    .custom-table tbody td:last-child {
        border-bottom: none;
        justify-content: center;
        padding-bottom: 0;
        padding-top: 1rem;
        margin-top: 0.5rem;
        border-top: 2px solid #e2e8f0;
    }

    .custom-table tbody td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #4a5568;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
        flex-basis: 100%;
    }

    .custom-table tbody td:last-child::before {
        text-align: center;
    }

    /* Badges mobile optimization */
    .badge-dept,
    .badge-head,
    .badge-created-by {
        font-size: 0.75rem;
        padding: 0.35rem 0.75rem;
        margin: 0.25rem 0;
    }

    .badge-head,
    .badge-created-by {
        margin-left: 0;
        margin-top: 0.5rem;
        display: inline-flex;
    }

    /* Action buttons on mobile */
    .btn-action {
        width: 42px;
        height: 42px;
        margin: 0 0.35rem;
    }

    .btn-action i {
        font-size: 1.1rem;
    }

    /* Modal adjustments */
    .modal-dialog {
        margin: 0.5rem;
    }

    .modal-content {
        border-radius: 15px;
    }

    .modal-header {
        padding: 1.25rem 1.5rem;
    }

    .modal-header .modal-title {
        font-size: 1.1rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        font-size: 0.85rem;
    }

    .form-control-custom {
        padding: 0.65rem;
        font-size: 0.9rem;
    }

    .dept-head-checkbox {
        padding: 0.875rem;
    }

    .btn-submit {
        padding: 0.75rem 1.5rem;
        font-size: 0.95rem;
    }

    .alert-info-custom {
        padding: 0.875rem;
        font-size: 0.85rem;
    }

    /* Empty state */
    .empty-state {
        padding: 2rem 1rem;
    }

    .empty-state i {
        font-size: 3rem;
    }

    .empty-state h5 {
        font-size: 1.1rem;
    }
}

/* Mobile Landscape (667px and below, landscape) */
@media (max-width: 667px) and (orientation: landscape) {
    .page-header {
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
    }

    .stats-card {
        padding: 0.75rem;
    }

    .stats-card .icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }

    .stats-card h3 {
        font-size: 1.35rem;
    }

    .modal-dialog {
        max-width: 90%;
        margin: 0.5rem auto;
    }

    .modal-body {
        max-height: 65vh;
        overflow-y: auto;
    }
}

/* Small Mobile Devices (480px and below) */
@media (max-width: 480px) {
    .page-header {
        padding: 1.25rem;
        border-radius: 12px;
    }

    .page-header h4 {
        font-size: 1.1rem;
        gap: 0.5rem;
    }

    .page-header h4 i {
        font-size: 1.35rem;
    }

    /* Stats cards - single column */
    .row {
        margin-left: 0;
        margin-right: 0;
    }

    .col-md-3 {
        padding-left: 0;
        padding-right: 0;
    }

    .stats-card {
        margin-bottom: 0.875rem;
        padding: 0.875rem;
    }

    .stats-card .icon {
        width: 48px;
        height: 48px;
        font-size: 1.2rem;
    }

    .stats-card h3 {
        font-size: 1.4rem;
    }

    .stats-card p {
        font-size: 0.8rem;
    }

    /* Main card */
    .main-card {
        border-radius: 15px;
        margin-bottom: 1.5rem;
    }

    .card-header-custom {
        padding: 1rem;
    }

    .card-header-custom h5 {
        font-size: 1rem;
    }

    .card-header-custom h5 i {
        font-size: 1.1rem;
    }

    .btn-add {
        padding: 0.65rem 1.25rem;
        font-size: 0.9rem;
    }

    .btn-add i {
        font-size: 1.1rem;
    }

    /* Search */
    .search-filter-bar {
        padding: 1rem;
    }

    .search-box input {
        font-size: 0.85rem;
        padding: 0.6rem 0.875rem 0.6rem 2.5rem;
    }

    .search-box i {
        left: 0.875rem;
        font-size: 1rem;
    }

    /* Table cards */
    .custom-table tbody tr {
        padding: 0.875rem;
        margin-bottom: 0.875rem;
        border-radius: 10px;
    }

    .custom-table tbody td {
        padding: 0.65rem 0;
        font-size: 0.85rem;
    }

    .custom-table tbody td::before {
        font-size: 0.75rem;
    }

    /* Badges */
    .badge-dept {
        font-size: 0.7rem;
        padding: 0.3rem 0.65rem;
    }

    .badge-head {
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
        margin-top: 0.4rem;
    }

    .badge-created-by {
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
        margin-top: 0.4rem;
    }

    /* Action buttons */
    .btn-action {
        width: 40px;
        height: 40px;
        margin: 0 0.3rem;
    }

    /* Modal */
    .modal-dialog {
        margin: 0.25rem;
    }

    .modal-header {
        padding: 1rem 1.25rem;
    }

    .modal-header .modal-title {
        font-size: 1rem;
    }

    .modal-header .modal-title i {
        font-size: 1.15rem;
    }

    .modal-header .close {
        font-size: 1.35rem;
        padding: 0.5rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .form-group {
        margin-bottom: 0.875rem;
    }

    .form-group label {
        font-size: 0.8rem;
        margin-bottom: 0.4rem;
    }

    .form-control-custom {
        padding: 0.6rem;
        font-size: 0.85rem;
        border-radius: 8px;
    }

    .form-text {
        font-size: 0.75rem;
    }

    .dept-head-checkbox {
        padding: 0.75rem;
        border-radius: 8px;
    }

    .custom-control-label {
        font-size: 0.85rem;
    }

    .dept-head-checkbox small {
        font-size: 0.75rem;
    }

    .btn-submit {
        padding: 0.7rem 1.25rem;
        font-size: 0.9rem;
        border-radius: 40px;
    }

    .alert-info-custom {
        padding: 0.75rem;
        font-size: 0.8rem;
    }

    .alert-info-custom i {
        font-size: 1.1rem;
    }

    /* Empty state */
    .empty-state {
        padding: 1.75rem 0.875rem;
    }

    .empty-state i {
        font-size: 2.75rem;
    }

    .empty-state h5 {
        font-size: 1rem;
    }

    .empty-state p {
        font-size: 0.85rem;
    }
}

/* Extra Small Mobile (375px and below) */
@media (max-width: 375px) {
    .page-header h4 {
        font-size: 1rem;
    }

    .stats-card h3 {
        font-size: 1.3rem;
    }

    .card-header-custom h5 {
        font-size: 0.95rem;
    }

    .btn-add {
        font-size: 0.85rem;
        padding: 0.6rem 1rem;
    }

    .custom-table tbody tr {
        padding: 0.75rem;
    }

    .btn-action {
        width: 38px;
        height: 38px;
    }

    .modal-body {
        padding: 1rem;
    }
}

/* Touch device improvements */
@media (hover: none) and (pointer: coarse) {
    /* Better touch targets */
    .btn-action {
        min-width: 44px;
        min-height: 44px;
    }

    .custom-control-label {
        padding-left: 0.5rem;
    }

    /* Prevent touch callout */
    .btn-action,
    .btn-add,
    .btn-submit {
        -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
        -webkit-touch-callout: none;
    }

    /* Remove hover effects on touch */
    .stats-card:hover {
        transform: none;
    }

    .custom-table tbody tr:hover {
        transform: none;
    }

    .btn-add:hover {
        transform: none;
    }

    .btn-submit:hover {
        transform: none;
    }
}

/* Landscape orientation for phones */
@media (max-height: 600px) and (orientation: landscape) {
    .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }

    .page-header {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .stats-card {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }
}
/* Custom Alert Styles */
.custom-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    min-width: 350px;
    max-width: 500px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.custom-alert-success {
    border-left: 5px solid #10b981;
}

.custom-alert-error {
    border-left: 5px solid #ef4444;
}

.custom-alert .alert-content {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    flex: 1;
}

.custom-alert .alert-content i {
    font-size: 28px;
    margin-top: 2px;
}

.custom-alert-success .alert-content i {
    color: #10b981;
}

.custom-alert-error .alert-content i {
    color: #ef4444;
}

.custom-alert .alert-text {
    flex: 1;
}

.custom-alert .alert-text strong {
    display: block;
    font-size: 16px;
    color: #1f2937;
    margin-bottom: 5px;
}

.custom-alert .alert-text p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}

.custom-alert .alert-close {
    background: transparent;
    border: none;
    color: #9ca3af;
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.custom-alert .alert-close:hover {
    background: #f3f4f6;
    color: #1f2937;
}

/* Mobile responsiveness for alerts */
@media (max-width: 480px) {
    .custom-alert {
        top: 10px;
        right: 10px;
        left: 10px;
        min-width: auto;
        max-width: none;
        padding: 15px;
    }
    
    .custom-alert .alert-content i {
        font-size: 24px;
    }
    
    .custom-alert .alert-text strong {
        font-size: 14px;
    }
    
    .custom-alert .alert-text p {
        font-size: 13px;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h4><i class='bx bx-user-circle'></i> Manage LGU Personnel</h4>
        </div>
    </div>

    <div class="container">
        <!-- Stats Card - UPDATED -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon">
                            <i class='bx bx-group'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count($personnel) ?></h3>
                            <p>Total Personnel</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: var(--success-gradient);">
                            <i class='bx bx-buildings'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count($departments) ?></h3>
                            <p>Departments</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class='bx bx-crown'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= $dept_heads_count ?></h3>
                            <p>Department Heads</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="icon" style="background: var(--danger-gradient);">
                            <i class='bx bx-user-check'></i>
                        </div>
                        <div class="ml-3">
                            <h3><?= count(array_filter($personnel, fn($p) => $p['dept_name'])) ?></h3>
                            <p>Assigned</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="main-card">
            <div class="card-header-custom">
                <h5><i class='bx bx-list-ul'></i> Personnel Directory</h5>
                <button class="btn btn-add" data-toggle="modal" data-target="#addModal">
                    <i class='bx bx-plus-circle'></i> Add New Personnel
                </button>
            </div>

            <div class="search-filter-bar">
                <div class="search-box">
                    <i class='bx bx-search'></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name, email, or department...">
                </div>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table custom-table" id="personnelTable">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email Address</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personnel as $p): ?>
                                <tr data-id="<?= $p['id'] ?>">
                                    <td data-label="Full Name">
                                        <strong><?= htmlspecialchars("{$p['first_name']} {$p['middle_name']} {$p['last_name']}") ?></strong>
                                        <?php if ($p['is_department_head']): ?>
                                            <span class="badge-head">
                                                <i class='bx bx-crown'></i> Head
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Email">
                                        <?= htmlspecialchars($p['email']) ?>
                                    </td>
                                    <td data-label="Department">
                                        <span class="badge-dept"><?= htmlspecialchars($p['dept_name']) ?></span>
                                    </td>
                                    <td data-label="Role">
                                        <?php if ($p['is_department_head']): ?>
                                            <span class="badge-created-by badge-admin">
                                                <i class='bx bx-shield-alt'></i> Department Head
                                            </span>
                                        <?php elseif ($p['created_by_personnel_id']): ?>
                                            <span class="badge-created-by badge-personnel">
                                                <i class='bx bx-user'></i> Co-Personnel
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-created-by" style="background: #e0e7ff; color: #4338ca;">
                                                <i class='bx bx-user-check'></i> Personnel
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="text-center">
                                        <button class="btn btn-action btn-edit editBtn"
                                            title="Edit Personnel"
                                            data-id="<?= $p['id'] ?>"
                                            data-first="<?= htmlspecialchars($p['first_name']) ?>"
                                            data-middle="<?= htmlspecialchars($p['middle_name']) ?>"
                                            data-last="<?= htmlspecialchars($p['last_name']) ?>"
                                            data-email="<?= htmlspecialchars($p['email']) ?>"
                                            data-dept="<?= $p['department_id'] ?>"
                                            data-is-head="<?= $p['is_department_head'] ?>">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="btn btn-action btn-delete deleteBtn" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars("{$p['first_name']} {$p['last_name']}") ?>"
                                                title="Delete Personnel">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($personnel)): ?>
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <h5>No Personnel Found</h5>
                            <p>Start by adding your first LGU personnel member.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal - UPDATED -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="addForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel"><i class='bx bx-user-plus'></i> Add New Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input name="first_name" class="form-control form-control-custom" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input name="middle_name" class="form-control form-control-custom" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input name="last_name" class="form-control form-control-custom" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input name="email" type="email" class="form-control form-control-custom" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input name="password" type="password" class="form-control form-control-custom" placeholder="Enter password" minlength="6" required>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-control form-control-custom" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="dept-head-checkbox">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_department_head" name="is_department_head">
                            <label class="custom-control-label" for="is_department_head">
                                <strong><i class='bx bx-crown'></i> Set as Department Head</strong>
                            </label>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class='bx bx-info-circle'></i> Department heads can create and manage co-personnel within their department
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-submit">
                        <i class='bx bx-save'></i> Add Personnel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal - UPDATED -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form id="editForm" class="modal-content">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-edit'></i> Edit Personnel</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert-info-custom" id="edit-info-box" style="display: none;">
                        <i class='bx bx-info-circle'></i>
                        <div>
                            <strong>Note:</strong>
                            <p class="mb-0" style="font-size: 0.9rem;" id="edit-info-text"></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>First Name <span class="text-danger">*</span></label>
                        <input name="first_name" class="form-control form-control-custom" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input name="middle_name" class="form-control form-control-custom" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="text-danger">*</span></label>
                        <input name="last_name" class="form-control form-control-custom" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-control form-control-custom" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input name="email" type="email" class="form-control form-control-custom" placeholder="Enter email address" required>
                    </div>
                    
                    <div class="dept-head-checkbox">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="edit_is_department_head" name="is_department_head">
                            <label class="custom-control-label" for="edit_is_department_head">
                                <strong><i class='bx bx-crown'></i> Department Head Status</strong>
                            </label>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class='bx bx-info-circle'></i> Check to grant or maintain department head privileges
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-submit">
                        <i class='bx bx-save'></i> Update Personnel
                    </button>
                </div>
            </form>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
$("#searchInput").on("keyup", function() {
    const value = $(this).val().toLowerCase();
    $("#personnelTable tbody tr").filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

// Function to reload just the personnel table
function loadPersonnelTable() {
    $.ajax({
        url: 'admin_create_lgu_personnel.php',
        method: 'GET',
        success: function(html) {
            // Extract and update the table
            const newTable = $(html).find('#personnelTable tbody').html();
            $('#personnelTable tbody').html(newTable);
            
            // Update stats cards
            const newStats = $(html).find('.stats-card h3');
            $('.stats-card h3').each(function(index) {
                $(this).text($(newStats[index]).text());
            });
        },
        error: function() {
            console.error('Failed to reload table');
            // Fallback to full page reload if dynamic load fails
            location.reload();
        }
    });
}

// ADD with validation and dynamic update
$("#addForm").submit(function(e) {
    e.preventDefault();
    
    const isDeptHead = $('#is_department_head').is(':checked');
    
    // GET THE DEPARTMENT NAME
    const deptSelect = $(this).find('[name="department_id"]');
    const deptName = deptSelect.find('option:selected').text();
    
    let confirmMsg = "Are you sure you want to add this personnel";
    if (isDeptHead) {
        confirmMsg += " as a Department Head";
    }
    confirmMsg += "?";
    
    if (confirm(confirmMsg)) {
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<i class="bx bx-loader-alt bx-spin"></i> Creating...').prop('disabled', true);
        
        // ADD DEPARTMENT NAME TO FORM DATA
        let formData = $(this).serialize();
        formData += '&department_name=' + encodeURIComponent(deptName);
        
        $.ajax({
            url: "ajax/ajax_create_personnel.php",
            method: "POST",
            data: formData,
            dataType: 'json',
            success: function(response) {
                // Reset button state
                submitBtn.html(originalBtnText).prop('disabled', false);
                
                if (response.success) {
                    // Close modal FIRST - completely and cleanly
                    $('#addModal').modal('hide');
                    
                    // Wait a moment for modal to close
                    setTimeout(function() {
                        // Remove ALL modal backdrops (in case multiple exist)
                        $('.modal-backdrop').remove();
                        
                        // Reset body
                        $('body').removeClass('modal-open');
                        $('body').css('padding-right', '');
                        $('body').css('overflow', '');
                        
                        // Reset form
                        $('#addForm')[0].reset();
                        
                        // Show success alert with custom styling
                        showSuccessAlert(response.message);
                        
                        // Reload table
                        loadPersonnelTable();
                    }, 300); // Wait 300ms for smooth modal close
                    
                } else {
                    // Show error without closing modal
                    showErrorAlert(response.message);
                }
            },
            error: function(xhr) {
                // Reset button state
                submitBtn.html(originalBtnText).prop('disabled', false);
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    showErrorAlert('Error: ' + response.message);
                } catch(e) {
                    console.error('Raw error:', xhr.responseText);
                    showErrorAlert('An error occurred. Check console for details.');
                }
            }
        });
    }
});

// Custom Success Alert Function
function showSuccessAlert(message) {
    // Remove any existing alerts
    $('.custom-alert').remove();
    
    const alertHtml = `
        <div class="custom-alert custom-alert-success">
            <div class="alert-content">
                <i class='bx bx-check-circle'></i>
                <div class="alert-text">
                    <strong>Success!</strong>
                    <p>${message}</p>
                </div>
            </div>
            <button class="alert-close" onclick="$(this).parent().fadeOut(300, function(){ $(this).remove(); })">
                <i class='bx bx-x'></i>
            </button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Animate in
    $('.custom-alert').hide().fadeIn(300);
    
    // Auto close after 5 seconds
    setTimeout(function() {
        $('.custom-alert').fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

// Custom Error Alert Function
function showErrorAlert(message) {
    // Remove any existing alerts
    $('.custom-alert').remove();
    
    const alertHtml = `
        <div class="custom-alert custom-alert-error">
            <div class="alert-content">
                <i class='bx bx-error-circle'></i>
                <div class="alert-text">
                    <strong>Error!</strong>
                    <p>${message}</p>
                </div>
            </div>
            <button class="alert-close" onclick="$(this).parent().fadeOut(300, function(){ $(this).remove(); })">
                <i class='bx bx-x'></i>
            </button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Animate in
    $('.custom-alert').hide().fadeIn(300);
    
    // Auto close after 5 seconds
    setTimeout(function() {
        $('.custom-alert').fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

// Clean up modal when it's hidden (extra safety measure)
$('#addModal').on('hidden.bs.modal', function () {
    // Reset form
    $('#addForm')[0].reset();
    
    // Clean up any lingering backdrops
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('padding-right', '');
    $('body').css('overflow', '');
});

// FILL EDIT MODAL
$(document).on("click", ".editBtn", function() {
    const btn = $(this);
    const isDeptHead = btn.data("is-head") == 1;
    
    $("#editForm [name=id]").val(btn.data("id"));
    $("#editForm [name=first_name]").val(btn.data("first"));
    $("#editForm [name=middle_name]").val(btn.data("middle"));
    $("#editForm [name=last_name]").val(btn.data("last"));
    $("#editForm [name=email]").val(btn.data("email"));
    $("#editForm [name=department_id]").val(btn.data("dept"));
    
    // Set department head checkbox
    $("#edit_is_department_head").prop('checked', isDeptHead);
    
    // Show info if department head
    if (isDeptHead) {
        $("#edit-info-box").show();
        $("#edit-info-text").html("This personnel is currently a <strong>Department Head</strong>. Unchecking will remove their ability to manage co-personnel.");
    } else {
        $("#edit-info-box").hide();
    }
    
    $("#editModal").modal("show");
});

// EDIT with validation and dynamic update
$("#editForm").submit(function(e) {
    e.preventDefault();
    
    const isDeptHead = $('#edit_is_department_head').is(':checked');
    let confirmMsg = "Are you sure you want to update this personnel";
    if (isDeptHead) {
        confirmMsg += " with Department Head status";
    }
    confirmMsg += "?";
    
    if (confirm(confirmMsg)) {
        $.ajax({
            url: "ajax/ajax_update_personnel.php",
            method: "POST",
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal properly and remove backdrop
                    $('#editModal').modal('hide');
                    
                    // Remove modal backdrop and reset body
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    
                    alert(response.message);
                    
                    // Reload the table dynamically
                    loadPersonnelTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + response.message);
                } catch(e) {
                    alert('Error: ' + xhr.responseText);
                }
            }
        });
    }
});

// DELETE with dynamic update
$(document).on("click", ".deleteBtn", function() {
    const id = $(this).data("id");
    const name = $(this).data("name");
    
    if (confirm("Are you sure you want to delete " + name + "?\n\nThis will permanently remove their account and all associated data.")) {
        $.ajax({
            url: "ajax/ajax_delete_personnel.php",
            method: "POST",
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    
                    // Reload the table dynamically (no modal to close for delete)
                    loadPersonnelTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + response.message);
                } catch(e) {
                    alert('Error: ' + xhr.responseText);
                }
            }
        });
    }
});
</script>
</body>
</html>