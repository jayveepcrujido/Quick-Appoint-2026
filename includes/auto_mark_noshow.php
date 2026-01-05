<?php
function autoMarkNoShow() {
    global $pdo;
    
    try {
        // Define cache file path
        $cache_dir = __DIR__ . '/../cache';
        $cache_file = $cache_dir . '/last_noshow_check.txt';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($cache_dir)) {
            if (!mkdir($cache_dir, 0755, true)) {
                error_log("Failed to create cache directory: $cache_dir");
                return 0;
            }
        }
        
        // Check if we should run (only once per hour to reduce database load)
        $should_run = false;
        
        if (file_exists($cache_file)) {
            $last_check = (int)file_get_contents($cache_file);
            // Run only if last check was more than 1 hour ago (3600 seconds)
            if (time() - $last_check > 3600) {
                $should_run = true;
            }
        } else {
            $should_run = true;
        }
        
        if ($should_run) {
            // Update appointments to No Show if they're 24 hours past and still Pending
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'No Show',
                    updated_at = NOW()
                WHERE status = 'Pending' 
                AND scheduled_for < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $stmt->execute();
            $affected_rows = $stmt->rowCount();
            
            // Save current timestamp to cache file
            file_put_contents($cache_file, time());
            
            // Log if any appointments were marked (optional - good for debugging)
            if ($affected_rows > 0) {
                error_log("Auto No-Show: Marked $affected_rows appointment(s) as No Show - " . date('Y-m-d H:i:s'));
            }
            
            return $affected_rows;
        }
        
        return 0;
        
    } catch (Exception $e) {
        error_log("Error in autoMarkNoShow: " . $e->getMessage());
        return 0;
    }
}

// Auto-execute if $pdo exists (when file is included)
if (isset($pdo) && $pdo instanceof PDO) {
    autoMarkNoShow();
}
?>