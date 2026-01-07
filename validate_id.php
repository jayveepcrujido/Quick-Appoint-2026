<?php

class IDValidator {
    private $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    
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
            if (!file_exists($this->tesseractPath)) {
                throw new Exception('Tesseract OCR not found at: ' . $this->tesseractPath);
            }
            
            if (!file_exists($imagePath)) {
                throw new Exception('Image file not found: ' . $imagePath);
            }
            
            $outputFile = sys_get_temp_dir() . '/' . uniqid('ocr_');
            
            $escapedImagePath = escapeshellarg($imagePath);
            $escapedOutputFile = escapeshellarg($outputFile);
            $escapedTesseract = escapeshellarg($this->tesseractPath);
            
            $command = "$escapedTesseract $escapedImagePath $escapedOutputFile";
            exec($command . ' 2>&1', $output, $returnCode);
            
            $textFile = $outputFile . '.txt';
            if (file_exists($textFile)) {
                $extractedText = file_get_contents($textFile);
                unlink($textFile);
                return $extractedText;
            }
            
            throw new Exception('Failed to extract text from image. Tesseract output: ' . implode("\n", $output));
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