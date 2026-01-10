<?php
function autoMarkNoShow() {
    global $pdo;
    
    try {
        // --- CACHE SETTINGS ---
        $cache_dir = __DIR__ . '/../cache';
        $cache_file = $cache_dir . '/last_noshow_check.txt';
        
        // Ensure cache directory exists
        if (!is_dir($cache_dir)) {
            if (!mkdir($cache_dir, 0755, true)) {
                return 0;
            }
        }

        $should_run = true; 

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
            // UPDATED QUERY:
            // Changed "INTERVAL 1 HOUR" to "INTERVAL 8 HOUR"
            // This marks it No Show if 8 hours have passed since the appointment time.
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'No Show',
                    updated_at = NOW()
                WHERE status = 'Pending' 
                AND scheduled_for < DATE_SUB(NOW(), INTERVAL 8 HOUR)
            ");
            
            $stmt->execute();
            $affected_rows = $stmt->rowCount();
            
            // Update cache timestamp
            file_put_contents($cache_file, time());
            
            return $affected_rows;
        }
        
        return 0;
        
    } catch (Exception $e) {
        // Silent error to not break the UI
        return 0;
    }
}

// Auto-execute if $pdo exists
if (isset($pdo)) {
    autoMarkNoShow();
}
?>