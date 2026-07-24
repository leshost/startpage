<?php
require_once 'config.php';
session_destroy();
$referer = $_SERVER['HTTP_REFERER'] ?? '/';
header("Location: $referer");
exit();
