<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$appointmentLocations = [];
$sql = "SELECT r.address, 
        a.id,
        a.requested_at,
        a.resident_id,
        COUNT(a.id) as appointment_count,
        MAX(a.requested_at) as last_appointment,
        COUNT(DISTINCT a.resident_id) as resident_count
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL AND r.address != ''
        GROUP BY r.address
        ORDER BY appointment_count DESC";

$result = $conn->query($sql);
$allAppointmentsData = [];
while ($row = $result->fetch_assoc()) {
    $allAppointmentsData[] = $row;
}

$sql_individual = "SELECT r.address, a.requested_at, a.resident_id
                   FROM appointments a
                   JOIN residents r ON a.resident_id = r.id
                   WHERE r.address IS NOT NULL AND r.address != ''";
$result_individual = $conn->query($sql_individual);
$individualAppointments = [];
while ($row = $result_individual->fetch_assoc()) {
    $individualAppointments[] = $row;
}

$totalResidents = count($appointmentLocations);
$totalAppointments = array_sum(array_column($appointmentLocations, 'count'));
?>

<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />

<style>
.geomap-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.geomap-stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.geomap-stat-card:hover {
    transform: translateY(-5px);
}

.geomap-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.geomap-stat-icon.blue { background: linear-gradient(135deg, #36d1dc, #5b86e5); }
.geomap-stat-icon.purple { background: linear-gradient(135deg, #6a11cb, #2575fc); }
.geomap-stat-icon.green { background: linear-gradient(135deg, #2ecc71, #27ae60); }

.geomap-stat-info h3 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #333;
}

.geomap-stat-info p {
    margin: 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}

.geomap-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.geomap-header {
    background: linear-gradient(135deg, #0D92F4, #27548A);
    padding: 20px 25px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.geomap-header-left h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.geomap-header-left p {
    margin: 8px 0 0 0;
    font-size: 14px;
    opacity: 0.9;
}

.geomap-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.geomap-filter-select-header {
    padding: 10px 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: white;
    background: rgba(255, 255, 255, 0.15);
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.geomap-filter-select-header option {
    color: #333;
    background: white;
}

.geomap-filter-select-header:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
}

.geomap-filter-select-header:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.25);
    border-color: white;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
}

.geomap-export-btn {
    background: white;
    color: #084672ff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.geomap-export-btn:hover {
    background: #f0f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.geomap-export-btn:active {
    transform: translateY(0);
}

.geomap-export-btn i {
    font-size: 18px;
}

#geomap-map {
    height: 600px;
    width: 100%;
    touch-action: pan-x pan-y;
}

.geomap-legend {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    line-height: 20px;
}

.geomap-legend h6 {
    margin: 0 0 10px 0;
    font-weight: 700;
    color: #333;
    font-size: 14px;
}

.geomap-legend-item {
    display: flex;
    align-items: center;
    margin: 5px 0;
    font-size: 13px;
}

.geomap-legend-color {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 8px;
    border: 2px solid rgba(0,0,0,0.2);
}

.geomap-popup-content {
    font-family: "Segoe UI", sans-serif;
}

.geomap-popup-content h6 {
    margin: 0 0 8px 0;
    color: #0D92F4;
    font-weight: 700;
    font-size: 16px;
}

.geomap-popup-content p {
    margin: 4px 0;
    font-size: 13px;
}

.geomap-popup-content strong {
    color: #333;
}

.approximate-location {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

.leaflet-control-layers {
    border-radius: 8px !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15) !important;
    border: none !important;
}

.leaflet-control-layers-toggle {
    background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"%3e%3cpath fill="%230D92F4" d="m21.484 7.125l-9.022-5a1.003 1.003 0 0 0-.968 0l-8.978 4.96a1 1 0 0 0-.003 1.748l9.022 5.04a.995.995 0 0 0 .973.001l8.978-5a1 1 0 0 0-.002-1.749zm-9.461 4.73l-6.964-3.89L12 4.98l6.964 3.99l-6.941 3.885z"/%3e%3cpath fill="%230D92F4" d="M12 15.856l-8.515-4.73l-.971 1.748l9 5a1 1 0 0 0 .971 0l9-5l-.971-1.748L12 15.856z"/%3e%3cpath fill="%230D92F4" d="M12 19.856l-8.515-4.73l-.971 1.748l9 5a1 1 0 0 0 .971 0l9-5l-.971-1.748L12 19.856z"/%3e%3c/svg%3e') !important;
    width: 44px !important;
    height: 44px !important;
    background-size: 24px 24px !important;
    background-position: center !important;
}

.leaflet-control-layers-expanded {
    padding: 10px !important;
    font-family: "Segoe UI", sans-serif !important;
}

.leaflet-control-layers-base label,
.leaflet-control-layers-overlays label {
    font-size: 13px !important;
    color: #333 !important;
    padding: 5px 0 !important;
}

.header-option-1 {
    background: linear-gradient(135deg, #0D92F4, #27548A);
    border-radius: 20px;
    padding: 30px 40px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.header-option-1::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

.header-option-1 .header-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-option-1 .icon-wrapper {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.header-option-1 .icon-wrapper i {
    font-size: 36px;
    color: white;
}

.header-option-1 .header-text h2 {
    font-size: 28px;
    font-weight: 700;
    color: white;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.header-option-1 .header-text p {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 400;
}

.header-option-1 .dept-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.25);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: white;
    margin-top: 10px;
    backdrop-filter: blur(10px);
}

.option-label {
    background: #f0f0f0;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    margin-bottom: 15px;
    display: inline-block;
}

@media (max-width: 1024px) {
    #geomap-map {
        height: 500px;
    }

    .geomap-stats-container {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
    }

    .geomap-stat-card {
        padding: 15px;
    }

    .geomap-stat-icon {
        width: 45px;
        height: 45px;
        font-size: 22px;
    }

    .geomap-stat-info h3 {
        font-size: 24px;
    }

    .header-option-1 {
        padding: 25px 30px;
    }

    .header-option-1 .icon-wrapper {
        width: 60px;
        height: 60px;
    }

    .header-option-1 .icon-wrapper i {
        font-size: 30px;
    }

    .header-option-1 .header-text h2 {
        font-size: 24px;
    }
}

@media (max-width: 768px) {
    #geomap-map {
        height: 450px;
        min-height: 400px;
    }

    .geomap-stats-container {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }

    .geomap-stat-card {
        padding: 15px;
        gap: 12px;
    }

    .geomap-stat-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
    }

    .geomap-stat-info h3 {
        font-size: 26px;
    }

    .geomap-stat-info p {
        font-size: 13px;
    }

    .geomap-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
    }

    .geomap-header-left h2 {
        font-size: 18px;
    }

    .geomap-header-left p {
        font-size: 13px;
    }

    .geomap-header-right {
        width: 100%;
        flex-direction: column;
        gap: 10px;
    }

    .geomap-filter-select-header,
    .geomap-export-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 16px;
        font-size: 14px;
    }

    .header-option-1 {
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 20px;
    }

    .header-option-1 .header-content {
        flex-direction: row;
        align-items: center;
        gap: 15px;
    }

    .header-option-1 .icon-wrapper {
        width: 55px;
        height: 55px;
        flex-shrink: 0;
    }

    .header-option-1 .icon-wrapper i {
        font-size: 28px;
    }

    .header-option-1 .header-text h2 {
        font-size: 20px;
        margin-bottom: 6px;
    }

    .header-option-1 .header-text p {
        font-size: 13px;
    }

    .leaflet-popup-content-wrapper {
        max-width: 280px;
    }

    .geomap-popup-content h6 {
        font-size: 15px;
    }

    .geomap-popup-content p {
        font-size: 12px;
    }

    .geomap-legend {
        padding: 12px;
        max-width: 200px;
    }

    .geomap-legend h6 {
        font-size: 13px;
    }

    .geomap-legend-item {
        font-size: 12px;
    }

    .geomap-legend-color {
        width: 18px;
        height: 18px;
    }

    .leaflet-marker-icon {
        cursor: pointer;
    }

    .leaflet-popup-close-button {
        width: 30px !important;
        height: 30px !important;
        font-size: 24px !important;
        line-height: 30px !important;
    }

    .leaflet-container {
        -webkit-tap-highlight-color: transparent;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .leaflet-popup {
        margin-bottom: 30px;
    }
}

@media (max-width: 667px) and (orientation: landscape) {
    #geomap-map {
        height: 350px;
        min-height: 300px;
    }

    .geomap-stats-container {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .geomap-stat-card {
        padding: 10px;
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }

    .geomap-stat-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }

    .geomap-stat-info h3 {
        font-size: 20px;
    }

    .geomap-stat-info p {
        font-size: 11px;
    }

    .header-option-1 {
        padding: 15px;
    }

    .header-option-1 .header-text h2 {
        font-size: 18px;
    }

    .header-option-1 .header-text p {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    #geomap-map {
        height: 400px;
        min-height: 350px;
    }

    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }

    .geomap-stats-container {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 15px;
    }

    .geomap-stat-card {
        padding: 12px 15px;
        gap: 12px;
    }

    .geomap-stat-icon {
        width: 45px;
        height: 45px;
        font-size: 22px;
    }

    .geomap-stat-info h3 {
        font-size: 24px;
    }

    .geomap-stat-info p {
        font-size: 12px;
    }

    .geomap-card {
        border-radius: 12px;
    }

    .geomap-header {
        padding: 15px;
    }

    .geomap-header-left h2 {
        font-size: 16px;
        gap: 8px;
    }

    .geomap-header-left h2 i {
        font-size: 20px;
    }

    .geomap-header-left p {
        font-size: 12px;
        margin-top: 5px;
    }

    .geomap-filter-select-header,
    .geomap-export-btn {
        padding: 10px 14px;
        font-size: 13px;
    }

    .geomap-export-btn i {
        font-size: 16px;
    }

    .header-option-1 {
        padding: 15px;
        border-radius: 15px;
    }

    .header-option-1::before {
        width: 250px;
        height: 250px;
    }

    .header-option-1 .header-content {
        gap: 12px;
    }

    .header-option-1 .icon-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 12px;
    }

    .header-option-1 .icon-wrapper i {
        font-size: 24px;
    }

    .header-option-1 .header-text h2 {
        font-size: 18px;
        margin-bottom: 5px;
    }

    .header-option-1 .header-text p {
        font-size: 12px;
        line-height: 1.4;
    }

    .header-option-1 .dept-badge {
        font-size: 11px;
        padding: 5px 12px;
        margin-top: 8px;
    }

    .leaflet-popup-content-wrapper {
        max-width: 240px;
        border-radius: 8px;
    }

    .geomap-popup-content {
        padding: 5px;
    }

    .geomap-popup-content h6 {
        font-size: 14px;
        margin-bottom: 6px;
    }

    .geomap-popup-content p {
        font-size: 11px;
        margin: 3px 0;
    }

    .geomap-legend {
        padding: 10px;
        max-width: 180px;
        font-size: 11px;
    }

    .geomap-legend h6 {
        font-size: 12px;
        margin-bottom: 8px;
    }

    .geomap-legend-item {
        margin: 4px 0;
        font-size: 11px;
    }

    .geomap-legend-color {
        width: 16px;
        height: 16px;
        margin-right: 6px;
    }

    .leaflet-control-layers-toggle {
        width: 38px !important;
        height: 38px !important;
        background-size: 20px 20px !important;
    }

    .leaflet-control-zoom a {
        width: 38px;
        height: 38px;
        line-height: 38px;
        font-size: 20px;
    }

    .leaflet-touch .leaflet-control-layers-toggle {
        width: 44px !important;
        height: 44px !important;
    }

    .leaflet-touch .leaflet-control-zoom a {
        width: 44px;
        height: 44px;
        line-height: 44px;
    }

    .leaflet-marker-icon {
        transition: transform 0.1s ease;
    }
    
    .leaflet-marker-icon:active {
        transform: scale(1.1);
    }
    
    .leaflet-bottom.leaflet-right {
        bottom: 10px;
        right: 10px;
    }
    
    .leaflet-bottom.leaflet-left {
        bottom: 10px;
        left: 10px;
    }
}

@media (max-width: 375px) {
    #geomap-map {
        height: 350px;
        min-height: 320px;
    }

    .geomap-stat-card {
        padding: 10px 12px;
    }

    .geomap-stat-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }

    .geomap-stat-info h3 {
        font-size: 22px;
    }

    .geomap-stat-info p {
        font-size: 11px;
    }

    .header-option-1 .header-text h2 {
        font-size: 16px;
    }

    .header-option-1 .header-text p {
        font-size: 11px;
    }

    .geomap-header-left h2 {
        font-size: 15px;
    }

    .geomap-filter-select-header,
    .geomap-export-btn {
        font-size: 12px;
        padding: 9px 12px;
    }

    .leaflet-popup-content-wrapper {
        max-width: 220px;
    }

    .geomap-legend {
        max-width: 160px;
        padding: 8px;
    }
}

@supports (-webkit-touch-callout: none) {
    #geomap-map {
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 768px) {
        #geomap-map {
            height: 50vh;
            min-height: 350px;
        }
    }
}

@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .leaflet-marker-icon {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
}
</style>

<div class="container-fluid">
    <div class="header-option-1">
        <div class="header-content">
            <div class="icon-wrapper">
                <i class='bx bx-map-alt'></i>
            </div>
            <div class="header-text">
                <h2>Appointment Hotspot Map</h2>
                <p>Geographic distribution of appointments across Unisan, Quezon</p>
            </div>
        </div>
    </div>

    <div class="geomap-stats-container">
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon blue">
                <i class='bx bx-map-pin'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalResidents ?></h3>
                <p>Locations</p>
            </div>
        </div>
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon purple">
                <i class='bx bx-calendar-check'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalAppointments ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon green">
                <i class='bx bx-current-location'></i>
            </div>
            <div class="geomap-stat-info">
                <h3>Unisan</h3>
                <p>Municipality</p>
            </div>
        </div>
    </div>

    <div class="geomap-card">
        <div class="geomap-header">
            <div class="geomap-header-left">
                <h2>
                    <i class='bx bx-map'></i>
                    Geographic Distribution
                </h2>
                <p>Showing appointment hotspots across Unisan, Quezon Province</p>
            </div>
            <div class="geomap-header-right">
                <select id="geomap-time-filter" class="geomap-filter-select-header">
                    <option value="all">All Time</option>
                    <option value="week">Past Week</option>
                    <option value="month">Past Month</option>
                    <option value="year">Past Year</option>
                </select>
                <button class="geomap-export-btn" onclick="window.location.href='GeoMap_Report.php'">
                    <i class='bx bx-download'></i>
                    Generate Excel Report
                </button>
            </div>
        </div>
        <div id="geomap-map"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
(function() {
    console.log('GeoMap: Initializing...');
    
    let geomapInstance = null;
    let allMarkers = [];
    let legendControl = null;
    
    const allIndividualAppointments = <?= json_encode($individualAppointments) ?>;
    
    window.geomapCleanup = function() {
        console.log('GeoMap: Cleaning up...');
        if (geomapInstance !== null) {
            try {
                geomapInstance.remove();
                console.log('GeoMap: Map instance removed');
            } catch(e) {
                console.warn('GeoMap: Error removing map:', e);
            }
            geomapInstance = null;
        }
    };
    
    function filterAppointmentsByTime(timeFilter) {
        const now = new Date();
        const cutoffDate = new Date();
        
        switch(timeFilter) {
            case 'week':
                cutoffDate.setDate(now.getDate() - 7);
                break;
            case 'month':
                cutoffDate.setMonth(now.getMonth() - 1);
                break;
            case 'year':
                cutoffDate.setFullYear(now.getFullYear() - 1);
                break;
            case 'all':
            default:
                return allIndividualAppointments;
        }
        
        return allIndividualAppointments.filter(apt => {
            const aptDate = new Date(apt.requested_at);
            return aptDate >= cutoffDate;
        });
    }
    
    function aggregateAppointments(appointments) {
        const aggregated = {};
        
        appointments.forEach(apt => {
            if (!aggregated[apt.address]) {
                aggregated[apt.address] = {
                    address: apt.address,
                    count: 0,
                    resident_ids: new Set(),
                    last_appointment: apt.requested_at
                };
            }
            
            aggregated[apt.address].count++;
            aggregated[apt.address].resident_ids.add(apt.resident_id);
            
            if (new Date(apt.requested_at) > new Date(aggregated[apt.address].last_appointment)) {
                aggregated[apt.address].last_appointment = apt.requested_at;
            }
        });
        
        return Object.values(aggregated).map(item => ({
            ...item,
            resident_count: item.resident_ids.size
        }));
    }
    
    function updateStats(appointments) {
        const totalLocations = appointments.length;
        const totalAppointments = appointments.reduce((sum, apt) => sum + apt.count, 0);
        
        document.querySelector('.geomap-stat-card:nth-child(1) h3').textContent = totalLocations;
        document.querySelector('.geomap-stat-card:nth-child(2) h3').textContent = totalAppointments;
    }
    
    function initGeoMap() {
        console.log('GeoMap: Starting initialization...');
        
        if (geomapInstance !== null) {
            console.log('GeoMap: Removing existing map instance...');
            geomapInstance.remove();
            geomapInstance = null;
        }
        
        const mapContainer = document.getElementById('geomap-map');
        if (!mapContainer) {
            console.error('GeoMap: Map container not found!');
            return;
        }
        
        mapContainer.innerHTML = '';
        
        geomapInstance = L.map('geomap-map').setView([13.8383, 121.9782], 13);
        
        const baseLayers = {
            "🛰️ Satellite": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 18,
            }),
            "🗺️ Street Map": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }),
            "🌍 Terrain": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 18,
            }),
            "🌆 Light Theme": L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                maxZoom: 19,
            }),
            "🌃 Dark Theme": L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                maxZoom: 19,
            })
        };
        
        baseLayers["🗺️ Street Map"].addTo(geomapInstance);
        
        const labelOverlay = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap, © CartoDB',
            maxZoom: 18,
            opacity: 0.7,
            pane: 'shadowPane'
        });
        
        const overlayLayers = {
            "📍 Place Labels": labelOverlay
        };
        
        labelOverlay.addTo(geomapInstance);
        
        L.control.layers(baseLayers, overlayLayers, {
            position: 'topright',
            collapsed: true
        }).addTo(geomapInstance);
        
        geomapInstance.on('baselayerchange', function(e) {
            if (e.name === "🛰️ Satellite" || e.name === "🌃 Dark Theme") {
                if (!geomapInstance.hasLayer(labelOverlay)) {
                    labelOverlay.addTo(geomapInstance);
                }
            } else {
                if (geomapInstance.hasLayer(labelOverlay)) {
                    geomapInstance.removeLayer(labelOverlay);
                }
            }
        });
        
        const unisanBounds = L.latLngBounds(
            [13.7800, 121.9200],
            [13.9000, 122.0400]
        );
        
        geomapInstance.setMaxBounds(unisanBounds);
        geomapInstance.setMinZoom(12);
        geomapInstance.setMaxZoom(18);
        geomapInstance.fitBounds(unisanBounds);
        
        L.marker([13.8383, 121.9782], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(geomapInstance)
        .bindPopup(`
            <div class="geomap-popup-content">
                <h6><i class='bx bx-building'></i> Unisan Municipal Hall</h6>
                <p>Municipality Center</p>
            </div>
        `);
        
        updateMapData('all');
        
        setTimeout(function() {
            if (geomapInstance) {
                geomapInstance.invalidateSize();
            }
        }, 100);
        
        console.log('GeoMap: Initialization complete!');
    }
    
    function updateMapData(timeFilter) {
        console.log('GeoMap: Updating data for filter:', timeFilter);
        
        allMarkers.forEach(marker => geomapInstance.removeLayer(marker));
        allMarkers = [];
        
        if (legendControl) {
            geomapInstance.removeControl(legendControl);
        }
        
        const filteredAppointments = filterAppointmentsByTime(timeFilter);
        const appointments = aggregateAppointments(filteredAppointments);
        
        updateStats(appointments);
        
        if (appointments.length === 0) {
            console.warn('GeoMap: No appointments found for this time period');
            return;
        }
        
        const barangayCoords = {
            'Almacen': [13.8666, 121.9958],
            'Balagbag': [13.8250, 122.0100],
            'Balagtas': [13.8404, 122.0389],
            'Balanacan': [13.8510, 121.9895],
            'Bonifacio': [13.8770, 122.0320],
            'Bulo Ibaba': [13.8657, 121.9738],
            'Bulo Ilaya': [13.8754, 121.9559],
            'Burgos': [13.8909, 121.9589,],
            'Cabulihan Ibaba': [13.8153, 122.0243],
            'Cabulihan Ilaya': [13.8322, 122.0190],
            'Caigdal': [13.8332, 122.0286 ],
            'F. de Jesus': [13.8373, 121.9776],
            'General Luna': [13.8459, 122.0170],
            'Kalilayan Ibaba': [13.8550, 121.9636],
            'Kalilayan Ilaya': [13.8563, 121.9554],
            'Mabini': [13.8548, 122.0334],
            'Mairok Ibaba': [13.8948, 121.9810],
            'Mairok Ilaya': [13.9046, 121.9609],
            'Malvar': [13.8209, 121.9840],
            'Maputat': [13.8209, 121.9840],
            'Maliguin': [13.8316, 122.0016], 
            'Pagaguasan': [13.8590, 121.9681],
            'Panaon Ibaba': [13.8783, 121.9819],
            'Panaon Ilaya': [13.8865, 121.9788],
            'Plaridel': [13.8634, 122.0159],
            'Poctol': [13.8777, 122.0082],
            'Punta': [13.8056, 121.9890],
            'R. Lapu-Lapu': [13.8392, 121.9754],
            'R. Magsaysay': [13.8407, 121.9781],
            'Raja Soliman': [13.8418, 121.9766],
            'Rizal Ibaba': [13.9015, 122.0091],
            'Rizal Ilaya': [13.8927, 122.0209],
            'San Roque': [13.8293, 122.0543],
            'Socorro': [13.9078, 121.9906],
            'Tagumpay': [13.8337, 122.0462],
            'Tubas': [13.8424, 122.0526],
            'Tubigan': [13.8842, 121.9883],
            'Sildora': [13.8665, 121.9243],
            'Unisan': [13.8383, 121.9782],
            'Unisan, Quezon': [13.8383, 121.9782]
        };
        
        const counts = appointments.map(apt => apt.count);
        const maxCount = Math.max(...counts);
        const minCount = Math.min(...counts);
        const sortedCounts = [...counts].sort((a, b) => a - b);
        const lowThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.33)];
        const highThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.67)];
        
        function getMarkerColor(count) {
            if (count >= highThreshold) return '#e74c3c';
            if (count >= lowThreshold) return '#ff9800';
            return '#2ecc71';
        }
        
        function getMarkerSize(count) {
            const range = maxCount - minCount;
            const normalized = range > 0 ? (count - minCount) / range : 0.5;
            const baseSize = 24 + (normalized * 32);
            return {
                iconSize: [baseSize, baseSize * 1.4],
                iconAnchor: [baseSize / 2, baseSize * 1.4],
                popupAnchor: [0, -baseSize * 1.4]
            };
        }
        
        function extractBarangay(address) {
            const lower = address.toLowerCase().trim();
            
            for (let brgy in barangayCoords) {
                const brgyLower = brgy.toLowerCase();
                
                if (lower === brgyLower ||
                    lower.includes('brgy. ' + brgyLower) ||
                    lower.includes('brgy ' + brgyLower) ||
                    lower.includes('barangay ' + brgyLower) ||
                    lower.includes(brgyLower + ',') ||
                    lower.includes(brgyLower + ' ') ||
                    lower.startsWith(brgyLower) ||
                    lower.endsWith(brgyLower)) {
                    return brgy;
                }
            }
            
            for (let brgy in barangayCoords) {
                if (lower.includes(brgy.toLowerCase())) {
                    return brgy;
                }
            }
            
            return null;
        }
        
        let markersAdded = 0;
        
        appointments.forEach(apt => {
            const barangay = extractBarangay(apt.address);
            let coords = null;
            let isApproximate = false;
            
            if (barangay && barangayCoords[barangay]) {
                coords = barangayCoords[barangay];
            } else {
                coords = [13.8383, 121.9782];
                isApproximate = true;
            }
            
            if (coords) {
                const lat = coords[0] + (Math.random() - 0.5) * (isApproximate ? 0.01 : 0.003);
                const lng = coords[1] + (Math.random() - 0.5) * (isApproximate ? 0.01 : 0.003);
                
                const color = getMarkerColor(apt.count);
                const size = getMarkerSize(apt.count);
                
                const marker = L.marker([lat, lng], {
                    icon: L.icon({
                        iconUrl: `data:image/svg+xml;base64,${btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="${size.iconSize[0]}" height="${size.iconSize[1]}" viewBox="0 0 32 45">
                                <path fill="${color}" stroke="${isApproximate ? '#ffa500' : '#fff'}" stroke-width="2" 
                                    d="M16 0C9.4 0 4 5.4 4 12c0 8 12 28 12 28s12-20 12-28c0-6.6-5.4-12-12-12z"/>
                                <circle cx="16" cy="12" r="4" fill="white" opacity="${isApproximate ? '0.7' : '1'}"/>
                            </svg>
                        `)}`,
                        iconSize: size.iconSize,
                        iconAnchor: size.iconAnchor,
                        popupAnchor: size.popupAnchor,
                        className: isApproximate ? 'approximate-location' : ''
                    })
                }).addTo(geomapInstance);
                
                const lastDate = new Date(apt.last_appointment);
                const formattedDate = lastDate.toLocaleDateString('en-PH', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                
                marker.bindPopup(`
                    <div class="geomap-popup-content">
                        <h6><i class='bx bx-map-pin'></i> ${apt.address}</h6>
                        ${isApproximate ? '<p style="color: #ff9800; font-size: 12px;"><em>⚠️ Approximate location</em></p>' : ''}
                        ${barangay && !isApproximate ? '<p><strong>Barangay:</strong> ' + barangay + '</p>' : ''}
                        <p><strong>Total Appointments:</strong> ${apt.count}</p>
                        <p><strong>Residents from this area:</strong> ${apt.resident_count}</p>
                        <p><strong>Last Appointment:</strong> ${formattedDate}</p>
                    </div>
                `);
                
                allMarkers.push(marker);
                markersAdded++;
            }
        });
        
        legendControl = L.control({ position: 'bottomright' });
        legendControl.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'geomap-legend');
            div.innerHTML = `
                <h6>Appointment Ranking</h6>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #2ecc71;"></div>
                    <span>Low (${minCount}-${lowThreshold - 1})</span>
                </div>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #ff9800;"></div>
                    <span>Medium (${lowThreshold}-${highThreshold - 1})</span>
                </div>
                <div class="geomap-legend-item">
                    <div class="geomap-legend-color" style="background: #e74c3c;"></div>
                    <span>High (${highThreshold}+)</span>
                </div>
            `;
            return div;
        };
        legendControl.addTo(geomapInstance);
        
        console.log('GeoMap:', markersAdded, 'markers added');
    }
    
    setTimeout(initGeoMap, 200);
    
    setTimeout(function() {
        const filterSelect = document.getElementById('geomap-time-filter');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                updateMapData(this.value);
            });
            console.log('GeoMap: Filter listener attached');
        }
    }, 500);
})();
function getDeviceType() {
    const width = window.innerWidth;
    if (width <= 480) return 'mobile';
    if (width <= 768) return 'tablet';
    return 'desktop';
}

function getResponsiveMarkerSize(count, maxCount, minCount) {
    const deviceType = getDeviceType();
    const range = maxCount - minCount;
    const normalized = range > 0 ? (count - minCount) / range : 0.5;
    
    let baseSize, maxSize;
    
    switch(deviceType) {
        case 'mobile':
            baseSize = 20;
            maxSize = 36;
            break;
        case 'tablet':
            baseSize = 22;
            maxSize = 42;
            break;
        default:
            baseSize = 24;
            maxSize = 56;
    }
    
    const size = baseSize + (normalized * (maxSize - baseSize));
    
    return {
        iconSize: [size, size * 1.4],
        iconAnchor: [size / 2, size * 1.4],
        popupAnchor: [0, -size * 1.4]
    };
}

function getMarkerSize(count) {
    return getResponsiveMarkerSize(count, maxCount, minCount);
}

function getMobileMapOptions() {
    const isMobile = window.innerWidth <= 768;
    
    return {
        zoomControl: !isMobile,
        dragging: true,
        touchZoom: true,
        scrollWheelZoom: isMobile ? false : true,
        doubleClickZoom: true,
        boxZoom: !isMobile,
        tap: isMobile,
        tapTolerance: 15,
        bounceAtZoomLimits: true
    };
}

function initGeoMap() {
    console.log('GeoMap: Starting initialization...');
    
    if (geomapInstance !== null) {
        console.log('GeoMap: Removing existing map instance...');
        geomapInstance.remove();
        geomapInstance = null;
    }
    
    const mapContainer = document.getElementById('geomap-map');
    if (!mapContainer) {
        console.error('GeoMap: Map container not found!');
        return;
    }
    
    mapContainer.innerHTML = '';
    
    const mapOptions = getMobileMapOptions();
    geomapInstance = L.map('geomap-map', mapOptions).setView([13.8383, 121.9782], 13);
    
    if (window.innerWidth <= 768) {
        L.control.zoom({
            position: 'bottomleft'
        }).addTo(geomapInstance);
        
        geomapInstance.on('click', function() {
            if (!geomapInstance.scrollWheelZoom.enabled()) {
                geomapInstance.scrollWheelZoom.enable();
            }
        });
        
        geomapInstance.on('mouseout', function() {
            if (window.innerWidth <= 768) {
                geomapInstance.scrollWheelZoom.disable();
            }
        });
    }
    
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (geomapInstance) {
                geomapInstance.invalidateSize();
                const currentFilter = document.getElementById('geomap-time-filter')?.value || 'all';
                updateMapData(currentFilter);
            }
        }, 250);
    });
    
    geomapInstance.on('popupopen', function(e) {
        const px = geomapInstance.project(e.popup._latlng);
        px.y -= e.popup._container.clientHeight / 2;
        geomapInstance.panTo(geomapInstance.unproject(px), {animate: true});
    });
}

function addMobileStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .leaflet-container {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        .leaflet-marker-icon {
            transition: transform 0.1s ease;
        }
        
        .leaflet-marker-icon:active {
            transform: scale(1.1);
        }
        
        @media (max-width: 480px) {
            .leaflet-bottom.leaflet-right {
                bottom: 10px;
                right: 10px;
            }
            
            .leaflet-bottom.leaflet-left {
                bottom: 10px;
                left: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .leaflet-popup {
                margin-bottom: 30px;
            }
        }
    `;
    document.head.appendChild(style);
}

document.addEventListener('DOMContentLoaded', function() {
    addMobileStyles();
});
</script>