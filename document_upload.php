<?php
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $vendor_id = $_GET['vendor_id'] ?? '';
        if (!$vendor_id) {
            echo json_encode(['ok' => false, 'error' => 'Vendor ID required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT d.*, o.order_number as order_id, u.name as student_name, u.phone as student_phone, o.payment_status
                FROM documents d
                JOIN orders o ON d.order_id = o.id
                JOIN users u ON o.student_id = u.id
                JOIN vendors v ON o.vendor_id = v.id
                WHERE v.user_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            $stmt->execute([$vendor_id]);
            $documents = $stmt->fetchAll();

            echo json_encode(['ok' => true, 'documents' => $documents]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'download') {
        $file_id = $_GET['file_id'] ?? '';
        if (!$file_id) {
            die('File ID required');
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
            $stmt->execute([$file_id]);
            $doc = $stmt->fetch();

            if ($doc) {
                // Update status to downloaded
                $update = $pdo->prepare("UPDATE documents SET status = 'downloaded' WHERE id = ?");
                $update->execute([$file_id]);

                header('Content-Description: File Transfer');
                header('Content-Type: ' . $doc['mime_type']);
                header('Content-Disposition: attachment; filename="' . $doc['original_name'] . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . $doc['file_size']);
                readfile('../' . $doc['file_path']);
                exit;
            } else {
                die('File not found');
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'delete') {
        $file_id = $data['file_id'] ?? '';
        $hard = $data['hard'] ?? false;

        try {
            if ($hard) {
                // Get file path to delete from disk
                $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
                $stmt->execute([$file_id]);
                $doc = $stmt->fetch();
                if ($doc && file_exists('../' . $doc['file_path'])) {
                    unlink('../' . $doc['file_path']);
                }
                $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                $stmt->execute([$file_id]);
            } else {
                // Mark as printed
                $stmt = $pdo->prepare("UPDATE documents SET status = 'printed' WHERE id = ?");
                $stmt->execute([$file_id]);
            }
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
?>
