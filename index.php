<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// AJAX Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Несанкціонований доступ']);
        exit();
    }
    
    // Delete Site
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM sites WHERE id = :id");
        if($stmt->execute(['id' => (int)$_POST['id']])) {
            echo json_encode(['success' => true]);
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


}

// Fetch Sites
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT `id`, `name`, `url`, `icon` FROM `sites` WHERE `user` IS NULL OR `user` = ? ORDER BY `order`");
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt = $pdo->query("SELECT `id`, `name`, `url`, `icon` FROM `sites` WHERE `user` IS NULL ORDER BY `order`");
}
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);



$userIp = getUserIP();
$clientInfo = getClientInfo();

$pageTitle = 'Головна';
$bodyClass = 'startpage-body';

require_once 'includes/header.php';
?>

<div class="startpage-overlay"></div>

<?php require_once 'includes/navbar.php'; ?>

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
                            <?php if (isLoggedIn()): ?>
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
            <h4 class="mb-3"><i class="bi bi-plus-circle"></i> Додати сайт</h4>
            <form id="addSiteForm">
                <div class="mb-3">
                    <input type="text" id="addName" class="form-control" placeholder="Назва" required>
                </div>
                <div class="mb-3">
                    <input type="url" id="addUrl" class="form-control" placeholder="URL" required>
                </div>
                <div class="mb-3">
                    <input type="text" id="addIcon" class="form-control" placeholder="URL іконки" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Додати сайт</button>
            </form>
        </div>
    <?php endif; ?>

</div>

<script>
<?php if (isLoggedIn()): ?>
// Edit Mode Toggle
document.getElementById('editModeToggle')?.addEventListener('change', function() {
    const isEdit = this.checked;
    document.querySelectorAll('.edit-element').forEach(el => {
        if (isEdit) el.classList.remove('d-none');
        else el.classList.add('d-none');
    });
});

// Delete Site AJAX
function deleteSite(event, id) {
    event.preventDefault(); // Prevent navigating to the URL
    if (!confirm('Ви впевнені, що хочете видалити цей сайт?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Сайт видалено!');
            // Remove element from DOM
            document.querySelector(`.site-item[data-id="${id}"]`).remove();
        } else {
            toastr.error(data.message || 'Помилка видалення');
        }
    })
    .catch(handleAjaxError);
}

// Add Site AJAX
document.getElementById('addSiteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('name', document.getElementById('addName').value);
    formData.append('url', document.getElementById('addUrl').value);
    formData.append('icon', document.getElementById('addIcon').value);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Сайт додано!');
            
            // Dynamically append new site to grid
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
            
            // Clear form
            document.getElementById('addSiteForm').reset();
        } else {
            toastr.error(data.message || 'Помилка додавання');
        }
    })
    .catch(handleAjaxError);
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
