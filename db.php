<?php
/**
 * Database Connection and Helper Functions
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => DEMO_MODE ? $e->getMessage() : 'Database connection failed'
    ]);
    exit();
}

/**
 * Helper function to get current user from session/token
 */
function getCurrentUser($pdo) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
        $token = $matches[1];
        $user_id = validateToken($token);
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        }
    }
    return null;
}

/**
 * Validate JWT token (placeholder)
 */
function validateToken($token) {
    return null;
}

/**
 * Generate JWT token (placeholder)
 */
function generateToken($user_id) {
    return 'token_' . $user_id . '_' . time();
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate OTP
 */
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send OTP via email (placeholder)
 */
/**
 * Send OTP via Beem Africa SMS
 */
function sendOTP($phone, $otp) {
    // 1. Clean the phone number (remove +, spaces, and leading 0)
    $phone = preg_replace('/\D/', '', $phone);
    if (strpos($phone, '0') === 0) {
        $phone = '255' . substr($phone, 1);
    }
    if (strpos($phone, '255') !== 0) {
        $phone = '255' . $phone;
    }

    $message = "Your FA Print verification code is: $otp. Do not share this code.";

    $postData = array(
        'source_addr' => BEEM_SENDER_ID,
        'encoding' => 0,
        'schedule_time' => '',
        'message' => $message,
        'recipients' => [array('recipient_id' => '1', 'dest_addr' => $phone)]
    );

    $ch = curl_init('https://api.beem.africa/v1/send' );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization:Basic ' . base64_encode(BEEM_API_KEY . ':' . BEEM_SECRET_KEY)
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE );
    curl_close($ch);

    // Log the result for debugging
    if ($httpCode !== 200 ) {
        error_log("Beem SMS Error: " . $response);
        return false;
    }
    
    return true;
}

/**
 * Sanitize file name
 */
function sanitizeFileName($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 */
function isAllowedExtension($filename) {
    $ext = getFileExtension($filename);
    return in_array($ext, ALLOWED_EXTENSIONS);
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

/**
 * Get MIME type from extension
 */
function getMimeType($filename) {
    $ext = getFileExtension($filename);
    $mimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];
    return $mimes[$ext] ?? 'application/octet-stream';
}

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400) {
    sendJSON(['ok' => false, 'error' => $message], $statusCode);
}

/**
 * Send success response
 */
function sendSuccess($data = [], $statusCode = 200) {
    sendJSON(array_merge(['ok' => true], $data), $statusCode);
}

?>
