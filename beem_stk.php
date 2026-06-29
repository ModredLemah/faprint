<?php
/**
 * FA Print — Beem Africa STK Push (Mobile Money Payment)
 * File: payment/beem_stk.php
 *
 * Called by the student page via fetch() when they click "Send USSD Push".
 * Uses the SAME API key & secret key as your OTP (account-level credentials).
 */

require_once '../config.php';  // loads BEEM_API_KEY, BEEM_SECRET_KEY, DB_*, etc.
require_once '../db.php';       // gives you $pdo

header('Content-Type: application/json');

// ── Only accept POST requests ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Read & validate form fields from the student page ───────────────────────
$phone       = trim($_POST['phone']       ?? '');
$amount      = trim($_POST['amount']      ?? '');
$order_id    = trim($_POST['order_id']    ?? '');
$description = trim($_POST['description'] ?? 'FA Print Payment');

if (!$phone || !$amount || !$order_id) {
    echo json_encode(['success' => false, 'message' => 'Phone, amount, and order ID are required']);
    exit;
}

// ── Normalise phone to 255XXXXXXXXX format ──────────────────────────────────
$phone = preg_replace('/\D/', '', $phone);          // strip non-digits
if (str_starts_with($phone, '0'))   $phone = '255' . substr($phone, 1);
if (str_starts_with($phone, '+'))   $phone = substr($phone, 1);
if (!str_starts_with($phone, '255')) {
    echo json_encode(['success' => false, 'message' => 'Invalid TZ phone number']);
    exit;
}

// ── Build the Beem payment payload ──────────────────────────────────────────
/*
 * Beem uses their Bpay / Collection API.
 * Endpoint: POST https://apigw.beemafrica.com:8443/v1/checkout/initiate
 *
 * If Beem gives you a different endpoint in their API Docs page,
 * replace BEEM_PAYMENT_URL below with the correct one.
 */
define('BEEM_PAYMENT_URL', 'https://apigw.beemafrica.com:8443/v1/checkout/initiate');

$payload = json_encode([
    'amount'        => (float) $amount,
    'currency'      => 'TZS',
    'reference'     => $order_id,
    'mobile_number' => $phone,
    'description'   => $description,
    'callback_url'  => 'https://www.faprint.co.tz/payment/beem_callback.php'
]);

// ── Send request to Beem ─────────────────────────────────────────────────────
$ch = curl_init(BEEM_PAYMENT_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(BEEM_API_KEY . ':' . BEEM_SECRET_KEY)
    ],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true    // keep true in production
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    error_log("Beem cURL error: $curl_err");
    echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Try again.']);
    exit;
}

$result = json_decode($response, true);

// ── Log the attempt to the database ─────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO payment_logs (order_id, phone, amount, gateway, reference, status, created_at)
        VALUES (?, ?, ?, 'beem', ?, 'pending', NOW())
    ");
    $stmt->execute([
        $order_id,
        $phone,
        (float) $amount,
        $result['data']['transaction_id'] ?? $result['reference'] ?? ''
    ]);
} catch (Exception $e) {
    // Log silently — don't fail the payment because of a DB log error
    error_log("FA Print payment_logs insert error: " . $e->getMessage());
}

// ── Return result to the student page ───────────────────────────────────────
if ($http_code === 200 && isset($result['data'])) {
    echo json_encode([
        'success'    => true,
        'message'    => 'STK Push sent. Check your phone and enter your PIN.',
        'reference'  => $result['data']['transaction_id'] ?? ''
    ]);
} else {
    $err_msg = $result['message'] ?? $result['error'] ?? 'Payment initiation failed';
    error_log("Beem STK failed [$http_code]: $response");
    echo json_encode(['success' => false, 'message' => $err_msg]);
}
?>
