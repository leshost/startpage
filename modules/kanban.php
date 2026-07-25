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

// Kanbans sharing support
$pdo->exec("CREATE TABLE IF NOT EXISTS `kanban_shares` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `task_views` (
    `task_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`task_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try { $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `created_by` INT DEFAULT NULL AFTER `project_id`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `updated_by` INT DEFAULT NULL AFTER `created_by`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER `updated_by`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `is_deleted` BOOLEAN DEFAULT 0 AFTER `updated_at`"); } catch (PDOException $e) {}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    verifyCsrf();
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if (!$title) { echo json_encode(['success' => false, 'message' => 'Пуста назва']); exit; }

        // Determine owner and check access
        $owner_id = $_SESSION['user_id'];
        if ($project_id) {
            $stmt = $pdo->prepare("SELECT p.user_id as owner_id, s.user_id as share_id FROM kanban_projects p LEFT JOIN kanban_shares s ON p.id = s.project_id AND s.user_id = ? WHERE p.id = ? AND p.is_deleted = 0");
            $stmt->execute([$_SESSION['user_id'], $project_id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res || ($res['owner_id'] != $_SESSION['user_id'] && empty($res['share_id']))) {
                echo json_encode(['success' => false, 'message' => 'Немає доступу до проекту']); exit;
            }
            $owner_id = $res['owner_id'];
        }

        $stmt = $pdo->prepare("INSERT INTO tasks (title, status, order_num, user_id, project_id, created_by, updated_by) VALUES (?, 'todo', 0, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $owner_id, $project_id, $_SESSION['user_id'], $_SESSION['user_id']])) {
            $taskId = $pdo->lastInsertId();
            
            // Get username for created_by
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $username = $stmt->fetchColumn();

            echo json_encode([
                'success' => true, 
                'id' => $taskId, 
                'title' => htmlspecialchars($title),
                'created_by_name' => htmlspecialchars($username)
            ]);
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
        $stmt = $pdo->prepare("SELECT user_id FROM kanban_projects WHERE id = ?");
        $stmt->execute([$id]);
        $owner_id = $stmt->fetchColumn();
        
        if ($owner_id == $_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE kanban_projects SET is_deleted = 1 WHERE id = ?");
            echo json_encode(['success' => $stmt->execute([$id])]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM kanban_shares WHERE project_id = ? AND user_id = ?");
            echo json_encode(['success' => $stmt->execute([$id, $_SESSION['user_id']])]);
        }
        exit;
    }

    if ($action === 'edit_task') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if (!$title) { echo json_encode(['success' => false, 'message' => 'Пуста назва']); exit; }

        // Validate access
        $stmt = $pdo->prepare("SELECT t.project_id, t.user_id FROM tasks t WHERE t.id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hasAccess = false;
        if ($task) {
            if ($task['user_id'] == $_SESSION['user_id']) $hasAccess = true;
            elseif ($task['project_id']) {
                $stmt = $pdo->prepare("SELECT 1 FROM kanban_shares WHERE project_id = ? AND user_id = ?");
                $stmt->execute([$task['project_id'], $_SESSION['user_id']]);
                if ($stmt->fetchColumn()) $hasAccess = true;
            }
        }
        
        if ($hasAccess) {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, updated_by = ? WHERE id = ?");
            $success = $stmt->execute([$title, $_SESSION['user_id'], $id]);
            
            $stmt = $pdo->prepare("SELECT u.username, DATE_FORMAT(t.updated_at, '%H:%i') as up_time FROM tasks t JOIN users u ON t.updated_by = u.id WHERE t.id = ?");
            $stmt->execute([$id]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => $success, 'title' => htmlspecialchars($title), 'updated_by_name' => $meta['username'], 'updated_at' => $meta['up_time']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Недостатньо прав']);
        }
        exit;
    }

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'todo';
        if (in_array($status, ['todo', 'in_progress', 'done'])) {
            // Validate access
            $stmt = $pdo->prepare("SELECT t.project_id, t.user_id FROM tasks t WHERE t.id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $hasAccess = false;
            if ($task) {
                if ($task['user_id'] == $_SESSION['user_id']) $hasAccess = true;
                elseif ($task['project_id']) {
                    $stmt = $pdo->prepare("SELECT 1 FROM kanban_shares WHERE project_id = ? AND user_id = ?");
                    $stmt->execute([$task['project_id'], $_SESSION['user_id']]);
                    if ($stmt->fetchColumn()) $hasAccess = true;
                }
            }
            
            if ($hasAccess) {
                $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_by = ? WHERE id = ?");
                $success = $stmt->execute([$status, $_SESSION['user_id'], $id]);
                
                $stmt = $pdo->prepare("SELECT u.username, DATE_FORMAT(t.updated_at, '%H:%i') as up_time FROM tasks t JOIN users u ON t.updated_by = u.id WHERE t.id = ?");
                $stmt->execute([$id]);
                $meta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => $success, 'updated_by_name' => $meta['username'], 'updated_at' => $meta['up_time']]);
            } else {
                echo json_encode(['success' => false]);
            }
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Validate access
        $stmt = $pdo->prepare("SELECT t.project_id, t.user_id FROM tasks t WHERE t.id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hasAccess = false;
        if ($task) {
            if ($task['user_id'] == $_SESSION['user_id']) $hasAccess = true;
            elseif ($task['project_id']) {
                $stmt = $pdo->prepare("SELECT 1 FROM kanban_shares WHERE project_id = ? AND user_id = ?");
                $stmt->execute([$task['project_id'], $_SESSION['user_id']]);
                if ($stmt->fetchColumn()) $hasAccess = true;
            }
        }
        
        if ($hasAccess) {
            $stmt = $pdo->prepare("UPDATE tasks SET is_deleted = 1, updated_by = ? WHERE id = ?");
            echo json_encode(['success' => $stmt->execute([$_SESSION['user_id'], $id])]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    if ($action === 'get_shareable_friends') {
        $project_id = (int)$_POST['project_id'];
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, 
                   (SELECT COUNT(*) FROM kanban_shares WHERE project_id = ? AND user_id = u.id) as is_shared
            FROM users u 
            JOIN friends f ON (u.id = f.user_id OR u.id = f.friend_id) 
            WHERE (f.user_id = ? OR f.friend_id = ?) 
              AND f.status = 'accepted' 
              AND u.id != ?
        ");
        $stmt->execute([$project_id, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'friends' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($action === 'toggle_share') {
        $project_id = (int)$_POST['project_id'];
        $friend_id = (int)$_POST['friend_id'];
        
        // Ensure owner
        $stmt = $pdo->prepare("SELECT user_id FROM kanban_projects WHERE id = ?");
        $stmt->execute([$project_id]);
        if ($stmt->fetchColumn() != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Недостатньо прав']); exit;
        }
        
        // Check if shared
        $stmt = $pdo->prepare("SELECT id FROM kanban_shares WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$project_id, $friend_id]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("DELETE FROM kanban_shares WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$project_id, $friend_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO kanban_shares (project_id, user_id) VALUES (?, ?)");
            $stmt->execute([$project_id, $friend_id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Невідома дія']);
    exit;
}

// Fetch projects
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.user_id, IF(p.user_id = ?, 1, 0) as is_owner 
    FROM kanban_projects p 
    LEFT JOIN kanban_shares s ON p.id = s.project_id 
    WHERE (p.user_id = ? OR s.user_id = ?) AND p.is_deleted = 0 
    GROUP BY p.id 
    ORDER BY p.created_at ASC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active project
$active_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$active_project_name = "Загальна дошка";
$is_active_project_owner = true;

if ($active_project_id) {
    $found = false;
    foreach ($projects as $p) {
        if ($p['id'] == $active_project_id) {
            $found = true;
            $active_project_name = $p['name'];
            $is_active_project_owner = (bool)$p['is_owner'];
            break;
        }
    }
    if (!$found) $active_project_id = null;
}

// Register views
if ($active_project_id) {
    $stmt = $pdo->prepare("INSERT INTO task_views (task_id, user_id, viewed_at) SELECT id, ?, NOW() FROM tasks WHERE project_id = ? AND IFNULL(is_deleted, 0) = 0 ON DUPLICATE KEY UPDATE viewed_at = NOW()");
    $stmt->execute([$_SESSION['user_id'], $active_project_id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO task_views (task_id, user_id, viewed_at) SELECT id, ?, NOW() FROM tasks WHERE project_id IS NULL AND user_id = ? AND IFNULL(is_deleted, 0) = 0 ON DUPLICATE KEY UPDATE viewed_at = NOW()");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
}

// Task Counts per project
$stmt = $pdo->prepare("
    SELECT t.project_id, COUNT(t.id) as count 
    FROM tasks t 
    LEFT JOIN kanban_projects p ON t.project_id = p.id
    LEFT JOIN kanban_shares s ON p.id = s.project_id
    WHERE (t.user_id = ? OR p.user_id = ? OR s.user_id = ?) 
      AND IFNULL(t.is_deleted, 0) = 0
    GROUP BY t.project_id
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$task_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $task_counts[$row['project_id'] ?: 'general'] = $row['count'];
}

// Fetch tasks for active project
if ($active_project_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.username as created_by_name, u.username as updated_by_name, DATE_FORMAT(t.updated_at, '%H:%i') as up_time,
               (SELECT GROUP_CONCAT(CONCAT(vu.username, ' (', DATE_FORMAT(tv.viewed_at, '%H:%i'), ')') SEPARATOR ', ') 
                FROM task_views tv JOIN users vu ON tv.user_id = vu.id 
                WHERE tv.task_id = t.id AND tv.user_id != t.created_by) as viewed_by_list
        FROM tasks t 
        LEFT JOIN users c ON t.created_by = c.id
        LEFT JOIN users u ON t.updated_by = u.id
        WHERE t.project_id = ? AND IFNULL(t.is_deleted, 0) = 0
        ORDER BY t.id ASC
    ");
    $stmt->execute([$active_project_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, c.username as created_by_name, u.username as updated_by_name, DATE_FORMAT(t.updated_at, '%H:%i') as up_time,
               (SELECT GROUP_CONCAT(CONCAT(vu.username, ' (', DATE_FORMAT(tv.viewed_at, '%H:%i'), ')') SEPARATOR ', ') 
                FROM task_views tv JOIN users vu ON tv.user_id = vu.id 
                WHERE tv.task_id = t.id AND tv.user_id != t.created_by) as viewed_by_list
        FROM tasks t 
        LEFT JOIN users c ON t.created_by = c.id
        LEFT JOIN users u ON t.updated_by = u.id
        WHERE t.user_id = ? AND t.project_id IS NULL AND IFNULL(t.is_deleted, 0) = 0
        ORDER BY t.id ASC
    ");
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
.btn-delete-task, .btn-edit-task {
    background: none;
    border: none;
    padding: 0 0 0 10px;
    opacity: 0.5;
    transition: opacity 0.2s;
}
.btn-delete-task { color: #dc3545; }
.btn-edit-task { color: #0dcaf0; }
.btn-delete-task:hover, .btn-edit-task:hover {
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
                <?php $is_shared = !(bool)$p['is_owner']; ?>
                <div class="list-group-item list-group-item-action border-secondary d-flex justify-content-between align-items-center <?= $active_project_id == $p['id'] ? 'active bg-primary text-white' : 'bg-dark text-light' ?>">
                    <a href="/?module=kanban&project_id=<?= $p['id'] ?>" class="text-decoration-none flex-grow-1 <?= $active_project_id == $p['id'] ? 'text-white' : 'text-light' ?>">
                        <?= $is_shared ? '<i class="bi bi-people-fill text-info me-1"></i> ' : '' ?><?= htmlspecialchars($p['name']) ?> <span class="<?= $active_project_id == $p['id'] ? 'text-light' : 'text-secondary' ?> small">(<?= $task_counts[$p['id']] ?? 0 ?>)</span>
                    </a>
                    <button class="btn btn-sm btn-outline-danger border-0 p-1 <?= $active_project_id == $p['id'] ? 'text-white' : '' ?>" onclick="deleteProject(<?= $p['id'] ?>)" title="<?= $is_shared ? 'Відмовитись від спільного доступу' : 'Видалити проект' ?>"><i class="bi bi-x-lg"></i></button>
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
                <div class="col-12 text-center position-relative">
                    <h2 class="text-light">
                        <i class="bi bi-kanban text-info"></i> <?= htmlspecialchars($active_project_name) ?>
                        <?php if ($active_project_id && $is_active_project_owner): ?>
                            <button class="btn btn-sm btn-outline-info ms-2" onclick="openShareModal(<?= $active_project_id ?>)" title="Спільний доступ"><i class="bi bi-share"></i></button>
                        <?php endif; ?>
                    </h2>
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
                    <div class="kanban-card flex-column align-items-start" data-id="<?= $t['id'] ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <span class="kanban-card-text"><?= htmlspecialchars($t['title']) ?></span>
                            <div class="d-flex text-nowrap ms-2">
                                <button class="btn-edit-task" onclick="editTask(this, <?= $t['id'] ?>)" title="Редагувати"><i class="bi bi-pencil"></i></button>
                                <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)" title="Видалити"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="task-meta w-100 text-muted mt-2" style="font-size: 0.75rem;">
                            Створив: <?= htmlspecialchars($t['created_by_name'] ?? 'Невідомо') ?>
                            <?php if ($t['updated_by_name']): ?>
                                | Змінив: <?= htmlspecialchars($t['updated_by_name']) ?> о <?= htmlspecialchars($t['up_time']) ?>
                            <?php endif; ?>
                            <?php if (!empty($t['viewed_by_list'])): ?>
                                <div class="mt-1 text-info"><i class="bi bi-check-all"></i> Переглянули: <?= htmlspecialchars($t['viewed_by_list']) ?></div>
                            <?php endif; ?>
                        </div>
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
                    <div class="kanban-card flex-column align-items-start" data-id="<?= $t['id'] ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <span class="kanban-card-text"><?= htmlspecialchars($t['title']) ?></span>
                            <div class="d-flex text-nowrap ms-2">
                                <button class="btn-edit-task" onclick="editTask(this, <?= $t['id'] ?>)" title="Редагувати"><i class="bi bi-pencil"></i></button>
                                <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)" title="Видалити"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="task-meta w-100 text-muted mt-2" style="font-size: 0.75rem;">
                            Створив: <?= htmlspecialchars($t['created_by_name'] ?? 'Невідомо') ?>
                            <?php if ($t['updated_by_name']): ?>
                                | Змінив: <?= htmlspecialchars($t['updated_by_name']) ?> о <?= htmlspecialchars($t['up_time']) ?>
                            <?php endif; ?>
                            <?php if (!empty($t['viewed_by_list'])): ?>
                                <div class="mt-1 text-info"><i class="bi bi-check-all"></i> Переглянули: <?= htmlspecialchars($t['viewed_by_list']) ?></div>
                            <?php endif; ?>
                        </div>
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
                    <div class="kanban-card flex-column align-items-start" data-id="<?= $t['id'] ?>" style="opacity: 0.7;">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <span class="kanban-card-text text-decoration-line-through"><?= htmlspecialchars($t['title']) ?></span>
                            <div class="d-flex text-nowrap ms-2">
                                <button class="btn-edit-task" onclick="editTask(this, <?= $t['id'] ?>)" title="Редагувати"><i class="bi bi-pencil"></i></button>
                                <button class="btn-delete-task" onclick="deleteTask(this, <?= $t['id'] ?>)" title="Видалити"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div class="task-meta w-100 text-muted mt-2" style="font-size: 0.75rem;">
                            Створив: <?= htmlspecialchars($t['created_by_name'] ?? 'Невідомо') ?>
                            <?php if ($t['updated_by_name']): ?>
                                | Змінив: <?= htmlspecialchars($t['updated_by_name']) ?> о <?= htmlspecialchars($t['up_time']) ?>
                            <?php endif; ?>
                            <?php if (!empty($t['viewed_by_list'])): ?>
                                <div class="mt-1 text-info"><i class="bi bi-check-all"></i> Переглянули: <?= htmlspecialchars($t['viewed_by_list']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div> <!-- End Kanban Board Row -->
    </div> <!-- End Main Board Col -->
    </div> <!-- End Main Row -->
</div>

<!-- Share Project Modal -->
<div class="modal fade" id="shareProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Спільний доступ до проекту</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">Виберіть друзів, яким ви хочете надати доступ до цього проекту.</p>
                <div id="shareFriendsList" class="list-group list-group-flush bg-dark">
                    <div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div></div>
                </div>
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

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                toastr.error('Помилка збереження статусу');
            } else {
                // Update meta text in the UI
                const card = document.querySelector(`.kanban-card[data-id="${id}"]`);
                if (card && d.updated_by_name) {
                    let metaEl = card.querySelector('.task-meta');
                    if (metaEl) {
                        let text = metaEl.innerHTML;
                        if (text.includes('| Змінив:')) {
                            text = text.replace(/\| Змінив:.*$/, `| Змінив: ${d.updated_by_name} о ${d.updated_at}`);
                        } else {
                            text += ` | Змінив: ${d.updated_by_name} о ${d.updated_at}`;
                        }
                        metaEl.innerHTML = text;
                    }
                }
            }
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

// AJAX: Edit Task
window.editTask = function(btn, id) {
    const card = btn.closest('.kanban-card');
    const textEl = card.querySelector('.kanban-card-text');
    const oldTitle = textEl.innerText;
    
    const newTitle = prompt('Редагувати завдання:', oldTitle);
    if (newTitle === null) return;
    
    const title = newTitle.trim();
    if (!title) {
        toastr.error('Назва не може бути порожньою');
        return;
    }
    if (title === oldTitle) return;

    const fd = new FormData();
    fd.append('action', 'edit_task');
    fd.append('id', id);
    fd.append('title', title);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                textEl.innerText = d.title;
                toastr.success('Завдання оновлено');
                
                // Update meta text in the UI
                if (d.updated_by_name) {
                    let metaEl = card.querySelector('.task-meta');
                    if (metaEl) {
                        let text = metaEl.innerHTML;
                        if (text.includes('| Змінив:')) {
                            text = text.replace(/\| Змінив:.*$/, `| Змінив: ${d.updated_by_name} о ${d.updated_at}`);
                        } else {
                            text += ` | Змінив: ${d.updated_by_name} о ${d.updated_at}`;
                        }
                        metaEl.innerHTML = text;
                    }
                }
            } else {
                toastr.error(d.message || 'Помилка оновлення');
            }
        })
        .catch(() => toastr.error('Мережева помилка'));
}

// Sharing Logic
let shareModal;
window.openShareModal = function(projectId) {
    if (!shareModal) shareModal = new bootstrap.Modal(document.getElementById('shareProjectModal'));
    
    const list = document.getElementById('shareFriendsList');
    list.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div></div>';
    
    shareModal.show();
    
    const fd = new FormData();
    fd.append('action', 'get_shareable_friends');
    fd.append('project_id', projectId);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                list.innerHTML = '';
                if (d.friends.length === 0) {
                    list.innerHTML = '<div class="text-center text-secondary py-3">Немає друзів у списку контактів.</div>';
                    return;
                }
                d.friends.forEach(f => {
                    const isShared = f.is_shared > 0;
                    list.innerHTML += `
                        <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                            <span class="text-light"><i class="bi bi-person-circle text-info me-2"></i> ${f.username}</span>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" onchange="toggleShare(${projectId}, ${f.id}, this)" ${isShared ? 'checked' : ''}>
                            </div>
                        </div>
                    `;
                });
            } else {
                list.innerHTML = '<div class="text-danger py-3">Помилка завантаження</div>';
            }
        });
};

window.toggleShare = function(projectId, friendId, checkbox) {
    checkbox.disabled = true;
    const fd = new FormData();
    fd.append('action', 'toggle_share');
    fd.append('project_id', projectId);
    fd.append('friend_id', friendId);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            checkbox.disabled = false;
            if (!d.success) {
                checkbox.checked = !checkbox.checked;
                toastr.error(d.message || 'Помилка');
            } else {
                toastr.success('Доступ змінено');
            }
        })
        .catch(() => {
            checkbox.disabled = false;
            checkbox.checked = !checkbox.checked;
            toastr.error('Помилка мережі');
        });
};
</script>

