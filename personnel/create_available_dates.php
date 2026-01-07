<?php
if (!isset($_SESSION)) {
    session_start();
}

include '../conn.php';

if (!isset($_SESSION['auth_id']) || $_SESSION['role'] !== 'LGU Personnel') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please refresh the page.']);
        exit();
    } else {
        echo "<div class='alert alert-danger'>Session expired. Please <a href='javascript:location.reload()'>refresh the page</a>.</div>";
        exit();
    }
}

$authId = $_SESSION['auth_id'];

try {
    $stmt = $pdo->prepare("SELECT department_id FROM lgu_personnel WHERE auth_id = ?");
    $stmt->execute([$authId]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personnel || !$personnel['department_id']) {
        die("<div class='alert alert-danger'>Assigned department not found.</div>");
    }

    $departmentId = $personnel['department_id'];
} catch (Exception $e) {
    die("<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['available_date'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO available_dates (department_id, date_time, status, created_at, am_slots, pm_slots)
            VALUES (?, ?, 'available', NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                am_slots = VALUES(am_slots), 
                pm_slots = VALUES(pm_slots)
        ");

        foreach ($_POST['available_date'] as $date => $entry) {
            $am = (int)$entry['am'];
            $pm = (int)$entry['pm'];
            $dateObj = new DateTime($date);
            $standardizedDate = $dateObj->format('Y-m-d') . " 00:00:00";

            $stmt->execute([$departmentId, $standardizedDate, $am, $pm]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Available dates and slots saved successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_date'])) {
    try {
        $deleteDate = $_POST['delete_date'];
        $stmt = $pdo->prepare("DELETE FROM available_dates WHERE department_id = ? AND DATE(date_time) = ?");
        $stmt->execute([$departmentId, $deleteDate]);
        
        echo json_encode(['status' => 'success', 'message' => 'Date deleted successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting date: ' . $e->getMessage()]);
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT date_time, DATE(date_time) as date, am_slots, pm_slots, am_booked, pm_booked 
    FROM available_dates 
    WHERE department_id = ?
    ORDER BY date_time ASC
");
$stmt->execute([$departmentId]);

$availableDates = [];
$existingDates = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['date'];
    $availableDates[$date] = [
        'am_slots'   => (int)$row['am_slots'],
        'pm_slots'   => (int)$row['pm_slots'],
        'am_booked'  => (int)$row['am_booked'],
        'pm_booked'  => (int)$row['pm_booked']
    ];
    $existingDates[] = $date;
}
?>

<style>
/* Page Header - Professional Gradient Style */
.page-header,
.card {
    max-width: 1000px; /* Same width for both */
    margin-left: auto;
    margin-right: auto;
}

.page-header {
    background: linear-gradient(135deg, #0D92F4, #27548A);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
    /* margin-top: 0rem; */
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
}

.page-header h2,
.page-header h4 {
    color: white;
    font-weight: 700;
    margin: 0;
    font-size: 2rem;
    position: relative;
    z-index: 1;
}

.page-header p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0.5rem 0 0 0;
    position: relative;
    z-index: 1;
}

/* Card Container - Professional (No Hover Effect) */
.card {
    border: none;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: none !important;
}

.card:hover {
    transform: none !important;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3) !important;
}

.card-body {
    padding: 30px;
}

/* Enhanced Calendar Styles */
#calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fc;
    border-radius: 8px;
}

.calendar-header {
    font-weight: 600;
    text-align: center;  
    padding: 8px 4px;
    background: white;
    color: #27548A;
    border: 2px solid #0D92F4; 
    border-radius: 6px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.calendar-day {
    padding: 8px 4px;
    text-align: center;
    border: 2px solid #e3e8ef;
    cursor: pointer;
    background: white;
    min-height: 60px;
    position: relative;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.calendar-day:hover:not(.disabled) {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.calendar-day:active:not(.disabled) {
    transform: translateY(-1px);
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 80%); 
    color: white;
    border-color: #0D92F4;
}

.calendar-day.selected {
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
    color: white;
    border-color: #0D92F4;
    transform: scale(1.05);
    box-shadow: 0 8px 16px rgba(13, 146, 244, 0.5);
}

.calendar-day.has-data {
    background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
    color: white;
    border-color: #11998e;
}

.calendar-day.has-data:hover:not(.disabled) {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 16px rgba(17, 153, 142, 0.4);
}

.calendar-day.has-data:active {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    transform: translateY(-1px);
}

.calendar-day.disabled {
    background: #f1f3f5;
    color: #adb5bd;
    cursor: not-allowed;
    border-color: #e9ecef;
    opacity: 0.5;
    box-shadow: none;
}

.calendar-day.disabled:active {
    transform: none;
    background: #f1f3f5;
}

.calendar-day .badge {
    display: block;
    font-size: 8px;
    margin-top: 4px;
    background: rgba(255, 255, 255, 0.9);
    color: #11998e;
    padding: 2px 4px;
    border-radius: 10px;
    font-weight: 600;
    letter-spacing: 0.2px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    white-space: nowrap;
}

.calendar-day.selected .badge,
.calendar-day:active:not(.disabled) .badge {
    background: white;
    color: #0D92F4;
}

.calendar-day.disabled .badge {
    background: #dee2e6;
    color: #868e96;
    box-shadow: none;
}

/* Month Navigation */
.month-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.month-nav #calendar-header {
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%); 
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0 15px;
}

.month-nav .btn {
    border-radius: 6px;
    padding: 6px 12px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.month-nav .btn:active {
    transform: scale(0.95);
}

/* Selected dates input area */
#dateInputs {
    min-height: 60px;
    background: #f8f9fc;
    border: 2px dashed #dee2e6;
    border-radius: 10px;
}

#dateInputs > div {
    background: white;
    border: 2px solid #e3e8ef;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Form styling */
.form-control:focus {
    border-color: #0D92F4;
    box-shadow: 0 0 0 0.2rem rgba(13, 146, 244, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%);
    border: none;
    border-radius: 8px;
    padding: 10px 30px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 4px 8px rgba(13, 146, 244, 0.3);
}

.btn-primary:active {
    transform: scale(0.98);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-primary:disabled:active {
    transform: none;
}

/* Modal styling */
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    border-radius: 15px 15px 0 0;
    background: linear-gradient(135deg, #0D92F4, #27548A);
    color: white;
    border: none;
}

.modal-header.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    border: none;
    padding: 20px 30px;
}

/* Legend for calendar */
.calendar-legend {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 25px;
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    flex-wrap: wrap;
}

.calendar-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.legend-box {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    border: 2px solid #e3e8ef;
}

.legend-available {
    background: white;
    border-color: #e3e8ef;
}

.legend-selected {
    background: linear-gradient(135deg, #0D92F4 0%, #27548A 100%); 
    border-color: #0D92F4;
}

.legend-configured {
    background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
    border-color: #11998e;
}

.legend-unavailable {
    background: #f1f3f5;
    border-color: #e9ecef;
    opacity: 0.5;
}

/* Response Message Animations */
#response-msg {
    position: relative;
    z-index: 1;
}

#response-msg .alert {
    animation: slideInDown 0.3s ease;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert {
    margin-bottom: 1rem;
    border-radius: 8px;
}

.alert-dismissible .close {
    padding: 0.75rem 1.25rem;
}

/* Modal Transitions */
.modal.fade {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.modal.fade.show {
    opacity: 1;
}

.modal-backdrop.fade {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.modal-backdrop.fade.show {
    opacity: 0.5;
}

body.modal-open {
    overflow: hidden;
}

/* Responsive Design - Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
    #calendar {
        gap: 5px;
        padding: 10px;
    }
    
    .calendar-header {
        padding: 6px 3px;
        font-size: 0.7rem;
    }
    
    .calendar-day {
        min-height: 60px;
        padding: 6px 3px;
        font-size: 0.9rem;
    }
    
    .calendar-day .badge {
        font-size: 7px;
        padding: 2px 4px;
    }
    
    .month-nav {
        padding: 12px;
    }
    
    .month-nav #calendar-header {
        font-size: 1.1rem;
    }
    
    .month-nav .btn {
        padding: 7px 14px;
        font-size: 0.9rem;
    }
    
    .calendar-legend {
        gap: 15px;
        padding: 12px;
    }
    
    .calendar-legend-item {
        font-size: 0.85rem;
    }
    
    .card-body {
        padding: 20px;
    }
}

/* Responsive Design - Mobile */
@media (max-width: 768px) {
    .page-header h2,
    .page-header h4 {
        font-size: 1.5rem;
    }
    
    #calendar {
        gap: 3px;
        padding: 5px;
    }
    
    .calendar-header {
        padding: 4px 1px;
        font-size: 0.6rem;
        letter-spacing: 0px;
    }
    
    .calendar-day {
        min-height: 50px;
        padding: 4px 1px;
        font-size: 0.75rem;
        border-width: 1px;
    }
    
    .calendar-day .badge {
        font-size: 6px;
        padding: 1px 2px;
        margin-top: 2px;
        letter-spacing: 0px;
    }
    
    .month-nav {
        flex-direction: row;
        gap: 5px;
        padding: 8px;
    }
    
    .month-nav #calendar-header {
        font-size: 0.9rem;
        margin: 0 5px;
        flex: 1;
    }
    
    .month-nav .btn {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
    
    .calendar-legend {
        gap: 8px;
        padding: 8px;
        flex-wrap: wrap;
    }
    
    .calendar-legend-item {
        font-size: 0.75rem;
        flex: 0 0 calc(50% - 4px);
    }
    
    .legend-box {
        width: 16px;
        height: 16px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    #dateInputs > div {
        padding: 10px !important;
    }
    
    #dateInputs > div strong {
        font-size: 0.95rem;
    }
    
    .form-row {
        gap: 5px;
    }
    
    .form-row .col {
        flex: 1;
    }
    
    .form-row .col label {
        font-size: 0.85rem;
    }
    
    .form-row .col input {
        font-size: 0.9rem;
    }
    
    .btn-primary {
        width: 100%;
        padding: 10px;
        font-size: 0.9rem;
    }
    
    h4 {
        font-size: 1.1rem;
    }
    
    h5 {
        font-size: 1rem;
    }
    
    .modal-body {
        padding: 20px 15px;
    }
    
    .modal-header h5 {
        font-size: 1rem;
    }
    
    .remove-btn,
    .btn-action {
        font-size: 0.8rem;
        padding: 4px 8px !important;
        min-width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Responsive Design - Small Mobile */
@media (max-width: 480px) {
    #calendar {
        gap: 2px;
        padding: 3px;
    }
    
    .calendar-day {
        min-height: 45px;
        font-size: 0.7rem;
        padding: 3px 1px;
    }
    
    .calendar-day .badge {
        font-size: 5px;
        padding: 1px 2px;
        margin-top: 1px;
    }
    
    .month-nav {
        padding: 6px;
        gap: 3px;
    }
    
    .month-nav #calendar-header {
        font-size: 0.85rem;
        margin: 0 3px;
    }
    
    .month-nav .btn {
        padding: 5px 8px;
        font-size: 0.75rem;
    }
    
    .month-nav .btn i {
        font-size: 0.7rem;
    }
    
    .calendar-legend {
        padding: 6px;
        gap: 5px;
    }
    
    .calendar-legend-item {
        font-size: 0.7rem;
        flex: 0 0 calc(50% - 3px);
    }
    
    .legend-box {
        width: 14px;
        height: 14px;
    }
    
    h4 {
        font-size: 1rem;
    }
    
    h5 {
        font-size: 0.9rem;
    }
    
    .card-body {
        padding: 10px;
    }
    
    #dateInputs > div {
        padding: 8px !important;
    }
    
    #dateInputs > div strong {
        font-size: 0.85rem;
    }
    
    .form-row .col label {
        font-size: 0.75rem;
    }
    
    .form-row .col input {
        font-size: 0.85rem;
        padding: 6px;
    }
    
    .remove-btn {
        font-size: 0.7rem;
        padding: 3px 6px !important;
    }
    
    .btn-primary {
        padding: 8px;
        font-size: 0.85rem;
    }
    
    .modal-body {
        padding: 15px 10px;
    }
    
    .modal-header {
        padding: 10px 15px;
    }
    
    .modal-header h5 {
        font-size: 0.9rem;
    }
    
    .modal-footer {
        padding: 10px 15px;
    }
    
    .modal-footer .btn {
        font-size: 0.85rem;
        padding: 6px 12px;
    }
}

/* Responsive Design - Extra Small Mobile */
@media (max-width: 360px) {
    .calendar-day {
        min-height: 40px;
        font-size: 0.65rem;
    }
    
    .calendar-day .badge {
        font-size: 4px;
        padding: 1px;
        margin-top: 1px;
    }
    
    .calendar-header {
        font-size: 0.55rem;
        padding: 3px 1px;
    }
    
    .month-nav #calendar-header {
        font-size: 0.8rem;
    }
    
    .month-nav .btn {
        padding: 4px 6px;
        font-size: 0.7rem;
    }
}
/* Bulk Date Selection Styles */
.bulk-date-section {
  border: 2px solid #e3e8ef;
  transition: all 0.3s ease;
}

.bulk-date-section:hover {
  border-color: #0D92F4;
  box-shadow: 0 4px 12px rgba(13, 146, 244, 0.2) !important;
}

.bulk-date-section h6 {
  margin-bottom: 1rem;
}

.bulk-date-section .form-control-sm {
  border-radius: 6px;
  border: 1px solid #dee2e6;
  transition: border-color 0.2s ease;
}

.bulk-date-section .form-control-sm:focus {
  border-color: #0D92F4;
  box-shadow: 0 0 0 0.15rem rgba(13, 146, 244, 0.25);
}

/* Responsive adjustments for bulk section */
@media (max-width: 768px) {
  .bulk-date-section {
    padding: 15px !important;
  }
  
  .bulk-date-section h6 {
    font-size: 0.95rem;
  }
  
  .bulk-date-section .small,
  .bulk-date-section small {
    font-size: 0.75rem;
  }
}
</style>
</style>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<div class="page-header">
    <h2><i class="fas fa-calendar mr-2"></i> Create Available Dates</h2>
    <p>Manage your calendar by creating and configuring availability</p>
</div>
<div class="container mb-5">
  <div class="card shadow-sm">
    <div class="card-body">

      <div id="response-msg"></div>

      <form method="post" id="available-dates-form">
        <div class="form-group">
          <label for="selected-dates" class="font-weight-bold">Selected Dates and Slots:</label>
          <div id="dateInputs" class="p-2 bg-light rounded border"></div>
        </div>

<!-- Bulk Date Selection Section -->
<div class="bulk-date-section mb-3 p-3" style="background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
  <h6 class="font-weight-bold mb-3" style="color: #0D92F4;">
    <i class="fas fa-calendar-plus mr-2"></i>Bulk Create Available Dates
  </h6>
  <div class="row">
    <div class="col-md-3 col-6 mb-2">
      <label class="font-weight-semibold small">Start Date</label>
      <input type="date" id="bulkStartDate" class="form-control form-control-sm">
    </div>
    <div class="col-md-3 col-6 mb-2">
      <label class="font-weight-semibold small">End Date</label>
      <input type="date" id="bulkEndDate" class="form-control form-control-sm">
    </div>
    <div class="col-md-2 col-6 mb-2">
      <label class="font-weight-semibold small">AM Slots</label>
      <input type="number" id="bulkAMSlots" class="form-control form-control-sm" min="0" value="5">
    </div>
    <div class="col-md-2 col-6 mb-2">
      <label class="font-weight-semibold small">PM Slots</label>
      <input type="number" id="bulkPMSlots" class="form-control form-control-sm" min="0" value="5">
    </div>
    <div class="col-md-2 col-12 mb-2">
      <label class="font-weight-semibold small d-none d-md-block">&nbsp;</label>
      <button type="button" id="bulkAddDatesBtn" class="btn btn-primary btn-sm w-100">
        <i class="fas fa-plus-circle mr-1"></i>Add Dates
      </button>
    </div>
  </div>
  <small class="text-muted">
    <i class="fas fa-info-circle mr-1"></i>Select a date range to quickly add multiple available dates. Weekends will be automatically skipped.
  </small>
</div>

        <div class="form-group">
          <label class="font-weight-bold">Select Dates from Calendar</label>
          
          <!-- Calendar Legend -->
          <div class="calendar-legend">
            <div class="calendar-legend-item">
              <div class="legend-box legend-available"></div>
              <span>Available</span>
            </div>
            <div class="calendar-legend-item">
              <div class="legend-box legend-selected"></div>
              <span>Selected</span>
            </div>
            <div class="calendar-legend-item">
              <div class="legend-box legend-configured"></div>
              <span>Already Configured</span>
            </div>
            <div class="calendar-legend-item">
              <div class="legend-box legend-unavailable"></div>
              <span>Unavailable/Past</span>
            </div>
          </div>
          
          <div class="month-nav">
            <button type="button" id="prev" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-chevron-left"></i> Previous
            </button>
            <h5 id="calendar-header" class="mb-0 text-center font-weight-bold"></h5>
            <button type="button" id="next" class="btn btn-outline-secondary btn-sm">
              Next <i class="fas fa-chevron-right"></i>
            </button>
          </div>

          <div id="calendar"></div>
        </div>

        <div class="text-right">
          <button type="submit" class="btn btn-primary mt-3 px-4" id="submitDatesBtn" disabled>
            <i class="fas fa-check-circle mr-1"></i> Submit Available Dates
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-check-circle mr-2"></i>Success
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
        <p class="mt-3 mb-0" id="successMessage" style="font-size: 1.1rem;"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-edit mr-2"></i>Edit Date Slots
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editDateValue">
        <div class="form-group">
          <label class="font-weight-bold">Date:</label>
          <p id="editDateDisplay" class="form-control-plaintext font-weight-bold" style="color: #0D92F4;"></p>
        </div>
        <div class="form-row">
          <div class="col-md-6">
            <label class="font-weight-bold">AM Slots</label>
            <input type="number" id="editAMSlots" class="form-control" min="0">
            <small class="text-muted">Currently booked: <span id="editAMBooked">0</span></small>
          </div>
          <div class="col-md-6">
            <label class="font-weight-bold">PM Slots</label>
            <input type="number" id="editPMSlots" class="form-control" min="0">
            <small class="text-muted">Currently booked: <span id="editPMBooked">0</span></small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="deleteFromModalBtn">
          <i class="fas fa-trash mr-1"></i>Delete Date
        </button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveEditBtn">
          <i class="fas fa-save mr-1"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white">
          <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <i class="fas fa-trash-alt text-danger" style="font-size: 4rem;"></i>
        <p class="mt-3 mb-0" style="font-size: 1.1rem;">Are you sure you want to delete this date?</p>
        <p class="text-muted" id="deleteMessage"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="fas fa-trash mr-1"></i>Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  'use strict';
  
  if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
    return;
  }

  const NAMESPACE = 'availDates_' + Date.now();
  console.log('Initializing available dates module with namespace:', NAMESPACE);

  if (window.availableDatesCleanup) {
    console.log('Cleaning up previous instance...');
    try {
      window.availableDatesCleanup();
    } catch (e) {
      console.error('Error during cleanup:', e);
    }
  }

  const existingDatesData = <?php echo json_encode($existingDates); ?>;
  const availableDatesData = <?php echo json_encode($availableDates); ?>;

  let currentMonth = new Date().getMonth();
  let currentYear = new Date().getFullYear();
  let dateToDelete = null;
  let isInitialized = false;
  let cleanupCompleted = false;

  window.availableDatesModule = window.availableDatesModule || {};

  function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
      console.error('Modal not found:', modalId);
      return;
    }

    modal.style.display = 'block';
    modal.classList.add('fade');
    requestAnimationFrame(() => {
      modal.classList.add('show');
    });

    document.body.classList.add('modal-open');

    let backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade';
    document.body.appendChild(backdrop);

    requestAnimationFrame(() => {
      backdrop.classList.add('show');
    });
  }

  function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove('show');

    const backdrop = document.querySelector('.modal-backdrop');

    if (backdrop) {
      backdrop.classList.remove('show');
      backdrop.addEventListener('transitionend', () => backdrop.remove(), { once: true });
    }

    modal.addEventListener('transitionend', function onHide() {
      modal.style.display = 'none';
      modal.removeEventListener('transitionend', onHide);
      document.body.classList.remove('modal-open');
    }, { once: true });
  }

  function generateCalendar(month, year) {
    const calendar = $('#calendar');
    if (calendar.length === 0) {
      console.error('Calendar element not found');
      return;
    }
    
    calendar.empty();

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay = new Date(year, month, 1);
    const lastDate = new Date(year, month + 1, 0).getDate();
    const startDay = firstDay.getDay();
    const monthName = firstDay.toLocaleString('default', { month: 'long' });
    $('#calendar-header').text(`${monthName} ${year}`);

    const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    daysOfWeek.forEach(day => calendar.append(`<div class="calendar-header">${day}</div>`));

    for (let i = 0; i < startDay; i++) calendar.append('<div></div>');

    for (let date = 1; date <= lastDate; date++) {
      const dateObj = new Date(year, month, date);
      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
      const cell = $(`<div class="calendar-day" data-date="${dateStr}">${date}</div>`);

      const amData = availableDatesData[dateStr]?.am_slots ?? 0;
      const amUsed = availableDatesData[dateStr]?.am_booked ?? 0;
      const pmData = availableDatesData[dateStr]?.pm_slots ?? 0;
      const pmUsed = availableDatesData[dateStr]?.pm_booked ?? 0;

      const remainingAM = amData - amUsed;
      const remainingPM = pmData - pmUsed;

      cell.append(`<div class="badge">AM: ${remainingAM} PM: ${remainingPM}</div>`);

      if (dateObj < today || dateObj.getDay() === 0 || dateObj.getDay() === 6) {
        cell.addClass('disabled');
        cell.attr('title', 
          dateObj < today ? 'Date has passed' : 'Weekends unavailable'
        );
      } else if (existingDatesData.includes(dateStr)) {
        cell.addClass('has-data');
        cell.attr('title', 'Click to edit this date');
      }
      calendar.append(cell);
    }
    
    console.log('Calendar generated for', monthName, year);
  }

  function addDateInput(dateStr) {
    if ($(`#dateInputs > div[data-date="${dateStr}"]`).length > 0) {
      console.log('Date input already exists for:', dateStr);
      return;
    }
    
    const inputHTML = `
      <div class="mb-2 border p-3" data-date="${dateStr}" style="border-radius: 8px; border-left: 4px solid #0D92F4 !important;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong style="color: #0D92F4; font-size: 1.1rem;">${dateStr}</strong>
          <button type="button" class="btn btn-sm btn-danger remove-date-btn" data-date="${dateStr}" style="border-radius: 6px;">
            <i class="fas fa-times"></i> Remove
          </button>
        </div>
        <div class="form-row">
          <div class="col">
            <label style="font-weight: 600; color: #495057;">AM Slots</label>
            <input type="number" name="available_date[${dateStr}][am]" class="form-control" min="0" value="5" style="border-radius: 6px;">
          </div>
          <div class="col">
            <label style="font-weight: 600; color: #495057;">PM Slots</label>
            <input type="number" name="available_date[${dateStr}][pm]" class="form-control" min="0" value="5" style="border-radius: 6px;">
          </div>
        </div>
        <input type="hidden" name="available_date[${dateStr}][date]" value="${dateStr}">
      </div>`;
    $('#dateInputs').append(inputHTML);
    checkSubmitButton();
  }

  function checkSubmitButton() {
    const hasSelectedDates = $('#dateInputs > div').length > 0;
    $('#submitDatesBtn').prop('disabled', !hasSelectedDates);
  }

  function openEditModal(dateStr) {
    console.log('Opening edit modal for:', dateStr);
    const data = availableDatesData[dateStr];
    if (!data) {
      console.error('No data found for date:', dateStr);
      alert('No data found for the selected date.');
      return;
    }
    $('#editDateValue').val(dateStr);
    $('#editDateDisplay').text(dateStr);
    $('#editAMSlots').val(data.am_slots);
    $('#editPMSlots').val(data.pm_slots);
    $('#editAMBooked').text(data.am_booked);
    $('#editPMBooked').text(data.pm_booked);
    
    showModal('editModal');
  }

  function showSuccessModal(message) {
    $('#successMessage').text(message);
    showModal('successModal');
  }

  function addBulkDates() {
    const startDate = $('#bulkStartDate').val();
    const endDate = $('#bulkEndDate').val();
    const amSlots = parseInt($('#bulkAMSlots').val()) || 0;
    const pmSlots = parseInt($('#bulkPMSlots').val()) || 0;
    
    if (!startDate || !endDate) {
      alert('Please select both start and end dates.');
      return;
    }
    
    if (amSlots < 0 || pmSlots < 0) {
      alert('Slots cannot be negative.');
      return;
    }
    
    if (amSlots === 0 && pmSlots === 0) {
      alert('Please set at least one slot (AM or PM).');
      return;
    }
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (start > end) {
      alert('Start date must be before or equal to end date.');
      return;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    let addedCount = 0;
    let skippedCount = 0;
    let current = new Date(start);
    
    while (current <= end) {
      if (current.getDay() !== 0 && current.getDay() !== 6) {
        if (current >= today) {
          const dateStr = current.toISOString().split('T')[0];
          
          if ($(`#dateInputs > div[data-date="${dateStr}"]`).length === 0) {
            if (existingDatesData.includes(dateStr)) {
              skippedCount++;
            } else {
              $(`.calendar-day[data-date="${dateStr}"]`).addClass('selected');
              
              const inputHTML = `
                <div class="mb-2 border p-3" data-date="${dateStr}" style="border-radius: 8px; border-left: 4px solid #0D92F4 !important;">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="color: #0D92F4; font-size: 1.1rem;">${dateStr}</strong>
                    <button type="button" class="btn btn-sm btn-danger remove-date-btn" data-date="${dateStr}" style="border-radius: 6px;">
                      <i class="fas fa-times"></i> Remove
                    </button>
                  </div>
                  <div class="form-row">
                    <div class="col">
                      <label style="font-weight: 600; color: #495057;">AM Slots</label>
                      <input type="number" name="available_date[${dateStr}][am]" class="form-control" min="0" value="${amSlots}" style="border-radius: 6px;">
                    </div>
                    <div class="col">
                      <label style="font-weight: 600; color: #495057;">PM Slots</label>
                      <input type="number" name="available_date[${dateStr}][pm]" class="form-control" min="0" value="${pmSlots}" style="border-radius: 6px;">
                    </div>
                  </div>
                  <input type="hidden" name="available_date[${dateStr}][date]" value="${dateStr}">
                </div>`;
              $('#dateInputs').append(inputHTML);
              addedCount++;
            }
          }
        }
      }
      
      current.setDate(current.getDate() + 1);
    }
    
    checkSubmitButton();
    
    let message = '';
    if (addedCount > 0) {
      message += `${addedCount} date(s) added successfully!`;
    }
    if (skippedCount > 0) {
      message += ` ${skippedCount} date(s) skipped (already configured).`;
    }
    if (addedCount === 0 && skippedCount === 0) {
      message = 'No valid dates found in the selected range.';
    }
    
    $('#response-msg').html(`<div class="alert alert-${addedCount > 0 ? 'success' : 'warning'} alert-dismissible fade show">
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      ${message}
    </div>`);
    
    setTimeout(() => {
      $('#response-msg').empty();
    }, 4000);
    
    $('#bulkStartDate').val('');
    $('#bulkEndDate').val('');
    $('#bulkAMSlots').val('5');
    $('#bulkPMSlots').val('5');
  }

  window.availableDatesModule.generateCalendar = generateCalendar;
  window.availableDatesModule.addDateInput = addDateInput;
  window.availableDatesModule.checkSubmitButton = checkSubmitButton;
  window.availableDatesModule.openEditModal = openEditModal;
  window.availableDatesModule.showSuccessModal = showSuccessModal;
  window.availableDatesModule.addBulkDates = addBulkDates;

  function setupEventHandlers() {
    if (isInitialized) {
      console.log('Event handlers already initialized, skipping...');
      return;
    }
    
    console.log('Setting up event handlers with namespace:', NAMESPACE);
    
    $(document).off('click.' + NAMESPACE).on('click.' + NAMESPACE, '.calendar-day', function(e) {
      if ($('#calendar').length === 0) return;
      
      e.preventDefault();
      e.stopPropagation();
      
      const $cell = $(this);
      const dateStr = $cell.attr('data-date');
      
      if (!dateStr || $cell.hasClass('disabled')) {
        return;
      }
      
      if ($cell.hasClass('has-data')) {
        openEditModal(dateStr);
      } else {
        if ($cell.hasClass('selected')) {
          $cell.removeClass('selected');
          $(`#dateInputs > div[data-date="${dateStr}"]`).remove();
          checkSubmitButton();
        } else {
          $cell.addClass('selected');
          addDateInput(dateStr);
        }
      }
    });

    $(document).off('click.removeBtn_' + NAMESPACE).on('click.removeBtn_' + NAMESPACE, '.remove-date-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const date = $(this).attr('data-date');
      $(`.calendar-day[data-date="${date}"]`).removeClass('selected');
      $(`#dateInputs > div[data-date="${date}"]`).remove();
      checkSubmitButton();
    });

    $(document).off('click.prevMonth_' + NAMESPACE).on('click.prevMonth_' + NAMESPACE, '#prev', function(e) {
      e.preventDefault();
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      generateCalendar(currentMonth, currentYear);
    });

    $(document).off('click.nextMonth_' + NAMESPACE).on('click.nextMonth_' + NAMESPACE, '#next', function(e) {
      e.preventDefault();
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      generateCalendar(currentMonth, currentYear);
    });

    $(document).off('click.bulkAdd_' + NAMESPACE).on('click.bulkAdd_' + NAMESPACE, '#bulkAddDatesBtn', function(e) {
      e.preventDefault();
      addBulkDates();
    });

    $(document).off('click.deleteFromModal_' + NAMESPACE).on('click.deleteFromModal_' + NAMESPACE, '#deleteFromModalBtn', function(e) {
      e.preventDefault();
      dateToDelete = $('#editDateValue').val();
      
      if (!dateToDelete) return;
      
      const dataToDelete = availableDatesData[dateToDelete];
      if (dataToDelete) {
        $('#deleteMessage').html(`<strong>${dateToDelete}</strong><br>AM: ${dataToDelete.am_booked}/${dataToDelete.am_slots} booked | PM: ${dataToDelete.pm_booked}/${dataToDelete.pm_slots} booked`);
        hideModal('editModal');
        setTimeout(() => {
          showModal('deleteModal');
        }, 300);
      }
    });

    $(document).off('click.saveEdit_' + NAMESPACE).on('click.saveEdit_' + NAMESPACE, '#saveEditBtn', function(e) {
      e.preventDefault();
      const date = $('#editDateValue').val();
      const amSlots = parseInt($('#editAMSlots').val());
      const pmSlots = parseInt($('#editPMSlots').val());
      const amBooked = parseInt($('#editAMBooked').text());
      const pmBooked = parseInt($('#editPMBooked').text());
      
      if (amSlots < amBooked) {
        alert(`AM slots cannot be less than already booked slots (${amBooked})`);
        return;
      }
      
      if (pmSlots < pmBooked) {
        alert(`PM slots cannot be less than already booked slots (${pmBooked})`);
        return;
      }
      
      const formData = new FormData();
      formData.append(`available_date[${date}][am]`, amSlots);
      formData.append(`available_date[${date}][pm]`, pmSlots);
      formData.append(`available_date[${date}][date]`, date);
      
      $.ajax({
        url: 'create_available_dates.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
          let result;
          try { 
            result = JSON.parse(res); 
          } catch { 
            alert('Unexpected server response.');
            return; 
          }
          
          if (result.status === 'success') {
            availableDatesData[date] = {
              am_slots: amSlots,
              pm_slots: pmSlots,
              am_booked: amBooked,
              pm_booked: pmBooked
            };
            
            if (!existingDatesData.includes(date)) {
              existingDatesData.push(date);
            }
            
            hideModal('editModal');
            
            setTimeout(() => {
              generateCalendar(currentMonth, currentYear);
              showSuccessModal(result.message);

              setTimeout(() => {
                hideModal('successModal');
              }, 2000);
            }, 300);
          } else {
            alert(result.message);
          }
        },
        error: function() {
          alert('Error updating date. Please try again.');
        }
      });
    });

    $(document).off('click.confirmDel_' + NAMESPACE).on('click.confirmDel_' + NAMESPACE, '#confirmDeleteBtn', function(e) {
      e.preventDefault();
      if (!dateToDelete) return;
      
      $.ajax({
        url: 'create_available_dates.php',
        method: 'POST',
        data: { delete_date: dateToDelete },
        success: function(res) {
          let result;
          try { 
            result = JSON.parse(res); 
          } catch { 
            alert('Unexpected server response.');
            return; 
          }
          
          if (result.status === 'success') {
            delete availableDatesData[dateToDelete];
            const index = existingDatesData.indexOf(dateToDelete);
            if (index > -1) {
              existingDatesData.splice(index, 1);
            }
            
            hideModal('deleteModal');
            
            setTimeout(() => {
              generateCalendar(currentMonth, currentYear);
              showSuccessModal(result.message);
              
              setTimeout(() => {
                hideModal('successModal');
              }, 2000);
            }, 300);
            
            dateToDelete = null;
          } else {
            alert(result.message);
          }
        },
        error: function() {
          alert('Error deleting date. Please try again.');
        }
      });
    });

    $(document).off('submit.form_' + NAMESPACE).on('submit.form_' + NAMESPACE, '#available-dates-form', function(e) {
      e.preventDefault();
      
      if ($('#dateInputs > div').length === 0) {
        $('#response-msg').html('<div class="alert alert-warning alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Please select at least one date before submitting.</div>');
        setTimeout(() => {
          $('#response-msg').empty();
        }, 3000);
        return false;
      }
      
      $('#submitDatesBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...');
      
      $.ajax({
        url: 'create_available_dates.php',
        method: 'POST',
        data: $(this).serialize(),
        success: function(res) {
          let result;
          try { 
            result = JSON.parse(res); 
          } catch { 
            $('#response-msg').html('<div class="alert alert-danger alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Unexpected server response.</div>'); 
            $('#submitDatesBtn').prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Submit Available Dates');
            return; 
          }
          
          if (result.status === 'success') {
            $('#dateInputs > div').each(function() {
              const date = $(this).attr('data-date');
              const amSlots = parseInt($(this).find('input[name*="[am]"]').val());
              const pmSlots = parseInt($(this).find('input[name*="[pm]"]').val());
              
              availableDatesData[date] = {
                am_slots: amSlots,
                pm_slots: pmSlots,
                am_booked: 0,
                pm_booked: 0
              };
              
              if (!existingDatesData.includes(date)) {
                existingDatesData.push(date);
              }
            });
            
            $('#dateInputs').empty();
            $('.calendar-day.selected').removeClass('selected');
            checkSubmitButton();
            
            $('#response-msg').html('<div class="alert alert-success alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>' + result.message + '</div>');
            
            generateCalendar(currentMonth, currentYear);
            
            $('#submitDatesBtn').prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Submit Available Dates');
            
            setTimeout(() => {
              $('#response-msg').empty();
            }, 3000);
          } else if (result.message && result.message.includes('Session expired')) {
            alert('Your session has expired. Please refresh the page.');
            location.reload();
          } else {
            $('#response-msg').html('<div class="alert alert-danger alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>' + result.message + '</div>');
            $('#submitDatesBtn').prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Submit Available Dates');
          }
        },
        error: function(xhr) {
          if (xhr.status === 403) {
            alert('Your session has expired. Please refresh the page.');
            location.reload();
          } else {
            $('#response-msg').html('<div class="alert alert-danger alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>Error submitting form. Please try again.</div>');
            $('#submitDatesBtn').prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Submit Available Dates');
          }
        }
      });
    });

    document.querySelectorAll('.modal .close, .modal button[data-dismiss="modal"]').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const modal = this.closest('.modal');
        if (modal) {
          hideModal(modal.id);
        }
      });
    });

    isInitialized = true;
    console.log('Event handlers setup complete');
  }

  function initializeCalendar() {
    const maxAttempts = 20;
    let attempts = 0;
    
    function tryInit() {
      attempts++;
      
      if ($('#calendar').length > 0) {
        console.log('Calendar element found on attempt', attempts);
        generateCalendar(currentMonth, currentYear);
        checkSubmitButton();
        
        const today = new Date().toISOString().split('T')[0];
        $('#bulkStartDate, #bulkEndDate').attr('min', today);
        
        setupEventHandlers();
        return true;
      } else if (attempts < maxAttempts) {
        console.log('Calendar element not found, attempt', attempts + '/' + maxAttempts);
        setTimeout(tryInit, 100);
        return false;
      } else {
        console.error('Failed to find calendar element after', maxAttempts, 'attempts');
        return false;
      }
    }
    
    tryInit();
  }

  window.availableDatesCleanup = function() {
    if (cleanupCompleted) {
      console.log('Cleanup already completed');
      return;
    }
    
    console.log('Running cleanup for namespace:', NAMESPACE);
    
    $(document).off('.' + NAMESPACE);
    $(document).off('.removeBtn_' + NAMESPACE);
    $(document).off('.prevMonth_' + NAMESPACE);
    $(document).off('.nextMonth_' + NAMESPACE);
    $(document).off('.bulkAdd_' + NAMESPACE);
    $(document).off('.deleteFromModal_' + NAMESPACE);
    $(document).off('.saveEdit_' + NAMESPACE);
    $(document).off('.confirmDel_' + NAMESPACE);
    $(document).off('.form_' + NAMESPACE);
    
    document.querySelectorAll('#editModal, #deleteModal, #successModal').forEach(modal => {
      modal.style.display = 'none';
      modal.classList.remove('show');
    });
    
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    
    if (window.availableDatesModule) {
      delete window.availableDatesModule.generateCalendar;
      delete window.availableDatesModule.addDateInput;
      delete window.availableDatesModule.checkSubmitButton;
      delete window.availableDatesModule.openEditModal;
      delete window.availableDatesModule.showSuccessModal;
      delete window.availableDatesModule.addBulkDates;
    }
    
    isInitialized = false;
    cleanupCompleted = true;
    console.log('Cleanup completed');
  };

  if (document.readyState === 'loading') {
    $(document).ready(function() {
      console.log('Document ready, initializing...');
      initializeCalendar();
    });
  } else {
    console.log('Document already ready, initializing immediately...');
    initializeCalendar();
  }

})();
</script>