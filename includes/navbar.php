<?php
$current_page = $_SERVER['PHP_SELF'];
$userQuery = (isLoggedIn() && isset($_SESSION['secret_key'])) ? '?user=' . urlencode($_SESSION['secret_key']) : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark startpage-navbar">
    <div class="container-fluid">
        <a class="navbar-brand text-light fw-bold" href="/index.php<?= $userQuery ?>"><i class="bi bi-box"></i> Стартова</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Головна -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == '/index.php' || $current_page == '/') ? 'active' : '' ?>" href="/<?= $userQuery ?>">
                        <i class="bi bi-house-door"></i> Головна
                    </a>
                </li>
                
                <!-- Фінанси -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['/modules/kanban.php', '/modules/finance.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-briefcase"></i> Продуктивність
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/kanban.php') ? 'active' : '' ?>" href="/modules/kanban.php<?= $userQuery ?>">
                                <i class="bi bi-kanban"></i> Канбан-дошка
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/finance.php') ? 'active' : '' ?>" href="/modules/finance.php<?= $userQuery ?>">
                                <i class="bi bi-cash-stack"></i> Калькулятор фінансів
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Паролі -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['/modules/pass.php', '/modules/check.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-shield-lock"></i> Безпека
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/pass.php') ? 'active' : '' ?>" href="/modules/pass.php<?= $userQuery ?>">
                                <i class="bi bi-key"></i> Генератор паролів
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/check.php') ? 'active' : '' ?>" href="/modules/check.php<?= $userQuery ?>">
                                <i class="bi bi-shield-check"></i> Перевірка паролів
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Інструменти -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['/modules/qr.php', '/modules/json.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-tools"></i> Інструменти
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/qr.php') ? 'active' : '' ?>" href="/modules/qr.php<?= $userQuery ?>">
                                <i class="bi bi-qr-code"></i> Генератор QR
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/json.php') ? 'active' : '' ?>" href="/modules/json.php<?= $userQuery ?>">
                                <i class="bi bi-braces"></i> JSON Форматер
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_page == '/modules/dev-tools.php') ? 'active' : '' ?>" href="/modules/dev-tools.php<?= $userQuery ?>">
                                <i class="bi bi-wrench-adjustable"></i> Конвертер / Хеші
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if(isLoggedIn()): ?>
                    <?php if($current_page == '/index.php' || $current_page == '/'): ?>
                        <li class="nav-item me-3">
                            <div class="form-check form-switch m-0 pt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="editModeToggle">
                                <label class="form-check-label text-secondary small" for="editModeToggle"><i class="bi bi-pencil-square"></i> Редагування</label>
                            </div>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center me-3">
                        <button class="btn btn-sm btn-outline-info me-2" onclick="copySecretUrl('<?= htmlspecialchars($_SESSION['secret_key']) ?>')" title="Копіювати секретний URL">
                            <i class="bi bi-link-45deg"></i> Мій URL
                        </button>
                        <span class="text-secondary"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Користувач') ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/modules/logout.php"><i class="bi bi-box-arrow-right"></i> Вихід</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/login.php"><i class="bi bi-box-arrow-in-right"></i> Вхід</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/register.php"><i class="bi bi-person-plus"></i> Реєстрація</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
function copySecretUrl(secret) {
    const url = window.location.origin + '/?user=' + secret;
    navigator.clipboard.writeText(url).then(() => {
        if (typeof toastr !== 'undefined') toastr.success('Секретний URL скопійовано!');
        else alert('Скопійовано: ' + url);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>
