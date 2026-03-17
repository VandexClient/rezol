<?php
// clear_calls.php - Исправленная версия
session_start();

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Очистка звонков</h2>";
    
    // Удаляем все из active_calls
    $db->exec("DELETE FROM active_calls");
    echo "<p>✓ Таблица active_calls очищена</p>";
    
    // Обновляем все звонки
    $db->exec("UPDATE calls SET status = 'ended' WHERE status IN ('calling', 'active')");
    echo "<p>✓ Все активные звонки завершены</p>";
    
    // Удаляем старые звонки (старше 1 дня)
    $stmt = $db->prepare("DELETE FROM calls WHERE datetime(start_time) < datetime('now', '-1 day')");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "<p>✓ Удалено старых звонков: $deleted</p>";
    
    echo "<p style='color: green; font-weight: bold;'>✓ Все звонки очищены!</p>";
    echo "<p><a href='index.php'>Вернуться в чат</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}
?>