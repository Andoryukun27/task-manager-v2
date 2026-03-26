<?php require_once "auth.php";
requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="app-shell">

        <header class="app-header">
            <div class="header-inner">
                <div class="brand">
                    <span class="brand-icon">&#10003;</span>
                    <h1>TaskFlow</h1>
                </div>
                <p class="brand-sub">Stay on top of everything</p>
                <div class="header-user">
                    <span class="header-username">👤 <?= htmlspecialchars($_SESSION["username"]) ?></span>
                    <a href="logout.php" class="btn btn-ghost btn-sm">Sign out</a>
                </div>
            </div>
        </header>

        <main class="app-main">

            <section class="form-section">
                <div class="form-card">
                    <h2 class="form-title" id="form-title">Add New Task</h2>

                    <input type="hidden" id="task-id">

                    <div class="field-group">
                        <label for="task-title">Title <span class="required">*</span></label>
                        <input type="text" id="task-title" placeholder="What needs to be done?">
                    </div>

                    <div class="field-group">
                        <label for="task-desc">Description</label>
                        <textarea id="task-desc" placeholder="Add details..." rows="3"></textarea>
                    </div>

                    <div class="field-group">
                        <label for="task-due">Due Date</label>
                        <input type="date" id="task-due">
                    </div>

                    <div class="field-group">
                        <label for="task-priority">Priority</label>
                        <select id="task-priority">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button id="cancel-btn" class="btn btn-ghost" style="display:none">Cancel</button>
                        <button id="submit-btn" class="btn btn-primary" onclick="submitTask()">Add Task</button>
                    </div>
                </div>
            </section>

            <section class="tasks-section">

                <div class="tasks-toolbar">
                    <div class="toolbar-row">
                        <input type="text" id="search-input" placeholder="🔍 Search tasks..." oninput="searchTasks()">
                        <select id="sort-select"
                            onchange="document.getElementById('search-input').value=''; loadTasks(); loadCounts();">
                            <option value="created_at">Date Created</option>
                            <option value="due_date">Due Date</option>
                            <option value="title">Title</option>
                        </select>
                    </div>
                    <div class="filter-bar">
                        <button class="filter-btn active" data-filter="all" onclick="setFilter(this)">All</button>
                        <button class="filter-btn" data-filter="pending" onclick="setFilter(this)">Pending</button>
                        <button class="filter-btn" data-filter="completed" onclick="setFilter(this)">Completed</button>
                    </div>
                </div>

                <div id="task-list" class="task-list">
                    <p class="no-results" id="no-results">No tasks match your search.</p>
                    <div class="loading">Loading tasks...</div>
                </div>

            </section>

        </main>

    </div>

    <div id="delete-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-icon">🗑</div>
            <h3 class="modal-title">Delete this task?</h3>
            <p class="modal-body">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-ghost" id="modal-cancel">Cancel</button>
                <button class="btn btn-danger" id="modal-delete">Delete</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="js/app.js"></script>
</body>

</html>