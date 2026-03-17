// dark-mode.js - Управление темной темой

// Переключение темы
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
    
    // Обновление иконок
    updateThemeIcons(newTheme);
    
    // Сохранение темы в localStorage
    localStorage.setItem('theme', newTheme);
    
    // Анимация переключения
    animateThemeSwitch();
}

// Обновление иконок темы
function updateThemeIcons(theme) {
    const icons = document.querySelectorAll('[data-theme-icon]');
    icons.forEach(icon => {
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

// Анимация переключения темы
function animateThemeSwitch() {
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.3)';
    overlay.style.zIndex = '9999';
    overlay.style.pointerEvents = 'none';
    overlay.style.animation = 'fadeOut 0.5s ease';
    
    document.body.appendChild(overlay);
    
    setTimeout(() => {
        overlay.remove();
    }, 500);
}

// Загрузка темы при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Проверка сохраненной темы
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcons(savedTheme);
    }
    
    // Проверка системной темы
    if (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
        updateThemeIcons('dark');
    }
    
    // Слушатель изменения системной темы
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            const theme = e.matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
            updateThemeIcons(theme);
        }
    });
});

// Добавление стилей для анимации
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .theme-transition {
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }
    
    * {
        transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }
`;
document.head.appendChild(style);