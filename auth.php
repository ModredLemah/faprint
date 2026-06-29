<?php
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ─────────────────────────────────────────────
//  GET REQUESTS
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_vendor_details') {
        try {
            $user_id = $_GET['user_id'] ?? null;
            if (!$user_id) {
                echo json_encode(['ok' => false, 'error' => 'User ID is required']);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.phone, u.role,
                       v.shop_name, v.latitude, v.longitude
                FROM users u
                JOIN vendors v ON u.id = v.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $vendor_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($vendor_details) {
                echo json_encode(['ok' => true, 'vendor' => $vendor_details]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Vendor not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
    exit();
}

// ─────────────────────────────────────────────
//  POST REQUESTS
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // ── REGISTER STUDENT ──────────────────────
    if ($action === 'register_student') {
        try {
            // Generate OTP before saving anything
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Format phone to 255XXXXXXXXX for Beem Africa
            // Handles: 0712345678 → 255712345678
            //          +255712345678 → 255712345678
            //          255712345678  → 255712345678 (already correct)
            $phone = trim($data['phone']);
            if (substr($phone, 0, 1) === '+') {
                $phone = substr($phone, 1); // remove leading +
            } elseif (substr($phone, 0, 1) === '0') {
                $phone = '255' . substr($phone, 1); // replace leading 0 with 255
            }
            // If it already starts with 255 leave it as-is

            // ── Step 1: Send SMS FIRST — only save to DB if SMS succeeds ──
            $smsResult = sendSMS($phone, $otp);
            $smsData   = json_decode($smsResult, true);

            // Beem returns {"successful": true} on success
            if (!$smsData || !isset($smsData['successful']) || $smsData['successful'] !== true) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Failed to send OTP SMS. Please check your phone number and try again.',
                    'beem'  => $smsData  // remove this line in production
                ]);
                exit();
            }

            // ── Step 2: SMS sent OK → now save student to database ──
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, role, regno, otp)
                VALUES (?, ?, ?, ?, 'student', ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $phone,   // store the formatted 255XXXXXXXXX number
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['regno'],
                $otp
            ]);

            $pdo->commit();

            echo json_encode(['ok' => true, 'otp' => $otp]);
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit();
        }

    // ── LOGIN ─────────────────────────────────
    } elseif ($action === 'login') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();

            if ($user && password_verify($data['password'], $user['password'])) {
                if (!$user['verified']) {
                    echo json_encode(['ok' => false, 'error' => 'Account not verified', 'needs_otp' => true]);
                } else {
                    echo json_encode(['ok' => true, 'user' => [
                        'id'    => $user['id'],
                        'name'  => $user['name'],
                        'email' => $user['email'],
                        'role'  => $user['role']
                    ]]);
                }
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }

    // ── UPDATE VENDOR LOCATION ────────────────
    } elseif ($action === 'update_vendor_location') {
        try {
            $stmt = $pdo->prepare("UPDATE vendors SET latitude = ?, longitude = ?, location = ? WHERE user_id = ?");
            $stmt->execute([
                $data['latitude'],
                $data['longitude'],
                $data['location'] ?? null,
                $data['user_id']
            ]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }

    // ── VERIFY OTP ────────────────────────────
    } elseif ($action === 'verify_otp') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND otp = ?");
            $stmt->execute([$data['email'], $data['otp']]);
            $user = $stmt->fetch();

            if ($user) {
                $stmt = $pdo->prepare("UPDATE users SET verified = 1, otp = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid OTP']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

// ─────────────────────────────────────────────
//  SEND SMS VIA BEEM AFRICA
// ─────────────────────────────────────────────
function sendSMS(string $recipient, string $otp): string
{
    $apiKey    = "f6c862447c98fac0";
    $secretKey = "MmM2MjI0MjQ3YzM4MjI0ZDI2OWQxMGExZTlmZmZkYWExOGU0OTc0ODRmNmRlNTNlNGMzNzE2ZWZkMzRkZGU5Ng==";
    $senderId  = "FAPRINT";

    $message = "Your FA Print verification code is: " . $otp . ". Do not share this code with anyone.";

    $postData = [
        'source_addr'   => $senderId,
        'encoding'      => 0,
        'schedule_time' => '',
        'message'       => $message,
        'recipients'    => [
            ['recipient_id' => '1', 'dest_addr' => $recipient]
        ]
    ];

    $ch = curl_init('https://apisms.beem.africa/v1/send');
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_SSL_VERIFYHOST  => 0,   // needed on most Tanzanian shared hosting
        CURLOPT_SSL_VERIFYPEER  => 0,   // needed on most Tanzanian shared hosting
        CURLOPT_TIMEOUT         => 15,  // don't hang forever
        CURLOPT_HTTPHEADER      => [
            'Authorization: Basic ' . base64_encode("$apiKey:$secretKey"),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS      => json_encode($postData)
    ]);

    $response = curl_exec($ch);

    // If cURL itself failed, return a JSON error string so caller can detect it
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return json_encode(['successful' => false, 'curl_error' => $error]);
    }

    curl_close($ch);
    return $response;
}
?>
