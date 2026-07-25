<?php
$readCode = $_GET['code'] ?? '';
$action = $_GET['action'] ?? '';

// Дозволяємо доступ без авторизації ТІЛЬКИ для читання повідомлень
if (!isLoggedIn()) {
    if (empty($readCode) && $action !== 'read_secret') {
        header("Location: /?module=login");
        exit;
    }
    // Забороняємо гостям виконувати інші дії
    if ($action && $action !== 'read_secret') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$pageTitle = 'Одноразові самознищувані повідомлення';

// ── Ініціалізація БД (міграції) ──────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `secrets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(32) NOT NULL UNIQUE,
            `encrypted_title` TEXT NOT NULL,
            `encrypted_content` TEXT NOT NULL,
            `has_password` BOOLEAN DEFAULT FALSE,
            `do_not_delete` BOOLEAN DEFAULT FALSE,
            `is_unencrypted` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `secrets` ADD COLUMN `do_not_delete` BOOLEAN DEFAULT FALSE"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `secrets` ADD COLUMN `is_unencrypted` BOOLEAN DEFAULT FALSE"); } catch (PDOException $e) {}

// ── AJAX Обробка ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $data = json_decode(file_get_contents('php://input'), true);
    verifyCsrf();

    if ($action == 'create_secret') {
        $encryptedTitle = $data['encrypted_title'] ?? '';
        $encryptedContent = $data['encrypted_content'] ?? '';
        $hasPassword = !empty($data['has_password']);
        $doNotDelete = !empty($data['do_not_delete']);
        $isUnencrypted = !empty($data['is_unencrypted']);

        // Генеруємо унікальний код (10 символів)
        $code = substr(bin2hex(random_bytes(10)), 0, 10);

        $stmt = $pdo->prepare("INSERT INTO secrets (code, encrypted_title, encrypted_content, has_password, do_not_delete, is_unencrypted) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $encryptedTitle, $encryptedContent, $hasPassword ? 1 : 0, $doNotDelete ? 1 : 0, $isUnencrypted ? 1 : 0]);

        echo json_encode(['success' => true, 'code' => $code]);
        exit;
    }

    if ($action == 'read_secret') {
        $code = $data['code'] ?? '';
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT encrypted_title, encrypted_content, has_password, do_not_delete, is_unencrypted FROM secrets WHERE code = ? FOR UPDATE");
            $stmt->execute([$code]);
            $secret = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($secret) {
                if (!$secret['do_not_delete']) {
                    // Видаляємо одразу після першого прочитання, якщо не встановлено прапорець
                    $stmtDel = $pdo->prepare("DELETE FROM secrets WHERE code = ?");
                    $stmtDel->execute([$code]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'secret' => $secret]);
            } else {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Повідомлення вже було прочитано або не існує.']);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Помилка доступу до бази даних.']);
        }
        exit;
    }

    exit;
}

$readCode = $_GET['code'] ?? '';
?>

<div class="container mt-5" style="max-width: 800px;">
    <div class="card bg-dark text-white shadow-lg border-secondary">
        <div class="card-header border-secondary d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-info"><i class="bi bi-envelope-x-fill me-2"></i>Одноразове повідомлення</h4>
            <?php if($readCode && isLoggedIn()): ?>
                <a href="/?module=secret" class="btn btn-sm btn-outline-light">Створити нове</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            
            <?php if(!$readCode): ?>
                <!-- РЕЖИМ СТВОРЕННЯ -->
                <p class="text-muted">
                    Створіть захищене повідомлення, яке самознищиться одразу після його першого прочитання. 
                    Шифрування виконується локально у вашому браузері (Zero-Knowledge). Сервер не бачить тексту повідомлення.
                </p>
                
                <div class="mb-3">
                    <label class="form-label text-warning"><i class="bi bi-file-earmark-lock2"></i> Заголовок</label>
                    <input type="text" id="secTitle" class="form-control bg-secondary text-white border-secondary" placeholder="Наприклад: Паролі від сервера...">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-warning"><i class="bi bi-body-text"></i> Вміст повідомлення</label>
                    <textarea id="secContent" class="form-control bg-secondary text-white border-secondary" rows="6" placeholder="Секретний текст..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-key"></i> Пароль (необов'язково)</label>
                    <input type="password" id="secPassword" class="form-control bg-secondary text-white border-secondary mb-2" placeholder="Якщо залишити пустим - згенерується лінк з вбудованим ключем">
                    <div class="d-flex flex-wrap gap-4 mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="secDoNotDelete">
                            <label class="form-check-label text-light" for="secDoNotDelete">Не видаляти (постійний доступ)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="secIsUnencrypted" onchange="toggleEncryption()">
                            <label class="form-check-label text-light" for="secIsUnencrypted">Не шифрувати (відкритий текст)</label>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2" id="secPasswordHelp">Встановіть пароль, якщо хочете, щоб отримувач вводив його власноруч. Якщо ні - ключ буде вбудовано в саме посилання.</small>
                </div>

                <button class="btn btn-primary w-100" id="btnCreate" onclick="createSecret()">
                    <i class="bi bi-lock-fill"></i> Зашифрувати та створити посилання
                </button>
                
                <div id="resultBox" class="mt-4 p-3 bg-secondary rounded" style="display:none; border-left: 5px solid #0dcaf0;">
                    <h5 class="text-info">Повідомлення створено!</h5>
                    <p>Скопіюйте це посилання та передайте отримувачу. Пам'ятайте, що воно спрацює лише один раз!</p>
                    <div class="input-group">
                        <input type="text" id="resultUrl" class="form-control bg-dark text-white" readonly>
                        <button class="btn btn-outline-info" onclick="copyUrl()"><i class="bi bi-clipboard"></i> Копіювати</button>
                    </div>
                </div>

            <?php else: ?>
                <!-- РЕЖИМ ЧИТАННЯ -->
                <?php if(!isLoggedIn()): ?>
                    <div class="alert alert-info border-info text-center">
                        Ви переглядаєте це повідомлення як гість.
                    </div>
                <?php endif; ?>
                
                <div id="readArea">
                    <div class="text-center p-4">
                        <i class="bi bi-envelope-exclamation-fill text-warning mb-3" style="font-size: 4rem;"></i>
                        <h4>Секретне повідомлення</h4>
                        <p class="text-muted">Це повідомлення буде видалено назавжди після того, як ви його прочитаєте.</p>
                        
                        <div id="passwordArea" class="mt-4 mx-auto" style="max-width: 400px; display: none;">
                            <label class="form-label text-warning">Введіть пароль для розшифрування:</label>
                            <input type="password" id="readPassword" class="form-control bg-secondary text-white text-center mb-3">
                        </div>

                        <button class="btn btn-danger btn-lg mt-3" id="btnRead" onclick="fetchAndDecrypt()">
                            <i class="bi bi-eye"></i> Прочитати повідомлення
                        </button>
                    </div>
                </div>

                <div id="decryptedArea" style="display:none;">
                    <div id="decryptedWarning" class="alert alert-warning border-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> <strong>Увага!</strong> Це повідомлення щойно було знищено з бази даних. Воно зникне назавжди після закриття цієї сторінки. Скопіюйте потрібну інформацію зараз.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-info"><i class="bi bi-file-earmark-lock2"></i> Заголовок</label>
                        <div id="decTitle" class="p-3 bg-secondary rounded border border-secondary" style="font-size: 1.1rem; font-weight: bold;"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-info"><i class="bi bi-body-text"></i> Вміст повідомлення</label>
                        <div id="decContent" class="p-3 bg-secondary rounded border border-secondary" style="white-space: pre-wrap; font-family: monospace;"></div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    const csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";
    const readCode = "<?= htmlspecialchars($readCode) ?>";
    let hasPasswordFlag = false;

    // --- Утиліти Base64 ---
    function base64ToArrayBuffer(base64) {
        const binary_string = window.atob(base64);
        const len = binary_string.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) bytes[i] = binary_string.charCodeAt(i);
        return bytes.buffer;
    }
    function arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) binary += String.fromCharCode(bytes[i]);
        return window.btoa(binary);
    }

    // --- Криптографія AES-GCM (PBKDF2 для пароля) ---
    async function deriveKeyFromPassword(password, salt) {
        const encoder = new TextEncoder();
        const keyMaterial = await window.crypto.subtle.importKey("raw", encoder.encode(password), { name: "PBKDF2" }, false, ["deriveKey"]);
        return await window.crypto.subtle.deriveKey(
            { name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256" },
            keyMaterial, { name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]
        );
    }

    // Генерація випадкового ключа (16 байт) -> Base64 (використовується, якщо немає пароля)
    function generateRandomPassword() {
        const arr = new Uint8Array(16);
        window.crypto.getRandomValues(arr);
        return arrayBufferToBase64(arr).replace(/[/+=]/g, ''); // Зручніший для URL вигляд
    }

    async function encryptSecret(text, password) {
        if (!text) return "";
        const salt = window.crypto.getRandomValues(new Uint8Array(16));
        const iv = window.crypto.getRandomValues(new Uint8Array(12));
        const key = await deriveKeyFromPassword(password, salt);
        const encrypted = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, new TextEncoder().encode(text));
        
        return JSON.stringify({
            salt: arrayBufferToBase64(salt),
            iv: arrayBufferToBase64(iv),
            ciphertext: arrayBufferToBase64(encrypted)
        });
    }

    async function decryptSecret(jsonPacket, password) {
        try {
            const data = JSON.parse(jsonPacket);
            const salt = base64ToArrayBuffer(data.salt);
            const iv = base64ToArrayBuffer(data.iv);
            const ciphertext = base64ToArrayBuffer(data.ciphertext);
            
            const key = await deriveKeyFromPassword(password, salt);
            const decrypted = await window.crypto.subtle.decrypt({ name: "AES-GCM", iv: iv }, key, ciphertext);
            return new TextDecoder().decode(decrypted);
        } catch (e) {
            return null; // Decryption failed (wrong password or corrupted)
        }
    }

    // --- Логіка створення ---
    function toggleEncryption() {
        const isUnencrypted = document.getElementById('secIsUnencrypted').checked;
        const passInput = document.getElementById('secPassword');
        const passHelp = document.getElementById('secPasswordHelp');
        
        if (isUnencrypted) {
            passInput.value = '';
            passInput.disabled = true;
            passHelp.innerHTML = 'Шифрування вимкнено. Текст буде збережено у базі даних у відкритому вигляді.';
        } else {
            passInput.disabled = false;
            passHelp.innerHTML = 'Встановіть пароль, якщо хочете, щоб отримувач вводив його власноруч. Якщо ні - ключ буде вбудовано в саме посилання.';
        }
    }

    async function createSecret() {
        const title = document.getElementById('secTitle').value;
        const content = document.getElementById('secContent').value;
        let password = document.getElementById('secPassword').value;
        let hasPassword = true;
        let urlHash = '';
        const doNotDelete = document.getElementById('secDoNotDelete').checked;
        const isUnencrypted = document.getElementById('secIsUnencrypted').checked;

        if (!title && !content) {
            toastr.error('Будь ласка, введіть заголовок або текст.');
            return;
        }

        if (isUnencrypted) {
            hasPassword = false;
            urlHash = '#unencrypted';
        } else if (!password) {
            hasPassword = false;
            password = generateRandomPassword();
            urlHash = '#' + password; // Ключ буде в хеші, сервер його не побачить
        }

        const btn = document.getElementById('btnCreate');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Зберігаємо...';
        btn.disabled = true;

        let encTitle = title;
        let encContent = content;

        if (!isUnencrypted) {
            encTitle = await encryptSecret(title, password);
            encContent = await encryptSecret(content, password);
        }

        const res = await fetch('/?module=secret&action=create_secret', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, 
            body: JSON.stringify({ 
                encrypted_title: encTitle, 
                encrypted_content: encContent, 
                has_password: hasPassword, 
                do_not_delete: doNotDelete,
                is_unencrypted: isUnencrypted
            })
        });
        const data = await res.json();
        
        btn.innerHTML = '<i class="bi bi-lock-fill"></i> Зашифрувати та створити посилання';
        btn.disabled = false;

        if (data.success) {
            const fullUrl = window.location.origin + '/?module=secret&code=' + data.code + urlHash;
            document.getElementById('resultUrl').value = fullUrl;
            document.getElementById('resultBox').style.display = 'block';
            
            // Clear inputs
            document.getElementById('secTitle').value = '';
            document.getElementById('secContent').value = '';
            document.getElementById('secPassword').value = '';
        }
    }

    function copyUrl() {
        const input = document.getElementById('resultUrl');
        input.select();
        document.execCommand('copy');
        toastr.success('Посилання скопійовано в буфер обміну');
    }

    // --- Логіка читання ---
    window.onload = function() {
        if (readCode) {
            // Перевіряємо, чи є пароль в хеші (якщо користувач не задавав свій пароль)
            const hash = window.location.hash.substring(1);
            if (!hash) {
                // Якщо хешу немає, можливо потрібен пароль користувача
                document.getElementById('passwordArea').style.display = 'block';
            }
        }
    };

    async function fetchAndDecrypt() {
        const btn = document.getElementById('btnRead');
        const hashPass = window.location.hash.substring(1);
        const userPass = document.getElementById('readPassword').value;
        const password = hashPass || userPass;

        if (!password && hashPass !== 'unencrypted') {
            toastr.error('Будь ласка, введіть пароль або переконайтеся, що посилання повне (містить #).');
            return;
        }

        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Отримання...';
        btn.disabled = true;

        // Звертаємося до сервера, щоб ОДИН РАЗ забрати повідомлення
        const res = await fetch('/?module=secret&action=read_secret', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, 
            body: JSON.stringify({ code: readCode })
        });
        const data = await res.json();

        if (!data.success) {
            document.getElementById('readArea').innerHTML = `
                <div class="text-center p-4">
                    <i class="bi bi-x-circle text-danger mb-3" style="font-size: 4rem;"></i>
                    <h4 class="text-danger">Помилка!</h4>
                    <p class="text-muted">${data.message}</p>
                </div>
            `;
            return;
        }

        // Намагаємося розшифрувати
        let decTitle = data.secret.encrypted_title;
        let decContent = data.secret.encrypted_content;

        if (data.secret.is_unencrypted != 1) {
            decTitle = await decryptSecret(data.secret.encrypted_title, password);
            decContent = await decryptSecret(data.secret.encrypted_content, password);
        }

        if (decTitle === null || decContent === null) {
            // Розшифрування не вдалося
            document.getElementById('readArea').innerHTML = `
                <div class="text-center p-4">
                    <i class="bi bi-key-fill text-danger mb-3" style="font-size: 4rem;"></i>
                    <h4 class="text-danger">Невірний пароль!</h4>
                    <p class="text-muted">Повідомлення було видалено з сервера під час спроби доступу, але розшифрувати його не вдалося через невірний ключ.</p>
                </div>
            `;
            return;
        }

        // Успішне розшифрування
        document.getElementById('readArea').style.display = 'none';
        document.getElementById('decryptedArea').style.display = 'block';

        if (data.secret.do_not_delete == 1) {
            document.getElementById('decryptedWarning').innerHTML = '<i class="bi bi-info-circle-fill"></i> <strong>Увага!</strong> Це повідомлення є постійним і не було видалено з бази даних. Воно залишиться доступним за цим посиланням.';
            document.getElementById('decryptedWarning').className = 'alert alert-info border-info';
        }

        if (data.secret.is_unencrypted == 1) {
            document.getElementById('decryptedWarning').innerHTML += '<br><i class="bi bi-unlock-fill mt-2 d-inline-block"></i> <strong>Відкритий текст:</strong> це повідомлення не було зашифроване.';
        }

        // Екранування від XSS
        const escapeHTML = str => str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );

        document.getElementById('decTitle').innerHTML = escapeHTML(decTitle);
        document.getElementById('decContent').innerHTML = escapeHTML(decContent);
        
        // Очищаємо хеш в URL для безпеки
        history.replaceState(null, null, ' ');
    }
</script>
