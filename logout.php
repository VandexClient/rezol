<?php
session_start();

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Обновление статуса
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("UPDATE users SET status = 'offline', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
} catch(PDOException $e) {
    // Логирование ошибки
}

session_destroy();
header("Location: login.php");
exit();
?>