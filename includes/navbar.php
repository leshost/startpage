<?php
$current_module = $_GET['module'] ?? 'sites';
$current_module = $_GET['module'] ?? 'sites';

$unreadTotal = 0;
if (isLoggedIn() && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadTotal = $stmt->fetchColumn();
    } catch (PDOException $e) {}
}
?>
<nav class="navbar navbar-expand-lg navbar-dark startpage-navbar">
    <div class="container-fluid">
        <a class="navbar-brand text-light fw-bold" href="/?module=sites"><i class="bi bi-box"></i> <?= __('nav_brand') ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Головна -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module == 'sites') ? 'active' : '' ?>" href="/?module=sites">
                        <i class="bi bi-house-door"></i> <?= __('nav_home') ?>
                    </a>
                </li>
                
                <!-- Фінанси -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['kanban', 'finance', 'notes']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-briefcase"></i> <?= __('nav_productivity') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'kanban') ? 'active' : '' ?>" href="/?module=kanban">
                                <i class="bi bi-kanban"></i> <?= __('nav_kanban') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'finance') ? 'active' : '' ?>" href="/?module=finance">
                                <i class="bi bi-cash-stack"></i> <?= __('nav_finance') ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'notes') ? 'active' : '' ?>" href="/?module=notes">
                                <i class="bi bi-journal-lock"></i> <?= __('nav_notes') ?>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Паролі -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['pass', 'check', 'secret']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-shield-lock"></i> <?= __('nav_security') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'pass') ? 'active' : '' ?>" href="/?module=pass">
                                <i class="bi bi-key"></i> <?= __('nav_pass_gen') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'check') ? 'active' : '' ?>" href="/?module=check">
                                <i class="bi bi-shield-check"></i> <?= __('nav_pass_check') ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'secret') ? 'active' : '' ?>" href="/?module=secret">
                                <i class="bi bi-envelope-x"></i> <?= __('nav_secret_msg') ?>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Інструменти -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['qr', 'json', 'dev-tools']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-tools"></i> <?= __('nav_tools') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'qr') ? 'active' : '' ?>" href="/?module=qr">
                                <i class="bi bi-qr-code"></i> <?= __('nav_qr') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'json') ? 'active' : '' ?>" href="/?module=json">
                                <i class="bi bi-braces"></i> <?= __('nav_json') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'dev-tools') ? 'active' : '' ?>" href="/?module=dev-tools">
                                <i class="bi bi-wrench-adjustable"></i> <?= __('nav_dev_tools') ?>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Language Switcher -->
                <li class="nav-item dropdown me-2">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe"></i> <?= strtoupper(CURRENT_LANG) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                        <li><a class="dropdown-item <?= CURRENT_LANG === 'ua' ? 'active' : '' ?>" href="?language=ua">UA</a></li>
                        <li><a class="dropdown-item <?= CURRENT_LANG === 'en' ? 'active' : '' ?>" href="?language=en">EN</a></li>
                    </ul>
                </li>
                <?php if(isLoggedIn()): ?>
                    <?php if($current_module == 'sites'): ?>
                        <li class="nav-item me-3">
                            <div class="form-check form-switch m-0 pt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="editModeToggle">
                                <label class="form-check-label text-secondary small" for="editModeToggle"><i class="bi bi-pencil-square"></i> <?= __('nav_edit_mode') ?></label>
                            </div>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center me-3 ms-3">
                        <button class="btn btn-sm btn-outline-info me-3" onclick="copySecretUrl('<?= htmlspecialchars($_SESSION['secret_key']) ?>')" title="Копіювати секретний URL">
                            <i class="bi bi-link-45deg"></i> <?= __('nav_my_url') ?>
                        </button>
                        <a href="/?module=chat" class="text-secondary text-decoration-none d-flex align-items-center position-relative">
                            <i class="bi bi-person-circle fs-5 me-2"></i>
                            <span><?= htmlspecialchars($_SESSION['username'] ?? 'Користувач') ?></span>
                            <?php if ($unreadTotal > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?= $unreadTotal ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if(!empty($_SESSION['is_admin'])): ?>
                        <li class="nav-item me-2">
                            <a class="nav-link <?= ($current_module == 'admin') ? 'active' : '' ?>" href="/?module=admin"><i class="bi bi-shield-lock"></i> <?= __('nav_admin') ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/?module=logout"><i class="bi bi-box-arrow-right"></i> <?= __('nav_logout') ?></a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module == 'login') ? 'active' : '' ?>" href="/?module=login"><i class="bi bi-box-arrow-in-right"></i> <?= __('nav_login') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module == 'register') ? 'active' : '' ?>" href="/?module=register"><i class="bi bi-person-plus"></i> <?= __('nav_register') ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
function copySecretUrl(secret) {
    const url = window.location.origin + '/?module=sites&user=' + secret;
    navigator.clipboard.writeText(url).then(() => {
        if (typeof toastr !== 'undefined') toastr.success('Секретний URL скопійовано!');
        else alert('Скопійовано: ' + url);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>

<?php if (isLoggedIn() && isset($pdo) && $current_module !== 'chat'): ?>
    <?php
    $showReminder = false;
    if (!isset($_SESSION['is_key_saved'])) {
        try {
            $stmt = $pdo->prepare("SELECT public_key, is_key_saved FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow && !empty($uRow['public_key'])) {
                $_SESSION['is_key_saved'] = (bool)$uRow['is_key_saved'];
            } else {
                $_SESSION['is_key_saved'] = true; // No keys generated, nothing to save
            }
        } catch (Exception $e) {}
    }
    if (isset($_SESSION['is_key_saved']) && !$_SESSION['is_key_saved']) {
        $showReminder = true;
    }
    ?>
    <?php if ($showReminder): ?>
        <div id="keyReminderBanner" class="alert alert-danger rounded-0 mb-0 border-0 text-center shadow">
            <strong><i class="bi bi-exclamation-triangle-fill fs-5"></i> <?= __('warn_critical') ?></strong> 
            <?= __('warn_key_not_saved') ?> 
            <a href="/?module=chat" class="btn btn-sm btn-danger ms-3 text-uppercase fw-bold"><i class="bi bi-shield-lock"></i> <?= __('btn_go_chat_save_key') ?></a>
        </div>
    <?php endif; ?>
<?php endif; ?>
