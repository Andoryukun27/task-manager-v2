<?php
header("Content-Type: application/json");
require_once "db.php";

$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($action === "read") {
    $filter = $_GET["filter"] ?? "all";
    $sort   = $_GET["sort"]   ?? "created_at";

    $allowed_sorts = ["created_at", "due_date", "title"];
    if (!in_array($sort, $allowed_sorts)) {
        $sort = "created_at";
    }

    $order = $sort === "title" ? "ASC" : "DESC";

    if ($filter === "pending" || $filter === "completed") {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY $sort $order");
        $stmt->bind_param("s", $filter);
    } else {
        $stmt = $conn->prepare("SELECT * FROM tasks ORDER BY $sort $order");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($tasks);
    $stmt->close();
}

if ($action === "create") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date = $_POST["due_date"] ?? null;
    $priority = $_POST["priority"] ?? "medium";

    if ($title === "") {
        echo json_encode(["error" => "Title is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, due_date, priority) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $description, $due_date, $priority);
    $stmt->execute();
    echo json_encode(["success" => true, "id" => $conn->insert_id]);
    $stmt->close();
}

if ($action === "update") {
    $id = intval($_POST["id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date = $_POST["due_date"] ?? null;
    $priority = $_POST["priority"] ?? "medium";

    if ($id === 0 || $title === "") {
        echo json_encode(["error" => "ID and title are required"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $title, $description, $due_date, $priority, $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
    $stmt->close();
}

if ($action === "delete") {
    $id = intval($_POST["id"] ?? 0);

    if ($id === 0) {
        echo json_encode(["error" => "ID is required"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
    $stmt->close();
}

if ($action === "toggle") {
    $id = intval($_POST["id"] ?? 0);

    if ($id === 0) {
        echo json_encode(["error" => "ID is required"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET status = IF(status = 'pending', 'completed', 'pending') WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
    $stmt->close();
}

if ($action === "counts") {
    $result = $conn->query("SELECT status, COUNT(*) as total FROM tasks GROUP BY status");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $counts = ["all" => 0, "pending" => 0, "completed" => 0];
    foreach ($rows as $row) {
        $counts[$row["status"]] = $row["total"];
        $counts["all"] += $row["total"];
    }

    echo json_encode($counts);
}

$conn->close();
