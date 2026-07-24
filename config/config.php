<?php
session_start();

// Конфігурація БД
define('DB_HOST', '206.189.0.193');
define('DB_NAME', 'startpage_site');
define('DB_USER', 'startpage_site');
define('DB_PASS', 'JaLoiPeiw_TNhLm0');


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
