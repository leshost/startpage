<?php
// Жорсткі налаштування безпеки для сесійних Cookie
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, // 30 днів
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => true,         // Тільки через HTTPS
    'httponly' => true,       // Заборона доступу через JS (захист від XSS)
    'samesite' => 'Lax'       // Захист від міжсайтових атак (CSRF)
]);
session_start();

// CSRF Token — генеруємо один раз за сесію
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Перевіряє CSRF-токен.
 * Підтримує два способи передачі:
 *   1. POST-поле csrf_token (для HTML-форм)
 *   2. HTTP-заголовок X-CSRF-Token (для AJAX fetch)
 */
function verifyCsrf(): void {
    $expected = $_SESSION['csrf_token'] ?? '';
    $received  = $_POST['csrf_token']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? '';

    if (!$expected || !hash_equals($expected, $received)) {
        http_response_code(403);
        // Якщо це AJAX-запит — відповідаємо JSON
        $isAjax = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
               || (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json');
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'CSRF-перевірка не пройдена. Оновіть сторінку.']);
        } else {
            echo '<div style="font-family:sans-serif;padding:2rem;"><h2>403 — CSRF-помилка</h2><p>Недійсний або відсутній токен безпеки. <a href="/">Повернутися</a></p></div>';
        }
        exit;
    }
}

// ── Налаштування реєстрації ────────────────────────────────────────────────────────────
//  true  — відкрита: будь-хто може зареєструватись
//  false — закрита: для реєстрації потрібен дійсний код запрошення (адмін генерує љого в Панелі)
define('OPEN_REGISTRATION', true);

// Конфігурація БД
/* 
 * Для безпеки паролі бази даних винесені у файл .env, який лежить за межами публічної директорії.
 * Створіть файл .env у папці private (на рівень вище папки сайту) з таким вмістом:
 * 
 * DB_HOST="localhost"
 * DB_NAME="ваша_база"
 * DB_USER="ваш_логін"
 * DB_PASS="ваш_пароль"
 */
$envPath = __DIR__ . '/../../private/.env'; 
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    define('DB_HOST', $env['DB_HOST']);
    define('DB_NAME', $env['DB_NAME']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);
} else {
    die("Помилка: Файл .env не знайдено!");
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Критична помилка підключення до бази даних. Спробуйте пізніше.");
}

// Auto-login via Secret URL Parameter (Read-Only Mode)
if (isset($_GET['user']) && !empty($_GET['user'])) {
    $secret = trim($_GET['user']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE secret_key = ? AND is_blocked = 0");
    $stmt->execute([$secret]);
    $userId = $stmt->fetchColumn();
    
    if ($userId) {
        $_SESSION['view_only_user_id'] = $userId;
    }
    
    // Redirect to clear the secret from the URL (prevents Referer leaks)
    header("Location: /");
    exit;
}

// Функція перевірки авторизації
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Багатомовність (i18n)
if (isset($_GET['language'])) {
    $lang = in_array($_GET['language'], ['ua', 'en']) ? $_GET['language'] : 'ua';
    $_SESSION['language'] = $lang;
    setcookie('language', $lang, time() + 60 * 60 * 24 * 30, '/');
    
    // Редирект для очищення URL
    $url = $_SERVER['REQUEST_URI'];
    $url = preg_replace('/([?&])language=[^&]*(&|$)/', '$1', $url);
    $url = rtrim($url, '?&');
    header("Location: $url");
    exit;
}

// Визначення поточної мови
if (isset($_SESSION['language'])) {
    $currentLang = $_SESSION['language'];
} elseif (isset($_COOKIE['language'])) {
    $currentLang = $_COOKIE['language'];
} else {
    $currentLang = 'ua';
}
define('CURRENT_LANG', $currentLang);

// Завантаження масиву перекладів
global $translations;
$translations = require_once __DIR__ . '/../includes/languages.php';
?>
