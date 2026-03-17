<?php
// add_calls_table.php
try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Таблица звонков
    $db->exec("
        CREATE TABLE IF NOT EXISTS calls (
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
    
    // Таблица для активных звонков
    $db->exec("
        CREATE TABLE IF NOT EXISTS active_calls (
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
    
    echo "Таблицы для звонков успешно созданы!";
    
} catch(PDOException $e) {
    die("Ошибка: " . $e->getMessage());
}
?>