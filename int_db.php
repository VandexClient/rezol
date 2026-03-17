<?php
// init_db.php - Расширенная версия с дополнительными таблицами
try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Включение поддержки внешних ключей
    $db->exec("PRAGMA foreign_keys = ON");
    
    // Создание таблицы пользователей
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        avatar TEXT DEFAULT 'default.png',
        bio TEXT,
        status TEXT DEFAULT 'online',
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        theme TEXT DEFAULT 'light',
        notifications_enabled INTEGER DEFAULT 1,
        sound_enabled INTEGER DEFAULT 1
    )");
    
    // Создание таблицы сообщений
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        message TEXT,
        file_path TEXT,
        file_name TEXT,
        file_size INTEGER,
        is_read INTEGER DEFAULT 0,
        is_edited INTEGER DEFAULT 0,
        is_deleted INTEGER DEFAULT 0,
        reply_to_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id),
        FOREIGN KEY (reply_to_id) REFERENCES messages(id)
    )");
    
    // Создание таблицы реакций на сообщения
    $db->exec("CREATE TABLE IF NOT EXISTS message_reactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        emoji TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(message_id, user_id, emoji),
        FOREIGN KEY (message_id) REFERENCES messages(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Создание таблицы групповых чатов
    $db->exec("CREATE TABLE IF NOT EXISTS group_chats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        avatar TEXT DEFAULT 'group.png',
        creator_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");
    
    // Создание таблицы участников групповых чатов
    $db->exec("CREATE TABLE IF NOT EXISTS group_members (
        group_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role TEXT DEFAULT 'member',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        FOREIGN KEY (group_id) REFERENCES group_chats(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Создание таблицы групповых сообщений
    $db->exec("CREATE TABLE IF NOT EXISTS group_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        sender_id INTEGER NOT NULL,
        message TEXT,
        file_path TEXT,
        file_name TEXT,
        file_size INTEGER,
        is_edited INTEGER DEFAULT 0,
        is_deleted INTEGER DEFAULT 0,
        reply_to_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES group_chats(id),
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (reply_to_id) REFERENCES group_messages(id)
    )");
    
    // Создание таблицы контактов
    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        user_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        nickname TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, contact_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (contact_id) REFERENCES users(id)
    )");
    
    // Создание таблицы заблокированных пользователей
    $db->exec("CREATE TABLE IF NOT EXISTS blocked_users (
        user_id INTEGER NOT NULL,
        blocked_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, blocked_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (blocked_id) REFERENCES users(id)
    )");
    
    // Создание таблицы для статуса "печатает"
    $db->exec("CREATE TABLE IF NOT EXISTS typing_status (
        user_id INTEGER NOT NULL,
        chat_with_id INTEGER NOT NULL,
        is_typing INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, chat_with_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (chat_with_id) REFERENCES users(id)
    )");
    
    // Создание таблицы для уведомлений
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        content TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Создание таблицы для звонков
    $db->exec("CREATE TABLE IF NOT EXISTS calls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        caller_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        status TEXT DEFAULT 'missed',
        duration INTEGER DEFAULT 0,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME,
        FOREIGN KEY (caller_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    )");
    
    // Создание таблицы для избранных сообщений
    $db->exec("CREATE TABLE IF NOT EXISTS saved_messages (
        user_id INTEGER NOT NULL,
        message_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, message_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (message_id) REFERENCES messages(id)
    )");
    
    // Создание индексов для оптимизации
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_group_messages_group ON group_messages(group_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_calls_users ON calls(caller_id, receiver_id)");
    
    // Создание тестового пользователя (опционально)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password, bio) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@example.com', $password, 'Администратор системы']);
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password, bio) VALUES (?, ?, ?, ?)");
        $password2 = password_hash('user123', PASSWORD_DEFAULT);
        $stmt->execute(['user1', 'user1@example.com', $password2, 'Тестовый пользователь']);
        
        echo "Тестовые пользователи созданы.<br>";
    }
    
    echo "База данных успешно создана и обновлена!";
    
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>