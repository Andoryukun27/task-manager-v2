<?php require_once "auth.php";
requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div id="root"></div>

    <script>
        window.APP_DATA = { username: <?= json_encode($_SESSION["username"]) ?> };
    </script>

    <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="js/app.js"></script>

</body>

</html>