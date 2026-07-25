<?php
if (!isLoggedIn() || empty($_SESSION['is_admin'])) {
    header("Location: /");
    exit;
}

// Обробка AJAX запитів
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    verifyCsrf();
    // Управління користувачами
    if ($_POST['action'] === 'toggle_block' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        if ($userId === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Не можна заблокувати самого себе']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT is_blocked FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $newStatus = $user['is_blocked'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
            if ($update->execute([$newStatus, $userId])) {
                echo json_encode(['success' => true, 'is_blocked' => $newStatus]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Помилка оновлення статусу']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Користувача не знайдено']);
        }
        exit;
    }

    // Управління спільними сайтами
    if ($_POST['action'] === 'add_shared_site' && isset($_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon, user) VALUES (?, ?, ?, NULL)");
        if ($stmt->execute([trim($_POST['name']), $url, trim($_POST['icon'])])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }

    if ($_POST['action'] === 'edit_shared_site' && isset($_POST['id'], $_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Некоректний URL']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE sites SET name = ?, url = ?, icon = ? WHERE id = ? AND user IS NULL");
        if ($stmt->execute([trim($_POST['name']), $url, trim($_POST['icon']), (int)$_POST['id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_shared_site' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ? AND user IS NULL");
        if ($stmt->execute([(int)$_POST['id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }
}

// Отримання даних для відображення
$usersStmt = $pdo->query("SELECT id, username, is_admin, is_blocked, created_at FROM users ORDER BY id ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$sitesStmt = $pdo->query("SELECT id, name, url, icon FROM sites WHERE user IS NULL ORDER BY `order` ASC, id DESC");
$sharedSites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Адмін Панель';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2><i class="bi bi-shield-lock text-info"></i> Панель Адміністратора</h2>
            <p class="text-secondary">Управління користувачами та спільними сайтами</p>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-light border-secondary" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab"><i class="bi bi-people"></i> Користувачі</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-light border-secondary" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites-pane" type="button" role="tab"><i class="bi bi-globe"></i> Спільні сайти</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">
        <!-- Користувачі -->
        <div class="tab-pane fade show active" id="users-pane" role="tabpanel" tabindex="0">
            <div class="tool-box">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логін</th>
                                <th>Роль</th>
                                <th>Дата реєстрації</th>
                                <th>Статус</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td>
                                        <?php if($u['is_admin']): ?>
                                            <span class="badge bg-primary">Адмін</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Користувач</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= $u['is_blocked'] ? 'bg-danger' : 'bg-success' ?>" id="status-<?= $u['id'] ?>">
                                            <?= $u['is_blocked'] ? 'Заблокований' : 'Активний' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($u['id'] !== $_SESSION['user_id'] && !$u['is_admin']): ?>
                                            <button class="btn btn-sm <?= $u['is_blocked'] ? 'btn-success' : 'btn-danger' ?>" onclick="toggleBlock(<?= $u['id'] ?>, this)">
                                                <?= $u['is_blocked'] ? '<i class="bi bi-unlock"></i> Розблокувати' : '<i class="bi bi-lock"></i> Заблокувати' ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Спільні сайти -->
        <div class="tab-pane fade" id="sites-pane" role="tabpanel" tabindex="0">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="tool-box">
                        <h5 class="mb-3" id="formTitle">Додати спільний сайт</h5>
                        <form id="sharedSiteForm">
                            <input type="hidden" id="editSiteId" value="">
                            <div class="mb-3">
                                <label class="form-label text-secondary small">Назва</label>
                                <input type="text" id="addName" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small">URL</label>
                                <input type="url" id="addUrl" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small">URL іконки</label>
                                <input type="text" id="addIcon" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <button type="submit" id="formSubmitBtn" class="btn btn-success w-100">Додати сайт</button>
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary w-100 mt-2 d-none" onclick="cancelEdit()">Скасувати редагування</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="tool-box">
                        <h5 class="mb-3">Список спільних сайтів</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0" id="sitesTable">
                                <thead>
                                    <tr>
                                        <th>Іконка</th>
                                        <th>Назва</th>
                                        <th>URL</th>
                                        <th>Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($sharedSites as $s): ?>
                                        <tr data-id="<?= $s['id'] ?>">
                                            <td><img src="<?= htmlspecialchars($s['icon']) ?>" alt="icon" style="width:24px; height:24px; border-radius:4px;"></td>
                                            <td class="site-name"><?= htmlspecialchars($s['name']) ?></td>
                                            <td class="site-url"><a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" class="text-info"><?= htmlspecialchars($s['url']) ?></a></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editSite(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= htmlspecialchars(addslashes($s['url'])) ?>', '<?= htmlspecialchars(addslashes($s['icon'])) ?>')"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSharedSite(<?= $s['id'] ?>)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Управління користувачами
function toggleBlock(userId, btn) {
    const formData = new FormData();
    formData.append('action', 'toggle_block');
    formData.append('user_id', userId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            toastr.success('Статус оновлено');
            const badge = document.getElementById('status-' + userId);
            if(data.is_blocked) {
                badge.className = 'badge bg-danger';
                badge.innerText = 'Заблокований';
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="bi bi-unlock"></i> Розблокувати';
            } else {
                badge.className = 'badge bg-success';
                badge.innerText = 'Активний';
                btn.className = 'btn btn-sm btn-danger';
                btn.innerHTML = '<i class="bi bi-lock"></i> Заблокувати';
            }
        } else {
            toastr.error(data.message || 'Помилка');
        }
    })
    .catch(err => console.error(err));
}

// Управління сайтами
function editSite(id, name, url, icon) {
    document.getElementById('editSiteId').value = id;
    document.getElementById('addName').value = name;
    document.getElementById('addUrl').value = url;
    document.getElementById('addIcon').value = icon;
    
    document.getElementById('formTitle').innerText = 'Редагувати сайт';
    document.getElementById('formSubmitBtn').innerText = 'Зберегти зміни';
    document.getElementById('formSubmitBtn').className = 'btn btn-primary w-100';
    document.getElementById('cancelEditBtn').classList.remove('d-none');
}

function cancelEdit() {
    document.getElementById('sharedSiteForm').reset();
    document.getElementById('editSiteId').value = '';
    
    document.getElementById('formTitle').innerText = 'Додати спільний сайт';
    document.getElementById('formSubmitBtn').innerText = 'Додати сайт';
    document.getElementById('formSubmitBtn').className = 'btn btn-success w-100';
    document.getElementById('cancelEditBtn').classList.add('d-none');
}

document.getElementById('sharedSiteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const id = document.getElementById('editSiteId').value;
    const action = id ? 'edit_shared_site' : 'add_shared_site';
    
    const formData = new FormData();
    formData.append('action', action);
    if(id) formData.append('id', id);
    formData.append('name', document.getElementById('addName').value);
    formData.append('url', document.getElementById('addUrl').value);
    formData.append('icon', document.getElementById('addIcon').value);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            toastr.success('Успішно збережено! Сторінка оновиться.');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            toastr.error(data.message || 'Помилка');
        }
    })
    .catch(err => console.error(err));
});

function deleteSharedSite(id) {
    if(!confirm('Ви впевнені, що хочете видалити цей спільний сайт?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_shared_site');
    formData.append('id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            toastr.success('Видалено успішно!');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        } else {
            toastr.error(data.message || 'Помилка видалення');
        }
    })
    .catch(err => console.error(err));
}
</script>
