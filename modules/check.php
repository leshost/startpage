<?php
require_once '../config/config.php';

$pageTitle = 'Перевірка пароля';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password === '') {
        $result = [
            'status' => 'error',
            'message' => 'Введіть пароль.'
        ];
    } else {
        // SHA1 пароля
        $hash = strtoupper(sha1($password));

        // Prefix і Suffix
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        $url = "https://api.pwnedpasswords.com/range/" . $prefix;

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: PasswordChecker\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $result = [
                'status' => 'error',
                'message' => 'Не вдалося підключитися до сервера HIBP.'
            ];
        } else {
            $found = false;
            $count = 0;

            foreach (explode("\n", $response) as $line) {
                $line = trim($line);
                if (!$line) continue;

                list($remoteSuffix, $remoteCount) = explode(':', $line);

                if (strtoupper($remoteSuffix) === $suffix) {
                    $found = true;
                    $count = (int)$remoteCount;
                    break;
                }
            }

            $result = [
                'status' => 'ok',
                'hash' => $hash,
                'prefix' => $prefix,
                'suffix' => $suffix,
                'found' => $found,
                'count' => $count
            ];
        }
    }
}
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 600px;">
        <h2 class="text-center mb-4"><i class="bi bi-shield-lock text-info"></i> Перевірка пароля (HIBP)</h2>
        <p class="text-secondary text-center small mb-4">Цей інструмент безпечно перевіряє пароль через базу витоків <a href="https://haveibeenpwned.com/" target="_blank" class="text-info">Have I Been Pwned</a>, використовуючи k-anonymity (передаються лише перші 5 символів хешу).</p>

        <form method="post" class="mb-4">
            <div class="input-group">
                <input type="password" name="password" class="form-control bg-dark text-light border-secondary" autocomplete="off" placeholder="Введіть пароль" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>" required>
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Перевірити</button>
            </div>
        </form>

        <?php if($result): ?>
            <hr class="border-secondary mb-4">
            
            <?php if($result['status'] == 'error'): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($result['message']) ?>
                </div>
            <?php else: ?>
                
                <div class="bg-dark p-3 rounded mb-4">
                    <p class="mb-1 text-secondary small">SHA1 Хеш:</p>
                    <code class="d-block text-break fs-6 text-light"><?= htmlspecialchars($result['hash']) ?></code>
                    <p class="mb-0 mt-2 text-secondary small">Передано на сервер HIBP (Префікс): <span class="text-light fw-bold"><?= htmlspecialchars($result['prefix']) ?></span></p>
                </div>

                <?php if($result['found']): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-shield-x fs-3 me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Пароль скомпрометовано!</h5>
                            <p class="mb-0">Він знайдений у базах витоків <strong><?= number_format($result['count'], 0, '.', ' ') ?></strong> разів. Негайно змініть його.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-shield-check fs-3 me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Пароль безпечний!</h5>
                            <p class="mb-0">Цей пароль відсутній у базі відомих витоків HIBP.</p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>