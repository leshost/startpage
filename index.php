<?php
// Конфігурація бази даних
$host = 'localhost'; // Сервер БД
$dbname = 'startpage_db'; // Назва бази
$username = 'startpage_user'; // Логін
$password = 'PASSWORD'; // Пароль (змініть, якщо потрібно)

$secret_key = 'SECRET_KEY';

// Підключення до MySQL
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}

// Додавання нового сайту
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['key']) && $_GET['key'] == $secret_key) {
    $name = $_POST['name'] ?? '';
    $url = $_POST['url'] ?? '';
    $icon = $_POST['icon'] ?? '';

    if (!empty($name) && !empty($url) && !empty($icon)) {
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon) VALUES (:name, :url, :icon)");
        $stmt->execute(['name' => $name, 'url' => $url, 'icon' => $icon]);
        $message = "Сайт '$name' успішно додано!";
    } else {
        $message = "Будь ласка, заповніть всі поля!";
    }
}

// Отримання сайтів
$query = "SELECT name, url, icon FROM sites ORDER BY `order`";
$stmt = $pdo->query($query);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        .search-box {
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .search-input {
            height: 50px;
            font-size: 18px;
            background-color: #1e1e1e;
            border: 1px solid #444;
            color: #fff;
            padding: 10px;
        }

        .search-input::placeholder {
            color: #bbb;
        }

        .search-button {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
            font-size: 18px;
        }

        .search-button:hover {
            background-color: #0056b3;
            border-color: #004b9a;
        }

        .admin-panel {
            background: #222;
            padding: 20px;
            border-radius: 10px;
            margin-top: 40px;
            width: 400px;
            text-align: left;
        }

        .admin-panel input {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>

    <div class="overlay"></div>

    <div class="container d-flex flex-column justify-content-center align-items-center vh-100 content">

        <form action="https://www.google.com/search" method="GET" class="search-box w-100">
            <div class="input-group">
                <input type="text" name="q" class="form-control search-input" placeholder="Пошук у Google">
                <button type="submit" class="btn search-button">🔍</button>
            </div>
        </form>

        <h1 class="mb-4">&nbsp;</h1>

        <div class="container">
            <div class="row justify-content-center gap-3">
                <?php foreach ($sites as $site): ?>
                    <div class="col-lg-1 col-md-2 col-sm-3 col-4 d-flex flex-column align-items-center">
                        <a href="<?= htmlspecialchars($site['url']) ?>" class="d-block text-decoration-none text-light">
                            <div class="link-box">
                                <img src="<?= htmlspecialchars($site['icon']) ?>" alt="<?= htmlspecialchars($site['name']) ?>">
                            </div>
                            <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (isset($_GET['key']) && $_GET['key'] == $secret_key): ?>
            <div class="admin-panel">
                <h3>Панель адміністратора</h3>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-info"><?= $message ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-2">
                        <label class="form-label">Назва сайту</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">URL сайту</label>
                        <input type="url" name="url" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">URL іконки</label>
                        <input type="url" name="icon" class="form-control" required value="favicon.ico">
                    </div>
                    <button type="submit" class="btn btn-success">Додати сайт</button>
                </form>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>