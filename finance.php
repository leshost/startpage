<?php
require_once 'config.php';

$pageTitle = 'Фінансовий калькулятор';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center"><i class="bi bi-cash-stack text-success"></i> Фінансовий калькулятор розподілу зарплати</h2>
        </div>
    </div>

    <div class="row g-4">
        <!-- Калькулятор -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-box h-100">
                <h3>Розподіл</h3>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Зарплата (грн)</label>
                    <input type="number" id="salary" class="form-control bg-dark text-light border-secondary" value="20000">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-secondary small">Обов’язкові витрати (%)</label>
                    <input type="number" id="needPercent" class="form-control bg-dark text-light border-secondary mb-2" value="60">
                    <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded">
                        <span class="text-secondary small">Сума</span>
                        <span class="text-info fw-bold" id="needResult"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Гнучкі витрати (%)</label>
                    <input type="number" id="wantPercent" class="form-control bg-dark text-light border-secondary mb-2" value="20">
                    <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded">
                        <span class="text-secondary small">Сума</span>
                        <span class="text-info fw-bold" id="wantResult"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Накопичення (%)</label>
                    <input type="number" id="savePercent" class="form-control bg-dark text-light border-secondary mb-2" value="20">
                    <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded">
                        <span class="text-secondary small">Сума</span>
                        <span class="text-info fw-bold" id="saveResult"></span>
                    </div>
                </div>

                <div id="totalCheck" class="text-center mt-3 fw-bold"></div>
            </div>
        </div>

        <!-- Пресети -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-box h-100">
                <h3>Варіанти</h3>
                <div class="d-grid gap-2 mb-4">
                    <button class="btn btn-outline-secondary preset-btn" data-preset="77-7-16" onclick="setPreset(77,7,16)">77 / 7 / 16</button>
                    <button class="btn btn-outline-secondary preset-btn" data-preset="75-10-15" onclick="setPreset(75,10,15)">75 / 10 / 15</button>
                    <button class="btn btn-outline-secondary preset-btn" data-preset="70-15-15" onclick="setPreset(70,15,15)">70 / 15 / 15</button>
                    <button class="btn btn-outline-secondary preset-btn" data-preset="60-20-20" onclick="setPreset(60,20,20)">60 / 20 / 20</button>
                </div>

                <div class="bg-dark p-3 rounded">
                    <div class="mb-2">💰 За місяць: <b id="monthlySave" class="text-success"></b></div>
                    <div>📅 За рік: <b id="yearlySave" class="text-success"></b></div>
                </div>
            </div>
        </div>

        <!-- Рекомендації -->
        <div class="col-md-12 col-lg-4">
            <div class="tool-box h-100">
                <h3><i class="bi bi-info-circle text-info"></i> Рекомендації</h3>
                <ul class="list-unstyled text-secondary">
                    <li class="mb-3">
                        <strong class="text-light">1. Спочатку плати собі</strong><br>
                        Відкладай гроші одразу після отримання зарплати, а не в кінці місяця.
                    </li>
                    <li class="mb-3">
                        <strong class="text-light">2. Мінімум 10%</strong><br>
                        Навіть при маленькій зарплаті намагайся відкладати хоча б 10%.
                    </li>
                    <li class="mb-3">
                        <strong class="text-light">3. Фінансова подушка</strong><br>
                        Ціль — накопичити 3–6 місяців витрат.
                    </li>
                    <li class="mb-3">
                        <strong class="text-light">4. Не лізь у накопичення</strong><br>
                        Якщо не вистачає — проблема в витратах, а не в “малій зп”.
                    </li>
                </ul>
                <div class="alert alert-dark border-secondary text-info mt-4" role="alert">
                    <i class="bi bi-lightbulb"></i> <b>Порада:</b><br>
                    Якщо постійно не вистачає грошей — зменшуй % витрат або збільшуй дохід. Баланс важливіший за “ідеальну формулу”.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load from localStorage
document.addEventListener("DOMContentLoaded", () => {
    if(localStorage.getItem('fin_salary')) document.getElementById('salary').value = localStorage.getItem('fin_salary');
    if(localStorage.getItem('fin_need')) document.getElementById('needPercent').value = localStorage.getItem('fin_need');
    if(localStorage.getItem('fin_want')) document.getElementById('wantPercent').value = localStorage.getItem('fin_want');
    if(localStorage.getItem('fin_save')) document.getElementById('savePercent').value = localStorage.getItem('fin_save');
    calculate();
});

function calculate() {
    let s = +document.getElementById('salary').value || 0;
    let n = +document.getElementById('needPercent').value || 0;
    let w = +document.getElementById('wantPercent').value || 0;
    let sv = +document.getElementById('savePercent').value || 0;

    // Save to localStorage
    localStorage.setItem('fin_salary', s);
    localStorage.setItem('fin_need', n);
    localStorage.setItem('fin_want', w);
    localStorage.setItem('fin_save', sv);

    let need = s * n / 100;
    let want = s * w / 100;
    let save = s * sv / 100;

    document.getElementById('needResult').innerText = need.toFixed(2) + ' грн';
    document.getElementById('wantResult').innerText = want.toFixed(2) + ' грн';
    document.getElementById('saveResult').innerText = save.toFixed(2) + ' грн';

    document.getElementById('monthlySave').innerText = save.toFixed(2) + ' грн';
    document.getElementById('yearlySave').innerText = (save * 12).toFixed(2) + ' грн';

    let t = n + w + sv;
    let checkEl = document.getElementById('totalCheck');
    if(t === 100) {
        checkEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> 100%';
        checkEl.className = 'text-center mt-3 fw-bold text-success';
    } else {
        checkEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + t + '%';
        checkEl.className = 'text-center mt-3 fw-bold text-danger';
    }

    document.querySelectorAll('.preset-btn').forEach(b => {
        if(b.dataset.preset === `${n}-${w}-${sv}`) {
            b.classList.remove('btn-outline-secondary');
            b.classList.add('btn-success');
        } else {
            b.classList.add('btn-outline-secondary');
            b.classList.remove('btn-success');
        }
    });
}

function setPreset(n, w, s) {
    document.getElementById('needPercent').value = n;
    document.getElementById('wantPercent').value = w;
    document.getElementById('savePercent').value = s;
    calculate();
}

document.querySelectorAll('input[type="number"]').forEach(i => i.oninput = calculate);
</script>

<?php require_once 'includes/footer.php'; ?>