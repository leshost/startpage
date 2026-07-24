<?php

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';

    if ($password === '') {
        $result = [
            'status' => 'error',
            'message' => 'Введіть пароль.'
        ];
    } else {

        // SHA1 пароля
        $hash = strtoupper(sha1($password));

        // Prefix і Suffix
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        $url = "https://api.pwnedpasswords.com/range/" . $prefix;

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' =>
                    "User-Agent: PasswordChecker\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {

            $result = [
                'status' => 'error',
                'message' => 'Не вдалося підключитися до сервера HIBP.'
            ];

        } else {

            $found = false;
            $count = 0;

            foreach (explode("\n", $response) as $line) {

                $line = trim($line);

                if (!$line)
                    continue;

                list($remoteSuffix, $remoteCount) = explode(':', $line);

                if (strtoupper($remoteSuffix) === $suffix) {
                    $found = true;
                    $count = (int)$remoteCount;
                    break;
                }
            }

            $result = [
                'status' => 'ok',
                'hash' => $hash,
                'prefix' => $prefix,
                'suffix' => $suffix,
                'found' => $found,
                'count' => $count
            ];
        }
    }
}
?>
<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<title>Перевірка пароля через HIBP</title>

<style>

body{
    font-family:Arial;
    margin:40px;
    background:#f5f5f5;
}

.container{
    background:white;
    padding:25px;
    border-radius:8px;
    max-width:700px;
    margin:auto;
}

input[type=password]{
    width:100%;
    padding:12px;
    font-size:16px;
}

button{
    margin-top:15px;
    padding:10px 20px;
    font-size:16px;
}

.good{
    color:green;
    font-weight:bold;
}

.bad{
    color:red;
    font-weight:bold;
}

.error{
    color:#b00000;
}

pre{
    background:#eee;
    padding:10px;
    overflow:auto;
}

</style>

</head>

<body>

<div class="container">

<h2><a href="">Перевірка пароля через Have I Been Pwned</a></h2>

<form method="post">

<input
    type="text"
    name="password"
    autocomplete="off"
    placeholder="Введіть пароль"
    value="<?php echo (isset($_POST['password']))?($_POST['password']):(''); ?>"
>
<br>

<button>Перевірити</button>

</form>

<?php if($result): ?>

<hr>

<?php if($result['status']=='error'): ?>

<div class="error">
<?=htmlspecialchars($result['message'])?>
</div>

<?php else: ?>

<p><b>SHA1:</b></p>

<pre><?=htmlspecialchars($result['hash'])?></pre>

<p>
<b>Передано на сервер тільки:</b>
<?=htmlspecialchars($result['prefix'])?>
</p>

<?php if($result['found']): ?>

<p class="bad">
❌ Пароль знайдений у базі витоків.
</p>

<p>
Він зустрічався
<b><?=number_format($result['count'],0,'.',' ')?></b>
разів.
</p>

<?php else: ?>

<p class="good">
✅ Пароль відсутній у базі витоків HIBP.
</p>

<?php endif; ?>

<?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>