<?php

class IDValidator {
    private $ocrApiKey = 'K89335427588957';
    private $ocrApiUrl = 'https://api.ocr.space/parse/image';
    
    private $idKeywords = [
        'Integrated Bar of the Philippines' => [
            'required' => ['integrated bar', 'ibp', 'lawyer'],
            'optional' => ['attorney', 'philippines', 'member']
        ],
        'Overseas Workers Welfare Administration' => [
            'required' => ['owwa', 'overseas', 'worker'],
            'optional' => ['welfare', 'administration', 'ofw']
        ],
        'Person with Disability' => [
            'required' => ['pwd', 'disability', 'person with disability'],
            'optional' => ['ncda', 'republic', 'philippines']
        ],
        "PH Driver's License" => [
            'required' => ['driver', 'license', 'lto'],
            'optional' => ['land transportation', 'restriction', 'dl no']
        ],
        'PH National ID' => [
            'required' => ['philippine identification', 'philsys', 'national id'],
            'optional' => ['psa', 'republic', 'philippines']
        ],
        'PhilHealth' => [
            'required' => ['philhealth', 'philippine health'],
            'optional' => ['insurance', 'member', 'phic']
        ],
        'Philippine Passport' => [
            'required' => ['passport', 'republic of the philippines', 'dfa'],
            'optional' => ['passport no', 'surname', 'given name']
        ],
        'Philippine Statistics Authority Live Birth' => [
            'required' => ['psa', 'birth certificate', 'live birth'],
            'optional' => ['philippine statistics', 'registry', 'certificate']
        ],
        'Postal ID' => [
            'required' => ['postal', 'phlpost'],
            'optional' => ['philippine postal', 'corporation', 'identification']
        ],
        'Professional Regulation Commission' => [
            'required' => ['prc', 'professional regulation'],
            'optional' => ['commission', 'license', 'professional']
        ],
        "Seaman's Book" => [
            'required' => ['seaman', 'maritime', 'marina'],
            'optional' => ['seafarer', 'book', 'continuous']
        ],
        'Senior Citizen' => [
            'required' => ['senior citizen', 'senior'],
            'optional' => ['osca', 'elderly', 'identification']
        ],
        'Social Security System' => [
            'required' => ['sss', 'social security'],
            'optional' => ['system', 'member', 'ss no']
        ],
        'Solo Parent' => [
            'required' => ['solo parent', 'single parent'],
            'optional' => ['dswd', 'identification', 'parent']
        ],
        'Tax Identification Number' => [
            'required' => ['tin', 'bir', 'tax'],
            'optional' => ['bureau', 'internal revenue', 'taxpayer']
        ],
        'Unified Multi-purpose ID' => [
            'required' => ['umid', 'unified'],
            'optional' => ['multi-purpose', 'sss', 'gsis', 'philhealth']
        ],
        "Voter's ID" => [
            'required' => ['comelec', 'voter', 'election'],
            'optional' => ['commission', 'precinct', 'voter\'s']
        ]
    ];
    
    public function extractTextFromImage($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                throw new Exception('Image file not found: ' . $imagePath);
            }
            
            // Check if cURL is available
            if (!function_exists('curl_init')) {
                throw new Exception('cURL is not available. Please enable cURL in your PHP configuration.');
            }
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $this->ocrApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Prepare the file for upload - FIX: Use string values for boolean parameters
            $postFields = array(
                'apikey' => $this->ocrApiKey,
                'file' => new CURLFile($imagePath),
                'language' => 'eng',
                'isOverlayRequired' => 'false',  // Changed from false to 'false'
                'detectOrientation' => 'true',   // Changed from true to 'true'
                'scale' => 'true',               // Changed from true to 'true'
                'OCREngine' => '2'
            );
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception('cURL Error: ' . $error);
            }
            
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('OCR API returned HTTP code: ' . $httpCode);
            }
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new Exception('Failed to parse OCR API response');
            }
            
            if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing'] === true) {
                $errorMessage = isset($result['ErrorMessage']) ? $result['ErrorMessage'][0] : 'Unknown error';
                throw new Exception('OCR Processing Error: ' . $errorMessage);
            }
            
            if (!isset($result['ParsedResults']) || empty($result['ParsedResults'])) {
                throw new Exception('No text could be extracted from the image');
            }
            
            $extractedText = $result['ParsedResults'][0]['ParsedText'];
            
            return $extractedText;
            
        } catch (Exception $e) {
            throw new Exception('OCR Error: ' . $e->getMessage());
        }
    }
    
    public function validateID($imagePath, $selectedIDType) {
        try {
            $extractedText = $this->extractTextFromImage($imagePath);
            
            if (empty($extractedText)) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'No text could be extracted from the image. Please ensure the image is clear and readable.',
                    'extracted_text' => ''
                ];
            }
            
            $extractedText = strtolower($extractedText);
            
            if (!isset($this->idKeywords[$selectedIDType])) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'Invalid ID type selected.',
                    'extracted_text' => $extractedText
                ];
            }
            
            $keywords = $this->idKeywords[$selectedIDType];
            $requiredKeywords = $keywords['required'];
            $optionalKeywords = $keywords['optional'];
            
            $requiredMatches = 0;
            $optionalMatches = 0;
            $matchedKeywords = [];
            
            foreach ($requiredKeywords as $keyword) {
                if (strpos($extractedText, strtolower($keyword)) !== false) {
                    $requiredMatches++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            foreach ($optionalKeywords as $keyword) {
                if (strpos($extractedText, strtolower($keyword)) !== false) {
                    $optionalMatches++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            $totalRequired = count($requiredKeywords);
            $requiredScore = ($totalRequired > 0) ? ($requiredMatches / $totalRequired) * 70 : 0;
            $optionalScore = (count($optionalKeywords) > 0) ? ($optionalMatches / count($optionalKeywords)) * 30 : 0;
            $totalScore = $requiredScore + $optionalScore;
            
            $isValid = $totalScore >= 50;
            
            $message = $isValid 
                ? "ID validation successful! Match score: " . round($totalScore, 2) . "%"
                : "The uploaded ID doesn't match the selected ID type. Please upload the correct ID. Match score: " . round($totalScore, 2) . "%";
            
            return [
                'valid' => $isValid,
                'score' => round($totalScore, 2),
                'message' => $message,
                'extracted_text' => $extractedText,
                'matched_keywords' => $matchedKeywords,
                'required_matches' => $requiredMatches,
                'total_required' => $totalRequired
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'score' => 0,
                'message' => $e->getMessage(),
                'extracted_text' => ''
            ];
        }
    }
}

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }
    
    if (!isset($_FILES['id_front']) || !isset($_FILES['selfie_with_id']) || !isset($_POST['id_type'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required files or ID type'
        ]);
        exit;
    }
    
    try {
        $id_type = $_POST['id_type'];
        $id_front_file = $_FILES['id_front'];
        $selfie_file = $_FILES['selfie_with_id'];
        
        // Validate file uploads
        if ($id_front_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading ID front image');
        }
        
        if ($selfie_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading selfie image');
        }
        
        $tempDir = sys_get_temp_dir() . '/id_validation/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $temp_id_path = $tempDir . uniqid() . '_' . basename($id_front_file['name']);
        $temp_selfie_path = $tempDir . uniqid() . '_' . basename($selfie_file['name']);
        
        if (!move_uploaded_file($id_front_file['tmp_name'], $temp_id_path)) {
            throw new Exception('Failed to save ID image');
        }
        
        if (!move_uploaded_file($selfie_file['tmp_name'], $temp_selfie_path)) {
            if (file_exists($temp_id_path)) unlink($temp_id_path);
            throw new Exception('Failed to save selfie image');
        }
        
        $validator = new IDValidator();
        $validationResult = $validator->validateID($temp_id_path, $id_type);
        
        // Clean up temporary files
        if (file_exists($temp_id_path)) unlink($temp_id_path);
        if (file_exists($temp_selfie_path)) unlink($temp_selfie_path);
        
        if ($validationResult['valid']) {
            echo json_encode([
                'success' => true,
                'message' => $validationResult['message'],
                'score' => $validationResult['score'],
                'id_type' => $id_type
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $validationResult['message'],
                'score' => $validationResult['score'],
                'id_type' => $id_type
            ]);
        }
        
    } catch (Exception $e) {
        error_log('ID Validation Error: ' . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Validation error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}
?>