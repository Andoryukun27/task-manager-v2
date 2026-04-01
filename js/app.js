const { useState, useEffect, useRef } = React;

// ─── API helpers ──────────────────────────────────────────────────────────────
//
// These replace all the fetch() calls scattered through the old app.js.
// Each function maps to one HTTP method. They all send/receive JSON.
//
// The old code used FormData (key=value pairs, like HTML forms).
// REST APIs use JSON bodies instead — that's what Content-Type: application/json
// and JSON.stringify() do here.
//
async function apiGet(url) {
    const res = await fetch(url);
    if (res.status === 204) return null;
    return res.json();
}

async function apiPost(url, data) {
    const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    });
    return res.json();
}

async function apiPut(url, data) {
    const res = await fetch(url, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    });
    return res.json();
}

async function apiPatch(url) {
    const res = await fetch(url, { method: "PATCH" });
    return res.json();
}

async function apiDelete(url) {
    await fetch(url, { method: "DELETE" });
}

// ─── Utility ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) return "";
    const d = new Date(dateStr + "T00:00:00");
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

function getDateStatus(due_date, status) {
    if (!due_date || status === "completed") return "";
    const today    = new Date();
    const todayStr = today.toISOString().slice(0, 10);
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().slice(0, 10);
    if (due_date < todayStr)    return "overdue";
    if (due_date === todayStr)  return "due-today";
    if (due_date === tomorrowStr) return "due-soon";
    return "";
}

// ─── Toast ────────────────────────────────────────────────────────────────────
//
// In the old app, showToast() directly manipulated a DOM element.
// Here, `message` is a piece of React state. When it's set, the component
// re-renders with the "show" class. When it's cleared, the class is removed.
//
function Toast({ message }) {
    return <div className={`toast ${message ? "show" : ""}`}>{message}</div>;
}

// ─── DeleteModal ──────────────────────────────────────────────────────────────

function DeleteModal({ open, onConfirm, onCancel }) {
    return (
        <div
            className={`modal-overlay ${open ? "open" : ""}`}
            onClick={e => e.target === e.currentTarget && onCancel()}
        >
            <div className="modal">
                <div className="modal-icon">🗑</div>
                <h3 className="modal-title">Delete this task?</h3>
                <p className="modal-body">This action cannot be undone.</p>
                <div className="modal-actions">
                    <button className="btn btn-ghost" onClick={onCancel}>Cancel</button>
                    <button className="btn btn-danger" onClick={onConfirm}>Delete</button>
                </div>
            </div>
        </div>
    );
}

// ─── TaskCard ─────────────────────────────────────────────────────────────────
//
// In the old app, buildTaskCard() built a DOM element and returned it.
// Here, TaskCard is a function that returns JSX — React's HTML-like syntax.
// JSX looks like HTML but it compiles to JavaScript. For example:
//   <div className="task-card"> becomes React.createElement("div", {className: "task-card"})
//
// Note: React uses "className" instead of "class" because "class" is a
// reserved word in JavaScript.
//
function TaskCard({ task, onEdit, onDelete, onToggle }) {
    const dateStatus = getDateStatus(task.due_date, task.status);
    const cardClass  = [
        "task-card",
        task.status === "completed" ? "completed" : (dateStatus || ""),
    ].filter(Boolean).join(" ");

    const dateLabels = {
        overdue:    " (overdue)",
        "due-today": " — Due today",
        "due-soon":  " — Due tomorrow",
    };

    return (
        <div className={cardClass} data-id={task.id}>
            <div className="task-top">
                <div className="task-check" onClick={() => onToggle(task.id)}>✓</div>
                <div className="task-body">
                    <div className="task-title">{task.title}</div>
                    {task.description && <div className="task-desc">{task.description}</div>}
                    <div className="task-meta">
                        {task.due_date && (
                            <span className={`task-due ${dateStatus}`}>
                                📅 {formatDate(task.due_date)}{dateLabels[dateStatus] ?? ""}
                            </span>
                        )}
                        <span className={`task-badge ${task.status === "completed" ? "badge-completed" : "badge-pending"}`}>
                            {task.status}
                        </span>
                        <span className={`task-badge badge-${task.priority}`}>{task.priority}</span>
                        {task.category_name && (
                            <span className={`task-badge cat-pill cat-${task.category_color}`}>
                                {task.category_name}
                            </span>
                        )}
                    </div>
                </div>
                <div className="task-actions">
                    <button className="icon-btn edit"   onClick={() => onEdit(task)}     title="Edit">✏</button>
                    <button className="icon-btn delete" onClick={() => onDelete(task.id)} title="Delete">🗑</button>
                </div>
            </div>
        </div>
    );
}

// ─── TaskForm ─────────────────────────────────────────────────────────────────
//
// This component manages its own local state for each field.
// When `editing` changes (user clicks Edit on a task), a useEffect syncs
// the form fields to that task's data.
//
// "Controlled inputs" is a key React concept: the input's value is always
// driven by a state variable, and onChange updates that state variable.
// This is the opposite of reading from the DOM when the form is submitted.
//
function TaskForm({ editing, categories, onSubmit, onCancel, onCategoryCreate }) {
    const [title,       setTitle]       = useState("");
    const [desc,        setDesc]        = useState("");
    const [due,         setDue]         = useState("");
    const [priority,    setPriority]    = useState("medium");
    const [categoryId,  setCategoryId]  = useState("");
    const [showNewCat,  setShowNewCat]  = useState(false);
    const [newCatName,  setNewCatName]  = useState("");
    const [newCatColor, setNewCatColor] = useState("blue");

    useEffect(() => {
        setTitle(editing?.title ?? "");
        setDesc(editing?.description ?? "");
        setDue(editing?.due_date ?? "");
        setPriority(editing?.priority ?? "medium");
        setCategoryId(String(editing?.category_id ?? ""));
        setShowNewCat(false);
    }, [editing]);

    function handleCategoryChange(val) {
        setCategoryId(val);
        setShowNewCat(val === "new");
    }

    async function handleCreateCategory() {
        if (!newCatName.trim()) return;
        const result = await onCategoryCreate(newCatName.trim(), newCatColor);
        if (result?.id) {
            setCategoryId(String(result.id));
            setNewCatName("");
            setShowNewCat(false);
        }
    }

    function handleSubmit() {
        if (categoryId === "new") return;
        onSubmit({ title, description: desc, due_date: due, priority, category_id: categoryId });
    }

    return (
        <div className="form-card">
            <h2 className="form-title">{editing ? "Edit Task" : "Add New Task"}</h2>

            <div className="field-group">
                <label>Title <span className="required">*</span></label>
                <input
                    value={title}
                    onChange={e => setTitle(e.target.value)}
                    placeholder="What needs to be done?"
                />
            </div>

            <div className="field-group">
                <label>Description</label>
                <textarea
                    value={desc}
                    onChange={e => setDesc(e.target.value)}
                    placeholder="Add details..."
                    rows="3"
                />
            </div>

            <div className="field-group">
                <label>Due Date</label>
                <input type="date" value={due} onChange={e => setDue(e.target.value)} />
            </div>

            <div className="field-group">
                <label>Priority</label>
                <select value={priority} onChange={e => setPriority(e.target.value)}>
                    <option value="low">🟢 Low</option>
                    <option value="medium">🟡 Medium</option>
                    <option value="high">🔴 High</option>
                </select>
            </div>

            <div className="field-group">
                <label>Category</label>
                <select value={categoryId} onChange={e => handleCategoryChange(e.target.value)}>
                    <option value="">— No category —</option>
                    {categories.map(c => (
                        <option key={c.id} value={String(c.id)}>{c.name}</option>
                    ))}
                    <option value="new">＋ Create new category...</option>
                </select>

                {showNewCat && (
                    <div className="new-cat-form">
                        <input
                            value={newCatName}
                            onChange={e => setNewCatName(e.target.value)}
                            placeholder="Category name"
                        />
                        <select value={newCatColor} onChange={e => setNewCatColor(e.target.value)}>
                            <option value="blue">🔵 Blue</option>
                            <option value="green">🟢 Green</option>
                            <option value="purple">🟣 Purple</option>
                            <option value="red">🔴 Red</option>
                            <option value="orange">🟠 Orange</option>
                            <option value="yellow">🟡 Yellow</option>
                        </select>
                        <button className="btn btn-ghost btn-sm" onClick={handleCreateCategory}>
                            Save category
                        </button>
                    </div>
                )}
            </div>

            <div className="form-actions">
                {editing && (
                    <button className="btn btn-ghost" onClick={onCancel}>Cancel</button>
                )}
                <button className="btn btn-primary" onClick={handleSubmit}>
                    {editing ? "Save Changes" : "Add Task"}
                </button>
            </div>
        </div>
    );
}

// ─── App ──────────────────────────────────────────────────────────────────────
//
// This is the root component. All shared state lives here.
// Child components receive data as "props" and call callback functions
// (like onEdit, onDelete) to ask the parent to update state.
//
// This pattern — state up, callbacks down — is called "lifting state up"
// and is the core data-flow pattern in React.
//
function App() {
    const [tasks,          setTasks]          = useState([]);
    const [categories,     setCategories]     = useState([]);
    const [counts,         setCounts]         = useState({ all: 0, pending: 0, completed: 0 });
    const [filter,         setFilter]         = useState("all");
    const [categoryFilter, setCategoryFilter] = useState(0);
    const [sort,           setSort]           = useState("created_at");
    const [search,         setSearch]         = useState("");
    const [offset,         setOffset]         = useState(0);
    const [hasMore,        setHasMore]        = useState(false);
    const [editing,        setEditing]        = useState(null);
    const [deleteId,       setDeleteId]       = useState(null);
    const [toast,          setToast]          = useState("");
    const [refreshKey,     setRefreshKey]     = useState(0);
    const searchTimer = useRef(null);

    function showToast(msg) {
        setToast(msg);
        setTimeout(() => setToast(""), 2800);
    }

    function refresh() {
        setRefreshKey(k => k + 1);
    }

    // Load tasks whenever filter, category, sort, search, or refreshKey changes.
    // useEffect runs after every render where one of its dependencies changed.
    // The empty second argument [] would mean "run once on mount".
    useEffect(() => {
        async function load() {
            const params = new URLSearchParams({
                filter,
                sort,
                offset: 0,
                category_id: categoryFilter,
                search,
            });
            const data = await apiGet(`tasks.php?${params}`);
            setTasks(data.tasks);
            setOffset(data.tasks.length);
            setHasMore(data.has_more);
        }
        load();
    }, [filter, categoryFilter, sort, search, refreshKey]);

    useEffect(() => {
        async function loadCounts() {
            const data = await apiGet("tasks.php?action=counts");
            setCounts(data);
        }
        loadCounts();
    }, [refreshKey]);

    useEffect(() => {
        async function loadCategories() {
            const data = await apiGet("categories.php");
            setCategories(data);
        }
        loadCategories();
    }, []);

    async function loadMore() {
        const params = new URLSearchParams({
            filter, sort, offset, category_id: categoryFilter, search,
        });
        const data = await apiGet(`tasks.php?${params}`);
        setTasks(prev => [...prev, ...data.tasks]);
        setOffset(prev => prev + data.tasks.length);
        setHasMore(data.has_more);
    }

    async function handleSubmit({ title, description, due_date, priority, category_id }) {
        if (!title.trim()) { showToast("Title is required."); return; }
        if (editing) {
            await apiPut(`tasks.php?id=${editing.id}`, { title, description, due_date, priority, category_id });
            showToast("Task updated.");
        } else {
            await apiPost("tasks.php", { title, description, due_date, priority, category_id });
            showToast("Task added.");
        }
        setEditing(null);
        refresh();
    }

    async function handleToggle(id) {
        await apiPatch(`tasks.php?id=${id}`);
        refresh();
    }

    async function handleDelete() {
        await apiDelete(`tasks.php?id=${deleteId}`);
        setDeleteId(null);
        showToast("Task deleted.");
        refresh();
    }

    async function handleCategoryCreate(name, color) {
        const result = await apiPost("categories.php", { name, color });
        if (result?.id) {
            const data = await apiGet("categories.php");
            setCategories(data);
            showToast("Category created.");
        }
        return result;
    }

    function handleFilterChange(f) {
        setFilter(f);
        setSearch("");
    }

    function handleCategoryChange(catId) {
        setCategoryFilter(catId);
        setSearch("");
    }

    function handleSearchInput(val) {
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => setSearch(val), 350);
    }

    function filterLabel(f) {
        const n = counts[f] ?? 0;
        return f.charAt(0).toUpperCase() + f.slice(1) + (n > 0 ? ` (${n})` : "");
    }

    return (
        <div className="app-shell">

            <header className="app-header">
                <div className="header-inner">
                    <div className="brand">
                        <span className="brand-icon">✓</span>
                        <h1>TaskFlow</h1>
                    </div>
                    <p className="brand-sub">Stay on top of everything</p>
                    <div className="header-user">
                        <span className="header-username">👤 {window.APP_DATA.username}</span>
                        <a href="logout.php" className="btn btn-ghost btn-sm">Sign out</a>
                    </div>
                </div>
            </header>

            <main className="app-main">

                <section className="form-section">
                    <TaskForm
                        editing={editing}
                        categories={categories}
                        onSubmit={handleSubmit}
                        onCancel={() => setEditing(null)}
                        onCategoryCreate={handleCategoryCreate}
                    />
                </section>

                <section className="tasks-section">
                    <div className="tasks-toolbar">
                        <div className="toolbar-row">
                            <input
                                type="text"
                                id="search-input"
                                placeholder="🔍 Search tasks..."
                                onChange={e => handleSearchInput(e.target.value)}
                            />
                            <select
                                id="sort-select"
                                defaultValue="created_at"
                                onChange={e => setSort(e.target.value)}
                            >
                                <option value="sort_order">Custom Order</option>
                                <option value="created_at">Date Created</option>
                                <option value="due_date">Due Date</option>
                                <option value="title">Title</option>
                            </select>
                        </div>

                        <div className="filter-bar">
                            {["all", "pending", "completed"].map(f => (
                                <button
                                    key={f}
                                    className={`filter-btn ${filter === f ? "active" : ""}`}
                                    onClick={() => handleFilterChange(f)}
                                >
                                    {filterLabel(f)}
                                </button>
                            ))}
                        </div>

                        <div className="filter-bar" id="category-filter-bar">
                            <button
                                className={`filter-btn ${categoryFilter === 0 ? "active" : ""}`}
                                onClick={() => handleCategoryChange(0)}
                            >
                                All
                            </button>
                            {categories.map(c => (
                                <button
                                    key={c.id}
                                    className={`filter-btn cat-pill cat-${c.color} ${categoryFilter === c.id ? "active" : ""}`}
                                    onClick={() => handleCategoryChange(c.id)}
                                >
                                    {c.name}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="task-list">
                        {tasks.length === 0 ? (
                            search
                                ? <p className="no-results visible">No tasks match your search.</p>
                                : <div className="empty-state"><div className="empty-icon">☐</div><p>No tasks here yet.</p></div>
                        ) : (
                            tasks.map(task => (
                                <TaskCard
                                    key={task.id}
                                    task={task}
                                    onEdit={setEditing}
                                    onDelete={setDeleteId}
                                    onToggle={handleToggle}
                                />
                            ))
                        )}
                    </div>

                    {hasMore && (
                        <div className="load-more-wrap">
                            <button className="btn btn-ghost" onClick={loadMore}>Load more</button>
                        </div>
                    )}
                </section>

            </main>

            <DeleteModal
                open={deleteId !== null}
                onConfirm={handleDelete}
                onCancel={() => setDeleteId(null)}
            />

            <Toast message={toast} />

        </div>
    );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
