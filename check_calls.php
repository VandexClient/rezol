<?php
// check_calls.php - Исправленная версия
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_call' => false]);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    
    // Очищаем старые звонки (старше 1 минуты)
    $db->prepare("
        UPDATE calls 
        SET status = 'ended' 
        WHERE receiver_id = ? 
        AND status = 'calling' 
        AND datetime(start_time) < datetime('now', '-1 minute')
    ")->execute([$user_id]);
    
    // Проверяем входящие звонки
    $stmt = $db->prepare("
        SELECT c.*, 
               u.username as caller_name, 
               u.avatar as caller_avatar 
        FROM calls c
        JOIN users u ON c.caller_id = u.id
        WHERE c.receiver_id = ? AND c.status = 'calling'
        ORDER BY c.id DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $incoming = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($incoming) {
        echo json_encode([
            'has_call' => true,
            'call_id' => $incoming['id'],
            'caller_id' => $incoming['caller_id'],
            'caller_name' => $incoming['caller_name'],
            'caller_avatar' => $incoming['caller_avatar'] ?: 'default.png',
            'type' => $incoming['type']
        ]);
    } else {
        echo json_encode(['has_call' => false]);
    }
    
} catch(PDOException $e) {
    echo json_encode(['has_call' => false, 'error' => $e->getMessage()]);
}
?>