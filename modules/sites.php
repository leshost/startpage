<?php
// Автоматичне створення таблиць бази даних при першому запуску
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `secret_key` VARCHAR(64) NOT NULL UNIQUE,
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
    ");
} catch (PDOException $e) {
    die("Помилка автоматичного створення таблиць: " . $e->getMessage());
}

// AJAX Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Несанкціонований доступ']);
        exit();
    }
    
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
    if ($_POST['action'] === 'add' && isset($_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon, user) VALUES (:name, :url, :icon, :user)");
        if($stmt->execute([
            'name' => trim($_POST['name']),
            'url' => $url,
            'icon' => trim($_POST['icon']),
            'user' => $_SESSION['user_id']
        ])) {
            echo json_encode([
                'success' => true, 
                'id' => $pdo->lastInsertId(),
                'name' => htmlspecialchars(trim($_POST['name'])),
                'url' => htmlspecialchars($url),
                'icon' => htmlspecialchars(trim($_POST['icon']))
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit();
    }
    
    // Edit Site
    if ($_POST['action'] === 'edit' && isset($_POST['id'], $_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE sites SET name = :name, url = :url, icon = :icon WHERE id = :id AND user = :user_id");
        if($stmt->execute([
            'name' => trim($_POST['name']),
            'url' => $url,
            'icon' => trim($_POST['icon']),
            'id' => (int)$_POST['id'],
            'user_id' => $_SESSION['user_id']
        ])) {
            echo json_encode([
                'success' => true,
                'id' => (int)$_POST['id'],
                'name' => htmlspecialchars(trim($_POST['name'])),
                'url' => htmlspecialchars($url),
                'icon' => htmlspecialchars(trim($_POST['icon']))
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
            $stmt = $pdo->prepare("UPDATE sites SET `order` = :order WHERE id = :id");
            foreach ($orderArray as $index => $id) {
                $stmt->execute(['order' => $index, 'id' => (int)$id]);
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
} else {
    $stmt = $pdo->query("SELECT `id`, `name`, `url`, `icon`, `user` FROM `sites` WHERE `user` IS NULL ORDER BY `order`");
}
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userIp = getUserIP();
$clientInfo = getClientInfo();

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
</style>

<div class="container d-flex flex-column justify-content-center align-items-center min-vh-100 content py-5">
    
    <!-- IP and OS Info -->
    <div class="text-center text-light text-shadow mb-4 mt-2">
        <h1 class="display-5 fw-bold mb-2"><?= htmlspecialchars($userIp) ?></h1>
        <p class="fs-5 text-light mb-1">
            <i class="bi bi-browser-chrome text-info"></i> <?= htmlspecialchars($clientInfo['browser']) ?> &nbsp;|&nbsp; 
            <i class="bi bi-display text-warning"></i> <?= htmlspecialchars($clientInfo['os']) ?>
        </p>
        <p class="text-secondary small font-monospace mt-2 mx-auto" style="max-width: 600px;"><?= htmlspecialchars($clientInfo['raw']) ?></p>
    </div>

    <!-- Sites Grid -->
    <div class="container-lg">
        <div class="row justify-content-center gap-4" id="sites-container">
            <?php foreach ($sites as $site): ?>
                <div class="col-lg-1 col-md-3 col-sm-4 col-4 d-flex flex-column align-items-center position-relative site-item" data-id="<?= $site['id'] ?>">
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
                    <input type="text" id="addIcon" class="form-control" placeholder="URL іконки" required>
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
                    <div class="col-lg-1 col-md-3 col-sm-4 col-4 d-flex flex-column align-items-center position-relative site-item" data-id="${data.id}">
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
