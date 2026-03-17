<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    $chat_with_id = $_POST['user_id'];
    $typing = $_POST['typing'] === 'true' ? 1 : 0;
    
    $stmt = $db->prepare("
        INSERT INTO typing_status (user_id, chat_with_id, is_typing, updated_at) 
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(user_id, chat_with_id) 
        DO UPDATE SET is_typing = ?, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $chat_with_id, $typing, $typing]);
    
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>