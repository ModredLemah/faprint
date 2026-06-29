<?php
/**
 * User Management API
 * Handles user registration, login, profile management, and vendor operations
 */

require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════════════
    
    if ($action === 'register_student' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (!$data['name'] || !$data['email'] || !$data['phone'] || !$data['password'] || !$data['regno']) {
            sendError('All fields are required', 400);
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            sendError('Email already registered', 400);
        }
        
        $pdo->beginTransaction();
        try {
            $otp = generateOTP();
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, role, regno, otp, verified)
                VALUES (?, ?, ?, ?, 'student', ?, ?, 0)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                hashPassword($data['password']),
                $data['regno'],
                $otp
            ]);
            
           $pdo->commit();
            
            // 1. Tuma OTP kupitia Beem Africa SMS
            if (function_exists('sendSMS')) {
                sendSMS($data['phone'], $otp);
            }
            
            // 2. Rudisha majibu safi kwenda HTML bila kuonyesha OTP (otp => null)
            sendSuccess([
                'message' => 'Registration successful. Please verify your phone number.',
                'otp' => null
            ], 201);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            sendError($e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'register_vendor' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['name'] || !$data['email'] || !$data['phone'] || !$data['password'] || !$data['shop_name']) {
            sendError('All fields are required', 400);
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            sendError('Email already registered', 400);
        }
        
        $pdo->beginTransaction();
        try {
            $otp = generateOTP();
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, role, otp, verified)
                VALUES (?, ?, ?, ?, 'vendor', ?, 0)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                hashPassword($data['password']),
                $otp
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO vendors (user_id, shop_name, latitude, longitude)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['shop_name'],
                CAMPUS_LAT,
                CAMPUS_LNG
            ]);
            
            $pdo->commit();
            
            sendOTP($data['email'], $otp);
            
            sendSuccess([
                'message' => 'Vendor registration successful. Please verify your email.',
                'otp' => DEMO_MODE ? $otp : null
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendError($e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'verify_otp' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['email'] || !$data['otp']) {
            sendError('Email and OTP required', 400);
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND otp = ?");
        $stmt->execute([$data['email'], $data['otp']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('Invalid OTP', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET verified = 1, otp = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        sendSuccess(['message' => 'Email verified successfully']);
    }
    
    elseif ($action === 'login' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['email'] || !$data['password']) {
            sendError('Email and password required', 400);
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($data['password'], $user['password'])) {
            sendError('Invalid email or password', 401);
        }
        
        if (!$user['verified']) {
            sendError('Please verify your email first', 403);
        }
        
        $token = generateToken($user['id']);
        
        sendSuccess([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role']
            ]
        ]);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // USER PROFILE ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_profile' && $method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            sendError('User ID required', 400);
        }
        
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('User not found', 404);
        }
        
        // Get vendor details if applicable
        if ($user['role'] === 'vendor') {
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $vendor = $stmt->fetch();
            $user['vendor'] = $vendor;
        }
        
        sendSuccess(['user' => $user]);
    }
    
    elseif ($action === 'update_profile' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        
        if (!$user_id) {
            sendError('User ID required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$data['name'] ?? null, $data['phone'] ?? null, $user_id]);
        
        sendSuccess(['message' => 'Profile updated successfully']);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // VENDOR ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_vendor_details' && $method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            sendError('User ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.role, 
                   v.id as vendor_id, v.shop_name, v.latitude, v.longitude, v.location,
                   v.price_bw, v.price_color, v.status, v.photos
            FROM users u
            LEFT JOIN vendors v ON u.id = v.user_id
            WHERE u.id = ? AND u.role = 'vendor'
        ");
        $stmt->execute([$user_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            sendError('Vendor not found', 404);
        }
        
        sendSuccess(['vendor' => $vendor]);
    }
    
    elseif ($action === 'update_vendor_location' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        
        if (!$user_id || !isset($data['latitude']) || !isset($data['longitude'])) {
            sendError('User ID, latitude, and longitude required', 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE vendors 
            SET latitude = ?, longitude = ?, location = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $data['latitude'],
            $data['longitude'],
            $data['location'] ?? null,
            $user_id
        ]);
        
        sendSuccess(['message' => 'Location updated successfully']);
    }
    
    elseif ($action === 'update_vendor_prices' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        
        if (!$user_id || !isset($data['price_bw']) || !isset($data['price_color'])) {
            sendError('User ID and prices required', 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE vendors 
            SET price_bw = ?, price_color = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $data['price_bw'],
            $data['price_color'],
            $user_id
        ]);
        
        sendSuccess(['message' => 'Prices updated successfully']);
    }
    
    elseif ($action === 'get_all_vendors' && $method === 'GET') {
        $status = $_GET['status'] ?? 'approved';
        
        $sql = "
            SELECT u.id, u.name, u.email, u.phone,
                   v.id as vendor_id, v.shop_name, v.latitude, v.longitude, v.location,
                   v.price_bw, v.price_color, v.status, v.photos
            FROM users u
            JOIN vendors v ON u.id = v.user_id
        ";
        
        if ($status !== 'all') {
            $sql .= " WHERE v.status = ?";
            $stmt = $pdo->prepare($sql . " ORDER BY v.shop_name ASC");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY v.shop_name ASC");
            $stmt->execute();
        }
        
        $vendors = $stmt->fetchAll();
        sendSuccess(['vendors' => $vendors]);
    }
    
    else {
        sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
function sendSMS(string $recipient, string $otp) {
    // Endpoints ya Beem Africa kwa ajili ya kutuma Local SMS
    $url = 'https://api.beem.co.tz/v1/sms';
    
    // Inachukua siri zako ulizoweka kwenye config.php
    $apiKey = BEEM_API_KEY;
    $secretKey = BEEM_SECRET_KEY;
    $senderId = BEEM_SENDER_ID; 

    $message = "Your FA Print verification code is: " . $otp;

    // Kusafisha namba ya simu ili kuondoa alama ya '+' na nafasi (spaces)
    $clean_phone = str_replace(['+', ' '], '', $recipient);

    $postData = array(
        'source_addr' => $senderId,
        'schedule_time' => '',
        'message' => $message,
        'recipients' => array(
            array(
                'recipient_id' => '1',
                'dest_addr' => $clean_phone
            )
        )
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Authorization:Basic ' . base64_encode("$apiKey:$secretKey"),
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($postData)
    ));

    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
?>
