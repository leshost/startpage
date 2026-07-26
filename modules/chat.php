<?php
if (!isLoggedIn()) {
    header("Location: /?module=login");
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `friends` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `friend_id` INT NOT NULL,
            `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sender_id` INT NOT NULL,
            `receiver_id` INT NOT NULL,
            `encrypted_content` TEXT NOT NULL,
            `encrypted_for_sender` TEXT NOT NULL,
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Дії, що змінюють стан даних — потребують CSRF-захисту
$csrfRequiredActions = [
    'init_keys', 'mark_key_saved',
    'send_friend_request', 'accept_friend_request', 'reject_friend_request',
    'send_message', 'mark_as_read',
];

// Обробка AJAX-запитів
if ($action) {
    header('Content-Type: application/json');
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    if (!$data) $data = $_POST;

    // Перевіряємо CSRF тільки для записуючих (state-changing) POST-дій
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, $csrfRequiredActions, true)) {
        verifyCsrf();
    }

    $myId = $_SESSION['user_id'];

    if ($action == 'init_keys') {
        $stmt = $pdo->prepare("UPDATE users SET public_key = ? WHERE id = ?");
        $stmt->execute([$data['publicKey'], $myId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'get_my_info') {
        $stmt = $pdo->prepare("SELECT username, public_key FROM users WHERE id = ?");
        $stmt->execute([$myId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'status'      => 'success',
                'username'    => $user['username'],
                'public_key'  => $user['public_key']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => __('err_user_not_found')]);
        }
        exit;
    }

    if ($action == 'mark_key_saved') {
        $stmt = $pdo->prepare("UPDATE users SET is_key_saved = 1 WHERE id = ?");
        $stmt->execute([$myId]);
        $_SESSION['is_key_saved'] = true;
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- СИСТЕМА ДРУЗІВ ---
    if ($action == 'send_friend_request') {
        $username = trim($data['username'] ?? '');
        
        if ($username === $_SESSION['username']) {
            echo json_encode(['status' => 'error', 'message' => __('err_add_self')]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $friend = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$friend) {
            echo json_encode(['status' => 'error', 'message' => __('err_user_not_found')]);
            exit;
        }
        
        $friendId = $friend['id'];
        
        $stmt = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$myId, $friendId, $friendId, $myId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo json_encode(['status' => 'error', 'message' => __('err_req_sent_or_friends')]);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$myId, $friendId]);
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'get_friend_requests') {
        $stmt = $pdo->prepare("
            SELECT f.id as request_id, u.id as user_id, u.username 
            FROM friends f
            JOIN users u ON f.user_id = u.id
            WHERE f.friend_id = ? AND f.status = 'pending'
        ");
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action == 'accept_friend_request') {
        $requestId = $data['request_id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
        $stmt->execute([$requestId, $myId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'reject_friend_request') {
        $requestId = $data['request_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'");
        $stmt->execute([$requestId, $myId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action == 'get_friends') {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.public_key 
            FROM users u
            JOIN friends f ON (u.id = f.user_id OR u.id = f.friend_id)
            WHERE (f.user_id = ? OR f.friend_id = ?) 
              AND f.status = 'accepted'
              AND u.id != ?
        ");
        $stmt->execute([$myId, $myId, $myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // --- ПОВІДОМЛЕННЯ ---
    if ($action == 'send_message') {
        $receiverId = $data['receiver_id'] ?? 0;
        
        // Перевірка, чи є користувачі друзями
        $stmt = $pdo->prepare("SELECT 1 FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'");
        $stmt->execute([$myId, $receiverId, $receiverId, $myId]);
        if (!$stmt->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => __('err_only_friends')]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, encrypted_content, encrypted_for_sender) VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([
            $myId, 
            $receiverId, 
            $data['content'], 
            $data['content_self']
        ]);
        echo json_encode(['status' => $success ? 'ok' : 'error']);
        exit;
    }

    if ($action == 'get_messages') {
        $friendId = $_GET['friendId'];
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->execute([$myId, $friendId, $friendId, $myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action == 'get_unread_counts') {
        $stmt = $pdo->prepare("
            SELECT sender_id, COUNT(*) as unread_count 
            FROM messages 
            WHERE receiver_id = ? AND is_read = 0 
            GROUP BY sender_id
        ");
        $stmt->execute([$myId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action == 'mark_as_read') {
        $friendId = $data['friendId'];
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$friendId, $myId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    exit;
}

// Перевірка чи ініціалізовано
$stmt = $pdo->prepare("SELECT public_key, is_key_saved FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$isInitialized = !empty($user['public_key']);
$isKeySaved = $user['is_key_saved'] ?? true;

$pageTitle = __('secret_chat');
?>

<div class="container py-5">
    <?php if (!$isInitialized): ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="tool-box text-center">
                    <h3 class="mb-4 text-warning"><i class="bi bi-shield-lock"></i> <?= __('e2ee_init_title') ?></h3>
                    <p class="text-light mb-4"><?= __('e2ee_init_desc') ?></p>
                    
                    <form id="initForm" onsubmit="handleInit(event)">
                        <div class="mb-3 text-start">
                            <label class="form-label text-secondary"><?= __('label_master_pwd') ?></label>
                            <div class="input-group">
                                <input type="password" id="masterPasswordInit" class="form-control bg-dark text-light border-secondary" required placeholder="<?= __('placeholder_master_pwd') ?>">
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div id="pwdStatusInit" class="form-text mt-1"></div>
                            <div class="form-text text-secondary"><?= __('msg_pwd_required_every_time') ?></div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100" id="initBtn"><i class="bi bi-key"></i> <?= __('btn_generate_keys') ?></button>
                    </form>
                </div>
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
            const initBtn = document.getElementById('initBtn');
            const pwdStatus = document.getElementById('pwdStatusInit');
            const passwordInput = document.getElementById('masterPasswordInit');

            function togglePasswordVisibility() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                const icon = document.getElementById('togglePasswordIcon');
                if (type === 'password') {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                } else {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            }

            function validatePassword() {
                clearTimeout(timeoutId);
                const password = passwordInput.value;
                
                if (!password) {
                    pwdStatus.innerHTML = '';
                    initBtn.disabled = true;
                    return;
                }
            
                if (password.length < 6) {
                    pwdStatus.innerHTML = '<span class="text-secondary"><?= __('err_pwd_too_short') ?></span>';
                    initBtn.disabled = true;
                    return;
                }
            
                pwdStatus.innerHTML = '<span class="text-secondary spinner-border spinner-border-sm" role="status"></span> <span class="text-secondary"><?= __('msg_security_check') ?></span>';
                initBtn.disabled = true;
                
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
                            pwdStatus.innerHTML = `<span class="text-danger"><i class="bi bi-shield-x"></i> <?= __('msg_pwd_leaked_1') ?>${count}<?= __('msg_pwd_leaked_2') ?></span>`;
                            initBtn.disabled = true;
                        } else {
                            pwdStatus.innerHTML = '<span class="text-success"><i class="bi bi-shield-check"></i> <?= __('msg_pwd_safe') ?></span>';
                            initBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error(error);
                        pwdStatus.innerHTML = '<span class="text-warning"><?= __('err_pwd_check_failed') ?></span>';
                        initBtn.disabled = false;
                    }
                }, 500);
            }

            if (passwordInput) {
                initBtn.disabled = true;
                passwordInput.addEventListener('input', validatePassword);
            }

            function arrayBufferToBase64(buffer) { return btoa(String.fromCharCode(...new Uint8Array(buffer))); }
            async function deriveMasterKey(password, salt) {
                const encoder = new TextEncoder();
                const baseKey = await window.crypto.subtle.importKey("raw", encoder.encode(password), "PBKDF2", false, ["deriveKey"]);
                return window.crypto.subtle.deriveKey(
                    { name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256" },
                    baseKey, { name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]
                );
            }
            async function encryptAndSaveKey(privKeyRaw, password) {
                const salt = window.crypto.getRandomValues(new Uint8Array(16));
                const iv = window.crypto.getRandomValues(new Uint8Array(12));
                const masterKey = await deriveMasterKey(password, salt);
                const encryptedKey = await window.crypto.subtle.encrypt(
                    { name: "AES-GCM", iv: iv }, masterKey, new TextEncoder().encode(privKeyRaw)
                );
                localStorage.setItem('chat_priv_key_encrypted', JSON.stringify({
                    salt: arrayBufferToBase64(salt), iv: arrayBufferToBase64(iv), ciphertext: arrayBufferToBase64(encryptedKey)
                }));
            }
            async function handleInit(e) {
                e.preventDefault();
                const password = document.getElementById('masterPasswordInit').value;
                document.getElementById('initBtn').disabled = true;
                document.getElementById('initBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> <?= __('msg_generating') ?>';
                try {
                    const keyPair = await window.crypto.subtle.generateKey(
                        { name: "RSA-OAEP", modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: "SHA-256" },
                        true, ["encrypt", "decrypt"]
                    );
                    const pubKeyBuffer = await window.crypto.subtle.exportKey("spki", keyPair.publicKey);
                    const privKeyBuffer = await window.crypto.subtle.exportKey("pkcs8", keyPair.privateKey);
                    const pubKeyBase64 = arrayBufferToBase64(pubKeyBuffer);
                    const privKeyBase64 = arrayBufferToBase64(privKeyBuffer);
                    
                    await encryptAndSaveKey(privKeyBase64, password);
                    
                    const res = await fetch('/?module=chat&action=init_keys', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ publicKey: pubKeyBase64 })
                    });
                    
                    if (res.ok) {
                        toastr.success('<?= __('msg_keys_generated_success') ?>');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        toastr.error('<?= __('err_save_keys_server') ?>');
                        document.getElementById('initBtn').disabled = false;
                    }
                } catch (err) {
                    console.error(err);
                    toastr.error('<?= __('err_keys_creation') ?>');
                    document.getElementById('initBtn').disabled = false;
                }
            }
        </script>
    <?php else: ?>
        
        <style>
            #chat-window { height: 500px; overflow-y: auto; }
            .contact-item { cursor: pointer; transition: 0.2s; }
            .contact-item:hover { background: rgba(255,255,255,0.05) !important; }
            .contact-item.active-user {
                background-color: rgba(255,255,255,0.1) !important;
                border-left: 4px solid #0dcaf0 !important;
            }
            .msg-me { background: #0dcaf0; color: #000; border-radius: 12px 12px 0 12px; }
            .msg-them { background: #343a40; color: #fff; border-radius: 12px 12px 12px 0; border: 1px solid #495057; }
        </style>
        
        <?php if (!$isKeySaved && $isInitialized): ?>
        <div id="keyReminderBanner" class="alert alert-danger shadow mb-4">
            <strong><i class="bi bi-exclamation-triangle-fill fs-5"></i> <?= __('warning_critical') ?></strong> 
            <?= __('msg_key_not_saved') ?>
            <button class="btn btn-sm btn-danger ms-3 text-uppercase fw-bold" onclick="exportPrivateKey()"><i class="bi bi-download"></i> <?= __('btn_save_key') ?></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="tool-box p-0 overflow-hidden h-100 d-flex flex-column">
                    <div class="p-3 bg-dark border-bottom border-secondary d-flex justify-content-between align-items-center">
                        <span class="text-light fw-bold"><i class="bi bi-people"></i> <?= __('tab_contacts') ?></span>
                    </div>
                    
                    <div class="p-3 border-bottom border-secondary">
                        <div class="input-group">
                            <input type="text" id="searchUsername" class="form-control bg-dark text-light border-secondary" placeholder="<?= __('placeholder_username') ?>">
                            <button class="btn btn-outline-info" onclick="sendFriendRequest()"><i class="bi bi-person-plus"></i></button>
                        </div>
                    </div>

                    <div id="friend-requests-container" class="list-group list-group-flush d-none border-bottom border-warning border-2">
                        <!-- Вхідні заявки -->
                    </div>

                    <div class="list-group list-group-flush flex-grow-1 overflow-auto bg-dark" id="contacts-list">
                        <!-- Контакти -->
                    </div>

                    <div class="p-3 border-top border-secondary mt-auto bg-dark">
                        <h6 class="text-secondary small mb-2"><i class="bi bi-key"></i> <?= __('title_key_management') ?></h6>
                        <div class="d-grid gap-2">
                            <button onclick="exportPrivateKey()" class="btn btn-sm btn-outline-secondary text-start"><i class="bi bi-download"></i> <?= __('btn_download_backup') ?></button>
                            <button onclick="document.getElementById('importFile').click()" class="btn btn-sm btn-outline-info text-start"><i class="bi bi-upload"></i> <?= __('btn_restore_from_file') ?></button>
                            <input type="file" id="importFile" class="d-none" onchange="importPrivateKey(this)">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="tool-box p-0 overflow-hidden h-100 d-flex flex-column">
                    <div id="chat-header" class="p-3 bg-secondary text-white text-center fw-bold"><?= __('msg_select_contact') ?></div>
                    <div id="chat-window" class="p-4 bg-dark d-none flex-grow-1">
                    </div>
                    <div id="input-area" class="p-3 border-top border-secondary bg-dark d-none mt-auto">
                        <div class="input-group">
                            <input type="text" id="messageText" class="form-control bg-dark text-light border-secondary" placeholder="<?= __('placeholder_encrypted_msg') ?>">
                            <button class="btn btn-info" onclick="sendMessage()"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="unlockModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-light border-warning">
                    <div class="modal-header border-warning bg-warning text-dark">
                        <h5 class="modal-title"><i class="bi bi-unlock"></i> <?= __('title_unlock_access') ?></h5>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary mb-3"><?= __('msg_enter_master_pwd') ?></p>
                        <div class="input-group">
                            <input type="password" id="masterPassword" class="form-control bg-dark text-light border-secondary" placeholder="<?= __('placeholder_master_pwd_short') ?>" autofocus>
                            <button class="btn btn-warning" onclick="handleUnlock()"><?= __('btn_unlock') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        let currentContactId = null;
        let currentContactPubKey = null;
        let decryptedPrivKey = null; 
        let inactivityTimer;
        const INACTIVITY_LIMIT = 24 * 60 * 60 * 1000; // 24 години
        let unlockModal;

        function lockChat() {
            decryptedPrivKey = null;
            sessionStorage.removeItem('temp_priv_key');
            
            const chatWindow = document.getElementById('chat-window');
            if (chatWindow && currentContactId) {
                chatWindow.innerHTML = '<div class="text-center text-muted mt-5"><i class="bi bi-lock fs-1"></i><br><?= __('msg_enter_pwd_unlock') ?></div>';
            } else if (chatWindow) {
                chatWindow.innerHTML = '';
            }
            
            const inputArea = document.getElementById('input-area');
            if (inputArea) inputArea.classList.add('d-none');
            
            if (unlockModal) unlockModal.show();
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            if (decryptedPrivKey) inactivityTimer = setTimeout(lockChat, INACTIVITY_LIMIT);
        }

        document.addEventListener('DOMContentLoaded', async () => {
            await initMyInfo();
            updateUnreadCounters();
            loadFriendRequests();
            loadFriends();
            
            const msgInput = document.getElementById('messageText');
            if (msgInput) {
                msgInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            const activityEvents = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'];
            activityEvents.forEach(eventName => document.addEventListener(eventName, resetInactivityTimer, true));
            resetInactivityTimer();

            setInterval(() => { loadFriendRequests(); loadFriends(); }, 10000);
            setInterval(updateUnreadCounters, 5000);
            setInterval(() => { if (currentContactId && decryptedPrivKey) loadMessages(); }, 3000);
        });

        async function initMyInfo() {
            const modalElem = document.getElementById('unlockModal');
            if (modalElem && typeof bootstrap !== 'undefined') unlockModal = new bootstrap.Modal(modalElem);

            const sessionKey = sessionStorage.getItem('temp_priv_key');
            if (sessionKey) decryptedPrivKey = sessionKey;

            try {
                const res = await fetch('/?module=chat&action=get_my_info');
                if (res.ok) {
                    const data = await res.json();
                    localStorage.setItem('chat_my_pub_key', data.public_key);
                    localStorage.setItem('chat_user_username', data.username);
                    if (data.private_key) {
                        localStorage.setItem('chat_priv_key_encrypted', data.private_key);
                    }
                }
            } catch (e) { console.error(e); }

            const hasKeyInStorage = localStorage.getItem('chat_priv_key_encrypted');

            if (decryptedPrivKey) {
                if (currentContactId) loadMessages(); 
                updateUnreadCounters();
            } else if (hasKeyInStorage) {
                if (unlockModal) {
                    unlockModal.show();
                    setTimeout(() => {
                        const passInput = document.getElementById('masterPassword');
                        if (passInput) {
                            passInput.focus();
                            passInput.onkeydown = (e) => { if (e.key === 'Enter') handleUnlock(); };
                        }
                    }, 500);
                }
            } else {
                const chatHeader = document.getElementById('chat-header');
                if (chatHeader) chatHeader.innerHTML = "<span class='text-warning' style='cursor: pointer;' onclick=\"document.getElementById('importFile').click()\"><i class='bi bi-exclamation-triangle'></i> <?= __('msg_key_missing_import') ?></span>";
            }
        }

        function arrayBufferToBase64(buffer) { return btoa(String.fromCharCode(...new Uint8Array(buffer))); }
        function base64ToArrayBuffer(base64) {
            const binary = atob(base64);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            return bytes.buffer;
        }

        async function deriveMasterKey(password, salt) {
            const encoder = new TextEncoder();
            const baseKey = await window.crypto.subtle.importKey("raw", encoder.encode(password), "PBKDF2", false, ["deriveKey"]);
            return window.crypto.subtle.deriveKey(
                { name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256" },
                baseKey, { name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]
            );
        }

        async function encryptAndSaveKey(privKeyRaw, password) {
            const salt = window.crypto.getRandomValues(new Uint8Array(16));
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const masterKey = await deriveMasterKey(password, salt);
            const encryptedKey = await window.crypto.subtle.encrypt(
                { name: "AES-GCM", iv: iv }, masterKey, new TextEncoder().encode(privKeyRaw)
            );
            const storageObj = { salt: arrayBufferToBase64(salt), iv: arrayBufferToBase64(iv), ciphertext: arrayBufferToBase64(encryptedKey) };
            localStorage.setItem('chat_priv_key_encrypted', JSON.stringify(storageObj));
        }

        async function handleUnlock() {
            const passInput = document.getElementById('masterPassword');
            const password = passInput.value;
            if (!password) { passInput.classList.add('is-invalid'); return; }

            const success = await unlockPrivateKey(password);
            if (success) {
                passInput.classList.remove('is-invalid');
                passInput.value = '';
                if (unlockModal) unlockModal.hide();
                resetInactivityTimer();
                await savePrivateKeyToServer();
                if (currentContactId) await loadMessages();
                updateUnreadCounters();
                toastr.success("<?= __('msg_chat_unlocked') ?>");
            } else {
                passInput.classList.add('is-invalid');
                passInput.value = '';
                passInput.focus();
                toastr.error("<?= __('err_invalid_master_pwd') ?>");
            }
        }

        async function unlockPrivateKey(password) {
            const dataRaw = localStorage.getItem('chat_priv_key_encrypted');
            if (!dataRaw) return false;
            try {
                const data = JSON.parse(dataRaw);
                const masterKey = await deriveMasterKey(password, base64ToArrayBuffer(data.salt));
                const decrypted = await window.crypto.subtle.decrypt(
                    { name: "AES-GCM", iv: base64ToArrayBuffer(data.iv) },
                    masterKey, base64ToArrayBuffer(data.ciphertext)
                );
                const keyText = new TextDecoder().decode(decrypted);
                decryptedPrivKey = keyText;
                sessionStorage.setItem('temp_priv_key', keyText);
                return true;
            } catch (e) { return false; }
        }

        function exportPrivateKey() {
            const data = localStorage.getItem('chat_priv_key_encrypted');
            if (!data) return toastr.warning("<?= __('err_no_key_to_export') ?>");
            let userEmail = localStorage.getItem('chat_user_username') || 'backup';
            let safeName = userEmail.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            const blob = new Blob([data], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `private_key_${safeName}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // Mark key as saved
            fetch('/?module=chat&action=mark_key_saved', { method: 'POST' })
                .then(() => {
                    document.querySelectorAll('#keyReminderBanner').forEach(b => b.remove());
                })
                .catch(e => console.error(e));
        }

        async function importPrivateKey(input) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (e) => {
                const content = e.target.result.trim();
                if (content.startsWith('{')) {
                    localStorage.setItem('chat_priv_key_encrypted', content);
                } else {
                    const password = prompt("<?= __('msg_file_unencrypted_enter_pwd') ?>");
                    if (!password) { input.value = ''; return; }
                    await encryptAndSaveKey(content, password);
                }
                location.reload();
            };
            reader.readAsText(file);
        }

        async function encryptHybrid(text, rsaPubKeyB64) {
            const rsaKey = await window.crypto.subtle.importKey(
                "spki", base64ToArrayBuffer(rsaPubKeyB64), { name: "RSA-OAEP", hash: "SHA-256" }, false, ["encrypt"]
            );
            const aesKey = await window.crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt"]);
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const encryptedContent = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, aesKey, new TextEncoder().encode(text));
            const exportedAesKey = await window.crypto.subtle.exportKey("raw", aesKey);
            const encryptedAesKey = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, rsaKey, exportedAesKey);

            return JSON.stringify({
                key: arrayBufferToBase64(encryptedAesKey), iv: arrayBufferToBase64(iv), content: arrayBufferToBase64(encryptedContent)
            });
        }

        async function decryptHybrid(jsonPacket) {
            try {
                if (!decryptedPrivKey) return "<?= __('msg_chat_locked') ?>";
                const data = JSON.parse(jsonPacket);
                const rsaKey = await window.crypto.subtle.importKey(
                    "pkcs8", base64ToArrayBuffer(decryptedPrivKey), { name: "RSA-OAEP", hash: "SHA-256" }, false, ["decrypt"]
                );
                const decAesKeyRaw = await window.crypto.subtle.decrypt({ name: "RSA-OAEP" }, rsaKey, base64ToArrayBuffer(data.key));
                const aesKey = await window.crypto.subtle.importKey("raw", decAesKeyRaw, "AES-GCM", false, ["decrypt"]);
                const decText = await window.crypto.subtle.decrypt({ name: "AES-GCM", iv: base64ToArrayBuffer(data.iv) }, aesKey, base64ToArrayBuffer(data.content));
                return new TextDecoder().decode(decText);
            } catch (e) { return "<?= __('err_decryption') ?>"; }
        }

        async function selectContact(id, username, pubKeyB64, event) {
            currentContactId = id;
            currentContactPubKey = pubKeyB64;
            
            document.querySelectorAll('.contact-item').forEach(i => i.classList.remove('active-user', 'bg-secondary'));
            if (event && event.currentTarget) event.currentTarget.classList.add('active-user');

            document.getElementById('chat-window').innerHTML = '';
            document.getElementById('chat-header').innerHTML = `<i class="bi bi-chat-dots"></i> <?= __('chat_with') ?><strong>${username}</strong>`;
            document.getElementById('chat-window').classList.remove('d-none');
            
            if (decryptedPrivKey) document.getElementById('input-area').classList.remove('d-none');
            else document.getElementById('input-area').classList.add('d-none');

            await markAsRead(id);
            loadMessages();
        }

        async function sendMessage() {
            const input = document.getElementById('messageText');
            const text = input.value.trim();
            if (!text || !currentContactId) return;

            const myPubKey = localStorage.getItem('chat_my_pub_key');
            const packFriend = await encryptHybrid(text, currentContactPubKey);
            const packMe = await encryptHybrid(text, myPubKey);

            const res = await fetch('/?module=chat&action=send_message', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receiver_id: currentContactId, content: packFriend, content_self: packMe })
            });
            if (res.ok) {
                input.value = '';
                await loadMessages();
            }
        }

        async function loadMessages() {
            if (!currentContactId) return;
            const chatBox = document.getElementById('chat-window');
            const inputArea = document.getElementById('input-area');
            
            if (!decryptedPrivKey) {
                if (chatBox) chatBox.innerHTML = '<div class="text-center text-muted mt-5"><i class="bi bi-lock fs-1"></i><br><?= __('msg_enter_pwd_unlock') ?></div>';
                if (inputArea) inputArea.classList.add('d-none');
                return;
            }
            if (inputArea) inputArea.classList.remove('d-none');

            const res = await fetch(`/?module=chat&action=get_messages&friendId=${currentContactId}`);
            const msgs = await res.json();
            const existingIds = Array.from(chatBox.querySelectorAll('[data-msg-id]')).map(d => d.getAttribute('data-msg-id'));

            let added = false;
            for (let m of msgs) {
                if (existingIds.includes(m.id.toString())) {
                    if (m.sender_id != currentContactId) {
                        const s = document.getElementById(`status-${m.id}`);
                        if (s) s.innerHTML = (m.is_read == 1 ? '<i class="bi bi-check-all text-info"></i>' : '<i class="bi bi-check"></i>');
                    }
                    continue;
                }

                added = true;
                const isMe = (m.sender_id != currentContactId);
                const text = await decryptHybrid(isMe ? m.encrypted_for_sender : m.encrypted_content);
                const escapedText = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                const status = isMe ? `<span id="status-${m.id}" class="ms-1">${m.is_read == 1 ? '<i class="bi bi-check-all text-info"></i>' : '<i class="bi bi-check"></i>'}</span>` : '';

                const div = document.createElement('div');
                div.className = isMe ? 'text-end mb-3' : 'text-start mb-3';
                div.setAttribute('data-msg-id', m.id);
                div.innerHTML = `
                    <div class="d-inline-block p-2 shadow-sm ${isMe ? 'msg-me' : 'msg-them'}" style="max-width: 80%; text-align: left;">
                        <span style="white-space: pre-wrap;">${escapedText}</span>
                        <div class="d-flex justify-content-end align-items-center mt-1" style="font-size: 0.65rem; opacity: 0.8;">
                            <span>${m.created_at.substring(11, 16)}</span>${status}
                        </div>
                    </div>`;
                chatBox.appendChild(div);
            }
            if (added) chatBox.scrollTop = chatBox.scrollHeight;
        }

        async function updateUnreadCounters() {
            const res = await fetch('/?module=chat&action=get_unread_counts');
            const data = await res.json();
            document.querySelectorAll('.unread-badge').forEach(b => b.classList.add('d-none'));
            data.forEach(item => {
                const b = document.getElementById(`unread-${item.sender_id}`);
                if (b) { b.innerText = item.unread_count; b.classList.remove('d-none'); }
            });
        }

        async function markAsRead(friendId) {
            await fetch('/?module=chat&action=mark_as_read', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ friendId })
            });
            updateUnreadCounters();
        }

        async function sendFriendRequest() {
            const usernameInput = document.getElementById('searchUsername');
            const username = usernameInput.value.trim();
            if (!username) return;

            try {
                const res = await fetch('/?module=chat&action=send_friend_request', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    toastr.success('<?= __('msg_req_sent') ?>');
                    usernameInput.value = '';
                } else { toastr.error(data.message); }
            } catch (e) { toastr.error('<?= __('err_network') ?>'); }
        }

        async function loadFriendRequests() {
            const res = await fetch('/?module=chat&action=get_friend_requests');
            if (!res.ok) return;
            const requests = await res.json();
            const container = document.getElementById('friend-requests-container');
            if (!container) return;

            if (requests.length > 0) {
                container.classList.remove('d-none');
                let html = '<div class="p-2 bg-dark text-warning small fw-bold"><i class="bi bi-bell"></i> <?= __('msg_new_requests') ?></div>';
                requests.forEach(req => {
                    const safeUsername = req.username.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center bg-dark border-secondary">
                            <span class="text-light text-truncate" style="max-width: 120px;">${safeUsername}</span>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="acceptRequest(${req.request_id})"><i class="bi bi-check"></i></button>
                                <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="rejectRequest(${req.request_id})"><i class="bi bi-x"></i></button>
                            </div>
                        </div>`;
                });
                container.innerHTML = html;
            } else { container.classList.add('d-none'); container.innerHTML = ''; }
        }

        async function acceptRequest(id) {
            const res = await fetch('/?module=chat&action=accept_friend_request', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: id })
            });
            const data = await res.json();
            if (data.status === 'success') { await loadFriendRequests(); await loadFriends(); }
        }

        async function rejectRequest(id) {
            const res = await fetch('/?module=chat&action=reject_friend_request', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: id })
            });
            const data = await res.json();
            if (data.status === 'success') { await loadFriendRequests(); }
        }

        async function loadFriends() {
            const res = await fetch('/?module=chat&action=get_friends');
            if (!res.ok) return;
            const friends = await res.json();
            const container = document.getElementById('contacts-list');
            if (!container) return;

            let html = '';
            friends.forEach(user => {
                const safeUsername = user.username.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                const safeUsernameJs = safeUsername.replace(/'/g, "\\'");
                const safePubKeyJs = user.public_key.replace(/'/g, "\\'");
                const isActive = currentContactId == user.id ? 'active-user' : '';
                html += `
                    <div class="list-group-item contact-item d-flex justify-content-between align-items-center bg-dark text-light border-secondary ${isActive}" 
                        onclick="selectContact(${user.id}, '${safeUsernameJs}', '${safePubKeyJs}', event)">
                        <span><i class="bi bi-person text-secondary"></i> ${safeUsername}</span>
                        <span class="badge bg-danger rounded-pill unread-badge d-none" id="unread-${user.id}">0</span>
                    </div>`;
            });
            container.innerHTML = html;
            updateUnreadCounters();
        }
        </script>
    <?php endif; ?>
</div>
