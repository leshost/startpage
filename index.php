<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

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
