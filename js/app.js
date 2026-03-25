let currentFilter = "all";
let editingId = null;

async function loadTasks() {
    const list = document.getElementById("task-list");
    list.innerHTML = '<div class="loading">Loading tasks...</div>';

    const res = await fetch("tasks.php?action=read&filter=" + currentFilter);
    const tasks = await res.json();

    if (!tasks.length) {
        list.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9744;</div><p>No tasks here yet.</p></div>';
        return;
    }

    list.innerHTML = "";
    tasks.forEach(task => {
        list.appendChild(buildTaskCard(task));
    });
}

async function loadCounts() {
    const res = await fetch("tasks.php?action=counts");
    const counts = await res.json();

    document.querySelectorAll(".filter-btn").forEach(btn => {
        const filter = btn.dataset.filter;
        const n = counts[filter] ?? 0;
        btn.textContent = filter.charAt(0).toUpperCase() + filter.slice(1) + (n > 0 ? ` (${n})` : "");
    });
}

function buildTaskCard(task) {
    const card = document.createElement("div");
    card.className = "task-card" + (task.status === "completed" ? " completed" : "");
    card.dataset.id = task.id;
    card.dataset.priority = task.priority;

    const isOverdue = task.due_date && task.status === "pending" && new Date(task.due_date) < new Date();
    const dueDateLabel = task.due_date
        ? `<span class="task-due ${isOverdue ? "overdue" : ""}">&#128197; ${formatDate(task.due_date)}${isOverdue ? " (overdue)" : ""}</span>`
        : "";

    card.innerHTML = `
        <div class="task-top">
            <div class="task-check" onclick="toggleTask(${task.id})">&#10003;</div>
            <div class="task-body">
                <div class="task-title">${escapeHtml(task.title)}</div>
                ${task.description ? `<div class="task-desc">${escapeHtml(task.description)}</div>` : ""}
                <div class="task-meta">
                    ${dueDateLabel}
                    <span class="task-badge ${task.status === "completed" ? "badge-completed" : "badge-pending"}">${task.status}</span>
                    <span class="task-badge badge-${task.priority}>${task.priority}</span>
                </div>
            </div>
            <div class="task-actions">
                <button class="icon-btn edit" onclick="openEdit(${task.id})" title="Edit">&#9998;</button>
                <button class="icon-btn delete" onclick="deleteTask(${task.id})" title="Delete">&#128465;</button>
            </div>
        </div>
    `;

    return card;
}

async function submitTask() {
    const id = document.getElementById("task-id").value;
    const title = document.getElementById("task-title").value.trim();
    const desc = document.getElementById("task-desc").value.trim();
    const due = document.getElementById("task-due").value;
    const priority = document.getElementById("task-priority").value;

    if (!title) {
        showToast("Title is required.");
        return;
    }

    const body = new FormData();
    body.append("action", id ? "update" : "create");
    if (id) body.append("id", id);
    body.append("title", title);
    body.append("description", desc);
    body.append("due_date", due);
    body.append("priority", priority);

    const res = await fetch("tasks.php", { method: "POST", body });
    const data = await res.json();

    if (data.error) {
        showToast(data.error);
        return;
    }

    showToast(id ? "Task updated." : "Task added.");
    resetForm();
    loadTasks();
    loadCounts();
}

async function toggleTask(id) {
    const body = new FormData();
    body.append("action", "toggle");
    body.append("id", id);

    await fetch("tasks.php", { method: "POST", body });
    loadTasks();
    loadCounts();
}

let pendingDeleteId = null;

function deleteTask(id) {
    pendingDeleteId = id;
    document.getElementById("delete-modal").classList.add("open");
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const body = new FormData();
    body.append("action", "delete");
    body.append("id", pendingDeleteId);

    await fetch("tasks.php", { method: "POST", body });
    closeModal();
    showToast("Task deleted.");
    loadTasks();
    loadCounts();
}

function closeModal() {
    document.getElementById("delete-modal").classList.remove("open");
    pendingDeleteId = null;
}

function openEdit(id) {
    const card = document.querySelector(`.task-card[data-id="${id}"]`);
    const priority = card.dataset.priority || "medium";
    const title = card.querySelector(".task-title").textContent;
    const descEl = card.querySelector(".task-desc");
    const desc = descEl ? descEl.textContent : "";

    const dueMeta = card.querySelector(".task-due");
    let due = "";
    if (dueMeta) {
        const raw = dueMeta.textContent.replace("(overdue)", "").replace("&#128197;", "").trim();
        due = parseDateForInput(raw);
    }

    document.getElementById("task-id").value = id;
    document.getElementById("task-title").value = title;
    document.getElementById("task-desc").value = desc;
    document.getElementById("task-due").value = due;
    document.getElementById("task-priority").value = priority;
    document.getElementById("form-title").textContent = "Edit Task";
    document.getElementById("submit-btn").textContent = "Save Changes";
    document.getElementById("cancel-btn").style.display = "flex";

    document.getElementById("task-title").focus();
    window.scrollTo({ top: 0, behavior: "smooth" });

    editingId = id;
}

function resetForm() {
    document.getElementById("task-id").value = "";
    document.getElementById("task-title").value = "";
    document.getElementById("task-desc").value = "";
    document.getElementById("task-due").value = "";
    document.getElementById("task-priority").value = "medium";
    document.getElementById("form-title").textContent = "Add New Task";
    document.getElementById("submit-btn").textContent = "Add Task";
    document.getElementById("cancel-btn").style.display = "none";
    editingId = null;
}

document.getElementById("cancel-btn").addEventListener("click", resetForm);

function setFilter(btn) {
    document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    currentFilter = btn.dataset.filter;
    document.getElementById("search-input").value = "";
    loadTasks();
}

function formatDate(dateStr) {
    const date = new Date(dateStr + "T00:00:00");
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

function parseDateForInput(label) {
    const d = new Date(label);
    if (isNaN(d)) return "";
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

function escapeHtml(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

let toastTimer;
function showToast(msg) {
    const toast = document.getElementById("toast");
    toast.textContent = msg;
    toast.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("show"), 2800);
}

function searchTasks() {
    const query = document.getElementById("search-input").value.toLowerCase().trim();
    const cards = document.querySelectorAll(".task-card");
    let visible = 0;

    cards.forEach(card => {
        const title = card.querySelector(".task-title").textContent.toLowerCase();
        const descEl = card.querySelector(".task-desc");
        const desc = descEl ? descEl.textContent.toLowerCase() : "";
        const matches = title.includes(query) || desc.includes(query);
        card.classList.toggle("hidden", !matches);
        if (matches) visible++;
    });

    const noResults = document.getElementById("no-results");
    noResults.classList.toggle("visible", visible === 0 && query !== "");
}

document.getElementById("modal-confirm").addEventListener("click", confirmDelete);
document.getElementById("modal-cancel").addEventListener("click", closeModal);

document.getElementById("delete-modal").addEventListener("click", function(e) {
    if (e.target === this) closeModal();
});

loadTasks();
loadCounts();