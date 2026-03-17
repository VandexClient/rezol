<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    $call_id = $_POST['call_id'];
    $duration = $_POST['duration'] ?? 0;
    
    $db->beginTransaction();
    
    // Обновляем статус звонка
    $stmt = $db->prepare("
        UPDATE calls 
        SET status = 'ended', end_time = CURRENT_TIMESTAMP, duration = ? 
        WHERE id = ? AND (caller_id = ? OR receiver_id = ?)
    ");
    $stmt->execute([$duration, $call_id, $user_id, $user_id]);
    
    // Удаляем из активных
    $db->prepare("DELETE FROM active_calls WHERE call_id = ?")->execute([$call_id]);
    
    $db->commit();
    
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>