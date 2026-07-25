<?php
// Автоматичне створення таблиць бази даних при першому запуску
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
        
        CREATE TABLE IF NOT EXISTS `sites` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order` INT DEFAULT 0,
            `name` VARCHAR(255) NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `icon` VARCHAR(500) NOT NULL,
            `user` INT DEFAULT NULL,
            FOREIGN KEY (`user`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS `tasks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `status` ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
            `order_num` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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

    // Міграції для додавання нових колонок
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_admin` BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_blocked` BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `public_key` TEXT NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_key_saved` BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` DROP COLUMN `private_key`");
    } catch (PDOException $e) {}
    
    // Робимо першого користувача адміністратором, якщо він існує
    $pdo->exec("UPDATE `users` SET `is_admin` = TRUE ORDER BY `id` ASC LIMIT 1");

} catch (PDOException $e) {
    error_log("DB Initialization Error: " . $e->getMessage());
    die("Помилка ініціалізації бази даних. Спробуйте пізніше.");
}

// AJAX Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Несанкціонований доступ']);
        exit();
    }
    verifyCsrf();
    
    // Delete Site
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM sites WHERE id = :id AND user = :user_id");
        if($stmt->execute(['id' => (int)$_POST['id'], 'user_id' => $_SESSION['user_id']])) {
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Немає прав на видалення або сайт не знайдено']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit();
    }
    
    // Add Site
    if ($_POST['action'] === 'add' && isset($_POST['name'], $_POST['url'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit();
        }
        
        $icon = trim($_POST['icon'] ?? '');
        if (empty($icon)) {
            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? '';
            $icon = "https://www.google.com/s2/favicons?domain=" . urlencode($host) . "&sz=128";
        }

        $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon, user) VALUES (:name, :url, :icon, :user)");
        if($stmt->execute([
            'name' => trim($_POST['name']),
            'url' => $url,
            'icon' => $icon,
            'user' => $_SESSION['user_id']
        ])) {
            echo json_encode([
                'success' => true, 
                'id' => $pdo->lastInsertId(),
                'name' => htmlspecialchars(trim($_POST['name'])),
                'url' => htmlspecialchars($url),
                'icon' => htmlspecialchars($icon)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit();
    }
    
    // Edit Site
    if ($_POST['action'] === 'edit' && isset($_POST['id'], $_POST['name'], $_POST['url'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit();
        }

        $icon = trim($_POST['icon'] ?? '');
        if (empty($icon)) {
            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? '';
            $icon = "https://www.google.com/s2/favicons?domain=" . urlencode($host) . "&sz=128";
        }

        $stmt = $pdo->prepare("UPDATE sites SET name = :name, url = :url, icon = :icon WHERE id = :id AND user = :user_id");
        if($stmt->execute([
            'name' => trim($_POST['name']),
            'url' => $url,
            'icon' => $icon,
            'id' => (int)$_POST['id'],
            'user_id' => $_SESSION['user_id']
        ])) {
            echo json_encode([
                'success' => true,
                'id' => (int)$_POST['id'],
                'name' => htmlspecialchars(trim($_POST['name'])),
                'url' => htmlspecialchars($url),
                'icon' => htmlspecialchars($icon)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit();
    }

    // Reorder Sites
    if ($_POST['action'] === 'reorder' && isset($_POST['order'])) {
        $orderArray = json_decode($_POST['order'], true);
        if (is_array($orderArray)) {
            // Додаємо AND user = :user_id — UPDATE буде ігнорувати чужі ID,
            // навіть якщо зловмисник передасть чужі site ID
            $stmt = $pdo->prepare("
                UPDATE sites SET `order` = :order
                WHERE id = :id AND user = :user_id
            ");
            foreach ($orderArray as $index => $id) {
                $stmt->execute([
                    'order'   => $index,
                    'id'      => (int)$id,
                    'user_id' => $_SESSION['user_id'],
                ]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Невірний формат даних']);
        }
        exit();
    }
}

// Fetch Sites
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT `id`, `name`, `url`, `icon`, `user` FROM `sites` WHERE `user` IS NULL OR `user` = ? ORDER BY `order`");
    $stmt->execute([$_SESSION['user_id']]);
} elseif (!empty($_SESSION['view_only_user_id'])) {
    $stmt = $pdo->prepare("SELECT `id`, `name`, `url`, `icon`, `user` FROM `sites` WHERE `user` IS NULL OR `user` = ? ORDER BY `order`");
    $stmt->execute([$_SESSION['view_only_user_id']]);
} else {
    $stmt = $pdo->query("SELECT `id`, `name`, `url`, `icon`, `user` FROM `sites` WHERE `user` IS NULL ORDER BY `order`");
}
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userIp = getUserIP();
$clientInfo = getClientInfo();

$chatUnread = [];
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, COUNT(m.id) as unread_count
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ? AND m.is_read = 0
            GROUP BY m.sender_id
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $chatUnread = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$pageTitle = 'Головна';
$bodyClass = 'startpage-body';

?>

<div class="startpage-overlay"></div>

<style>
body.edit-mode-active .site-item {
    cursor: grab;
}
body.edit-mode-active .site-item:active {
    cursor: grabbing;
}
.sites-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
    max-width: 1800px; /* Дозволяє розтягуватись на широких екранах */
    margin: 0 auto;
    gap: 1.5rem;
}
.sites-grid .site-item {
    width: 90px;
}
@media (min-width: 576px) {
    .sites-grid { gap: 2rem; }
    .sites-grid .site-item { width: 100px; }
}
@media (min-width: 992px) {
    .sites-grid { gap: 2.5rem; }
    .sites-grid .site-item { width: 110px; }
}
@media (min-width: 1400px) {
    .sites-grid { gap: 3rem; }
    .sites-grid .site-item { width: 115px; }
}
</style>

<div class="container-fluid d-flex flex-column justify-content-center align-items-center min-vh-100 content py-5 px-3 px-md-5">
    
    <!-- Top Header Row -->
    <div class="row w-100 mb-5 mt-2 align-items-center justify-content-between px-md-4">
        
        <!-- Left: IP and OS Info -->
        <div class="col-md-3 text-start text-light text-shadow d-none d-md-block">
            <h5 class="fw-bold mb-1"><i class="bi bi-hdd-network text-success"></i> <?= htmlspecialchars($userIp) ?></h5>
            <p class="small text-light mb-0" style="opacity: 0.8;">
                <i class="bi bi-browser-chrome text-info"></i> <?= htmlspecialchars($clientInfo['browser']) ?> &nbsp;|&nbsp; 
                <i class="bi bi-display text-warning"></i> <?= htmlspecialchars($clientInfo['os']) ?>
            </p>
        </div>

        <!-- Center: Search Form -->
        <div class="col-md-6 text-center">
            <form action="https://duckduckgo.com/" method="GET" target="_blank" id="searchForm" class="d-flex justify-content-center">
                <div class="input-group" style="max-width: 600px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border-radius: 20px;">
                    <input type="text" name="q" class="form-control bg-dark text-light border-secondary form-control-lg px-4" placeholder="Пошук..." required autofocus style="border: none; border-top-left-radius: 20px; border-bottom-left-radius: 20px;">
                    
                    <button class="btn btn-dark border-start border-secondary dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="searchEngineBtn">
                        🦆
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="#" onclick="setEngine(event, 'duckduckgo', '🦆')">🦆 DuckDuckGo</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setEngine(event, 'google', '<i class=&quot;bi bi-google&quot;></i>')"><i class="bi bi-google"></i> Google</a></li>
                    </ul>

                    <button class="btn btn-primary px-4" type="submit" style="border-top-right-radius: 20px; border-bottom-right-radius: 20px;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <script>
                function setEngine(e, engine, iconHTML) {
                    e.preventDefault();
                    const form = document.getElementById('searchForm');
                    const btn = document.getElementById('searchEngineBtn');
                    btn.innerHTML = iconHTML;
                    if (engine === 'google') {
                        form.action = 'https://www.google.com/search';
                    } else {
                        form.action = 'https://duckduckgo.com/';
                    }
                }
            </script>
        </div>

        <!-- Right: Chat Widget -->
        <div class="col-md-3 text-end d-none d-md-block">
            <a href="<?= isLoggedIn() ? '/?module=chat' . $userQuery : '/?module=register' ?>" class="text-decoration-none">
                <div class="d-inline-block p-2 px-3 rounded-pill" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); transition: 0.2s;">
                    <div class="d-flex align-items-center text-light">
                        <i class="bi bi-chat-lock-fill fs-5 text-info me-2"></i>
                        <span class="small me-2">Секретний Чат</span>
                        <?php if (isLoggedIn()): ?>
                            <?php if (empty($chatUnread)): ?>
                                <span class="badge bg-secondary rounded-pill">0</span>
                            <?php else: ?>
                                <?php $total = array_sum(array_column($chatUnread, 'unread_count')); ?>
                                <span class="badge bg-danger rounded-pill shadow-sm heartbeat-animation"><?= $total ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php if (isLoggedIn() && !empty($chatUnread)): ?>
            <style>
                .heartbeat-animation { animation: heartbeat 2s infinite; }
                @keyframes heartbeat {
                    0% { transform: scale(1); }
                    10% { transform: scale(1.2); }
                    20% { transform: scale(1); }
                }
            </style>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sites Grid -->
    <div class="w-100 px-2 px-md-4">
        <div class="sites-grid w-100" id="sites-container">
            <?php foreach ($sites as $site): ?>
                <div class="d-flex flex-column align-items-center position-relative site-item" data-id="<?= $site['id'] ?>">
                    <a href="<?= htmlspecialchars($site['url']) ?>" class="d-block text-decoration-none text-light w-100">
                        <div class="link-box">
                            <?php if (isLoggedIn() && $site['user'] == $_SESSION['user_id']): ?>
                                <button type="button" class="delete-btn d-none edit-element" onclick="deleteSite(event, <?= $site['id'] ?>)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars($site['icon']) ?>" alt="<?= htmlspecialchars($site['name']) ?>">
                        </div>
                        <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Admin Panel: Add Site -->
    <?php if (isLoggedIn()): ?>
        <div class="mt-5 p-4 tool-box w-100 d-none edit-element" id="adminPanel" style="max-width: 500px;">
            <h4 class="mb-3" id="formTitle"><i class="bi bi-plus-circle"></i> Додати сайт</h4>
            <form id="addSiteForm">
                <input type="hidden" id="editSiteId" value="">
                <div class="mb-3">
                    <input type="text" id="addName" class="form-control" placeholder="Назва" required>
                </div>
                <div class="mb-3">
                    <input type="url" id="addUrl" class="form-control" placeholder="URL" required>
                </div>
                <div class="mb-3">
                    <input type="text" id="addIcon" class="form-control" placeholder="URL іконки (необов'язково)">
                    <div class="form-text text-secondary small">Якщо залишити порожнім, іконка підтягнеться автоматично.</div>
                </div>
                <button type="submit" id="formSubmitBtn" class="btn btn-success w-100">Додати сайт</button>
                <button type="button" id="cancelEditBtn" class="btn btn-secondary w-100 mt-2 d-none">Скасувати редагування</button>
            </form>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
<?php if (isLoggedIn()): ?>
// Initialize Sortable
const container = document.getElementById('sites-container');
let sortable;
if (container) {
    sortable = new Sortable(container, {
        animation: 150,
        disabled: true,
        ghostClass: 'opacity-50',
        onEnd: function (evt) {
            const itemEls = container.querySelectorAll('.site-item');
            const newOrder = Array.from(itemEls).map(el => el.getAttribute('data-id'));
            
            const formData = new FormData();
            formData.append('action', 'reorder');
            formData.append('order', JSON.stringify(newOrder));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) toastr.error(data.message || 'Помилка збереження порядку');
            })
            .catch(err => console.error(err));
        }
    });
}

// Edit Mode Toggle
document.getElementById('editModeToggle')?.addEventListener('change', function() {
    const isEdit = this.checked;
    document.querySelectorAll('.edit-element').forEach(el => {
        if (isEdit) el.classList.remove('d-none');
        else el.classList.add('d-none');
    });
    
    if (sortable) sortable.option('disabled', !isEdit);
    
    if (isEdit) document.body.classList.add('edit-mode-active');
    else document.body.classList.remove('edit-mode-active');
});

// Delete Site AJAX
function deleteSite(event, id) {
    event.preventDefault();
    if (!confirm('Ви впевнені, що хочете видалити цей сайт?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Сайт видалено!');
            document.querySelector(`.site-item[data-id="${id}"]`).remove();
        } else {
            toastr.error(data.message || 'Помилка видалення');
        }
    })
    .catch(err => console.error(err));
}

// Edit Mode click logic
document.getElementById('sites-container')?.addEventListener('click', function(e) {
    const editToggle = document.getElementById('editModeToggle');
    if (!editToggle || !editToggle.checked) return;

    // Find if clicked on a site link
    const link = e.target.closest('a');
    if (!link) return;
    
    // Prevent delete button from triggering edit
    if (e.target.closest('.delete-btn')) return;

    e.preventDefault();
    
    const siteItem = link.closest('.site-item');
    if (!siteItem) return;

    const id = siteItem.getAttribute('data-id');
    const url = link.getAttribute('href');
    const name = link.querySelector('.site-name').innerText;
    const icon = link.querySelector('img').getAttribute('src');

    // Populate form
    document.getElementById('editSiteId').value = id;
    document.getElementById('addName').value = name;
    document.getElementById('addUrl').value = url;
    document.getElementById('addIcon').value = icon;

    // Change form appearance
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Редагувати сайт';
    document.getElementById('formSubmitBtn').innerText = 'Зберегти зміни';
    document.getElementById('formSubmitBtn').classList.replace('btn-success', 'btn-primary');
    document.getElementById('cancelEditBtn').classList.remove('d-none');

    // Scroll to form
    document.getElementById('adminPanel').scrollIntoView({ behavior: 'smooth' });
});

// Cancel Edit
document.getElementById('cancelEditBtn')?.addEventListener('click', function() {
    document.getElementById('addSiteForm').reset();
    document.getElementById('editSiteId').value = '';
    
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Додати сайт';
    document.getElementById('formSubmitBtn').innerText = 'Додати сайт';
    document.getElementById('formSubmitBtn').classList.replace('btn-primary', 'btn-success');
    this.classList.add('d-none');
});

// Add / Edit Site AJAX
document.getElementById('addSiteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const id = document.getElementById('editSiteId').value;
    const action = id ? 'edit' : 'add';
    
    const formData = new FormData();
    formData.append('action', action);
    if (id) formData.append('id', id);
    formData.append('name', document.getElementById('addName').value);
    formData.append('url', document.getElementById('addUrl').value);
    formData.append('icon', document.getElementById('addIcon').value);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (action === 'add') {
                toastr.success('Сайт додано!');
                
                const container = document.getElementById('sites-container');
                const toggleElement = document.getElementById('editModeToggle');
                const isEditMode = toggleElement ? toggleElement.checked : false;
                const displayClass = isEditMode ? '' : 'd-none';
                
                const newSiteHTML = `
                    <div class="d-flex flex-column align-items-center position-relative site-item" data-id="${data.id}">
                        <a href="${data.url}" class="d-block text-decoration-none text-light w-100">
                            <div class="link-box">
                                <button type="button" class="delete-btn edit-element ${displayClass}" onclick="deleteSite(event, ${data.id})">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <img src="${data.icon}" alt="${data.name}">
                            </div>
                            <div class="site-name">${data.name}</div>
                        </a>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', newSiteHTML);
                document.getElementById('addSiteForm').reset();
            } else {
                toastr.success('Сайт оновлено!');
                const siteItem = document.querySelector(`.site-item[data-id="${data.id}"]`);
                if (siteItem) {
                    const link = siteItem.querySelector('a');
                    link.setAttribute('href', data.url);
                    link.querySelector('.site-name').innerText = data.name;
                    link.querySelector('img').setAttribute('src', data.icon);
                }
                document.getElementById('cancelEditBtn').click(); // Reset form
            }
        } else {
            toastr.error(data.message || 'Помилка збереження');
        }
    })
    .catch(err => console.error(err));
});
<?php endif; ?>
</script>
