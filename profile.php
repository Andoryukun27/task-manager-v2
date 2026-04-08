<?php
header("Content-Type: application/json");
require_once "auth.php";
requireLogin();
require_once "db.php";

$method = $_SERVER["REQUEST_METHOD"];
$uid    = (int) $_SESSION["user_id"];
$input  = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === "GET") {
    $stmt = $conn->prepare("SELECT email, notify_enabled FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    $stmt->close();
    exit;
}

if ($method === "PUT") {
    $email          = trim($input["email"] ?? "");
    $notify_enabled = isset($input["notify_enabled"]) ? (int)(bool)$input["notify_enabled"] : null;

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(["error" => "Invalid email address"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET email = ?, notify_enabled = ? WHERE id = ?");
    $notify_val = $notify_enabled ?? 1;
    $stmt->bind_param("sii", $email, $notify_val, $uid);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
$conn->close();
