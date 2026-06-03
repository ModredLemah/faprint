<?php
/**
 * Admin API
 * Handles admin operations: vendor approval, order management, statistics, and support tickets
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
    // VENDOR MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════
    
    if ($action === 'get_all_vendors' && $method === 'GET') {
        $status = $_GET['status'] ?? null;
        
        $query = "
            SELECT u.id, u.name, u.email, u.phone, u.created_at,
                   v.id as vendor_id, v.shop_name, v.latitude, v.longitude, v.location,
                   v.price_bw, v.price_color, v.status, v.photos,
                   COUNT(o.id) as total_orders,
                   SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders
            FROM users u
            JOIN vendors v ON u.id = v.user_id
            LEFT JOIN orders o ON v.id = o.vendor_id
            " . ($status ? "WHERE v.status = ?" : "") . "
            GROUP BY v.id
            ORDER BY u.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        if ($status) {
            $stmt->execute([$status]);
        } else {
            $stmt->execute();
        }
        $vendors = $stmt->fetchAll();
        
        sendSuccess(['vendors' => $vendors]);
    }
    
    elseif ($action === 'approve_vendor' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['vendor_id']) {
            sendError('Vendor ID required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE vendors SET status = 'approved' WHERE id = ?");
        $stmt->execute([$data['vendor_id']]);
        
        sendSuccess(['message' => 'Vendor approved successfully']);
    }
    
    elseif ($action === 'suspend_vendor' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['vendor_id']) {
            sendError('Vendor ID required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE vendors SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$data['vendor_id']]);
        
        sendSuccess(['message' => 'Vendor suspended successfully']);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // ORDER MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_all_orders' && $method === 'GET') {
        $status = $_GET['status'] ?? null;
        
        $query = "
            SELECT o.*, u.name as student_name, u.email as student_email,
                   v.shop_name, v.user_id as vendor_user_id
            FROM orders o
            JOIN users u ON o.student_id = u.id
            JOIN vendors v ON o.vendor_id = v.id
            " . ($status ? "WHERE o.status = ?" : "") . "
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        if ($status) {
            $stmt->execute([$status]);
        } else {
            $stmt->execute();
        }
        $orders = $stmt->fetchAll();
        
        sendSuccess(['orders' => $orders]);
    }
    
    elseif ($action === 'get_order_details' && $method === 'GET') {
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
        
        // Get documents
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order['documents'] = $stmt->fetchAll();
        
        sendSuccess(['order' => $order]);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // SUPPORT TICKETS
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_all_tickets' && $method === 'GET') {
        $status = $_GET['status'] ?? null;
        
        $query = "
            SELECT t.*, u.name as user_name, u.email as user_email, u.role
            FROM support_tickets t
            JOIN users u ON t.user_id = u.id
            " . ($status ? "WHERE t.status = ?" : "") . "
            ORDER BY t.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        if ($status) {
            $stmt->execute([$status]);
        } else {
            $stmt->execute();
        }
        $tickets = $stmt->fetchAll();
        
        sendSuccess(['tickets' => $tickets]);
    }
    
    elseif ($action === 'close_ticket' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['ticket_id']) {
            sendError('Ticket ID required', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?");
        $stmt->execute([$data['ticket_id']]);
        
        sendSuccess(['message' => 'Ticket closed successfully']);
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // STATISTICS & DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════
    
    elseif ($action === 'get_dashboard_stats' && $method === 'GET') {
        $stats = [];
        
        // Total users by role
        $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $stmt->execute();
        $user_stats = $stmt->fetchAll();
        $stats['users_by_role'] = $user_stats;
        
        // Total orders and revenue
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_price) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as paid_revenue
            FROM orders
        ");
        $stmt->execute();
        $stats['order_stats'] = $stmt->fetch();
        
        // Vendor stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_vendors,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_vendors,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_vendors,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_vendors
            FROM vendors
        ");
        $stmt->execute();
        $stats['vendor_stats'] = $stmt->fetch();
        
        // Open tickets
        $stmt = $pdo->prepare("SELECT COUNT(*) as open_tickets FROM support_tickets WHERE status = 'open'");
        $stmt->execute();
        $stats['ticket_stats'] = $stmt->fetch();
        
        sendSuccess(['stats' => $stats]);
    }
    
    elseif ($action === 'get_revenue_report' && $method === 'GET') {
        $period = $_GET['period'] ?? 'month'; // day, week, month, year
        
        $date_format = match($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-W%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m'
        };
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as order_count,
                SUM(total_price) as revenue,
                SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as paid_revenue
            FROM orders
            GROUP BY DATE_FORMAT(created_at, ?)
            ORDER BY period DESC
        ");
        $stmt->execute([$date_format, $date_format]);
        $report = $stmt->fetchAll();
        
        sendSuccess(['report' => $report]);
    }
    
    else {
        sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
