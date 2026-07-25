<?php
if (!isLoggedIn()) {
    header("Location: /?module=login");
    exit;
}
$pageTitle = 'Dev Multi-Tool';
?>

<div class="container-fluid py-4 px-lg-5">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="text-center"><i class="bi bi-wrench-adjustable text-primary"></i> Dev Multi-Tool</h2>
            <p class="text-secondary text-center mb-0">Кодувальник, хешер та інструмент для роботи з датами в одному місці.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Input -->
        <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <label class="form-label text-secondary mb-0 fw-bold">Вхідні дані (Input)</label>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="btnClear" title="Очистити"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <textarea id="inputText" class="form-control bg-dark text-light border-secondary" rows="18" placeholder="Вставте текст сюди..."></textarea>
        </div>

        <!-- Toolbar (Center) -->
        <div class="col-lg-2 d-flex flex-column align-items-center justify-content-center">
            <div class="d-grid gap-2 w-100 mb-4" style="max-height: 60vh; overflow-y: auto; padding-right: 5px;">
                
                <h6 class="text-light text-center border-bottom border-secondary pb-1 mt-2">Кодування</h6>
                <div class="btn-group btn-group-sm w-100 mb-1">
                    <button class="btn btn-outline-info" onclick="processText('base64en')">B64 Enc</button>
                    <button class="btn btn-outline-info" onclick="processText('base64de')">B64 Dec</button>
                </div>
                <div class="btn-group btn-group-sm w-100 mb-1">
                    <button class="btn btn-outline-info" onclick="processText('urlen')">URL Enc</button>
                    <button class="btn btn-outline-info" onclick="processText('urlde')">URL Dec</button>
                </div>
                <div class="btn-group btn-group-sm w-100 mb-1">
                    <button class="btn btn-outline-info" onclick="processText('hexen')">HEX Enc</button>
                    <button class="btn btn-outline-info" onclick="processText('hexde')">HEX Dec</button>
                </div>
                <div class="btn-group btn-group-sm w-100 mb-1">
                    <button class="btn btn-outline-info" onclick="processText('htmlen')">HTML Enc</button>
                    <button class="btn btn-outline-info" onclick="processText('htmlde')">HTML Dec</button>
                </div>

                <h6 class="text-light text-center border-bottom border-secondary pb-1 mt-3">Хешування</h6>
                <button class="btn btn-sm btn-outline-warning mb-1" onclick="processText('md5')">MD5</button>
                <button class="btn btn-sm btn-outline-warning mb-1" onclick="processText('sha1')">SHA-1</button>
                <button class="btn btn-sm btn-outline-warning mb-1" onclick="processText('sha256')">SHA-256</button>

                <h6 class="text-light text-center border-bottom border-secondary pb-1 mt-3">Час та Дати</h6>
                <div class="btn-group btn-group-sm w-100 mb-1">
                    <button class="btn btn-outline-success" onclick="processText('unixtodate')" title="Unix -> Date">U -> D</button>
                    <button class="btn btn-outline-success" onclick="processText('datetounix')" title="Date -> Unix">D -> U</button>
                </div>

                <h6 class="text-light text-center border-bottom border-secondary pb-1 mt-3">Утиліти</h6>
                <button class="btn btn-sm btn-outline-primary mb-3" onclick="processText('jwt')">JWT Decode</button>

                <!-- Swap Button -->
                <button class="btn btn-secondary mt-auto fw-bold" id="btnSwap" title="Поміняти місцями">
                    <i class="bi bi-arrow-left-right"></i> Swap
                </button>
            </div>
        </div>

        <!-- Output -->
        <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <label class="form-label text-secondary mb-0 fw-bold">Результат (Output)</label>
                <div>
                    <button class="btn btn-sm btn-outline-success" id="btnCopy" title="Копіювати результат"><i class="bi bi-clipboard"></i> Копіювати</button>
                </div>
            </div>
            <textarea id="outputText" class="form-control bg-dark text-success border-secondary" rows="18" readonly placeholder="Тут з'явиться результат..."></textarea>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>
<script>
    const inputEl = document.getElementById('inputText');
    const outputEl = document.getElementById('outputText');

    // On load, set current Unix timestamp as hint
    window.addEventListener('DOMContentLoaded', () => {
        if (!inputEl.value) {
            inputEl.placeholder = "Вставте текст сюди...\n\nПриклад Unix Timestamp: " + Math.floor(Date.now() / 1000);
        }
    });

    // Helper: UTF-8 safe Base64
    function utoa(str) {
        return window.btoa(unescape(encodeURIComponent(str)));
    }
    function atou(str) {
        return decodeURIComponent(escape(window.atob(str)));
    }

    // Helper: HTML Entities
    function encodeHTML(str) {
        return str.replace(/[\u00A0-\u9999<>\&]/g, function(i) {
            return '&#'+i.charCodeAt(0)+';';
        });
    }
    function decodeHTML(str) {
        const txt = document.createElement("textarea");
        txt.innerHTML = str;
        return txt.value;
    }

    // Helper: HEX
    function textToHex(text) {
        return Array.from(text).map(c => c.charCodeAt(0).toString(16).padStart(2, '0')).join('');
    }
    function hexToText(hex) {
        let str = '';
        for (let i = 0; i < hex.length; i += 2) {
            str += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
        }
        return str;
    }

    // Helper: Async Hash
    async function hashText(algo, text) {
        const msgBuffer = new TextEncoder().encode(text);
        const hashBuffer = await crypto.subtle.digest(algo, msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function processText(action) {
        const text = inputEl.value.trim();
        if (!text) {
            toastr.warning('Спочатку введіть текст у ліве поле!');
            return;
        }

        try {
            let res = '';
            
            switch (action) {
                case 'base64en': res = utoa(text); break;
                case 'base64de': res = atou(text); break;
                case 'urlen': res = encodeURIComponent(text); break;
                case 'urlde': res = decodeURIComponent(text); break;
                case 'hexen': res = textToHex(text); break;
                case 'hexde': res = hexToText(text); break;
                case 'htmlen': res = encodeHTML(text); break;
                case 'htmlde': res = decodeHTML(text); break;
                case 'md5': res = md5(text); break;
                case 'sha1': res = await hashText('SHA-1', text); break;
                case 'sha256': res = await hashText('SHA-256', text); break;
                
                case 'unixtodate':
                    // Parse sec or millisec
                    let ms = parseInt(text);
                    if (text.length <= 10) ms *= 1000;
                    const d = new Date(ms);
                    if (isNaN(d.getTime())) throw new Error('Невірний формат Timestamp');
                    // Format YYYY-MM-DD HH:MM:SS
                    const pad = (n) => n.toString().padStart(2, '0');
                    res = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                    break;

                case 'datetounix':
                    const parsedDate = new Date(text);
                    if (isNaN(parsedDate.getTime())) throw new Error('Невірний формат дати');
                    res = Math.floor(parsedDate.getTime() / 1000).toString();
                    break;
                    
                case 'jwt':
                    const parts = text.split('.');
                    if (parts.length !== 3) throw new Error('Невірний формат JWT');
                    // Extract payload correctly considering base64url padding
                    let base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
                    while (base64.length % 4) {
                        base64 += '=';
                    }
                    const payload = atou(base64);
                    // Pretty print JSON
                    res = JSON.stringify(JSON.parse(payload), null, 4);
                    break;
            }

            outputEl.value = res;
            toastr.success('Готово!');

        } catch (e) {
            console.error(e);
            toastr.error(e.message || 'Помилка конвертації. Перевірте формат вхідних даних.');
            outputEl.value = 'Помилка: ' + (e.message || 'Невідомий формат');
        }
    }

    document.getElementById('btnSwap').addEventListener('click', () => {
        const out = outputEl.value;
        if (out && !out.startsWith('Помилка:')) {
            inputEl.value = out;
            outputEl.value = '';
        } else {
            toastr.info('Немає результату для перенесення');
        }
    });

    document.getElementById('btnCopy').addEventListener('click', () => {
        const out = outputEl.value;
        if (!out) return toastr.warning('Немає що копіювати!');
        navigator.clipboard.writeText(out).then(() => {
            toastr.info('Скопійовано!');
        });
    });

    document.getElementById('btnClear').addEventListener('click', () => {
        inputEl.value = '';
        outputEl.value = '';
    });

</script>

