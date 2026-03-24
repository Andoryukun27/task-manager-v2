# ✅ TaskFlow — PHP & MySQL Task Manager

A simple, clean full-stack task manager built with vanilla PHP, MySQL, and JavaScript. No frameworks, no dependencies — just beginner-friendly code you can read, understand, and build on.

---

## 📸 Features

- Add, edit, and delete tasks
- Mark tasks as completed or pending
- Filter tasks by **All**, **Pending**, or **Completed**
- Overdue date highlighting
- Fully responsive UI
- JSON-based PHP API with prepared statements

---

## 🗂️ Project Structure

```
task-manager-v2/
├── index.php        # Main page (HTML structure)
├── tasks.php        # Backend API — handles all CRUD operations
├── db.php           # Database connection (shared config)
├── database.sql     # Run this once to set up the database
├── css/
│   └── style.css    # All styling
└── js/
    └── app.js       # All browser-side logic (fetch, render, events)
```

---

## ⚙️ Requirements

- [XAMPP](https://www.apachefriends.org/) or [Laragon](https://laragon.org/)
- PHP 7.4+
- MySQL 5.7+
- A modern web browser

---

## 🚀 Setup & Installation

### 1. Clone the repository

```bash
git clone https://github.com/Andoryukun27/task-manager-v2.git
```

### 2. Move the folder to your server root

| Tool | Folder |
|------|--------|
| XAMPP | `C:\xampp\htdocs\task-manager-v2` |
| Laragon | `C:\laragon\www\task-manager-v2` |

### 3. Set up the database

1. Start Apache and MySQL
2. Open **http://localhost/phpmyadmin**
3. Click the **SQL** tab
4. Copy and paste the contents of `database.sql` and click **Go**

### 4. Configure the database connection

Open `db.php` and confirm these values match your local setup:

```php
$host = "localhost";
$db   = "task_manager";
$user = "root";
$pass = "";        // Leave empty for XAMPP/Laragon default
```

### 5. Run the app

Visit: **http://localhost/task-manager-v2/**

---

## 🔌 How It Works

The browser never talks to the database directly. Every action goes through `tasks.php`, which validates input, runs a prepared SQL statement, and returns JSON.

```
Browser (app.js)
    └── fetch("tasks.php?action=...")
            └── tasks.php validates + queries MySQL
                    └── returns JSON back to browser
```

---

## 📋 API Reference

All requests go to `tasks.php`.

| Action | Method | Params | Description |
|--------|--------|--------|-------------|
| `read` | GET | `filter` (all/pending/completed) | Fetch tasks |
| `create` | POST | `title`, `description`, `due_date` | Add a task |
| `update` | POST | `id`, `title`, `description`, `due_date` | Edit a task |
| `delete` | POST | `id` | Delete a task |
| `toggle` | POST | `id` | Toggle task status |

---

## 🛡️ Security

- All database queries use **prepared statements** to prevent SQL injection
- User input is HTML-escaped before rendering to prevent XSS

---

## 📄 License

This project is open source and free to use for learning purposes.
