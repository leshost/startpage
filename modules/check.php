<?php
if (!isLoggedIn()) {
    header("Location: /?module=login");
    exit;
}
$pageTitle = 'Перевірка пароля';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 600px;">
        <h2 class="text-center mb-4"><i class="bi bi-shield-lock text-info"></i> Перевірка пароля (HIBP)</h2>
        <p class="text-secondary text-center small mb-4">Цей інструмент безпечно перевіряє пароль через базу витоків <a href="https://haveibeenpwned.com/" target="_blank" class="text-info">Have I Been Pwned</a>, використовуючи k-anonymity (передаються лише перші 5 символів хешу).</p>

        <form id="checkForm" class="mb-4">
            <div class="input-group">
                <input type="password" id="passwordInput" class="form-control bg-dark text-light border-secondary" autocomplete="off" placeholder="Введіть пароль" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                <button type="submit" class="btn btn-primary" id="submitBtn"><i class="bi bi-search"></i> Перевірити</button>
            </div>
        </form>

        <div id="loading" class="text-center d-none my-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Перевірка...</span>
            </div>
            <p class="text-secondary mt-2 small">Звернення до бази HIBP...</p>
        </div>

        <div id="resultsContainer" class="d-none">
            <hr class="border-secondary mb-4">
            
            <div class="bg-dark p-3 rounded mb-4">
                <p class="mb-1 text-secondary small">SHA1 Хеш:</p>
                <code id="resHash" class="d-block text-break fs-6 text-light"></code>
                <p class="mb-0 mt-2 text-secondary small">Передано на сервер HIBP (Префікс): <span id="resPrefix" class="text-light fw-bold"></span></p>
            </div>

            <div id="alertDanger" class="alert alert-danger d-none align-items-center" role="alert">
                <i class="bi bi-shield-x fs-3 me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Пароль скомпрометовано!</h5>
                    <p class="mb-0">Він знайдений у базах витоків <strong id="resCount"></strong> разів. Негайно змініть його.</p>
                </div>
            </div>

            <div id="alertSuccess" class="alert alert-success d-none align-items-center" role="alert">
                <i class="bi bi-shield-check fs-3 me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Пароль безпечний!</h5>
                    <p class="mb-0">Цей пароль відсутній у базі відомих витоків HIBP.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle Password Visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const pwd = document.getElementById('passwordInput');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});

// SHA-1 Helper Function using Web Crypto API
async function sha1(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex.toUpperCase();
}

// Form Submission
document.getElementById('checkForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const pwd = document.getElementById('passwordInput').value;
    if (!pwd) return;

    // UI State: Loading
    const btn = document.getElementById('submitBtn');
    const resultsContainer = document.getElementById('resultsContainer');
    const loading = document.getElementById('loading');
    const alertDanger = document.getElementById('alertDanger');
    const alertSuccess = document.getElementById('alertSuccess');

    btn.disabled = true;
    resultsContainer.classList.add('d-none');
    alertDanger.classList.remove('d-flex');
    alertDanger.classList.add('d-none');
    alertSuccess.classList.remove('d-flex');
    alertSuccess.classList.add('d-none');
    loading.classList.remove('d-none');

    try {
        // Calculate Hash locally
        const hash = await sha1(pwd);
        const prefix = hash.substring(0, 5);
        const suffix = hash.substring(5);

        // Fetch from HIBP directly from browser
        const response = await fetch(`https://api.pwnedpasswords.com/range/${prefix}`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const text = await response.text();
        const lines = text.split('\n');
        
        let foundCount = 0;
        
        // Compare suffix locally
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            
            const parts = line.split(':');
            const remoteSuffix = parts[0];
            const remoteCount = parts[1];

            if (remoteSuffix === suffix) {
                foundCount = parseInt(remoteCount, 10);
                break;
            }
        }

        // Display Results
        document.getElementById('resHash').innerText = hash;
        document.getElementById('resPrefix').innerText = prefix;

        if (foundCount > 0) {
            document.getElementById('resCount').innerText = new Intl.NumberFormat('uk-UA').format(foundCount);
            alertDanger.classList.remove('d-none');
            alertDanger.classList.add('d-flex');
        } else {
            alertSuccess.classList.remove('d-none');
            alertSuccess.classList.add('d-flex');
        }

        resultsContainer.classList.remove('d-none');

    } catch (error) {
        console.error('HIBP Error:', error);
        toastr.error('Не вдалося з\'єднатися із сервером HIBP.');
    } finally {
        // UI State: Reset
        btn.disabled = false;
        loading.classList.add('d-none');
    }
});
</script>