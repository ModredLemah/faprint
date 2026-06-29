<?php
/**
 * Orders API
 * Handles document uploads, order creation, status updates, and order history
 */

require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // ═══════════════════════════════════════════════════════════════════════════
    // ORDER CREATION & MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════
    
    if ($action === 'create_order' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['student_id'] || !$data['vendor_id'] || !$data['documents']) {
            sendError('Missing required fields', 400);
        }
        
        $pdo->beginTransaction();
        try {
            // Generate order number
            $order_number = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            // Calculate total price
            $total_price = 0;
            foreach ($data['documents'] as $doc) {
                $price = ($doc['color_mode'] === 'color' ? $doc['price_color'] : $doc['price_bw']) * $doc['copies'];
                if ($doc['binding']) {
                    $price += BINDING_PRICE;
                }
                $total_price += $price;
            }
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_number, student_id, vendor_id, total_price, status, payment_status)
                VALUES (?, ?, ?, ?, 'pending', 'unpaid')
            ");
            $stmt->execute([$order_number, $data['student_id'], $data['vendor_id'], $total_price]);
            $order_id = $pdo->lastInsertId();
            
            // Create documents for this order
            foreach ($data['documents'] as $doc) {
                $stmt = $pdo->prepare("
                    INSERT INTO documents (
                        order_id, original_name, stored_name, file_path, mime_type, file_size,
                        copies, color_mode, binding, notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $order_id,
                    $doc['original_name'],
                    $doc['stored_name'],
                    $doc['file_path'],
                    $doc['mime_type'],
                    $doc['file_size'],
                    $doc['copies'],
                    $doc['color_mode'],
                    $doc['binding'] ? 1 : 0,
                    $doc['notes'] ?? null
                ]);
            }
            
            $pdo->commit();
            
            sendSuccess([
                'message' => 'Order created successfully',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total_price' => $total_price
            ], 201);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            sendError($e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'get_order' && $method === 'GET') {
        $order_id = $_GET['order_id'] ?? null;
        if (!$order_id) {
            sendError('Order ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as student_name, u.email as student_email,
                   v.shop_name, v.user_id as vendor_user_id
            FROM orders o
            JOIN users u ON o.student_id = u.id
            JOIN vendors v ON o.vendor_id = v.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendError('Order not found', 404);
        }
        
        // Get documents for this order
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $documents = $stmt->fetchAll();
        $order['documents'] = $documents;
        
        sendSuccess(['order' => $order]);
    }
    
    elseif ($action === 'get_student_orders' && $method === 'GET') {
        $student_id = $_GET['student_id'] ?? null;
        if (!$student_id) {
            sendError('Student ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT o.*, v.shop_name, v.user_id as vendor_user_id
            FROM orders o
            JOIN vendors v ON o.vendor_id = v.id
            WHERE o.student_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$student_id]);
        $orders = $stmt->fetchAll();
        
        sendSuccess(['orders' => $orders]);
    }
    
    elseif ($action === 'get_vendor_orders' && $method === 'GET') {
        $vendor_id = $_GET['vendor_id'] ?? null;
        if (!$vendor_id) {
            sendError('Vendor ID required', 400);
        }
        
        // Get vendor's user_id
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            sendError('Vendor not found', 404);
        }
        
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as student_name, u.email as student_email, u.phone as student_phone
            FROM orders o
            JOIN users u ON o.student_id = u.id
            WHERE o.vendor_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$vendor['id']]);
        $orders = $stmt->fetchAll();
        
        sendSuccess(['orders' => $orders]);
    }
    
    elseif ($action === 'update_order_status' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['order_id'] || !$data['status']) {
            sendError('Order ID and status required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$data['status'], $data['order_id']]);
        
        sendSuccess(['message' => 'Order status updated']);
    }
    
    elseif ($action === 'update_payment_status' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['order_id'] || !$data['payment_status']) {
            sendError('Order ID and payment status required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$data['payment_status'], $data['order_id']]);
        
        sendSuccess(['message' => 'Payment status updated']);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // DOCUMENT MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_documents' && $method === 'GET') {
        $vendor_user_id = $_GET['vendor_user_id'] ?? null;
        if (!$vendor_user_id) {
            sendError('Vendor user ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT d.*, o.order_number, u.name as student_name, u.phone as student_phone,
                   o.payment_status
            FROM documents d
            JOIN orders o ON d.order_id = o.id
            JOIN users u ON o.student_id = u.id
            JOIN vendors v ON o.vendor_id = v.id
            WHERE v.user_id = ?
            ORDER BY d.uploaded_at DESC
        ");
        $stmt->execute([$vendor_user_id]);
        $documents = $stmt->fetchAll();
        
        sendSuccess(['documents' => $documents]);
    }
    
    elseif ($action === 'update_document_status' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['document_id'] || !$data['status']) {
            sendError('Document ID and status required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE documents SET status = ? WHERE id = ?");
        $stmt->execute([$data['status'], $data['document_id']]);
        
        sendSuccess(['message' => 'Document status updated']);
    }
    
    elseif ($action === 'download_document' && $method === 'GET') {
        $document_id = $_GET['document_id'] ?? null;
        if (!$document_id) {
            sendError('Document ID required', 400);
        }
        
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch();
        
        if (!$doc) {
            sendError('Document not found', 404);
        }
        
        // Update status to downloaded
        $stmt = $pdo->prepare("UPDATE documents SET status = 'downloaded' WHERE id = ?");
        $stmt->execute([$document_id]);
        
        // Return download URL or file content
        $file_path = __DIR__ . '/../' . $doc['file_path'];
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $doc['mime_type']);
            header('Content-Disposition: attachment; filename="' . $doc['original_name'] . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $doc['file_size']);
            readfile($file_path);
            exit;
        } else {
            sendError('File not found on server', 404);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // STATISTICS
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_vendor_stats' && $method === 'GET') {
        $vendor_user_id = $_GET['vendor_user_id'] ?? null;
        if (!$vendor_user_id) {
            sendError('Vendor user ID required', 400);
        }
        
        // Get vendor ID
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $stmt->execute([$vendor_user_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            sendError('Vendor not found', 404);
        }
        
        $vendor_id = $vendor['id'];
        
        // Get stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as total_revenue
            FROM orders WHERE vendor_id = ?
        ");
        $stmt->execute([$vendor_id]);
        $stats = $stmt->fetch();
        
        sendSuccess(['stats' => $stats]);
    }
    
    else {
        sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
