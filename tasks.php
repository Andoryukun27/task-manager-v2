<?php
header("Content-Type: application/json");
require_once "auth.php";
requireLogin();
require_once "db.php";

$uid = (int) $_SESSION["user_id"];
$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($action === "read") {
    $filter = $_GET["filter"] ?? "all";
    $sort = $_GET["sort"] ?? "created_at";
    $offset = max(0, intval($_GET["offset"] ?? 0));
    $fetch = 11;

    $allowed_sorts = ["created_at", "due_date", "title"];
    if (!in_array($sort, $allowed_sorts)) {
        $sort = "created_at";
    }

    $order = $sort === "title" ? "ASC" : "DESC";

    if ($filter === "pending" || $filter === "completed") {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY $sort $order LIMIT ? OFFSET ?");
        $stmt->bind_param("sii", $filter, $fetch, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM tasks ORDER BY $sort $order LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $fetch, $offset);
    }

    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $has_more = count($tasks) === $fetch;
    if ($has_more) {
        array_pop($tasks);
    }

    echo json_encode(["tasks" => $tasks, "has_more" => $has_more]);
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

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $uid, $title, $description, $due_date, $priority);
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

    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssssii", $title, $description, $due_date, $priority, $id, $uid);
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

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid);
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

    $stmt = $conn->prepare("UPDATE tasks SET status = IF(status = 'pending', 'completed', 'pending') WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    echo json_encode(["success" => true]);
    $stmt->close();
}

if ($action === "counts") {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM tasks WHERE user_id = ? GROUP BY status");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $counts = ["all" => 0, "pending" => 0, "completed" => 0];
    foreach ($rows as $row) {
        $counts[$row["status"]] = (int) $row["total"];
        $counts["all"] += (int) $row["total"];
    }

    echo json_encode($counts);
}

$conn->close();