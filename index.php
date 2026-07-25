<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Базовий Content Security Policy (CSP)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: blob: https: http:; font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self' https://api.pwnedpasswords.com https://cloudflareinsights.com; frame-src 'self'; object-src 'none';");

// Визначаємо поточний модуль
$module = $_GET['module'] ?? 'sites';
// Захист від directory traversal
$module = preg_replace('/[^a-zA-Z0-9_-]/', '', $module);
$moduleFile = 'modules/' . $module . '.php';

if (!file_exists($moduleFile)) {
    $module = 'sites';
    $moduleFile = 'modules/sites.php';
}

// Починаємо буферизацію, щоб модуль міг задати $pageTitle та інші змінні перед виводом header
ob_start();
require_once $moduleFile;
$moduleContent = ob_get_clean();

// Підключаємо загальну шапку сайту (вона може використовувати змінні типу $pageTitle з модуля)
require_once 'includes/header.php';

// Підключаємо навігацію
require_once 'includes/navbar.php';

// Виводимо вміст поточного модуля
echo $moduleContent;

// Підключаємо загальний підвал сайту
require_once 'includes/footer.php';
