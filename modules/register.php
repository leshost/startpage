<?php
require_once '../config/config.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Всі поля обов\'язкові для заповнення.';
    } elseif (strlen($username) < 3) {
        $error = 'Логін повинен містити не менше 3 символів.';
    } elseif ($password !== $password_confirm) {
        $error = 'Паролі не співпадають.';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль повинен містити не менше 6 символів.';
    } else {
        // Перевірка на унікальність
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Користувач з таким логіном вже існує.';
        } else {
            // Реєстрація
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $secret_key = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, secret_key) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $hash, $secret_key])) {
                $success = 'Реєстрація успішна! Тепер ви можете <a href="login.php" class="alert-link">увійти</a>.';
            } else {
                $error = 'Помилка бази даних під час реєстрації.';
            }
        }
    }
}

$pageTitle = 'Реєстрація';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 400px;">
        <h3 class="text-center mb-4"><i class="bi bi-person-plus text-info"></i> Реєстрація</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>
            <form method="post">
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
                <button type="submit" id="regSubmitBtn" class="btn btn-primary w-100">Зареєструватися</button>
            </form>
            <div class="text-center mt-3">
                <small class="text-secondary">Вже є акаунт? <a href="login.php" class="text-info text-decoration-none">Увійти</a></small>
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

    if (password.length < 6) {
        pwdStatus.innerHTML = '<span class="text-secondary">Пароль занадто короткий</span>';
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

<?php require_once '../includes/footer.php'; ?>
