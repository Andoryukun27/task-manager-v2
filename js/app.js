let currentFilter = "all";
let currentCategory = 0;
let currentSearch = "";
let currentOffset = 0;
let editingId = null;
let searchTimer = null;

async function loadTasks(append = false) {
    const list = document.getElementById("task-list");
    const btn = document.getElementById("load-more-btn");
    const noResults = document.getElementById("no-results");

    if (!append) {
        currentOffset = 0;
        list.innerHTML = '<div class="loading">Loading tasks...</div>';
        btn.style.display = "none";
        noResults.classList.remove("visible");
    } else {
        btn.disabled = true;
        btn.textContent = "Loading...";
    }

    const sort = document.getElementById("sort-select").value;
    const url = "tasks.php?action=read"
        + "&filter=" + currentFilter
        + "&sort=" + sort
        + "&offset=" + currentOffset
        + "&category_id=" + currentCategory
        + "&search=" + encodeURIComponent(currentSearch);

    const res = await fetch(url);
    const data = await res.json();
    const tasks = data.tasks;

    if (!append) list.innerHTML = "";

    if (tasks.length === 0 && !append) {
        if (currentSearch !== "") {
            noResults.classList.add("visible");
        } else {
            list.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9744;</div><p>No tasks here yet.</p></div>';
        }
    } else {
        tasks.forEach(task => list.appendChild(buildTaskCard(task)));
        currentOffset += tasks.length;
    }

    btn.disabled = false;
    btn.textContent = "Load more";
    btn.style.display = data.has_more ? "block" : "none";
}

function loadMore() {
    loadTasks(true);
}

async function loadCounts() {
    const res = await fetch("tasks.php?action=counts");
    const counts = await res.json();

    document.querySelectorAll(".filter-btn").forEach(btn => {
        const filter = btn.dataset.filter;
        if (!filter) return;
        const n = counts[filter] ?? 0;
        btn.textContent = filter.charAt(0).toUpperCase() + filter.slice(1) + (n > 0 ? ` (${n})` : "");
    });
}

async function loadCategories() {
    const res = await fetch("tasks.php?action=read_categories");
    const cats = await res.json();

    const select = document.getElementById("task-category");
    const savedValue = select.value;
    select.innerHTML = '<option value="">— No category —</option>';
    cats.forEach(cat => {
        const opt = document.createElement("option");
        opt.value = cat.id;
        opt.textContent = cat.name;
        select.appendChild(opt);
    });
    select.innerHTML += '<option value="new">＋ Create new category...</option>';
    if (savedValue) select.value = savedValue;

    const bar = document.getElementById("category-filter-bar");
    bar.innerHTML = '<button class="filter-btn' + (currentCategory === 0 ? " active" : "") + '" data-cat="0" onclick="setCategory(this)">All</button>';
    cats.forEach(cat => {
        const btn = document.createElement("button");
        btn.className = "filter-btn cat-pill cat-" + cat.color + (currentCategory === cat.id ? " active" : "");
        btn.dataset.cat = cat.id;
        btn.textContent = cat.name;
        btn.onclick = function () { setCategory(this); };
        bar.appendChild(btn);
    });
}

function getDateStatus(due_date, status) {
    if (!due_date || status === "completed") return "";

    const today = new Date();
    const todayStr = today.getFullYear() + "-"
        + String(today.getMonth() + 1).padStart(2, "0") + "-"
        + String(today.getDate()).padStart(2, "0");

    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const tomorrowStr = tomorrow.getFullYear() + "-"
        + String(tomorrow.getMonth() + 1).padStart(2, "0") + "-"
        + String(tomorrow.getDate()).padStart(2, "0");

    if (due_date < todayStr) return "overdue";
    if (due_date === todayStr) return "due-today";
    if (due_date === tomorrowStr) return "due-soon";
    return "";
}

function buildTaskCard(task) {
    const card = document.createElement("div");
    const dateStatus = getDateStatus(task.due_date, task.status);
    card.className = "task-card" + (task.status === "completed" ? " completed" : (dateStatus ? " " + dateStatus : ""));
    card.dataset.id = task.id;
    card.dataset.priority = task.priority;
    card.dataset.categoryId = task.category_id ?? "";

    const dateLabels = { overdue: " (overdue)", "due-today": " — Due today", "due-soon": " — Due tomorrow" };
    const dueDateLabel = task.due_date
        ? `<span class="task-due ${dateStatus}">&#128197; ${formatDate(task.due_date)}${dateLabels[dateStatus] ?? ""}</span>`
        : "";

    const catBadge = task.category_name
        ? `<span class="task-badge cat-pill cat-${task.category_color}">${escapeHtml(task.category_name)}</span>`
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
                    <span class="task-badge badge-${task.priority}">${task.priority}</span>
                    ${catBadge}
                </div>
            </div>
            <div class="task-actions">
                <button class="icon-btn edit"   onclick="openEdit(${task.id})"   title="Edit">&#9998;</button>
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
    const category_id = document.getElementById("task-category").value;

    if (!title) { showToast("Title is required."); return; }
    if (category_id === "new") { showToast("Save your new category first."); return; }

    const body = new FormData();
    body.append("action", id ? "update" : "create");
    if (id) body.append("id", id);
    body.append("title", title);
    body.append("description", desc);
    body.append("due_date", due);
    body.append("priority", priority);
    body.append("category_id", category_id);

    const res = await fetch("tasks.php", { method: "POST", body });
    const data = await res.json();

    if (data.error) { showToast(data.error); return; }

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
    const catId = card.dataset.categoryId || "";
    const title = card.querySelector(".task-title").textContent;
    const descEl = card.querySelector(".task-desc");
    const desc = descEl ? descEl.textContent : "";

    const dueMeta = card.querySelector(".task-due");
    let due = "";
    if (dueMeta) {
        const raw = dueMeta.textContent.replace("(overdue)", "").trim();
        const parts = raw.split(" ").slice(1).join(" ");
        due = parseDateForInput(parts);
    }

    document.getElementById("task-id").value = id;
    document.getElementById("task-title").value = title;
    document.getElementById("task-desc").value = desc;
    document.getElementById("task-due").value = due;
    document.getElementById("task-priority").value = priority;
    document.getElementById("task-category").value = catId;
    document.getElementById("new-category-form").style.display = "none";
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
    document.getElementById("task-category").value = "";
    document.getElementById("new-category-form").style.display = "none";
    document.getElementById("form-title").textContent = "Add New Task";
    document.getElementById("submit-btn").textContent = "Add Task";
    document.getElementById("cancel-btn").style.display = "none";
    editingId = null;
}

document.getElementById("cancel-btn").addEventListener("click", resetForm);

function setFilter(btn) {
    document.querySelectorAll(".filter-btn[data-filter]").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    currentFilter = btn.dataset.filter;
    currentSearch = "";
    document.getElementById("search-input").value = "";
    loadTasks();
}

function setCategory(btn) {
    document.querySelectorAll(".filter-btn[data-cat]").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    currentCategory = parseInt(btn.dataset.cat) || 0;
    currentSearch = "";
    document.getElementById("search-input").value = "";
    loadTasks();
}

function onCategoryChange() {
    const val = document.getElementById("task-category").value;
    document.getElementById("new-category-form").style.display = val === "new" ? "block" : "none";
}

async function createCategory() {
    const name = document.getElementById("new-cat-name").value.trim();
    const color = document.getElementById("new-cat-color").value;

    if (!name) { showToast("Category name is required."); return; }

    const body = new FormData();
    body.append("action", "create_category");
    body.append("name", name);
    body.append("color", color);

    const res = await fetch("tasks.php", { method: "POST", body });
    const data = await res.json();

    if (data.error) { showToast(data.error); return; }

    document.getElementById("new-cat-name").value = "";
    await loadCategories();
    document.getElementById("task-category").value = data.id;
    document.getElementById("new-category-form").style.display = "none";
    showToast("Category created.");
}

function searchTasks() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentSearch = document.getElementById("search-input").value.trim();
        loadTasks(false);
    }, 350);
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

document.getElementById("modal-delete").addEventListener("click", confirmDelete);
document.getElementById("modal-cancel").addEventListener("click", closeModal);
document.getElementById("delete-modal").addEventListener("click", function (e) {
    if (e.target === this) closeModal();
});

loadCategories();
loadTasks();
loadCounts();