<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    $query = isset($_GET['query']) ? $_GET['query'] : '';
    $chat_id = isset($_GET['chat_id']) ? $_GET['chat_id'] : null;
    
    if (empty($query) || strlen($query) < 2) {
        exit();
    }
    
    $searchTerm = "%$query%";
    
    if ($chat_id) {
        // Поиск в конкретном чате
        $stmt = $db->prepare("
            SELECT m.*, 
                   u.username as sender_name,
                   u.avatar as sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.message LIKE ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id, $chat_id, $chat_id, $user_id, $searchTerm]);
    } else {
        // Глобальный поиск
        $stmt = $db->prepare("
            SELECT m.*, 
                   u.username as sender_name,
                   u.avatar as sender_avatar,
                   CASE 
                       WHEN m.sender_id = ? THEN 'sent'
                       ELSE 'received'
                   END as direction
            FROM messages m
            JOIN users u ON 
                CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id = u.id
                    ELSE m.sender_id = u.id
                END
            WHERE (m.sender_id = ? OR m.receiver_id = ?)
                  AND m.message LIKE ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $searchTerm]);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo '<div class="text-center p-4" style="color: var(--text-secondary);">Ничего не найдено</div>';
        exit();
    }
    
    foreach ($results as $result):
        $other_user = $result['sender_id'] == $user_id ? 'Вы' : $result['sender_name'];
        $avatar = $result['sender_id'] == $user_id ? 
                  $_SESSION['avatar'] ?? 'default.png' : 
                  $result['sender_avatar'];
?>
    <div class="search-result-item" onclick="jumpToMessage(<?php echo $result['id']; ?>)">
        <img src="avatars/<?php echo htmlspecialchars($avatar); ?>" alt="" class="search-result-avatar">
        <div class="search-result-info">
            <div class="search-result-name"><?php echo htmlspecialchars($other_user); ?></div>
            <div class="search-result-preview">
                <span><?php echo htmlspecialchars(substr($result['message'], 0, 50)) . '...'; ?></span>
                <span class="search-result-time"><?php echo date('H:i d.m', strtotime($result['created_at'])); ?></span>
            </div>
        </div>
    </div>
<?php 
    endforeach;
    
} catch(PDOException $e) {
    echo "Ошибка поиска";
}
?>