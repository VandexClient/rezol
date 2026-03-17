<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $creator_id = $_SESSION['user_id'];
    $name = trim($_POST['name']);
    $members = isset($_POST['members']) ? $_POST['members'] : [];
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Название группы обязательно']);
        exit();
    }
    
    if (count($members) < 2) {
        echo json_encode(['success' => false, 'error' => 'Минимум 2 участника']);
        exit();
    }
    
    // Начало транзакции
    $db->beginTransaction();
    
    // Создание группы
    $stmt = $db->prepare("INSERT INTO group_chats (name, creator_id) VALUES (?, ?)");
    $stmt->execute([$name, $creator_id]);
    $group_id = $db->lastInsertId();
    
    // Добавление создателя
    $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$group_id, $creator_id]);
    
    // Добавление участников
    $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
    foreach ($members as $member_id) {
        if ($member_id != $creator_id) {
            $stmt->execute([$group_id, $member_id]);
        }
    }
    
    // Создание системного сообщения
    $stmt = $db->prepare("
        INSERT INTO group_messages (group_id, sender_id, message) 
        VALUES (?, ?, ?)
    ");
    $message = "Группа создана пользователем " . $_SESSION['username'];
    $stmt->execute([$group_id, $creator_id, $message]);
    
    // Коммит транзакции
    $db->commit();
    
    echo json_encode(['success' => true, 'group_id' => $group_id]);
    
} catch(PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>