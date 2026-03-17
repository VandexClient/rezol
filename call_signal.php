<?php
// call_signal.php - Обработка WebRTC сигналов
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Создаем таблицу для сигналов если её нет
    $db->exec("
        CREATE TABLE IF NOT EXISTS call_signals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            call_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            received INTEGER DEFAULT 0
        )
    ");
    
    $type = $_GET['type'] ?? $_POST['type'] ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Сохраняем сигнал
        $call_id = $_POST['call_id'];
        $data = $_POST['data'];
        
        $stmt = $db->prepare("
            INSERT INTO call_signals (call_id, type, data) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$call_id, $type, $data]);
        
        echo json_encode(['success' => true]);
        
    } else if ($type === 'get') {
        // Получаем новые сигналы
        $call_id = $_GET['call_id'];
        
        $stmt = $db->prepare("
            SELECT * FROM call_signals 
            WHERE call_id = ? AND received = 0 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$call_id]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Отмечаем как полученные
        if ($signals) {
            $ids = implode(',', array_column($signals, 'id'));
            $db->exec("UPDATE call_signals SET received = 1 WHERE id IN ($ids)");
        }
        
        echo json_encode($signals);
    }
    
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>