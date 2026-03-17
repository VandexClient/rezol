<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new PDO('sqlite:messenger.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Обновление статуса
            $stmt = $db->prepare("UPDATE users SET status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Неверное имя пользователя или пароль";
        }
    } catch(PDOException $e) {
        $error = "Ошибка подключения к базе данных";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Skype 2025</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <img src="skype-logo.png" alt="Skype 2025" class="auth-logo">
                <h2>Вход в Skype 2025</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Имя пользователя или Email" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Пароль" required>
                </div>
                
                <button type="submit" class="auth-btn">Войти</button>
                
                <div class="auth-links">
                    <a href="register.php">Создать аккаунт</a>
                    <a href="#">Забыли пароль?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>