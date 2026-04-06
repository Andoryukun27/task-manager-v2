<?php
header("Content-Type: application/json");
require_once "auth.php";
requireLogin();
require_once "db.php";

$method     = $_SERVER["REQUEST_METHOD"];
$uid        = (int) $_SESSION["user_id"];
$task_id    = intval($_GET["task_id"] ?? 0);
$att_id     = intval($_GET["id"] ?? 0);
$upload_dir = __DIR__ . "/uploads/";

// ── GET: list all attachments for a task ──────────────────────────────────────
//
// Before returning anything, we verify the task belongs to the logged-in user.
// This prevents user A from listing user B's attachments by guessing a task_id.
//
if ($method === "GET" && $task_id > 0) {
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $uid);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(["error" => "Not found"]);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, original_name, stored_name, file_size, mime_type, created_at
         FROM task_attachments WHERE task_id = ? ORDER BY created_at ASC"
    );
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    exit;
}

// ── POST: upload a file and attach it to a task ───────────────────────────────
//
// This route is different from all other POST routes in the app.
// Because we're sending a real file, the browser uses multipart/form-data
// instead of JSON. PHP receives the file in $_FILES, not php://input.
//
// $_FILES["file"] contains:
//   name     — original filename from the user's computer
//   tmp_name — where PHP temporarily stored the file during the request
//   size     — file size in bytes
//   type     — MIME type reported by the browser (DO NOT TRUST THIS)
//   error    — an integer code; 0 means success
//
if ($method === "POST" && $task_id > 0) {
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $uid);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(["error" => "Not found"]);
        exit;
    }
    $stmt->close();

    if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        $codes = [
            UPLOAD_ERR_INI_SIZE  => "File exceeds the server upload limit",
            UPLOAD_ERR_FORM_SIZE => "File exceeds the form upload limit",
            UPLOAD_ERR_NO_FILE   => "No file was sent",
        ];
        $code = $_FILES["file"]["error"] ?? UPLOAD_ERR_NO_FILE;
        echo json_encode(["error" => $codes[$code] ?? "Upload failed"]);
        exit;
    }

    $max_bytes = 5 * 1024 * 1024;
    if ($_FILES["file"]["size"] > $max_bytes) {
        http_response_code(422);
        echo json_encode(["error" => "File must be 5 MB or smaller"]);
        exit;
    }

    // We use finfo to detect the real MIME type by reading the file's actual
    // bytes, rather than trusting $_FILES["file"]["type"].
    // A browser can send any string for that field — it's user-controlled input.
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($_FILES["file"]["tmp_name"]);

    $allowed_mime = [
        "image/jpeg", "image/png", "image/gif", "image/webp",
        "application/pdf",
        "text/plain",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.ms-excel",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    ];

    if (!in_array($real_mime, $allowed_mime)) {
        http_response_code(422);
        echo json_encode(["error" => "File type not allowed"]);
        exit;
    }

    // Sanitize the original filename so it's safe to store in the DB and display.
    // Then prepend a unique ID so two uploads of "report.pdf" don't collide on disk.
    $original_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($_FILES["file"]["name"]));
    $stored_name   = uniqid() . "_" . $original_name;

    // move_uploaded_file() is the only correct way to move an uploaded file.
    // It verifies the file actually came from an HTTP upload, which closes a
    // class of path-traversal attacks.
    if (!move_uploaded_file($_FILES["file"]["tmp_name"], $upload_dir . $stored_name)) {
        http_response_code(500);
        echo json_encode(["error" => "Could not save file to disk"]);
        exit;
    }

    $size = (int) $_FILES["file"]["size"];
    $stmt = $conn->prepare(
        "INSERT INTO task_attachments (task_id, user_id, original_name, stored_name, file_size, mime_type)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissis", $task_id, $uid, $original_name, $stored_name, $size, $real_mime);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        "id"            => $new_id,
        "original_name" => $original_name,
        "stored_name"   => $stored_name,
        "file_size"     => $size,
        "mime_type"     => $real_mime,
    ]);
    exit;
}

// ── DELETE: remove an attachment record and its file from disk ────────────────
//
// We JOIN tasks to verify the attachment belongs to a task that belongs to
// this user. Without that check, any logged-in user could delete any file
// by guessing an attachment ID.
//
if ($method === "DELETE" && $att_id > 0) {
    $stmt = $conn->prepare(
        "SELECT a.stored_name FROM task_attachments a
         JOIN tasks t ON a.task_id = t.id
         WHERE a.id = ? AND t.user_id = ?"
    );
    $stmt->bind_param("ii", $att_id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(["error" => "Not found"]);
        exit;
    }

    $file_path = $upload_dir . $row["stored_name"];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $stmt = $conn->prepare("DELETE FROM task_attachments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $att_id, $uid);
    $stmt->execute();
    $stmt->close();

    http_response_code(204);
    exit;
}

http_response_code(400);
echo json_encode(["error" => "Bad request"]);
$conn->close();
