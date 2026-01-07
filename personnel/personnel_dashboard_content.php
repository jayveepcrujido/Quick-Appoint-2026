<?php
include '../conn.php';
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
session_set_cookie_params(86400);

session_start();

if (!isset($_SESSION['last_regenerate'])) {
    $_SESSION['last_regenerate'] = time();
} elseif (time() - $_SESSION['last_regenerate'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'LGU Personnel';

$dept_stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
$dept_stmt->execute([$_SESSION['auth_id']]);
$user_department_id = $dept_stmt->fetchColumn();

$today = date('Y-m-d');
$today_tasks = [];
$pending_count = 0;
$completed_today = 0;

if ($user_department_id) {
    $tasks_query = $pdo->prepare("
        SELECT 
            a.id, 
            a.transaction_id, 
            a.status, 
            a.scheduled_for,
            CONCAT(r.first_name, ' ', r.last_name) as resident_name,
            ds.service_name,
            TIME(a.scheduled_for) as appointment_time
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        LEFT JOIN department_services ds ON a.service_id = ds.id
        WHERE a.department_id = ? 
        AND DATE(a.scheduled_for) = ?
        AND a.status IN ('Pending', 'Confirmed')
        ORDER BY a.scheduled_for ASC
        LIMIT 5
    ");
    $tasks_query->execute([$user_department_id, $today]);
    $today_tasks = $tasks_query->fetchAll(PDO::FETCH_ASSOC);
    
    $pending_query = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE department_id = ? AND status = 'Pending'
    ");
    $pending_query->execute([$user_department_id]);
    $pending_count = $pending_query->fetchColumn();
    
    $completed_query = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE department_id = ? 
        AND status = 'Completed' 
        AND DATE(updated_at) = ?
    ");
    $completed_query->execute([$user_department_id, $today]);
    $completed_today = $completed_query->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Personnel Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../assets/css/personnel.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1e293b;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-top: -3rem;
        }

        .header {
            background: linear-gradient(to right, #0D92F4, #27548A);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-top: -3rem;
        }

        .header_toggle i {
            font-size: 1.8rem;
            color: var(--light-bg);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .header_toggle i:hover {
            transform: scale(1.1);
        }

        .profile-trigger {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
        }

        .profile-trigger i {
            font-size: 2.5rem;
            color: var(--light-bg);
            transition: all 0.3s ease;
        }

        .profile-trigger:hover i {
            color: #0D92F4;
        }

        .profile-dropdown {
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-area {
            margin-top: -7px;
            padding: 1rem 1rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 768px) {
            .content-area {
                padding: 2.5rem 2rem;
            }
        }

        .welcome-card {
            background: linear-gradient(to right, #0D92F4, #27548A);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            color: white;
            box-shadow: var(--card-shadow-hover);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .welcome-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 576px) {
            .welcome-card {
                padding: 1.5rem 1rem;
            }
            .welcome-card h2 {
                font-size: 1.5rem;
            }
            .welcome-card p {
                font-size: 1rem;
            }
        }

        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-color);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .icon-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: var(--danger-color);
        }

        .icon-primary {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: var(--primary-color);
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: var(--warning-color);
        }

        .icon-secondary {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #64748b;
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: var(--secondary-color);
        }

        .feature-card h5 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .feature-card small {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .stats-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        @media (max-width: 767px) {
            .feature-card {
                margin-bottom: 1rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .feature-col {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        .feature-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }
        .feature-card:nth-child(5) { animation-delay: 0.5s; }

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

        .section-title {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1.5rem;
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .notification-trigger {
            cursor: pointer;
            position: relative;
            padding: 8px;
            transition: all 0.3s ease;
            }

        .notification-trigger:hover {
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 50%;
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
.tasks-section {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.tasks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.tasks-header h3 {
    margin: 0;
    color: var(--dark-color);
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tasks-header .date-badge {
    background: linear-gradient(135deg, #0D92F4, #27548A);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.task-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.stat-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
}

.task-item {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-left: 4px solid var(--primary-color);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.task-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.task-item.status-confirmed {
    border-left-color: var(--secondary-color);
}

.task-item.status-pending {
    border-left-color: var(--warning-color);
}

.task-main-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.task-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.95rem;
}

.task-service-compact {
    color: #64748b;
    font-size: 0.85rem;
}

.task-time {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: linear-gradient(135deg, #0D92F4, #27548A);
    color: white;
    padding: 0.25rem 0.65rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.task-status-badge {
    padding: 0.25rem 0.65rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .task-main-info {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .task-service-compact {
        flex-basis: 100%;
        font-size: 0.8rem;
    }
}

.task-time {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: linear-gradient(135deg, #0D92F4, #27548A);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.task-info {
    margin-top: 0.25rem;
}

.task-info h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1rem;
}

.task-info p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

.task-status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.task-status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.task-status-badge.confirmed {
    background: #d1fae5;
    color: #065f46;
}

.no-tasks {
    text-align: center;
    padding: 2rem;
    color: #94a3b8;
}

.no-tasks i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

@media (max-width: 768px) {
    .tasks-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .task-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }
    
    .stat-card {
        padding: 0.75rem 0.5rem;
    }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
    }
    
    .task-item {
        padding: 0.75rem;
    }
}
.tasks-header:hover {
    background-color: rgba(0, 0, 0, 0.02);
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.tasks-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
    </style>

<script>
        window.pageCleanupRegistry = {
            intervals: [],
            timeouts: [],
            eventHandlers: [],
            ajaxRequests: []
        };

        const originalSetInterval = window.setInterval;
        window.setInterval = function(fn, delay) {
            const id = originalSetInterval(fn, delay);
            window.pageCleanupRegistry.intervals.push(id);
            return id;
        };

        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(fn, delay) {
            const id = originalSetTimeout(fn, delay);
            window.pageCleanupRegistry.timeouts.push(id);
            return id;
        };

        if (typeof jQuery !== 'undefined') {
            $(document).ajaxSend(function(event, jqXHR, settings) {
                window.pageCleanupRegistry.ajaxRequests.push(jqXHR);
            });
        }

window.cleanupAllPages = function() {
    console.log('=== STARTING UNIVERSAL CLEANUP ===');
    
    console.log('Clearing', window.pageCleanupRegistry.intervals.length, 'intervals');
    window.pageCleanupRegistry.intervals.forEach(function(id) {
        clearInterval(id);
    });
    window.pageCleanupRegistry.intervals = [];
    
    console.log('Clearing', window.pageCleanupRegistry.timeouts.length, 'timeouts');
    window.pageCleanupRegistry.timeouts.forEach(function(id) {
        clearTimeout(id);
    });
    window.pageCleanupRegistry.timeouts = [];
    
    console.log('Aborting', window.pageCleanupRegistry.ajaxRequests.length, 'AJAX requests');
    window.pageCleanupRegistry.ajaxRequests.forEach(function(jqXHR) {
        if (jqXHR && jqXHR.abort) {
            try {
                jqXHR.abort();
            } catch(e) {
                console.warn('Error aborting AJAX request:', e);
            }
        }
    });
    window.pageCleanupRegistry.ajaxRequests = [];
    
    if (window.availableDatesCleanup && typeof window.availableDatesCleanup === 'function') {
        console.log('Running availableDatesCleanup...');
        try {
            window.availableDatesCleanup();
        } catch(e) {
            console.error('Error in availableDatesCleanup:', e);
        }
    }
    
    if (window.appointmentStatusCleanup && typeof window.appointmentStatusCleanup === 'function') {
        console.log('Running appointmentStatusCleanup...');
        try {
            window.appointmentStatusCleanup();
        } catch(e) {
            console.error('Error in appointmentStatusCleanup:', e);
        }
    }
    
    if (window.manageAppointmentsCleanup && typeof window.manageAppointmentsCleanup === 'function') {
        console.log('Running manageAppointmentsCleanup...');
        try {
            window.manageAppointmentsCleanup();
        } catch(e) {
            console.error('Error in manageAppointmentsCleanup:', e);
        }
    }
    
    if (typeof jQuery !== 'undefined' && jQuery) {
        const namespaces = ['availDates', 'appointmentStatus', 'manageAppt', 'analytics'];
        namespaces.forEach(function(ns) {
            console.log('Removing event handlers for namespace:', ns);
            $(document).off('.' + ns);
            $(document).off('.editBtn_' + ns);
            $(document).off('.deleteBtn_' + ns);
            $(document).off('.removeBtn_' + ns);
        });
        
        try {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            
            if (typeof $.fn.modal !== 'undefined') {
                $('.modal').modal('hide');
            } else {
                console.warn('⚠️ Bootstrap modal not available, using manual hide');
                $('.modal').hide();
                $('.modal').attr('aria-hidden', 'true');
            }
        } catch(e) {
            console.warn('Error cleaning up modals:', e);
        }
        
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
    } else {
        console.warn('jQuery not available during cleanup');
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        });
        
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(backdrop) {
            backdrop.remove();
        });
        
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    console.log('=== CLEANUP COMPLETE ===');
};

        let currentPage = null;
        let isLoading = false;
        let loadAbortController = null;

        document.addEventListener("DOMContentLoaded", function () {
            const toggle = document.getElementById('header-toggle');
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');

            if (toggle) {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    nav.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }

            document.addEventListener('click', (e) => {
                if (nav && nav.classList.contains('show') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });

            const keepAliveInterval = setInterval(function() {
                $.ajax({
                    url: 'keep_alive.php',
                    method: 'GET',
                    cache: false,
                    error: function(xhr) {
                        if (xhr.status === 403) {
                            console.warn('Session expired, redirecting to login...');
                            clearInterval(keepAliveInterval);
                            window.location.href = '../login.php?timeout=1';
                        }
                    }
                });
            }, 300000);
        });

        function loadContent(page) {
            console.log('\n>>> NAVIGATION REQUEST: ' + page + ' <<<');
            
            if (isLoading) {
                console.warn('⚠️ Already loading content, please wait...');
                return false;
            }

            if (currentPage === page) {
                console.log('ℹ️ Already on page:', page);
                return false;
            }

            console.log('✓ Navigation approved from', currentPage || 'homepage', 'to', page);
            isLoading = true;
            
            const nav = document.getElementById('nav-bar');
            const overlay = document.getElementById('overlay');
            if (nav && nav.classList.contains('show')) {
                nav.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
            
            console.log('🧹 Initiating comprehensive cleanup...');
            window.cleanupAllPages();
            
            const cacheBuster = '?_=' + new Date().getTime();
            
            const contentArea = $("#content-area");
            contentArea.html(
                '<div class="text-center p-5">' +
                '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">' +
                '<span class="sr-only">Loading...</span>' +
                '</div>' +
                '<p class="mt-3 font-weight-bold">Loading ' + page + '...</p>' +
                '</div>'
            );
            
            setTimeout(function() {
                console.log('📥 Loading content from:', page + cacheBuster);
                
                contentArea.load(page + cacheBuster, function(response, status, xhr) {
                    isLoading = false;
                    
                    if (status === "error") {
                        console.error("❌ Error loading content:", xhr.status, xhr.statusText);
                        
                        if (xhr.status === 403 || response.includes('Unauthorized') || response.includes('Session expired')) {
                            contentArea.html(
                                '<div class="alert alert-danger m-4">' +
                                '<h4><i class="bx bx-error-circle"></i> Session Expired</h4>' +
                                '<p>Your session has expired. Please log in again.</p>' +
                                '<a href="../login.php" class="btn btn-primary">Go to Login</a>' +
                                '</div>'
                            );
                            setTimeout(function() {
                                window.location.href = '../login.php?timeout=1';
                            }, 2000);
                        } else if (xhr.status === 404) {
                            contentArea.html(
                                '<div class="alert alert-warning m-4">' +
                                '<h4><i class="bx bx-error"></i> Page Not Found</h4>' +
                                '<p>The requested page could not be found: <strong>' + page + '</strong></p>' +
                                '<button class="btn btn-primary" onclick="loadContent(\'personnel_analytics.php\')">Go to Dashboard</button>' +
                                '</div>'
                            );
                            currentPage = null;
                        } else {
                            contentArea.html(
                                '<div class="alert alert-danger m-4">' +
                                '<h4><i class="bx bx-error-circle"></i> Error Loading Content</h4>' +
                                '<p>Failed to load the requested page. Please try again.</p>' +
                                '<button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>' +
                                '</div>'
                            );
                            currentPage = null;
                        }
                    } else if (status === "success") {
                        console.log('✅ Content loaded successfully:', page);
                        currentPage = page;
                        
                        $('html, body').animate({ scrollTop: 0 }, 300);
                        
                        setTimeout(function() {
                            console.log('⚙️ Content initialization phase complete for:', page);
                            
                            if (page.includes('create_available_dates.php')) {
                                console.log('📅 Verifying calendar initialization...');
                                
                                setTimeout(function() {
                                    if ($('#calendar').length > 0) {
                                        if ($('#calendar').children().length === 0) {
                                            console.warn('⚠️ Calendar empty, attempting manual init...');
                                            if (window.availableDatesModule && 
                                                typeof window.availableDatesModule.generateCalendar === 'function') {
                                                const now = new Date();
                                                window.availableDatesModule.generateCalendar(now.getMonth(), now.getFullYear());
                                                window.availableDatesModule.loadExistingDates();
                                                window.availableDatesModule.checkSubmitButton();
                                            }
                                        } else {
                                            console.log('✓ Calendar initialized with', $('#calendar').children().length, 'elements');
                                        }
                                    }
                                }, 300);
                            }
                        }, 200);
                    }
                });
            }, 150);
            
            return false;
        }

        function toggleDropdown(id) {
            $("#" + id).slideToggle("fast");
        }

        function toggleProfileMenu() {
            const menu = document.getElementById("profileMenu");
            if (menu) {
                menu.style.display = (menu.style.display === "none" || menu.style.display === "") ? "block" : "none";
            }
        }

        window.addEventListener('click', function(e) {
            const trigger = document.querySelector('.profile-trigger');
            const menu = document.getElementById("profileMenu");
            if (trigger && menu && !trigger.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = "none";
            }
        });

        window.addEventListener('popstate', function(event) {
            if (currentPage) {
                console.log('⏮️ Browser navigation detected');
                const pageToLoad = currentPage;
                currentPage = null;
                loadContent(pageToLoad);
            }
        });

        window.addEventListener('beforeunload', function() {
            console.log('🚪 Page unloading, running final cleanup...');
            window.cleanupAllPages();
        });

        window.addEventListener('error', function(e) {
            console.error('💥 Global error:', e.message, 'at', e.filename + ':' + e.lineno);
        });

        window.debugLoadContent = function() {
            console.log('=== DEBUG INFO ===');
            console.log('Current page:', currentPage);
            console.log('Is loading:', isLoading);
            console.log('Intervals tracked:', window.pageCleanupRegistry.intervals.length);
            console.log('Timeouts tracked:', window.pageCleanupRegistry.timeouts.length);
            console.log('AJAX requests tracked:', window.pageCleanupRegistry.ajaxRequests.length);
            console.log('==================');
        };
        function checkNewNotifications() {
            $.ajax({
                url: 'check_notifications.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.hasNew) {
                        $('#notificationBadge').show();
                    } else {
                        $('#notificationBadge').hide();
                    }
                },
                error: function() {
                    console.log('Error checking notifications');
                }
            });
        }

        $(document).ready(function() {
            checkNewNotifications();
            setInterval(checkNewNotifications, 30000);
        });

        function toggleTasksSection() {
            const content = $('#tasks-collapsible-content');
            const icon = $('#tasks-toggle-icon');
            
            content.slideToggle(300, function() {
                if (content.is(':visible')) {
                    icon.css('transform', 'rotate(0deg)');
                } else {
                    icon.css('transform', 'rotate(-90deg)');
                }
            });
        }
        $(document).ready(function() {
            console.log('✓ Dashboard content loaded and initialized');
            
            $(document).on('click', '.task-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const transactionId = $(this).data('transaction-id');
                console.log('✓ Task card clicked, Transaction ID:', transactionId);
                
                if (transactionId) {
                    sessionStorage.setItem('highlightAppointment', transactionId);
                    console.log('✓ Stored in sessionStorage:', transactionId);
                    
                    if (typeof loadContent === 'function') {
                        loadContent('personnel_manage_appointments.php');
                    } else {
                        console.error('❌ loadContent function not available');
                        window.location.href = 'personnel_manage_appointments.php';
                    }
                } else {
                    console.error('❌ No transaction ID found on clicked element');
                }
            });
            
            if (typeof checkNewNotifications === 'function') {
                checkNewNotifications();
                setInterval(checkNewNotifications, 30000);
            }
        });
    </script>

</head>
<body id="body-pd">
    

    <div class="content-area" id="content-area">
        <div class="container-fluid">
            <div class="welcome-card">
                <h2><i class='bx bx-wave'></i> Welcome Back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>You're logged in as LGU Personnel. Manage your appointments and availability from your dashboard.</p>
            </div>

            <div class="tasks-section">
            <div class="tasks-header" style="cursor: pointer;" onclick="toggleTasksSection()">
                <h3>
                    <i class='bx bx-task'></i>
                    My Tasks Today
                    <i class='bx bx-chevron-down' id="tasks-toggle-icon" style="font-size: 1.2rem; transition: transform 0.3s ease; transform: rotate(-90deg);"></i>
                </h3>
                <span class="date-badge">
                        <i class='bx bx-calendar'></i>
                        <?php echo date('F j, Y'); ?>
                    </span>
                </div>
                
                <div id="tasks-collapsible-content" style="display: none;">
                    <div class="task-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count($today_tasks); ?></div>
                            <div class="stat-label">Scheduled Today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $completed_today; ?></div>
                            <div class="stat-label">Completed Today</div>
                        </div>
                    </div>
                
                <div class="tasks-list">
                    <?php if (!empty($today_tasks)): ?>
                        <?php foreach ($today_tasks as $task): ?>
                            <div class="task-item status-<?php echo strtolower($task['status']); ?>" 
                                data-transaction-id="<?php echo $task['transaction_id']; ?>">
                                <div class="task-main-info">
                                    <span class="task-time">
                                        <i class='bx bx-time-five'></i>
                                        <?php 
                                            $hour = (int)date('G', strtotime($task['appointment_time']));
                                            echo ($hour < 12) ? 'Morning' : 'Afternoon';
                                        ?>
                                    </span>
                                    <span class="task-name"><?php echo htmlspecialchars($task['resident_name']); ?></span>
                                    <span class="task-service-compact">
                                        <?php echo htmlspecialchars($task['service_name'] ?? 'General'); ?>
                                    </span>
                                </div>
                                <span class="task-status-badge <?php echo strtolower($task['status']); ?>">
                                    <?php echo $task['status']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($today_tasks) >= 5): ?>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="loadContent('personnel_view_appointments_status.php');">
                                    <i class='bx bx-list-ul'></i> View All Appointments
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-tasks">
                            <i class='bx bx-check-circle'></i>
                            <p>No appointments scheduled for today. You're all caught up!</p>
                            <button class="btn btn-primary btn-sm mt-2" onclick="loadContent('create_available_dates.php');">
                                <i class='bx bx-calendar-plus'></i> Create Available Dates
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <h3 class="section-title">Quick Actions</h3>

    <div class="row">
        <div class="col-lg-4 col-md-6 mb-4 feature-col">
            <div class="feature-card" onclick="loadContent('personnel_analytics.php');">
                <div class="feature-icon icon-success">
                    <i class='bx bx-line-chart'></i>
                </div>
                <h5>Dashboard Analytics</h5>
                <small>View comprehensive statistics and insights about appointments and bookings</small>
            </div>
        </div>

            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_manage_appointments.php');">
                    <div class="feature-icon icon-danger">
                        <i class='bx bx-calendar-event'></i>
                    </div>
                    <h5>View Appointments</h5>
                    <small>Review and manage all scheduled appointments with residents</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_view_appointments_status.php');">
                    <div class="feature-icon icon-primary">
                        <i class='bx bx-calendar-check'></i>
                    </div>
                    <h5>Appointments Status</h5>
                    <small>Track the status of all appointments in real-time</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('create_available_dates.php');">
                    <div class="feature-icon icon-success">
                        <i class='bx bx-calendar-plus'></i>
                    </div>
                    <h5>Create Available Dates</h5>
                    <small>Set your availability schedule for resident bookings</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" onclick="loadContent('personnel_view_feedbacks.php');">
                    <div class="feature-icon icon-warning">
                        <i class='bx bx-message-dots'></i>
                    </div>
                    <h5>View Feedback</h5>
                    <small>Read and respond to feedback from residents about services</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4 feature-col">
                <div class="feature-card" data-toggle="modal" data-target="#logoutModal">
                    <div class="feature-icon icon-secondary">
                        <i class='bx bx-log-out'></i>
                    </div>
                    <h5>Logout</h5>
                    <small>Securely sign out of your account</small>
                </div>
            </div>
        </div>
        </div>
    </div>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="logoutModalLabel"><i class="bx bx-log-out-circle mr-2"></i>Confirm Logout</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body text-center">
            <i class='bx bx-error-circle' style="font-size: 4rem; color: #ef4444;"></i>
            <p class="mt-3 mb-0">Are you sure you want to log out?</p>
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <a href="../logout.php" class="btn btn-danger">Yes, Logout</a>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
