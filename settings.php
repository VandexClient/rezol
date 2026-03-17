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
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : $user['theme'];
    
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Skype 2025</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h2>Настройки</h2>
        </div>
        
        <?php if (isset($_SESSION['settings_saved'])): ?>
            <div class="success-message" style="margin: 16px;">
                Настройки успешно сохранены
            </div>
            <?php unset($_SESSION['settings_saved']); ?>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="settings-tabs">
                <span class="settings-tab active" onclick="switchSettingsTab('general')">Основные</span>
                <span class="settings-tab" onclick="switchSettingsTab('notifications')">Уведомления</span>
                <span class="settings-tab" onclick="switchSettingsTab('privacy')">Приватность</span>
                <span class="settings-tab" onclick="switchSettingsTab('appearance')">Внешний вид</span>
            </div>
            
            <div class="settings-content">
                <!-- Основные настройки -->
                <div id="general-settings" class="settings-section">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Имя пользователя</h3>
                            <p><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                        <button type="button" class="auth-btn" style="width: auto;" onclick="changeUsername()">
                            Изменить
                        </button>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Email</h3>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <button type="button" class="auth-btn" style="width: auto;" onclick="changeEmail()">
                            Изменить
                        </button>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Пароль</h3>
                            <p>●●●●●●●●</p>
                        </div>
                        <button type="button" class="auth-btn" style="width: auto;" onclick="changePassword()">
                            Изменить
                        </button>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>О себе</h3>
                            <p><?php echo htmlspecialchars($user['bio'] ?: 'Не указано'); ?></p>
                        </div>
                        <button type="button" class="auth-btn" style="width: auto;" onclick="changeBio()">
                            Редактировать
                        </button>
                    </div>
                </div>
                
                <!-- Уведомления -->
                <div id="notifications-settings" class="settings-section" style="display: none;">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Уведомления</h3>
                            <p>Показывать уведомления о новых сообщениях</p>
                        </div>
                        <label class="theme-toggle">
                            <input type="checkbox" name="notifications" 
                                   <?php echo $user['notifications_enabled'] ? 'checked' : ''; ?>>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Звук</h3>
                            <p>Воспроизводить звук при новых сообщениях</p>
                        </div>
                        <label class="theme-toggle">
                            <input type="checkbox" name="sound" 
                                   <?php echo $user['sound_enabled'] ? 'checked' : ''; ?>>
                        </label>
                    </div>
                </div>
                
                <!-- Приватность -->
                <div id="privacy-settings" class="settings-section" style="display: none;">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Блокировка контактов</h3>
                            <p>Управление заблокированными пользователями</p>
                        </div>
                        <button type="button" class="auth-btn" style="width: auto;" onclick="manageBlocked()">
                            Управлять
                        </button>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Статус в сети</h3>
                            <p>Кто может видеть ваш статус</p>
                        </div>
                        <select name="privacy_status" class="create-group-input" style="width: 200px;">
                            <option value="all">Все</option>
                            <option value="contacts">Только контакты</option>
                            <option value="none">Никто</option>
                        </select>
                    </div>
                </div>
                
                <!-- Внешний вид -->
                <div id="appearance-settings" class="settings-section" style="display: none;">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Тема</h3>
                            <p>Выберите оформление</p>
                        </div>
                        <div class="theme-toggle">
                            <button type="button" onclick="setTheme('light')" 
                                    class="<?php echo $theme === 'light' ? 'active' : ''; ?>">
                                <i class="fas fa-sun"></i>
                            </button>
                            <button type="button" onclick="setTheme('dark')" 
                                    class="<?php echo $theme === 'dark' ? 'active' : ''; ?>">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>
                        <input type="hidden" name="theme" id="themeInput" value="<?php echo $theme; ?>">
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Размер шрифта</h3>
                            <p>Настройте размер текста</p>
                        </div>
                        <select name="font_size" class="create-group-input" style="width: 150px;">
                            <option value="small">Маленький</option>
                            <option value="medium" selected>Средний</option>
                            <option value="large">Большой</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div style="padding: 24px; border-top: 1px solid var(--border-color);">
                <button type="submit" class="save-btn">Сохранить настройки</button>
            </div>
        </form>
    </div>
    
    <script>
        function switchSettingsTab(tab) {
            $('.settings-section').hide();
            $(`#${tab}-settings`).show();
            
            $('.settings-tab').removeClass('active');
            $(`.settings-tab[onclick*="${tab}"]`).addClass('active');
        }
        
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.cookie = `theme=${theme}; path=/; max-age=31536000`;
            $('#themeInput').val(theme);
            
            $('.theme-toggle button').removeClass('active');
            $(`.theme-toggle button[onclick*="${theme}"]`).addClass('active');
        }
        
        function changeUsername() {
            // Реализация смены имени
        }
        
        function changeEmail() {
            // Реализация смены email
        }
        
        function changePassword() {
            // Реализация смены пароля
        }
        
        function changeBio() {
            // Реализация смены био
        }
        
        function manageBlocked() {
            // Реализация управления блокировками
        }
    </script>
</body>
</html>