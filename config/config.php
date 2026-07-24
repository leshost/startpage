<?php
session_start();

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
    die("Помилка підключення до БД: " . $e->getMessage());
}

// Auto-login via Secret URL Parameter
if (isset($_GET['user']) && !empty($_GET['user'])) {
    $secret = trim($_GET['user']);
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE secret_key = ?");
    $stmt->execute([$secret]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['secret_key'] = $secret;
    }
}

// Функція перевірки авторизації
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
?>
