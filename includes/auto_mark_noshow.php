<?php
function autoMarkNoShow() {
    global $pdo;
    
    try {
        $cache_dir = __DIR__ . '/../cache';
        $cache_file = $cache_dir . '/last_noshow_check.txt';
        
        if (!is_dir($cache_dir)) {
            if (!mkdir($cache_dir, 0755, true)) {
                return 0;
            }
        }

        $should_run = true; 

        if (file_exists($cache_file)) {
            $last_check = (int)file_get_contents($cache_file);
            if (time() - $last_check > 3600) {
                $should_run = true;
            }
        } else {
            $should_run = true;
        }
        
        if ($should_run) {
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'No Show',
                    updated_at = NOW()
                WHERE status = 'Pending' 
                AND scheduled_for < DATE_SUB(NOW(), INTERVAL 8 HOUR)
            ");
            
            $stmt->execute();
            $affected_rows = $stmt->rowCount();
            
            file_put_contents($cache_file, time());
            
            return $affected_rows;
        }
        
        return 0;
        
    } catch (Exception $e) {
        return 0;
    }
}

if (isset($pdo)) {
    autoMarkNoShow();
}
?>