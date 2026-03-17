<?php
// check_call_status.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $call_id = $_GET['call_id'];
    
    $stmt = $db->prepare("SELECT status FROM calls WHERE id = ?");
    $stmt->execute([$call_id]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => $call['status'] ?? 'ended']);
    
} catch(PDOException $e) {
    echo json_encode(['status' => 'error']);
}
?>