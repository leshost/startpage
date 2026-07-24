<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'] ?? '';
    
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        
        // Optional: redirect back to where they came from
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: $referer");
        exit();
    } else {
        // Bad password
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        // Add error param or flash message in session in real app
        header("Location: $referer?error=1");
        exit();
    }
}

header("Location: /");
exit();
