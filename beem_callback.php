<?php
/**
 * FA Print — Beem Africa Payment Callback
 * File: payment/beem_callback.php
 *
 * Beem POSTs to this URL automatically after the student completes
 * (or cancels) the STK Push on their phone.
 *
 * Make sure this URL is reachable from the internet:
 *   https://www.faprint.co.tz/payment/beem_callback.php
 */

require_once '../config.php';
require_once '../db.php';

// Read raw JSON body from Beem
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log everything Beem sends (helpful during testing)
error_log("Beem callback received: " . $raw);

// Beem sends: transaction_status, reference (=order_id), transaction_id, amount
$order_id   = $data['reference']          ?? '';
$status     = $data['transaction_status'] ?? '';
$txn_id     = $data['transaction_id']     ?? '';
$amount     = $data['amount']             ?? 0;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['received' => false, 'error' => 'Missing reference']);
    exit;
}

try {
    if ($status === 'SUCCESS') {
        // Mark the order as paid
        $stmt = $pdo->prepare("UPDATE orders SET payment_status='paid', paid_at=NOW() WHERE id=?");
        $stmt->execute([$order_id]);

        // Update the payment log
        $stmt = $pdo->prepare("UPDATE payment_logs SET status='paid', reference=? WHERE order_id=?");
        $stmt->execute([$txn_id, $order_id]);

    } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
        $stmt = $pdo->prepare("UPDATE payment_logs SET status=? WHERE order_id=?");
        $stmt->execute([strtolower($status), $order_id]);
    }

    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Exception $e) {
    error_log("Beem callback DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['received' => false, 'error' => 'DB error']);
}
?>
