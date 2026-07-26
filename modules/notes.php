<?php
if (!isLoggedIn()) {
    header("Location: /?module=login");
    exit;
}

$myId = $_SESSION['user_id'];
$pageTitle = 'Секретні нотатки';

// ── AJAX Обробка ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Перевірка CSRF (через заголовок X-CSRF-Token для AJAX fetch)
    verifyCsrf();

    if ($action == 'get_my_pub_key') {
        $stmt = $pdo->prepare("SELECT public_key FROM users WHERE id = ?");
        $stmt->execute([$myId]);
        echo json_encode(['public_key' => $stmt->fetchColumn()]);
        exit;
    }

    if ($action == 'get_notes') {
        $stmt = $pdo->prepare("SELECT id, title, DATE_FORMAT(updated_at, '%d.%m.%Y %H:%i') as updated_at FROM notes WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$myId]);
        echo json_encode(['notes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action == 'get_note') {
        $noteId = $data['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, title, encrypted_content FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$noteId, $myId]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($note) {
            echo json_encode(['success' => true, 'note' => $note]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Нотатку не знайдено']);
        }
        exit;
    }

    if ($action == 'create_note') {
        // Рахуємо кількість нотаток, щоб дати назву по порядку
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ?");
        $stmtCount->execute([$myId]);
        $count = (int)$stmtCount->fetchColumn();
        $title = 'Нотатка ' . ($count + 1);

        // Початково створюємо з порожнім контентом, щоб клієнт міг одразу її відкрити
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, encrypted_content) VALUES (?, ?, '')");
        $stmt->execute([$myId, $title]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    if ($action == 'save_note') {
        $noteId = $data['id'] ?? 0;
        $title = trim($data['title'] ?? 'Без назви');
        $encryptedContent = $data['encrypted_content'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE notes SET title = ?, encrypted_content = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $encryptedContent, $noteId, $myId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action == 'delete_note') {
        $noteId = $data['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$noteId, $myId]);
        echo json_encode(['success' => true]);
        exit;
    }

    exit; // Stop executing on AJAX requests
}

?>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
    .notes-container {
        display: flex;
        height: calc(100vh - 70px);
        margin: -1.5rem; /* Компенсуємо паддінги контейнера, якщо вони є */
        overflow: hidden;
    }
    .notes-sidebar {
        width: 300px;
        background: #2a2a2a;
        border-right: 1px solid #3d3d3d;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .note-item {
        padding: 15px;
        border-bottom: 1px solid #3d3d3d;
        cursor: pointer;
        transition: background 0.2s;
    }
    .note-item:hover { background: #333; }
    .note-item.active { background: #3d3d3d; border-left: 4px solid #0d6efd; }
    .note-item-title { font-weight: bold; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff;}
    .note-item-date { font-size: 0.8rem; color: #aaa; margin-top: 5px; }
    
    .notes-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #1e1e1e;
        position: relative;
    }
    .note-header {
        padding: 15px 20px;
        background: #2a2a2a;
        border-bottom: 1px solid #3d3d3d;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .note-title-input {
        background: transparent;
        border: none;
        color: #fff;
        font-size: 1.5rem;
        font-weight: bold;
        width: 100%;
        outline: none;
    }
    .note-editor-area {
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        min-height: 0; /* Fix flexbox overflow issue */
    }
    .note-textarea {
        flex: 1;
        background: transparent;
        color: #ddd;
        border: none;
        padding: 20px;
        font-family: monospace;
        font-size: 1rem;
        resize: none;
        outline: none;
        width: 100%;
        overflow-y: auto;
    }
    .note-preview {
        flex: 1;
        padding: 20px;
        background: #1e1e1e;
        color: #ddd;
        overflow-y: auto;
        display: none;
    }
    .note-preview h1, .note-preview h2, .note-preview h3 { color: #fff; }
    .note-preview a { color: #0d6efd; }
    .note-preview code { background: #2a2a2a; padding: 2px 4px; border-radius: 4px; color: #e83e8c;}
    .note-preview pre { background: #2a2a2a; padding: 10px; border-radius: 6px; overflow-x: auto; }
    
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
    }

    .markdown-helper {
        width: 250px;
        background: #2a2a2a;
        border-left: 1px solid #3d3d3d;
        padding: 15px;
        color: #ddd;
        overflow-y: auto;
    }
    .markdown-helper h6 { color: #fff; margin-top: 15px; font-size: 0.9rem; }
    .markdown-helper code { background: #1e1e1e; padding: 2px 4px; border-radius: 4px; color: #e83e8c; font-size: 0.85rem;}
    
    @media (max-width: 768px) {
        .notes-container {
            flex-direction: column;
            height: auto;
            min-height: calc(100vh - 70px);
            overflow: visible;
        }
        .notes-sidebar {
            width: 100%;
            height: 250px;
            border-right: none;
            border-bottom: 1px solid #3d3d3d;
        }
        .notes-main {
            height: 500px;
        }
        .markdown-helper {
            width: 100%;
            height: auto;
            border-left: none;
            border-top: 1px solid #3d3d3d;
        }
    }
    
    /* Модальне вікно для пароля */
    #unlockModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1050; align-items: center; justify-content: center; }
</style>

<div class="notes-container w-100 m-0">
    <!-- Sidebar -->
    <div class="notes-sidebar">
        <div class="p-3 border-bottom" style="border-color: #3d3d3d !important; display: flex; justify-content: space-between; align-items: center; background: #212529;">
            <h5 class="m-0 text-white"><i class="bi bi-journal-lock text-primary me-2"></i>Нотатки</h5>
            <button class="btn btn-sm btn-primary" onclick="createNote()"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div id="notesList">
            <!-- Тут будуть нотатки -->
        </div>
    </div>

    <!-- Main Content -->
    <div class="notes-main">
        <div id="emptyState" class="empty-state">
            <i class="bi bi-shield-lock" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <h4>E2EE Секретні нотатки</h4>
            <p>Оберіть нотатку зліва або створіть нову.</p>
        </div>
        
        <div id="noteEditor" style="display: none; flex: 1; flex-direction: column; height: 100%;">
            <div class="note-header">
                <input type="text" id="noteTitle" class="note-title-input" placeholder="Назва нотатки" oninput="markUnsaved()">
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="btnEdit" onclick="switchTab('edit')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-info" id="btnPreview" onclick="switchTab('preview')"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-sm btn-success" id="btnSave" onclick="saveNote()"><i class="bi bi-save"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCurrentNote()"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="note-editor-area">
                <textarea id="noteContent" class="note-textarea" placeholder="Введіть текст (підтримується Markdown)..." oninput="markUnsaved()"></textarea>
                <div id="notePreviewContent" class="note-preview"></div>
            </div>
        </div>
    </div>

    <!-- Markdown Helper -->
    <div class="markdown-helper d-none d-md-block" id="markdownHelper">
        <h5 class="text-white border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-info me-2"></i>Markdown</h5>
        <p class="small text-muted mb-2">Основні команди форматування:</p>
        
        <h6>Заголовки</h6>
        <code># Заголовок 1</code><br>
        <code>## Заголовок 2</code>
        
        <h6>Текст</h6>
        <code>**Жирний**</code><br>
        <code>*Курсив*</code><br>
        <code>~~Закреслений~~</code>
        
        <h6>Списки</h6>
        <code>- Пункт 1</code><br>
        <code>- Пункт 2</code><br>
        <code>1. Нумерований</code>
        
        <h6>Код та посилання</h6>
        <code>`Код`</code><br>
        <code>[Текст](URL)</code>
    </div>
</div>

<!-- Модальне вікно для пароля (якщо E2EE ключ не завантажено) -->
<div id="unlockModal">
    <div class="card bg-dark text-white shadow-lg p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-3">
            <i class="bi bi-lock text-warning" style="font-size: 3rem;"></i>
        </div>
        <h4 class="text-center">Розблокуйте сховище</h4>
        <p class="text-muted text-center small mb-4">Для доступу до ваших зашифрованих нотаток, будь ласка, введіть свій майстер-пароль.</p>
        <input type="password" id="unlockPassword" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Майстер-пароль">
        <button class="btn btn-primary w-100" onclick="handleUnlock()">Розблокувати</button>
        <div id="unlockError" class="text-danger small mt-2 text-center" style="display:none;"></div>
        
        <hr class="border-secondary mt-4 mb-3">
        <p class="text-muted text-center small mb-2">Або завантажте файл з вашим приватним ключем, якщо ви заходите з нового пристрою.</p>
        <input type="file" id="importKeyFile" class="form-control form-control-sm bg-secondary text-white border-0" accept=".txt">
    </div>
</div>

<script>
    // --- Змінні та налаштування ---
    const csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";
    let decryptedPrivKey = null;
    let myPublicKeyB64 = null;
    
    let currentNoteId = null;
    let isUnsaved = false;

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

    // --- Криптографія (Гібридне шифрування E2EE) ---
    async function deriveMasterKey(password, salt) {
        const encoder = new TextEncoder();
        const keyMaterial = await window.crypto.subtle.importKey("raw", encoder.encode(password), { name: "PBKDF2" }, false, ["deriveKey"]);
        return await window.crypto.subtle.deriveKey(
            { name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256" },
            keyMaterial, { name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]
        );
    }

    async function unlockPrivateKey(password) {
        try {
            const dataRaw = localStorage.getItem('chat_priv_key_encrypted');
            if (!dataRaw) return false;
            const data = JSON.parse(dataRaw);
            const salt = base64ToArrayBuffer(data.salt);
            const iv = base64ToArrayBuffer(data.iv);
            const ciphertext = base64ToArrayBuffer(data.ciphertext);
            const aesKey = await deriveMasterKey(password, salt);
            const decrypted = await window.crypto.subtle.decrypt({ name: "AES-GCM", iv: iv }, aesKey, ciphertext);
            const keyText = new TextDecoder().decode(decrypted);
            decryptedPrivKey = keyText;
            sessionStorage.setItem('temp_priv_key', keyText);
            return true;
        } catch (e) {
            return false;
        }
    }

    async function encryptHybrid(text, rsaPubKeyB64) {
        if (!text) return "";
        const rsaKey = await window.crypto.subtle.importKey(
            "spki", base64ToArrayBuffer(rsaPubKeyB64), { name: "RSA-OAEP", hash: "SHA-256" }, false, ["encrypt"]
        );
        const aesKey = await window.crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt"]);
        const iv = window.crypto.getRandomValues(new Uint8Array(12));
        const encryptedContent = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, aesKey, new TextEncoder().encode(text));
        const exportedAesKey = await window.crypto.subtle.exportKey("raw", aesKey);
        const encryptedAesKey = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, rsaKey, exportedAesKey);

        return JSON.stringify({
            key: arrayBufferToBase64(encryptedAesKey), 
            iv: arrayBufferToBase64(iv), 
            content: arrayBufferToBase64(encryptedContent)
        });
    }

    async function decryptHybrid(jsonPacket) {
        if (!jsonPacket) return "";
        
        let data;
        try {
            data = JSON.parse(jsonPacket);
            if (!data.key || !data.iv || !data.content) throw new Error("Not E2EE JSON");
        } catch (e) {
            // Якщо це не JSON, значить це старий або нешифрований текст (наприклад, заголовок по замовчуванню)
            return jsonPacket; 
        }

        try {
            if (!decryptedPrivKey) return "[Заблоковано]";
            const rsaKey = await window.crypto.subtle.importKey(
                "pkcs8", base64ToArrayBuffer(decryptedPrivKey), { name: "RSA-OAEP", hash: "SHA-256" }, false, ["decrypt"]
            );
            const decAesKeyRaw = await window.crypto.subtle.decrypt({ name: "RSA-OAEP" }, rsaKey, base64ToArrayBuffer(data.key));
            const aesKey = await window.crypto.subtle.importKey("raw", decAesKeyRaw, "AES-GCM", false, ["decrypt"]);
            const decText = await window.crypto.subtle.decrypt({ name: "AES-GCM", iv: base64ToArrayBuffer(data.iv) }, aesKey, base64ToArrayBuffer(data.content));
            return new TextDecoder().decode(decText);
        } catch (e) { return "[Помилка дешифрування]"; }
    }

    // --- Логіка UI ---
    async function loadNotes() {
        const res = await fetch('/?module=notes&action=get_notes', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({})
        });
        const data = await res.json();
        const list = document.getElementById('notesList');
        list.innerHTML = '';
        if(data.notes.length === 0) {
            list.innerHTML = '<div class="p-3 text-muted text-center small">Немає нотаток</div>';
            return;
        }
        for (const note of data.notes) {
            const decTitle = await decryptHybrid(note.title);
            const div = document.createElement('div');
            div.className = 'note-item' + (note.id == currentNoteId ? ' active' : '');
            
            // Екрануємо потенційно небезпечні символи (XSS)
            const safeTitle = decTitle.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            
            div.innerHTML = `<div class="note-item-title">${safeTitle}</div><div class="note-item-date">${note.updated_at}</div>`;
            div.onclick = () => openNote(note.id);
            list.appendChild(div);
        }
    }

    async function createNote() {
        const res = await fetch('/?module=notes&action=create_note', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({})
        });
        const data = await res.json();
        if(data.success) {
            await loadNotes();
            openNote(data.id, true);
        }
    }

    async function openNote(id, isNew = false) {
        if (isUnsaved && !confirm("У вас є незбережені зміни. Продовжити без збереження?")) return;
        
        currentNoteId = id;
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('noteEditor').style.display = 'flex';
        
        isUnsaved = false;
        document.getElementById('btnSave').classList.replace('btn-warning', 'btn-success');
        
        const res = await fetch('/?module=notes&action=get_note', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({id: id})
        });
        const data = await res.json();
        if(data.success) {
            document.getElementById('noteTitle').value = await decryptHybrid(data.note.title);
            if (data.note.encrypted_content) {
                document.getElementById('noteContent').value = await decryptHybrid(data.note.encrypted_content);
            } else {
                document.getElementById('noteContent').value = '';
            }
            switchTab(isNew ? 'edit' : 'preview');
            await loadNotes(); // Щоб оновити активний стан в списку
        }
    }

    async function saveNote() {
        if(!currentNoteId) return;
        const title = document.getElementById('noteTitle').value;
        const content = document.getElementById('noteContent').value;
        
        const btnSave = document.getElementById('btnSave');
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        
        const encryptedTitle = await encryptHybrid(title, myPublicKeyB64);
        const encryptedContent = await encryptHybrid(content, myPublicKeyB64);
        
        const res = await fetch('/?module=notes&action=save_note', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, 
            body: JSON.stringify({id: currentNoteId, title: encryptedTitle, encrypted_content: encryptedContent})
        });
        const data = await res.json();
        if(data.success) {
            isUnsaved = false;
            btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Збережено';
            btnSave.classList.replace('btn-warning', 'btn-success');
            setTimeout(() => { btnSave.innerHTML = '<i class="bi bi-save"></i> Зберегти'; }, 2000);
            loadNotes();
        }
    }

    async function deleteCurrentNote() {
        if(!currentNoteId || !confirm("Ви впевнені, що хочете видалити цю нотатку?")) return;
        const res = await fetch('/?module=notes&action=delete_note', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, 
            body: JSON.stringify({id: currentNoteId})
        });
        const data = await res.json();
        if(data.success) {
            currentNoteId = null;
            document.getElementById('emptyState').style.display = 'flex';
            document.getElementById('noteEditor').style.display = 'none';
            loadNotes();
        }
    }

    function switchTab(tab) {
        const editArea = document.getElementById('noteContent');
        const prevArea = document.getElementById('notePreviewContent');
        const btnEdit = document.getElementById('btnEdit');
        const btnPrev = document.getElementById('btnPreview');
        
        if (tab === 'edit') {
            editArea.style.display = 'block';
            prevArea.style.display = 'none';
            btnEdit.classList.add('active');
            btnPrev.classList.remove('active');
        } else {
            editArea.style.display = 'none';
            prevArea.style.display = 'block';
            btnEdit.classList.remove('active');
            btnPrev.classList.add('active');
            // Parse Markdown
            prevArea.innerHTML = marked.parse(editArea.value);
        }
    }

    function markUnsaved() {
        isUnsaved = true;
        document.getElementById('btnSave').classList.replace('btn-success', 'btn-warning');
    }

    // --- Ініціалізація E2EE ---
    async function handleUnlock() {
        const pass = document.getElementById('unlockPassword').value;
        const err = document.getElementById('unlockError');
        if (!pass) return;
        if (await unlockPrivateKey(pass)) {
            document.getElementById('unlockModal').style.display = 'none';
            loadNotes();
        } else {
            err.innerText = "Невірний пароль!";
            err.style.display = 'block';
        }
    }

    document.getElementById('importKeyFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            const content = evt.target.result;
            try {
                JSON.parse(content); // validate JSON
                localStorage.setItem('chat_priv_key_encrypted', content);
                document.getElementById('unlockError').style.display = 'none';
                document.getElementById('unlockPassword').focus();
                toastr.success('Ключ успішно імпортовано. Тепер введіть пароль.');
            } catch (err) {
                document.getElementById('unlockError').innerText = "Недійсний файл ключа";
                document.getElementById('unlockError').style.display = 'block';
            }
        };
        reader.readAsText(file);
    });

    async function fetchMyPubKey() {
        const res = await fetch('/?module=notes&action=get_my_pub_key', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({})
        });
        const data = await res.json();
        myPublicKeyB64 = data.public_key;
        if (!myPublicKeyB64) {
            alert("Помилка: Не знайдено публічний ключ. Створіть його у модулі Чат.");
            window.location.href = '/?module=chat';
        }
    }

    window.onload = async () => {
        await fetchMyPubKey();
        const sessionKey = sessionStorage.getItem('temp_priv_key');
        const hasKeyInStorage = localStorage.getItem('chat_priv_key_encrypted');
        
        if (sessionKey) {
            decryptedPrivKey = sessionKey;
            loadNotes();
        } else if (!hasKeyInStorage) {
            document.getElementById('unlockModal').style.display = 'flex';
        } else {
            document.getElementById('unlockModal').style.display = 'flex'; // Ask for password
        }
    };
    
</script>
