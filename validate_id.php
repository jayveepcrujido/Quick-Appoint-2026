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
            'required' => ['owwa', 'overseas', 'worker', 'ofw id card'],
            'optional' => ['welfare', 'administration', 'ofw']
        ],
        'Person with Disability' => [
            'required' => ['pwd', 'disability', 'person with disability'],
            'optional' => ['ncda', 'republic', 'philippines','type of disability']
        ],
        "PH Driver's License" => [
            'required' => ['driver', 'license', 'lto'],
            'optional' => ['land transportation', 'restriction', 'dl no', 'non-professional','professional']
        ],
        'PH National ID' => [
<<<<<<< Updated upstream
            'required' => ['republika ng pilipinas','pambansang pagkakakilanlan','philippine identification', 'philsys', 'national id'],
=======
            'required' => ['philippine identification', 'philsys', 'national id', 'pambansang pagkakakilanlan'],
>>>>>>> Stashed changes
            'optional' => ['psa', 'republic', 'philippines']
        ],
        'PhilHealth' => [
            'required' => ['philhealth', 'philippine health'],
            'optional' => ['insurance', 'member', 'phic','republic of the philippines']
        ],
        'Philippine Passport' => [
            'required' => ['passport', 'republic of the philippines', 'dfa'],
            'optional' => ['passport no', 'surname', 'given name','republika ng pilipinas']
        ],
        'Philippine Statistics Authority Live Birth' => [
            'required' => ['psa', 'birth certificate', 'live birth','certificate of live birth'],
            'optional' => ['philippine statistics', 'registry', 'certificate']
        ],
        'Postal ID' => [
            'required' => ['postal', 'phlpost','postal identity card'],
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
            'optional' => ['osca', 'elderly', 'identification', 'office of the senior citizen affairs']
        ],
        'Social Security System' => [
            'required' => ['sss', 'social security'],
            'optional' => ['system', 'member', 'ss no','unified multi-purpose']
        ],
        'Solo Parent' => [
            'required' => ['solo parent', 'single parent'],
            'optional' => ['dswd', 'identification', 'parent']
        ],
        'Tax Identification Number' => [
            'required' => ['tin', 'bir', 'tax', 'bureau of internal revenue'],
            'optional' => ['bureau', 'internal revenue', 'taxpayer']
        ],
        'Unified Multi-purpose ID' => [
            'required' => ['umid', 'unified','unified multi-purpose'],
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
            
            if (!function_exists('curl_init')) {
                throw new Exception('cURL is not available. Please enable cURL in your PHP configuration.');
            }
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $this->ocrApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $postFields = array(
                'apikey' => $this->ocrApiKey,
                'file' => new CURLFile($imagePath),
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
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
    
    public function extractStructuredData($extractedText, $idType) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        $lines = explode("\n", $extractedText);
        
        switch ($idType) {
            case "PH Driver's License":
                $data = $this->extractDriversLicenseData($lines, $extractedText);
                break;
            case 'PH National ID':
                $data = $this->extractNationalIDData($lines, $extractedText);
                break;
            case 'Philippine Passport':
                $data = $this->extractPassportData($lines, $extractedText);
                break;
            case "Voter's ID":
                $data = $this->extractVotersIDData($lines, $extractedText);
                break;
            case 'Social Security System':
                $data = $this->extractSSSData($lines, $extractedText);
                break;
            case 'PhilHealth':
                $data = $this->extractPhilHealthData($lines, $extractedText);
                break;
            case 'Postal ID':
                $data = $this->extractPostalIDData($lines, $extractedText);
                break;
            case 'Unified Multi-purpose ID':
                $data = $this->extractUMIDData($lines, $extractedText);
                break;
            default:
                $data = $this->extractGenericData($lines, $extractedText);
                break;
        }
        
        return $data;
    }
    
    private function extractDriversLicenseData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name (format: LASTNAME, FIRSTNAME MIDDLENAME)
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^([A-Z\s]+),\s*([A-Z\s]+?)(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // If not found, try alternative pattern
        if (empty($data['first_name'])) {
            if (preg_match('/LAST\s*NAME[:\s]*([A-Z\s]+)/i', $text, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
            }
            if (preg_match('/FIRST\s*NAME[:\s]*([A-Z\s]+)/i', $text, $matches)) {
                $data['first_name'] = ucwords(strtolower(trim($matches[1])));
            }
            if (preg_match('/MIDDLE\s*NAME[:\s]*([A-Z\s]+)/i', $text, $matches)) {
                $data['middle_name'] = ucwords(strtolower(trim($matches[1])));
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $text, $matches)) {
            $data['birthday'] = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        // Extract address
        if (preg_match('/ADDRESS[:\s]*(.+?)(?=\n|NATIONALITY|SEX|$)/i', $text, $matches)) {
            $data['address'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function extractNationalIDData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name fields
        if (preg_match('/(SURNAME|LAST\s*NAME|APELYIDO)[:\s]*([A-Z\s]+?)(?=\n|GIVEN|FIRST)/i', $text, $matches)) {
            $data['last_name'] = ucwords(strtolower(trim($matches[2])));
        }
        if (preg_match('/(GIVEN\s*NAME|FIRST\s*NAME|PANGALAN)[:\s]*([A-Z\s]+?)(?=\n|MIDDLE|DATE)/i', $text, $matches)) {
            $data['first_name'] = ucwords(strtolower(trim($matches[2])));
        }
        if (preg_match('/(MIDDLE\s*NAME|GITNANG\s*PANGALAN)[:\s]*([A-Z\s]+?)(?=\n|DATE|SEX)/i', $text, $matches)) {
            $data['middle_name'] = ucwords(strtolower(trim($matches[2])));
        }
        
        // Extract birthday
        if (preg_match('/(DATE\s*OF\s*BIRTH|BIRTH\s*DATE|PETSA\s*NG\s*KAPANGANAKAN)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i', $text, $matches)) {
            $data['birthday'] = $matches[4] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/(SEX|GENDER|KASARIAN)[:\s]*(M|F|MALE|FEMALE|LALAKI|BABAE)/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE' || $sex == 'LALAKI') ? 'Male' : 'Female';
        }
        
        // Extract address
        if (preg_match('/(PERMANENT\s*ADDRESS|ADDRESS|TIRAHAN)[:\s]*(.+?)(?=\n|PCN|$)/i', $text, $matches)) {
            $data['address'] = trim($matches[2]);
        }
        
        return $data;
    }
    
    private function extractPassportData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name fields
        if (preg_match('/(SURNAME|LAST\s*NAME)[:\s]*([A-Z\s]+?)(?=\n|GIVEN)/i', $text, $matches)) {
            $data['last_name'] = ucwords(strtolower(trim($matches[2])));
        }
        if (preg_match('/(GIVEN\s*NAME|FIRST\s*NAME)[:\s]*([A-Z\s]+?)(?=\n|MIDDLE|DATE)/i', $text, $matches)) {
            $data['first_name'] = ucwords(strtolower(trim($matches[2])));
        }
        if (preg_match('/(MIDDLE\s*NAME)[:\s]*([A-Z\s]+?)(?=\n|DATE|SEX)/i', $text, $matches)) {
            $data['middle_name'] = ucwords(strtolower(trim($matches[2])));
        }
        
        // Extract birthday
        if (preg_match('/(DATE\s*OF\s*BIRTH)[:\s]*(\d{1,2})\s*(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s*(\d{4})/i', $text, $matches)) {
            $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
            $month = $months[strtoupper(substr($matches[3], 0, 3))];
            $data['birthday'] = $matches[4] . '-' . $month . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        return $data;
    }
    
    private function extractVotersIDData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Voter's ID format: LASTNAME, FIRSTNAME MIDDLENAME
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\s]+),\s*([A-Z\s]+?)(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[1]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        // Extract address
        if (preg_match('/ADDRESS[:\s]*(.+?)(?=\n|PRECINCT|$)/i', $text, $matches)) {
            $data['address'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function extractSSSData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\s]+),\s*([A-Z\s]+?)(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        return $data;
    }
    
    private function extractPhilHealthData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\s]+),\s*([A-Z\s]+?)(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        return $data;
    }
    
    private function extractPostalIDData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name
        if (preg_match('/NAME[:\s]*([A-Z\s]+?)(?=\n|ADDRESS)/i', $text, $matches)) {
            $fullName = trim($matches[1]);
            $nameParts = explode(' ', $fullName);
            if (count($nameParts) >= 2) {
                $data['first_name'] = ucwords(strtolower($nameParts[0]));
                $data['last_name'] = ucwords(strtolower($nameParts[count($nameParts) - 1]));
                if (count($nameParts) > 2) {
                    $data['middle_name'] = ucwords(strtolower($nameParts[1]));
                }
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        // Extract address
        if (preg_match('/ADDRESS[:\s]*(.+?)(?=\n|$)/i', $text, $matches)) {
            $data['address'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function extractUMIDData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Extract name
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\s]+),\s*([A-Z\s]+?)(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // Extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Extract sex
        if (preg_match('/\b(SEX|GENDER)[:\s]*(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[2]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        return $data;
    }
    
    private function extractGenericData($lines, $text) {
        $data = [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'birthday' => '',
            'address' => '',
            'sex' => ''
        ];
        
        // Try to extract name in LASTNAME, FIRSTNAME MIDDLENAME format
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\s]{2,}),\s*([A-Z\s]{2,})(?:\s+([A-Z\s]+))?$/i', $line, $matches)) {
                $data['last_name'] = ucwords(strtolower(trim($matches[1])));
                $data['first_name'] = ucwords(strtolower(trim($matches[2])));
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $data['middle_name'] = ucwords(strtolower(trim($matches[3])));
                }
                break;
            }
        }
        
        // Try to extract birthday
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $matches)) {
            $data['birthday'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $text, $matches)) {
            $data['birthday'] = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        }
        
        // Try to extract sex
        if (preg_match('/\b(M|F|MALE|FEMALE)\b/i', $text, $matches)) {
            $sex = strtoupper($matches[1]);
            $data['sex'] = ($sex == 'M' || $sex == 'MALE') ? 'Male' : 'Female';
        }
        
        // Try to extract address
        if (preg_match('/ADDRESS[:\s]*(.+?)(?=\n|$)/i', $text, $matches)) {
            $data['address'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    public function validateID($imagePath, $selectedIDType) {
        try {
            $extractedText = $this->extractTextFromImage($imagePath);
            
            if (empty($extractedText)) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'No text could be extracted from the image. Please ensure the image is clear and readable.',
                    'extracted_text' => '',
                    'extracted_data' => []
                ];
            }
            
            $originalText = $extractedText;
            $extractedText = strtolower($extractedText);
            
            if (!isset($this->idKeywords[$selectedIDType])) {
                return [
                    'valid' => false,
                    'score' => 0,
                    'message' => 'Invalid ID type selected.',
                    'extracted_text' => $extractedText,
                    'extracted_data' => []
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
            
            // Extract structured data
            $structuredData = $this->extractStructuredData($originalText, $selectedIDType);
            
            $message = $isValid 
                ? "ID validation successful! Match score: " . round($totalScore, 2) . "%"
                : "The uploaded ID doesn't match the selected ID type. Please upload the correct ID. Match score: " . round($totalScore, 2) . "%";
            
            return [
                'valid' => $isValid,
                'score' => round($totalScore, 2),
                'message' => $message,
                'extracted_text' => $extractedText,
                'extracted_data' => $structuredData,
                'matched_keywords' => $matchedKeywords,
                'required_matches' => $requiredMatches,
                'total_required' => $totalRequired
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'score' => 0,
                'message' => $e->getMessage(),
                'extracted_text' => '',
                'extracted_data' => []
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
                'id_type' => $id_type,
                'extracted_data' => $validationResult['extracted_data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $validationResult['message'],
                'score' => $validationResult['score'],
                'id_type' => $id_type,
                'extracted_data' => $validationResult['extracted_data']
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