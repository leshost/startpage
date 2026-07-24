<?php
// Конфігурація
$host = 'localhost';
$dbname = 'startpage_site';
$username = 'startpage_site';
$password = 'JaLoiPeiw_TNhLm0';
$secret_key = 'hastala8615';
$user = '';

if(isset($_GET['user'])) {
    if($_GET['user'] == 'andjey') {
        $user = " OR `user` = '1'";
    }

    if($_GET['user'] == 'a10') {
        $user = " OR `user` = '2'";
    }
}    

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}

// Видалення сайту
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_id']) && isset($_GET['key']) && $_GET['key'] == $secret_key) {
    $stmt = $pdo->prepare("DELETE FROM sites WHERE id = :id");
    $stmt->execute(['id' => $_POST['delete_id']]);
    header("Location: index.php?key=" . $secret_key);
    exit();
}

// Додавання сайту
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['key']) && $_GET['key'] == $secret_key && isset($_POST['name'], $_POST['url'], $_POST['icon'])) {
    $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon) VALUES (:name, :url, :icon)");
    $stmt->execute([
        'name' => $_POST['name'],
        'url' => $_POST['url'],
        'icon' => $_POST['icon']
    ]);
    header("Location: index.php?key=" . $secret_key);
    exit();
}

// Отримання сайтів
$query = "SELECT `id`, `name`, `url`, `icon` FROM `sites` WHERE `user` IS NULL ".$user." ORDER BY `order`";
$stmt = $pdo->query($query);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Отримати IP-адресу
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$ip = getUserIP();
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// Отримати геолокацію з ip-api.com
$apiUrl = "http://ip-api.com/json/$ip?fields=status,message,country,regionName,city,zip,lat,lon,isp,org,as,query";
$response = @file_get_contents($apiUrl);
$data = $response ? json_decode($response, true) : null;
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Стартова сторінка</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('bg.avif') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff;
        }
        .overlay {
            background-color: rgba(18, 18, 18, 0.25);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .link-box {
            position: relative;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #1e1e1e;
            border-radius: 10px;
            transition: 0.3s;
            margin: 10px auto;
        }
        .link-box:hover {
            background-color: #292929;
        }
        .link-box img {
            width: 50px;
            height: 50px;
        }
        .site-name {
            margin-top: 5px;
            text-align: center;
            font-size: 14px;
            color: #ffffff;
        }
        .delete-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 24px;
            height: 24px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 16px;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
        }
        .delete-btn:hover {
            background: darkred;
        }
        .home-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            font-size: 24px;
            text-decoration: none;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 50%;
            transition: 0.3s;
        }
        .home-btn2 {
            position: absolute;
            top: 15px;
            left: 65px;
            font-size: 24px;
            text-decoration: none;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 50%;
            transition: 0.3s;
        }
        .home-btn3 {
            position: absolute;
            top: 15px;
            left: 120px;
            font-size: 24px;
            text-decoration: none;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 50%;
            transition: 0.3s;
        }
        .home-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .home-btn2:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .home-btn3:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>

<div class="overlay"></div>

<!-- Кнопка "🏠" на домашню сторінку -->
<a href="/" class="home-btn">🏠</a>&nbsp;
<a href="/finance.php" class="home-btn2">💰</a>
<a href="/pass.php" class="home-btn3">🔐</a>

<!-- Кнопка "Шестерня" + поле "Пароль" -->
<div class="position-absolute top-0 end-0 m-3">
    <button id="settings-btn" class="btn btn-dark">⚙️</button>
    <form id="password-form" action="" method="GET" class="d-none mt-2">
        <input type="password" name="key" class="form-control" placeholder="Пароль">
    </form>
</div>

<div class="container d-flex flex-column justify-content-center align-items-center vh-100 content">

    <form action="https://www.google.com/search" method="GET" class="mb-4 w-50 d-none">
        <div class="input-group">
            <input type="text" name="q" class="form-control" autofocus placeholder="Пошук у Google">
            <button type="submit" class="btn btn-primary">🔍</button>
        </div>
    </form>

    <h1 class="mb-0 d-none_"><?php echo $ip; ?></h1>
    <p class="mb-4"><?php echo $data['country'].', '.$data['city'].' | <b>'.$data['isp'].'</b><br>'.$userAgent; ?></p>

    <div class="container-lg">
        <div class="row justify-content-center gap-4">
            <?php foreach ($sites as $site): ?>
                <div class="col-lg-1 col-md-3 col-sm-2 col-3 d-flex flex-column align-items-center position-relative">
                    <a href="<?= htmlspecialchars($site['url']) ?>" class="d-block text-decoration-none text-light">
                        <div class="link-box">
                            <?php if (isset($_GET['key']) && $_GET['key'] == $secret_key): ?>
                                <form method="POST" class="position-absolute" style="top: 0; right: 0;">
                                    <input type="hidden" name="delete_id" value="<?= $site['id'] ?>">
                                    <button type="submit" class="delete-btn">✖</button>
                                </form>
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars($site['icon']) ?>" alt="<?= htmlspecialchars($site['name']) ?>">
                        </div>
                        <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Панель адміністратора -->
    <?php if (isset($_GET['key']) && $_GET['key'] == $secret_key): ?>
        <div class="mt-5 p-4 bg-dark rounded w-50">
            <h3>Додати сайт</h3>
            <form method="POST">
                <div class="mb-2">
                    <input type="text" name="name" class="form-control" placeholder="Назва" required>
                </div>
                <div class="mb-2">
                    <input type="url" name="url" class="form-control" placeholder="URL" required>
                </div>
                <div class="mb-2">
                    <input type="text" name="icon" class="form-control" placeholder="URL іконки" required>
                </div>
                <button type="submit" class="btn btn-success">Додати сайт</button>
            </form>
        </div>
    <?php endif; ?>


</div>


<script>
    document.getElementById('settings-btn').addEventListener('click', function() {
        this.classList.add('d-none');
        document.getElementById('password-form').classList.remove('d-none');
    });
</script>

</body>
</html>
