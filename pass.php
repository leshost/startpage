<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Генератор паролів</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #121212;
    color: #e0e0e0;
    margin: 0;
}

/* 🔝 NAVBAR */
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
    gap: 15px;
}

.menu a {
    color: #ccc;
    text-decoration: none;
    font-size: 14px;
}

.menu a:hover {
    color: #fff;
}

/* центр */
.wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 15px;
}

/* контейнер */
.box {
    background: #1e1e1e;
    padding: 25px;
    border-radius: 12px;
    width: 380px;
}

/* заголовок */
h2 {
    margin-top: 0;
    text-align: center;
}

/* чекбокси */
.options {
    margin-bottom: 15px;
}

.options label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
}

/* інпут */
input[type="number"] {
    width: 100%;
    padding: 8px;
    margin: 5px 0 15px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #2a2a2a;
    color: #fff;
}

/* кнопка генерації */
.generate-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    background: #4CAF50;
    color: #fff;
    cursor: pointer;
}

.generate-btn:hover {
    background: #43a047;
}

/* поле + кнопка */
.output {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.output input {
    flex: 1;
    padding: 12px;
    font-size: 16px;
    border-radius: 8px;
    border: 1px solid #333;
    background: #2a2a2a;
    color: #fff;
}

/* 🔥 темна кнопка копію */
.copy-btn {
    width: 45px;
    border: none;
    border-radius: 8px;
    background: #333;
    color: #ccc;
    cursor: pointer;
    font-size: 16px;
}

.copy-btn:hover {
    background: #444;
    color: #fff;
}

/* повідомлення */
.msg {
    text-align: center;
    font-size: 13px;
    margin-top: 8px;
    color: #66bb6a;
}

/* 📱 адаптив */
@media (max-width: 768px) {
    .menu {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
</head>

<body>

<!-- 🔝 NAVBAR -->
<div class="navbar">
    <a class="logo" href="https://startpage.uax.cloud/?user=andjey">🔐 Pass Generator</a>
    <div class="menu">
        <a href="#">Калькулятор</a>
        <a href="#">Генератор</a>
        <a href="#">Статті</a>
        <a href="#">Контакти</a>
    </div>
</div>

<div class="wrapper">
<div class="box">
    <h2>🔐 Генератор паролів</h2>

    <div class="options">
        <label><input type="checkbox" id="numbers" checked> Цифри</label>
        <label><input type="checkbox" id="letters" checked> Букви</label>
        <label><input type="checkbox" id="symbols"> Спецсимволи</label>
    </div>

    <label>Довжина пароля</label>
    <input type="number" id="length" value="12" min="4" max="64">

    <button class="generate-btn" onclick="generate()">Згенерувати</button>

    <div class="output">
        <input type="text" id="password" readonly>
        <button class="copy-btn" onclick="copyPass()">📋</button>
    </div>

    <div class="msg" id="msg"></div>
</div>
</div>

<script>
function generate() {
    let length = parseInt(document.getElementById('length').value);

    let useNumbers = document.getElementById('numbers').checked;
    let useLetters = document.getElementById('letters').checked;
    let useSymbols = document.getElementById('symbols').checked;

    let chars = '';

    if (useNumbers) chars += '0123456789';
    if (useLetters) chars += 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (useSymbols) chars += '!@#$%^&*()_+{}[]<>?';

    if (chars === '') {
        alert('Вибери хоча б один тип символів');
        return;
    }

    let password = '';

    for (let i = 0; i < length; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }

    document.getElementById('password').value = password;
    document.getElementById('msg').innerText = '';
}

function copyPass() {
    let pass = document.getElementById('password').value;

    if (!pass) return;

    navigator.clipboard.writeText(pass).then(() => {
        let msg = document.getElementById('msg');
        msg.innerText = '✅ Скопійовано!';
        
        setTimeout(() => {
            msg.innerText = '';
        }, 1500);
    });
}
</script>

</body>
</html>