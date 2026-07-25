<?php
$pageTitle = 'Генератор паролів';
?>

<div class="container py-5 d-flex justify-content-center">
    <div class="tool-box w-100" style="max-width: 450px;">
        <h2 class="text-center mb-4"><i class="bi bi-key text-warning"></i> Генератор паролів</h2>

        <div class="mb-4">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="numbers" checked>
                <label class="form-check-label" for="numbers">Цифри (0-9)</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="letters" checked>
                <label class="form-check-label" for="letters">Букви (A-Z, a-z)</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="symbols">
                <label class="form-check-label" for="symbols">Спецсимволи (!@#...)</label>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label d-flex justify-content-between">
                <span>Довжина пароля</span>
                <span id="lengthVal" class="text-info fw-bold">16</span>
            </label>
            <input type="range" class="form-range" id="length" min="4" max="64" value="16" oninput="document.getElementById('lengthVal').innerText = this.value">
        </div>

        <button class="btn btn-primary w-100 mb-4" onclick="generate()"><i class="bi bi-arrow-clockwise"></i> Згенерувати</button>

        <div class="input-group mb-3">
            <input type="text" id="password" class="form-control bg-dark text-light border-secondary fs-5 text-center" readonly>
            <button class="btn btn-outline-secondary" type="button" onclick="copyPass()"><i class="bi bi-clipboard"></i></button>
        </div>
        
        <!-- Strength Meter -->
        <div class="progress" style="height: 5px;">
            <div id="strengthMeter" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div id="strengthText" class="text-center small mt-1 text-secondary"></div>
    </div>
</div>

<script>
function checkStrength(password) {
    let strength = 0;
    if (password.length > 7) strength += 25;
    if (password.length > 12) strength += 25;
    if (/[A-Z]/.test(password)) strength += 15;
    if (/[a-z]/.test(password)) strength += 15;
    if (/[0-9]/.test(password)) strength += 10;
    if (/[^A-Za-z0-9]/.test(password)) strength += 10;
    
    const meter = document.getElementById('strengthMeter');
    const text = document.getElementById('strengthText');
    meter.style.width = strength + '%';
    
    if(strength < 30) {
        meter.className = 'progress-bar bg-danger';
        text.innerText = 'Слабкий';
        text.className = 'text-center small mt-1 text-danger';
    } else if(strength < 70) {
        meter.className = 'progress-bar bg-warning';
        text.innerText = 'Середній';
        text.className = 'text-center small mt-1 text-warning';
    } else {
        meter.className = 'progress-bar bg-success';
        text.innerText = 'Надійний';
        text.className = 'text-center small mt-1 text-success';
    }
}

function generate() {
    let length = parseInt(document.getElementById('length').value);
    let useNumbers = document.getElementById('numbers').checked;
    let useLetters = document.getElementById('letters').checked;
    let useSymbols = document.getElementById('symbols').checked;

    let chars = '';
    if (useNumbers) chars += '0123456789';
    if (useLetters) chars += 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (useSymbols) chars += '!@#$%^&*()_+{}[]<>?-=';

    if (chars === '') {
        toastr.warning('Виберіть хоча б один тип символів!');
        return;
    }

    let password = '';
    let array = new Uint32Array(length);
    window.crypto.getRandomValues(array);
    for (let i = 0; i < length; i++) {
        password += chars[array[i] % chars.length];
    }

    document.getElementById('password').value = password;
    checkStrength(password);
}

function copyPass() {
    let pass = document.getElementById('password').value;
    if (!pass) return;

    navigator.clipboard.writeText(pass).then(() => {
        toastr.success('Скопійовано!');
    }).catch(err => {
        toastr.error('Не вдалося скопіювати');
    });
}

// Generate on load
document.addEventListener('DOMContentLoaded', generate);
</script>