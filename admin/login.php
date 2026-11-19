<?php

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $error = 'Enter a valid email and password.';
        } else {
            $statement = $pdo->prepare('SELECT id, email, password_hash, failed_attempts, locked_until FROM admin_users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            $admin = $statement->fetch();

            $isLocked = $admin && $admin['locked_until'] && strtotime($admin['locked_until']) > time();
            $ipStatement = $pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );
            $ipStatement->execute([$ipAddress]);
            $ipLocked = (int) $ipStatement->fetchColumn() >= 10;

            if ($isLocked || $ipLocked) {
                $error = 'Too many login attempts. Please try again later.';
            } elseif ($admin && password_verify($password, $admin['password_hash'])) {
                $update = $pdo->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?');
                $update->execute([$admin['id']]);

                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['last_activity'] = time();
                header('Location: dashboard.php');
                exit;
            } else {
                if ($admin) {
                    $attempts = (int) $admin['failed_attempts'] + 1;
                    $lockedUntil = null;

                    if ($attempts >= 5) {
                        $lockedUntil = date('Y-m-d H:i:s', time() + 900);
                    }

                    $update = $pdo->prepare('UPDATE admin_users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
                    $update->execute([$attempts, $lockedUntil, $admin['id']]);
                }

                $attempt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
                $attempt->execute([$email, $ipAddress]);

                $error = 'Incorrect email or password.';
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
    <form class="admin-login-card" method="post" action="login.php">
        <h1>Admin Login</h1>
        <?php if ($error !== ''): ?>
            <p class="admin-error"><?php echo escape_html($error); ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
        <label>Email
            <input type="email" name="email" maxlength="190" required autocomplete="username">
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" maxlength="255" required autocomplete="current-password">
        </label>
        <button type="submit" class="admin-button">Login</button>
    </form>
</main>
</body>
</html>
