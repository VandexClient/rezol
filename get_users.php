<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $current_user_id = $_SESSION['user_id'];
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    if ($search) {
        // Поиск пользователей
        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE id != ? AND (username LIKE ? OR email LIKE ?)
            ORDER BY username
            LIMIT 20
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$current_user_id, $searchTerm, $searchTerm]);
    } else {
        // Получение контактов пользователя
        $stmt = $db->prepare("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM messages 
                    WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
                   (SELECT message FROM messages 
                    WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
                    ORDER BY created_at DESC LIMIT 1) as last_message
            FROM users u
            WHERE u.id != ?
            ORDER BY u.status = 'online' DESC, u.username
        ");
        $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user):
        $status_class = $user['status'];
        $status_text = $user['status'];
        $unread = $user['unread_count'] ?? 0;
?>
    <div class="contact-item" onclick="selectContact(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['avatar']; ?>', '<?php echo $status_text; ?>')">
        <div class="contact-avatar">
            <img src="avatars/<?php echo $user['avatar']; ?>" alt="Avatar">
            <span class="status-dot <?php echo $status_class; ?>"></span>
        </div>
        <div class="contact-info">
            <div class="contact-header">
                <span class="contact-name"><?php echo htmlspecialchars($user['username']); ?></span>
                <?php if ($unread > 0): ?>
                    <span class="unread-badge"><?php echo $unread; ?></span>
                <?php endif; ?>
            </div>
            <div class="contact-last-message">
                <?php 
                if (isset($user['last_message'])) {
                    echo htmlspecialchars(substr($user['last_message'], 0, 30)) . '...';
                }
                ?>
            </div>
            <div class="contact-time">
                <?php 
                if ($user['status'] == 'online') {
                    echo 'В сети';
                } else {
                    echo 'Был(а) недавно';
                }
                ?>
            </div>
        </div>
    </div>
<?php 
    endforeach;
    
} catch(PDOException $e) {
    echo "Ошибка загрузки контактов";
}
?>