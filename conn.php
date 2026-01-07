<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "lgu_q_a";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("SET NAMES 'utf8'");

    if (file_exists(__DIR__ . '/includes/auto_mark_noshow.php')) {
        include __DIR__ . '/includes/auto_mark_noshow.php';
    }

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();    
}
?>