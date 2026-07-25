<?php

if (isLoggedIn()) {
    header("Location: /");
    exit;
}

// ── Rate Limiting ─────────────────────────────────────────────────────────────
// Таблиця зберігає невдалі спроби входу. Використовуємо REMOTE_ADDR,
// а не HTTP_X_FORWARDED_FOR, бо той заголовок легко підроблюється.

$pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `ip`           VARCHAR(45)  NOT NULL,
    `attempted_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

const RATE_LIMIT_MAX     = 5;   // максимум невдалих спроб
const RATE_LIMIT_WINDOW  = 15;  // хвилин — вікно відстеження
const RATE_LIMIT_LOCKOUT = 15;  // хвилин — блокування

$clientIp = getUserIP();

// Очищаємо записи старіші за добу (профілактика росту таблиці)
$pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY")->execute();

/**
 * Повертає кількість секунд до кінця блокування, або 0 якщо IP не заблокований.
 */
function getRemainingLockout(PDO $pdo, string $ip): int {
    $stmt = $pdo->prepare("
        SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), MAX(attempted_at) + INTERVAL :mins MINUTE)) AS secs_left
        FROM login_attempts
        WHERE ip = :ip
          AND attempted_at >= NOW() - INTERVAL :win MINUTE
        HAVING COUNT(*) >= :max
    ");
    $stmt->execute([
        ':ip'   => $ip,
        ':mins' => RATE_LIMIT_LOCKOUT,
        ':win'  => RATE_LIMIT_WINDOW,
        ':max'  => RATE_LIMIT_MAX,
    ]);
    $row = $stmt->fetchColumn();
    return $row !== false ? (int)$row : 0;
}

// ─────────────────────────────────────────────────────────────────────────────

$error   = '';
$blocked = false;

// Перевірка блокування при будь-якому запиті (GET або POST)
$secsLeft = getRemainingLockout($pdo, $clientIp);
if ($secsLeft > 0) {
    $blocked  = true;
    $minsLeft = (int)ceil($secsLeft / 60);
    $error    = "Забагато невдалих спроб. Спробуйте через {$minsLeft} хв.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Введіть логін та пароль.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, secret_key, is_admin, is_blocked FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_blocked']) {
                // Заблокований адміном — теж лічимо як невдалу спробу,
                // щоб не розкривати факт існування акаунту через різний відповідь
                $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$clientIp]);
                $error = 'Невірний логін або пароль.';
            } else {
                // Успішний вхід — очищаємо спроби для цього IP
                $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$clientIp]);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['secret_key'] = $user['secret_key'];
                $_SESSION['is_admin']   = (bool)$user['is_admin'];
                header("Location: /");
                exit;
            }
        } else {
            // Невдала спроба — записуємо
            $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$clientIp]);

            // Рахуємо поточну кількість спроб у вікні
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE ip = ? AND attempted_at >= NOW() - INTERVAL ? MINUTE
            ");
            $countStmt->execute([$clientIp, RATE_LIMIT_WINDOW]);
            $attempts  = (int)$countStmt->fetchColumn();
            $remaining = RATE_LIMIT_MAX - $attempts;

            if ($remaining <= 0) {
                $blocked = true;
                $error   = 'Забагато невдалих спроб. Спробуйте через ' . RATE_LIMIT_LOCKOUT . ' хв.';
            } else {
                $error = 'Невірний логін або пароль. ' .
                         "Залишилось спроб: {$remaining} з " . RATE_LIMIT_MAX . '.';
            }
        }
    }
}

$pageTitle = 'Вхід';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 400px;">
        <h3 class="text-center mb-4"><i class="bi bi-box-arrow-in-right text-info"></i> Вхід</h3>

        <?php if ($error): ?>
            <div class="alert <?= $blocked ? 'alert-warning' : 'alert-danger' ?>">
                <?php if ($blocked): ?>
                    <i class="bi bi-shield-lock me-1"></i>
                <?php endif; ?>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="mb-3">
                <label class="form-label text-light">Логін</label>
                <input type="text" name="username" class="form-control bg-dark text-light border-secondary"
                       required <?= $blocked ? 'disabled' : '' ?>
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label text-light">Пароль</label>
                <input type="password" name="password" class="form-control bg-dark text-light border-secondary"
                       required <?= $blocked ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn btn-primary w-100" <?= $blocked ? 'disabled' : '' ?>>
                <?php if ($blocked): ?>
                    <i class="bi bi-lock"></i> Заблоковано
                <?php else: ?>
                    Увійти
                <?php endif; ?>
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-secondary">Ще немає акаунта? <a href="/?module=register" class="text-info text-decoration-none">Зареєструватися</a></small>
        </div>
    </div>
</div>
