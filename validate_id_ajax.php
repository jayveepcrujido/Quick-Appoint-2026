<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_POST['validate_only'])) {
        throw new Exception('Missing validation flag');
    }
    
    if (!file_exists('validate_id.php')) {
        throw new Exception('validate_id.php file not found');
    }
    
    require_once 'validate_id.php';
    
    $valid_id_type = isset($_POST['valid_id_type']) ? trim($_POST['valid_id_type']) : '';
    
    if (empty($valid_id_type)) {
        throw new Exception('ID type not provided');
    }
    
    if (!isset($_FILES['id_front'])) {
        throw new Exception('No file uploaded');
    }
    
    if ($_FILES['id_front']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        $error_code = $_FILES['id_front']['error'];
        $error_msg = isset($error_messages[$error_code]) 
            ? $error_messages[$error_code] 
            : 'Unknown upload error';
        
        throw new Exception($error_msg);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = $_FILES['id_front']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, and GIF images are allowed.');
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($_FILES['id_front']['size'] > $max_size) {
        throw new Exception('File is too large. Maximum size is 5MB.');
    }
    
    $uploadDir = 'temp_uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    $file_extension = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
    $temp_file = $uploadDir . 'temp_' . uniqid() . '_' . time() . '.' . $file_extension;
    
    if (!move_uploaded_file($_FILES['id_front']['tmp_name'], $temp_file)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    if (!file_exists($temp_file) || !is_readable($temp_file)) {
        throw new Exception('Uploaded file is not accessible');
    }
    
    if (!class_exists('IDValidator')) {
        if (file_exists($temp_file)) unlink($temp_file);
        throw new Exception('IDValidator class not found');
    }
    
    $validator = new IDValidator();
    $result = $validator->validateID($temp_file, $valid_id_type);
    
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }

    ob_end_clean();
    
    echo json_encode([
        'valid' => $result['valid'],
        'score' => $result['score'],
        'message' => $result['message'],
        'debug' => [
            'id_type' => $valid_id_type,
            'matched_keywords' => isset($result['matched_keywords']) ? $result['matched_keywords'] : [],
            'required_matches' => isset($result['required_matches']) ? $result['required_matches'] : 0
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($temp_file) && file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    ob_end_clean();
    
    echo json_encode([
        'valid' => false,
        'score' => 0,
        'message' => $e->getMessage()
    ]);
    
} catch (Error $e) {
    if (isset($temp_file) && file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    ob_end_clean();
    
    echo json_encode([
        'valid' => false,
        'score' => 0,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>