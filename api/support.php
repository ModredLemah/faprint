<?php
/**
 * Support Tickets API
 * Handles user support ticket creation and management
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
    
    if ($action === 'create_ticket' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['user_id'] || !$data['subject'] || !$data['message']) {
            sendError('User ID, subject, and message required', 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO support_tickets (user_id, subject, message, status)
            VALUES (?, ?, ?, 'open')
        ");
        $stmt->execute([
            $data['user_id'],
            $data['subject'],
            $data['message']
        ]);
        
        $ticket_id = $pdo->lastInsertId();
        
        sendSuccess([
            'message' => 'Ticket created successfully',
            'ticket_id' => $ticket_id
        ], 201);
    }
    
    elseif ($action === 'get_user_tickets' && $method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            sendError('User ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM support_tickets
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        $tickets = $stmt->fetchAll();
        
        sendSuccess(['tickets' => $tickets]);
    }
    
    elseif ($action === 'get_ticket' && $method === 'GET') {
        $ticket_id = $_GET['ticket_id'] ?? null;
        if (!$ticket_id) {
            sendError('Ticket ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as user_name, u.email as user_email
            FROM support_tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            sendError('Ticket not found', 404);
        }
        
        sendSuccess(['ticket' => $ticket]);
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
    
    else {
        sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
