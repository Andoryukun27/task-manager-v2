<?php
header("Content-Type: application/json");
require_once "auth.php";
requireLogin();
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$uid    = (int) $_SESSION["user_id"];
$id     = intval($_GET["id"] ?? 0);
$action = $_GET["action"] ?? "";
$input  = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === "GET" && $action === "counts") {
    $stmt = $conn->prepare("SELECT status, COUNT(*) AS n FROM tasks WHERE user_id = ? GROUP BY status");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = ["all" => 0, "pending" => 0, "completed" => 0];
    foreach ($rows as $r) {
        $out[$r["status"]] = (int) $r["n"];
        $out["all"] += (int) $r["n"];
    }
    echo json_encode($out);
    exit;
}

if ($method === "GET" && $id === 0 && $action === "") {
    $filter      = $_GET["filter"] ?? "all";
    $sort        = $_GET["sort"] ?? "created_at";
    $offset      = max(0, intval($_GET["offset"] ?? 0));
    $category_id = max(0, intval($_GET["category_id"] ?? 0));
    $search      = trim($_GET["search"] ?? "");
    $fetch       = 11;

    $allowed_sorts = ["created_at", "due_date", "title", "sort_order"];
    if (!in_array($sort, $allowed_sorts)) $sort = "created_at";
    $order = $sort === "title" ? "ASC" : "DESC";

    $where  = "WHERE t.user_id = ?";
    $types  = "i";
    $params = [$uid];

    if ($filter === "pending" || $filter === "completed") {
        $where   .= " AND t.status = ?";
        $types   .= "s";
        $params[] = $filter;
    }

    if ($category_id > 0) {
        $where   .= " AND t.category_id = ?";
        $types   .= "i";
        $params[] = $category_id;
    }

    if ($search !== "") {
        $like     = "%" . $search . "%";
        $where   .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $types   .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    if ($sort === "sort_order") {
        $sql = "SELECT t.*, c.name AS category_name, c.color AS category_color
                FROM tasks t LEFT JOIN categories c ON t.category_id = c.id
                $where ORDER BY t.sort_order ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(["tasks" => $tasks, "has_more" => false]);
    } else {
        $types   .= "ii";
        $params[] = $fetch;
        $params[] = $offset;
        $sql = "SELECT t.*, c.name AS category_name, c.color AS category_color
                FROM tasks t LEFT JOIN categories c ON t.category_id = c.id
                $where ORDER BY t.$sort $order LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $has_more = count($tasks) === $fetch;
        if ($has_more) array_pop($tasks);
        echo json_encode(["tasks" => $tasks, "has_more" => $has_more]);
    }
    exit;
}

if ($method === "POST" && $action === "reorder") {
    $ids = $input["ids"] ?? [];
    if (!is_array($ids)) { echo json_encode(["error" => "Invalid data"]); exit; }
    foreach ($ids as $order => $taskId) {
        $taskId = intval($taskId);
        if (!$taskId) continue;
        $stmt = $conn->prepare("UPDATE tasks SET sort_order = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $order, $taskId, $uid);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["success" => true]);
    exit;
}

if ($method === "POST" && $action === "") {
    $title       = trim($input["title"] ?? "");
    $description = trim($input["description"] ?? "");
    $due_date    = $input["due_date"] ?: null;
    $priority    = $input["priority"] ?? "medium";
    $category_id = intval($input["category_id"] ?? 0) ?: null;

    if (!$title) {
        http_response_code(422);
        echo json_encode(["error" => "Title is required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tasks WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $sort_order = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, sort_order, title, description, due_date, priority, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssi", $uid, $sort_order, $title, $description, $due_date, $priority, $category_id);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    http_response_code(201);
    echo json_encode(["id" => $newId]);
    exit;
}

if ($method === "PUT" && $id > 0) {
    $title       = trim($input["title"] ?? "");
    $description = trim($input["description"] ?? "");
    $due_date    = $input["due_date"] ?: null;
    $priority    = $input["priority"] ?? "medium";
    $category_id = intval($input["category_id"] ?? 0) ?: null;

    if (!$title) {
        http_response_code(422);
        echo json_encode(["error" => "Title is required"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, due_date=?, priority=?, category_id=? WHERE id=? AND user_id=?");
    $stmt->bind_param("ssssiii", $title, $description, $due_date, $priority, $category_id, $id, $uid);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]);
    exit;
}

if ($method === "PATCH" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status = IF(status='pending','completed','pending') WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]);
    exit;
}

if ($method === "DELETE" && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();
    http_response_code(204);
    exit;
}

http_response_code(400);
echo json_encode(["error" => "Bad request"]);
$conn->close();