<?php
// Session check should be done in the parent file (personnel dashboard)
// This assumes $_SESSION['personnel_id'] and $_SESSION['department_id'] are set

// DB Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Check what's in the session
error_log("Session data: " . print_r($_SESSION, true));

// Get personnel's department ID from session
$personnel_department_id = $_SESSION['department_id'] ?? null;
$personnel_id = $_SESSION['personnel_id'] ?? null;

if (!$personnel_department_id) {
    // Try to fetch from database using personnel_id
    if ($personnel_id) {
        $fetch_dept_sql = "SELECT department_id FROM personnel WHERE id = ?";
        $fetch_stmt = $conn->prepare($fetch_dept_sql);
        $fetch_stmt->bind_param("i", $personnel_id);
        $fetch_stmt->execute();
        $fetch_result = $fetch_stmt->get_result();
        
        if ($row = $fetch_result->fetch_assoc()) {
            $personnel_department_id = $row['department_id'];
            $_SESSION['department_id'] = $personnel_department_id; // Save it back to session
        }
        $fetch_stmt->close();
    }
    
    // Still no department? Show error
    if (!$personnel_department_id) {
        echo "<div class='alert alert-danger'>
                <strong>Error:</strong> Department not found. Please contact administrator.<br>
                <small>Debug: personnel_id = " . ($personnel_id ?? 'NOT SET') . ", department_id = NOT SET</small>
              </div>";
        exit;
    }
}

$appointmentLocations = [];

// Fetch initial data for "All Time"
$sql = "SELECT r.address, 
        COUNT(DISTINCT a.id) as appointment_count,
        COUNT(DISTINCT r.id) as resident_count,
        MAX(a.requested_at) as last_appointment
        FROM appointments a
        JOIN residents r ON a.resident_id = r.id
        WHERE r.address IS NOT NULL 
        AND r.address != ''
        AND a.department_id = ?
        GROUP BY r.address
        ORDER BY appointment_count DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $personnel_department_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $appointmentLocations[] = [
        'address' => $row['address'],
        'appointment_count' => (int)$row['appointment_count'],
        'resident_count' => (int)$row['resident_count'],
        'last_appointment' => $row['last_appointment']
    ];
}
$stmt->close();

// Get department name
$dept_sql = "SELECT name, acronym FROM departments WHERE id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $personnel_department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$department = $dept_result->fetch_assoc();
$dept_name = $department['acronym'] ?: $department['name'];
$dept_stmt->close();

// Get total stats
$totalLocations = count($appointmentLocations);
$totalAppointments = array_sum(array_column($appointmentLocations, 'appointment_count'));
$totalResidents = array_sum(array_column($appointmentLocations, 'resident_count'));
?>

<!-- Leaflet CSS (if not already in main page) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />

<style>
    /* Stats Cards */
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

    /* Map Container */
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
    }

    .geomap-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .geomap-header p {
        margin: 8px 0 0 0;
        font-size: 14px;
        opacity: 0.9;
    }

    #geomap-map {
        height: 600px;
        width: 100%;
    }

    /* Legend */
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

    /* Popup */
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

    /* Layer Control Styles */
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

    /* Responsive */
    @media (max-width: 768px) {
        #geomap-map {
            height: 450px;
        }

        .geomap-stats-container {
            grid-template-columns: 1fr;
        }
    }
    /* Option 1: Gradient Card Style */
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
        @media (max-width: 768px) {
            .header-option-2 .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-option-1 .header-content,
            .header-option-3 .header-content,
            .header-option-4 .header-content,
            .header-option-5 .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-option-1 h2,
            .header-option-2 h2,
            .header-option-3 h2,
            .header-option-4 h2,
            .header-option-5 h2 {
                font-size: 22px;
            }
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
        /* Custom Pin Marker Styles */
.custom-pin-marker {
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
    transition: transform 0.3s ease;
}

.custom-pin-marker:hover {
    transform: scale(1.1);
    filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.4));
}

.custom-pin-marker svg {
    transition: all 0.3s ease;
}

.approximate-location {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { 
        opacity: 0.7;
        transform: scale(1);
    }
    50% { 
        opacity: 1;
        transform: scale(1.05);
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
                    <span class="dept-badge"><?= htmlspecialchars($dept_name) ?></span>
                </div>
            </div>
        </div>

    <!-- Stats Cards -->
    <div class="geomap-stats-container">
        <div class="geomap-stat-card">
            <div class="geomap-stat-icon blue">
                <i class='bx bx-map-pin'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalLocations ?></h3>
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
                <i class='bx bx-user'></i>
            </div>
            <div class="geomap-stat-info">
                <h3><?= $totalResidents ?></h3>
                <p>Residents</p>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="geomap-card">
        <div class="geomap-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2>
                        <i class='bx bx-map'></i>
                        Geographic Distribution
                    </h2>
                    <p>Showing appointment hotspots for <?= htmlspecialchars($dept_name) ?> across Unisan, Quezon Province</p>
                </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="geomap-date-filter" style="padding: 10px 15px; border-radius: 8px; border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.2); color: black; font-size: 14px; font-weight: 600; cursor: pointer;">
                    <option value="all">All Time</option>
                    <option value="week">Past Week</option>
                    <option value="month">Past Month</option>
                    <option value="year">Past Year</option>
                </select>
                <button id="geomap-generate-report" style="padding: 10px 20px; border-radius: 8px; border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.95); color: #2c3e50; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'">
                    <i class='bx bxs-file-export'></i>
                    Generate Excel Report
                </button>
            </div>
            </div>
        </div>
        <div id="geomap-map"></div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>

<script>
(function() {
    console.log('GeoMap Personnel: Initializing...');
    
    let geomapInstance = null;
    let currentMarkers = [];
    let currentLegend = null;
    
    window.geomapCleanup = function() {
        console.log('GeoMap Personnel: Cleaning up...');
        if (geomapInstance !== null) {
            try {
                geomapInstance.remove();
                console.log('GeoMap Personnel: Map instance removed');
            } catch(e) {
                console.warn('GeoMap Personnel: Error removing map:', e);
            }
            geomapInstance = null;
        }
    };
    
    function clearMarkers() {
        currentMarkers.forEach(marker => {
            geomapInstance.removeLayer(marker);
        });
        currentMarkers = [];
        
        if (currentLegend) {
            geomapInstance.removeControl(currentLegend);
            currentLegend = null;
        }
    }
    
    function loadHotspotData(dateFilter = 'all') {
        console.log('Loading hotspot data for:', dateFilter);
        
        fetch(`get_hotspot_data.php?date_filter=${dateFilter}`)
            .then(response => response.json())
            .then(appointments => {
                console.log('Received', appointments.length, 'location groups');
                updateMap(appointments);
                updateStats(appointments);
            })
            .catch(error => {
                console.error('Error loading hotspot data:', error);
            });
    }
    
    function updateStats(appointments) {
        const totalLocations = appointments.length;
        const totalAppointments = appointments.reduce((sum, apt) => sum + apt.appointment_count, 0);
        const totalResidents = appointments.reduce((sum, apt) => sum + apt.resident_count, 0);
        
        document.querySelector('.geomap-stat-card:nth-child(1) h3').textContent = totalLocations;
        document.querySelector('.geomap-stat-card:nth-child(2) h3').textContent = totalAppointments;
        
        // Update third stat card to show residents
        const thirdCard = document.querySelector('.geomap-stat-card:nth-child(3)');
        thirdCard.querySelector('h3').textContent = totalResidents;
        thirdCard.querySelector('p').textContent = 'RESIDENTS';
        thirdCard.querySelector('.geomap-stat-icon').innerHTML = '<i class="bx bx-user"></i>';
    }
    
    function updateMap(appointments) {
        clearMarkers();
        
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

        const counts = appointments.map(apt => apt.appointment_count);
        const maxCount = Math.max(...counts, 1);
        const minCount = Math.min(...counts, 1);
        const sortedCounts = [...counts].sort((a, b) => a - b);
        const lowThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.33)] || 1;
        const highThreshold = sortedCounts[Math.floor(sortedCounts.length * 0.67)] || 1;

        function getMarkerColor(count) {
            if (count >= highThreshold) return '#e74c3c';
            if (count >= lowThreshold) return '#ff9800';
            return '#2ecc71';
        }

        function getMarkerSize(count) {
            const range = maxCount - minCount;
            const normalized = range > 0 ? (count - minCount) / range : 0.5;
            return 15 + (normalized * 15); // Sizes from 15 to 30 for pin markers
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
                const lat = coords[0] + (Math.random() - 0.5) * 0.002;
                const lng = coords[1] + (Math.random() - 0.5) * 0.002;
                
                const color = getMarkerColor(apt.appointment_count);
                const size = getMarkerSize(apt.appointment_count);
                
            // Create custom icon based on color and size
            const iconHtml = `
                <div style="position: relative; width: ${size * 2}px; height: ${size * 2.5}px;">
                    <svg width="${size * 2}" height="${size * 2.5}" viewBox="0 0 24 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 9 12 24 12 24s12-15 12-24c0-6.627-5.373-12-12-12z" 
                            fill="${color}" 
                            stroke="${isApproximate ? '#ffa500' : '#fff'}" 
                            stroke-width="2"
                            opacity="${isApproximate ? '0.7' : '0.9'}"/>
                        <circle cx="12" cy="12" r="4" fill="white" opacity="0.9"/>
                    </svg>
                    ${isApproximate ? '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; animation: pulse 2s infinite;"></div>' : ''}
                </div>
            `;

            const customIcon = L.divIcon({
                html: iconHtml,
                className: isApproximate ? 'custom-pin-marker approximate-location' : 'custom-pin-marker',
                iconSize: [size * 2, size * 2.5],
                iconAnchor: [size, size * 2.5],
                popupAnchor: [0, -size * 2.5]
            });

            const marker = L.marker([lat, lng], {
                icon: customIcon
            }).addTo(geomapInstance);

                const lastDate = apt.last_appointment ? new Date(apt.last_appointment) : null;
                const formattedDate = lastDate ? lastDate.toLocaleDateString('en-PH', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                }) : 'N/A';

                marker.bindPopup(`
                    <div class="geomap-popup-content">
                        <h6><i class='bx bx-map-pin'></i> ${apt.address}</h6>
                        ${isApproximate ? '<p style="color: #ff9800; font-size: 12px;"><em>⚠️ Approximate location</em></p>' : ''}
                        ${barangay && !isApproximate ? '<p><strong>Barangay:</strong> ' + barangay + '</p>' : ''}
                        <p><strong>Total Appointments:</strong> ${apt.appointment_count}</p>
                        <p><strong>Total Residents:</strong> ${apt.resident_count}</p>
                        <p><strong>Last Appointment:</strong> ${formattedDate}</p>
                    </div>
                `);
                
                currentMarkers.push(marker);
                markersAdded++;
            }
        });
        
        console.log('Added', markersAdded, 'area markers');

        // Add legend
        currentLegend = L.control({ position: 'bottomright' });
        currentLegend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'geomap-legend');
            div.innerHTML = `
                <h6>Appointment Hotspots</h6>
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
                <div class="geomap-legend-item" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                    <div class="geomap-legend-color" style="background: #999; border-color: #ffa500;"></div>
                    <span style="font-size: 11px;">Approximate</span>
                </div>
            `;
            return div;
        };
        currentLegend.addTo(geomapInstance);
    }
    
    function initGeoMap() {
        console.log('GeoMap Personnel: Starting initialization...');
        
        if (geomapInstance !== null) {
            geomapInstance.remove();
            geomapInstance = null;
        }
        
        const mapContainer = document.getElementById('geomap-map');
        if (!mapContainer) {
            console.error('GeoMap Personnel: Map container not found!');
            return;
        }
        
        mapContainer.innerHTML = '';
        
        const initialData = <?= json_encode($appointmentLocations) ?>;
        
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

        // Add Municipal Hall marker
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

        // Load initial data
        updateMap(initialData);

        // Add date filter event listener
        document.getElementById('geomap-date-filter').addEventListener('change', function() {
            loadHotspotData(this.value);
        });


        setTimeout(function() {
            if (geomapInstance) {
                geomapInstance.invalidateSize();
            }
        }, 100);

        console.log('GeoMap Personnel: Initialization complete!');
    }
    
    setTimeout(initGeoMap, 200);
})();

// Add report generation button event listener
document.getElementById('geomap-generate-report').addEventListener('click', function() {
    const dateFilter = document.getElementById('geomap-date-filter').value;
    const deptId = <?= $personnel_department_id ?>;
    
    // Show loading state
    this.disabled = true;
    this.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Generating...';
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'GeoMap_Report.php';
    form.target = '_blank';
    
    const filterInput = document.createElement('input');
    filterInput.type = 'hidden';
    filterInput.name = 'date_filter';
    filterInput.value = dateFilter;
    
    const deptInput = document.createElement('input');
    deptInput.type = 'hidden';
    deptInput.name = 'department_id';
    deptInput.value = deptId;
    
    form.appendChild(filterInput);
    form.appendChild(deptInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Reset button after 2 seconds
    setTimeout(() => {
        this.disabled = false;
        this.innerHTML = '<i class="bx bxs-file-export"></i> Generate Excel Report';
    }, 2000);
});
</script>