<?php
require_once '../config/config.php';

$pageTitle = 'Генератор QR-кодів';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center"><i class="bi bi-qr-code text-info"></i> Генератор QR-кодів</h2>
            <p class="text-secondary text-center">Створюйте QR-коди для посилань, Wi-Fi та контактів миттєво і приватно.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Settings Panel -->
        <div class="col-md-7 col-lg-8">
            <div class="tool-box h-100">
                <ul class="nav nav-tabs border-secondary mb-4" id="qrTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-light border-secondary" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-pane" type="button" role="tab"><i class="bi bi-link-45deg"></i> Текст / URL</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-light border-secondary" id="wifi-tab" data-bs-toggle="tab" data-bs-target="#wifi-pane" type="button" role="tab"><i class="bi bi-wifi"></i> Wi-Fi</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-light border-secondary" id="vcard-tab" data-bs-toggle="tab" data-bs-target="#vcard-pane" type="button" role="tab"><i class="bi bi-person-lines-fill"></i> Контакт</button>
                    </li>
                </ul>

                <div class="tab-content" id="qrTabContent">
                    <!-- Text/URL -->
                    <div class="tab-pane fade show active" id="text-pane" role="tabpanel" tabindex="0">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Введіть посилання або будь-який текст</label>
                            <textarea id="qrText" class="form-control bg-dark text-light border-secondary qr-input" rows="4" placeholder="https://example.com"></textarea>
                        </div>
                    </div>

                    <!-- Wi-Fi -->
                    <div class="tab-pane fade" id="wifi-pane" role="tabpanel" tabindex="0">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Назва мережі (SSID)</label>
                            <input type="text" id="wifiSsid" class="form-control bg-dark text-light border-secondary qr-input" placeholder="MyWiFi">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Пароль</label>
                            <input type="password" id="wifiPassword" class="form-control bg-dark text-light border-secondary qr-input" placeholder="SecretPassword123">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Тип захисту</label>
                            <select id="wifiSecurity" class="form-select bg-dark text-light border-secondary qr-input">
                                <option value="WPA">WPA/WPA2/WPA3</option>
                                <option value="WEP">WEP</option>
                                <option value="nopass">Без пароля (Відкрита)</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input qr-input" type="checkbox" id="wifiHidden">
                            <label class="form-check-label text-secondary small" for="wifiHidden">Прихована мережа</label>
                        </div>
                    </div>

                    <!-- vCard -->
                    <div class="tab-pane fade" id="vcard-pane" role="tabpanel" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Ім'я</label>
                                <input type="text" id="vcardName" class="form-control bg-dark text-light border-secondary qr-input" placeholder="Іван Іванов">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Компанія</label>
                                <input type="text" id="vcardCompany" class="form-control bg-dark text-light border-secondary qr-input" placeholder="Компанія">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Телефон</label>
                                <input type="tel" id="vcardPhone" class="form-control bg-dark text-light border-secondary qr-input" placeholder="+380991234567">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Email</label>
                                <input type="email" id="vcardEmail" class="form-control bg-dark text-light border-secondary qr-input" placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Веб-сайт</label>
                            <input type="url" id="vcardWebsite" class="form-control bg-dark text-light border-secondary qr-input" placeholder="https://example.com">
                        </div>
                    </div>
                </div>

                <hr class="border-secondary my-4">
                
                <h5 class="fs-6 text-secondary mb-3">Налаштування вигляду</h5>
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label text-secondary small mb-1">Колір коду</label>
                        <input type="color" id="qrColorDark" class="form-control form-control-color bg-dark border-secondary w-100 qr-setting" value="#000000" title="Оберіть колір">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small mb-1">Колір фону</label>
                        <input type="color" id="qrColorLight" class="form-control form-control-color bg-dark border-secondary w-100 qr-setting" value="#ffffff" title="Оберіть фон">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small mb-1">Розмір (px)</label>
                        <input type="number" id="qrSize" class="form-control bg-dark text-light border-secondary qr-setting" value="256" min="100" max="1000" step="50">
                    </div>
                </div>

            </div>
        </div>

        <!-- Preview Panel -->
        <div class="col-md-5 col-lg-4">
            <div class="tool-box h-100 d-flex flex-column align-items-center justify-content-center text-center">
                <h4 class="mb-4">Результат</h4>
                
                <div class="bg-white p-3 rounded mb-4" id="qrcode-container" style="min-height: 256px; min-width: 256px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 15px rgba(0,0,0,0.5);">
                    <!-- QR Code will be rendered here -->
                </div>
                
                <button id="downloadBtn" class="btn btn-success w-100 mt-auto"><i class="bi bi-download"></i> Завантажити PNG</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let qrcode = null;
let currentTab = 'text';

// Listen to tab changes
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', event => {
        currentTab = event.target.id.replace('-tab', '');
        generateQR();
    });
});

// Add listeners to all inputs
document.querySelectorAll('.qr-input, .qr-setting').forEach(input => {
    input.addEventListener('input', generateQR);
});

function generateQR() {
    let text = '';
    
    // Get text based on active tab
    if (currentTab === 'text') {
        text = document.getElementById('qrText').value.trim();
    } 
    else if (currentTab === 'wifi') {
        const ssid = document.getElementById('wifiSsid').value.trim();
        const pwd = document.getElementById('wifiPassword').value;
        const type = document.getElementById('wifiSecurity').value;
        const hidden = document.getElementById('wifiHidden').checked ? 'true' : 'false';
        
        if (ssid) {
            text = `WIFI:T:${type};S:${ssid};P:${pwd};H:${hidden};;`;
        }
    } 
    else if (currentTab === 'vcard') {
        const n = document.getElementById('vcardName').value.trim();
        const org = document.getElementById('vcardCompany').value.trim();
        const tel = document.getElementById('vcardPhone').value.trim();
        const email = document.getElementById('vcardEmail').value.trim();
        const url = document.getElementById('vcardWebsite').value.trim();
        
        if (n || tel || email || url || org) {
            text = `BEGIN:VCARD\nVERSION:3.0\nFN:${n}\nORG:${org}\nTEL:${tel}\nEMAIL:${email}\nURL:${url}\nEND:VCARD`;
        }
    }
    
    const container = document.getElementById('qrcode-container');
    const colorDark = document.getElementById('qrColorDark').value;
    const colorLight = document.getElementById('qrColorLight').value;
    let size = parseInt(document.getElementById('qrSize').value) || 256;
    
    // Clear previous
    container.innerHTML = '';
    
    if (!text) {
        container.innerHTML = '<span class="text-muted small">Заповніть поля для генерації</span>';
        return;
    }
    
    // Generate new
    qrcode = new QRCode(container, {
        text: text,
        width: size,
        height: size,
        colorDark : colorDark,
        colorLight : colorLight,
        correctLevel : QRCode.CorrectLevel.M
    });
}

// Download Button
document.getElementById('downloadBtn').addEventListener('click', () => {
    const canvas = document.querySelector('#qrcode-container canvas');
    if (!canvas) {
        toastr.warning('Спочатку згенеруйте QR-код!');
        return;
    }
    
    const link = document.createElement('a');
    link.download = 'qrcode.png';
    link.href = canvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

// Initial trigger
generateQR();

</script>

<?php require_once '../includes/footer.php'; ?>
