<?php
$pageTitle = 'Форматер JSON';
?>

<!-- Highlight.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">

<style>
    .json-textarea, .json-output {
        height: 60vh;
        min-height: 400px;
        font-family: monospace;
        font-size: 14px;
        border-radius: 0.375rem;
    }
    .json-output {
        overflow: auto;
        background-color: #282c34; /* Atom One Dark background */
        border: 1px solid var(--bs-border-color);
        padding: 1rem;
        margin: 0;
    }
    /* Hide scrollbar for cleaner look if desired, but here we want it */
</style>

<div class="container-fluid py-4 px-lg-5">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="text-center"><i class="bi bi-braces text-warning"></i> Форматер JSON</h2>
            <p class="text-secondary text-center mb-0">Створюйте красивий, читабельний код або стискайте його для оптимізації.</p>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="row mb-3 justify-content-center">
        <div class="col-auto">
            <button class="btn btn-outline-success me-2" id="btnFormat" title="Структурувати JSON (Beautify)"><i class="bi bi-magic"></i> Форматувати</button>
            <button class="btn btn-outline-warning me-2" id="btnMinify" title="Стиснути в один рядок (Minify)"><i class="bi bi-file-earmark-zip"></i> Мінімізувати</button>
            <button class="btn btn-outline-info me-2" id="btnCopy" title="Копіювати результат"><i class="bi bi-clipboard"></i> Копіювати</button>
            <button class="btn btn-outline-danger" id="btnClear" title="Очистити всі поля"><i class="bi bi-trash"></i> Очистити</button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="row g-4">
        <!-- Input -->
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <label class="form-label text-secondary mb-0">Вхідний JSON</label>
                <small id="inputStatus" class="text-muted"></small>
            </div>
            <textarea id="jsonInput" class="form-control bg-dark text-light border-secondary json-textarea" placeholder='Вставте сюди ваш сирий JSON...'></textarea>
        </div>

        <!-- Output -->
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-end mb-2">
                <label class="form-label text-secondary mb-0">Результат</label>
                <small id="outputStatus" class="text-muted"></small>
            </div>
            <pre class="json-output"><code id="jsonOutput" class="language-json">Тут з'явиться результат...</code></pre>
        </div>
    </div>
</div>

<!-- Highlight.js JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<!-- Highlight.js JSON Language -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>

<script>
    const inputEl = document.getElementById('jsonInput');
    const outputEl = document.getElementById('jsonOutput');
    const btnFormat = document.getElementById('btnFormat');
    const btnMinify = document.getElementById('btnMinify');
    const btnCopy = document.getElementById('btnCopy');
    const btnClear = document.getElementById('btnClear');
    
    // Auto-update output when user types? No, better explicit action for large JSONs.
    
    function processJson(action) {
        const raw = inputEl.value.trim();
        if (!raw) {
            toastr.warning('Вставте JSON у ліве поле!');
            return;
        }

        try {
            const parsed = JSON.parse(raw);
            let result = '';
            
            if (action === 'format') {
                result = JSON.stringify(parsed, null, 4);
                toastr.success('JSON успішно відформатовано!');
            } else if (action === 'minify') {
                result = JSON.stringify(parsed);
                toastr.success('JSON успішно стиснено!');
            }
            
            outputEl.textContent = result;
            
            // Re-apply highlight.js
            delete outputEl.dataset.highlighted;
            hljs.highlightElement(outputEl);
            
            // Auto copy to input for continuous operations? (Optional, let's leave it separate)

        } catch (e) {
            console.error(e);
            toastr.error('Невалідний JSON! Перевірте синтаксис.');
            outputEl.innerHTML = `<span class="text-danger">Помилка парсингу:\n${e.message}</span>`;
        }
    }

    btnFormat.addEventListener('click', () => processJson('format'));
    btnMinify.addEventListener('click', () => processJson('minify'));
    
    btnCopy.addEventListener('click', () => {
        const textToCopy = outputEl.textContent;
        if (!textToCopy || textToCopy === 'Тут з\'явиться результат...') {
            toastr.warning('Немає результату для копіювання!');
            return;
        }
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            toastr.info('Результат скопійовано в буфер обміну!');
        }).catch(err => {
            console.error('Copy failed', err);
            toastr.error('Не вдалося скопіювати текст.');
        });
    });
    
    btnClear.addEventListener('click', () => {
        inputEl.value = '';
        outputEl.textContent = 'Тут з\'явиться результат...';
        delete outputEl.dataset.highlighted;
    });

</script>

