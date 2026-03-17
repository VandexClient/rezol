<?php
// fix_calls_table.php - Исправление структуры таблицы calls
session_start();

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Исправление таблицы calls</h2>";
    
    // Проверяем существующие колонки
    $columns = $db->query("PRAGMA table_info(calls)")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Текущие колонки:</h3><pre>";
    print_r($columns);
    echo "</pre>";
    
    // Создаем временную таблицу с правильной структурой
    $db->exec("
        CREATE TABLE calls_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            caller_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            type TEXT CHECK(type IN ('audio', 'video')) NOT NULL,
            status TEXT DEFAULT 'calling',
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            answer_time DATETIME,
            end_time DATETIME,
            duration INTEGER DEFAULT 0,
            FOREIGN KEY (caller_id) REFERENCES users(id),
            FOREIGN KEY (receiver_id) REFERENCES users(id)
        )
    ");
    
    // Копируем данные из старой таблицы (если есть)
    $db->exec("
        INSERT INTO calls_new (id, caller_id, receiver_id, type, status, duration)
        SELECT id, caller_id, receiver_id, type, status, duration FROM calls
    ");
    
    // Удаляем старую таблицу
    $db->exec("DROP TABLE calls");
    
    // Переименовываем новую таблицу
    $db->exec("ALTER TABLE calls_new RENAME TO calls");
    
    // Создаем индексы
    $db->exec("CREATE INDEX IF NOT EXISTS idx_calls_status ON calls(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_calls_users ON calls(caller_id, receiver_id)");
    
    echo "<p style='color: green;'>✓ Таблица calls успешно обновлена!</p>";
    
    // Проверяем таблицу active_calls
    $activeExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='active_calls'")->fetch();
    
    if (!$activeExists) {
        $db->exec("
            CREATE TABLE active_calls (
                call_id INTEGER PRIMARY KEY,
                caller_id INTEGER NOT NULL,
                receiver_id INTEGER NOT NULL,
                caller_ready INTEGER DEFAULT 0,
                receiver_ready INTEGER DEFAULT 0,
                FOREIGN KEY (call_id) REFERENCES calls(id),
                FOREIGN KEY (caller_id) REFERENCES users(id),
                FOREIGN KEY (receiver_id) REFERENCES users(id)
            )
        ");
        echo "<p style='color: green;'>✓ Таблица active_calls создана!</p>";
    }
    
    // Очищаем все старые звонки
    $db->exec("DELETE FROM active_calls");
    $db->exec("DELETE FROM calls WHERE status IN ('calling', 'active')");
    
    echo "<p style='color: green;'>✓ Все старые звонки очищены!</p>";
    echo "<p><a href='index.php'>Вернуться в чат</a></p>";
    
} catch(PDOException $e) {
    die("<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>");
}
?>