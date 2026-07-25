<?php

if (isLoggedIn()) {
    header("Location: /");
    exit;
}

$error   = '';
$success = '';

// ── Таблиця invite_codes (тільки якщо реєстрація закрита) ─────────────────────
if (!OPEN_REGISTRATION) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `invite_codes` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `code`       VARCHAR(32) NOT NULL UNIQUE,
        `created_by` INT NOT NULL,
        `used_by`    INT NULL DEFAULT NULL,
        `used_at`    TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (`code`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// ── Стандартні міграції таблиці users ─────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `secret_key` VARCHAR(64) NOT NULL UNIQUE,
            `is_admin` BOOLEAN DEFAULT FALSE,
            `is_blocked` BOOLEAN DEFAULT FALSE,
            `public_key` TEXT NULL,
            `is_key_saved` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_admin` BOOLEAN DEFAULT FALSE");
} catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_blocked` BOOLEAN DEFAULT FALSE"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `public_key` TEXT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_key_saved` BOOLEAN DEFAULT FALSE"); } catch (PDOException $e) {}
// ── Обробка форми ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username         = trim($_POST['username'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $invite_code      = trim($_POST['invite_code'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Всі поля обов\'язкові для заповнення.';
    } elseif (strlen($username) < 3) {
        $error = 'Логін повинен містити не менше 3 символів.';
    } elseif ($password !== $password_confirm) {
        $error = 'Паролі не співпадають.';
    } elseif (strlen($password) < 8) {
        $error = 'Пароль повинен містити не менше 8 символів.';
    } else {

        // ── Перевірка коду запрошення ──────────────────────────────────────────
        $inviteRow = null;
        if (!OPEN_REGISTRATION) {
            if (empty($invite_code)) {
                $error = 'Введіть код запрошення.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM invite_codes WHERE code = ? AND used_by IS NULL");
                $stmt->execute([$invite_code]);
                $inviteRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$inviteRow) {
                    $error = 'Невірний або вже використаний код запрошення.';
                }
            }
        }

        if (!$error) {
            // Перевірка на унікальність username
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Користувач з таким логіном вже існує.';
            } else {
                // Реєстрація
                $hash       = password_hash($password, PASSWORD_DEFAULT);
                $secret_key = bin2hex(random_bytes(16));
                $stmt       = $pdo->prepare("INSERT INTO users (username, password_hash, secret_key) VALUES (?, ?, ?)");
                if ($stmt->execute([$username, $hash, $secret_key])) {
                    $newUserId = (int)$pdo->lastInsertId();

                    // Позначаємо код як використаний
                    if (!OPEN_REGISTRATION && $inviteRow) {
                        $pdo->prepare("UPDATE invite_codes SET used_by = ?, used_at = NOW() WHERE id = ?")
                            ->execute([$newUserId, $inviteRow['id']]);
                    }

                    $success = 'Реєстрація успішна! Тепер ви можете <a href="/?module=login" class="alert-link">увійти</a>.';
                } else {
                    $error = 'Помилка бази даних під час реєстрації.';
                }
            }
        }
    }
}

$pageTitle = 'Реєстрація';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 400px;">
        <h3 class="text-center mb-4"><i class="bi bi-person-plus text-info"></i> Реєстрація</h3>

        <?php if (!OPEN_REGISTRATION): ?>
            <div class="alert alert-secondary border-secondary py-2 mb-3 small">
                <i class="bi bi-shield-lock me-1 text-warning"></i>
                Реєстрація доступна лише за запрошенням. Зверніться до адміністратора.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="mb-3">
                    <label class="form-label text-light">Логін</label>
                    <input type="text" name="username" class="form-control bg-dark text-light border-secondary" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label text-light">Пароль</label>
                    <input type="password" name="password" id="regPassword" class="form-control bg-dark text-light border-secondary" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-light">Підтвердження пароля</label>
                    <input type="password" name="password_confirm" id="regPasswordConfirm" class="form-control bg-dark text-light border-secondary" required>
                    <div id="pwdStatus" class="form-text mt-1"></div>
                </div>

                <?php if (!OPEN_REGISTRATION): ?>
                <div class="mb-4">
                    <label class="form-label text-light">
                        <i class="bi bi-ticket-perforated text-warning me-1"></i>Код запрошення
                    </label>
                    <input type="text" name="invite_code" class="form-control bg-dark text-light border-secondary font-monospace"
                           placeholder="xxxx-xxxx-xxxx-xxxx" required
                           value="<?= htmlspecialchars($_POST['invite_code'] ?? '') ?>">
                </div>
                <?php endif; ?>

                <button type="submit" id="regSubmitBtn" class="btn btn-primary w-100">Зареєструватися</button>
            </form>
            <div class="text-center mt-3">
                <small class="text-secondary">Вже є акаунт? <a href="/?module=login" class="text-info text-decoration-none">Увійти</a></small>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// SHA-1 Helper Function using Web Crypto API
async function sha1(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
}

let timeoutId;
const passwordInput = document.getElementById('regPassword');
const confirmInput = document.getElementById('regPasswordConfirm');
const submitBtn = document.getElementById('regSubmitBtn');
const pwdStatus = document.getElementById('pwdStatus');

function validatePassword() {
    clearTimeout(timeoutId);
    const password = passwordInput.value;
    const confirm = confirmInput.value;
    
    if (!password || !confirm) {
        pwdStatus.innerHTML = '';
        submitBtn.disabled = true;
        return;
    }

    if (password !== confirm) {
        pwdStatus.innerHTML = '<span class="text-danger">Паролі не співпадають</span>';
        submitBtn.disabled = true;
        return;
    }

    if (password.length < 8) {
        pwdStatus.innerHTML = '<span class="text-secondary">Пароль занадто короткий (мінімум 8 символів)</span>';
        submitBtn.disabled = true;
        return;
    }

    pwdStatus.innerHTML = '<span class="text-secondary spinner-border spinner-border-sm" role="status"></span> <span class="text-secondary">Перевірка безпеки...</span>';
    submitBtn.disabled = true; // Disable while checking
    
    timeoutId = setTimeout(async () => {
        try {
            const hash = await sha1(password);
            const prefix = hash.substring(0, 5);
            const suffix = hash.substring(5);

            const response = await fetch(`https://api.pwnedpasswords.com/range/${prefix}`);
            if (!response.ok) throw new Error('API Error');
            
            const text = await response.text();
            const lines = text.split('\n');
            let isCompromised = false;
            let count = 0;

            for (let line of lines) {
                if (line.startsWith(suffix)) {
                    isCompromised = true;
                    count = line.split(':')[1].trim();
                    break;
                }
            }

            if (isCompromised) {
                pwdStatus.innerHTML = `<span class="text-danger"><i class="bi bi-shield-x"></i> Цей пароль був знайдений у витоках даних (${count} разів). Використовувати його небезпечно!</span>`;
                submitBtn.disabled = true;
            } else {
                pwdStatus.innerHTML = '<span class="text-success"><i class="bi bi-shield-check"></i> Пароль надійний і не знайдений у базах витоків!</span>';
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            pwdStatus.innerHTML = '<span class="text-warning">Не вдалося перевірити пароль, спробуйте пізніше.</span>';
            submitBtn.disabled = false;
        }
    }, 500); // Debounce delay
}

if (passwordInput && confirmInput) {
    // Disable submit button by default
    submitBtn.disabled = true;
    passwordInput.addEventListener('input', validatePassword);
    confirmInput.addEventListener('input', validatePassword);
}
</script>
