<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

require_once "db.php";

$error = "";
$success = "";
$mode = $_GET["mode"] ?? "login";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = $_POST["mode"] ?? "login";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($mode === "register") {
        $email = trim($_POST["email"] ?? "");
        if ($username === "" || $password === "" || $email === "") {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        }
        $email = trim($_POST["email"] ?? "");
        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "That username is already taken.";
            } else {
                $email = trim($_POST["email"] ?? "");
                if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                    $ins->bind_param("sss", $username, $hash, $email);
                    $ins->execute();
                    $ins->close();
                }
                $success = "Account created. You can now sign in.";
                $mode = "login";
            }
            $check->close();
        }
    } else {
        if ($username === "" || $password === "") {
            $error = "All fields are required.";
        } else {
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user["password"])) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $username;
                header("Location: index.php");
                exit;
            } else {
                $error = "Incorrect username or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow — <?= $mode === "register" ? "Register" : "Sign In" ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="auth-shell">
        <div class="auth-card">

            <div class="auth-brand">
                <span class="brand-icon">&#10003;</span>
                <span class="auth-brand-name">TaskFlow</span>
            </div>

            <div class="auth-tabs">
                <a href="login.php" class="auth-tab <?= $mode !== "register" ? "active" : "" ?>">Sign In</a>
                <a href="login.php?mode=register"
                    class="auth-tab <?= $mode === "register" ? "active" : "" ?>">Register</a>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-alert auth-alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="mode" value="<?= $mode === "register" ? "register" : "login" ?>">

                <div class="field-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter your username"
                        value="<?= htmlspecialchars($_POST["username"] ?? "") ?>" autocomplete="username" required>
                </div>

                <?php if ($mode === "register"): ?>
                    <div class="field-group">
                        <label for="email">Email address</label>
                        <input type="email" name="email" id="email" placeholder="you@example.com"
                            value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" autocomplete="email" required>
                    </div>
                <?php endif; ?>

                <div class="field-group">
                    <label for="password">Password
                        <?= $mode === "register" ? '<span class="auth-hint">(min. 6 characters)</span>' : "" ?></label>
                    <input type="password" name="password" id="password" placeholder="Enter your password"
                        autocomplete="<?= $mode === "register" ? "new-password" : "current-password" ?>" required>
                </div>

                <?php if ($mode === "register"): ?>
                    <div class="field-group">
                        <label for="email">Email <span class="auth-hint">(for due-date reminders — optional)</span></label>
                        <input type="email" name="email" id="email" placeholder="you@example.com"
                            value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" autocomplete="email">
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary auth-submit">
                    <?= $mode === "register" ? "Create Account" : "Sign In" ?>
                </button>
            </form>

        </div>
    </div>
</body>

</html>