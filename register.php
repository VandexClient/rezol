<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new PDO('sqlite:messenger.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Валидация
        if (empty($username) || empty($email) || empty($password)) {
            $error = "Все поля обязательны для заполнения";
        } elseif ($password !== $confirm_password) {
            $error = "Пароли не совпадают";
        } elseif (strlen($password) < 6) {
            $error = "Пароль должен содержать минимум 6 символов";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Неверный формат email";
        } else {
            // Проверка существования пользователя
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = "Пользователь с таким именем или email уже существует";
            } else {
                // Хеширование пароля
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Загрузка аватара
                $avatar = 'default.png';
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['avatar']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        $avatar = uniqid() . '.' . $ext;
                        move_uploaded_file($_FILES['avatar']['tmp_name'], 'avatars/' . $avatar);
                    }
                }
                
                // Создание пользователя
                $stmt = $db->prepare("INSERT INTO users (username, email, password, avatar) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $avatar]);
                
                $success = "Регистрация успешна! Теперь вы можете войти.";
            }
        }
    } catch(PDOException $e) {
        $error = "Ошибка базы данных: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Skype 2025</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <img src="skype-logo.png" alt="Skype 2025" class="auth-logo">
                <h2>Создать аккаунт Skype 2025</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" class="auth-form">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Имя пользователя" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Пароль" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="Подтвердите пароль" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-image"></i>
                    <input type="file" name="avatar" accept="image/*">
                </div>
                
                <button type="submit" class="auth-btn">Зарегистрироваться</button>
                
                <div class="auth-links">
                    <a href="login.php">Уже есть аккаунт? Войти</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>