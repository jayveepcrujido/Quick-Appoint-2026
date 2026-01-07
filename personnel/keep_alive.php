<?php
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
session_set_cookie_params(86400);

session_start();

if (isset($_SESSION['auth_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'alive', 'time' => time()]);
} else {
    http_response_code(403);
    echo json_encode(['status' => 'expired']);
}
exit;
?>