<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Фінансовий калькулятор</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #121212;
    color: #e0e0e0;
    margin: 0;
}

/* NAVBAR */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e1e1e;
    padding: 15px 20px;
    flex-wrap: wrap;
}

.logo {
    font-size: 18px;
    font-weight: bold;
    color: #4CAF50;
    text-decoration: none;
}

.menu {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.menu a {
    color: #ccc;
    text-decoration: none;
    font-size: 14px;
}

/* TITLE */
.page-title {
    padding: 15px 20px 0;
    font-size: 20px;
    font-weight: bold;
}

/* LAYOUT */
.wrapper {
    display: flex;
    gap: 20px;
    padding: 20px;
    flex-wrap: wrap;
}

/* BLOCKS */
.container, .presets, .tips {
    background: #1e1e1e;
    padding: 20px;
    border-radius: 12px;
    flex: 1;
    min-width: 280px;
}

/* 🔥 НОВА ФОРМА */
.field {
    margin-bottom: 18px;
}

.field label {
    display: block;
    font-size: 13px;
    color: #aaa;
    margin-bottom: 6px;
}

.field input {
    width: 100%;
    padding: 8px 10px;
    font-size: 15px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #2a2a2a;
    color: #fff;
}

/* результат */
.result-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding: 6px 10px;
    background: #252525;
    border-radius: 6px;
}

.result-label {
    font-size: 13px;
    color: #888;
}

.result-value {
    font-size: 18px;
    font-weight: bold;
    color: #90caf9;
}

/* total */
#totalCheck {
    margin-top: 15px;
    font-size: 14px;
    text-align: center;
}

/* BUTTONS */
.preset-btn {
    width: 100%;
    padding: 12px;
    margin-bottom: 10px;
    border: none;
    border-radius: 8px;
    background: #2a2a2a;
    color: #ccc;
    font-size: 14px;
    cursor: pointer;
}

.preset-btn.active {
    background: #4CAF50;
    color: #fff;
}

/* SAVINGS */
.savings-box {
    margin-top: 15px;
    padding: 12px;
    background: #252525;
    border-radius: 10px;
}

/* TIPS */
.tips p {
    font-size: 14px;
    color: #ccc;
    margin-bottom: 12px;
    line-height: 1.6;
}

.highlight {
    background: #263238;
    padding: 10px;
    border-radius: 8px;
    color: #90caf9;
}

/* MOBILE */
@media (max-width: 768px) {

    .wrapper {
        flex-direction: column;
        padding: 10px;
    }

    .navbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .menu {
        width: 100%;
        justify-content: space-between;
    }

    .container, .presets, .tips {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="navbar">
    <a class="logo" href="https://startpage.uax.cloud/?user=andjey">💰 Finance Tool</a>
    <div class="menu">
        <a href="https://startpage.uax.cloud/?user=andjey">Головна</a>
        <a href="/finance.php">Калькулятор</a>
        <a href="https://ip.uax.cloud/">MyIP</a>
    </div>
</div>

<div class="page-title">
    Фінансовий калькулятор розподілу зарплати
</div>

<div class="wrapper">

    <!-- 🔥 КАЛЬКУЛЯТОР -->
    <div class="container">
        <h3>Розподіл</h3>

        <div class="field">
            <label>Зарплата (грн)</label>
            <input type="number" id="salary" value="20000">
        </div>

        <div class="field">
            <label>Обов’язкові витрати (%)</label>
            <input type="number" id="needPercent" value="60">
            <div class="result-line">
                <span class="result-label">Сума</span>
                <span class="result-value" id="needResult"></span>
            </div>
        </div>

        <div class="field">
            <label>Гнучкі витрати (%)</label>
            <input type="number" id="wantPercent" value="20">
            <div class="result-line">
                <span class="result-label">Сума</span>
                <span class="result-value" id="wantResult"></span>
            </div>
        </div>

        <div class="field">
            <label>Накопичення (%)</label>
            <input type="number" id="savePercent" value="20">
            <div class="result-line">
                <span class="result-label">Сума</span>
                <span class="result-value" id="saveResult"></span>
            </div>
        </div>

        <div id="totalCheck"></div>
    </div>

    <!-- ПРЕСЕТИ -->
    <div class="presets">
        <h3>Варіанти</h3>

        <button class="preset-btn" data-preset="77-7-16" onclick="setPreset(77,7,16)">77 / 7 / 16</button>
        <button class="preset-btn" data-preset="75-10-15" onclick="setPreset(75,10,15)">75 / 10 / 15</button>
        <button class="preset-btn" data-preset="70-15-15" onclick="setPreset(70,15,15)">70 / 15 / 15</button>
        <button class="preset-btn" data-preset="60-20-20" onclick="setPreset(60,20,20)">60 / 20 / 20</button>

        <div class="savings-box">
            💰 За місяць: <b id="monthlySave"></b><br>
            📅 За рік: <b id="yearlySave"></b>
        </div>
    </div>

    <!-- РЕКОМЕНДАЦІЇ -->
    <div class="tips">
        <h3>📊 Рекомендації</h3>

        <p><b>1. Спочатку плати собі</b><br>
        Відкладай гроші одразу після отримання зарплати, а не в кінці місяця.</p>

        <p><b>2. Мінімум 10%</b><br>
        Навіть при маленькій зарплаті намагайся відкладати хоча б 10%.</p>

        <p><b>3. Фінансова подушка</b><br>
        Ціль — накопичити 3–6 місяців витрат.</p>

        <p><b>4. Не лізь у накопичення</b><br>
        Якщо не вистачає — проблема в витратах, а не в “малій зп”.</p>

        <div class="highlight">
            💡 <b>Порада:</b><br>
            Якщо постійно не вистачає грошей — зменшуй % витрат або збільшуй дохід.<br><br>
            Баланс важливіший за “ідеальну формулу”.
        </div>
    </div>

</div>

<script>
function calculate(){
    let s=+salary.value||0;
    let n=+needPercent.value||0;
    let w=+wantPercent.value||0;
    let sv=+savePercent.value||0;

    let need=s*n/100;
    let want=s*w/100;
    let save=s*sv/100;

    needResult.innerText=need.toFixed(2)+' грн';
    wantResult.innerText=want.toFixed(2)+' грн';
    saveResult.innerText=save.toFixed(2)+' грн';

    monthlySave.innerText=save.toFixed(2)+' грн';
    yearlySave.innerText=(save*12).toFixed(2)+' грн';

    let t=n+w+sv;
    totalCheck.innerText=t===100?'✅ 100%':'⚠️ '+t+'%';
    totalCheck.style.color=t===100?'#66bb6a':'#ef5350';

    document.querySelectorAll('.preset-btn').forEach(b=>{
        b.classList.toggle('active', b.dataset.preset===`${n}-${w}-${sv}`);
    });
}

function setPreset(n,w,s){
    needPercent.value=n;
    wantPercent.value=w;
    savePercent.value=s;
    calculate();
}

document.querySelectorAll('input').forEach(i=>i.oninput=calculate);
calculate();
</script>

</body>
</html>