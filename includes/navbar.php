<?php
$userQuery = '';
if (isset($_GET['user'])) {
    $userQuery = '?user=' . urlencode($_GET['user']);
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm border-bottom border-secondary">
    <div class="container-fluid">
        <a class="navbar-brand text-success fw-bold" href="/<?= $userQuery ?>">
            <i class="bi bi-rocket-takeoff"></i> Startpage Tools
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['PHP_SELF'] == '/index.php' || $_SERVER['PHP_SELF'] == '/') ? 'active' : '' ?>" href="/<?= $userQuery ?>">
                        <i class="bi bi-house-door"></i> Головна
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['PHP_SELF'] == '/modules/finance.php') ? 'active' : '' ?>" href="/modules/finance.php<?= $userQuery ?>">
                        <i class="bi bi-cash-stack"></i> Калькулятор
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['PHP_SELF'] == '/modules/pass.php') ? 'active' : '' ?>" href="/modules/pass.php<?= $userQuery ?>">
                        <i class="bi bi-key"></i> Генератор паролів
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['PHP_SELF'] == '/modules/check.php') ? 'active' : '' ?>" href="/modules/check.php<?= $userQuery ?>">
                        <i class="bi bi-shield-check"></i> Перевірка паролів
                    </a>
                </li>
            </ul>
            <div class="d-flex">
                <?php if (isLoggedIn()): ?>
                    <a href="/modules/logout.php" class="btn btn-outline-danger btn-sm">Вийти</a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-gear"></i> Налаштування
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Modal Login -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Авторизація</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="loginForm" method="POST" action="/modules/login.php">
                    <div class="mb-3">
                        <input type="password" class="form-control" name="password" placeholder="Введіть пароль" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Увійти</button>
                </form>
            </div>
        </div>
    </div>
</div>
