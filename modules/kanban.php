<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Auto-create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `status` ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
    `order_num` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if (!$title) {
            echo json_encode(['success' => false, 'message' => 'Пуста назва']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO tasks (title, status, order_num, user_id) VALUES (?, 'todo', 0, ?)");
        if ($stmt->execute([$title, $_SESSION['user_id']])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'title' => htmlspecialchars($title)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'todo';
        if (in_array($status, ['todo', 'in_progress', 'done'])) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
            echo json_encode(['success' => $stmt->execute([$status, $id, $_SESSION['user_id']])]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        echo json_encode(['success' => $stmt->execute([$id, $_SESSION['user_id']])]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Невідома дія']);
    exit;
}

// Fetch tasks for rendering
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY id ASC");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tasksByStatus = [
    'todo' => [],
    'in_progress' => [],
    'done' => []
];

foreach ($tasks as $task) {
    if (isset($tasksByStatus[$task['status']])) {
        $tasksByStatus[$task['status']][] = $task;
    }
}

$pageTitle = 'Канбан-дошка';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
.kanban-column {
    background-color: #212529;
    border-radius: 8px;
    padding: 15px;
    min-height: 500px;
    border: 1px solid #495057;
}
.kanban-card {
    background-color: #343a40;
    border: 1px solid #495057;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
    cursor: grab;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kanban-card:active {
    cursor: grabbing;
}
.kanban-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    transform: translateY(-2px);
    border-color: #6c757d;
}
.kanban-card-text {
    flex-grow: 1;
    word-break: break-word;
    color: #e9ecef;
}
.btn-delete-task {
    color: #dc3545;
    background: none;
    border: none;
    padding: 0 0 0 10px;
    opacity: 0.5;
    transition: opacity 0.2s;
}
.btn-delete-task:hover {
    opacity: 1;
}
.sortable-ghost {
    opacity: 0.3;
    background-color: #495057;
}
</style>

<div class="container-fluid py-5 px-4 content">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2 class="text-light"><i class="bi bi-kanban text-info"></i> Канбан-дошка</h2>
            <p class="text-secondary">Організуйте свої завдання. Перетягуйте їх між колонками мишкою.</p>
        </div>
    </div>

    <!-- Add Task Form -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-6 col-lg-4">
            <form id="addTaskForm" class="d-flex">
                <input type="text" id="taskTitle" class="form-control bg-dark text-light border-secondary me-2" placeholder="Що потрібно зробити?" required autocomplete="off">
                <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Додати</button>
            </form>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="row g-4 pb-5">
        
        <!-- To Do -->
        <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
                <h5 class="mb-0 text-light me-2">Зробити</h5>
                <span class="badge bg-secondary rounded-pill" id="count-todo"><?= count($tasksByStatus['todo']) ?></span>
            </div>
            <div class="kanban-column" id="col-todo" data-status="todo">
                <?php foreach($tasksByStatus['todo'] as $t): ?>
                    <div class="kanban-card" data-id="<?= $t['id'] ?>">
                        <span class="kanban-card-text"><?= htmlspecialchars($t['title']) ?></span>
                        <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- In Progress -->
        <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
                <h5 class="mb-0 text-light me-2">В процесі</h5>
                <span class="badge bg-primary rounded-pill" id="count-in_progress"><?= count($tasksByStatus['in_progress']) ?></span>
            </div>
            <div class="kanban-column border-primary" id="col-in_progress" data-status="in_progress" style="border-width: 2px; border-style: dashed;">
                <?php foreach($tasksByStatus['in_progress'] as $t): ?>
                    <div class="kanban-card" data-id="<?= $t['id'] ?>">
                        <span class="kanban-card-text"><?= htmlspecialchars($t['title']) ?></span>
                        <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Done -->
        <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
                <h5 class="mb-0 text-light me-2">Готово</h5>
                <span class="badge bg-success rounded-pill" id="count-done"><?= count($tasksByStatus['done']) ?></span>
            </div>
            <div class="kanban-column border-success" id="col-done" data-status="done">
                <?php foreach($tasksByStatus['done'] as $t): ?>
                    <div class="kanban-card" data-id="<?= $t['id'] ?>" style="opacity: 0.7;">
                        <span class="kanban-card-text text-decoration-line-through"><?= htmlspecialchars($t['title']) ?></span>
                        <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
// Initialization of Sortable logic
document.addEventListener("DOMContentLoaded", () => {
    const cols = ['todo', 'in_progress', 'done'];
    
    cols.forEach(status => {
        const el = document.getElementById('col-' + status);
        new Sortable(el, {
            group: 'shared', // set both lists to same group
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                const itemEl = evt.item;
                const taskId = itemEl.getAttribute('data-id');
                const newStatus = evt.to.getAttribute('data-status');
                
                // If status changed, update backend
                if (evt.from !== evt.to) {
                    updateTaskStatus(taskId, newStatus);
                    updateCounters();
                    applyStyling(itemEl, newStatus);
                }
            },
        });
    });
});

// Update Counters
function updateCounters() {
    ['todo', 'in_progress', 'done'].forEach(status => {
        const count = document.getElementById('col-' + status).children.length;
        document.getElementById('count-' + status).innerText = count;
    });
}

// Apply styling based on status (e.g. strikethrough for Done)
function applyStyling(cardEl, status) {
    const textEl = cardEl.querySelector('.kanban-card-text');
    if (status === 'done') {
        cardEl.style.opacity = '0.7';
        textEl.classList.add('text-decoration-line-through');
    } else {
        cardEl.style.opacity = '1';
        textEl.classList.remove('text-decoration-line-through');
    }
}

// AJAX: Update Status
function updateTaskStatus(id, status) {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('id', id);
    fd.append('status', status);

    fetch('kanban.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) toastr.error('Помилка збереження статусу');
        })
        .catch(() => toastr.error('Мережева помилка'));
}

// AJAX: Add Task
document.getElementById('addTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('taskTitle');
    const title = input.value.trim();
    if (!title) return;

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('title', title);

    fetch('kanban.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const col = document.getElementById('col-todo');
                const html = `
                    <div class="kanban-card" data-id="${d.id}">
                        <span class="kanban-card-text">${d.title}</span>
                        <button class="btn-delete-task" onclick="deleteTask(this, ${d.id})"><i class="bi bi-trash"></i></button>
                    </div>
                `;
                col.insertAdjacentHTML('beforeend', html);
                input.value = '';
                updateCounters();
            } else {
                toastr.error(d.message || 'Помилка додавання');
            }
        })
        .catch(() => toastr.error('Мережева помилка'));
});

// AJAX: Delete Task
window.deleteTask = function(btn, id) {
    if (!confirm('Видалити це завдання?')) return;
    
    const card = btn.closest('.kanban-card');
    card.remove();
    updateCounters();

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    fetch('kanban.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toastr.success('Завдання видалено');
            } else {
                toastr.error('Помилка видалення');
            }
        })
        .catch(() => toastr.error('Мережева помилка'));
}
</script>

<?php require_once '../includes/footer.php'; ?>
