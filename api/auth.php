<?php
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_vendor_details') {
        try {
            $user_id = $_GET['user_id'] ?? null;
            if (!$user_id) {
                echo json_encode(['ok' => false, 'error' => 'User ID is required']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.role, v.shop_name, v.latitude, v.longitude FROM users u JOIN vendors v ON u.id = v.user_id WHERE u.id = ?");
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'register_student') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, regno, otp) VALUES (?, ?, ?, ?, 'student', ?, ?)");
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['regno'],
                $otp
            ]);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'otp' => $otp]); // In real app, send via email
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'register_vendor') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, otp) VALUES (?, ?, ?, ?, 'vendor', ?)");
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $otp
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO vendors (user_id, shop_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $data['shop_name']]);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'otp' => $otp]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
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
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]]);
                }
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'update_vendor_location') {
        try {
            $stmt = $pdo->prepare("UPDATE vendors SET latitude = ?, longitude = ?, location = ? WHERE user_id = ?");
            $stmt->execute([$data["latitude"], $data["longitude"], $data["location"] ?? null, $data["user_id"]]);
            echo json_encode(["ok" => true]);
        } catch (Exception $e) {
            echo json_encode(["ok" => false, "error" => $e->getMessage()]);
        }
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
?>
