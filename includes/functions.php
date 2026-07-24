<?php

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    else return $_SERVER['REMOTE_ADDR'];
}

function getClientInfo() {
    $u_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bname = 'Unknown Browser';
    $platform = 'Unknown OS';
    $ub = 'Unknown';
    $version = "";

    // Detect Platform/OS
    if (preg_match('/linux/i', $u_agent)) $platform = 'Linux';
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) $platform = 'Mac';
    elseif (preg_match('/windows|win32/i', $u_agent)) $platform = 'Windows';
    elseif (preg_match('/android/i', $u_agent)) $platform = 'Android';
    elseif (preg_match('/iphone/i', $u_agent)) $platform = 'iPhone';
    elseif (preg_match('/ipad/i', $u_agent)) $platform = 'iPad';
    
    if (preg_match('/windows nt 10/i', $u_agent)) $platform = 'Windows 10/11';
    elseif (preg_match('/windows nt 6.3/i', $u_agent)) $platform = 'Windows 8.1';
    elseif (preg_match('/windows nt 6.2/i', $u_agent)) $platform = 'Windows 8';
    elseif (preg_match('/windows nt 6.1/i', $u_agent)) $platform = 'Windows 7';

    if (preg_match('/mac os x ([0-9_]+)/i', $u_agent, $m)) $platform = 'macOS ' . str_replace('_', '.', $m[1]);
    if (preg_match('/android ([0-9\.]+)/i', $u_agent, $m)) $platform = 'Android ' . $m[1];
    if (preg_match('/os ([0-9_]+) like mac os x/i', $u_agent, $m)) $platform .= ' iOS ' . str_replace('_', '.', $m[1]);

    // Detect Browser
    if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) { $bname = 'Internet Explorer'; $ub = "MSIE"; }
    elseif (preg_match('/Trident/i', $u_agent)) { $bname = 'Internet Explorer'; $ub = "rv"; }
    elseif (preg_match('/Firefox/i', $u_agent)) { $bname = 'Mozilla Firefox'; $ub = "Firefox"; }
    elseif (preg_match('/OPR/i', $u_agent) || preg_match('/Opera/i', $u_agent)) { $bname = 'Opera'; $ub = "OPR"; }
    elseif (preg_match('/Edg/i', $u_agent)) { $bname = 'Microsoft Edge'; $ub = "Edg"; }
    elseif (preg_match('/Chrome/i', $u_agent)) { $bname = 'Google Chrome'; $ub = "Chrome"; }
    elseif (preg_match('/Safari/i', $u_agent)) { $bname = 'Apple Safari'; $ub = "Safari"; }

    // Detect Version
    $pattern = '#(?<browser>' . $ub . '|Version)[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    preg_match_all($pattern, $u_agent, $matches);
    if (count($matches['browser']) != 1) {
        if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) $version = $matches['version'][0] ?? '';
        else $version = $matches['version'][1] ?? '';
    } else {
        $version = $matches['version'][0] ?? '';
    }
    
    if (!$version) $version = "?";
    
    // Architecture
    $arch = "";
    if (preg_match('/x86_64|Win64|WOW64|x64/i', $u_agent)) $arch = ' (64-bit)';
    elseif (preg_match('/i686|i386|Win32/i', $u_agent)) $arch = ' (32-bit)';

    return [
        'browser' => "$bname (v$version)",
        'os' => "$platform$arch",
        'raw' => $u_agent
    ];
}
