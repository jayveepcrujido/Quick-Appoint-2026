<?php
session_start();
include '../conn.php';

if (!isset($_SESSION['auth_id'])) {
    echo "<script>alert('Please log in first.'); window.location.href='../login.php';</script>";
    exit();
}

$query = "SELECT d.*, 
                s.id AS service_id,
                s.service_name,
                r.requirement
            FROM departments d
            LEFT JOIN department_services s ON d.id = s.department_id
            LEFT JOIN service_requirements r ON s.id = r.service_id
            ORDER BY d.name ASC, s.service_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departments = [];
foreach ($rawResults as $row) {
    $deptId = $row['id'];
    if (!isset($departments[$deptId])) {
        $departments[$deptId] = [
            'id' => $deptId,
            'name' => $row['name'],
            'acronym' => $row['acronym'],
            'description' => $row['description'],
            'services' => []
        ];
    }

    $serviceName = $row['service_name'];
    $requirement = $row['requirement'];

    if ($serviceName) {
        $departments[$deptId]['services'][$serviceName][] = $requirement;
    }
}
?>

<style>
/* Card Styles */
.department-card .card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 4px 12px rgba(0, 123, 204, 0.08);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    background: #ffffff;
    overflow: hidden;
    position: relative;
}

.department-card .card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0066cc, #0099ff);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.department-card .card:hover::before {
    transform: scaleX(1);
}

.department-card .card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 28px rgba(0, 123, 204, 0.18);
}

.department-card .card-body {
    padding: 1.75rem;
    min-height: 220px;
}

.department-card .icon-box {
    width: 56px;
    height: 56px;
    font-size: 1.6rem;
    background: linear-gradient(135deg, #0066cc, #0099ff);
    border-radius: 14px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 102, 204, 0.25);
    transition: all 0.3s ease;
}

.department-card .card:hover .icon-box {
    transform: rotate(5deg) scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 102, 204, 0.35);
}

.department-card .card-title {
    font-size: 1.35rem;
    color: #1a1a1a;
    letter-spacing: -0.3px;
    font-weight: 700;
}

.department-card .card-text.fw-bold {
    background: linear-gradient(135deg, #e3f2fd, #f0f8ff);
    border: 1.5px solid #0066cc;
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    color: #0066cc;
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.department-card .card-text.text-muted {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #6c757d;
    margin-bottom: 0;
}

.department-card .card-description {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: all 0.4s ease;
    margin-bottom: 0;
}

.department-card .card:hover .card-description {
    max-height: 100px;
    opacity: 1;
    margin-top: 0.75rem;
}

.department-card .btn-outline-primary {
    border: 2px solid #0066cc;
    color: #0066cc;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.5rem 1.25rem;
    transition: all 0.3s ease;
    background: transparent;
}

.department-card .card:hover .btn-outline-primary {
    background: linear-gradient(135deg, #0066cc, #0099ff);
    color: #fff;
    border-color: transparent;
    transform: translateX(4px);
}

/* Search Bar */
#searchInput {
    border-radius: 50px 0 0 50px;
    border: 2px solid #e0e0e0;
    padding: 0.85rem 1.5rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #ffffff;
}

#searchInput:focus {
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    outline: none;
}

#clearSearch {
    border-radius: 0 50px 50px 0;
    transition: all 0.3s ease;
    border: 2px solid #dc3545;
    background: transparent;
    color: #dc3545;
    padding: 0.85rem 1.5rem;
    font-weight: 600;
}

#clearSearch:hover {
    background: #dc3545;
    color: #fff;
    border-color: #dc3545;
    transform: translateX(-2px);
}

.input-group {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border-radius: 50px;
}

/* Header Styling */
h3.mb-4 {
    font-size: 1.85rem;
    color: #1a1a1a;
    font-weight: 700;
    letter-spacing: -0.5px;
    padding-bottom: 0.75rem;
    border-bottom: 3px solid #0066cc;
    display: inline-flex;
    margin-bottom: 2rem !important;
}

h3.mb-4 i {
    font-size: 2rem;
    margin-right: 0.75rem;
    color: #0066cc;
}

/* Calendar Styles */
#calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
}

.calendar-day {
    min-height: 90px;
    padding: 8px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 14px;
    background-color: #ffffff;
    text-align: center;
    transition: all 0.3s ease;
    cursor: default;
}

.calendar-day.available {
    background: linear-gradient(135deg, #e6ffe6, #f0fff0);
    border-color: #28a745;
    cursor: pointer;
}

.calendar-day.available:hover {
    background: linear-gradient(135deg, #ccffcc, #e6ffe6);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

.calendar-day.selected {
    background: linear-gradient(135deg, #28a745, #20c997) !important;
    color: white;
    font-weight: bold;
    border-color: #28a745;
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
}

/* Transaction Modal */
#transactionModal .modal-content {
    border: none;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

#transactionModal .modal-header {
    background: linear-gradient(135deg, #0066cc, #0099ff);
    padding: 2rem 1.5rem;
    border-bottom: none;
}

#transactionModal .modal-header h4 {
    font-size: 1.5rem;
    letter-spacing: -0.3px;
}

#transactionModal .modal-header .close {
    opacity: 1;
    text-shadow: none;
    font-size: 2rem;
    font-weight: 300;
    position: absolute;
    right: 1.5rem;
    top: 1.5rem;
    transition: transform 0.2s ease;
}

#transactionModal .modal-header .close:hover {
    transform: rotate(90deg);
}

#transactionModal .modal-body {
    padding: 2.5rem 2rem;
    background: #fafbfc;
}

#transactionModal .modal-body .h5 {
    color: #495057;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

#transactionNumber {
    font-size: 2.25rem;
    font-weight: 800;
    color: #0066cc;
    letter-spacing: 2px;
    text-shadow: 0 2px 4px rgba(0, 102, 204, 0.1);
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #e3f2fd, #f0f8ff);
    border-radius: 12px;
    display: inline-block;
}

#transactionModal .alert {
    background: linear-gradient(135deg, #fff3cd, #fff8e1);
    border-left: 5px solid #ffc107 !important;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.15);
}

#transactionModal .alert i {
    font-size: 1.75rem;
    margin-top: 0;
}

#transactionModal .alert strong {
    font-size: 1rem;
    display: block;
    margin-bottom: 0.5rem;
}

#transactionModal .modal-footer {
    background: #ffffff;
    padding: 1.5rem 2rem 2rem;
    border-top: none;
}

#downloadTransactionBtn {
    background: linear-gradient(135deg, #0066cc, #0099ff);
    border: none;
    padding: 0.75rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 50px;
    box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    transition: all 0.3s ease;
}

#downloadTransactionBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
    background: linear-gradient(135deg, #0052a3, #007acc);
}

#downloadTransactionBtn i {
    font-size: 1.2rem;
}

/* Hide footer during capture */
.no-capture-capturing {
    display: none !important;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .department-card .card-body {
        padding: 1.5rem;
    }
    
    h3.mb-4 {
        font-size: 1.5rem;
    }
    
    #transactionNumber {
        font-size: 1.75rem;
        letter-spacing: 1px;
    }
    
    #transactionModal .modal-body {
        padding: 2rem 1.5rem;
    }
}
.page-header {
            background: linear-gradient(135deg, #0D92F4, #27548A);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .page-header h3 {
            color: white;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .header-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
</style>
<div class="container">
    <div class="page-header">
        <h3>
            <div class="header-icon">
                <i class="bx bx-building"></i>
            </div>
            <span>Select a Department</span>
        </h3>
        <p>Select a department to view available services and book your appointment</p>
    </div>
    
    <!-- Search Bar -->
    <div class="input-group mb-4">
        <input type="text" class="form-control h-50" id="searchInput" placeholder="Search department or service...">
        <div class="input-group-append">
            <button class="btn btn-outline-danger" id="clearSearch">
                <i class="fas fa-times-circle"></i> Clear
            </button>
        </div>
    </div>

    <!-- Department Cards -->
    <div class="row" id="departmentList">
        <?php foreach ($departments as $d): ?>
        <div class="col-md-4 mb-3 department-card">
            <a href="javascript:void(0)" 
               onclick="loadContent('resident_view_department_details.php?id=<?= urlencode($d['id']) ?>')" 
               class="text-decoration-none text-reset">
                
                <div class="card h-100 shadow border-0 rounded-3">
                    <div class="card-body d-flex flex-column justify-content-between">
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-box">
                                <i class="bx bx-building-house"></i>
                            </div>
                            <h5 class="card-title font-weight-bold mb-0 ml-3">
                                <?= htmlspecialchars($d['acronym']) ?>
                            </h5>
                        </div>

                        <p class="card-text fw-bold small flex-grow-1 border border-primary rounded px-2 py-1 text-primary">
                            <?= htmlspecialchars($d['name']) ?>
                        </p>

                        <p class="card-text text-muted small card-description">
                            <?= htmlspecialchars($d['description']) ?>
                        </p>

                        <div class="mt-3 text-right">
                            <span class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                                View Details <i class="bx bx-chevron-right"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Transaction Success Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" id="transactionModalContent">
            
            <div class="modal-header border-0 justify-content-center" 
                 style="background: linear-gradient(135deg, #0066cc, #0099ff); padding: 25px 15px;">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/logo.png" alt="LGU Logo" style="height: 40px; margin-right: 10px;">
                    <h4 class="text-white font-weight-bold mb-0">LGU Quick Appoint</h4>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body text-center p-4">
                <p class="h5 font-weight-semibold text-dark mb-2">Transaction Number:</p>
                <h3 id="transactionNumber" class="font-weight-bold text-primary mb-4">-</h3>

                <div class="alert border-0 mb-3" style="background: #fff3cd; border-left: 4px solid #ffc107 !important; text-align: left;">
                    <div class="d-flex align-items-start">
                        <i class="bx bx-error-circle" style="color: #ff9800; font-size: 1.5rem; margin-right: 12px; margin-top: 2px;"></i>
                        <div>
                            <strong style="color: #856404;">Please remember or take a screenshot of your Transaction Number.</strong>
                            <p class="mb-0 mt-1" style="color: #856404; font-size: 0.9rem;">
                                You may also download this slip and present it to the assigned personnel when requested.
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-muted mb-0 small">
                    Please bring all the necessary requirements and present them together with this slip.
                </p>
            </div>

            <div class="modal-footer border-0 justify-content-center p-4 no-capture">
                <button id="downloadTransactionBtn" class="btn btn-primary px-4 py-2 font-weight-semibold rounded-pill">
                    <i class="bx bx-download mr-2"></i> Download
                </button>
            </div>

        </div>
    </div>
</div>

<script>
$('#searchInput').on('input', function() {
    const val = $(this).val().toLowerCase();
    $('.department-card').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(val));
    });
});

$('#clearSearch').click(function() {
    $('#searchInput').val('');
    $('.department-card').show();
});

document.getElementById("downloadTransactionBtn").addEventListener("click", function() {
    const modalContent = document.getElementById("transactionModalContent");
    const footer = modalContent.querySelector(".no-capture");
    
    footer.classList.add("no-capture-capturing");

    html2canvas(modalContent, { scale: 2 }).then((canvas) => {
        footer.classList.remove("no-capture-capturing");
        const link = document.createElement("a");
        link.download = "appointment_slip.png";
        link.href = canvas.toDataURL("image/png");
        link.click();
    });
});
</script>