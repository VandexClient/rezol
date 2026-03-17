<?php
// answer_call.php - Исправленная версия
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
    $action = $_POST['action']; // accept или reject
    
    if ($action == 'accept') {
        // Принимаем звонок
        $stmt = $db->prepare("
            UPDATE calls 
            SET status = 'active' 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->execute([$call_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Звонок принят']);
        
    } else {
        // Отклоняем звонок
        $stmt = $db->prepare("
            UPDATE calls 
            SET status = 'rejected' 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->execute([$call_id, $user_id]);
        
        // Удаляем из активных
        $db->prepare("DELETE FROM active_calls WHERE call_id = ?")->execute([$call_id]);
        
        echo json_encode(['success' => true, 'message' => 'Звонок отклонен']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>