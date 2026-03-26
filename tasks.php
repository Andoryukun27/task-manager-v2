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
    $category_id = max(0, intval($_GET["category_id"] ?? 0));
    $search = trim($_GET["search"] ?? "");
    $fetch = 11;

    $allowed_sorts = ["created_at", "due_date", "title"];
    if (!in_array($sort, $allowed_sorts))
        $sort = "created_at";
    $order = $sort === "title" ? "ASC" : "DESC";

    $where = "WHERE t.user_id = ?";
    $types = "i";
    $params = [$uid];

    if ($filter === "pending" || $filter === "completed") {
        $where .= " AND t.status = ?";
        $types .= "s";
        $params[] = $filter;
    }

    if ($category_id > 0) {
        $where .= " AND t.category_id = ?";
        $types .= "i";
        $params[] = $category_id;
    }

    if ($search !== "") {
        $like = "%" . $search . "%";
        $where .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $types .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    $types .= "ii";
    $params[] = $fetch;
    $params[] = $offset;

    $sql = "SELECT t.*, c.name AS category_name, c.color AS category_color
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id
            $where
            ORDER BY t.$sort $order
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $has_more = count($tasks) === $fetch;
    if ($has_more)
        array_pop($tasks);

    echo json_encode(["tasks" => $tasks, "has_more" => $has_more]);
}

if ($action === "create") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date = $_POST["due_date"] ?? null;
    $priority = $_POST["priority"] ?? "medium";
    $category_id = intval($_POST["category_id"] ?? 0) ?: null;

    if ($title === "") {
        echo json_encode(["error" => "Title is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, category_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $uid, $title, $description, $due_date, $priority, $category_id);
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
    $category_id = intval($_POST["category_id"] ?? 0) ?: null;

    if ($id === 0 || $title === "") {
        echo json_encode(["error" => "ID and title are required"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, category_id = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssssiii", $title, $description, $due_date, $priority, $category_id, $id, $uid);
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

if ($action === "read_categories") {
    $stmt = $conn->prepare("SELECT id, name, color FROM categories WHERE user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode($cats);
}

if ($action === "create_category") {
    $name = trim($_POST["name"] ?? "");
    $color = trim($_POST["color"] ?? "blue");

    $allowed_colors = ["blue", "green", "purple", "red", "orange", "yellow"];
    if (!in_array($color, $allowed_colors))
        $color = "blue";

    if ($name === "") {
        echo json_encode(["error" => "Category name is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $uid, $name, $color);
    $stmt->execute();
    echo json_encode(["success" => true, "id" => $conn->insert_id]);
    $stmt->close();
}

$conn->close();