<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "lgu_quick_appoint";

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optionally, set the character set to utf8 for compatibility
    $pdo->exec("SET NAMES 'utf8'");

    if (file_exists(__DIR__ . '/includes/auto_mark_noshow.php')) {
        include __DIR__ . '/includes/auto_mark_noshow.php';
    }

} catch (PDOException $e) {
    // If the connection fails, display an error message
    echo "Connection failed: " . $e->getMessage();
    die(); // Stop execution if the connection fails    
}
?>