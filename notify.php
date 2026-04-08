<?php
// ── notify.php — run from the terminal: php notify.php ───────────────────────
//
// This script is never called by a browser. It runs as a background process,
// either manually during development or on a daily schedule via cron /
// Windows Task Scheduler in production.
//
// Because there is no HTTP request there is no $_SESSION, no header(), and
// no json_encode() output. Everything goes straight to the terminal with echo.
//

if (php_sapi_name() !== "cli") {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

// ── Tomorrow's date ───────────────────────────────────────────────────────────
//
// We build the date string in PHP rather than using MySQL date functions so
// the logic stays in one language and is easy to read and unit-test.
//
$tomorrow = date("Y-m-d", strtotime("+1 day"));

echo "Looking for tasks due on {$tomorrow}...\n";

// ── Query: pending tasks due tomorrow for users who have an email ─────────────
//
// notify_enabled lets users opt out. We JOIN users so we get the email
// address in one query instead of a second round-trip per task.
//
$stmt = $conn->prepare("
    SELECT t.id, t.title, t.description, t.due_date, t.priority,
           u.id AS user_id, u.username, u.email
    FROM   tasks t
    JOIN   users u ON t.user_id = u.id
    WHERE  t.due_date       = ?
    AND    t.status         = 'pending'
    AND    u.email         != ''
    ORDER  BY u.id ASC, t.priority DESC
");
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    echo "No pending tasks due tomorrow — nothing to send.\n";
    $conn->close();
    exit;
}

// ── Group rows by user ────────────────────────────────────────────────────────
//
// The query returns one row per task. We want one email per user that lists
// all of their due tasks. Grouping by user_id builds that structure.
//
$byUser = [];
foreach ($rows as $row) {
    $uid = $row["user_id"];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = [
            "username" => $row["username"],
            "email"    => $row["email"],
            "tasks"    => [],
        ];
    }
    $byUser[$uid]["tasks"][] = $row;
}

$taskCount  = count($rows);
$userCount  = count($byUser);
echo "Found {$taskCount} task(s) across {$userCount} user(s).\n\n";

$conn->close();

// ── Send one email per user ───────────────────────────────────────────────────

$sent   = 0;
$failed = 0;

foreach ($byUser as $user) {
    $n       = count($user["tasks"]);
    $plural  = $n > 1 ? "s" : "";
    $subject = "TaskFlow: you have {$n} task{$plural} due tomorrow";

    // ── Build the HTML body ───────────────────────────────────────────────────
    //
    // We build table rows for each task, then slot them into the outer
    // template. Keeping the HTML in one place makes it easy to edit later.
    //
    $rows_html = "";
    foreach ($user["tasks"] as $task) {
        $priority_color = match($task["priority"]) {
            "high"   => "#c0392b",
            "medium" => "#b7770d",
            default  => "#2a7a4b",
        };
        $title         = htmlspecialchars($task["title"]);
        $priority_label = ucfirst($task["priority"]);
        $desc_html     = $task["description"]
            ? "<p style='margin:4px 0 0;font-size:13px;color:#6b6460;'>"
              . htmlspecialchars($task["description"]) . "</p>"
            : "";

        $rows_html .= "
        <tr>
            <td style='padding:12px 16px;border-bottom:1px solid #e4ddd3;'>
                <strong style='font-size:15px;color:#1a1714;'>{$title}</strong>
                {$desc_html}
            </td>
            <td style='padding:12px 16px;border-bottom:1px solid #e4ddd3;white-space:nowrap;'>
                <span style='
                    display:inline-block;padding:2px 10px;border-radius:20px;
                    font-size:11px;font-weight:600;text-transform:uppercase;
                    background:{$priority_color}22;color:{$priority_color};
                '>{$priority_label}</span>
            </td>
        </tr>";
    }

    $uname = htmlspecialchars($user["username"]);

    $html = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f5f2ed;font-family:Helvetica Neue,Arial,sans-serif;'>
        <div style='max-width:560px;margin:40px auto;background:#fff;border-radius:12px;border:1px solid #e4ddd3;overflow:hidden;'>

            <div style='background:#c8622a;padding:28px 32px;'>
                <span style='color:#fff;font-size:22px;font-weight:700;letter-spacing:-0.5px;'>&#10003; TaskFlow</span>
            </div>

            <div style='padding:28px 32px 8px;'>
                <p style='font-size:16px;color:#1a1714;margin:0 0 6px;'>Hi <strong>{$uname}</strong>,</p>
                <p style='font-size:14px;color:#6b6460;margin:0;'>
                    You have <strong>{$n}</strong> pending task{$plural} due <strong>tomorrow</strong>.
                    Here's a quick reminder so nothing slips through the cracks.
                </p>
            </div>

            <div style='padding:16px 32px 28px;'>
                <table width='100%' cellpadding='0' cellspacing='0'
                       style='border-collapse:collapse;border:1px solid #e4ddd3;border-radius:8px;overflow:hidden;'>
                    <thead>
                        <tr style='background:#f5f2ed;'>
                            <th style='padding:10px 16px;text-align:left;font-size:12px;color:#6b6460;
                                       font-weight:600;text-transform:uppercase;letter-spacing:.04em;'>Task</th>
                            <th style='padding:10px 16px;text-align:left;font-size:12px;color:#6b6460;
                                       font-weight:600;text-transform:uppercase;letter-spacing:.04em;'>Priority</th>
                        </tr>
                    </thead>
                    <tbody>{$rows_html}</tbody>
                </table>
            </div>

            <div style='padding:0 32px 28px;'>
                <a href='http://localhost/task-manager/'
                   style='display:inline-block;background:#c8622a;color:#fff;text-decoration:none;
                          padding:11px 22px;border-radius:8px;font-size:14px;font-weight:500;'>
                    Open TaskFlow &rarr;
                </a>
            </div>

            <div style='padding:16px 32px;border-top:1px solid #e4ddd3;background:#f5f2ed;'>
                <p style='font-size:11px;color:#a09890;margin:0;'>
                    You received this because notifications are enabled on your TaskFlow account.
                </p>
            </div>

        </div>
    </body>
    </html>";

    $text = "TaskFlow reminder\n\n"
        . "You have {$n} pending task{$plural} due tomorrow:\n\n";
    foreach ($user["tasks"] as $task) {
        $text .= "  - {$task["title"]} (" . ucfirst($task["priority"]) . " priority)\n";
        if ($task["description"]) {
            $text .= "    {$task["description"]}\n";
        }
    }
    $text .= "\nOpen TaskFlow: http://localhost/task-manager/";

    // ── Send ──────────────────────────────────────────────────────────────────
    //
    // sendMail() is defined in mailer.php. It makes one curl request to the
    // Mailtrap API and returns true/false — no exception to catch.
    //
    $ok = sendMail($user["email"], $user["username"], $subject, $html, $text);

    if ($ok) {
        echo "  ✓ Sent to {$user["email"]} ({$n} task{$plural})\n";
        $sent++;
    } else {
        echo "  ✗ Failed for {$user["email"]} — check the PHP error log\n";
        $failed++;
    }
}

echo "\nDone. Sent: {$sent}  Failed: {$failed}\n";