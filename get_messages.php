<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $current_user_id = $_SESSION['user_id'];
    $other_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 0;
    $last_id = isset($_GET['last_id']) ? $_GET['last_id'] : 0;
    
    // Получение сообщений
    $stmt = $db->prepare("
        SELECT m.*, 
               u_sender.username as sender_name,
               u_sender.avatar as sender_avatar
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id, $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Отметка о прочтении
    $stmt = $db->prepare("
        UPDATE messages SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$other_user_id, $current_user_id]);
    
    foreach ($messages as $message):
        $is_own = $message['sender_id'] == $current_user_id;
        $message_class = $is_own ? 'own' : 'other';
?>
    <div class="message <?php echo $message_class; ?>" data-id="<?php echo $message['id']; ?>">
        <?php if (!$is_own): ?>
            <img src="avatars/<?php echo $message['sender_avatar']; ?>" alt="" class="message-avatar">
        <?php endif; ?>
        
        <div class="message-content">
            <?php if (!$is_own): ?>
                <div class="message-sender"><?php echo htmlspecialchars($message['sender_name']); ?></div>
            <?php endif; ?>
            
            <?php if ($message['message']): ?>
                <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
            <?php endif; ?>
            
            <?php if ($message['file_path']): ?>
                <div class="message-file">
                    <i class="fas fa-file"></i>
                    <a href="uploads/<?php echo $message['file_path']; ?>" target="_blank">
                        <?php echo htmlspecialchars($message['file_name']); ?>
                    </a>
                    <span class="file-size">(<?php echo round($message['file_size'] / 1024, 2); ?> KB)</span>
                </div>
            <?php endif; ?>
            
            <div class="message-time">
                <?php echo date('H:i', strtotime($message['created_at'])); ?>
                <?php if ($is_own): ?>
                    <?php if ($message['is_read']): ?>
                        <i class="fas fa-check-double read"></i>
                    <?php else: ?>
                        <i class="fas fa-check"></i>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php 
    endforeach;
    
} catch(PDOException $e) {
    // Логирование ошибки
}
?>