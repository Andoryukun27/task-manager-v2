<?php
header("Content-Type: application/json");
require_once "auth.php";
requireLogin();
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$uid    = (int) $_SESSION["user_id"];
$input  = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === "GET") {
    $stmt = $conn->prepare("SELECT id, name, color FROM categories WHERE user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    exit;
}

if ($method === "POST") {
    $name    = trim($input["name"] ?? "");
    $color   = $input["color"] ?? "blue";
    $allowed = ["blue", "green", "purple", "red", "orange", "yellow"];
    if (!in_array($color, $allowed)) $color = "blue";

    if (!$name) {
        http_response_code(422);
        echo json_encode(["error" => "Name is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $uid, $name, $color);
    $stmt->execute();
    http_response_code(201);
    echo json_encode(["id" => $conn->insert_id]);
    $stmt->close();
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
$conn->close();
