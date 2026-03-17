<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получение информации о текущем пользователе
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получение непрочитанных сообщений
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Определение темы
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Skype 2025 - Мессенджер</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Базовые стили */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            height: 100vh;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        /* Основной контейнер */
        .app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 100%;
            margin: 0;
            background: var(--bg-primary);
        }
        
        /* Область чата (занимает всё свободное место) */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        
        /* Шапка чата */
        .chat-header {
            padding: 12px 16px;
            background: var(--block-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            flex: 1;
        }
        
        .small-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-primary);
        }
        
        .chat-user-info h3 {
            font-size: 16px;
            margin-bottom: 2px;
            color: var(--text-primary);
        }
        
        .status-text {
            font-size: 12px;
        }
        
        .status-text.online {
            color: var(--success-color, #22c55e);
        }
        
        .status-text.offline {
            color: var(--text-secondary);
        }
        
        .chat-actions {
            display: flex;
            gap: 16px;
        }
        
        .chat-actions i {
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 50%;
        }
        
        .chat-actions i:hover {
            background: var(--bg-tertiary);
            color: var(--accent-primary);
        }
        
        /* Контейнер сообщений */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        /* Сообщения */
        .message {
            display: flex;
            margin-bottom: 4px;
            animation: fadeIn 0.2s ease;
        }
        
        .message.own {
            justify-content: flex-end;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin: 0 8px;
            align-self: flex-end;
        }
        
        .message-content {
            max-width: 75%;
            background: var(--block-bg);
            padding: 10px 14px;
            border-radius: 18px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            position: relative;
            word-wrap: break-word;
        }
        
        .message.own .message-content {
            background: var(--accent-primary);
            color: white;
        }
        
        .message-sender {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--accent-primary);
        }
        
        .message.own .message-sender {
            display: none;
        }
        
        .message-text {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .message-time {
            font-size: 10px;
            color: var(--text-tertiary);
            margin-top: 4px;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
        }
        
        .message.own .message-time {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .message-time .read {
            color: var(--success-color);
        }
        
        .message-file {
            background: var(--bg-secondary);
            padding: 8px 12px;
            border-radius: 12px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message.own .message-file {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .message-file i {
            font-size: 20px;
        }
        
        .message-file a {
            color: var(--accent-primary);
            text-decoration: none;
            font-size: 13px;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .message.own .message-file a {
            color: white;
        }
        
        .file-size {
            font-size: 10px;
            color: var(--text-tertiary);
        }
        
        /* Системные сообщения */
        .system-message {
            text-align: center;
            margin: 16px 0;
        }
        
        .system-message .message-content {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
            max-width: 90%;
        }
        
        /* Поле ввода */
        .message-input-container {
            padding: 12px 16px;
            background: var(--block-bg);
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .message-input-container form {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-secondary);
            border-radius: 28px;
            padding: 4px 4px 4px 16px;
        }
        
        #messageText {
            flex: 1;
            border: none;
            background: none;
            padding: 12px 0;
            outline: none;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        #messageText::placeholder {
            color: var(--text-tertiary);
        }
        
        .attach-btn, .emoji-btn, .send-btn {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .attach-btn, .emoji-btn {
            background: transparent;
            color: var(--text-secondary);
        }
        
        .attach-btn:hover, .emoji-btn:hover {
            background: var(--bg-tertiary);
            color: var(--accent-primary);
        }
        
        .send-btn {
            background: var(--accent-primary);
            color: white;
        }
        
        .send-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
        }
        
        /* Нижняя навигация */
        .bottom-nav {
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 6px 12px;
            background: var(--block-bg);
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 8px 12px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.2s ease;
            flex: 1;
            max-width: 90px;
            position: relative;
        }
        
        .nav-item i {
            font-size: 22px;
        }
        
        .nav-item span {
            font-size: 11px;
            font-weight: 500;
        }
        
        .nav-item.active {
            color: var(--accent-primary);
        }
        
        .nav-item.active i {
            transform: scale(1.1);
        }
        
        .badge {
            position: absolute;
            top: 2px;
            right: 18px;
            min-width: 18px;
            height: 18px;
            background: var(--accent-like);
            color: white;
            border-radius: 10px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--block-bg);
        }
        
        /* Панель контактов */
        .contacts-panel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-primary);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .contacts-panel.active {
            transform: translateX(0);
        }
        
        .contacts-header {
            padding: 16px;
            background: var(--block-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .contacts-header h2 {
            font-size: 20px;
            color: var(--text-primary);
            flex: 1;
        }
        
        .back-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .back-btn:hover {
            background: var(--accent-primary);
            color: white;
        }
        
        .contacts-search {
            padding: 12px 16px;
            background: var(--block-bg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .contacts-search input {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 24px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .contacts-search input:focus {
            outline: 2px solid var(--accent-primary);
        }
        
        .contacts-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .contact-item:hover {
            background: var(--block-hover-bg);
        }
        
        .contact-avatar {
            position: relative;
        }
        
        .contact-avatar img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .status-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--bg-primary);
        }
        
        .status-dot.online {
            background: var(--success-color, #22c55e);
        }
        
        .status-dot.offline {
            background: var(--text-secondary);
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .contact-last-message {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .contact-time {
            font-size: 10px;
            color: var(--text-tertiary);
        }
        
        .unread-badge {
            background: var(--accent-primary);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        /* Эмодзи-пикер */
        .emoji-picker {
            position: absolute;
            bottom: 80px;
            right: 16px;
            width: 300px;
            background: var(--block-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            border: 1px solid var(--border-color);
            z-index: 100;
            display: none;
        }
        
        .emoji-picker.active {
            display: block;
        }
        
        .emoji-categories {
            display: flex;
            gap: 4px;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }
        
        .emoji-category {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            white-space: nowrap;
        }
        
        .emoji-category:hover {
            background: var(--bg-tertiary);
        }
        
        .emoji-category.active {
            background: var(--accent-primary);
            color: white;
        }
        
        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .emoji-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        
        .emoji-item:hover {
            background: var(--bg-tertiary);
        }
        
        /* Уведомления */
        .notifications-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .notification {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--block-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            border-left: 4px solid var(--accent-primary);
            animation: slideIn 0.3s ease;
            min-width: 300px;
        }
        
        .notification.success {
            border-left-color: var(--success-color, #22c55e);
        }
        
        .notification.error {
            border-left-color: var(--error-color, #ef4444);
        }
        
        .notification.warning {
            border-left-color: var(--warning-color, #f59e0b);
        }
        
        .notification.info {
            border-left-color: var(--accent-primary);
        }
        
        .notification-icon {
            font-size: 20px;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-primary);
        }
        
        .notification-message {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .notification-close {
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
        }
        
        .notification-close:hover {
            color: var(--text-primary);
        }
        
        /* Уведомления о звонках */
        .incoming-call-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--block-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 10000;
            min-width: 320px;
            border-left: 4px solid #4a90e2;
            animation: slideIn 0.3s ease;
        }
        
        .incoming-call-notification .caller-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .incoming-call-notification .caller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-primary);
        }
        
        .incoming-call-notification .caller-details h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
            font-size: 18px;
        }
        
        .incoming-call-notification .caller-details p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .incoming-call-notification .caller-details i {
            margin-right: 5px;
            color: #4a90e2;
        }
        
        .incoming-call-notification .call-buttons {
            display: flex;
            gap: 10px;
        }
        
        .incoming-call-notification .accept-btn {
            flex: 1;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .incoming-call-notification .accept-btn:hover {
            background: #2ecc71;
            transform: scale(1.02);
        }
        
        .incoming-call-notification .decline-btn {
            flex: 1;
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .incoming-call-notification .decline-btn:hover {
            background: #c0392b;
            transform: scale(1.02);
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Индикатор печатания */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: var(--bg-tertiary);
            border-radius: 12px;
            width: fit-content;
            margin-top: 4px;
        }
        
        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: var(--text-secondary);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-6px); }
        }
        
        /* Адаптация для десктопа */
        @media (min-width: 768px) {
            .app-container {
                flex-direction: row;
            }
            
            .bottom-nav {
                display: none;
            }
            
            .contacts-panel {
                position: static;
                transform: none;
                width: 320px;
                border-right: 1px solid var(--border-color);
                box-shadow: none;
            }
            
            .chat-main {
                flex: 1;
            }
            
            .back-btn {
                display: none;
            }
            
            .contacts-header h2 {
                padding-left: 16px;
            }
        }
        
        /* Пустой чат */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
            padding: 20px;
        }
        
        .empty-chat i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-chat h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .empty-chat p {
            font-size: 14px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Панель контактов -->
        <div class="contacts-panel" id="contactsPanel">
            <div class="contacts-header">
                <button class="back-btn" onclick="hideContacts()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2>Контакты</h2>
                <button class="back-btn" onclick="window.location.href='profile.php'" style="width: auto; padding: 0 15px; border-radius: 20px;">
                    <i class="fas fa-user"></i>
                </button>
            </div>
            <div class="contacts-search">
                <input type="text" id="searchUsers" placeholder="Поиск контактов..." onkeyup="searchContacts(this.value)">
            </div>
            <div class="contacts-list" id="contactsList">
                <!-- Контакты будут загружаться через AJAX -->
            </div>
        </div>
        
        <!-- Основная область чата -->
        <div class="chat-main">
            <!-- Шапка чата -->
            <div class="chat-header" id="chatHeader">
                <div class="chat-user-info" onclick="showUserProfile()">
                    <img src="avatars/default.png" alt="" id="chatAvatar" class="small-avatar">
                    <div>
                        <h3 id="chatUserName">Skype 2025</h3>
                        <span id="chatUserStatus" class="status-text"></span>
                        <div id="typingIndicator" class="typing-indicator" style="display: none;">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
                <div class="chat-actions">
                    <i class="fas fa-phone" onclick="startCall()" title="Аудиозвонок"></i>
                    <i class="fas fa-video" onclick="startVideoCall()" title="Видеозвонок"></i>
                    <i class="fas fa-search" onclick="searchInChat()" title="Поиск в чате"></i>
                    <i class="fas fa-ellipsis-v" onclick="showChatMenu()" title="Меню чата"></i>
                </div>
            </div>
            
            <!-- Сообщения -->
            <div class="messages-container" id="messagesContainer">
                <!-- Приветственное сообщение -->
                <div class="empty-chat" id="emptyChat">
                    <i class="fas fa-comments"></i>
                    <h3>Добро пожаловать в Skype 2025</h3>
                    <p>Выберите контакт для начала общения</p>
                </div>
            </div>
            
            <!-- Поле ввода -->
            <div class="message-input-container">
                <form id="messageForm" onsubmit="sendMessage(event)">
                    <input type="hidden" id="receiverId" name="receiver_id">
                    <input type="hidden" id="messageType" value="private">
                    <button type="button" class="attach-btn" onclick="document.getElementById('fileInput').click();" title="Прикрепить файл">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input type="file" id="fileInput" name="file" style="display: none;" multiple>
                    <button type="button" class="emoji-btn" onclick="toggleEmojiPicker()" title="Эмодзи">
                        <i class="fas fa-smile"></i>
                    </button>
                    <input type="text" id="messageText" placeholder="Напишите сообщение..." autocomplete="off" onkeyup="checkTyping(event)">
                    <button type="submit" class="send-btn" title="Отправить">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                
                <!-- Эмодзи-пикер -->
                <div class="emoji-picker" id="emojiPicker">
                    <div class="emoji-categories">
                        <span class="emoji-category active" onclick="loadEmojiCategory('recent')">Недавние</span>
                        <span class="emoji-category" onclick="loadEmojiCategory('smileys')">Смайлы</span>
                        <span class="emoji-category" onclick="loadEmojiCategory('people')">Люди</span>
                        <span class="emoji-category" onclick="loadEmojiCategory('animals')">Животные</span>
                        <span class="emoji-category" onclick="loadEmojiCategory('food')">Еда</span>
                    </div>
                    <div class="emoji-grid" id="emojiGrid"></div>
                </div>
            </div>
        </div>
        
        <!-- Нижняя навигация для мобильных -->
        <div class="bottom-nav">
            <button class="nav-item active" onclick="switchTab('chats', this)">
                <i class="fas fa-comment"></i>
                <span>Чаты</span>
            </button>
            <button class="nav-item" onclick="switchTab('contacts', this)">
                <i class="fas fa-address-book"></i>
                <span>Контакты</span>
            </button>
            <button class="nav-item" onclick="switchTab('calls', this)">
                <i class="fas fa-phone"></i>
                <span>Звонки</span>
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo min($unread_count, 99); ?></span>
                <?php endif; ?>
            </button>
            <button class="nav-item" onclick="switchTab('profile', this)">
                <i class="fas fa-user"></i>
                <span>Профиль</span>
            </button>
        </div>
    </div>
    
    <!-- Контейнер для уведомлений -->
    <div class="notifications-container" id="notifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Глобальные переменные
        let currentChatId = null;
        let currentChatType = 'private';
        let lastMessageId = 0;
        let currentUser = <?php echo json_encode($current_user); ?>;
        let typingTimeout = null;
        let isTyping = false;
        let lastCallCheck = 0;
        let ringtone = null;
        
        // Эмодзи
        const emojiCategories = {
            recent: JSON.parse(localStorage.getItem('recentEmojis')) || [],
            smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚'],
            people: ['👋', '🤚', '🖐', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '👍', '👎'],
            animals: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐙', '🐵', '🙈', '🙉', '🙊', '🐒'],
            food: ['🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦']
        };
        
        // ===== ФУНКЦИИ ДЛЯ ЧАТА =====
        
        // Переключение вкладок
        function switchTab(tab, element) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');
            
            switch(tab) {
                case 'chats':
                    document.querySelector('.chat-main').style.display = 'flex';
                    document.getElementById('contactsPanel').classList.remove('active');
                    break;
                case 'contacts':
                    if (window.innerWidth < 768) {
                        document.getElementById('contactsPanel').classList.add('active');
                    }
                    loadContacts();
                    break;
                case 'calls':
                    window.location.href = 'calls.php';
                    break;
                case 'profile':
                    window.location.href = 'profile.php';
                    break;
            }
        }
        
        // Скрыть панель контактов
        function hideContacts() {
            document.getElementById('contactsPanel').classList.remove('active');
            document.querySelectorAll('.nav-item').forEach((item, index) => {
                if (index === 0) item.classList.add('active');
                else item.classList.remove('active');
            });
        }
        
        // Загрузка контактов
        function loadContacts() {
            fetch('get_users.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('contactsList').innerHTML = data;
                });
        }
        
        // Поиск контактов
        function searchContacts(query) {
            fetch('get_users.php?search=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('contactsList').innerHTML = data;
                });
        }
        
        // Выбор контакта
        function selectContact(userId, username, avatar, status) {
            currentChatId = userId;
            document.getElementById('receiverId').value = userId;
            document.getElementById('chatUserName').textContent = username;
            document.getElementById('chatAvatar').src = 'avatars/' + avatar;
            document.getElementById('chatUserStatus').textContent = status === 'online' ? 'В сети' : 'Не в сети';
            document.getElementById('chatUserStatus').className = 'status-text ' + status;
            
            document.getElementById('emptyChat').style.display = 'none';
            document.getElementById('messagesContainer').innerHTML = '';
            lastMessageId = 0;
            loadMessages(userId);
            
            if (window.innerWidth < 768) hideContacts();
        }
        
        // Загрузка сообщений
        function loadMessages(userId) {
            fetch('get_messages.php?user_id=' + userId + '&last_id=' + lastMessageId)
                .then(response => response.text())
                .then(data => {
                    if (data.trim()) {
                        document.getElementById('messagesContainer').innerHTML = data;
                        scrollToBottom();
                        const messages = document.querySelectorAll('.message');
                        if (messages.length > 0) {
                            lastMessageId = parseInt(messages[messages.length - 1].getAttribute('data-id') || 0);
                        }
                    }
                });
        }
        
        // Отправка сообщения
        function sendMessage(event) {
            event.preventDefault();
            
            const message = document.getElementById('messageText').value.trim();
            const receiverId = document.getElementById('receiverId').value;
            const fileInput = document.getElementById('fileInput');
            
            if (!message && !fileInput.files.length) return;
            if (!receiverId) {
                showNotification('Выберите контакт для отправки сообщения', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message', message);
            
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('messageText').value = '';
                    fileInput.value = '';
                    loadMessages(receiverId);
                    stopTyping();
                }
            });
        }
        
        // Скролл вниз
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            container.scrollTop = container.scrollHeight;
        }
        
        // Проверка печатания
        function checkTyping(event) {
            if (event.key === 'Enter' && !event.shiftKey) return;
            if (!currentChatId) return;
            
            if (!isTyping) {
                isTyping = true;
                fetch('typing.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'user_id=' + currentChatId + '&typing=true'
                });
            }
            
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(stopTyping, 2000);
        }
        
        function stopTyping() {
            if (isTyping && currentChatId) {
                isTyping = false;
                fetch('typing.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'user_id=' + currentChatId + '&typing=false'
                });
            }
        }
        
        // Эмодзи
        function toggleEmojiPicker() {
            const picker = document.getElementById('emojiPicker');
            picker.classList.toggle('active');
            if (picker.classList.contains('active')) loadEmojiCategory('recent');
        }
        
        function loadEmojiCategory(category) {
            const emojis = emojiCategories[category] || [];
            const grid = document.getElementById('emojiGrid');
            grid.innerHTML = '';
            emojis.forEach(emoji => {
                const div = document.createElement('div');
                div.className = 'emoji-item';
                div.textContent = emoji;
                div.onclick = () => insertEmoji(emoji);
                grid.appendChild(div);
            });
            
            document.querySelectorAll('.emoji-category').forEach(cat => cat.classList.remove('active'));
            event.target.classList.add('active');
        }
        
        function insertEmoji(emoji) {
            document.getElementById('messageText').value += emoji;
            if (!emojiCategories.recent.includes(emoji)) {
                emojiCategories.recent.unshift(emoji);
                if (emojiCategories.recent.length > 20) emojiCategories.recent.pop();
                localStorage.setItem('recentEmojis', JSON.stringify(emojiCategories.recent));
            }
            toggleEmojiPicker();
        }
        
        // ===== ФУНКЦИИ ДЛЯ ЗВОНКОВ =====
        
        // Начать аудиозвонок
        function startCall() {
            if (!currentChatId) {
                showNotification('Выберите контакт для звонка', 'warning');
                return;
            }
            
            fetch('start_call.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'receiver_id=' + currentChatId + '&type=audio'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'call_page.php?call_id=' + data.call_id + '&type=audio';
                } else {
                    showNotification(data.error || 'Ошибка при звонке', 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка соединения', 'error');
            });
        }
        
        // Начать видеозвонок
        function startVideoCall() {
            if (!currentChatId) {
                showNotification('Выберите контакт для видеозвонка', 'warning');
                return;
            }
            
            fetch('start_call.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'receiver_id=' + currentChatId + '&type=video'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'call_page.php?call_id=' + data.call_id + '&type=video';
                } else {
                    showNotification(data.error || 'Ошибка при видеозвонке', 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка соединения', 'error');
            });
        }
        
        // ===== ПРОВЕРКА ВХОДЯЩИХ ЗВОНКОВ =====
        function checkIncomingCalls() {
            // Не проверяем, если мы уже на странице звонка
            if (window.location.pathname.includes('call_page.php')) {
                return;
            }
            
            // Проверяем не чаще чем раз в 3 секунды
            const now = Date.now();
            if (now - lastCallCheck < 3000) return;
            lastCallCheck = now;
            
            fetch('check_calls.php?' + now) // Добавляем timestamp чтобы избежать кэширования
                .then(response => response.json())
                .then(data => {
                    console.log('Проверка звонков:', data); // Для отладки
                    if (data.has_call) {
                        showIncomingCallNotification(data);
                    }
                })
                .catch(error => {
                    console.error('Ошибка проверки звонков:', error);
                });
        }
        
        // Показать уведомление о входящем звонке
        function showIncomingCallNotification(callData) {
            // Проверяем, нет ли уже такого уведомления
            if (document.querySelector(`[data-call-id="${callData.call_id}"]`)) return;
            
            // Воспроизводим звук звонка
            playRingtone();
            
            const notification = document.createElement('div');
            notification.className = 'incoming-call-notification';
            notification.setAttribute('data-call-id', callData.call_id);
            
            notification.innerHTML = `
                <div class="caller-info">
                    <img src="avatars/${callData.caller_avatar}" class="caller-avatar" onerror="this.src='avatars/default.png'">
                    <div class="caller-details">
                        <h4>${callData.caller_name}</h4>
                        <p><i class="fas fa-${callData.type === 'video' ? 'video' : 'phone'}"></i> Входящий ${callData.type === 'video' ? 'видео' : 'аудио'} звонок</p>
                    </div>
                </div>
                <div class="call-buttons">
                    <button class="accept-btn" onclick="acceptCall(${callData.call_id}, '${callData.type}')">
                        <i class="fas fa-phone"></i> Принять
                    </button>
                    <button class="decline-btn" onclick="rejectCall(${callData.call_id}, this)">
                        <i class="fas fa-times"></i> Отклонить
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Автоматически отклоняем через 30 секунд
            setTimeout(() => {
                if (notification.parentNode) {
                    rejectCall(callData.call_id, null);
                    notification.remove();
                }
            }, 30000);
        }
        
        // Принять звонок
        function acceptCall(callId, type) {
            // Останавливаем звук
            stopRingtone();
            
            // Убираем все уведомления
            document.querySelectorAll('.incoming-call-notification').forEach(el => el.remove());
            
            // Отправляем подтверждение
            fetch('answer_call.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'call_id=' + callId + '&action=accept'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'call_page.php?call_id=' + callId + '&type=' + type;
                }
            });
        }
        
        // Отклонить звонок
        function rejectCall(callId, btn) {
            // Останавливаем звук
            stopRingtone();
            
            fetch('answer_call.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'call_id=' + callId + '&action=reject'
            })
            .then(response => response.json())
            .then(() => {
                if (btn) {
                    btn.closest('.incoming-call-notification').remove();
                } else {
                    document.querySelectorAll('.incoming-call-notification').forEach(el => el.remove());
                }
            });
        }
        
        // Звук звонка
        function playRingtone() {
            try {
                // Создаем простой звук через Web Audio API
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                ringtone = audioContext;
                
                function playBeep() {
                    if (!ringtone) return;
                    
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.setValueAtTime(440, audioContext.currentTime); // Нота Ля
                    gainNode.gain.setValueAtTime(0.5, audioContext.currentTime);
                    
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.5);
                    
                    // Повторяем каждую секунду
                    setTimeout(playBeep, 1000);
                }
                
                playBeep();
            } catch(e) {
                console.log('Звук не поддерживается');
            }
        }
        
        function stopRingtone() {
            if (ringtone) {
                ringtone.close();
                ringtone = null;
            }
        }
        
        // ===== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =====
        
        // Показать уведомление
        function showNotification(message, type = 'info') {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const titles = {
                success: 'Успех',
                error: 'Ошибка',
                warning: 'Внимание',
                info: 'Информация'
            };
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-icon"><i class="fas ${icons[type]}"></i></div>
                <div class="notification-content">
                    <div class="notification-title">${titles[type]}</div>
                    <div class="notification-message">${message}</div>
                </div>
                <div class="notification-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></div>
            `;
            
            document.getElementById('notifications').appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Показать профиль пользователя
        function showUserProfile() {
            if (currentChatId) window.location.href = 'profile.php?id=' + currentChatId;
        }
        
        // Поиск в чате
        function searchInChat() {
            if (currentChatId) {
                const query = prompt('Введите текст для поиска:');
                if (query) window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&chat=' + currentChatId;
            } else {
                showNotification('Выберите чат для поиска', 'warning');
            }
        }
        
        // Меню чата
        function showChatMenu() {
            if (!currentChatId) {
                showNotification('Выберите чат', 'warning');
                return;
            }
            
            // Здесь можно добавить кастомное меню
            const actions = ['Информация', 'Очистить', 'Заблокировать', 'Экспорт'];
        }
        
        // Добавление реакции
        function addReaction(messageId, emoji) {
            fetch('add_reaction.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'message_id=' + messageId + '&emoji=' + encodeURIComponent(emoji)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && currentChatId) loadMessages(currentChatId);
            });
        }
        
        // Выход
        function logout() {
            window.location.href = 'logout.php';
        }
        
        // ===== АВТОМАТИЧЕСКИЕ ПРОВЕРКИ =====
        
        // Автообновление сообщений
        setInterval(() => {
            if (currentChatId) {
                fetch('get_messages.php?user_id=' + currentChatId + '&last_id=' + lastMessageId)
                    .then(response => response.text())
                    .then(data => {
                        if (data.trim()) {
                            document.getElementById('messagesContainer').insertAdjacentHTML('beforeend', data);
                            scrollToBottom();
                            const messages = document.querySelectorAll('.message');
                            if (messages.length > 0) {
                                lastMessageId = parseInt(messages[messages.length - 1].getAttribute('data-id') || 0);
                            }
                        }
                    });
            }
        }, 3000);
        
        // Проверка входящих звонков (каждые 3 секунды)
        setInterval(checkIncomingCalls, 3000);
        
        // Загрузка при старте
        document.addEventListener('DOMContentLoaded', function() {
            loadContacts();
            
            // Закрытие эмодзи-пикера при клике вне его
            document.addEventListener('click', function(e) {
                const picker = document.getElementById('emojiPicker');
                const emojiBtn = document.querySelector('.emoji-btn');
                if (!picker.contains(e.target) && !emojiBtn.contains(e.target)) {
                    picker.classList.remove('active');
                }
            });
            
            // Первая проверка звонков
            setTimeout(checkIncomingCalls, 1000);
        });
        
        // Адаптация при изменении размера окна
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                document.getElementById('contactsPanel').classList.remove('active');
            }
        });
    </script>
</body>
</html>