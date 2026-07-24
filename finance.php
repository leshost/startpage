<?php
require_once 'config.php';

$pageTitle = 'Фінансовий калькулятор';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center"><i class="bi bi-cash-stack text-success"></i> Фінансовий калькулятор розподілу бюджету</h2>
            <p class="text-secondary text-center">Професійний підхід до планування особистих фінансів та інвестицій.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Калькулятор (Вхідні дані) -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-box h-100">
                <h4 class="mb-4">1. Вхідні дані</h4>
                <div class="mb-4">
                    <label class="form-label fw-bold text-light">Загальний чистий дохід (на руки), грн</label>
                    <input type="number" id="salary" class="form-control form-control-lg bg-dark text-success border-secondary fw-bold" value="30000">
                </div>
                
                <h5 class="mt-4 mb-3 fs-6 text-secondary">Розподіл бюджету (%)</h5>
                <div class="mb-3">
                    <label class="form-label text-secondary small mb-1">Обов’язкові витрати (Житло, їжа, комуналка)</label>
                    <div class="input-group">
                        <input type="number" id="needPercent" class="form-control bg-dark text-light border-secondary" value="50">
                        <span class="input-group-text bg-dark text-light border-secondary w-50 justify-content-end fw-bold" id="needResult"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small mb-1">Гнучкі витрати (Розваги, хобі, шопінг)</label>
                    <div class="input-group">
                        <input type="number" id="wantPercent" class="form-control bg-dark text-light border-secondary" value="30">
                        <span class="input-group-text bg-dark text-light border-secondary w-50 justify-content-end fw-bold" id="wantResult"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small mb-1">Накопичення / Інвестиції / Борги</label>
                    <div class="input-group">
                        <input type="number" id="savePercent" class="form-control bg-dark text-light border-secondary" value="20">
                        <span class="input-group-text bg-dark text-light border-secondary w-50 justify-content-end fw-bold text-success" id="saveResult"></span>
                    </div>
                </div>

                <div id="totalCheck" class="text-center mt-4 fw-bold"></div>
            </div>
        </div>

        <!-- Візуалізація та Пресети -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-box h-100 d-flex flex-column">
                <h4 class="mb-3">2. Структура бюджету</h4>
                
                <!-- Chart Container -->
                <div class="mb-4 d-flex justify-content-center" style="height: 220px;">
                    <canvas id="budgetChart"></canvas>
                </div>

                <h5 class="fs-6 text-secondary mb-3">Стандартні правила (Пресети)</h5>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 preset-btn py-2" data-preset="50-30-20" onclick="setPreset(50,30,20)">
                            <div class="fw-bold">50/30/20</div>
                            <small style="font-size: 0.7em;">Золотий стандарт</small>
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 preset-btn py-2" data-preset="40-10-50" onclick="setPreset(40,10,50)">
                            <div class="fw-bold">40/10/50</div>
                            <small style="font-size: 0.7em;">Рух FIRE (Агресивно)</small>
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 preset-btn py-2" data-preset="75-10-15" onclick="setPreset(75,10,15)">
                            <div class="fw-bold">75/10/15</div>
                            <small style="font-size: 0.7em;">Збалансований</small>
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 preset-btn py-2" data-preset="60-20-20" onclick="setPreset(60,20,20)">
                            <div class="fw-bold">60/20/20</div>
                            <small style="font-size: 0.7em;">Комфортний</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Інвестиції та рекомендації -->
        <div class="col-md-12 col-lg-4">
            <div class="tool-box h-100">
                <h4 class="mb-3">3. Інвестиції та Капітал</h4>
                
                <div class="alert alert-dark border-secondary p-3 mb-4">
                    <label class="form-label text-light fw-bold mb-1">Очікувана річна дохідність (%)</label>
                    <p class="small text-secondary mb-2">Наприклад: 10-12% (ОВДП) або 7-9% (S&P 500 в $)</p>
                    <input type="number" id="annualYield" class="form-control bg-dark text-info border-secondary fw-bold" value="10">
                </div>

                <h5 class="fs-6 text-secondary mb-3">Прогноз капіталу (Складний відсоток)</h5>
                
                <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded mb-2 border border-secondary">
                    <span class="text-light">За 1 рік:</span>
                    <span class="text-success fw-bold fs-5" id="proj1Year">0 ₴</span>
                </div>
                <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded mb-2 border border-secondary">
                    <span class="text-light">За 5 років:</span>
                    <span class="text-success fw-bold fs-5" id="proj5Years">0 ₴</span>
                </div>
                <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded border border-secondary">
                    <span class="text-light">За 10 років:</span>
                    <span class="text-success fw-bold fs-5" id="proj10Years">0 ₴</span>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary">
                    <h5 class="fs-6 text-info"><i class="bi bi-lightbulb"></i> Поради</h5>
                    <ul class="small text-secondary list-unstyled">
                        <li class="mb-2">🔹 <strong>Спершу Подушка:</strong> Накопичення мають формувати фін. подушку (3-6 міс. витрат) у готівці/депозитах, лише потім — інвестуватися.</li>
                        <li class="mb-2">🔹 <strong>Дорогі борги:</strong> Якщо у вас є кредитки під 40% річних, всі "накопичення" мають йти на їх дострокове погашення.</li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
let budgetChart = null;

// Initialization
document.addEventListener("DOMContentLoaded", () => {
    // Restore from localStorage
    if(localStorage.getItem('fin_salary')) document.getElementById('salary').value = localStorage.getItem('fin_salary');
    if(localStorage.getItem('fin_need')) document.getElementById('needPercent').value = localStorage.getItem('fin_need');
    if(localStorage.getItem('fin_want')) document.getElementById('wantPercent').value = localStorage.getItem('fin_want');
    if(localStorage.getItem('fin_save')) document.getElementById('savePercent').value = localStorage.getItem('fin_save');
    if(localStorage.getItem('fin_yield')) document.getElementById('annualYield').value = localStorage.getItem('fin_yield');
    
    initChart();
    calculate();
});

function initChart() {
    const ctx = document.getElementById('budgetChart').getContext('2d');
    budgetChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Обов’язкові', 'Гнучкі', 'Накопичення'],
            datasets: [{
                data: [50, 30, 20],
                backgroundColor: [
                    '#dc3545', // Danger (Needs)
                    '#ffc107', // Warning (Wants)
                    '#198754'  // Success (Savings)
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#e0e0e0', font: { size: 12 } }
                }
            },
            cutout: '65%'
        }
    });
}

function calculateFutureValue(monthlyDeposit, annualYieldPercent, years) {
    if (annualYieldPercent <= 0) return monthlyDeposit * 12 * years;
    let r = (annualYieldPercent / 100) / 12;
    let n = years * 12;
    return monthlyDeposit * ((Math.pow(1 + r, n) - 1) / r);
}

function formatCurrency(val) {
    return new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH', maximumFractionDigits: 0 }).format(val);
}

function calculate() {
    let s = +document.getElementById('salary').value || 0;
    let n = +document.getElementById('needPercent').value || 0;
    let w = +document.getElementById('wantPercent').value || 0;
    let sv = +document.getElementById('savePercent').value || 0;
    let y = +document.getElementById('annualYield').value || 0;

    // Save to localStorage
    localStorage.setItem('fin_salary', s);
    localStorage.setItem('fin_need', n);
    localStorage.setItem('fin_want', w);
    localStorage.setItem('fin_save', sv);
    localStorage.setItem('fin_yield', y);

    let need = s * n / 100;
    let want = s * w / 100;
    let save = s * sv / 100;

    document.getElementById('needResult').innerText = formatCurrency(need);
    document.getElementById('wantResult').innerText = formatCurrency(want);
    document.getElementById('saveResult').innerText = formatCurrency(save);

    // Update Chart
    if (budgetChart) {
        budgetChart.data.datasets[0].data = [n, w, sv];
        budgetChart.update();
    }

    // Projections
    document.getElementById('proj1Year').innerText = formatCurrency(calculateFutureValue(save, y, 1));
    document.getElementById('proj5Years').innerText = formatCurrency(calculateFutureValue(save, y, 5));
    document.getElementById('proj10Years').innerText = formatCurrency(calculateFutureValue(save, y, 10));

    // Validation
    let t = n + w + sv;
    let checkEl = document.getElementById('totalCheck');
    if(t === 100) {
        checkEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> Всі 100% розподілено вірно';
        checkEl.className = 'text-center mt-4 fw-bold text-success';
    } else {
        checkEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Увага: сума ${t}% (має бути 100%)`;
        checkEl.className = 'text-center mt-4 fw-bold text-danger';
    }

    // Active Preset Styling
    document.querySelectorAll('.preset-btn').forEach(b => {
        if(b.dataset.preset === `${n}-${w}-${sv}`) {
            b.classList.remove('btn-outline-secondary');
            b.classList.add('btn-primary');
        } else {
            b.classList.add('btn-outline-secondary');
            b.classList.remove('btn-primary');
        }
    });
}

function setPreset(n, w, s) {
    document.getElementById('needPercent').value = n;
    document.getElementById('wantPercent').value = w;
    document.getElementById('savePercent').value = s;
    calculate();
}

// Event Listeners
document.querySelectorAll('input[type="number"]').forEach(i => i.oninput = calculate);
</script>

<?php require_once 'includes/footer.php'; ?>