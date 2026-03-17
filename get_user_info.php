<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $current_user_id = $_SESSION['user_id'];
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 0;
    
    // Получение информации о пользователе
    $stmt = $db->prepare("
        SELECT id, username, email, avatar, bio, status, last_seen, created_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        exit();
    }
    
    // Статистика
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM messages WHERE sender_id = ?) as sent_count,
            (SELECT COUNT(*) FROM messages WHERE receiver_id = ?) as received_count,
            (SELECT COUNT(*) FROM group_messages WHERE sender_id = ?) as group_count
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Общие контакты
    $stmt = $db->prepare("
        SELECT COUNT(*) as common
        FROM contacts c1
        JOIN contacts c2 ON c1.contact_id = c2.contact_id
        WHERE c1.user_id = ? AND c2.user_id = ?
    ");
    $stmt->execute([$current_user_id, $user_id]);
    $common = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="profile-info">
    <div class="profile-header" style="flex-direction: column; text-align: center;">
        <img src="avatars/<?php echo htmlspecialchars($user['avatar']); ?>" 
             alt="Avatar" style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 16px;">
        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
        <p class="status-text <?php echo $user['status']; ?>" style="margin: 8px 0;">
            <?php echo $user['status'] === 'online' ? 'В сети' : 'Был(а) ' . date('H:i d.m', strtotime($user['last_seen'])); ?>
        </p>
        <?php if ($user['bio']): ?>
            <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
            </p>
        <?php endif; ?>
    </div>
    
    <div class="profile-stats" style="margin-top: 24px;">
        <div class="stat-item">
            <div class="stat-value"><?php echo $stats['sent_count'] + $stats['group_count']; ?></div>
            <div class="stat-label">Сообщений</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo $stats['received_count']; ?></div>
            <div class="stat-label">Получено</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo $common['common']; ?></div>
            <div class="stat-label">Общих контактов</div>
        </div>
    </div>
    
    <div style="margin-top: 24px;">
        <p style="color: var(--text-tertiary); font-size: 12px; margin-bottom: 8px;">Email</p>
        <p style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($user['email']); ?></p>
    </div>
    
    <div style="margin-top: 16px;">
        <p style="color: var(--text-tertiary); font-size: 12px; margin-bottom: 8px;">На Skype с</p>
        <p style="color: var(--text-primary); font-size: 14px;"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
    </div>
    
    <div style="display: flex; gap: 8px; margin-top: 24px;">
        <button class="auth-btn" style="flex: 1;" onclick="startCall()">
            <i class="fas fa-phone"></i> Позвонить
        </button>
        <button class="auth-btn" style="flex: 1; background: var(--bg-tertiary); color: var(--text-primary);" 
                onclick="blockUser(<?php echo $user['id']; ?>)">
            <i class="fas fa-ban"></i> Заблокировать
        </button>
    </div>
</div>
<?php
} catch(PDOException $e) {
    echo "Ошибка загрузки информации";
}
?>