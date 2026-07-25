<?php
$current_module = $_GET['module'] ?? 'sites';
$userQuery = (isLoggedIn() && isset($_SESSION['secret_key'])) ? '&user=' . urlencode($_SESSION['secret_key']) : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark startpage-navbar">
    <div class="container-fluid">
        <a class="navbar-brand text-light fw-bold" href="/?module=sites<?= $userQuery ?>"><i class="bi bi-box"></i> Стартова</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Головна -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module == 'sites') ? 'active' : '' ?>" href="/?module=sites<?= $userQuery ?>">
                        <i class="bi bi-house-door"></i> Головна
                    </a>
                </li>
                
                <!-- Фінанси -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['kanban', 'finance']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-briefcase"></i> Продуктивність
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'kanban') ? 'active' : '' ?>" href="/?module=kanban<?= $userQuery ?>">
                                <i class="bi bi-kanban"></i> Канбан-дошка
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'finance') ? 'active' : '' ?>" href="/?module=finance<?= $userQuery ?>">
                                <i class="bi bi-cash-stack"></i> Калькулятор фінансів
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Паролі -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['pass', 'check']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-shield-lock"></i> Безпека
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'pass') ? 'active' : '' ?>" href="/?module=pass<?= $userQuery ?>">
                                <i class="bi bi-key"></i> Генератор паролів
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'check') ? 'active' : '' ?>" href="/?module=check<?= $userQuery ?>">
                                <i class="bi bi-shield-check"></i> Перевірка паролів
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Інструменти -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_module, ['qr', 'json', 'dev-tools']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-tools"></i> Інструменти
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'qr') ? 'active' : '' ?>" href="/?module=qr<?= $userQuery ?>">
                                <i class="bi bi-qr-code"></i> Генератор QR
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'json') ? 'active' : '' ?>" href="/?module=json<?= $userQuery ?>">
                                <i class="bi bi-braces"></i> JSON Форматер
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_module == 'dev-tools') ? 'active' : '' ?>" href="/?module=dev-tools<?= $userQuery ?>">
                                <i class="bi bi-wrench-adjustable"></i> Конвертер / Хеші
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if(isLoggedIn()): ?>
                    <?php if($current_module == 'sites'): ?>
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
                        <a class="nav-link text-danger" href="/?module=logout"><i class="bi bi-box-arrow-right"></i> Вихід</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module == 'login') ? 'active' : '' ?>" href="/?module=login"><i class="bi bi-box-arrow-in-right"></i> Вхід</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module == 'register') ? 'active' : '' ?>" href="/?module=register"><i class="bi bi-person-plus"></i> Реєстрація</a>
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
