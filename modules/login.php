<?php
require_once '../config/config.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Введіть логін та пароль.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, secret_key FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['secret_key'] = $user['secret_key'];
            header("Location: ../index.php");
            exit;
        } else {
            $error = 'Невірний логін або пароль.';
        }
    }
}

$pageTitle = 'Вхід';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 400px;">
        <h3 class="text-center mb-4"><i class="bi bi-box-arrow-in-right text-info"></i> Вхід</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label text-light">Логін</label>
                <input type="text" name="username" class="form-control bg-dark text-light border-secondary" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label text-light">Пароль</label>
                <input type="password" name="password" class="form-control bg-dark text-light border-secondary" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Увійти</button>
        </form>
        
        <div class="text-center mt-3">
            <small class="text-secondary">Ще немає акаунта? <a href="register.php" class="text-info text-decoration-none">Зареєструватися</a></small>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
