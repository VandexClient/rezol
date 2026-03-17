<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'];
    
    // Получение информации о пользователе
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // Обработка сохранения настроек
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $theme = $_POST['theme'] ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $sound = isset($_POST['sound']) ? 1 : 0;
        
        $stmt = $db->prepare("
            UPDATE users 
            SET theme = ?, notifications_enabled = ?, sound_enabled = ? 
            WHERE id = ?
        ");
        $stmt->execute([$theme, $notifications, $sound, $user_id]);
        
        $_SESSION['settings_saved'] = true;
        header("Location: settings.php");
        exit();
    }
    
    // Определение темы
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : ($user['theme'] ?? 'light');
    
} catch(PDOException $e) {
    error_log("Database error in settings.php: " . $e->getMessage());
    die("Ошибка подключения к базе данных.");
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Skype 2025</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Остальной код без изменений -->
</body>
</html>