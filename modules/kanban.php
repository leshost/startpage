<?php

if (!isLoggedIn()) {
    header("Location: /?module=login");
    exit;
}

// Auto-create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `kanban_projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `project_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `status` ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
    `order_num` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try {
    $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `user_id` INT NOT NULL AFTER `id`");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `user_id`");
} catch (PDOException $e) {}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if (!$title) {
            echo json_encode(['success' => false, 'message' => 'Пуста назва']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO tasks (title, status, order_num, user_id, project_id) VALUES (?, 'todo', 0, ?, ?)");
        if ($stmt->execute([$title, $_SESSION['user_id'], $project_id])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'title' => htmlspecialchars($title)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }

    if ($action === 'add_project') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Пуста назва']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO kanban_projects (user_id, name) VALUES (?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $name])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => htmlspecialchars($name)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка бази даних']);
        }
        exit;
    }

    if ($action === 'delete_project') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE kanban_projects SET is_deleted = 1 WHERE id = ? AND user_id = ?");
        echo json_encode(['success' => $stmt->execute([$id, $_SESSION['user_id']])]);
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

// Fetch projects
$stmt = $pdo->prepare("SELECT id, name FROM kanban_projects WHERE user_id = ? AND is_deleted = 0 ORDER BY created_at ASC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active project
$active_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$active_project_name = "Загальна дошка";

if ($active_project_id) {
    $found = false;
    foreach ($projects as $p) {
        if ($p['id'] == $active_project_id) {
            $found = true;
            $active_project_name = $p['name'];
            break;
        }
    }
    if (!$found) $active_project_id = null;
}

// Task Counts per project
$stmt = $pdo->prepare("SELECT project_id, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY project_id");
$stmt->execute([$_SESSION['user_id']]);
$task_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $task_counts[$row['project_id'] ?: 'general'] = $row['count'];
}

// Fetch tasks for active project
if ($active_project_id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND project_id = ? ORDER BY id ASC");
    $stmt->execute([$_SESSION['user_id'], $active_project_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND project_id IS NULL ORDER BY id ASC");
    $stmt->execute([$_SESSION['user_id']]);
}
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
    <div class="row">
        <!-- Sidebar: Projects -->
        <div class="col-md-3 col-lg-2 mb-4">
            <h5 class="text-light mb-3"><i class="bi bi-folder text-warning"></i> Проекти</h5>
            <div class="list-group list-group-flush bg-dark rounded border border-secondary mb-3" style="overflow: hidden;">
                <a href="/?module=kanban" class="list-group-item list-group-item-action border-secondary <?= !$active_project_id ? 'active bg-primary text-white' : 'bg-dark text-light' ?>">
                    Загальна дошка <span class="badge <?= !$active_project_id ? 'bg-light text-primary' : 'bg-secondary' ?> float-end"><?= $task_counts['general'] ?? 0 ?></span>
                </a>
                <?php foreach($projects as $p): ?>
                <div class="list-group-item list-group-item-action border-secondary d-flex justify-content-between align-items-center <?= $active_project_id == $p['id'] ? 'active bg-primary text-white' : 'bg-dark text-light' ?>">
                    <a href="/?module=kanban&project_id=<?= $p['id'] ?>" class="text-decoration-none flex-grow-1 <?= $active_project_id == $p['id'] ? 'text-white' : 'text-light' ?>">
                        <?= htmlspecialchars($p['name']) ?> <span class="<?= $active_project_id == $p['id'] ? 'text-light' : 'text-secondary' ?> small">(<?= $task_counts[$p['id']] ?? 0 ?>)</span>
                    </a>
                    <button class="btn btn-sm btn-outline-danger border-0 p-1 <?= $active_project_id == $p['id'] ? 'text-white' : '' ?>" onclick="deleteProject(<?= $p['id'] ?>)" title="Видалити проект"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <form id="addProjectForm" class="d-flex">
                <input type="text" id="projectName" class="form-control form-control-sm bg-dark text-light border-secondary me-1" placeholder="Новий проект..." required autocomplete="off">
                <button type="submit" class="btn btn-sm btn-success" title="Створити проект"><i class="bi bi-plus-lg"></i></button>
            </form>
        </div>

        <!-- Main Board -->
        <div class="col-md-9 col-lg-10">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="text-light"><i class="bi bi-kanban text-info"></i> <?= htmlspecialchars($active_project_name) ?></h2>
                    <p class="text-secondary">Організуйте свої завдання. Перетягуйте їх між колонками мишкою.</p>
                </div>
            </div>

            <!-- Add Task Form -->
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 col-lg-6">
                    <form id="addTaskForm" class="d-flex">
                        <input type="hidden" id="currentProjectId" value="<?= $active_project_id ?: '' ?>">
                        <input type="text" id="taskTitle" class="form-control bg-dark text-light border-secondary me-2" placeholder="Що потрібно зробити?" required autocomplete="off">
                        <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Додати завдання</button>
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

    </div> <!-- End Kanban Board Row -->
    </div> <!-- End Main Board Col -->
    </div> <!-- End Main Row -->
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

    fetch(window.location.href, { method: 'POST', body: fd })
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

    const projectId = document.getElementById('currentProjectId').value;

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('title', title);
    if (projectId) fd.append('project_id', projectId);

    fetch(window.location.href, { method: 'POST', body: fd })
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

// AJAX: Add Project
document.getElementById('addProjectForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('projectName');
    const name = input.value.trim();
    if (!name) return;

    const fd = new FormData();
    fd.append('action', 'add_project');
    fd.append('name', name);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toastr.success('Проект створено');
                setTimeout(() => window.location.href = '/?module=kanban&project_id=' + d.id, 500);
            } else {
                toastr.error(d.message || 'Помилка');
            }
        })
        .catch(() => toastr.error('Мережева помилка'));
});

// AJAX: Delete Project
window.deleteProject = function(id) {
    if (!confirm('Видалити цей проект? Завдання залишаться в базі, але проект буде сховано.')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_project');
    fd.append('id', id);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toastr.success('Проект видалено');
                setTimeout(() => window.location.href = '/?module=kanban', 500);
            } else {
                toastr.error('Помилка видалення');
            }
        })
        .catch(() => toastr.error('Мережева помилка'));
}

// AJAX: Delete Task
window.deleteTask = function(btn, id) {
    if (!confirm('Видалити це завдання?')) return;
    
    const card = btn.closest('.kanban-card');
    card.remove();
    updateCounters();

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    fetch(window.location.href, { method: 'POST', body: fd })
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

