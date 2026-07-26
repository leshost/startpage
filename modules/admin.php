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
            echo json_encode(['success' => false, 'message' => __('err_block_self')]);
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
                echo json_encode(['success' => false, 'message' => __('err_status_update')]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => __('err_user_not_found')]);
        }
        exit;
    }

    // Управління спільними сайтами
    if ($_POST['action'] === 'add_shared_site' && isset($_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => __('err_invalid_url')]);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO sites (name, url, icon, user) VALUES (?, ?, ?, NULL)");
        if ($stmt->execute([trim($_POST['name']), $url, trim($_POST['icon'])])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => __('err_db')]);
        }
        exit;
    }

    if ($_POST['action'] === 'edit_shared_site' && isset($_POST['id'], $_POST['name'], $_POST['url'], $_POST['icon'])) {
        $url = trim($_POST['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => __('err_invalid_url')]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE sites SET name = ?, url = ?, icon = ? WHERE id = ? AND user IS NULL");
        if ($stmt->execute([trim($_POST['name']), $url, trim($_POST['icon']), (int)$_POST['id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => __('err_db')]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_shared_site' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ? AND user IS NULL");
        if ($stmt->execute([(int)$_POST['id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => __('err_db')]);
        }
        exit;
    }

    // ── Запрошення ──────────────────────────────────────────────────────────
    if ($_POST['action'] === 'generate_invite') {
        if (OPEN_REGISTRATION) {
            echo json_encode(['success' => false, 'message' => __('err_reg_open_no_invites')]);
            exit;
        }
        // Формат: xxxx-xxxx-xxxx-xxxx (16 байт = 32 hex символи)
        $raw  = bin2hex(random_bytes(8));
        $code = implode('-', str_split($raw, 4));
        $stmt = $pdo->prepare("INSERT INTO invite_codes (code, created_by) VALUES (?, ?)");
        if ($stmt->execute([$code, $_SESSION['user_id']])) {
            echo json_encode(['success' => true, 'code' => $code, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => __('err_generation')]);
        }
        exit;
    }

    if ($_POST['action'] === 'revoke_invite' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM invite_codes WHERE id = ? AND used_by IS NULL");
        $stmt->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Отримання даних для відображення
$usersStmt = $pdo->query("SELECT id, username, is_admin, is_blocked, created_at FROM users ORDER BY id ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$sitesStmt = $pdo->query("SELECT id, name, url, icon FROM sites WHERE user IS NULL ORDER BY `order` ASC, id DESC");
$sharedSites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

// Запрошення — завантажуємо тільки якщо реєстрація закрита
$inviteCodes = [];
if (!OPEN_REGISTRATION) {
    try {
        $stmt = $pdo->prepare("
            SELECT ic.id, ic.code, ic.created_at,
                   ub.username AS used_by_name, ic.used_at
            FROM invite_codes ic
            LEFT JOIN users ub ON ic.used_by = ub.id
            ORDER BY ic.created_at DESC
        ");
        $stmt->execute();
        $inviteCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$pageTitle = 'Адмін Панель';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2><i class="bi bi-shield-lock text-info"></i> <?= __('admin_panel_title') ?></h2>
            <p class="text-secondary"><?= __('admin_panel_desc') ?></p>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-light border-secondary" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab"><i class="bi bi-people"></i> <?= __('tab_users') ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-light border-secondary" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites-pane" type="button" role="tab"><i class="bi bi-globe"></i> <?= __('tab_shared_sites') ?></button>
        </li>
        <?php if (!OPEN_REGISTRATION): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-light border-secondary" id="invites-tab" data-bs-toggle="tab" data-bs-target="#invites-pane" type="button" role="tab">
                <i class="bi bi-ticket-perforated text-warning"></i> <?= __('tab_invites') ?>
                <?php $unused = count(array_filter($inviteCodes, fn($c) => !$c['used_by_name'])); ?>
                <?php if ($unused > 0): ?><span class="badge bg-warning text-dark ms-1"><?= $unused ?></span><?php endif; ?>
            </button>
        </li>
        <?php endif; ?>
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
                                <th><?= __('th_login') ?></th>
                                <th><?= __('th_role') ?></th>
                                <th><?= __('th_reg_date') ?></th>
                                <th><?= __('th_status') ?></th>
                                <th><?= __('th_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td>
                                        <?php if($u['is_admin']): ?>
                                            <span class="badge bg-primary"><?= __('role_admin') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= __('role_user') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= $u['is_blocked'] ? 'bg-danger' : 'bg-success' ?>" id="status-<?= $u['id'] ?>">
                                            <?= $u['is_blocked'] ? __('status_blocked') : __('status_active') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($u['id'] !== $_SESSION['user_id'] && !$u['is_admin']): ?>
                                            <button class="btn btn-sm <?= $u['is_blocked'] ? 'btn-success' : 'btn-danger' ?>" onclick="toggleBlock(<?= $u['id'] ?>, this)">
                                                <?= $u['is_blocked'] ? '<i class="bi bi-unlock"></i> ' . __('btn_unblock') : '<i class="bi bi-lock"></i> ' . __('btn_block') ?>
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
                        <h5 class="mb-3" id="formTitle"><?= __('add_shared_site_title') ?></h5>
                        <form id="sharedSiteForm">
                            <input type="hidden" id="editSiteId" value="">
                            <div class="mb-3">
                                <label class="form-label text-secondary small"><?= __('label_name') ?></label>
                                <input type="text" id="addName" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small"><?= __('label_url') ?></label>
                                <input type="url" id="addUrl" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small"><?= __('label_icon_url') ?></label>
                                <input type="text" id="addIcon" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <button type="submit" id="formSubmitBtn" class="btn btn-success w-100"><?= __('btn_add_site') ?></button>
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary w-100 mt-2 d-none" onclick="cancelEdit()"><?= __('btn_cancel_edit') ?></button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="tool-box">
                        <h5 class="mb-3"><?= __('list_shared_sites') ?></h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0" id="sitesTable">
                                <thead>
                                    <tr>
                                        <th><?= __('th_icon') ?></th>
                                        <th><?= __('th_name') ?></th>
                                        <th><?= __('th_url') ?></th>
                                        <th><?= __('th_actions') ?></th>
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

        <?php if (!OPEN_REGISTRATION): ?>
        <!-- Запрошення -->
        <div class="tab-pane fade" id="invites-pane" role="tabpanel" tabindex="0">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="tool-box">
                        <h5 class="mb-3"><i class="bi bi-ticket-perforated text-warning"></i> <?= __('generate_code_title') ?></h5>
                        <p class="text-secondary small"><?= __('generate_code_desc') ?></p>
                        <button id="generateInviteBtn" class="btn btn-warning w-100">
                            <i class="bi bi-plus-circle"></i> <?= __('btn_generate_code') ?>
                        </button>
                        <div id="newInviteBox" class="mt-3 d-none">
                            <label class="form-label text-secondary small"><?= __('label_new_code') ?></label>
                            <div class="input-group">
                                <input type="text" id="newInviteCode" class="form-control bg-dark text-warning border-secondary font-monospace" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyInvite()" title="<?= __('title_copy') ?>"><i class="bi bi-clipboard"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="tool-box">
                        <h5 class="mb-3"><?= __('list_invite_codes') ?></h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0" id="inviteTable">
                                <thead>
                                    <tr>
                                        <th><?= __('th_code') ?></th>
                                        <th><?= __('th_created') ?></th>
                                        <th><?= __('th_used') ?></th>
                                        <th><?= __('th_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inviteCodes as $inv): ?>
                                    <tr id="invite-row-<?= $inv['id'] ?>">
                                        <td><code class="text-warning"><?= htmlspecialchars($inv['code']) ?></code></td>
                                        <td><small class="text-secondary"><?= date('d.m.Y H:i', strtotime($inv['created_at'])) ?></small></td>
                                        <td>
                                            <?php if ($inv['used_by_name']): ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($inv['used_by_name']) ?></span>
                                                <small class="text-secondary ms-1"><?= date('d.m.Y', strtotime($inv['used_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= __('status_not_used') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$inv['used_by_name']): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="revokeInvite(<?= $inv['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($inviteCodes)): ?>
                                    <tr id="emptyRow"><td colspan="4" class="text-center text-secondary py-3"><?= __('msg_no_codes') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
            toastr.success('<?= __('msg_status_updated') ?>');
            const badge = document.getElementById('status-' + userId);
            if(data.is_blocked) {
                badge.className = 'badge bg-danger';
                badge.innerText = '<?= __('status_blocked') ?>';
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="bi bi-unlock"></i> <?= __('btn_unblock') ?>';
            } else {
                badge.className = 'badge bg-success';
                badge.innerText = '<?= __('status_active') ?>';
                btn.className = 'btn btn-sm btn-danger';
                btn.innerHTML = '<i class="bi bi-lock"></i> <?= __('btn_block') ?>';
            }
        } else {
            toastr.error(data.message || '<?= __('err_general') ?>');
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
    
    document.getElementById('formTitle').innerText = '<?= __('btn_edit_site') ?>';
    document.getElementById('formSubmitBtn').innerText = '<?= __('btn_save_changes') ?>';
    document.getElementById('formSubmitBtn').className = 'btn btn-primary w-100';
    document.getElementById('cancelEditBtn').classList.remove('d-none');
}

function cancelEdit() {
    document.getElementById('sharedSiteForm').reset();
    document.getElementById('editSiteId').value = '';
    
    document.getElementById('formTitle').innerText = '<?= __('add_shared_site_title') ?>';
    document.getElementById('formSubmitBtn').innerText = '<?= __('btn_add_site') ?>';
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
            toastr.success('<?= __('msg_saved_reload') ?>');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            toastr.error(data.message || '<?= __('err_general') ?>');
        }
    })
    .catch(err => console.error(err));
});

function deleteSharedSite(id) {
    if(!confirm('<?= __('msg_confirm_delete_shared') ?>')) return;
    
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
            toastr.success('<?= __('msg_deleted_success') ?>');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        } else {
            toastr.error(data.message || '<?= __('err_delete') ?>');
        }
    })
    .catch(err => console.error(err));
}

// ── Запрошення ────────────────────────────────────────────────────────────────
document.getElementById('generateInviteBtn')?.addEventListener('click', function() {
    this.disabled = true;
    const fd = new FormData();
    fd.append('action', 'generate_invite');
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            this.disabled = false;
            if (!d.success) { toastr.error(d.message || '<?= __('err_general') ?>'); return; }

            // Показуємо новий код
            document.getElementById('newInviteBox').classList.remove('d-none');
            document.getElementById('newInviteCode').value = d.code;

            // Додаємо рядок у таблицю
            const tbody = document.querySelector('#inviteTable tbody');
            document.getElementById('emptyRow')?.remove();
            const now = new Date();
            const dateStr = now.toLocaleDateString('uk-UA', {day:'2-digit',month:'2-digit',year:'numeric'}) +
                            ' ' + now.toLocaleTimeString('uk-UA', {hour:'2-digit',minute:'2-digit'});
            const tr = document.createElement('tr');
            tr.id = 'invite-row-' + d.id;
            tr.innerHTML = `
                <td><code class="text-warning">${d.code}</code></td>
                <td><small class="text-secondary">${dateStr}</small></td>
                <td><span class="badge bg-secondary"><?= __('status_not_used') ?></span></td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="revokeInvite(${d.id})"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.insertAdjacentElement('afterbegin', tr);
            toastr.success('<?= __('msg_code_generated') ?>');
        })
        .catch(() => { this.disabled = false; toastr.error('<?= __('err_network') ?>'); });
});

function copyInvite() {
    const code = document.getElementById('newInviteCode').value;
    navigator.clipboard.writeText(code).then(() => toastr.info('<?= __('msg_code_copied') ?>'));
}

function revokeInvite(id) {
    if (!confirm('<?= __('msg_confirm_revoke_invite') ?>')) return;
    const fd = new FormData();
    fd.append('action', 'revoke_invite');
    fd.append('id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('invite-row-' + id)?.remove();
                toastr.success('<?= __('msg_invite_revoked') ?>');
            } else {
                toastr.error('<?= __('err_general') ?>');
            }
        });
}
</script>
