<?php
session_start();

// Конфігурація БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'startpage_site');
define('DB_USER', 'startpage_site');
define('DB_PASS', 'JaLoiPeiw_TNhLm0');

// Секретний ключ (пароль адміністратора)
define('ADMIN_PASSWORD', 'hastala8615');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}

// Функція перевірки авторизації
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
?>
