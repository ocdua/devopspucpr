<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Inicializa o banco de dados na sessão
if (!isset($_SESSION['tarefas'])) {
    $_SESSION['tarefas'] = [];
}

// Manipulador de requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        // CORREÇÃO 1: Usa empty() para forçar a criação do uniqid() se o ID vier vazio do form
        $id = !empty($_POST['id']) ? $_POST['id'] : uniqid();
        
        // CORREÇÃO 2: Preserva o estado atual da tarefa (se existir) para não zerar ao editar
        $tarefaExistente = $_SESSION['tarefas'][$id] ?? [];

        $task = [
            'id' => $id,
            'text' => htmlspecialchars($_POST['texto']),
            'priority' => $_POST['priority'] ?? 'normal',
            'start_date' => $_POST['start_date'] ?? '',
            'due_date' => $_POST['due_date'] ?? '',
            // Mantém os status anteriores se for edição
            'highlighted' => $tarefaExistente['highlighted'] ?? false,
            'completed' => $tarefaExistente['completed'] ?? false,
        ];
        
        $_SESSION['tarefas'][$id] = $task;
    } 
    elseif ($action === 'delete') {
        unset($_SESSION['tarefas'][$_POST['id']]);
    } 
    elseif ($action === 'bulk_delete') {
        $ids = json_decode($_POST['ids'], true);
        foreach ($ids as $id) {
            unset($_SESSION['tarefas'][$id]);
        }
    } 
    elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        if (isset($_SESSION['tarefas'][$id])) {
            $_SESSION['tarefas'][$id]['completed'] = !$_SESSION['tarefas'][$id]['completed'];
        }
    }
    elseif ($action === 'toggle_highlight') {
        $id = $_POST['id'];
        if (isset($_SESSION['tarefas'][$id])) {
            $_SESSION['tarefas'][$id]['highlighted'] = !$_SESSION['tarefas'][$id]['highlighted'];
        }
    }
    elseif ($action === 'reorder') {
        $orderIds = json_decode($_POST['order'], true);
        $newOrder = [];
        foreach ($orderIds as $id) {
            if (isset($_SESSION['tarefas'][$id])) {
                $newOrder[$id] = $_SESSION['tarefas'][$id];
            }
        }
        $_SESSION['tarefas'] = $newOrder;
    }
    
    // Retorna a lista atualizada
    echo json_encode(['status' => 'success', 'data' => array_values($_SESSION['tarefas'])]);
    exit;
}

// Calcula estatísticas para carregamento inicial
$total = count($_SESSION['tarefas']);
$completed = count(array_filter($_SESSION['tarefas'], fn($t) => $t['completed']));
$progress = $total > 0 ? round(($completed / $total) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevOps Pro Task Manager</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <button id="theme-toggle" class="btn-icon theme-btn"><i class="fas fa-moon"></i></button>

    <div class="container">
        <header>
            <h1><i class="fas fa-rocket"></i> DevOps Tasks</h1>
            <div class="progress-container">
                <div class="progress-bar" id="progress-bar" style="width: <?= $progress ?>%;"></div>
            </div>
            <p id="progress-text" class="text-muted"><?= $progress ?>% Concluído</p>
        </header>

        <div class="controls-bar">
            <button id="btn-select-all" class="btn btn-secondary">Selecionar Tudo</button>
            <button id="btn-bulk-delete" class="btn btn-danger" style="display: none;"><i class="fas fa-trash-alt"></i> Apagar Selecionados</button>
        </div>

        <form id="task-form" class="task-form">
            <input type="hidden" id="task-id">
            <div class="input-group">
                <input type="text" id="task-text" placeholder="O que precisa ser feito?" required autocomplete="off">
                <button type="submit" class="btn btn-primary" id="btn-submit"><i class="fas fa-plus"></i></button>
            </div>
            
            <div class="advanced-options">
                <select id="task-priority">
                    <option value="baixa">Baixa Prioridade</option>
                    <option value="normal" selected>Normal</option>
                    <option value="alta">Alta Prioridade</option>
                </select>
                <input type="date" id="task-start" title="Data de Início">
                <input type="date" id="task-due" title="Data de Entrega (Due Date)">
            </div>
        </form>

        <ul id="task-list" class="task-list">
            <?php foreach ($_SESSION['tarefas'] as $task): ?>
                <?php 
                    $isOverdue = !empty($task['due_date']) && strtotime($task['due_date']) < strtotime('today') && !$task['completed'];
                    $priorityClass = 'prio-' . $task['priority'];
                ?>
                <li class="task-item <?= $task['completed'] ? 'completed' : '' ?> <?= $task['highlighted'] ? 'highlighted' : '' ?> <?= $isOverdue ? 'overdue' : '' ?>" data-id="<?= $task['id'] ?>" draggable="true">
                    <i class="fas fa-grip-vertical drag-handle"></i>
                    
                    <input type="checkbox" class="task-select" value="<?= $task['id'] ?>" title="Selecionar">
                    
                    <div class="task-content">
                        <div class="task-header">
                            <span class="task-text" onclick="toggleStatus('<?= $task['id'] ?>')" style="cursor: pointer;"><?= htmlspecialchars($task['text']) ?></span>
                            <span class="badge <?= $priorityClass ?>"><?= ucfirst($task['priority']) ?></span>
                            <?php if ($task['highlighted']): ?>
                                <i class="fas fa-star star-icon text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <div class="task-meta">
                            <?php if ($task['start_date']): ?><span><i class="fas fa-play"></i> <?= date('d/m', strtotime($task['start_date'])) ?></span><?php endif; ?>
                            <?php if ($task['due_date']): ?><span><i class="fas fa-flag-checkered"></i> <?= date('d/m', strtotime($task['due_date'])) ?></span><?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="task-actions">
                        <button class="btn-icon <?= $task['completed'] ? 'text-muted' : 'text-success' ?>" onclick="toggleStatus('<?= $task['id'] ?>')" title="<?= $task['completed'] ? 'Desfazer' : 'Concluir' ?>">
                            <i class="fas <?= $task['completed'] ? 'fa-undo' : 'fa-check' ?>"></i>
                        </button>
                        
                        <button class="btn-icon" onclick="toggleHighlight('<?= $task['id'] ?>')" title="Destacar"><i class="<?= $task['highlighted'] ? 'fas' : 'far' ?> fa-star"></i></button>
                        <button class="btn-icon" onclick="editTask('<?= $task['id'] ?>')" title="Editar"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon text-danger" onclick="deleteTask('<?= $task['id'] ?>')" title="Apagar"><i class="fas fa-trash"></i></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script>
        // Funcionalidades AJAX e DOM
        async function sendAction(data) {
            const formData = new FormData();
            for (const key in data) { formData.append(key, data[key]); }
            const response = await fetch('', { method: 'POST', body: formData }); // alterado para '' pega o próprio arquivo
            if (response.ok) location.reload(); 
        }

        document.getElementById('task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('task-id').value;
            const text = document.getElementById('task-text').value;
            const priority = document.getElementById('task-priority').value;
            const start = document.getElementById('task-start').value;
            const due = document.getElementById('task-due').value;
            
            sendAction({ action: id ? 'edit' : 'add', id: id, texto: text, priority: priority, start_date: start, due_date: due });
        });

        function toggleStatus(id) {
            // Pequeno truque para não disparar confete se estiver apenas desmarcando
            const item = document.querySelector(`li[data-id="${id}"]`);
            if (item && !item.classList.contains('completed')) {
                triggerConfetti();
            }
            sendAction({ action: 'toggle_status', id: id });
        }

        function toggleHighlight(id) { sendAction({ action: 'toggle_highlight', id: id }); }
        function deleteTask(id) { if(confirm('Excluir esta tarefa?')) sendAction({ action: 'delete', id: id }); }
        
        function editTask(id) {
            const li = document.querySelector(`li[data-id="${id}"]`);
            document.getElementById('task-id').value = id;
            document.getElementById('task-text').value = li.querySelector('.task-text').innerText;
            document.getElementById('btn-submit').innerHTML = '<i class="fas fa-save"></i>';
            document.getElementById('task-text').focus();
        }

        // Bulk Actions
        const checkboxes = document.querySelectorAll('.task-select');
        const bulkDeleteBtn = document.getElementById('btn-bulk-delete');
        
        document.getElementById('btn-select-all').addEventListener('click', () => {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            checkboxes.forEach(c => c.checked = !allChecked);
            toggleBulkButton();
        });

        checkboxes.forEach(c => c.addEventListener('change', toggleBulkButton));

        function toggleBulkButton() {
            const hasChecked = Array.from(checkboxes).some(c => c.checked);
            bulkDeleteBtn.style.display = hasChecked ? 'inline-block' : 'none';
        }

        bulkDeleteBtn.addEventListener('click', () => {
            const ids = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
            if(confirm(`Apagar ${ids.length} tarefas selecionadas?`)) {
                sendAction({ action: 'bulk_delete', ids: JSON.stringify(ids) });
            }
        });

        // Drag and Drop
        const list = document.getElementById('task-list');
        let draggedItem = null;

        list.addEventListener('dragstart', e => {
            draggedItem = e.target.closest('li');
            if(draggedItem) setTimeout(() => draggedItem.style.opacity = '0.5', 0);
        });

        list.addEventListener('dragend', e => {
            if(!draggedItem) return;
            draggedItem.style.opacity = '1';
            draggedItem = null;
            const newOrder = Array.from(list.children).map(li => li.dataset.id);
            sendAction({ action: 'reorder', order: JSON.stringify(newOrder) });
        });

        list.addEventListener('dragover', e => {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            const li = e.target.closest('li');
            if (li && draggedItem && li !== draggedItem) {
                if (afterElement == null) {
                    list.appendChild(draggedItem);
                } else {
                    list.insertBefore(draggedItem, afterElement);
                }
            }
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child }
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // Dark Mode
        const themeBtn = document.getElementById('theme-toggle');
        const icon = themeBtn.querySelector('i');
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            icon.classList.replace('fa-moon', 'fa-sun');
        }
        
        themeBtn.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            icon.classList.replace(isDark ? 'fa-moon' : 'fa-sun', isDark ? 'fa-sun' : 'fa-moon');
        });

        // Confetti
        function triggerConfetti() {
            for (let i = 0; i < 30; i++) {
                let confetti = document.createElement('div');
                confetti.classList.add('confetti');
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confetti.style.backgroundColor = `hsl(${Math.random() * 360}, 100%, 50%)`;
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 5000);
            }
        }
    </script>
</body>
</html>
