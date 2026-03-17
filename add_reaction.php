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
    $message_id = $_POST['message_id'];
    $emoji = $_POST['emoji'];
    
    // Проверка существования реакции
    $stmt = $db->prepare("
        SELECT id FROM message_reactions 
        WHERE message_id = ? AND user_id = ? AND emoji = ?
    ");
    $stmt->execute([$message_id, $user_id, $emoji]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Удаление реакции
        $stmt = $db->prepare("DELETE FROM message_reactions WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        // Добавление реакции
        $stmt = $db->prepare("
            INSERT INTO message_reactions (message_id, user_id, emoji) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$message_id, $user_id, $emoji]);
    }
    
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>