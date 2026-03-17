<?php
// start_call.php - Исправленная версия
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $caller_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'];
    $type = $_POST['type']; // audio или video
    
    // Сначала удаляем все старые звонки этого пользователя
    $db->prepare("DELETE FROM active_calls WHERE caller_id = ? OR receiver_id = ?")
       ->execute([$caller_id, $caller_id]);
    
    // Обновляем старые звонки
    $db->prepare("
        UPDATE calls 
        SET status = 'ended' 
        WHERE (caller_id = ? OR receiver_id = ?) 
        AND status IN ('calling', 'active')
    ")->execute([$caller_id, $caller_id]);
    
    // Проверяем, нет ли уже активного звонка
    $stmt = $db->prepare("
        SELECT * FROM calls 
        WHERE (caller_id = ? OR receiver_id = ?) 
        AND status IN ('calling', 'active')
    ");
    $stmt->execute([$caller_id, $caller_id]);
    $active = $stmt->fetch();
    
    if ($active) {
        // Если есть старый звонок, завершаем его
        $db->prepare("UPDATE calls SET status = 'ended' WHERE id = ?")->execute([$active['id']]);
        $db->prepare("DELETE FROM active_calls WHERE call_id = ?")->execute([$active['id']]);
    }
    
    // Создаем новый звонок
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        INSERT INTO calls (caller_id, receiver_id, type, status) 
        VALUES (?, ?, ?, 'calling')
    ");
    $stmt->execute([$caller_id, $receiver_id, $type]);
    $call_id = $db->lastInsertId();
    
    $stmt = $db->prepare("
        INSERT INTO active_calls (call_id, caller_id, receiver_id) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$call_id, $caller_id, $receiver_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'call_id' => $call_id,
        'message' => 'Звонок инициирован'
    ]);
    
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>